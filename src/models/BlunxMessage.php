<?php

namespace Blunx\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Blunx\AI\Models\Concerns\UsesBlunxConnection;

/**
 * A message within a conversation (user or assistant).
 */
class BlunxMessage extends Model
{
    use HasUuids;
    use UsesBlunxConnection;

    protected $guarded = [];

    protected $hidden = ['id', 'conversation_id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('blunx.tables.messages', 'blunx_messages'));
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function conversation()
    {
        return $this->belongsTo(BlunxConversation::class, 'conversation_id');
    }
}
