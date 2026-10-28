<?php

namespace App\Services\Monitoring;

use App\Models\PerangkatMonitoring;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class AksesMonitorTv
{
    public const COOKIE = 'nusa_monitor_tv';

    public function token(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && preg_match('/^[a-zA-Z0-9]{80}$/D', $token) ? $token : null;
    }

    public function perangkat(Request $request): ?PerangkatMonitoring
    {
        $token = $this->token($request);

        return $token ? PerangkatMonitoring::where('token_hash', hash('sha256', $token))->whereNull('dicabut_pada')->first() : null;
    }

    public function csrf(string $token): string
    {
        return hash_hmac('sha256', 'monitor-tv-settings:'.$token, (string) config('app.key'));
    }

    public function cookie(Request $request, string $token): Cookie
    {
        return new Cookie(self::COOKIE, $token, now()->addDays(400), '/', null,
            $request->isSecure() || config('session.secure') === true, true, false, Cookie::SAMESITE_STRICT);
    }
}
