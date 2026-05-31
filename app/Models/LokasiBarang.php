<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LokasiBarang extends Model
{
    protected $table = 'lokasi_barang';

    public const DAFTAR_JENIS = [
        'gudang' => 'Gudang',
        'ruangan' => 'Ruangan',
        'kelas' => 'Kelas',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = [
        'kode',
        'nama',
        'jenis',
        'penanggung_jawab_pegawai_id',
        'deskripsi',
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'penanggung_jawab_pegawai_id');
    }

    public function barangSebagaiPenyimpanan(): HasMany
    {
        return $this->hasMany(Barang::class, 'lokasi_penyimpanan_id');
    }

    public function unitBarang(): HasMany
    {
        return $this->hasMany(UnitBarang::class);
    }

    public function saldoStokBarang(): HasMany
    {
        return $this->hasMany(SaldoStokBarang::class);
    }

    public function mutasiStokBarang(): HasMany
    {
        return $this->hasMany(MutasiStokBarang::class);
    }

    public function detailPeminjamanBarang(): HasMany
    {
        return $this->hasMany(DetailPeminjamanBarang::class);
    }

    public function labelJenis(): string
    {
        return self::DAFTAR_JENIS[$this->jenis] ?? str($this->jenis)->headline()->toString();
    }
}
