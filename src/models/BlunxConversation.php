<?php

namespace Blunx\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Blunx\AI\Models\Concerns\UsesBlunxConnection;

/**
 * A chat conversation, scoped to a user.
 */
class BlunxConversation extends Model
{
    use HasUuids;
    use UsesBlunxConnection;

    protected $guarded = [];

    protected $hidden = ['id', 'user_id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('blunx.tables.conversations', 'blunx_conversations'));
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function messages()
    {
        return $this->hasMany(BlunxMessage::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    public function lastMessage()
    {
        return $this->hasOne(BlunxMessage::class, 'conversation_id')->latestOfMany();
    }
}
