<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnggotaKelas extends Model
{
    protected $table = 'anggota_kelas';

    protected $fillable = [
        'tahun_pelajaran_id',
        'kelas_id',
        'siswa_id',
        'nomor_absen',
        'status_keanggotaan',
        'tanggal_masuk',
        'tanggal_keluar',
        'keterangan',
    ];

    protected $casts = [
        'nomor_absen' => 'integer',
        'tanggal_masuk' => 'date',
        'tanggal_keluar' => 'date',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function absensiSiswa(): HasMany
    {
        return $this->hasMany(AbsensiSiswa::class);
    }

    public function laporanPembinaanSiswa(): HasMany
    {
        return $this->hasMany(LaporanPembinaanSiswa::class);
    }
}
