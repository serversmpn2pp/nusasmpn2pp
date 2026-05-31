<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetailPeminjamanBarang extends Model
{
    protected $table = 'detail_peminjaman_barang';

    protected $fillable = [
        'peminjaman_barang_id',
        'barang_id',
        'unit_barang_id',
        'lokasi_barang_id',
        'tipe_pengelolaan',
        'jumlah',
        'jumlah_dikembalikan',
        'wajib_dikembalikan',
        'cara_input_barang',
        'catatan',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'jumlah_dikembalikan' => 'decimal:2',
        'wajib_dikembalikan' => 'boolean',
    ];

    public function peminjamanBarang(): BelongsTo
    {
        return $this->belongsTo(PeminjamanBarang::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function unitBarang(): BelongsTo
    {
        return $this->belongsTo(UnitBarang::class);
    }

    public function lokasiBarang(): BelongsTo
    {
        return $this->belongsTo(LokasiBarang::class);
    }

    public function detailPengembalianBarang(): HasMany
    {
        return $this->hasMany(DetailPengembalianBarang::class);
    }

    public function jumlahBelumDikembalikan(): float
    {
        return max((float) $this->jumlah - (float) $this->jumlah_dikembalikan, 0);
    }
}
