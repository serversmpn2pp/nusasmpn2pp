<?php

namespace App\Services\Monitoring;

use Closure;
use Illuminate\Support\Facades\Cache;

final class CacheMonitoringTv
{
    public const PREFIX = 'monitoring-tv:v3:';

    public static function ingat(string $konteks, int $detik, Closure $bangun): array
    {
        $kunci = self::PREFIX.$konteks;
        $interval = intdiv(now()->timestamp, $detik);
        $cache = Cache::get($kunci);

        if (($cache['interval'] ?? null) === $interval) {
            return $cache['data'];
        }

        // Pertahankan batas interval lama tanpa membuat kunci baru setiap kali polling.
        $data = $bangun();

        if (intdiv(now()->timestamp, $detik) === $interval) {
            Cache::put($kunci, ['interval' => $interval, 'data' => $data], $detik);
        }

        return $data;
    }
}
