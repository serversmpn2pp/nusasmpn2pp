<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsensiPegawai extends Model
{
    protected $table = 'absensi_pegawai';

    protected $fillable = [
        'tanggal',
        'pegawai_id',
        'pengaturan_absensi_pegawai_id',
        'jam_masuk',
        'status_masuk',
        'menit_terlambat',
        'jam_pulang',
        'status_pulang',
        'menit_pulang_cepat',
        'status_kehadiran',
        'sumber',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'pegawai_id' => 'integer',
        'pengaturan_absensi_pegawai_id' => 'integer',
        'menit_terlambat' => 'integer',
        'menit_pulang_cepat' => 'integer',
    ];

    public const DAFTAR_STATUS_KEHADIRAN = [
        'hadir' => 'Hadir',
        'sakit' => 'Sakit',
        'izin' => 'Izin',
        'dinas_luar' => 'Dinas Luar',
        'cuti' => 'Cuti',
        'alfa' => 'Alfa',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function pengaturanAbsensiPegawai(): BelongsTo
    {
        return $this->belongsTo(PengaturanAbsensiPegawai::class);
    }

    public function logScanAbsensiPegawai(): HasMany
    {
        return $this->hasMany(LogScanAbsensiPegawai::class);
    }

    public function labelStatusKehadiran(): string
    {
        return self::DAFTAR_STATUS_KEHADIRAN[$this->status_kehadiran]
            ?? ucfirst((string) $this->status_kehadiran);
    }
}
