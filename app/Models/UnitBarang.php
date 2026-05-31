<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitBarang extends Model
{
    protected $table = 'unit_barang';

    public const DAFTAR_KONDISI = [
        'baik' => 'Baik',
        'rusak_ringan' => 'Rusak ringan',
        'rusak_berat' => 'Rusak berat',
    ];

    public const DAFTAR_STATUS = [
        'tersedia' => 'Tersedia',
        'dipinjam' => 'Dipinjam',
        'dalam_perbaikan' => 'Dalam perbaikan',
        'hilang' => 'Hilang',
        'dihapuskan' => 'Dihapuskan',
    ];

    protected $fillable = [
        'barang_id',
        'nomor_unit',
        'kode_inventaris',
        'lokasi_barang_id',
        'nomor_seri',
        'kondisi',
        'status_unit',
        'tanggal_perolehan',
        'sumber_perolehan',
        'harga_perolehan',
        'keterangan',
        'aktif',
    ];

    protected $casts = [
        'nomor_unit' => 'integer',
        'tanggal_perolehan' => 'date',
        'harga_perolehan' => 'decimal:2',
        'aktif' => 'boolean',
    ];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function lokasiBarang(): BelongsTo
    {
        return $this->belongsTo(LokasiBarang::class);
    }

    public function detailPeminjamanBarang(): HasMany
    {
        return $this->hasMany(DetailPeminjamanBarang::class);
    }

    public function labelKondisi(): string
    {
        return self::DAFTAR_KONDISI[$this->kondisi] ?? str($this->kondisi)->headline()->toString();
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status_unit] ?? str($this->status_unit)->headline()->toString();
    }
}
