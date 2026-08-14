<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Para peticiones API (que es todo tu sistema), devolvemos null
        // Esto evita la redirección a una ruta 'login' que no existe
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // Para peticiones web (que no usas), devolver null también
        // ya que tu sistema es 100% API
        return null;
    }
}
