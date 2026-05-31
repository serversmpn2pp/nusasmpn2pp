<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaldoStokBarang extends Model
{
    protected $table = 'saldo_stok_barang';

    protected $fillable = [
        'barang_id',
        'lokasi_barang_id',
        'jumlah',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
    ];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function lokasiBarang(): BelongsTo
    {
        return $this->belongsTo(LokasiBarang::class);
    }

    public function mutasiStokBarang(): HasMany
    {
        return $this->hasMany(MutasiStokBarang::class);
    }
}
