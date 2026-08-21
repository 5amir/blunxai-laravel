<?php

namespace Blunx\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Blunx\AI\Models\Concerns\UsesBlunxConnection;

/**
 * An AI-generated insight for a widget: type, severity, message, metadata
 * and the generated PDF report.
 */
class BlunxInsight extends Model
{
    use HasUuids;
    use UsesBlunxConnection;

    protected $table = 'blunx_insights';

    protected $hidden = ['id', 'user_id', 'dashboard_id', 'widget_id'];

    protected $fillable = [
        'user_id',
        'dashboard_id',
        'widget_id',
        'type',
        'severity',
        'message',
        'meta',
        'is_read',
        'generated_at',
        'report_pdf',
    ];

    protected $casts = [
        'meta'         => 'array',
        'is_read'      => 'boolean',
        'generated_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ── Relations ─────────────────────────────────────────────────

    public function widget(): BelongsTo
    {
        return $this->belongsTo(BlunxDashboardWidget::class, 'widget_id');
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(BlunxDashboard::class, 'dashboard_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForWidget($query, int $widgetId)
    {
        return $query->where('widget_id', $widgetId);
    }

    public function scopeForDashboard($query, int $dashboardId)
    {
        return $query->where('dashboard_id', $dashboardId);
    }

    // ── Helpers ───────────────────────────────────────────────────

    public function severityIcon(): string
    {
        return match ($this->severity) {
            'critical' => '🔴',
            'warning'  => '🟡',
            default    => '🔵',
        };
    }

    public function severityClass(): string
    {
        return match ($this->severity) {
            'critical' => 'danger',
            'warning'  => 'warning',
            default    => 'info',
        };
    }

    /**
     * Lightweight context for the cloud report agent.
     *
     * Sends only the fields useful to the InsightReportAgent (no report_pdf,
     * no internal IDs).
     */
    public function toReportContext(): array
    {
        $meta = $this->meta ?? [];

        return [
            'type'         => $this->type,
            'severity'     => $this->severity,
            'message'      => $this->message,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'meta'         => [
                'indicator'     => $meta['indicator']     ?? 'N/A',
                'actual_value'  => $meta['actual_value']  ?? 'N/A',
                'last_value'    => $meta['last_value']    ?? 'N/A',
                'variation'     => $meta['variation']     ?? '',
                'global_score'  => $meta['global_score']  ?? 0,
                'priority_level'=> $meta['priority_level'] ?? 'low',
            ],
        ];
    }
}
