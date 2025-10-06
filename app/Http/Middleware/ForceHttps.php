<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        // если пришли за прокси по https — форсим схему
        if ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
        return $next($request);
    }
}
