<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenempatanPesertaUjianCbt extends Model
{
    protected $table = 'penempatan_peserta_ujian_cbt';

    protected $fillable = [
        'kelompok_peserta_kegiatan_ujian_cbt_id',
        'anggota_kelas_id',
        'ruang_kegiatan_ujian_cbt_id',
        'nomor_meja',
        'nomor_peserta',
    ];

    protected $casts = [
        'nomor_meja' => 'integer',
    ];

    public function kelompokPesertaKegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(KelompokPesertaKegiatanUjianCbt::class);
    }

    public function anggotaKelas(): BelongsTo
    {
        return $this->belongsTo(AnggotaKelas::class);
    }

    public function ruangKegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(RuangKegiatanUjianCbt::class);
    }
}
