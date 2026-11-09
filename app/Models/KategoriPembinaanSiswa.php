<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class KategoriPembinaanSiswa extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $kategori): void {
            if ($kategori->isDirty('aktif') && ! $kategori->aktif && $kategori->jenisPelanggaranSiswa()->get()
                ->contains(fn ($jenis) => $jenis->dipakaiPresensiManualTahunAktif())) {
                throw ValidationException::withMessages(['aktif' => 'Ganti aturan presensi manual pada tahun aktif sebelum menonaktifkan kategori ini.']);
            }
        });
    }

    protected $table = 'kategori_pembinaan_siswa';

    protected $fillable = [
        'nama',
        'kode',
        'deskripsi',
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function laporanPembinaanSiswa(): HasMany
    {
        return $this->hasMany(LaporanPembinaanSiswa::class);
    }

    public function jenisPelanggaranSiswa(): HasMany
    {
        return $this->hasMany(JenisPelanggaranSiswa::class);
    }
}
