<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KelulusanTahfidz extends Model
{
    protected $table = 'kelulusan_tahfidz';

    protected $fillable = ['siswa_id', 'anggota_tahfidz_id', 'pembimbing_id', 'nama_pembimbing', 'diverifikasi_pada',
        'wisuda_tahfidz_id', 'nomor_sertifikat', 'nama_penerima', 'penerbit_id', 'diterbitkan_pada',
        'dibagikan_pada', 'dibagikan_oleh', 'versi_publikasi'];

    protected $casts = ['diverifikasi_pada' => 'datetime', 'diterbitkan_pada' => 'datetime',
        'dibagikan_pada' => 'datetime', 'versi_publikasi' => 'integer'];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function wisuda(): BelongsTo
    {
        return $this->belongsTo(WisudaTahfidz::class, 'wisuda_tahfidz_id');
    }
}
