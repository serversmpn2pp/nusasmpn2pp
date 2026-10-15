<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LampiranSupervisi extends Model
{
    protected $table = 'lampiran_supervisi';

    protected $guarded = ['id'];

    protected $hidden = ['lokasi_file'];

    public function praobservasi()
    {
        return $this->belongsTo(PraobservasiSupervisi::class, 'praobservasi_supervisi_id');
    }
}
