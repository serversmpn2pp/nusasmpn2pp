<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesiUjianCbt extends Model
{
    protected $table = 'sesi_ujian_cbt';

    public const DAFTAR_STATUS = [
        'draft' => 'Draft',
        'aktif' => 'Aktif',
        'selesai' => 'Selesai',
        'nonaktif' => 'Nonaktif',
    ];

    protected $fillable = [
        'ujian_cbt_id',
        'kode',
        'nama',
        'waktu_mulai',
        'waktu_selesai',
        'kapasitas',
        'status',
        'keterangan',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'kapasitas' => 'integer',
    ];

    public function ujianCbt(): BelongsTo
    {
        return $this->belongsTo(UjianCbt::class);
    }

    public function pesertaUjianCbt(): HasMany
    {
        return $this->hasMany(PesertaUjianCbt::class);
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function labelWaktu(): string
    {
        if (! $this->waktu_mulai && ! $this->waktu_selesai) {
            return 'Mengikuti jadwal paket';
        }

        $mulai = $this->waktu_mulai?->format('d-m-Y H:i') ?? '-';
        $selesai = $this->waktu_selesai?->format('d-m-Y H:i') ?? '-';

        return "{$mulai} sampai {$selesai}";
    }
}
