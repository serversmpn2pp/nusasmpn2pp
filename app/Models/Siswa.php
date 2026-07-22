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
        'nomor_wa_ayah',
        'nama_ibu',
        'nomor_wa_ibu',
        'pekerjaan_ayah',
        'pekerjaan_ibu',
        'nama_wali',
        'hubungan_wali',
        'nomor_wa_wali',
        'kontak_absensi_utama',
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

    public function penugasanGuruWaliSiswa(): HasMany
    {
        return $this->hasMany(PenugasanGuruWaliSiswa::class);
    }

    public function transaksiPoinSiswa(): HasMany
    {
        return $this->hasMany(TransaksiPoinSiswa::class);
    }

    public function penguranganPoinSiswa(): HasMany
    {
        return $this->hasMany(PenguranganPoinSiswa::class);
    }

    public function sanksiPoinSiswa(): HasMany
    {
        return $this->hasMany(SanksiPoinSiswa::class);
    }

    public function logScanAbsensi(): HasMany
    {
        return $this->hasMany(LogScanAbsensi::class);
    }

    public function notifikasiAbsensiSiswa(): HasMany
    {
        return $this->hasMany(NotifikasiAbsensiSiswa::class);
    }

    public function peminjamanBarang(): HasMany
    {
        return $this->hasMany(PeminjamanBarang::class);
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
