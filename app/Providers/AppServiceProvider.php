<?php

namespace App\Providers;

use App\Models\AnggotaKelas;
use App\Models\Siswa;
use App\Observers\AnggotaKelasObserver;
use App\Observers\SiswaObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        RateLimiter::for('login-api', function (Request $request): array {
            $username = Str::lower(trim((string) $request->input('username')));
            $alamatIp = $request->ip();

            return [
                Limit::perMinute(5)->by('login-user:'.$username.'|'.$alamatIp),
                Limit::perMinute(60)->by('login-ip:'.$alamatIp),
            ];
        });

        Paginator::defaultView('vendor.pagination.nusa');
        Paginator::defaultSimpleView('vendor.pagination.nusa-simple');

        Blade::if('izin', function (string|array ...$kode): bool {
            $kode = count($kode) === 1 ? $kode[0] : $kode;

            return auth()->user()?->memilikiIzin($kode) ?? false;
        });
    }
}
