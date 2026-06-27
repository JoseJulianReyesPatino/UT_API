<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarFile extends Model
{
    protected $table = 'calendar_files';

    public $timestamps = false;

    protected $fillable = [
        'cycle_id',
        'file_name',
        'file_path',
        'uploaded_by',
        'uploaded_at',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'uploaded_at' => 'datetime',
    ];
}
