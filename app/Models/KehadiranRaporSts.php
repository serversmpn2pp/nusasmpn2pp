<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KehadiranRaporSts extends Model
{
    protected $table = 'kehadiran_rapor_sts';

    protected $fillable = ['rapor_sts_kelas_id', 'anggota_kelas_id', 'sakit', 'izin', 'alfa', 'rekap_sumber', 'catatan_koreksi', 'diperiksa_pada', 'diperiksa_oleh_pengguna_id'];

    protected $casts = ['sakit' => 'integer', 'izin' => 'integer', 'alfa' => 'integer', 'rekap_sumber' => 'array', 'diperiksa_pada' => 'datetime'];

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diperiksa_oleh_pengguna_id');
    }
}
