<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanAbsensi extends Model
{
    protected $table = 'pengaturan_absensi';

    protected $fillable = [
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
        'urutan_hari' => 'integer',
        'aktif' => 'boolean',
    ];

    public const DAFTAR_HARI = [
        'senin' => ['label' => 'Senin', 'urutan' => 1],
        'selasa' => ['label' => 'Selasa', 'urutan' => 2],
        'rabu' => ['label' => 'Rabu', 'urutan' => 3],
        'kamis' => ['label' => 'Kamis', 'urutan' => 4],
        'jumat' => ['label' => 'Jumat', 'urutan' => 5],
        'sabtu' => ['label' => 'Sabtu', 'urutan' => 6],
        'minggu' => ['label' => 'Minggu', 'urutan' => 7],
    ];

    public function labelHari(): string
    {
        return self::DAFTAR_HARI[$this->hari]['label'] ?? ucfirst($this->hari);
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
