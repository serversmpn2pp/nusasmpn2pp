<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerangkatMonitoring extends Model
{
    public const CAKUPAN = ['siswa' => 'Aktivitas siswa', 'ujian' => 'Ujian terpusat', 'keduanya' => 'Aktivitas siswa dan ujian terpusat'];

    protected $table = 'perangkat_monitoring';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected $casts = ['terakhir_digunakan_pada' => 'datetime', 'dicabut_pada' => 'datetime'];

    public function kegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(KegiatanUjianCbt::class);
    }

    public function mengizinkan(string $jenis): bool
    {
        return ! $this->dicabut_pada && in_array($this->cakupan ?? 'siswa', [$jenis, 'keduanya'], true)
            && ($jenis !== 'ujian' || $this->kegiatan_ujian_cbt_id !== null);
    }
}
