<?php

namespace Blunx\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Blunx\AI\Models\Concerns\UsesBlunxConnection;

/**
 * User feedback on an assistant message.
 */
class BlunxFeedback extends Model
{
    use UsesBlunxConnection;
    protected $table = 'blunx_feedbacks';

    protected $hidden = ['id'];

    protected $fillable = [
        'user_message_id',
        'model_message_id',
        'comment',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(BlunxMessage::class, 'user_message_id');
    }

    public function modelMessage(): BelongsTo
    {
        return $this->belongsTo(BlunxMessage::class, 'model_message_id');
    }

    public function isPending(): bool
    {
        return $this->resolved_at === null;
    }
}