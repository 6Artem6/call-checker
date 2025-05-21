<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\OAuth2Controller;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AuthorizationController;


Route::get('/', [SiteController::class, 'index'])->name('home');

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login-post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout-post');

Route::get('/oauth2', [OAuth2Controller::class, 'oauth2'])->name('oauth2');

Route::middleware(['auth.cookie'])->group(function () {
    Route::get('/panel/{account_id?}', [PanelController::class, 'index'])->whereNumber('account_id')->name('panel-index');
    Route::post('/panel/{account_id?}', [PanelController::class, 'save'])->whereNumber('account_id')->name('panel-save');
    Route::get('/panel-file/{name}', [PanelController::class, 'download'])->name('panel-file-download');
    Route::delete('/panel-file/{name}', [PanelController::class, 'delete'])->name('panel-file-delete');

    Route::get('/payment/create', [PaymentController::class, 'create'])->name('payment.create');
    Route::post('/payment/store', [PaymentController::class, 'store'])->name('payment.store');
    Route::get('/payment/return', [PaymentController::class, 'return'])->name('payment.return');
    Route::any('/payment/webhook', [PaymentWebhookController::class, 'handle'])->name('payment.webhook');
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
});

Route::get('/support', [SiteController::class, 'supportRequest'])->name('support-request');
Route::post('/support', [SiteController::class, 'supportSend'])->name('support-send');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/request-send', [SiteController::class, 'requestCreate'])->name('request-create');
    Route::post('/request-send', [SiteController::class, 'requestSend'])->name('request-send');
    Route::get('/request-list', [SiteController::class, 'requestList'])->name('request-list');
    Route::get('/file-list/{id?}', [SiteController::class, 'fileList'])->whereNumber('id')->name('file-list');
    Route::get('/file-info/{id}', [SiteController::class, 'fileInfo'])->whereNumber('id')->name('file-info');
    Route::post('/instruction-create', [SiteController::class, 'instructionCreate'])->name('instruction-create');
    Route::post('/instruction-list', [SiteController::class, 'instructionList'])->name('instruction-list');
    Route::get('/file/{id}', [SiteController::class, 'file'])->whereNumber('id')->name('file');
});

Route::middleware(['web'])->group(function () {
    Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])->name('passport.authorizations.authorize');
});
