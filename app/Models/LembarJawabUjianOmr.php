<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LembarJawabUjianOmr extends Model
{
    protected $table = 'lembar_jawab_ujian_omr';

    protected $fillable = [
        'ujian_omr_id',
        'kelas_ujian_omr_id',
        'anggota_kelas_id',
        'versi_soal_ujian_omr_id',
        'token',
        'status',
        'dibuat_oleh_pengguna_id',
    ];

    public function ujianOmr(): BelongsTo
    {
        return $this->belongsTo(UjianOmr::class);
    }

    public function kelasUjianOmr(): BelongsTo
    {
        return $this->belongsTo(KelasUjianOmr::class);
    }

    public function anggotaKelas(): BelongsTo
    {
        return $this->belongsTo(AnggotaKelas::class);
    }

    public function versiSoalUjianOmr(): BelongsTo
    {
        return $this->belongsTo(VersiSoalUjianOmr::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function hasilScan(): HasMany
    {
        return $this->hasMany(HasilScanLjkUjianOmr::class);
    }
}
