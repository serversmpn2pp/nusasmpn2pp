<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatFilePerangkatAjar extends Model
{
    protected $table = 'riwayat_file_perangkat_ajar';

    protected $fillable = [
        'perangkat_ajar_id',
        'diunggah_oleh_pengguna_id',
        'lokasi_file',
        'nama_file_asli',
        'tipe_file',
        'ukuran_file',
        'catatan',
        'diunggah_pada',
    ];

    protected $casts = [
        'ukuran_file' => 'integer',
        'diunggah_pada' => 'datetime',
    ];

    public function perangkatAjar(): BelongsTo
    {
        return $this->belongsTo(PerangkatAjar::class);
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diunggah_oleh_pengguna_id');
    }

    public function ukuranFileTampil(): string
    {
        return number_format($this->ukuran_file / 1024 / 1024, 2, ',', '.') . ' MB';
    }
}
