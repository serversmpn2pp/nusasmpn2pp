<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisPelanggaranSiswa extends Model
{
    protected $table = 'jenis_pelanggaran_siswa';

    protected $fillable = [
        'kategori_pembinaan_siswa_id',
        'kode',
        'nama',
        'tingkat',
        'poin',
        'urutan',
        'aktif',
    ];

    protected $casts = [
        'kategori_pembinaan_siswa_id' => 'integer',
        'poin' => 'integer',
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    public function kategoriPembinaanSiswa(): BelongsTo
    {
        return $this->belongsTo(KategoriPembinaanSiswa::class);
    }

    public function butirPelanggaranLaporan(): HasMany
    {
        return $this->hasMany(ButirPelanggaranLaporan::class);
    }
}
