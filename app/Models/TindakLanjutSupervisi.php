<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TindakLanjutSupervisi extends Model
{
    public const STATUS = ['draf' => 'Draf supervisor', 'ditugaskan' => 'Dalam pelaksanaan', 'diajukan' => 'Menunggu verifikasi', 'dikembalikan' => 'Perlu perbaikan', 'selesai' => 'Terverifikasi', 'dibatalkan' => 'Dibatalkan'];

    protected $table = 'tindak_lanjut_supervisi';

    protected $guarded = ['id'];

    protected $hidden = ['token_pembuatan', 'sidik_pembuatan'];

    protected $casts = ['versi' => 'integer', 'penilaian_versi' => 'integer', 'refleksi_versi' => 'integer', 'tenggat' => 'date', 'tanggal_pelaksanaan' => 'date', 'diterbitkan_pada' => 'datetime', 'diajukan_pada' => 'datetime', 'selesai_pada' => 'datetime'];

    public function sesi()
    {
        return $this->belongsTo(SesiSupervisi::class, 'sesi_supervisi_id');
    }

    public function lampiran()
    {
        return $this->hasMany(LampiranTindakLanjutSupervisi::class, 'tindak_lanjut_supervisi_id');
    }
}
