<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function pengaturanBatasProsesPelanggaran(): HasOne
    {
        return $this->hasOne(PengaturanBatasProsesPelanggaran::class);
    }

    public function pengaturanPoinKeterlambatan(): HasOne
    {
        return $this->hasOne(PengaturanPoinKeterlambatan::class);
    }

    public function pengaturanPeringatanDiniPoin(): HasOne
    {
        return $this->hasOne(PengaturanPeringatanDiniPoin::class);
    }

    public function peringatanDiniSiswa(): HasMany
    {
        return $this->hasMany(PeringatanDiniSiswa::class);
    }

    public function pendampinganSiswa(): HasMany
    {
        return $this->hasMany(PendampinganSiswa::class);
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

    public function jadwalPiketGuru(): HasMany
    {
        return $this->hasMany(JadwalPiketGuru::class);
    }

    public function jadwalKegiatanIbadah(): HasMany
    {
        return $this->hasMany(JadwalKegiatanIbadah::class);
    }

    public function presensiKegiatanIbadah(): HasMany
    {
        return $this->hasMany(PresensiKegiatanIbadah::class);
    }

    public function pengaturanMataPelajaran(): HasMany
    {
        return $this->hasMany(PengaturanMataPelajaran::class);
    }

    public function skemaBobotNilai(): HasMany
    {
        return $this->hasMany(SkemaBobotNilai::class);
    }

    public function perangkatAjar(): HasMany
    {
        return $this->hasMany(PerangkatAjar::class);
    }

    public function ujianOmr(): HasMany
    {
        return $this->hasMany(UjianOmr::class);
    }

    public function ujianCbt(): HasMany
    {
        return $this->hasMany(UjianCbt::class);
    }

    public function soalCbt(): HasMany
    {
        return $this->hasMany(SoalCbt::class);
    }
}
