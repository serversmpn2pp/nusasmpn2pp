<?php

namespace App\Http\Middleware;

use App\Services\Absensi\AksesPerangkatScan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class PastikanPerangkatScan
{
    public function __construct(private readonly AksesPerangkatScan $akses) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ipKey = 'scanner-ip:'.hash('sha256', $request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($ipKey, 600)) {
            return $this->privat(response()->json(['berhasil' => false, 'status' => 'terlalu_banyak_scan', 'pesan' => 'Terlalu banyak permintaan scanner. Tunggu sebentar.'], 429));
        }
        RateLimiter::hit($ipKey, 60);
        $jenis = $request->route('jenis_scanner');
        $device = $this->akses->perangkat($request);
        if (! $device || ! $device->mengizinkan($jenis)) {
            $message = $device ? 'Browser ini tidak diizinkan untuk scan '.$jenis.'. Hubungi administrator.' : 'Akses browser scanner belum aktif atau telah dicabut. Aktifkan kembali melalui administrator.';
            $response = $request->expectsJson() || ! $request->isMethod('GET')
                ? response()->json(['berhasil' => false, 'status' => 'perangkat_tidak_aktif', 'pesan' => $message], $device ? 403 : 401)
                : response()->view('perangkat-scan.belum-aktif', ['pesan' => $message, 'jenis' => $jenis], $device ? 403 : 401);

            return $this->privat($response);
        }

        $token = $this->akses->credential($request);
        if (! $request->isMethodSafe()) {
            $origin = $request->header('Origin');
            $sameOrigin = ! $origin || $origin === $request->getSchemeAndHttpHost();
            $csrf = $request->header('X-Scanner-CSRF');
            if (! $sameOrigin || $request->header('Sec-Fetch-Site') === 'cross-site' || ! is_string($csrf) || ! hash_equals($this->akses->csrf($token), $csrf)) {
                return $this->privat(response()->json(['berhasil' => false, 'status' => 'token_scanner_berubah', 'pesan' => 'Otorisasi scanner berubah. Sinkronkan ulang scanner.'], 419));
            }
            $key = 'scanner-device:'.$device->id.':'.$jenis;
            if (RateLimiter::tooManyAttempts($key, 180)) {
                return $this->privat(response()->json(['berhasil' => false, 'status' => 'terlalu_banyak_scan', 'pesan' => 'Terlalu banyak permintaan. Tunggu sebentar lalu scan kembali.'], 429)->header('Retry-After', RateLimiter::availableIn($key)));
            }
            RateLimiter::hit($key, 60);
        }

        $request->attributes->set('perangkat_scan', $device);
        $request->attributes->set('scanner_csrf', $this->akses->csrf($token));
        if (! $device->terakhir_digunakan_pada || $device->terakhir_digunakan_pada->lt(now()->subMinute())) {
            $device->forceFill(['terakhir_digunakan_pada' => now(), 'ip_terakhir' => $request->ip()])->save();
        }
        $response = $this->privat($next($request));
        $response->headers->setCookie($this->akses->cookie($request, $token));

        return $response;
    }

    private function privat(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
