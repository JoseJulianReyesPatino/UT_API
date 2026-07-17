<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($user = $request->user()) {
            try {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['last_active_at' => now()]);
            } catch (\Throwable) {
                // Si la columna no existe aún (migración pendiente), no interrumpir la request
            }
        }

        return $response;
    }
}
