<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogScanAbsensiPegawai extends Model
{
    protected $table = 'log_scan_absensi_pegawai';

    protected $fillable = [
        'absensi_pegawai_id',
        'pegawai_id',
        'isi_scan',
        'nip',
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
        'absensi_pegawai_id' => 'integer',
        'pegawai_id' => 'integer',
        'waktu_scan' => 'datetime',
        'tanggal' => 'date',
        'berhasil' => 'boolean',
    ];

    public function absensiPegawai(): BelongsTo
    {
        return $this->belongsTo(AbsensiPegawai::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }
}
