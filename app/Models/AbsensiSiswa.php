<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsensiSiswa extends Model
{
    protected $table = 'absensi_siswa';

    protected $fillable = [
        'tanggal',
        'tahun_pelajaran_id',
        'kelas_id',
        'anggota_kelas_id',
        'siswa_id',
        'jam_masuk',
        'status_masuk',
        'menit_terlambat',
        'status_poin_keterlambatan',
        'poin_keterlambatan_terhitung',
        'poin_keterlambatan_diproses_pada',
        'jam_pulang',
        'status_pulang',
        'menit_pulang_cepat',
        'status_kehadiran',
        'sumber',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'menit_terlambat' => 'integer',
        'poin_keterlambatan_terhitung' => 'integer',
        'poin_keterlambatan_diproses_pada' => 'datetime',
        'menit_pulang_cepat' => 'integer',
    ];

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

    public function logScanAbsensi(): HasMany
    {
        return $this->hasMany(LogScanAbsensi::class);
    }

    public function notifikasiAbsensiSiswa(): HasMany
    {
        return $this->hasMany(NotifikasiAbsensiSiswa::class);
    }

    public function laporanKeterlambatan(): HasMany
    {
        return $this->hasMany(LaporanPembinaanSiswa::class);
    }

    public function riwayatPerubahan(): HasMany
    {
        return $this->hasMany(RiwayatPerubahanAbsensiSiswa::class);
    }
}
