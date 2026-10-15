<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatSupervisi extends Model
{
    public $timestamps = false;

    protected $table = 'riwayat_supervisi';

    protected $guarded = ['id'];

    protected $casts = ['salinan' => 'array', 'created_at' => 'datetime'];

    public function pengguna()
    {
        return $this->belongsTo(Pengguna::class);
    }
}
