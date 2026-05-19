<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelas extends Model
{
    protected $table = 'kelas';

    protected $fillable = [
        'tahun_pelajaran_id',
        'wali_kelas_id',
        'nama',
        'tingkat',
        'kapasitas',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'tingkat' => 'integer',
        'kapasitas' => 'integer',
        'aktif' => 'boolean',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'wali_kelas_id');
    }

    public function anggotaKelas(): HasMany
    {
        return $this->hasMany(AnggotaKelas::class);
    }

    public function guruMataPelajaran(): HasMany
    {
        return $this->hasMany(GuruMataPelajaran::class);
    }

    public function absensiSiswa(): HasMany
    {
        return $this->hasMany(AbsensiSiswa::class);
    }

    public function siswa(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'anggota_kelas')
            ->withPivot([
                'id',
                'tahun_pelajaran_id',
                'nomor_absen',
                'status_keanggotaan',
                'tanggal_masuk',
                'tanggal_keluar',
                'keterangan',
            ])
            ->withTimestamps();
    }
}
