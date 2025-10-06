<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Token;

class AuthenticateByCookie
{
    public function handle(Request $request, Closure $next)
    {
        dd($request->headers->get('cookie', ''));
        $cookieName = config('session.cookie_token');
        $t = $request->cookie($cookieName);

        if (!$t) {
            preg_match("/{$cookieName}=([^;]+)/", $request->headers->get('cookie', ''), $m);
            $t = $m[1] ?? null;
        }

        if (!$t) {
            $t = $request->cookie('oidc_jwt');
        }
        if (!$t) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $parts = explode('.', $t);
        if (count($parts) !== 3) {
            return response()->json(['error' => 'Malformed token'], 401);
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $jti = $payload['jti'] ?? null;
        if (!$jti) {
            return response()->json(['error' => 'Invalid token payload'], 401);
        }

        // Проверка в кэше
        if (Cache::has("revoked:$jti")) {
            return response()->json(['error' => 'Token revoked'], 401);
        }

        $token = Token::where('id', $jti)->first();
        if (!$token || $token->expires_at < now()) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        // Если отозван — кладём в кэш и возвращаем 401
        if ($token->revoked) {
            Cache::put("revoked:$jti", true, now()->addMinutes(30));
            return response()->json(['error' => 'Token revoked'], 401);
        }

        Auth::guard('api')->setUser($token->user);

        return $next($request);
    }
}
