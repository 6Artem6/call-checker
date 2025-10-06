<?php

use App\Http\Controllers\{AmoCrmController, CronController, OIDCController, SeleniumAuthController, WebhookController};
use App\Http\Middleware\VerifySecretKey;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Illuminate\Support\Facades\Route;


Route::get('/webhook', [WebhookController::class, 'handleWebhook'])->name('webhook-get');
Route::post('/webhook', [WebhookController::class, 'handleWebhook'])->name('webhook-post');
Route::post('/webhook/channel/{scope_id}', [WebhookController::class, 'handleWebhookChannel'])->name('webhook-channel');
Route::post('/webhook/wuzzap', [WebhookController::class, 'handleWebhookWuzzap'])->name('webhook-wuzzap');
Route::post('/webhook/i2crm', [WebhookController::class, 'handleWebhookI2crm'])->name('webhook-i2crm');

Route::prefix('webhook')->group(function () {
    Route::post('/plugin-activate', [WebhookController::class, 'handleWebhookPluginActivate'])->name('webhook-plugin-activate');
    Route::post('/plugin-status', [WebhookController::class, 'handleWebhookPluginStatus'])->name('webhook-plugin-status');
    Route::post('/bot-status', [WebhookController::class, 'handleWebhookBotStatus'])->name('webhook-bot-status');
    Route::post('/bot-switch', [WebhookController::class, 'handleWebhookBotSwitch'])->name('webhook-bot-switch');

    Route::post('/pipelines', [WebhookController::class, 'handleWebhookPipelines'])->name('webhook-pipelines');
    Route::post('/lead-add', [WebhookController::class, 'handleWebhookLeadAdd'])->name('webhook-lead-add');
    Route::post('/lead-status', [WebhookController::class, 'handleWebhookLeadStatus'])->name('webhook-lead-status');

    Route::get('/test', [WebhookController::class, 'handleWebhookTest'])->name('webhook-test');
});

Route::prefix('auth')->group(function () {
    Route::get('/register', [OIDCController::class, 'registerForm'])->name('auth-register-form');
    Route::post('/register', [OIDCController::class, 'register'])->name('auth-register');
    Route::get('/login', [OIDCController::class, 'loginForm'])->name('auth-login-form');
    Route::post('/login', [OIDCController::class, 'login'])->name('auth-login');
    Route::post('/logout', [OIDCController::class, 'logout'])->name('auth-logout');
    Route::post('/redirect', [OIDCController::class, 'login'])->name('auth-redirect');
    Route::get('/callback', [OIDCController::class, 'callback'])->name('auth-callback');
    Route::post('/refresh-token', [OIDCController::class, 'refreshToken'])->name('auth-refresh-token');
});

Route::middleware(VerifySecretKey::class)->group(function () {
    Route::get('/cron', [CronController::class, 'index'])->name('cron.index');
    Route::get('/cron/file-transcribe', [CronController::class, 'fileTranscribe'])->name('cron.file-transcribe');
    Route::get('/cron/file-analysis', [CronController::class, 'fileAnalysis'])->name('cron.file-analysis');
    Route::get('/cron/refresh-tokens', [CronController::class, 'refreshTokens'])->name('cron.refresh-tokens');
});

Route::post('/oauth/token', [AccessTokenController::class, 'issueToken']);

//Route::get('/send-message', [AmoCrmController::class, 'sendMessage'])->name('send-message');
//Route::get('/test-send-message', [AmoCrmController::class, 'testSendMessage'])->name('test-send-message');
//Route::get('/test-request', [AmoCrmController::class, 'testRequest'])->name('test-request');
//
//Route::get('/selenium-test-request/{userId}', [SeleniumAuthController::class, 'seleniumTestRequest'])->name('selenium-test-request');
//Route::get('/puppeteer-test-request', [SeleniumAuthController::class, 'puppeteerTestRequest'])->name('puppeteer-test-request');
//
//Route::get('/leads', [AmoCrmController::class, 'leads'])->name('leads');
//Route::get('/deal/{dealId}', [AmoCrmController::class, 'dealDetail'])->name('deal.detail');
