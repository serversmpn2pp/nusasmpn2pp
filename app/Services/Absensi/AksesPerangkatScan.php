<?php

namespace App\Services\Absensi;

use App\Models\PerangkatScanPresensi;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class AksesPerangkatScan
{
    public const COOKIE = 'nusa_perangkat_scan';

    public function credential(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && preg_match('/^[a-zA-Z0-9]{80}$/D', $token) ? $token : null;
    }

    public function perangkat(Request $request): ?PerangkatScanPresensi
    {
        $token = $this->credential($request);

        return $token ? PerangkatScanPresensi::where('token_hash', hash('sha256', $token))->whereNull('dicabut_pada')->first() : null;
    }

    public function csrf(string $token): string
    {
        return hash_hmac('sha256', 'nusa-scanner-csrf|'.$token, config('app.key'));
    }

    public function cookie(Request $request, string $token): Cookie
    {
        // Renew independently of login; browsers cap persistent cookies at 400 days.
        return new Cookie(self::COOKIE, $token, now()->addDays(400), '/', null, $request->isSecure() || config('session.secure') === true, true, false, Cookie::SAMESITE_STRICT);
    }

    public function lupa(Request $request): Cookie
    {
        return $this->cookie($request, '')->withExpires(1);
    }
}
