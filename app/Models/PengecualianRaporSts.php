<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengecualianRaporSts extends Model
{
    protected $table = 'pengecualian_rapor_sts';

    protected $fillable = ['rapor_sts_kelas_id', 'anggota_kelas_id', 'mata_pelajaran_id', 'peserta_ujian_cbt_id', 'aktif', 'alasan', 'sidik_kondisi', 'ditetapkan_pada', 'ditetapkan_oleh_pengguna_id', 'dibatalkan_pada', 'dibatalkan_oleh_pengguna_id'];

    protected $casts = ['aktif' => 'boolean', 'ditetapkan_pada' => 'datetime', 'dibatalkan_pada' => 'datetime'];

    public function penetap(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'ditetapkan_oleh_pengguna_id');
    }
}
