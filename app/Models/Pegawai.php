<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    protected $table = 'pegawai';

    protected $fillable = [
        'nama_lengkap',
        'nip',
        'nuptk',
        'nik',
        'foto',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'email',
        'no_hp',
        'status_kepegawaian',
        'golongan',
        'tanggal_mulai_kerja',
        'tanggal_mulai_bertugas',
        'jenis_pegawai',
        'jabatan_utama',
        'sumber_gaji',
        'pendidikan_terakhir',
        'jurusan_pendidikan',
        'tahun_lulus',
        'keterangan',
        'aktif',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_mulai_kerja' => 'date',
        'tanggal_mulai_bertugas' => 'date',
        'aktif' => 'boolean',
    ];
}
