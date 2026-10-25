<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerangkatMonitoring extends Model
{
    protected $table = 'perangkat_monitoring';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected $casts = ['terakhir_digunakan_pada' => 'datetime', 'dicabut_pada' => 'datetime'];
}
