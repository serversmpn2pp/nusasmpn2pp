<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogScanBerhalanganIbadah extends Model
{
    protected $table = 'log_scan_berhalangan_ibadah';

    protected $fillable = [
        'presensi_berhalangan_ibadah_id',
        'periode_berhalangan_ibadah_id',
        'jadwal_kegiatan_ibadah_id',
        'kegiatan_ibadah_id',
        'siswa_id',
        'dipindai_oleh_pengguna_id',
        'isi_scan',
        'nisn',
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

    public function presensiBerhalanganIbadah(): BelongsTo
    {
        return $this->belongsTo(PresensiBerhalanganIbadah::class);
    }

    public function periodeBerhalanganIbadah(): BelongsTo
    {
        return $this->belongsTo(PeriodeBerhalanganIbadah::class);
    }

    public function jadwalKegiatanIbadah(): BelongsTo
    {
        return $this->belongsTo(JadwalKegiatanIbadah::class);
    }

    public function kegiatanIbadah(): BelongsTo
    {
        return $this->belongsTo(KegiatanIbadah::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function dipindaiOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dipindai_oleh_pengguna_id');
    }
}
