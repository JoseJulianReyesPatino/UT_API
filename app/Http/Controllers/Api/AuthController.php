<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function formatUser(User $user): array
    {
        $parts = preg_split('/\s+/', trim($user->full_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstNames = count($parts) <= 1 ? trim($user->full_name) : implode(' ', array_slice($parts, 0, -1));
        $lastNames = count($parts) <= 1 ? '' : (string) array_slice($parts, -1)[0];

        // Base64 data URIs are returned as-is; all other non-null values use the stable API route
        $avatarUrl = null;
        if ($user->avatar_url) {
            $avatarUrl = str_starts_with($user->avatar_url, 'data:')
                ? $user->avatar_url
                : '/api/users/' . $user->id . '/avatar';
        }

        $roles = $user->roles()->pluck('code')->values();
        $isSupervisor = $roles->contains('supervisor');

        $supervisorSections = null;
        if ($isSupervisor) {
            $perm = $user->supervisorSectionPermission()->first();
            $supervisorSections = $perm?->sections ?? [];
        }

        $payload = [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'first_names' => $firstNames,
            'last_names' => $lastNames,
            'email' => $user->email,
            'phone' => $user->phone,
            'area' => $user->area,
            'avatar_url' => $avatarUrl,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at?->toIso8601String(),
            'roles' => $roles,
        ];

        if ($supervisorSections !== null) {
            $payload['supervisor_sections'] = $supervisorSections;
        }

        return $payload;
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (!$user || !$user->is_active || !Hash::check($credentials['password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales invalidas.'],
            ]);
        }

        $roles = $user->roles()->pluck('code')->values();
        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => array_merge($this->formatUser($user), ['roles' => $roles]),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    public function profileStats(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'stats' => [
                'documents_sent' => \App\Models\Document::query()->where('uploaded_by', $user->id)->count(),
                'documents_reviewed' => \App\Models\Document::query()->where('uploaded_by', $user->id)->where('status', 'revisado')->count(),
                'documents_pending' => \App\Models\Document::query()->where('uploaded_by', $user->id)->where('status', 'pendiente')->count(),
                'documents_returned' => \App\Models\Document::query()->where('uploaded_by', $user->id)->where('status', 'devuelto')->count(),
                'member_since' => $user->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:150'],
            'phone' => ['nullable', 'digits:10'],
            'area' => ['nullable', 'string', 'max:120'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'avatar_url' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->hasFile('avatar')) {
            $avatarDir = public_path('uploads/avatars');
            if (!is_dir($avatarDir)) {
                mkdir($avatarDir, 0755, true);
            }

            // Delete all previous avatar files for this user
            foreach (glob($avatarDir . '/avatar_' . $user->id . '_*') ?: [] as $old) {
                @unlink($old);
            }
            // Also clean up any files stored in the old Laravel disk location
            foreach (glob(storage_path('app/public/avatars/avatar_' . $user->id . '_*')) ?: [] as $old) {
                @unlink($old);
            }

            $ext = $request->file('avatar')->getClientOriginalExtension() ?: 'png';
            $storedName = 'avatar_' . $user->id . '_' . uniqid() . '.' . $ext;
            $request->file('avatar')->move($avatarDir, $storedName);

            $data['avatar_url'] = '/api/users/' . $user->id . '/avatar';
        }

        $user->fill([
            'full_name' => $data['full_name'] ?? $user->full_name,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $user->phone,
            'area' => array_key_exists('area', $data) ? $data['area'] : $user->area,
            'avatar_url' => array_key_exists('avatar_url', $data) ? $data['avatar_url'] : $user->avatar_url,
        ])->save();

        return response()->json(['user' => $this->formatUser($user->fresh())]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual no es correcta.'],
            ]);
        }

        $user->forceFill([
            'password_hash' => Hash::make($data['password']),
        ])->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesion cerrada']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (!$user) {
            return response()->json(['message' => 'Si el correo existe, se enviara un enlace de recuperacion.']);
        }

        $plainToken = Str::random(64);
        PasswordResetToken::query()->updateOrCreate(
            ['email' => $user->email],
            [
                'token_hash' => Hash::make($plainToken),
                'expires_at' => now()->addMinutes(30),
            ]
        );

        return response()->json([
            'message' => 'Token de recuperacion generado.',
            'reset_token' => $plainToken,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = PasswordResetToken::query()->where('email', $data['email'])->first();

        if (!$record || !$record->expires_at || $record->expires_at->isPast() || !Hash::check($data['token'], $record->token_hash)) {
            throw ValidationException::withMessages([
                'token' => ['El token es invalido o expiro.'],
            ]);
        }

        $user = User::query()->where('email', $data['email'])->firstOrFail();
        $user->forceFill([
            'password_hash' => Hash::make($data['password']),
        ])->save();

        $record->delete();

        return response()->json(['message' => 'Contrasena actualizada correctamente.']);
    }
}
