<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersetujuanPelanggaran extends Model
{
    protected $table = 'persetujuan_pelanggaran';

    public const DAFTAR_JENIS = [
        'wali_kelas' => 'Wali Kelas',
        'guru_wali' => 'Guru Wali',
        'wakil_kesiswaan' => 'Wakil Kesiswaan/Pengganti',
    ];

    public const DAFTAR_KEPUTUSAN = [
        'setuju' => 'Setuju',
        'tidak_setuju' => 'Tidak Setuju',
    ];

    protected $fillable = [
        'laporan_pembinaan_siswa_id',
        'jenis_persetujuan',
        'pegawai_id',
        'pengguna_id',
        'keputusan',
        'catatan',
        'diputuskan_pada',
    ];

    protected $casts = ['diputuskan_pada' => 'datetime'];

    public function laporanPembinaanSiswa(): BelongsTo
    {
        return $this->belongsTo(LaporanPembinaanSiswa::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }

    public function labelJenis(): string
    {
        return self::DAFTAR_JENIS[$this->jenis_persetujuan] ?? str($this->jenis_persetujuan)->headline()->toString();
    }
}
