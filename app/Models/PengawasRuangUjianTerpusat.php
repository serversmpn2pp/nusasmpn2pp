<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengawasRuangUjianTerpusat extends Model
{
    protected $table = 'pengawas_ruang_ujian_terpusat';

    protected $fillable = [
        'jadwal_ujian_cbt_id',
        'ruang_kegiatan_ujian_cbt_id',
        'pengawas_utama_pegawai_id',
        'pengawas_pendamping_pegawai_id',
        'catatan',
        'ditugaskan_oleh_pengguna_id',
    ];

    public function jadwalUjianCbt(): BelongsTo
    {
        return $this->belongsTo(JadwalUjianCbt::class);
    }

    public function ruangKegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(RuangKegiatanUjianCbt::class);
    }

    public function pengawasUtama(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pengawas_utama_pegawai_id');
    }

    public function pengawasPendamping(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pengawas_pendamping_pegawai_id');
    }

    public function ditugaskanOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'ditugaskan_oleh_pengguna_id');
    }

    public function riwayatPergantian(): HasMany
    {
        return $this->hasMany(RiwayatPergantianPengawasUjian::class)
            ->latest('diganti_pada')
            ->latest('id');
    }
}
