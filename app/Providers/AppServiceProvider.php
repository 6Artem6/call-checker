<?php

namespace App\Providers;

use App\Http\Responses\AuthorizationRequestApprovedResponse;
use App\Models\AccountOAuth2;
use App\Observers\AccountOAuth2Observer;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Laravel\Passport\Contracts\AuthorizationViewResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */

    public function register()
    {
        $this->app->bind(AuthorizationViewResponse::class, AuthorizationRequestApprovedResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

//        AccountOAuth2::observe(AccountOAuth2Observer::class);
    }
}
