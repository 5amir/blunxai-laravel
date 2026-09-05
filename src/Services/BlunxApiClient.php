<?php

namespace Blunx\AI\Services;

use Illuminate\Support\Facades\Http;
use Blunx\AI\LLMException;

/**
 * HTTP client for the BlunxAI cloud API (/api/v1).
 *
 * API keys are sent via the X-Blunx-Key and X-Blunx-LLM-Key headers and are
 * never included in the request body. Non-SSE endpoints (insights, schema,
 * reference) return plain JSON; the /chat/* SSE endpoints are consumed
 * directly by BlunxApiController::stream().
 */
class BlunxApiClient
{
    protected string $baseUrl;
    protected array  $llmConfig;
    protected string $apiKey;
    protected string $llmApiKey;
    protected string $locale;
    protected array  $currency;
    protected string $db_connection;

    public function __construct()
    {
        $this->baseUrl      = rtrim(config('blunx.server_url'), '/');
        $this->apiKey       = config('blunx.api_key', '');
        $this->llmApiKey    = config('blunx.llm_api_key', '');
        $this->db_connection= config('blunx.db_connection', 'mysql');
        $this->locale       = config('blunx.locale', 'fr');
        $this->currency     = config('blunx.currency', []);
        $this->llmConfig    = [
            'driver'               => config('blunx.llm.driver', 'openai'),
            'endpoint'             => config('blunx.llm.endpoint', ''),
            // 'api_key' is removed from the body: it goes in the X-Blunx-LLM-Key header
            'model'                => config('blunx.llm.model', ''),
            'supports_json_format' => config('blunx.llm.supports_json_format', true),
        ];
    }

    /**
     * Base payload included in every request (database, locale, currency, LLM).
     */
    public function basePayload(): array
    {
        return [
            'db_connection' => $this->db_connection,
            'locale'        => $this->locale,
            'currency'      => $this->currency,
            'llm'           => $this->llmConfig,
        ];
    }

    /**
     * Send a non-SSE POST request and map HTTP errors to LLMException.
     */
    protected function post(string $endpoint, array $data): array
    {

        $response = Http::timeout((int) config('blunx.request_timeout', 600))
            ->withHeaders([
                'X-Blunx-Key'     => $this->apiKey,
                'X-Blunx-LLM-Key' => $this->llmApiKey,
            ])
            ->withOptions(
                ['verify' => true,  // certificate verification enabled
                 'http_errors' => false] // handle errors manually
            )
            ->post($this->baseUrl . $endpoint, array_merge($this->basePayload(), $data));

        if ($response->failed()) {
            $serverMessage = $response->json('message') ?? null;
            $code          = $response->status();

            $message = match ($code) {
                401 => $serverMessage ?? 'Invalid or expired API key',
                402 => $serverMessage ?? 'Billing inactive. Please check your subscription.',
                403 => $serverMessage ?? 'Application suspended. Contact support.',
                429 => $serverMessage ?? 'Quota exceeded. Please wait before retrying.',
                422 => $serverMessage ?? 'LLM configuration missing. Check your settings.',
                default => $serverMessage ?? 'AI service error. Please try again later.',
            };

            throw new LLMException($message, $code);
        }

        return $response->json() ?? [];
    }

    // ════════════════════════════════════════════════════════════════
    // INSIGHTS
    // ════════════════════════════════════════════════════════════════

    public function insightsAnalyze(
        string  $widgetTitle,
        string  $sqlQuery,
        array   $currentMeta,
        ?array  $previousMeta        = null,
        ?array  $metricWeights       = null,
    ): array {
        return $this->post('/api/v1/insights/analyze', [
            'widget_title'        => $widgetTitle,
            'sql_query'           => $sqlQuery,
            'current_meta'        => $currentMeta,
            'previous_meta'       => $previousMeta,
            'metric_weights'      => $metricWeights,
        ]);
    }

    public function insightsReport(
        array   $insightResult,
        array   $currentMeta,
        ?array  $previousMeta  = null,
        ?array  $lastInsight   = null,
        string  $widgetTitle   = '',
        string  $dashboardName = '',
        string  $reportFrequencency = 'weekly',
    ): string {
        $response = $this->post('/api/v1/insights/report', [
            'insight_result' => $insightResult,
            'current_meta'   => $currentMeta,
            'previous_meta'  => $previousMeta,
            'last_insight'   => $lastInsight,
            'widget_title'   => $widgetTitle,
            'dashboard_name' => $dashboardName,
            'report_frequency' => $reportFrequencency,
        ]);
        return $response['html'] ?? '';
    }

    // ════════════════════════════════════════════════════════════════
    // SCHEMA
    // ════════════════════════════════════════════════════════════════

    public function schemaEnrich(array $columns): array
    {
        $response = $this->post('/api/v1/schema/enrich', [
            'columns' => $columns,
        ]);
        return $response['columns'] ?? $columns;
    }

    // ════════════════════════════════════════════════════════════════
    // REFERENCE
    // ════════════════════════════════════════════════════════════════

    public function referenceGenerate(
        string $widgetTitle,
        string $widgetSql,
        array  $numericColumns = [],
        ?array $accessRules    = null,
    ): array {
        $payload = [
            'widget_title'    => $widgetTitle,
            'widget_sql'      => $widgetSql,
            'numeric_columns' => $numericColumns,
        ];
        if ($accessRules !== null) {
            $payload['access_rules'] = $accessRules;
        }
        $response = $this->post('/api/v1/reference/generate', $payload);
        return $response['queries'] ?? [];
    }

}