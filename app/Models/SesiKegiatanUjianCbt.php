<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesiKegiatanUjianCbt extends Model
{
    protected $table = 'sesi_kegiatan_ujian_cbt';

    protected $fillable = [
        'kegiatan_ujian_cbt_id',
        'kode',
        'nama',
        'waktu_mulai',
        'waktu_selesai',
        'urutan',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    public function kegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(KegiatanUjianCbt::class);
    }

    public function jadwalUjianCbt(): HasMany
    {
        return $this->hasMany(JadwalUjianCbt::class);
    }

    public function kelompokPesertaKegiatanUjianCbt(): HasMany
    {
        return $this->hasMany(KelompokPesertaKegiatanUjianCbt::class);
    }

    public function labelWaktu(): string
    {
        return substr((string) $this->waktu_mulai, 0, 5).' - '.substr((string) $this->waktu_selesai, 0, 5);
    }
}
