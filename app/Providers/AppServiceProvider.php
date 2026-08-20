<?php

namespace App\Providers;

use App\Models\AnggotaKelas;
use App\Models\Siswa;
use App\Observers\AnggotaKelasObserver;
use App\Observers\SiswaObserver;
use Illuminate\Pagination\Paginator;
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
        AnggotaKelas::observe(AnggotaKelasObserver::class);
        Siswa::observe(SiswaObserver::class);

        Paginator::defaultView('vendor.pagination.nusa');
        Paginator::defaultSimpleView('vendor.pagination.nusa-simple');

        Blade::if('izin', function (string|array ...$kode): bool {
            $kode = count($kode) === 1 ? $kode[0] : $kode;

            return auth()->user()?->memilikiIzin($kode) ?? false;
        });
    }
}
