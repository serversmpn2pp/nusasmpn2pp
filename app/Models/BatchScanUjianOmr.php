<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchScanUjianOmr extends Model
{
    protected $table = 'batch_scan_ujian_omr';

    protected $fillable = [
        'ujian_omr_id',
        'nama_file_asli',
        'lokasi_file',
        'jumlah_halaman_pdf',
        'jumlah_ljk_terdeteksi',
        'jumlah_berhasil',
        'jumlah_perlu_diperiksa',
        'status',
        'pesan_error',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'jumlah_halaman_pdf' => 'integer',
        'jumlah_ljk_terdeteksi' => 'integer',
        'jumlah_berhasil' => 'integer',
        'jumlah_perlu_diperiksa' => 'integer',
    ];

    public function ujianOmr(): BelongsTo
    {
        return $this->belongsTo(UjianOmr::class);
    }

    public function hasilScan(): HasMany
    {
        return $this->hasMany(HasilScanLjkUjianOmr::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }
}
