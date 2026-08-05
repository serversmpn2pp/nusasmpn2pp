<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\PastikanAdministrator;
use App\Http\Middleware\PastikanIzin;
use App\Http\Middleware\PastikanKataSandiBukanDefault;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare Tunnel meneruskan HTTPS ke origin lokal melalui proxy.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => PastikanAdministrator::class,
            'izin' => PastikanIzin::class,
            'kata_sandi_bukan_default' => PastikanKataSandiBukanDefault::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
