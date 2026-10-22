<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KinerjaSemesterHumas extends Model
{
    protected $table = 'kinerja_semester_humas';

    public const STATUS = ['draf' => 'Draf', 'final' => 'Final'];

    public const SEMESTER = ['ganjil' => 'Semester ganjil', 'genap' => 'Semester genap'];

    public const KOLOM = ['judul', 'ringkasan', 'capaian', 'kendala', 'tindak_lanjut', 'rekomendasi', 'tanggal_laporan',
        'nama_penyusun', 'nip_penyusun', 'nama_pimpinan', 'nip_pimpinan'];

    protected $fillable = ['token_pembuatan', 'tahun_pelajaran_id', 'semester', ...self::KOLOM];

    protected $hidden = ['token_pembuatan'];

    protected $casts = ['versi' => 'integer', 'snapshot' => 'array', 'tanggal_laporan' => 'date', 'difinalisasi_pada' => 'datetime'];
}
