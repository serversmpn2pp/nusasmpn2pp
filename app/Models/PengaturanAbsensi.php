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
        'pulang_jumat_dibedakan',
        'jam_scan_pulang_perempuan_mulai',
        'jam_pulang_perempuan',
        'jam_scan_pulang_perempuan_selesai',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'urutan_hari' => 'integer',
        'pulang_jumat_dibedakan' => 'boolean',
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
            .' - '
            .$this->formatJam($this->jam_scan_masuk_selesai);
    }

    public function pulangJumatDibedakan(): bool
    {
        return $this->hari === 'jumat'
            && $this->pulang_jumat_dibedakan
            && filled($this->jam_scan_pulang_perempuan_mulai)
            && filled($this->jam_pulang_perempuan)
            && filled($this->jam_scan_pulang_perempuan_selesai);
    }

    /** @return array{kelompok: string, jam_scan_pulang_mulai: string|null, jam_pulang: string|null, jam_scan_pulang_selesai: string|null} */
    public function jadwalPulangUntuk(?string $jenisKelamin = null): array
    {
        $jadwalPerempuan = $this->pulangJumatDibedakan()
            && mb_strtoupper(trim((string) $jenisKelamin)) === 'P';

        return [
            'kelompok' => $jadwalPerempuan
                ? 'perempuan'
                : ($this->pulangJumatDibedakan() ? 'laki_laki' : 'semua'),
            'jam_scan_pulang_mulai' => $jadwalPerempuan
                ? $this->jam_scan_pulang_perempuan_mulai
                : $this->jam_scan_pulang_mulai,
            'jam_pulang' => $jadwalPerempuan
                ? $this->jam_pulang_perempuan
                : $this->jam_pulang,
            'jam_scan_pulang_selesai' => $jadwalPerempuan
                ? $this->jam_scan_pulang_perempuan_selesai
                : $this->jam_scan_pulang_selesai,
        ];
    }

    public function rentangPulang(?string $jenisKelamin = null): string
    {
        $jadwal = $this->jadwalPulangUntuk($jenisKelamin);

        return $this->formatJam($jadwal['jam_scan_pulang_mulai'])
            .' - '
            .$this->formatJam($jadwal['jam_scan_pulang_selesai']);
    }

    public function formatJam(?string $jam): string
    {
        return $jam ? substr($jam, 0, 5) : '-';
    }
}
