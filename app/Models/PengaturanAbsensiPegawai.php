<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengaturanAbsensiPegawai extends Model
{
    protected $table = 'pengaturan_absensi_pegawai';

    protected $fillable = [
        'nama_jadwal',
        'cakupan',
        'jenis_pegawai',
        'pegawai_id',
        'hari',
        'urutan_hari',
        'jam_scan_masuk_mulai',
        'jam_masuk',
        'jam_scan_masuk_selesai',
        'jam_scan_pulang_mulai',
        'jam_pulang',
        'jam_scan_pulang_selesai',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'pegawai_id' => 'integer',
        'urutan_hari' => 'integer',
        'aktif' => 'boolean',
    ];

    public const DAFTAR_HARI = PengaturanAbsensi::DAFTAR_HARI;

    public const DAFTAR_CAKUPAN = [
        'semua' => 'Semua Pegawai',
        'jenis_pegawai' => 'Jenis Pegawai',
        'pegawai' => 'Pegawai Tertentu',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function absensiPegawai(): HasMany
    {
        return $this->hasMany(AbsensiPegawai::class);
    }

    public function labelHari(): string
    {
        return self::DAFTAR_HARI[$this->hari]['label'] ?? ucfirst($this->hari);
    }

    public function labelCakupan(): string
    {
        return self::DAFTAR_CAKUPAN[$this->cakupan] ?? ucfirst((string) $this->cakupan);
    }

    public function labelSasaran(): string
    {
        return match ($this->cakupan) {
            'jenis_pegawai' => $this->jenis_pegawai ?: 'Jenis pegawai belum dipilih',
            'pegawai' => $this->pegawai?->nama_lengkap ?: 'Pegawai belum dipilih',
            default => 'Semua pegawai',
        };
    }

    public function rentangMasuk(): string
    {
        return $this->formatJam($this->jam_scan_masuk_mulai)
            . ' - '
            . $this->formatJam($this->jam_scan_masuk_selesai);
    }

    public function rentangPulang(): string
    {
        return $this->formatJam($this->jam_scan_pulang_mulai)
            . ' - '
            . $this->formatJam($this->jam_scan_pulang_selesai);
    }

    public function formatJam(?string $jam): string
    {
        return $jam ? substr($jam, 0, 5) : '-';
    }
}
