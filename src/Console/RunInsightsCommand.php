<?php

namespace Blunx\AI\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Blunx\AI\Jobs\GenerateWidgetInsightJob;
use Blunx\AI\Models\BlunxWidgetInsightSetting;
use Blunx\AI\Services\BlunxApiClient;
use Blunx\AI\Support\InsightPdfGenerator;
use Blunx\AI\Http\Controllers\BlunxApiController;

/**
 * blunx:run-insights — run due insight jobs immediately.
 *
 * Finds settings whose next_run_at is due (or NULL) and executes the insight
 * job with retries (tries=5, 1s backoff), like the queue worker. In
 * production the hourly scheduler handles this automatically; this command
 * is for dedicated crons or manual triggers.
 */
class RunInsightsCommand extends Command
{
    protected $signature   = 'blunx:run-insights';
    protected $description = 'Run due insight jobs now (equivalent of the hourly scheduler)';

    private ?BlunxApiClient $api = null;
    private ?InsightPdfGenerator $pdfGenerator = null;
    private ?BlunxApiController $chatService = null;

    public function __construct(
        ?BlunxApiClient $api = null,
        ?InsightPdfGenerator $pdfGenerator = null,
        ?BlunxApiController $chatService = null,
    ) {
        parent::__construct();
        $this->api = $api;
        $this->pdfGenerator = $pdfGenerator;
        $this->chatService = $chatService;
    }

    public function handle(): int
    {
        $api = $this->api ?? app(BlunxApiClient::class);
        $pdfGenerator = $this->pdfGenerator ?? app(InsightPdfGenerator::class);
        $chatService = $this->chatService ?? app(BlunxApiController::class);

        $settings = BlunxWidgetInsightSetting::due()->with('widget')->get();
        $dispatched = 0;

        foreach ($settings as $setting) {
            $job          = new GenerateWidgetInsightJob($setting->id);
            $job->jobUuid = (string) Str::uuid();
            $maxTries     = max(1, $job->tries ?: 1);
            $attempts     = 0;

            while (true) {
                $attempts++;
                try {
                    $job->handle($api, $pdfGenerator, $chatService);
                    break; // success
                } catch (\Throwable $e) {
                    Log::warning('[Blunx Insights] Job failed, retrying', [
                        'setting_id' => $setting->id,
                        'attempt'    => $attempts,
                        'max_tries'  => $maxTries,
                        'error'      => $e->getMessage(),
                    ]);
                    if ($attempts >= $maxTries) {
                        Log::error('[Blunx Insights] Job failed after max attempts', [
                            'setting_id' => $setting->id,
                            'attempts'   => $attempts,
                            'max_tries'  => $maxTries,
                        ]);
                        // Exhausted: advance the schedule (safety net) so the
                        // setting is not stuck "due" in an infinite loop.
                        $job->finalizeFailure($setting);
                        break;
                    }
                    usleep(1000000); // 1s backoff (deterministic for tests)
                }
            }
            $dispatched++;
        }

        $this->line('');
        $this->line('🧠 Blunx AI — Run insights');
        $this->line('');
        if ($dispatched === 0) {
            $this->info('✅ No due insights. Nothing to run.');
        } else {
            $this->warn("🚀 {$dispatched} insight job(s) dispatched.");
        }
        $this->line('');

        return 0;
    }
}
