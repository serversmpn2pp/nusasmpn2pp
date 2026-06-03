<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KunciJawabanUjianOmr extends Model
{
    protected $table = 'kunci_jawaban_ujian_omr';

    protected $fillable = [
        'versi_soal_ujian_omr_id',
        'nomor_soal',
        'jawaban',
    ];

    protected $casts = [
        'nomor_soal' => 'integer',
    ];

    public function versiSoalUjianOmr(): BelongsTo
    {
        return $this->belongsTo(VersiSoalUjianOmr::class);
    }
}
