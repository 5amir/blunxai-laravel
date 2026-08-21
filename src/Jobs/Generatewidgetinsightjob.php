<?php

namespace Blunx\AI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Blunx\AI\Models\BlunxWidgetInsightSetting;
use Blunx\AI\Models\BlunxInsight;
use Blunx\AI\Models\BlunxDashboardWidget;
use Blunx\AI\Services\BlunxApiClient;
use Blunx\AI\Support\BlunxDatabase;
use Blunx\AI\Support\DataSummarizer;
use Blunx\AI\Support\InsightPdfGenerator;
use Blunx\AI\Support\ReferenceQueryExecutor;
use Blunx\AI\Support\SqlGuard;
use Blunx\AI\Mail\BlunxInsightMail;
use Blunx\AI\Http\Controllers\BlunxApiController;

/**
 * Queued job running the full insight pipeline for a widget.
 *
 * 1. Executes the widget SQL locally (read-only).
 * 2. Builds the dataset summary and reference values.
 * 3. Asks the cloud to analyze and score the data.
 * 4. Generates the PDF report and, if the priority threshold is met,
 *    sends the notification email.
 * 5. Schedules the next run.
 */
class GenerateWidgetInsightJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 5;
    public int    $timeout = 200;
    public string $jobUuid = '';

    public function __construct(
        public readonly int $settingId
    ) {
        $this->jobUuid = (string) Str::uuid();
    }

    public function handle(
        BlunxApiClient      $api,
        InsightPdfGenerator $pdfGenerator,
        BlunxApiController $chatService,
    ): void {
        $setting = BlunxWidgetInsightSetting::with('widget.dashboard')->find($this->settingId);

        if (!$setting || !$setting->widget) return;

        $widget    = $setting->widget;
        $dashboard = $widget->dashboard;

        if (!$dashboard) return;

        // ── 1. Execute the SQL locally ────────────────────────────
        try {
            $sql = trim($widget->sql_query);

            // Safety net: only run read-only SELECT/WITH queries.
            if (!SqlGuard::isReadOnly($sql)) {
                Log::warning('[Blunx Insights] Query blocked (not read-only)', [
                    'widget_id' => $widget->id,
                    'sql'       => $sql,
                ]);
                $this->updateNextRun($setting);
                return;
            }

            $currentRows = collect(BlunxDatabase::select($sql))
                ->map(fn($item) => (array) $item)
                ->toArray();

        } catch (\Throwable $e) {
            Log::error('[Blunx Insights] SQL error', [
                'widget_id' => $widget->id,
                'error'     => $e->getMessage(),
            ]);
            $this->updateNextRun($setting);
            return;
        }

        // ── 2. Build currentMeta ──────────────────────────────────
        $stats        = DataSummarizer::summarize($currentRows);
        $preview      = array_slice($currentRows, 0, 5);
        $concentration = DataSummarizer::computeConcentration($currentRows);

        $currentMeta = [
            'stats'         => $stats,
            'preview'       => $preview,
            'concentration' => $concentration,
        ];

        // ── 3. Reference queries (local storage) ──
        $refQueries = [];

        if (!empty($widget->reference_queries)) {
            $refQueries = is_array($widget->reference_queries)
                ? $widget->reference_queries
                : json_decode($widget->reference_queries, true);
        }

        // Role resolved via the Eloquent relation to the host `users` table;
        // outside an app context (standalone CLI) it may be absent → null role
        // (falls back to the `default` rules).
        $userRole = null;
        try {
            $userRole = $setting->user->role->name ?? null;
        } catch (\Throwable) {
            $userRole = null;
        }
        $userId = $setting->user_id;

        $accessRules = $chatService->getAccessRulesForUser($userRole, $userId);

        // Deferred generation via server when empty
        if (empty($refQueries) && !empty($stats['numerics'])) {
            try {
                $numericCols = array_keys($stats['numerics']);
                $refQueries  = $api->referenceGenerate($widget->title, $widget->sql_query, $numericCols, $accessRules);

                if (!empty($refQueries)) {
                    $widget->update(['reference_queries' => json_encode($refQueries)]);
                }
            } catch (\Throwable) {
                // Non blocking
            }
        }

        $refValues = ReferenceQueryExecutor::execute($refQueries);

        $currentMeta['ref_values'] = $refValues;

        // ── 4. Insight history ────────────────────────────────────
        $recentInsights = BlunxInsight::where('widget_id', $widget->id)
            ->where('user_id', $setting->user_id)
            ->whereNotNull('meta')
            ->orderBy('generated_at', 'desc')
            ->limit(5)
            ->get();

        $lastInsight  = $recentInsights->first();
        $previousMeta = null;

        if ($lastInsight && !empty($lastInsight->meta)) {
            $previousMeta = [
                'stats'         => $lastInsight->meta['stats'] ?? [],
                'preview'       => $lastInsight->meta['preview'] ?? [],
                'concentration' => $lastInsight->meta['concentration'] ?? [],
            ];
        }

        $history = $recentInsights
            ->filter(fn($i) => !empty($i->meta['stats']))
            ->map(fn($i) => [
                'stats'   => $i->meta['stats'] ?? [],
                'preview' => $i->meta['preview'] ?? [],
            ])
            ->values()
            ->toArray();

        $currentMeta['history'] = $history;

        // ── 5. Cloud call: InsightAgent ───────────────────────────
        try {
            $insightResult = $api->insightsAnalyze(
                widgetTitle   : $widget->title,
                sqlQuery      : $widget->sql_query,
                currentMeta   : $currentMeta,
                previousMeta  : $previousMeta,
                metricWeights : $setting->metric_weights ?? null,
            );

        } catch (\Throwable $e) {
            Log::error('[Blunx Insights] Error calling InsightAgent', [
                'widget_id' => $widget->id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            // Retryable failure: rethrow (Laravel queue worker → $tries,
            // or the RunInsightsCommand retry loop). No updateNextRun here:
            // once exhausted, `finalizeFailure()` advances the schedule.
            throw $e;
        }

        if (empty($insightResult)) {
            Log::warning('[Blunx Insights] InsightAgent returned empty result', ['widget_id' => $widget->id]);
            // Empty result: potentially transient (LLM) → retryable.
            throw new \RuntimeException('InsightAgent returned empty result');
        }

        // ── 6. Cloud call: InsightReportAgent ────────────────────
        $pdfBinary = null;
        $pdfBase64 = null;

        try {
            $reportHtml = $api->insightsReport(
                insightResult    : $insightResult,
                currentMeta      : $currentMeta,
                previousMeta     : $previousMeta,
                lastInsight      : $lastInsight?->toReportContext(),
                widgetTitle      : $widget->title,
                dashboardName    : $dashboard->name,
                reportFrequencency : $setting->frequency,
            );

            // ── Local PDF generation ──────────────────────────────
            // Rebuild an InsightResult from the array.
            $insightDto = new \Blunx\AI\DTO\InsightResult(
                type    : $insightResult['type']     ?? 'info',
                severity: $insightResult['severity'] ?? 'info',
                message : $insightResult['message']  ?? '',
                meta    : $insightResult['meta']     ?? [],
            );

            $pdfBinary = $pdfGenerator->generate(
                reportHtml   : $reportHtml,
                insightResult: $insightDto,
                widget       : $widget,
                dashboard    : $dashboard,
            );

            $pdfBase64 = base64_encode($pdfBinary);

            // Update the widget comment with the executive summary
            $executiveSummary = $this->extractExecutiveSummary($reportHtml);
            if (!empty($executiveSummary)) {
                $widget->update(['comment' => $executiveSummary]);
            }

        } catch (\Throwable $e) {
            Log::error('[Blunx Insights] Error generating PDF/Report', [
                'widget_id' => $widget->id,
                'error'     => $e->getMessage(),
            ]);
        }

        $meta    = array_merge($insightResult['meta'] ?? [], $currentMeta);
        $insight = BlunxInsight::create([
            'user_id'      => $setting->user_id,
            'dashboard_id' => $dashboard->id,
            'widget_id'    => $widget->id,
            'type'         => $insightResult['type']     ?? 'info',
            'severity'     => $insightResult['severity'] ?? 'info',
            'message'      => $insightResult['message']  ?? '',
            'meta'         => $meta,
            'is_read'      => false,
            'generated_at' => now(),
            'report_pdf'   => $pdfBase64,
        ]);

        // ── 8. Notification conditionnelle ────────────────────────
        $priorityLevel   = $insight->meta['priority_level'] ?? 'low';
        $notifyThreshold = $setting->notify_threshold       ?? 'medium';

        $priorityRank = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];
        $insightRank  = $priorityRank[$priorityLevel]   ?? 0;
        $threshold    = $priorityRank[$notifyThreshold] ?? 1;
        $shouldNotify = $insightRank >= $threshold;

        if ($setting->notify_email && $setting->email && $pdfBinary && $shouldNotify) {
            try {
                Mail::to($setting->email)->send(
                    new BlunxInsightMail(
                        insight    : $insight,
                        widget     : $widget,
                        dashboard  : $dashboard,
                        pdfContent : $pdfBinary,
                        generatedAt: $insight->generated_at,
                    )
                );
            } catch (\Throwable $e) {
                Log::error('[Blunx Insights] Error sending mail', [
                    'email' => $setting->email,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            
        }

        // ── 9. Planifier le prochain run ──────────────────────────
        $this->updateNextRun($setting);
    }

    /**
     * First paragraph following the first <h2> of the report.
     */
    protected function extractExecutiveSummary(string $reportHtml): string
    {
        // on cherche le premier bloc apres le premier <h2>...</h2>
        if (preg_match('/<h2[^>]*>.*?<\/h2>\s*<p>(.*?)<\/p>/is', $reportHtml, $matches)) {
            $text = strip_tags($matches[1]);
            $text = preg_replace('/\s+/', ' ', $text);
            return trim($text);
        }
        return '';
    }

    /**
     * Called after all attempts are exhausted: retries the job 30 minutes
     * later instead of waiting for the next regular slot.
     *
     * last_run_at is intentionally not updated (the job did not succeed).
     */
    public function finalizeFailure(?BlunxWidgetInsightSetting $setting = null): void
    {
        try {
            $setting = $setting ?? BlunxWidgetInsightSetting::find($this->settingId);
            if ($setting) {
                $this->retryLater($setting, now()->addMinutes(30));
            }
        } catch (\Throwable $e) {
            Log::error('[Blunx Insights] finalizeFailure error', [
                'setting_id' => $this->settingId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Schedule the job at a given time, without touching last_run_at.
     * Used by finalizeFailure to retry 30 minutes later.
     */
    protected function retryLater(BlunxWidgetInsightSetting $setting, \DateTimeInterface $nextRunAt): void
    {
        $nextJob = new self($setting->id);
        $newUuid = $nextJob->jobUuid;
        dispatch($nextJob)->delay($nextRunAt);

        $setting->update([
            'next_run_at' => $nextRunAt,
            'job_uuid'    => $newUuid,
        ]);
    }

    /**
     * Schedule the next run on success: update last_run_at/next_run_at and
     * dispatch the follow-up job.
     */
    protected function updateNextRun(BlunxWidgetInsightSetting $setting): void
    {
        $nextJob = new self($setting->id);
        $newUuid = $nextJob->jobUuid;
        dispatch($nextJob)->delay($setting->computeNextRunAt());

        $setting->update([
            'last_run_at' => now(),
            'next_run_at' => $setting->computeNextRunAt(),
            'job_uuid'    => $newUuid,
        ]);
    }
}