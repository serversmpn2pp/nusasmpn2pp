<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenilaianSupervisi extends Model
{
    protected $table = 'penilaian_supervisi';

    protected $guarded = ['id'];

    protected $casts = ['versi' => 'integer', 'praobservasi_versi' => 'integer', 'nilai' => 'array', 'tanggal_observasi' => 'date', 'difinalisasi_pada' => 'datetime'];

    public function sesi()
    {
        return $this->belongsTo(SesiSupervisi::class, 'sesi_supervisi_id');
    }
}
