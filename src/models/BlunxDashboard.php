<?php

namespace Blunx\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Blunx\AI\Models\Concerns\UsesBlunxConnection;

/**
 * A dashboard grouping widgets, scoped to a user.
 */
class BlunxDashboard extends Model
{
    use HasUuids;
    use UsesBlunxConnection;

    protected $table = 'blunx_dashboards';

    protected $hidden = ['id', 'user_id'];

    protected $fillable = [
        'user_id',
        'name',
        'description',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function widgets()
    {
        return $this->hasMany(BlunxDashboardWidget::class, 'dashboard_id');
    }

    public function insights()
    {
        return $this->hasMany(BlunxInsight::class, 'dashboard_id');
    }

    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }
}
