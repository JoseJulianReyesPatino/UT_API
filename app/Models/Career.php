<?php
// app/Models/Career.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Career extends Model
{
    protected $table = 'careers';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'plan',
        'level',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'career_id');
    }
}