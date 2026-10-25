<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerangkatScanPresensi extends Model
{
    protected $table = 'perangkat_scan_presensi';

    public const CAKUPAN = ['siswa' => 'Scan siswa', 'pegawai' => 'Scan pegawai', 'keduanya' => 'Siswa dan pegawai'];

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected $casts = ['terakhir_digunakan_pada' => 'datetime', 'dicabut_pada' => 'datetime'];

    public function pengaktif(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diaktifkan_oleh');
    }

    public function mengizinkan(string $jenis): bool
    {
        return ! $this->dicabut_pada && in_array($this->cakupan, [$jenis, 'keduanya'], true);
    }
}
