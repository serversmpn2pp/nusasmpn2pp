<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveiPembelajaran extends Model
{
    public const VERSI_PERTANYAAN = 1;

    public const PILIHAN = [
        1 => 'Sangat tidak sesuai',
        2 => 'Tidak sesuai',
        3 => 'Cukup sesuai',
        4 => 'Sesuai',
        5 => 'Sangat sesuai',
    ];

    protected $table = 'survei_pembelajaran';

    protected $fillable = [
        'guru_mata_pelajaran_id',
        'siswa_id',
        'semester',
        'versi_pertanyaan',
        'jawaban',
        'snapshot_pertanyaan',
        'saran',
        'diisi_pada',
    ];

    protected $casts = [
        'jawaban' => 'array',
        'snapshot_pertanyaan' => 'array',
        'diisi_pada' => 'datetime',
    ];

    public function guruMataPelajaran(): BelongsTo
    {
        return $this->belongsTo(GuruMataPelajaran::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
