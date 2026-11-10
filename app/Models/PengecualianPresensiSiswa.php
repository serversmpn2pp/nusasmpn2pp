<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PengecualianPresensiSiswa extends Model
{
    protected $table = 'pengecualian_presensi_siswa';

    protected $guarded = ['id'];

    protected $casts = [
        'tanggal_mulai' => 'date', 'tanggal_selesai' => 'date', 'aktif' => 'boolean',
        'dibatalkan_pada' => 'datetime', 'dampak_pratinjau' => 'array',
    ];

    public const JENIS = [
        'pjj' => 'PJJ', 'libur_khusus' => 'Libur khusus',
        'keadaan_darurat' => 'Keadaan darurat', 'gangguan_scanner' => 'Gangguan scanner',
    ];

    public static function jenisWeb(): array
    {
        return ['tugas_sekolah' => 'Tugas sekolah'] + self::JENIS;
    }

    public function anggotaKelas(): BelongsToMany
    {
        return $this->belongsToMany(AnggotaKelas::class, 'pengecualian_presensi_anggota', 'pengecualian_id', 'anggota_kelas_id')->withPivot('riwayat_pembatalan');
    }

    public function labelCakupan(): string
    {
        return $this->cakupan === 'siswa' ? $this->anggotaKelas->count().' siswa terpilih' : ($this->kelas?->nama ?: 'Seluruh sekolah');
    }

    public function label(): string
    {
        return $this->jenis === 'tugas_sekolah' ? 'Hadir — Tugas sekolah: '.$this->alasan
            : (self::JENIS[$this->jenis] ?? 'Pengecualian').' - scan sekolah tidak diwajibkan';
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibatalkan_oleh_pengguna_id');
    }
}
