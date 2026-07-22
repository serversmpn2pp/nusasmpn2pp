<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ButirPelanggaranLaporan extends Model
{
    protected $table = 'butir_pelanggaran_laporan';

    protected $fillable = [
        'laporan_pembinaan_siswa_id',
        'jenis_pelanggaran_siswa_id',
        'kode_pelanggaran',
        'nama_pelanggaran',
        'tingkat',
        'poin',
        'catatan',
    ];

    protected $casts = ['poin' => 'integer'];

    public function laporanPembinaanSiswa(): BelongsTo
    {
        return $this->belongsTo(LaporanPembinaanSiswa::class);
    }

    public function jenisPelanggaranSiswa(): BelongsTo
    {
        return $this->belongsTo(JenisPelanggaranSiswa::class);
    }
}
