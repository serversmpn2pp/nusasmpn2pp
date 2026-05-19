<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkemaBobotNilai extends Model
{
    protected $table = 'skema_bobot_nilai';

    protected $fillable = [
        'tahun_pelajaran_id',
        'semester',
        'tingkat',
        'bobot_formatif',
        'bobot_sumatif',
        'bobot_sts',
        'bobot_sas_saj',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'tingkat' => 'integer',
        'bobot_formatif' => 'integer',
        'bobot_sumatif' => 'integer',
        'bobot_sts' => 'integer',
        'bobot_sas_saj' => 'integer',
        'aktif' => 'boolean',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function totalBobot(): int
    {
        return $this->bobot_formatif
            + $this->bobot_sumatif
            + $this->bobot_sts
            + $this->bobot_sas_saj;
    }

    public function labelTingkat(): string
    {
        return $this->tingkat ? 'Kelas ' . $this->tingkat : 'Semua tingkat';
    }

    public function labelNilaiAkhir(): string
    {
        return (int) $this->tingkat === 9 ? 'SAJ' : 'SAS/SAJ';
    }
}
