<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $table = 'messages';

    const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id',
        'sender_user_id',
        'body',
        'reply_to_message_id',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class, 'message_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }
}
