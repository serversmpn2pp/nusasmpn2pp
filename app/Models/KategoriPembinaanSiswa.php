<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriPembinaanSiswa extends Model
{
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
}
