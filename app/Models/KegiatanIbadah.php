<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KegiatanIbadah extends Model
{
    protected $table = 'kegiatan_ibadah';

    protected $fillable = [
        'kode',
        'nama',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function jadwal(): HasMany
    {
        return $this->hasMany(JadwalKegiatanIbadah::class);
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(PresensiKegiatanIbadah::class);
    }

    public function logScan(): HasMany
    {
        return $this->hasMany(LogScanKegiatanIbadah::class);
    }

    public function presensiBerhalangan(): HasMany
    {
        return $this->hasMany(PresensiBerhalanganIbadah::class);
    }

    public function logScanBerhalangan(): HasMany
    {
        return $this->hasMany(LogScanBerhalanganIbadah::class);
    }
}
