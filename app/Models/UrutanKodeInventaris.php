<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrutanKodeInventaris extends Model
{
    protected $table = 'urutan_kode_inventaris';

    protected $fillable = [
        'jenis',
        'tahun',
        'nomor_terakhir',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'nomor_terakhir' => 'integer',
    ];
}
