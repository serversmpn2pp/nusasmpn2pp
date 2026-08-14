<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetailPenerimaanBarang extends Model
{
    protected $table = 'detail_penerimaan_barang';

    protected $fillable = [
        'penerimaan_barang_id',
        'barang_id',
        'lokasi_barang_id',
        'jumlah',
        'harga_satuan',
        'merek',
        'tipe',
        'kondisi',
        'keterangan',
        'mutasi_stok_barang_id',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'harga_satuan' => 'decimal:2',
    ];

    public function penerimaanBarang(): BelongsTo
    {
        return $this->belongsTo(PenerimaanBarang::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function lokasiBarang(): BelongsTo
    {
        return $this->belongsTo(LokasiBarang::class);
    }

    public function mutasiStokBarang(): BelongsTo
    {
        return $this->belongsTo(MutasiStokBarang::class);
    }

    public function unitBarang(): HasMany
    {
        return $this->hasMany(UnitBarang::class);
    }

    public function nilaiSubtotal(): float
    {
        return (float) $this->jumlah * (float) ($this->harga_satuan ?? 0);
    }
}
