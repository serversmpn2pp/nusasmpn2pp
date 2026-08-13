<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JadwalKegiatanIbadah extends Model
{
    public const DAFTAR_HARI = [
        'senin' => ['label' => 'Senin', 'urutan' => 1],
        'selasa' => ['label' => 'Selasa', 'urutan' => 2],
        'rabu' => ['label' => 'Rabu', 'urutan' => 3],
        'kamis' => ['label' => 'Kamis', 'urutan' => 4],
        'jumat' => ['label' => 'Jumat', 'urutan' => 5],
        'sabtu' => ['label' => 'Sabtu', 'urutan' => 6],
    ];

    protected $table = 'jadwal_kegiatan_ibadah';

    protected $fillable = [
        'kegiatan_ibadah_id',
        'tahun_pelajaran_id',
        'hari',
        'urutan_hari',
        'jam_scan_mulai',
        'jam_pelaksanaan',
        'jam_scan_selesai',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'urutan_hari' => 'integer',
        'aktif' => 'boolean',
    ];

    public function kegiatanIbadah(): BelongsTo
    {
        return $this->belongsTo(KegiatanIbadah::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(PresensiKegiatanIbadah::class);
    }

    public function logScan(): HasMany
    {
        return $this->hasMany(LogScanKegiatanIbadah::class);
    }

    public function labelHari(): string
    {
        return self::DAFTAR_HARI[$this->hari]['label'] ?? ucfirst($this->hari);
    }

    public function formatJam(?string $jam): string
    {
        return $jam ? substr($jam, 0, 5) : '-';
    }

    public function rentangScan(): string
    {
        return $this->formatJam($this->jam_scan_mulai).' - '.$this->formatJam($this->jam_scan_selesai);
    }
}
