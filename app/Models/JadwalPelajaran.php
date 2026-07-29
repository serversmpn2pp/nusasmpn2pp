<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalPelajaran extends Model
{
    protected $table = 'jadwal_pelajaran';

    protected $fillable = [
        'tahun_pelajaran_id',
        'kelas_id',
        'hari',
        'jam_pelajaran_id',
        'guru_mata_pelajaran_id',
        'mata_pelajaran_id',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function jamPelajaran(): BelongsTo
    {
        return $this->belongsTo(JamPelajaran::class);
    }

    public function guruMataPelajaran(): BelongsTo
    {
        return $this->belongsTo(GuruMataPelajaran::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function mataPelajaranTerjadwal(): ?MataPelajaran
    {
        return $this->mataPelajaran ?? $this->guruMataPelajaran?->mataPelajaran;
    }

    public function labelHari(): string
    {
        return JamPelajaran::DAFTAR_HARI[$this->hari] ?? str($this->hari)->headline()->toString();
    }
}
