<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesanPengaduanHumas extends Model
{
    protected $table = 'pesan_pengaduan_humas';

    public $timestamps = false;

    protected $fillable = ['token_pengiriman', 'asal', 'isi', 'pengguna_id', 'created_at'];

    protected $hidden = ['token_pengiriman', 'pengguna_id'];

    protected $casts = ['created_at' => 'datetime'];
}
