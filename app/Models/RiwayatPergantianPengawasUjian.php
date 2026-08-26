<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPergantianPengawasUjian extends Model
{
    protected $table = 'riwayat_pergantian_pengawas_ujian';

    protected $fillable = [
        'pengawas_ruang_ujian_terpusat_id',
        'jadwal_ujian_cbt_id',
        'ruang_kegiatan_ujian_cbt_id',
        'peran_pengawas',
        'pegawai_lama_id',
        'pegawai_baru_id',
        'alasan',
        'diganti_oleh_pengguna_id',
        'diganti_pada',
    ];

    protected $casts = [
        'diganti_pada' => 'datetime',
    ];

    public function penugasanPengawas(): BelongsTo
    {
        return $this->belongsTo(PengawasRuangUjianTerpusat::class, 'pengawas_ruang_ujian_terpusat_id');
    }

    public function jadwalUjianCbt(): BelongsTo
    {
        return $this->belongsTo(JadwalUjianCbt::class);
    }

    public function ruangKegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(RuangKegiatanUjianCbt::class);
    }

    public function pegawaiLama(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_lama_id');
    }

    public function pegawaiBaru(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_baru_id');
    }

    public function digantiOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diganti_oleh_pengguna_id');
    }

    public function labelPeran(): string
    {
        return $this->peran_pengawas === 'utama' ? 'Pengawas utama' : 'Pengawas pendamping';
    }
}
