<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Siswa extends Model
{
    protected $table = 'siswa';

    protected $fillable = [
        'nama_lengkap',
        'nis',
        'nisn',
        'nik',
        'foto',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'status_dalam_keluarga',
        'anak_ke',
        'nama_ayah',
        'nama_ibu',
        'pekerjaan_ayah',
        'pekerjaan_ibu',
        'alamat',
        'sekolah_asal',
        'keterangan',
        'aktif',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'anak_ke' => 'integer',
        'aktif' => 'boolean',
    ];

    public function anggotaKelas(): HasMany
    {
        return $this->hasMany(AnggotaKelas::class);
    }

    public function nilaiSiswa(): HasMany
    {
        return $this->hasMany(NilaiSiswa::class);
    }

    public function absensiSiswa(): HasMany
    {
        return $this->hasMany(AbsensiSiswa::class);
    }

    public function laporanPembinaanSiswa(): HasMany
    {
        return $this->hasMany(LaporanPembinaanSiswa::class);
    }

    public function logScanAbsensi(): HasMany
    {
        return $this->hasMany(LogScanAbsensi::class);
    }

    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'anggota_kelas')
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
