<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuktiLaporanPembinaanSiswa extends Model
{
    protected $table = 'bukti_laporan_pembinaan_siswa';

    protected $fillable = [
        'laporan_pembinaan_siswa_id', 'jenis', 'nama_file_asli', 'lokasi_file',
        'tipe_file', 'ukuran_file', 'keterangan', 'diunggah_oleh_pengguna_id', 'diunggah_pada',
    ];

    protected $casts = ['ukuran_file' => 'integer', 'diunggah_pada' => 'datetime'];

    public function laporanPembinaanSiswa(): BelongsTo
    {
        return $this->belongsTo(LaporanPembinaanSiswa::class);
    }

    public function diunggahOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diunggah_oleh_pengguna_id');
    }

    public function ukuranRingkas(): string
    {
        return $this->ukuran_file >= 1048576
            ? number_format($this->ukuran_file / 1048576, 1, ',', '.') . ' MB'
            : number_format(max(1, $this->ukuran_file / 1024), 0, ',', '.') . ' KB';
    }
}
