<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register application services here.
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('role', EnsureUserHasRole::class);
    }
}
