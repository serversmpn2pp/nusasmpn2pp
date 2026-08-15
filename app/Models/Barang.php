<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends Model
{
    protected $table = 'barang';

    public const DAFTAR_JENIS_BARANG = [
        'habis_pakai' => 'Barang habis pakai',
        'tidak_habis_pakai' => 'Barang tidak habis pakai',
    ];

    public const DAFTAR_TIPE_PENGELOLAAN = [
        'aset_individual' => 'Aset individual',
        'stok_dikembalikan' => 'Stok yang dikembalikan',
        'habis_pakai' => 'Barang habis pakai',
    ];

    protected $fillable = [
        'kode',
        'nama',
        'kategori_barang_id',
        'satuan_barang_id',
        'lokasi_penyimpanan_id',
        'tipe_pengelolaan',
        'jenis_barang',
        'stok_minimum',
        'deskripsi',
        'aktif',
    ];

    protected $casts = [
        'stok_minimum' => 'decimal:2',
        'aktif' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Barang $barang) {
            if (! filled($barang->jenis_barang)) {
                $barang->jenis_barang = $barang->tipe_pengelolaan === 'habis_pakai'
                    ? 'habis_pakai'
                    : 'tidak_habis_pakai';
            }
        });
    }

    public function kategoriBarang(): BelongsTo
    {
        return $this->belongsTo(KategoriBarang::class);
    }

    public function satuanBarang(): BelongsTo
    {
        return $this->belongsTo(SatuanBarang::class);
    }

    public function lokasiPenyimpanan(): BelongsTo
    {
        return $this->belongsTo(LokasiBarang::class, 'lokasi_penyimpanan_id');
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

    public function detailPenerimaanBarang(): HasMany
    {
        return $this->hasMany(DetailPenerimaanBarang::class);
    }

    public function pengajuanBarang(): HasMany
    {
        return $this->hasMany(PengajuanBarang::class);
    }

    public function labelTipePengelolaan(): string
    {
        return self::DAFTAR_TIPE_PENGELOLAAN[$this->tipe_pengelolaan]
            ?? str($this->tipe_pengelolaan)->headline()->toString();
    }

    public function labelJenisBarang(): string
    {
        return self::DAFTAR_JENIS_BARANG[$this->jenis_barang]
            ?? str($this->jenis_barang)->headline()->toString();
    }
}
