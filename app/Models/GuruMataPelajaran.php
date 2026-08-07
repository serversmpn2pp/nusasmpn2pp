<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuruMataPelajaran extends Model
{
    protected $table = 'guru_mata_pelajaran';

    protected $fillable = [
        'tahun_pelajaran_id',
        'kelas_id',
        'mata_pelajaran_id',
        'pegawai_id',
        'jenis_penugasan',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function komponenNilai(): HasMany
    {
        return $this->hasMany(KomponenNilai::class);
    }

    public function publikasiNilaiSiswa(): HasMany
    {
        return $this->hasMany(PublikasiNilaiSiswa::class);
    }

    public function surveiPembelajaran(): HasMany
    {
        return $this->hasMany(SurveiPembelajaran::class);
    }

    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class);
    }

    public function riwayatPergantian(): HasMany
    {
        return $this->hasMany(RiwayatPergantianGuruMapel::class)
            ->latest('tanggal_efektif')
            ->latest('id');
    }
}
