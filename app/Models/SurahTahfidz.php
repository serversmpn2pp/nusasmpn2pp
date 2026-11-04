<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurahTahfidz extends Model
{
    protected $table = 'surah_tahfidz';

    protected $primaryKey = 'nomor';

    public $incrementing = false;

    public $timestamps = false;
}
