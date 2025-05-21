<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugAuthCookie
{
    public function handle(Request $request, Closure $next)
    {
        Log::debug('--- DebugAuthCookie ---');
        Log::debug('Origin:', [$request->headers->get('origin')]);
        Log::debug('Referer:', [$request->headers->get('referer')]);
        Log::debug('Method:', [$request->method()]);
        Log::debug('Cookies:', $request->cookies->all());
        Log::debug('Headers:', $request->headers->all());

        $cookieHeader = $request->headers->get('cookie');
        if ($cookieHeader) {
            Log::debug('Raw Cookie header:', [$cookieHeader]);
        }

        $response = $next($request);

        // Проверка CORS-заголовков в ответе
        Log::debug('Response CORS headers:', [
            'Access-Control-Allow-Origin' => $response->headers->get('Access-Control-Allow-Origin'),
            'Access-Control-Allow-Credentials' => $response->headers->get('Access-Control-Allow-Credentials'),
        ]);

        return $response;
    }
}
