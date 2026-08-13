<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresensiKegiatanIbadah extends Model
{
    protected $table = 'presensi_kegiatan_ibadah';

    protected $fillable = [
        'jadwal_kegiatan_ibadah_id',
        'kegiatan_ibadah_id',
        'tahun_pelajaran_id',
        'kelas_id',
        'anggota_kelas_id',
        'siswa_id',
        'dipindai_oleh_pengguna_id',
        'dikoreksi_oleh_pengguna_id',
        'tanggal',
        'waktu_scan',
        'sumber',
        'ip_address',
        'user_agent',
        'dikoreksi_pada',
        'catatan_koreksi',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'dikoreksi_pada' => 'datetime',
    ];

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

    public function dipindaiOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dipindai_oleh_pengguna_id');
    }

    public function dikoreksiOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dikoreksi_oleh_pengguna_id');
    }

    public function logScan(): HasMany
    {
        return $this->hasMany(LogScanKegiatanIbadah::class);
    }

    public function riwayatKoreksi(): HasMany
    {
        return $this->hasMany(RiwayatKoreksiKegiatanIbadah::class);
    }
}
