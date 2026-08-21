<?php

namespace Blunx\AI\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Blunx\AI\Models\BlunxFeedback;

/**
 * blunx:feedback — review unresolved user feedback.
 *
 * Displays each pending feedback (question, intent, answer, user remark,
 * rendered interface) and lets the operator mark them as resolved.
 */
class FeedbackCommand extends Command
{
    protected $signature   = 'blunx:feedback';
    protected $description = 'Review unresolved user feedbacks';

    public function handle(): void
    {
        $feedbacks = BlunxFeedback::with(['userMessage', 'modelMessage'])
            ->whereNull('resolved_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('🧠 <info>Blunx AI — User Feedbacks</info>');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        if ($feedbacks->isEmpty()) {
            $this->info('✅ No pending feedbacks.');
            $this->line('');
            return;
        }

        $this->warn("⚠️  {$feedbacks->count()} unresolved feedback(s)");
        $this->line('');

        foreach ($feedbacks as $i => $fb) {
            $num      = $i + 1;
            $userMsg  = $fb->userMessage;
            $modelMsg = $fb->modelMessage;
            $meta     = is_array($modelMsg->meta_data)
                            ? $modelMsg->meta_data
                            : json_decode($modelMsg->meta_data, true);
            $synth    = $meta['Synthesizer_Agent'] ?? null;

            $this->line("── Feedback #{$num} ─────────────────────────────────────────");
            $this->line("  <info>ID</info>             : #{$fb->id}");
            $this->line("  <info>Date</info>           : {$fb->created_at->format('Y-m-d H:i')}");
            $this->line("  <info>User question</info>  : {$userMsg?->content}");
            $this->line("  <info>Intent</info>         : {$modelMsg?->intent}");
            $this->line("  <info>Blunx response</info> : {$modelMsg?->content}");

            if (!empty($fb->comment)) {
                $this->line("  <comment>User remark</comment>    : {$fb->comment}");
            }

            if ($synth) {
                $this->line('');
                $this->line("  <info>── Synthesizer (full meta_data) ──</info>");

                if (!empty($synth['title'])) {
                    $this->line("  Title   : {$synth['title']}");
                }

                if (!empty($synth['layout'])) {
                    foreach ($synth['layout'] as $j => $block) {
                        $type = $block['type'] ?? '?';
                        $this->line("  Block " . ($j + 1) . " [{$type}]");

                        if ($type === 'paragraph' && !empty($block['content'])) {
                            $this->line("    " . Str::limit(strip_tags($block['content']), 120));
                        }

                        if (in_array($type, ['chart', 'table'])) {
                            if (!empty($block['title']))     $this->line("    Title   : {$block['title']}");
                            if (!empty($block['comment']))   $this->line("    Comment : {$block['comment']}");
                            if (!empty($block['sql_query'])) $this->line("    SQL     : {$block['sql_query']}");
                        }
                    }
                }

                if (!empty($synth['Resume'])) {
                    $this->line("  Resume  : {$synth['Resume']}");
                }
            }

            $this->line('');
        }

        $this->line('────────────────────────────────────────────────────────────');
        $this->line('');
        $this->info('💡 Fix the relevant descriptions via: <comment>php artisan blunx:edit</comment>');
        $this->line('');

        if (!$this->confirm('Mark all these feedbacks as resolved?', false)) {
            return;
        }

        BlunxFeedback::whereNull('resolved_at')->update(['resolved_at' => now()]);
        $this->info('✅ Feedbacks marked as resolved.');
        $this->line('');
    }
}