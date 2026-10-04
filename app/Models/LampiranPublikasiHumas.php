<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LampiranPublikasiHumas extends Model
{
    protected $table = 'lampiran_publikasi_humas';

    protected $fillable = ['jenis', 'lokasi_file', 'nama_file_asli', 'tipe_file', 'ukuran_file', 'dihapus_pada'];

    protected $hidden = ['lokasi_file'];

    protected $casts = ['dihapus_pada' => 'datetime'];
}
