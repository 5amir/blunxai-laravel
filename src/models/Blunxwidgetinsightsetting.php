<?php

namespace Blunx\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Blunx\AI\Models\Concerns\UsesBlunxConnection;

/**
 * Scheduling settings for a widget's automated insights.
 */
class BlunxWidgetInsightSetting extends Model
{
    use HasUuids;
    use UsesBlunxConnection;

    protected $table = 'blunx_widget_insight_settings';

    protected $hidden = ['id', 'user_id', 'widget_id'];

    protected $fillable = [
        'user_id',
        'widget_id',
        'frequency',
        'notify_email',
        'notify_threshold',
        'metric_weights',
        'context_queries',
        'email',
        'last_run_at',
        'next_run_at',
        'job_uuid',
    ];

    protected $casts = [
        'notify_email' => 'boolean',
        'metric_weights' => 'array',
        'context_queries' => 'array',
        'last_run_at'  => 'datetime',
        'next_run_at'  => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    // ── Helpers ───────────────────────────────────────────────────

    public function computeNextRunAt(): Carbon
    {
        $base = now();

        return match ($this->frequency) {
            'daily'   => $base->addDay()->startOfDay()->addHours(7),
            'weekly'  => $base->next('Monday')->startOfDay()->addHours(7),
            'monthly' => $base->addMonthNoOverflow()->startOfMonth()->addHours(7),
            default   => $base->addDay()->startOfDay()->addHours(7),
        };
    }

    public function scopeDue($query)
    {
        return $query->where('next_run_at', '<=', now())
                     ->orWhereNull('next_run_at');
    }
}
