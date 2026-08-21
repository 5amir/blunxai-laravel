<?php

namespace Blunx\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Blunx\AI\Models\Concerns\UsesBlunxConnection;

/**
 * A chart or table widget backed by a SQL query.
 */
class BlunxDashboardWidget extends Model
{
    use HasUuids;
    use UsesBlunxConnection;

    protected $guarded = [];

    protected $hidden = ['id', 'dashboard_id'];

    protected $casts = [
        'data_columns' => 'array',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function dashboard()
    {
        return $this->belongsTo(BlunxDashboard::class, 'dashboard_id');
    }

    public function insights()
    {
        return $this->hasMany(\Blunx\AI\Models\BlunxInsight::class, 'widget_id');
    }

    public function insightSetting()
    {
        return $this->hasOne(\Blunx\AI\Models\BlunxWidgetInsightSetting::class, 'widget_id');
    }
}
