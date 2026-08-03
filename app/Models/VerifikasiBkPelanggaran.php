<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifikasiBkPelanggaran extends Model
{
    protected $table = 'verifikasi_bk_pelanggaran';

    public const DAFTAR_HASIL = [
        'sanksi_poin' => 'Tetapkan Sanksi Poin',
        'pembinaan' => 'Tetapkan Pembinaan Tanpa Poin',
        'perlu_klarifikasi' => 'Perlu Klarifikasi',
        'tidak_terbukti' => 'Tidak Terbukti',
    ];

    protected $fillable = [
        'laporan_pembinaan_siswa_id',
        'bk_pegawai_id',
        'pengguna_id',
        'hasil',
        'catatan',
        'diverifikasi_pada',
    ];

    protected $casts = ['diverifikasi_pada' => 'datetime'];

    public function laporanPembinaanSiswa(): BelongsTo
    {
        return $this->belongsTo(LaporanPembinaanSiswa::class);
    }

    public function bkPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'bk_pegawai_id');
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }

    public function labelHasil(): string
    {
        return self::DAFTAR_HASIL[$this->hasil] ?? str($this->hasil)->headline()->toString();
    }
}
