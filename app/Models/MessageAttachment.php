<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $table = 'message_attachments';

    protected $fillable = [
        'message_id',
        'file_name',
        'file_path',
        'file_size_bytes',
        'file_type_label',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}
