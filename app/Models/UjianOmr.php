<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UjianOmr extends Model
{
    protected $table = 'ujian_omr';

    public const DAFTAR_STATUS = [
        'draft' => 'Draft',
        'siap' => 'Siap digunakan',
        'nonaktif' => 'Nonaktif',
    ];

    protected $fillable = [
        'tahun_pelajaran_id',
        'mata_pelajaran_id',
        'kode',
        'nama',
        'semester',
        'tanggal_ujian',
        'jumlah_soal',
        'jumlah_pilihan',
        'status',
        'keterangan',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal_ujian' => 'date',
        'jumlah_soal' => 'integer',
        'jumlah_pilihan' => 'integer',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function kelasUjianOmr(): HasMany
    {
        return $this->hasMany(KelasUjianOmr::class);
    }

    public function versiSoal(): HasMany
    {
        return $this->hasMany(VersiSoalUjianOmr::class);
    }

    public function lembarJawabUjianOmr(): HasMany
    {
        return $this->hasMany(LembarJawabUjianOmr::class);
    }

    public function batchScanUjianOmr(): HasMany
    {
        return $this->hasMany(BatchScanUjianOmr::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }
}
