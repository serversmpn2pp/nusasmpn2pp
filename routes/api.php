<?php

use App\Http\Controllers\Api\V1\AutentikasiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')
    ->name('api.v1.auth.')
    ->controller(AutentikasiController::class)
    ->group(function () {
        Route::post('/login', 'login')
            ->middleware('throttle:login-api')
            ->name('login');

        Route::middleware(['auth:sanctum', 'abilities:mobile', 'akun_api_aktif'])->group(function () {
            Route::get('/saya', 'saya')->name('saya');
            Route::put('/kata-sandi', 'ubahKataSandi')->name('kata-sandi.update');
            Route::post('/logout', 'logout')->name('logout');
        });
    });
