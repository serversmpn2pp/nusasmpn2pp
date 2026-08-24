<?php

use App\Http\Middleware\PastikanAdministrator;
use App\Http\Middleware\PastikanAksesUjianCbt;
use App\Http\Middleware\PastikanAkunApiAktif;
use App\Http\Middleware\PastikanAkunPegawai;
use App\Http\Middleware\PastikanIdentitasSesi;
use App\Http\Middleware\PastikanIzin;
use App\Http\Middleware\PastikanKataSandiBukanDefault;
use App\Http\Middleware\PastikanUjianCbtTerpusat;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare Tunnel meneruskan HTTPS ke origin lokal melalui proxy.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => PastikanAdministrator::class,
            'akses_ujian_cbt' => PastikanAksesUjianCbt::class,
            'abilities' => CheckAbilities::class,
            'akun_api_aktif' => PastikanAkunApiAktif::class,
            'akun_pegawai' => PastikanAkunPegawai::class,
            'identitas_sesi' => PastikanIdentitasSesi::class,
            'izin' => PastikanIzin::class,
            'kata_sandi_bukan_default' => PastikanKataSandiBukanDefault::class,
            'ujian_terpusat' => PastikanUjianCbtTerpusat::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
