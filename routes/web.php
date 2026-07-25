<?php

use App\Http\Controllers\MobileAppController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', function () {
    return view('welcome');
});

Route::get(
    '/downloads/android',
    [
        MobileAppController::class,
        'downloadAndroid',
    ]
)->name('downloads.android')
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        VerifyCsrfToken::class,
    ]);
