<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatWaktuTambahanUjianCbt extends Model
{
    protected $table = 'riwayat_waktu_tambahan_ujian_cbt';

    protected $fillable = [
        'peserta_ujian_cbt_id',
        'menit_tambahan',
        'mulai_pada',
        'selesai_pada',
        'alasan',
        'diberikan_oleh_pengguna_id',
    ];

    protected $casts = [
        'menit_tambahan' => 'integer',
        'mulai_pada' => 'datetime',
        'selesai_pada' => 'datetime',
    ];

    public function pesertaUjianCbt(): BelongsTo
    {
        return $this->belongsTo(PesertaUjianCbt::class);
    }

    public function diberikanOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diberikan_oleh_pengguna_id');
    }
}
