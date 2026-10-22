<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqHumas extends Model
{
    protected $table = 'faq_humas';

    public const SASARAN = ['semua' => 'Semua akun', 'orang_tua' => 'Orang tua', 'siswa' => 'Siswa', 'pegawai' => 'Pegawai'];

    public const STATUS = ['draf' => 'Draf', 'terbit' => 'Diterbitkan', 'arsip' => 'Diarsipkan'];

    public const KOLOM = ['pertanyaan', 'jawaban', 'urusan', 'sasaran', 'urutan'];

    protected $fillable = ['token_pembuatan', ...self::KOLOM];

    protected $hidden = ['token_pembuatan'];

    protected $casts = ['versi' => 'integer', 'urutan' => 'integer', 'diterbitkan_pada' => 'datetime'];
}
