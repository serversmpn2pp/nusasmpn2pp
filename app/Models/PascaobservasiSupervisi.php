<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PascaobservasiSupervisi extends Model
{
    public const STATUS = ['belum_diisi' => 'Belum diisi', 'draf' => 'Draf guru', 'terkirim' => 'Menunggu tinjauan', 'dikembalikan' => 'Perlu perbaikan', 'diterima' => 'Refleksi diterima', 'selesai' => 'Pascaobservasi selesai'];

    protected $table = 'pascaobservasi_supervisi';

    protected $guarded = ['id'];

    protected $casts = ['versi' => 'integer', 'penilaian_versi' => 'integer', 'pertanyaan' => 'array', 'jawaban' => 'array', 'dikirim_pada' => 'datetime', 'diterima_pada' => 'datetime', 'selesai_pada' => 'datetime'];

    public function sesi()
    {
        return $this->belongsTo(SesiSupervisi::class, 'sesi_supervisi_id');
    }
}
