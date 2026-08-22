<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KomponenNilai extends Model
{
    protected $table = 'komponen_nilai';

    protected $fillable = [
        'guru_mata_pelajaran_id',
        'semester',
        'jenis_komponen',
        'nama',
        'tanggal_penilaian',
        'urutan',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_penilaian' => 'date',
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    public function guruMataPelajaran(): BelongsTo
    {
        return $this->belongsTo(GuruMataPelajaran::class);
    }

    public function nilaiSiswa(): HasMany
    {
        return $this->hasMany(NilaiSiswa::class);
    }

    public function kelasUjianCbt(): HasMany
    {
        return $this->hasMany(KelasUjianCbt::class);
    }

    public function labelJenis(): string
    {
        return match ($this->jenis_komponen) {
            'formatif' => 'Formatif',
            'sumatif' => 'Sumatif',
            'sts' => 'STS',
            'sas_saj' => $this->labelSasSaj(),
            default => '-',
        };
    }

    public function labelSasSaj(): string
    {
        $tingkat = $this->guruMataPelajaran?->kelas?->tingkat;

        return (int) $tingkat === 9 ? 'SAJ' : 'SAS';
    }
}
