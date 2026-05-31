<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pegawai extends Model
{
    protected $table = 'pegawai';

    protected $fillable = [
        'nama_lengkap',
        'nip',
        'nuptk',
        'nik',
        'foto',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'email',
        'no_hp',
        'status_kepegawaian',
        'golongan',
        'tanggal_mulai_kerja',
        'tanggal_mulai_bertugas',
        'jenis_pegawai',
        'jabatan_utama',
        'sumber_gaji',
        'pendidikan_terakhir',
        'jurusan_pendidikan',
        'tahun_lulus',
        'keterangan',
        'aktif',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_mulai_kerja' => 'date',
        'tanggal_mulai_bertugas' => 'date',
        'aktif' => 'boolean',
    ];

    public function kelasSebagaiWali(): HasMany
    {
        return $this->hasMany(Kelas::class, 'wali_kelas_id');
    }

    public function guruMataPelajaran(): HasMany
    {
        return $this->hasMany(GuruMataPelajaran::class);
    }

    public function pengguna(): HasOne
    {
        return $this->hasOne(Pengguna::class);
    }

    public function pengaturanAbsensiPegawai(): HasMany
    {
        return $this->hasMany(PengaturanAbsensiPegawai::class);
    }

    public function absensiPegawai(): HasMany
    {
        return $this->hasMany(AbsensiPegawai::class);
    }

    public function logScanAbsensiPegawai(): HasMany
    {
        return $this->hasMany(LogScanAbsensiPegawai::class);
    }

    public function laporanPembinaanSiswaSebagaiPelapor(): HasMany
    {
        return $this->hasMany(LaporanPembinaanSiswa::class, 'pelapor_pegawai_id');
    }

    public function tindakLanjutPembinaanSiswaSebagaiPetugas(): HasMany
    {
        return $this->hasMany(TindakLanjutPembinaanSiswa::class, 'petugas_pegawai_id');
    }

    public function perangkatAjar(): HasMany
    {
        return $this->hasMany(PerangkatAjar::class);
    }

    public function lokasiBarangSebagaiPenanggungJawab(): HasMany
    {
        return $this->hasMany(LokasiBarang::class, 'penanggung_jawab_pegawai_id');
    }

    public function peminjamanBarang(): HasMany
    {
        return $this->hasMany(PeminjamanBarang::class);
    }
}
