<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetoranTahfidz extends Model
{
    protected $table = 'setoran_tahfidz';

    protected $fillable = ['anggota_tahfidz_id', 'nomor_surah', 'penilai_id', 'nilai', 'catatan', 'token_setoran'];

    protected $casts = ['nilai' => 'decimal:2'];

    public function pertemuan(): BelongsTo
    {
        return $this->belongsTo(PertemuanTahfidz::class, 'pertemuan_tahfidz_id');
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(AnggotaTahfidz::class, 'anggota_tahfidz_id');
    }

    public function surah(): BelongsTo
    {
        return $this->belongsTo(SurahTahfidz::class, 'nomor_surah', 'nomor');
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'penilai_id');
    }

    public function labelNilai(): string
    {
        return rtrim(rtrim(number_format((float) $this->nilai, 2, ',', ''), '0'), ',');
    }
}
