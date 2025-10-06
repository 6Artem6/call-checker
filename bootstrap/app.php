<?php

use App\Http\Middleware\AuthenticateByCookie;
use App\Http\Middleware\ApiProxyMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // сначала доверяем прокси и форсим https
        $middleware->web(prepend: [
            TrustProxies::class,
            ForceHttps::class,
        ]);

        $middleware->api(prepend: [
            TrustProxies::class,
            ForceHttps::class,
        ]);

        // остальное — потом
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'auth.cookie' => AuthenticateByCookie::class,
            'api.proxy'   => ApiProxyMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: ['api/*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
