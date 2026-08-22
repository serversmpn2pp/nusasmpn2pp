<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuangKegiatanUjianCbt extends Model
{
    protected $table = 'ruang_kegiatan_ujian_cbt';

    protected $fillable = [
        'kegiatan_ujian_cbt_id',
        'kode',
        'nama',
        'lokasi',
        'kapasitas',
        'urutan',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'kapasitas' => 'integer',
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    public function kegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(KegiatanUjianCbt::class);
    }

    public function kelompokPesertaKegiatanUjianCbt(): BelongsToMany
    {
        return $this->belongsToMany(
            KelompokPesertaKegiatanUjianCbt::class,
            'kelompok_peserta_kegiatan_ujian_cbt_ruang'
        )->withPivot('urutan')->withTimestamps();
    }

    public function penempatanPesertaUjianCbt(): HasMany
    {
        return $this->hasMany(PenempatanPesertaUjianCbt::class);
    }

    public function pengawasRuangUjianTerpusat(): HasMany
    {
        return $this->hasMany(PengawasRuangUjianTerpusat::class);
    }
}
