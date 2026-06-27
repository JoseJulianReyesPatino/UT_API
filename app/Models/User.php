<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'full_name',
        'email',
        'password_hash',
        'phone',
        'area',
        'avatar_url',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['avatar'];

    public function getAvatarAttribute(): string
    {
        if ($this->avatar_url) {
            $url = $this->avatar_url;
            
            if (str_starts_with($url, 'http') || str_starts_with($url, 'data:')) {
                return $url;
            }
            
            if (str_starts_with($url, '/api/users/')) {
                return $this->getAvatarStorageUrl($url);
            }
            
            if (str_starts_with($url, '/storage/')) {
                return $url . '?t=' . ($this->updated_at?->timestamp ?? time());
            }
            
            return '/storage/' . ltrim($url, '/') . '?t=' . ($this->updated_at?->timestamp ?? time());
        }

        $parts = preg_split('/\s+/', trim($this->full_name)) ?: [];
        if (empty($parts)) return 'U';

        $firstInitial = mb_strtoupper(mb_substr($parts[0], 0, 1));
        if (count($parts) > 1) {
            $lastInitial = mb_strtoupper(mb_substr(end($parts), 0, 1));
            return $firstInitial . $lastInitial;
        }

        return $firstInitial;
    }

    private function getAvatarStorageUrl(string $apiUrl): string
    {
        preg_match('/\/api\/users\/(\d+)\/avatar/', $apiUrl, $matches);
        if (isset($matches[1])) {
            $userId = $matches[1];
            $avatarFile = "avatars/avatar_{$userId}_*";
            $files = glob(storage_path("app/public/{$avatarFile}"));
            if (!empty($files)) {
                $filename = basename($files[0]);
                return "/storage/avatars/{$filename}?t=" . ($this->updated_at?->timestamp ?? time());
            }
        }
        
        return $apiUrl . '?t=' . ($this->updated_at?->timestamp ?? time());
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')->withPivot('assigned_at');
    }

    public function documents()
    {
        return $this->hasMany(\App\Models\Document::class, 'uploaded_by');
    }
}
