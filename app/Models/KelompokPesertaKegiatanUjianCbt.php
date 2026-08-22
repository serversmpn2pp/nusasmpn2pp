<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelompokPesertaKegiatanUjianCbt extends Model
{
    protected $table = 'kelompok_peserta_kegiatan_ujian_cbt';

    protected $fillable = [
        'kegiatan_ujian_cbt_id',
        'sesi_kegiatan_ujian_cbt_id',
        'tingkat',
        'jumlah_peserta',
        'total_kapasitas',
        'dibangkitkan_pada',
        'dibangkitkan_oleh_pengguna_id',
    ];

    protected $casts = [
        'tingkat' => 'integer',
        'jumlah_peserta' => 'integer',
        'total_kapasitas' => 'integer',
        'dibangkitkan_pada' => 'datetime',
    ];

    public function kegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(KegiatanUjianCbt::class);
    }

    public function sesiKegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(SesiKegiatanUjianCbt::class);
    }

    public function dibangkitkanOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibangkitkan_oleh_pengguna_id');
    }

    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(
            Kelas::class,
            'kelompok_peserta_kegiatan_ujian_cbt_kelas'
        )->withTimestamps();
    }

    public function ruangKegiatanUjianCbt(): BelongsToMany
    {
        return $this->belongsToMany(
            RuangKegiatanUjianCbt::class,
            'kelompok_peserta_kegiatan_ujian_cbt_ruang'
        )->withPivot('urutan')->withTimestamps()->orderByPivot('urutan');
    }

    public function penempatanPesertaUjianCbt(): HasMany
    {
        return $this->hasMany(PenempatanPesertaUjianCbt::class);
    }
}
