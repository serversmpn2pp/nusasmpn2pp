<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatProsesPembinaanSiswa extends Model
{
    protected $table = 'riwayat_proses_pembinaan_siswa';

    protected $fillable = [
        'laporan_pembinaan_siswa_id', 'kode_kegiatan', 'judul', 'keterangan',
        'status_sebelum', 'status_sesudah', 'pengguna_id', 'terjadi_pada', 'data',
    ];

    protected $casts = ['terjadi_pada' => 'datetime', 'data' => 'array'];

    public function laporanPembinaanSiswa(): BelongsTo
    {
        return $this->belongsTo(LaporanPembinaanSiswa::class);
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
