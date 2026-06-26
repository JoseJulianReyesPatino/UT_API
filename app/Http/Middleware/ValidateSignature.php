<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ValidateSignature as Middleware;

class ValidateSignature extends Middleware
{
    protected $except = [
        // 'fbclid',
        // 'utm_campaign',
        // 'utm_medium',
        // 'utm_source',
        // 'utm_term',
    ];
}