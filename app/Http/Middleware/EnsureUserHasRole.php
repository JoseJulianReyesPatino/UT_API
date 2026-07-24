<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param mixed ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return $next($request);
                }
            }

            return response()->json(['message' => 'Prohibido.'], Response::HTTP_FORBIDDEN);
        }

        if (isset($user->roles) && is_iterable($user->roles)) {
            $codes = collect($user->roles)->pluck('code')->map(fn ($code) => (string) $code)->all();
            $names = collect($user->roles)->pluck('name')->map(fn ($name) => (string) $name)->all();

            foreach ($roles as $role) {
                $needle = (string) $role;
                if (in_array($needle, $codes, true) || in_array($needle, $names, true)) {
                    return $next($request);
                }
            }

            return response()->json(['message' => 'Prohibido.'], Response::HTTP_FORBIDDEN);
        }

        if (isset($user->role)) {
            foreach ($roles as $role) {
                if ((string) $user->role === (string) $role) {
                    return $next($request);
                }
            }
        }

        return response()->json(['message' => 'Prohibido.'], Response::HTTP_FORBIDDEN);
    }
}
