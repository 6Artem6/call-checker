<?php

namespace App\Providers;

use App\Http\Responses\AuthorizationRequestApprovedResponse;
use App\Models\AiLead\Account\AccountOAuth2;
use App\Models\AiLead\Payment\Invoice;
use App\Models\AiLead\Payment\Transaction;
use App\Observers\AccountOAuth2Observer;
use App\Observers\InvoiceObserver;
use App\Observers\TransactionObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Illuminate\Support\Facades\Log;


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
        Invoice::observe(InvoiceObserver::class);
        Transaction::observe(TransactionObserver::class);

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                Log::critical('PHP Fatal error', $error);
            }
        });
    }
}
