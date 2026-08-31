<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenerimaanBarang extends Model
{
    protected $table = 'penerimaan_barang';

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    public const DAFTAR_CARA_PEROLEHAN = [
        'pembelian' => 'Pembelian',
        'hibah' => 'Hibah/bantuan',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = [
        'token_penyimpanan',
        'nomor_penerimaan',
        'tanggal_penerimaan',
        'sumber_perolehan_barang_id',
        'cara_perolehan',
        'status',
        'nomor_dokumen',
        'asal_barang',
        'catatan',
        'alasan_pembatalan',
        'dibatalkan_pada',
        'dibatalkan_oleh_pengguna_id',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal_penerimaan' => 'date',
        'dibatalkan_pada' => 'datetime',
    ];

    public function sumberPerolehanBarang(): BelongsTo
    {
        return $this->belongsTo(SumberPerolehanBarang::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function dibatalkanOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibatalkan_oleh_pengguna_id');
    }

    public function detailPenerimaanBarang(): HasMany
    {
        return $this->hasMany(DetailPenerimaanBarang::class);
    }

    public function labelCaraPerolehan(): string
    {
        return self::DAFTAR_CARA_PEROLEHAN[$this->cara_perolehan]
            ?? str($this->cara_perolehan)->headline()->toString();
    }

    public function nilaiTotal(): float
    {
        return (float) $this->detailPenerimaanBarang->sum(
            fn (DetailPenerimaanBarang $detail) => (float) $detail->jumlah * (float) ($detail->harga_satuan ?? 0),
        );
    }

    public function sudahDibatalkan(): bool
    {
        return $this->status === self::STATUS_DIBATALKAN;
    }
}
