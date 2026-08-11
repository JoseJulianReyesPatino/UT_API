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
    /**
     * Deletes the physical avatar file for a given avatar_url value.
     * Handles /storage/... paths and legacy /uploads/... paths.
     */
    private function deleteStoredAvatar(?string $avatarUrl): void
    {
        if (!$avatarUrl) {
            return;
        }

        // Ignore external URLs and data URIs
        if (str_starts_with($avatarUrl, 'http') || str_starts_with($avatarUrl, 'data:')) {
            return;
        }

        // Resolve relative storage path
        $storagePath = $avatarUrl;
        if (str_starts_with($storagePath, '/storage/')) {
            $storagePath = substr($storagePath, strlen('/storage/'));
        } elseif (str_starts_with($storagePath, '/uploads/')) {
            // Legacy uploads path — try public disk path
            $legacyFull = public_path(ltrim($storagePath, '/'));
            if (file_exists($legacyFull)) {
                @unlink($legacyFull);
            }
            return;
        } else {
            $storagePath = ltrim($storagePath, '/');
        }

        if ($storagePath && Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->delete($storagePath);
        }
    }

    /**
     * Format user data for API response with proper null handling
     */
    private function formatUser(User $user): array
    {
        $parts = preg_split('/\s+/', trim($user->full_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstNames = count($parts) <= 1 ? trim($user->full_name) : implode(' ', array_slice($parts, 0, -1));
        $lastNames = count($parts) <= 1 ? '' : (string) array_slice($parts, -1)[0];

        // Asegurar que avatar_url sea una URL completa o null
        $avatarUrl = $user->avatar_url;
        if ($avatarUrl && !str_starts_with($avatarUrl, 'http')) {
            // Build origin from current request, check for ngrok X-Forwarded-Proto header
            $scheme = request()->headers->get('X-Forwarded-Proto') ?: request()->getScheme();
            $host = request()->getHost();
            $origin = $scheme . '://' . $host;

            if (str_starts_with($avatarUrl, '/storage/')) {
                $avatarUrl = $origin . $avatarUrl;
            } elseif (str_starts_with($avatarUrl, '/api/')) {
                $avatarUrl = $origin . $avatarUrl;
            } elseif (str_starts_with($avatarUrl, '/uploads/')) {
                // Legacy uploads path
                $avatarUrl = $origin . str_replace('/uploads/', '/storage/', $avatarUrl);
            } elseif (!str_starts_with($avatarUrl, '/')) {
                $avatarUrl = $origin . '/storage/' . $avatarUrl;
            } else {
                // Starts with / but no /storage/ or /api/
                $avatarUrl = $origin . $avatarUrl;
            }
        }

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'first_names' => $firstNames,
            'last_names' => $lastNames,
            'email' => $user->email,
            'phone' => $user->phone ?? '',  // ✅ Siempre string, nunca null
            'area' => $user->area ?? '',    // ✅ Siempre string, nunca null
            'avatar_url' => $avatarUrl,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at?->toIso8601String(),
            'roles' => $user->roles()->pluck('code')->values(),
            'supervisor_sections' => $user->supervisorSectionPermission?->sections ?? [],
        ];
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
                'email' => ['Credenciales inválidas.'],
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

        $removingAvatar = $request->has('avatar_url') && is_null($request->input('avatar_url'));

        if ($request->hasFile('avatar')) {
            // Eliminar avatar anterior si existe
            $this->deleteStoredAvatar($user->avatar_url);

            // Guardar nuevo avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar_url'] = env('APP_URL') . '/storage/' . $path;
        } elseif ($removingAvatar) {
            // Quitar foto de perfil: eliminar archivo físico
            $this->deleteStoredAvatar($user->avatar_url);
            $data['avatar_url'] = null;
        }

        $user->fill([
            'full_name' => $data['full_name'] ?? $user->full_name,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $user->phone,
            'area' => array_key_exists('area', $data) ? $data['area'] : $user->area,
            'avatar_url' => array_key_exists('avatar_url', $data) ? $data['avatar_url'] : $user->avatar_url,
        ])->save();

        return response()->json(['user' => $this->formatUser($user->fresh())]);
    }

    public function verifyCurrentPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (!Hash::check($data['password'], $request->user()->password_hash)) {
            throw ValidationException::withMessages([
                'password' => ['La contraseña actual no es correcta.'],
            ]);
        }

        return response()->json(['valid' => true]);
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

    public function getPreferences(Request $request): JsonResponse
    {
        $prefs = $request->user()->preferences ?? [];

        // Normalizar bgCustomUrl: si tiene host antiguo (localhost u otro), extraer solo la ruta relativa.
        if (is_array($prefs) && !empty($prefs['bgCustomUrl'])) {
            $url = $prefs['bgCustomUrl'];
            if (!str_starts_with($url, '/storage/') && preg_match('/\/storage\/(backgrounds\/.+)/', $url, $m)) {
                $prefs['bgCustomUrl'] = '/storage/' . $m[1];
            }
        }

        return response()->json([
            'preferences' => empty($prefs) ? (object)[] : $prefs,
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $incoming = $request->validate([
            'bgKey'          => ['sometimes', 'string', 'max:50'],
            'bgOverlay'      => ['sometimes', 'integer', 'min:0', 'max:80'],
            'containerAlpha' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'containerBlur'  => ['sometimes', 'integer', 'min:0', 'max:20'],
            'appTheme'       => ['sometimes', 'string', 'in:emerald,ocean,violet,rose,amber,indigo,red,orange,cyan,lime,teal,fuchsia,white,black'],
            'layoutStyle'    => ['sometimes', 'string', 'in:default,formal'],
        ]);

        $user->preferences = array_merge($user->preferences ?? [], $incoming);
        $user->save();

        return response()->json(['preferences' => $user->preferences]);
    }

    public function getBackgroundImage(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user  = $request->user();
        $prefs = $user->preferences ?? [];

        if (empty($prefs['bgCustomUrl'])) {
            abort(404);
        }

        $url = $prefs['bgCustomUrl'];
        // Normalizar si todavía tiene host completo
        if (!str_starts_with($url, '/storage/') && preg_match('/\/storage\/(backgrounds\/.+)/', $url, $m)) {
            $url = '/storage/' . $m[1];
        }

        if (preg_match('/\/storage\/(backgrounds\/[^?]+)/', $url, $m)) {
            $relativePath = $m[1];
            if (Storage::disk('public')->exists($relativePath)) {
                $filePath = Storage::disk('public')->path($relativePath);
                return response()->file($filePath, [
                    'Cache-Control' => 'private, max-age=3600',
                ]);
            }
        }

        abort(404);
    }

    public function uploadBackground(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // 5 MB
        ]);

        $user = $request->user();
        $prefs = $user->preferences ?? [];

        // Eliminar archivo anterior si existe
        if (!empty($prefs['bgCustomUrl'])) {
            if (preg_match('/\/storage\/(backgrounds\/[^?]+)/', $prefs['bgCustomUrl'], $m)) {
                if (Storage::disk('public')->exists($m[1])) {
                    Storage::disk('public')->delete($m[1]);
                }
            }
        }

        $ext  = $request->file('image')->getClientOriginalExtension();
        $path = $request->file('image')->storeAs(
            'backgrounds',
            "user_{$user->id}_bg.{$ext}",
            'public'
        );

        // Guardar ruta relativa — el frontend resuelve la URL completa según su apiOrigin
        $bgCustomPath = '/storage/' . $path . '?t=' . time();

        $user->preferences = array_merge($prefs, ['bgCustomUrl' => $bgCustomPath]);
        $user->save();

        return response()->json(['bgCustomUrl' => $bgCustomPath]);
    }

    public function deleteBackground(Request $request): JsonResponse
    {
        $user  = $request->user();
        $prefs = $user->preferences ?? [];

        if (!empty($prefs['bgCustomUrl'])) {
            if (preg_match('/\/storage\/(backgrounds\/[^?]+)/', $prefs['bgCustomUrl'], $m)) {
                if (Storage::disk('public')->exists($m[1])) {
                    Storage::disk('public')->delete($m[1]);
                }
            }
            unset($prefs['bgCustomUrl']);
            $user->preferences = empty($prefs) ? null : $prefs;
            $user->save();
        }

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (!$user) {
            return response()->json(['message' => 'Si el correo existe, se enviará un enlace de recuperación.']);
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
            'message' => 'Si el correo existe, se enviaron instrucciones para recuperar la contraseña.',
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
                'token' => ['El token es inválido o expiró.'],
            ]);
        }

        $user = User::query()->where('email', $data['email'])->firstOrFail();
        $user->forceFill([
            'password_hash' => Hash::make($data['password']),
        ])->save();

        $record->delete();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}
