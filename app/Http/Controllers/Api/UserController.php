<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    private function formatUser(User $user): array
    {
        // Base64 data URIs are returned as-is; all other non-null values use the stable API route
        $avatarUrl = null;
        if ($user->avatar_url) {
            if (str_starts_with($user->avatar_url, 'data:')) {
                $avatarUrl = $user->avatar_url;
            } else {
                // Build origin from current request, check for ngrok X-Forwarded-Proto header
                $scheme = request()->headers->get('X-Forwarded-Proto') ?: request()->getScheme();
                $host = request()->getHost();
                $origin = $scheme . '://' . $host;
                $avatarUrl = $origin . '/api/users/' . $user->id . '/avatar';
            }
        }

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'area' => $user->area,
            'avatar_url' => $avatarUrl,
            'is_active' => (bool) $user->is_active,
            'documents_count' => (int) ($user->documents_count ?? 0),
            'roles' => $user->roles->map(fn (Role $role) => ['code' => $role->code, 'name' => $role->name])->values(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('roles')
            ->withCount('documents')
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'data' => $users->map(fn (User $user) => $this->formatUser($user))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'digits:10'],
            'area' => ['nullable', 'string', 'max:120'],
            'avatar_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['in:administrador,docente,tutor,supervisor'],
        ]);

        $user = User::query()->create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'area' => $data['area'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $roleIds = Role::query()->whereIn('code', $data['roles'])->pluck('id')->all();
        $user->roles()->sync($roleIds);

        return response()->json(['data' => $this->formatUser($user->load('roles'))], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->loadMissing('roles')->loadCount('documents');
        return response()->json(['data' => $this->formatUser($user)]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email', 'max:150'],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'digits:10'],
            'area' => ['nullable', 'string', 'max:120'],
            'avatar_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['in:administrador,docente,tutor,supervisor'],
        ]);

        if (!empty($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
            unset($data['password']);
        }

        $user->fill($data)->save();

        if (array_key_exists('roles', $data)) {
            $roleIds = Role::query()->whereIn('code', $data['roles'])->pluck('id')->all();
            $user->roles()->sync($roleIds);
        }

        $user->load('roles')->loadCount('documents');
        return response()->json(['data' => $this->formatUser($user)]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->update(['is_active' => false]);

        return response()->json(['message' => 'Usuario desactivado']);
    }

    /**
     * Serve the avatar image for a user.
     */
    public function avatar(Request $request, int $userId): mixed
    {
        $user = User::findOrFail($userId);
        $url = $user->avatar_url;

        if (!$url) {
            return response()->json(['message' => 'Sin avatar'], 404);
        }

        // data: URI — cannot stream, return 404 so frontend falls back to initials
        if (str_starts_with($url, 'data:')) {
            return response()->json(['message' => 'Avatar inline no streamable'], 404);
        }

        // Full external / ngrok / http URL — redirect so browser fetches directly
        if (str_starts_with($url, 'http')) {
            return redirect($url);
        }

        // Resolve storage path - url should be like /storage/avatars/avatar_1_xxx.png
        if (str_starts_with($url, '/storage/')) {
            $storagePath = substr($url, strlen('/storage/'));
        } else {
            $storagePath = ltrim($url, '/');
        }

        // Check if file exists in storage
        if (!Storage::disk('public')->exists($storagePath)) {
            // Try legacy path
            $legacyPath = public_path('uploads/avatars/' . basename($url));
            if (file_exists($legacyPath)) {
                $mime = mime_content_type($legacyPath) ?: 'image/png';
                return response()->file($legacyPath, [
                    'Content-Type'  => $mime,
                    'Cache-Control' => 'public, max-age=3600',
                ]);
            }
            return response()->json(['message' => 'Avatar file not found'], 404);
        }

        // Serve from Laravel Storage
        $fullPath = Storage::disk('public')->path($storagePath);
        $mime = mime_content_type($fullPath) ?: 'image/png';

        return response()->file($fullPath, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
