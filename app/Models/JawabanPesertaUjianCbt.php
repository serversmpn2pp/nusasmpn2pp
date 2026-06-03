<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JawabanPesertaUjianCbt extends Model
{
    protected $table = 'jawaban_peserta_ujian_cbt';

    protected $fillable = [
        'peserta_ujian_cbt_id',
        'soal_ujian_cbt_id',
        'soal_cbt_id',
        'jawaban',
        'ragu',
        'skor',
        'benar',
        'waktu_dijawab',
    ];

    protected $casts = [
        'jawaban' => 'array',
        'ragu' => 'boolean',
        'skor' => 'decimal:2',
        'benar' => 'boolean',
        'waktu_dijawab' => 'datetime',
    ];

    public function pesertaUjianCbt(): BelongsTo
    {
        return $this->belongsTo(PesertaUjianCbt::class);
    }

    public function soalUjianCbt(): BelongsTo
    {
        return $this->belongsTo(SoalUjianCbt::class);
    }

    public function soalCbt(): BelongsTo
    {
        return $this->belongsTo(SoalCbt::class);
    }
}
