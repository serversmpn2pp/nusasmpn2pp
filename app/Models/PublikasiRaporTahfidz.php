<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublikasiRaporTahfidz extends Model
{
    protected $table = 'publikasi_rapor_tahfidz';

    protected $fillable = ['siswa_id', 'tahun_pelajaran_id', 'salinan', 'versi', 'dipublikasikan_pada', 'dipublikasikan_oleh'];

    protected $casts = ['salinan' => 'array', 'versi' => 'integer', 'dipublikasikan_pada' => 'datetime'];
}
