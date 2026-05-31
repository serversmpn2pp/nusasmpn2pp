<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailPengembalianBarang extends Model
{
    protected $table = 'detail_pengembalian_barang';

    protected $fillable = [
        'pengembalian_barang_id',
        'detail_peminjaman_barang_id',
        'jumlah',
        'kondisi_pengembalian',
        'cara_input_barang',
        'catatan',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
    ];

    public function pengembalianBarang(): BelongsTo
    {
        return $this->belongsTo(PengembalianBarang::class);
    }

    public function detailPeminjamanBarang(): BelongsTo
    {
        return $this->belongsTo(DetailPeminjamanBarang::class);
    }
}
