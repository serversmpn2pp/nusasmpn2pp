<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKemitraanHumas extends Model
{
    protected $table = 'riwayat_kemitraan_humas';

    public $timestamps = false;

    protected $fillable = ['kerja_sama_humas_id', 'aksi', 'data_sebelum', 'data_sesudah', 'pengguna_id', 'created_at'];

    protected $casts = ['data_sebelum' => 'array', 'data_sesudah' => 'array', 'created_at' => 'datetime'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
