<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahunPelajaran extends Model
{
    protected $table = 'tahun_pelajaran';

    protected $fillable = [
        'nama',
        'tanggal_mulai',
        'tanggal_selesai',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'aktif' => 'boolean',
    ];

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }

    public function anggotaKelas(): HasMany
    {
        return $this->hasMany(AnggotaKelas::class);
    }

    public function absensiSiswa(): HasMany
    {
        return $this->hasMany(AbsensiSiswa::class);
    }

    public function laporanPembinaanSiswa(): HasMany
    {
        return $this->hasMany(LaporanPembinaanSiswa::class);
    }

    public function guruMataPelajaran(): HasMany
    {
        return $this->hasMany(GuruMataPelajaran::class);
    }

    public function skemaBobotNilai(): HasMany
    {
        return $this->hasMany(SkemaBobotNilai::class);
    }
}
