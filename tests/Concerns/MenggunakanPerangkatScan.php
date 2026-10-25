<?php

namespace Tests\Concerns;

use App\Models\PerangkatScanPresensi;
use App\Services\Absensi\AksesPerangkatScan;
use Illuminate\Support\Str;

trait MenggunakanPerangkatScan
{
    private function aktifkanBrowserScan(string $cakupan = 'keduanya'): string
    {
        $token = Str::random(80);
        PerangkatScanPresensi::create(['nama' => 'Scanner pengujian', 'cakupan' => $cakupan, 'token_hash' => hash('sha256', $token)]);
        $this->withCredentials()->withCookie(AksesPerangkatScan::COOKIE, $token)->withHeader('X-Scanner-CSRF', app(AksesPerangkatScan::class)->csrf($token));

        return $token;
    }
}
