<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotifikasiPengguna extends Model
{
    protected $table = 'notifikasi_pengguna';

    public const DAFTAR_JENIS = [
        'informasi' => 'Informasi',
        'berhasil' => 'Berhasil',
        'peringatan' => 'Peringatan',
        'penting' => 'Penting',
    ];

    protected $fillable = [
        'pengguna_id',
        'jenis',
        'judul',
        'pesan',
        'tautan',
        'kunci_unik',
        'data_tambahan',
        'dibaca_pada',
    ];

    protected $casts = [
        'data_tambahan' => 'array',
        'dibaca_pada' => 'datetime',
    ];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }

    public function scopeBelumDibaca(Builder $query): Builder
    {
        return $query->whereNull('dibaca_pada');
    }

    public function scopeSudahDibaca(Builder $query): Builder
    {
        return $query->whereNotNull('dibaca_pada');
    }

    public function tandaiDibaca(): void
    {
        if (! $this->dibaca_pada) {
            $this->forceFill(['dibaca_pada' => now()])->save();
        }
    }

    public function masihBelumDibaca(): bool
    {
        return $this->dibaca_pada === null;
    }

    public function labelJenis(): string
    {
        return self::DAFTAR_JENIS[$this->jenis] ?? str($this->jenis)->headline()->toString();
    }
}
