<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPerubahanAbsensiSiswa extends Model
{
    protected $table = 'riwayat_perubahan_absensi_siswa';

    protected $fillable = [
        'absensi_siswa_id',
        'siswa_id',
        'tanggal',
        'status_sebelum',
        'status_sesudah',
        'sumber',
        'catatan',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function absensiSiswa(): BelongsTo
    {
        return $this->belongsTo(AbsensiSiswa::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }
}
