<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PraobservasiSupervisi extends Model
{
    public const STATUS = ['belum_diisi' => 'Belum diisi', 'draf' => 'Draf guru', 'terkirim' => 'Terkirim', 'dikembalikan' => 'Perlu perbaikan'];

    protected $table = 'praobservasi_supervisi';

    protected $guarded = ['id'];

    protected $casts = ['versi' => 'integer', 'isian' => 'array', 'dikirim_pada' => 'datetime'];

    public function sesi()
    {
        return $this->belongsTo(SesiSupervisi::class, 'sesi_supervisi_id');
    }

    public function lampiran()
    {
        return $this->hasMany(LampiranSupervisi::class, 'praobservasi_supervisi_id');
    }
}
