<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PertemuanTahfidz extends Model
{
    protected $table = 'pertemuan_tahfidz';

    protected $fillable = ['tanggal', 'selesai_pada', 'diselesaikan_oleh'];

    protected $casts = ['tanggal' => 'date', 'selesai_pada' => 'datetime'];

    public function setoran(): HasMany
    {
        return $this->hasMany(SetoranTahfidz::class);
    }
}
