<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::if('izin', function (string|array ...$kode): bool {
            $kode = count($kode) === 1 ? $kode[0] : $kode;

            return auth()->user()?->memilikiIzin($kode) ?? false;
        });
    }
}
