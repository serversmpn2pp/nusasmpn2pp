<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresensiBerhalanganIbadah extends Model
{
    protected $table = 'presensi_berhalangan_ibadah';

    protected $fillable = [
        'periode_berhalangan_ibadah_id',
        'jadwal_kegiatan_ibadah_id',
        'kegiatan_ibadah_id',
        'tahun_pelajaran_id',
        'kelas_id',
        'anggota_kelas_id',
        'siswa_id',
        'dipindai_oleh_pengguna_id',
        'tanggal',
        'waktu_scan',
        'sumber',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

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

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function anggotaKelas(): BelongsTo
    {
        return $this->belongsTo(AnggotaKelas::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function dipindaiOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dipindai_oleh_pengguna_id');
    }

    public function logScan(): HasMany
    {
        return $this->hasMany(LogScanBerhalanganIbadah::class);
    }
}
