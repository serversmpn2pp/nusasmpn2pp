<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\AksesMonitorTv;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class PastikanMonitorTv
{
    public function __construct(private readonly AksesMonitorTv $akses) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'monitor-tv:'.hash('sha256', $request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 240)) {
            $response = response()->json(['pesan' => 'Terlalu banyak permintaan monitoring.'], 429)->header('Retry-After', 60);
        } else {
            RateLimiter::hit($key, 60);
            $perangkat = $this->akses->perangkat($request);
            if (! $perangkat) {
                $response = $request->expectsJson()
                    ? response()->json(['pesan' => 'Akses layar monitoring belum aktif atau telah dicabut.'], 401)
                    : response()->view('monitoring-siswa.belum-aktif', [], 401);
            } elseif (! $perangkat->mengizinkan($request->route('jenis_monitor') ?? 'siswa')) {
                $response = $request->expectsJson()
                    ? response()->json(['pesan' => 'Browser ini tidak diizinkan untuk layar monitoring tersebut.'], 403)
                    : response()->view('monitoring-siswa.belum-aktif', [], 403);
            } else {
                if (! $request->isMethodSafe()) {
                    $origin = $request->header('Origin');
                    $csrf = $request->header('X-Monitor-CSRF');
                    if (($origin && $origin !== $request->getSchemeAndHttpHost()) || $request->header('Sec-Fetch-Site') === 'cross-site'
                        || ! is_string($csrf) || ! hash_equals($this->akses->csrf($this->akses->token($request)), $csrf)) {
                        return $this->privat(response()->json(['pesan' => 'Otorisasi layar berubah. Muat ulang halaman.'], 419));
                    }
                }
                $request->attributes->set('monitor_tv', $perangkat);
                $request->attributes->set('monitor_csrf', $this->akses->csrf($this->akses->token($request)));
                if (! $perangkat->terakhir_digunakan_pada || $perangkat->terakhir_digunakan_pada->lt(now()->subMinute())) {
                    $perangkat->update(['terakhir_digunakan_pada' => now()]);
                }
                $response = $next($request);
                $response->headers->setCookie($this->akses->cookie($request, $this->akses->token($request)));
            }
        }

        return $this->privat($response);
    }

    private function privat(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
