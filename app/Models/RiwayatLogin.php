<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatLogin extends Model
{
    protected $table = 'riwayat_login';

    protected $fillable = [
        'pengguna_id',
        'username',
        'berhasil',
        'alamat_ip',
        'user_agent',
    ];

    protected $casts = [
        'berhasil' => 'boolean',
    ];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }

    public function labelPerangkat(): string
    {
        $userAgent = strtolower((string) $this->user_agent);

        if ($userAgent === '') {
            return 'Perangkat tidak diketahui';
        }

        $perangkat = match (true) {
            str_contains($userAgent, 'android') => 'Android',
            str_contains($userAgent, 'iphone'), str_contains($userAgent, 'ipad') => 'iPhone/iPad',
            str_contains($userAgent, 'windows') => 'Windows',
            str_contains($userAgent, 'macintosh'), str_contains($userAgent, 'mac os') => 'Mac',
            str_contains($userAgent, 'linux') => 'Linux',
            default => 'Perangkat lain',
        };

        $browser = match (true) {
            str_contains($userAgent, 'edg/') => 'Edge',
            str_contains($userAgent, 'opr/'), str_contains($userAgent, 'opera') => 'Opera',
            str_contains($userAgent, 'firefox/') => 'Firefox',
            str_contains($userAgent, 'chrome/'), str_contains($userAgent, 'crios/') => 'Chrome',
            str_contains($userAgent, 'safari/') => 'Safari',
            default => 'Browser lain',
        };

        return $perangkat.' - '.$browser;
    }
}
