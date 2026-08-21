<?php

namespace Blunx\AI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Blunx\AI\Agents\DataExecutorAgent;
use Blunx\AI\Support\BlunxCache;
use Blunx\AI\DTO\QueryResult;
use Blunx\AI\Models\BlunxConversation;
use Blunx\AI\Models\BlunxDashboard;
use Blunx\AI\Models\BlunxDashboardWidget;
use Blunx\AI\Models\BlunxFeedback;
use Blunx\AI\Models\BlunxInsight;
use Blunx\AI\Models\BlunxMessage;
use Blunx\AI\Models\BlunxWidgetInsightSetting;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REST API controller for Blunx AI.
 *
 * Exposes the authenticated /api/blunx endpoints (conversations, messages,
 * dashboards, widgets, insights, feedback) and the SSE streaming pipeline
 * that proxies the conversation flow to the Blunx cloud.
 */
class BlunxApiController extends Controller
{
    protected DataExecutorAgent $executor;

    public function __construct(DataExecutorAgent $executor)
    {
        $this->executor = $executor;
    }

    /**
     * ID of the currently authenticated user.
     */
    protected function userId(): int
    {
        return (int) Auth::id();
    }

    // ════════════════════════════════════════════════════════════════
    // USER
    // ════════════════════════════════════════════════════════════════

