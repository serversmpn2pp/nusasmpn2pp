<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogScanAbsensi extends Model
{
    protected $table = 'log_scan_absensi';

    protected $fillable = [
        'absensi_siswa_id',
        'siswa_id',
        'isi_scan',
        'nisn',
        'scanner_id',
        'jenis_scan',
        'waktu_scan',
        'tanggal',
        'berhasil',
        'status_scan',
        'pesan',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'waktu_scan' => 'datetime',
        'tanggal' => 'date',
        'berhasil' => 'boolean',
    ];

    public function absensiSiswa(): BelongsTo
    {
        return $this->belongsTo(AbsensiSiswa::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
