<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function label(): string
    {
        return (self::JENIS[$this->jenis] ?? 'Pengecualian').' - scan sekolah tidak diwajibkan';
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
