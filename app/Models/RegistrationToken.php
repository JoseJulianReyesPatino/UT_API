<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RegistrationToken extends Model
{
    protected $fillable = [
        'email', 'full_name', 'username', 'password_hash', 'token_hash', 'expires_at',
    ];
    protected $casts = ['expires_at' => 'datetime'];
    protected $hidden = ['token_hash', 'password_hash'];
}
