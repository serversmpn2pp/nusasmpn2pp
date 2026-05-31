<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengembalianBarang extends Model
{
    protected $table = 'pengembalian_barang';

    protected $fillable = [
        'nomor_pengembalian',
        'peminjaman_barang_id',
        'tanggal_pengembalian',
        'catatan',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal_pengembalian' => 'date',
    ];

    public function peminjamanBarang(): BelongsTo
    {
        return $this->belongsTo(PeminjamanBarang::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function detailPengembalianBarang(): HasMany
    {
        return $this->hasMany(DetailPengembalianBarang::class);
    }
}
