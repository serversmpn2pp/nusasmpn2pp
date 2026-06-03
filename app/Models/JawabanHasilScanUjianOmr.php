<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JawabanHasilScanUjianOmr extends Model
{
    protected $table = 'jawaban_hasil_scan_ujian_omr';

    protected $fillable = [
        'hasil_scan_ljk_ujian_omr_id',
        'nomor_soal',
        'jawaban',
        'status',
        'tingkat_kehitaman',
        'benar',
    ];

    protected $casts = [
        'nomor_soal' => 'integer',
        'tingkat_kehitaman' => 'array',
        'benar' => 'boolean',
    ];

    public function hasilScanLjkUjianOmr(): BelongsTo
    {
        return $this->belongsTo(HasilScanLjkUjianOmr::class);
    }
}
