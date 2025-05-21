<?php

namespace App\Providers;

use App\Models\AccountUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Passport::tokensCan([
            'plugin' => 'Доступ к плагину',
            'read' => 'Read data',
            'write' => 'Write data',
        ]);

        // Чтобы поддерживать разные guards
        Passport::cookie('oauth_token');

        Passport::enableImplicitGrant();
        Passport::enablePasswordGrant();

        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::$keyPath = storage_path('oauth');
        Passport::loadKeysFrom(storage_path('oauth'));

    }
}
