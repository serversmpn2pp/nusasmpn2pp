<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatDokumenHumas extends Model
{
    protected $table = 'riwayat_dokumen_humas';

    protected $fillable = [
        'dokumen_humas_id',
        'versi',
        'lokasi_file',
        'nama_file_asli',
        'tipe_file',
        'ukuran_file',
        'catatan',
        'diunggah_oleh_pengguna_id',
        'diunggah_pada',
    ];

    protected $casts = [
        'versi' => 'integer',
        'ukuran_file' => 'integer',
        'diunggah_pada' => 'datetime',
    ];

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenHumas::class, 'dokumen_humas_id');
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diunggah_oleh_pengguna_id');
    }

    public function ukuranFileTampil(): string
    {
        return number_format($this->ukuran_file / 1024 / 1024, 2, ',', '.').' MB';
    }
}
