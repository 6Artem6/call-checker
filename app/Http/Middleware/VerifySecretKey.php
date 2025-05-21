<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySecretKey
{
    public $name = 'secret';
    
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->input('secret_key');
        if (empty($key) || !hash_equals($key, sha1(sha1(env('CRON_SECRET_KEY'))))) {
            abort(404);
        }
        return $next($request);
    }
}
