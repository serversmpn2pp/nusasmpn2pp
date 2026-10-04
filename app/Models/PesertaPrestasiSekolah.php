<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesertaPrestasiSekolah extends Model
{
    protected $table = 'peserta_prestasi_sekolah';

    public $timestamps = false;

    protected $fillable = ['siswa_id', 'pegawai_id', 'nama', 'kelas'];

    protected $casts = ['siswa_id' => 'integer', 'pegawai_id' => 'integer'];
}
