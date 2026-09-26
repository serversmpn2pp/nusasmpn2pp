<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RaporStsKelas extends Model
{
    protected $table = 'rapor_sts_kelas';

    protected $fillable = ['kegiatan_ujian_cbt_id', 'kelas_id', 'tanggal_awal_presensi', 'tanggal_akhir_presensi', 'tanggal_rapor', 'versi', 'diubah_oleh_pengguna_id'];

    protected $casts = ['tanggal_awal_presensi' => 'date', 'tanggal_akhir_presensi' => 'date', 'tanggal_rapor' => 'date', 'versi' => 'integer'];

    public function kehadiran(): HasMany
    {
        return $this->hasMany(KehadiranRaporSts::class);
    }

    public function pengecualian(): HasMany
    {
        return $this->hasMany(PengecualianRaporSts::class);
    }
}
