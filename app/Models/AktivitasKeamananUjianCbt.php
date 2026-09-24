<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AktivitasKeamananUjianCbt extends Model
{
    protected $table = 'aktivitas_keamanan_ujian_cbt';

    protected $fillable = [
        'peserta_ujian_cbt_id',
        'jenis',
        'mulai_pada',
        'selesai_pada',
        'durasi_detik',
        'dihitung',
        'oleh_pengguna_id',
        'catatan',
        'perangkat',
        'ip',
        'metadata',
    ];

    protected $casts = [
        'mulai_pada' => 'datetime',
        'selesai_pada' => 'datetime',
        'durasi_detik' => 'integer',
        'dihitung' => 'boolean',
        'metadata' => 'array',
    ];

    public function pesertaUjianCbt(): BelongsTo
    {
        return $this->belongsTo(PesertaUjianCbt::class);
    }

    public function dibukaOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'oleh_pengguna_id');
    }
}