    public function user(): JsonResponse
    {
        $user = Auth::user();
        return response()->json([
            'name'   => $user->name,
            'email'  => $user->email,
            'locale' => config('blunx.locale', 'fr'),
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    // CONVERSATIONS
    // ════════════════════════════════════════════════════════════════

    public function conversations(): JsonResponse
    {
        $convs = BlunxConversation::where('user_id', $this->userId())
            ->with('lastMessage')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->toArray();

        return response()->json(['data' => $convs]);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
        ]);

        $conv = BlunxConversation::create([
            'user_id' => $this->userId(),
            'title'   => $data['title'] ?? now()->format('H:i'),
        ]);

        return response()->json(['data' => $conv->toArray()], 201);
    }

    public function deleteConversation(string $uuid): JsonResponse
    {
        $conv = BlunxConversation::where('user_id', $this->userId())->where('uuid', $uuid)->first();
        if (!$conv) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }
        $conv->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // ════════════════════════════════════════════════════════════════
    // MESSAGES
    // ════════════════════════════════════════════════════════════════

    public function messages(string $convUuid): JsonResponse
    {
        $conv = BlunxConversation::where('user_id', $this->userId())->where('uuid', $convUuid)->first();
        if (!$conv) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }

        $messages = BlunxMessage::where('conversation_id', $conv->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();

        return response()->json(['data' => $messages]);
    }

    public function saveMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conversation_uuid' => 'nullable|string',
            'role'              => 'required|in:user,assistant',
            'content'           => 'required|string|max:50000',
            'intent'            => 'nullable|string|max:255',
            'meta_data'         => 'nullable|array',
        ]);

        $conv = null;
        if (!empty($data['conversation_uuid'])) {
            $conv = BlunxConversation::where('user_id', $this->userId())->where('uuid', $data['conversation_uuid'])->first();
        }

        if (!$conv) {
            $conv = BlunxConversation::create([
                'user_id' => $this->userId(),
                'title'   => mb_substr($data['content'], 0, 40),
            ]);
        }

        $msg = BlunxMessage::create([
            'conversation_id' => $conv->id,
            'role'            => $data['role'],
            'content'         => $data['content'],
            'intent'          => $data['intent'] ?? '',
            'meta_data'       => isset($data['meta_data']) ? json_encode($data['meta_data']) : null,
        ]);

        $conv->touch();

        return response()->json([
            'data'              => $msg->toArray(),
            'conversation_uuid' => $conv->uuid,
        ], 201);
    }

    public function deleteMessage(string $uuid): JsonResponse
    {
        $msg = BlunxMessage::where('uuid', $uuid)->first();
        if (!$msg) {
            return response()->json(['message' => 'Message not found'], 404);
        }

        $conv = BlunxConversation::where('user_id', $this->userId())->find($msg->conversation_id);
        if (!$conv) {
            return response()->json(['message' => 'Message not found'], 404);
        }

        $msg->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function executeSql(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message_uuid'  => 'required|string',
            'element_index' => 'required|integer|min:0',
        ]);

        // Load the message and verify ownership.
        $msg = BlunxMessage::where('uuid', $data['message_uuid'])->first();
        if (!$msg) {
            return response()->json(['message' => 'Message not found'], 404);
        }

        $conv = BlunxConversation::where('user_id', $this->userId())->find($msg->conversation_id);
        if (!$conv) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Extract the SQL query from the stored layout (Synthesizer_Agent)
        $meta    = is_array($msg->meta_data) ? $msg->meta_data : json_decode($msg->meta_data, true);
        $layout  = $meta['Synthesizer_Agent']['layout'] ?? [];
        $element = $layout[$data['element_index']] ?? null;

        if (!$element || empty($element['sql_query'])) {
            return response()->json(['message' => 'Element not found'], 404);
        }

        $uiHint     = $element['type'] === 'table' ? 'table' : 'chart';
        $queryResult = new QueryResult([$element['sql_query']], [], [$uiHint]);
        $execution   = $this->executor->execute($queryResult, previewOnly: false);

        return response()->json(['data' => $execution->toArray()]);
    }

    public function executeWidget(string $widgetUuid): JsonResponse
    {
        $widget = BlunxDashboardWidget::where('uuid', $widgetUuid)->first();
        if (!$widget) {
            return response()->json(['message' => 'Widget not found'], 404);
        }

        $dashboard = BlunxDashboard::where('user_id', $this->userId())->find($widget->dashboard_id);
        if (!$dashboard) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $uiHint     = $widget->type === 'chart' ? ['chart'] : ['table'];
        $queryResult = new QueryResult([$widget->sql_query], [], $uiHint);
        $execution   = $this->executor->execute($queryResult, previewOnly: false);

        return response()->json(['data' => $execution->toArray()]);
    }

    // ════════════════════════════════════════════════════════════════
    // DASHBOARDS & WIDGETS
    // ════════════════════════════════════════════════════════════════

    public function dashboards(): JsonResponse
    {
        $dashboards = BlunxDashboard::where('user_id', $this->userId())
            ->with('widgets')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        return response()->json(['data' => $dashboards]);
    }

    public function createDashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $dashboard = BlunxDashboard::create([
            'user_id'     => $this->userId(),
            'name'        => $data['name'],
            'description' => $data['description'] ?? '',
        ]);

        return response()->json(['data' => $dashboard->toArray()], 201);
    }

    public function showDashboard(string $uuid): JsonResponse
    {
        $dashboard = BlunxDashboard::where('uuid', $uuid)
            ->where('user_id', $this->userId())
            ->with('widgets')
            ->first();

        if (!$dashboard) {
            return response()->json(['message' => 'Dashboard not found'], 404);
        }

        return response()->json(['data' => $dashboard->toArray()]);
    }

    public function deleteDashboard(string $uuid): JsonResponse
    {
        $dashboard = BlunxDashboard::where('user_id', $this->userId())->where('uuid', $uuid)->first();
        if (!$dashboard) {
            return response()->json(['message' => 'Dashboard not found'], 404);
        }
        $dashboard->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function saveWidget(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dashboard_uuid' => 'required|string|exists:blunx_dashboards,uuid',
            'title'          => 'required|string|max:255',
            'type'           => 'required|in:chart,table',
            'chart_type'     => 'nullable|string|max:50',
            'sql_query'      => 'required|string|max:10000',
            'ui_hint'        => 'nullable|string',
            'label_column'   => 'nullable|string|max:255',
            'data_columns'   => 'nullable|array',
            'comment'        => 'nullable|string|max:5000',
        ]);

        $dashboard = BlunxDashboard::where('user_id', $this->userId())->where('uuid', $data['dashboard_uuid'])->first();
        if (!$dashboard) {
            return response()->json(['message' => 'Dashboard not found'], 404);
        }

        if ($dashboard->widgets()->where('sql_query', $data['sql_query'])->exists()) {
            return response()->json(['message' => 'A widget with this SQL query already exists in the dashboard'], 422);
        }

        $referenceQueries = [];
        try {
            $refApi = app(\Blunx\AI\Services\BlunxApiClient::class);
            $referenceQueries = $refApi->referenceGenerate(
                widgetTitle: $data['title'],
                widgetSql: $data['sql_query'],
            );
        } catch (\Throwable $e) {
            Log::warning('[saveWidget] referenceGenerate failed', ['error' => $e->getMessage()]);
        }

        $widget = BlunxDashboardWidget::create([
            'dashboard_id'      => $dashboard->id,
            'title'             => $data['title'],
            'type'              => $data['type'],
            'chart_type'        => $data['chart_type'] ?? null,
            'sql_query'         => $data['sql_query'],
            'ui_hint'           => $data['ui_hint'] ?? 'table',
            'label_column'      => $data['label_column'] ?? null,
            'data_columns'      => $data['data_columns'] ?? [],
            'comment'           => $data['comment'] ?? null,
            'reference_queries' => empty($referenceQueries) ? null : json_encode($referenceQueries),
        ]);

        return response()->json(['data' => $widget->toArray()], 201);
    }

    public function updateWidget(string $widgetUuid, Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'      => 'nullable|string|max:255',
            'comment'    => 'nullable|string',
            'chart_type' => 'nullable|string|max:50',
        ]);

        $widget = BlunxDashboardWidget::where('uuid', $widgetUuid)->first();
        if (!$widget) {
            return response()->json(['message' => 'Widget not found'], 404);
        }

        $dashboard = BlunxDashboard::where('user_id', $this->userId())->find($widget->dashboard_id);
        if (!$dashboard) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (isset($data['title'])) {
            $title = trim($data['title']);
            if (empty($title)) {
                return response()->json(['message' => 'Title cannot be empty'], 422);
            }
            if (strlen($title) > 100) {
                return response()->json(['message' => 'Title too long (max 100 characters)'], 422);
            }
            $widget->title = $title;
        }

        if (isset($data['comment'])) {
            $widget->comment = $data['comment'];
        }

        if (isset($data['chart_type'])) {
            $widget->chart_type = $data['chart_type'];
        }

        $widget->save();

        return response()->json(['data' => $widget->fresh()->toArray()]);
    }

    public function deleteWidget(string $widgetUuid): JsonResponse
    {
        $widget = BlunxDashboardWidget::where('uuid', $widgetUuid)->first();
        if (!$widget) {
            return response()->json(['message' => 'Widget not found'], 404);
        }

        $dashboard = BlunxDashboard::where('user_id', $this->userId())->find($widget->dashboard_id);
        if (!$dashboard) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $widget->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // ════════════════════════════════════════════════════════════════
    // INSIGHTS
    // ════════════════════════════════════════════════════════════════

    public function insights(string $dashUuid): JsonResponse
    {
        $dashboard = BlunxDashboard::where('user_id', $this->userId())->where('uuid', $dashUuid)->first();
        if (!$dashboard) {
            return response()->json(['message' => 'Dashboard not found'], 404);
        }

        $insights = BlunxInsight::where('user_id', $this->userId())
            ->where('dashboard_id', $dashboard->id)
            ->orderBy('generated_at', 'desc')
            ->get()
            ->map(function ($insight) {
                $data = $insight->toArray();
                $data['widget_uuid'] = $insight->widget?->uuid;
                $data['dashboard_uuid'] = $insight->dashboard?->uuid;
                unset($data['widget_id'], $data['dashboard_id'], $data['user_id']);
                return $data;
            });

        return response()->json(['data' => $insights]);
    }

    public function getInsightSettings(string $dashUuid): JsonResponse
    {
        $dashboard = BlunxDashboard::where('user_id', $this->userId())->where('uuid', $dashUuid)->first();
        if (!$dashboard) {
            return response()->json(['message' => 'Dashboard not found'], 404);
        }

        $settings = BlunxWidgetInsightSetting::where('user_id', $this->userId())
            ->whereIn('widget_id', $dashboard->widgets->pluck('id'))
            ->get()
            ->map(function ($setting) {
                $data = $setting->toArray();
                $data['uuid'] = $setting->widget?->uuid;
                unset($data['widget_id'], $data['user_id']);
                return $data;
            });

        return response()->json(['data' => $settings]);
    }

    public function deleteInsight(string $uuid): JsonResponse
    {
        $insight = BlunxInsight::where('uuid', $uuid)->first();
        if (!$insight) {
            return response()->json(['message' => 'Insight not found'], 404);
        }

        $dashboard = BlunxDashboard::where('user_id', $this->userId())->find($insight->dashboard_id);
        if (!$dashboard) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $insight->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function markInsightRead(string $uuid): JsonResponse
    {
        $insight = BlunxInsight::where('uuid', $uuid)->first();
        if (!$insight) {
            return response()->json(['message' => 'Insight not found'], 404);
        }

        $dashboard = BlunxDashboard::where('user_id', $this->userId())->find($insight->dashboard_id);
        if (!$dashboard) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $insight->update(['is_read' => true]);

        return response()->json(['message' => 'Marked as read']);
    }

    public function downloadReport(Request $request, string $uuid)
    {
        $insight = BlunxInsight::where('uuid', $uuid)->first();
        if (!$insight || !$insight->report_pdf) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $dashboard = BlunxDashboard::where('user_id', $this->userId())->find($insight->dashboard_id);
        if (!$dashboard) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $pdfBinary = base64_decode($insight->report_pdf);
        $filename = preg_replace('/[^a-zA-Z0-9._\-\p{L}]/u', '', $request->query('filename', 'rapport-insight-' . $uuid . '.pdf'));

        return response()->streamDownload(
            fn () => print($pdfBinary),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function saveInsightSetting(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid'      => 'required|string',
            'frequency'        => 'required|in:daily,weekly,monthly',
            'notify_email'     => 'nullable|boolean',
            'email'            => 'nullable|email',
            'notify_threshold' => 'nullable|string|in:low,medium,high,critical',
            'metric_weights'   => 'nullable|array',
        ]);

        $widget = BlunxDashboardWidget::where('uuid', $data['uuid'])->first();
        if (!$widget) {
            return response()->json(['message' => 'Widget not found'], 404);
        }

        $dashboard = BlunxDashboard::where('user_id', $this->userId())->find($widget->dashboard_id);
        if (!$dashboard) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rawWeights = $data['metric_weights'] ?? [];
        $defaultWeights = ['impact' => 0.35, 'risk' => 0.30, 'dependency' => 0.20, 'correlation' => 0.08, 'trend' => 0.07];
        $weights = array_merge($defaultWeights, $rawWeights);

        foreach ($weights as $key => $value) {
            $weights[$key] = max(0.0, (float) $value);
        }

        $wSum = array_sum($weights);
        $normalizedWeights = $wSum > 0
            ? array_map(fn($v) => round($v / $wSum, 4), $weights)
            : $defaultWeights;

        $isNew = !BlunxWidgetInsightSetting::where('user_id', $this->userId())
            ->where('widget_id', $widget->id)->exists();

        $setting = BlunxWidgetInsightSetting::updateOrCreate(
            [
                'user_id'   => $this->userId(),
                'widget_id' => $widget->id,
            ],
            [
                'frequency'       => $data['frequency'],
                'notify_email'    => $data['notify_email'] ?? false,
                'email'           => $data['email'] ?? null,
                'notify_threshold' => $data['notify_threshold'] ?? 'medium',
                'metric_weights'  => $normalizedWeights,
                'next_run_at'     => now(),
            ]
        );

        if ($isNew) {
            try {
                $job = new \Blunx\AI\Jobs\GenerateWidgetInsightJob($setting->id);
                dispatch($job);
                $setting->update(['job_uuid' => $job->jobUuid]);
            } catch (\Throwable $e) {
                Log::warning('[saveInsightSetting] Job dispatch failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json(['data' => $setting->fresh()->toArray()], 201);
    }

    public function cancelInsightJob(string $widgetUuid): JsonResponse
    {
        $widget = BlunxDashboardWidget::where('uuid', $widgetUuid)->first();
        if (!$widget) {
            return response()->json(['message' => 'Widget not found'], 404);
        }

        $setting = BlunxWidgetInsightSetting::where('user_id', $this->userId())
            ->where('widget_id', $widget->id)
            ->first();

        if (!$setting) {
            return response()->json(['message' => 'No active insight setting found - already unfollowed']);
        }

        if ($setting->job_uuid) {
            try {
                DB::table('jobs')
                    ->where('payload', 'like', '%' . $setting->job_uuid . '%')
                    ->delete();
            } catch (\Throwable $e) {
                Log::warning('[cancelInsightJob] Job cancellation failed', ['error' => $e->getMessage()]);
            }
        }

        $setting->delete();

        return response()->json(['message' => 'Insight job cancelled and setting removed']);
    }

    // ════════════════════════════════════════════════════════════════
    // FEEDBACK
    // ════════════════════════════════════════════════════════════════

    public function saveFeedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'model_message_uuid' => 'required|string',
            'user_message_uuid'  => 'nullable|string',
            'comment'            => 'nullable|string|max:1000',
        ]);

        $modelMsg = BlunxMessage::where('uuid', $data['model_message_uuid'])->first();
        if (!$modelMsg) {
            return response()->json(['message' => 'Message not found'], 404);
        }

        $conv = BlunxConversation::where('user_id', $this->userId())->find($modelMsg->conversation_id);
        if (!$conv) {
            return response()->json(['message' => 'Message not found'], 404);
        }

        $userMsgId = null;
        if (!empty($data['user_message_uuid'])) {
            $userMsg = BlunxMessage::where('uuid', $data['user_message_uuid'])->first();
            $userMsgId = $userMsg?->id;
        } else {
            $userMsg = BlunxMessage::where('conversation_id', $modelMsg->conversation_id)
                ->where('role', 'user')
                ->where('id', '<', $modelMsg->id)
                ->orderBy('id', 'desc')
                ->first();
            $userMsgId = $userMsg?->id;
        }

        $feedback = BlunxFeedback::create([
            'user_message_id'  => $userMsgId,
            'model_message_id' => $modelMsg->id,
            'comment'          => $data['comment'] ?? null,
        ]);

        return response()->json(['data' => $feedback->toArray()], 201);
    }

    // ════════════════════════════════════════════════════════════════
    // SSE PIPELINE — secure proxy to the Blunx cloud
    // ════════════════════════════════════════════════════════════════

    /**
     * Stream the full conversation pipeline (SSE):
     * natural language → AI SQL generation → local execution → validation →
     * re-execution → AI synthesis → final response.
     */
    public function stream(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'conversation_uuid' => 'required|string',
            'message'           => 'required|string',
        ]);

        $conversationUuid = $data['conversation_uuid'];
        $message          = $data['message'];

        return new StreamedResponse(function () use ($conversationUuid, $message) {

            $this->disableBuffering();

            if (!Auth::check()) {
                $this->sendSse('error', ['message' => 'User not authenticated']);
                return;
            }

            $conv = BlunxConversation::where('user_id', Auth::id())->where('uuid', $conversationUuid)->first();
            if (!$conv) {
                $this->sendSse('error', ['message' => 'Conversation not found']);
                return;
            }

            $conversationId = $conv->id;

            try {
                $userRole     = Auth::user()?->getUserRoleName();
                $accessRules  = $this->getAccessRulesForUser($userRole, Auth::id());
                if ($accessRules === null) {
                    $this->sendSse('error', ['message' => 'Access rules not resolved']);
                    return;
                }

                $history = $this->getConversationHistory($conversationId);
                $schemaId = BlunxCache::get('blunx_schema_id');

                $basePayload = function () {
                    $llm = config('blunx.llm');
                    // Remove api_key from the LLM body (sent via the X-Blunx-LLM-Key header)
                    if (is_array($llm)) {
                        unset($llm['api_key']);
                    }
                    return [
                        'db_connection' => config('blunx.db_connection'),
                        'locale'        => config('blunx.locale', 'fr'),
                        'currency'      => config('blunx.currency', []),
                        'llm'           => $llm,
                    ];
                };

                $makeQueriesPayload = function ($useSchemaId) use ($message, $history, $accessRules) {
                    $payload = [
                        'question'             => $message,
                        'conversation_history' => $history,
                        'access_rules'         => $accessRules,
                    ];
                    if ($useSchemaId) {
                        $payload['schema_id'] = $useSchemaId;
                    } else {
                        $schema  = $this->loadSchemaConfig();
                        $payload['schema'] = $schema;
                    }
                    return $payload;
                };

                $queriesResult  = null;
                $terminalResult = null;

                $handleResult = function (array $d) use (&$queriesResult, &$terminalResult, &$schemaId) {
                    if (isset($d['schema_id'])) {
                        $schemaId = $d['schema_id'];
                        BlunxCache::put('blunx_schema_id', $d['schema_id'], 48 * 3600);
                    }
                    if (!empty($d['needs_clarification']) || (isset($d['needs_data']) && $d['needs_data'] === false) || !empty($d['access_denied'])) {
                        $terminalResult = $d;
                        return;
                    }
                    $queriesResult = $d;
                };

                $serverUrl = rtrim(config('blunx.server_url'), '/');

                $queriesResult  = null;
                $terminalResult = null;

                $error = $this->consumeSse(
                    url    : $serverUrl . '/api/v1/chat/queries',
                    payload: array_merge($basePayload(), $makeQueriesPayload($schemaId)),
                    onStep : fn(array $d) => $this->sendSse('step', $d),
                    onResult: $handleResult,
                );

                if ($error) {
                    if (str_contains($error, 'SCHEMA_NOT_FOUND') && $schemaId !== null) {
                        BlunxCache::forget('blunx_schema_id');
                        $schemaId = null;
                        $error = $this->consumeSse(
                            url    : $serverUrl . '/api/v1/chat/queries',
                            payload: array_merge($basePayload(), $makeQueriesPayload(null)),
                            onStep : fn(array $d) => $this->sendSse('step', $d),
                            onResult: $handleResult,
                        );
                    }
                    if ($error) {
                        $this->sendSse('error', ['message' => $error]);
                        return;
                    }
                }

                if ($terminalResult !== null) {
                    $terminalIntent = ($terminalResult['intent'] ?? '') . (!empty($terminalResult['ambiguities']) ? ' | ' . $terminalResult['ambiguities'] : '');

                    if (!empty($terminalResult['needs_clarification'])) {
                        $content = $terminalResult['clarification_question'] ?? '';
                        $this->saveAssistantMessage($conversationId, $content, $terminalIntent);
                        $this->sendSse('done', ['result' => $content]);
                        return;
                    }
                    if (!empty($terminalResult['access_denied'])) {
                        $content = $terminalResult['message'] ?? 'Access denied.';
                        $this->saveAssistantMessage($conversationId, $content, $terminalIntent);
                        $this->sendSse('done', ['result' => $content]);
                        return;
                    }
                    if (isset($terminalResult['needs_data']) && $terminalResult['needs_data'] === false) {
                        $synthResult = null;
                        $error = $this->consumeSse(
                            url    : $serverUrl . '/api/v1/chat/synthesize',
                            payload: array_merge($basePayload(), [
                                'execution_results'    => [],
                                'conversation_history' => $history,
                                'normalized_question'  => $terminalResult['normalized_question'] ?? $message,
                                'needs_data'           => false,
                            ]),
                            onStep : fn(array $d) => $this->sendSse('step', $d),
                            onResult: function (array $d) use (&$synthResult) { $synthResult = $d; },
                        );
                        if ($error) {
                            $this->sendSse('error', ['message' => $error]);
                            return;
                        }
                        $content = $synthResult['Resume'] ?? 'Data unavailable.';
                        $this->saveAssistantMessage($conversationId, (string) $content, $terminalIntent);
                        $this->sendSse('done', ['result' => $synthResult]);
                        return;
                    }
                }

                if (!$queriesResult) {
                    $this->sendSse('error', ['message' => 'No queries results received from server.']);
                    return;
                }

                $normQ  = $queriesResult['normalized_question'] ?? $message;
                $intent = $queriesResult['intent'] ?? '';
                $queries = $queriesResult['queries'] ?? [];

                $this->sendSse('step', ['step' => 4, 'total' => 6, 'status' => 'start', 'duration' => 0]);

                $queryResult1 = new QueryResult($queries);
                $execution1   = $this->executor->execute($queryResult1);

                $this->sendSse('step', ['step' => 4, 'total' => 6, 'status' => 'done', 'duration' => 0]);

                $validateResult = null;

                $error = $this->consumeSse(
                    url    : $serverUrl . '/api/v1/chat/validate',
                    payload: array_merge($basePayload(), [
                        'normalized_question' => $normQ,
                        'intent'              => $intent,
                        'execution_results'   => $execution1->toArray(),
                        'filtered_schema_id'           => $queriesResult['filtered_schema_id'] ?? null,
                        'access_rules'        => $accessRules,
                    ]),
                    onStep : fn(array $d) => $this->sendSse('step', $d),
                    onResult: function (array $d) use (&$validateResult) { $validateResult = $d; },
                );

                if ($error) {
                    $this->sendSse('error', ['message' => $error]);
                    return;
                }

                $queries2     = $validateResult['queries']  ?? $queries;
                $queryResult2 = new QueryResult($queries2);
                $execution2   = $this->executor->execute($queryResult2);

                $finalResult = null;

                $error = $this->consumeSse(
                    url    : $serverUrl . '/api/v1/chat/synthesize',
                    payload: array_merge($basePayload(), [
                        'execution_results'    => $execution2->toArray(),
                        'conversation_history' => $history,
                        'normalized_question'  => $normQ,
                        'needs_data'           => true,
                        // Server-side link (Redis) to this question's pipeline trace:
                        // the Hub recovers the trace_id via filtered_schema_id
                        // (the trace_id itself is never exposed to the client).
                        'filtered_schema_id'   => $queriesResult['filtered_schema_id'] ?? null,
                    ]),
                    onStep : fn(array $d) => $this->sendSse('step', $d),
                    onResult: function (array $d) use (&$finalResult) { $finalResult = $d; },
                );

                if ($error) {
                    $this->sendSse('error', ['message' => $error]);
                    return;
                }

                if (!$finalResult || !isset($finalResult['Resume'])) {
                    $this->sendSse('error', ['message' => 'Response in unexpected format']);
                    return;
                }

                $savedMsg = $this->saveAssistantMessage(
                    $conversationId,
                    $finalResult['Resume'],
                    $intent,
                    ['Synthesizer_Agent' => $finalResult],
                );

                $this->sendSse('done', [
                    'result'        => $finalResult,
                    'message_uuid'  => $savedMsg->uuid,
                ]);

            } catch (\Throwable $e) {
                Log::error('[BlunxApiController::stream]', [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ]);
                $this->sendSse('error', ['message' => $e->getMessage()]);
            }

        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=UTF-8',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    // PIPELINE HELPERS
    // ════════════════════════════════════════════════════════════════

    /**
     * Disable output buffering and compression for SSE streaming.
     */
    protected function disableBuffering(): void
    {
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }
        ini_set('zlib.output_compression', '0');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        set_time_limit(180);
    }

    /**
     * Emit a single SSE event.
     */
    protected function sendSse(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }

    /**
     * Consume an SSE stream from the Blunx cloud.
     *
     * @return string|null Error message, or null on success.
     */
    protected function consumeSse(
        string   $url,
        array    $payload,
        callable $onStep,
        callable $onResult,
    ): ?string {
        return \Blunx\AI\Support\SseClient::consume(
            $url,
            $payload,
            (string) config('blunx.api_key'),
            (string) config('blunx.llm_api_key'),
            $onStep,
            $onResult,
        );
    }

    /**
     * Load the enriched schema JSON from storage.
     */
    protected function loadSchemaConfig(): string
    {
        $path = storage_path('app/blunx_schema.json');
        return File::exists($path) ? File::get($path) : '{}';
    }

    /**
     * Last 8 messages of a conversation, oldest first, with the rendered
     * interface appended to assistant messages.
     */
    protected function getConversationHistory(int $conversationId): array
    {
        return BlunxMessage::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->offset(1)
            ->limit(8)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($m) {
                $meta      = is_array($m->meta_data)
                    ? $m->meta_data
                    : json_decode($m->meta_data, true);
                $agentData = $meta['Synthesizer_Agent'] ?? '';
                $agentString = '';
                if (!empty($agentData)) {
                    $agentString = is_array($agentData)
                        ? json_encode($agentData, JSON_UNESCAPED_UNICODE)
                        : $agentData;
                }
                return [
                    'role'    => $m->role,
                    'content' => $m->content . ($agentString
                        ? "\n[Interface generated for that purpose: {$agentString}]"
                        : ''),
                ];
            })
            ->toArray();
    }

    /**
     * Persist an assistant message with optional metadata.
     */
    protected function saveAssistantMessage(
        int    $conversationId,
        string $content,
        string $intent,
        array  $meta = []
    ): BlunxMessage {
        return BlunxMessage::create([
            'conversation_id' => $conversationId,
            'role'            => 'assistant',
            'content'         => $content,
            'intent'          => $intent,
            'meta_data'       => empty($meta) ? null : json_encode($meta),
        ]);
    }

    /**
     * Resolve the access rules for a user role.
     *
     * A listed role gets its own rules only (no merge with `default`); an
     * unlisted role gets the `default` rules. The USER_ID placeholder is
     * replaced with the authenticated user's ID.
     */
    public function getAccessRulesForUser(?string $role, ?int $userId = null): ?array
    {
        if (!$role) return null;

        $allRules = config('blunx_access');
        if (!$allRules || !is_array($allRules)) return null;

        $rules = $allRules[$role] ?? ($allRules['default'] ?? []);

        if (empty($rules)) return null;

        if (isset($rules['row_level']) && is_array($rules['row_level'])) {
            foreach ($rules['row_level'] as $table => $config) {
                if (isset($config['value']) && $config['value'] === 'USER_ID') {
                    $rules['row_level'][$table]['value'] = $userId;
                }
            }
        }

        return $rules;
    }
}
