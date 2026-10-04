<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LampiranPengaduanHumas extends Model
{
    protected $table = 'lampiran_pengaduan_humas';

    protected $fillable = ['lokasi_file', 'nama_file_asli', 'tipe_file', 'ukuran_file', 'diunggah_oleh_pengguna_id', 'asal'];

    protected $hidden = ['lokasi_file'];
}
