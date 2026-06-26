<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentStatusHistory extends Model
{
    protected $table = 'document_status_history';
    
    protected $fillable = [
        'document_id',
        'action',
        'action_by',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'action_by');
    }
}
