<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HasilScanLjkUjianOmr extends Model
{
    protected $table = 'hasil_scan_ljk_ujian_omr';

    protected $fillable = [
        'batch_scan_ujian_omr_id',
        'lembar_jawab_ujian_omr_id',
        'halaman_pdf',
        'urutan_ljk',
        'token_terbaca',
        'lokasi_pratinjau',
        'status',
        'jumlah_benar',
        'jumlah_salah',
        'jumlah_kosong',
        'jumlah_ganda',
        'nilai',
        'nilai_siswa_id',
        'diterapkan_pada',
        'diterapkan_oleh_pengguna_id',
        'catatan',
        'catatan_koreksi',
        'dikoreksi_pada',
        'dikoreksi_oleh_pengguna_id',
    ];

    protected $casts = [
        'halaman_pdf' => 'integer',
        'urutan_ljk' => 'integer',
        'jumlah_benar' => 'integer',
        'jumlah_salah' => 'integer',
        'jumlah_kosong' => 'integer',
        'jumlah_ganda' => 'integer',
        'nilai' => 'decimal:2',
        'diterapkan_pada' => 'datetime',
        'dikoreksi_pada' => 'datetime',
    ];

    public function batchScanUjianOmr(): BelongsTo
    {
        return $this->belongsTo(BatchScanUjianOmr::class);
    }

    public function lembarJawabUjianOmr(): BelongsTo
    {
        return $this->belongsTo(LembarJawabUjianOmr::class);
    }

    public function nilaiSiswa(): BelongsTo
    {
        return $this->belongsTo(NilaiSiswa::class);
    }

    public function diterapkanOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diterapkan_oleh_pengguna_id');
    }

    public function dikoreksiOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dikoreksi_oleh_pengguna_id');
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(JawabanHasilScanUjianOmr::class);
    }
}
