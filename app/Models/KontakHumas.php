<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KontakHumas extends Model
{
    protected $table = 'kontak_humas';

    public const IZIN = ['kontak_humas.lihat', 'kontak_humas.kontak', 'kontak_humas.kelola'];

    public const STATUS = ['aktif' => 'Aktif', 'arsip' => 'Diarsipkan'];

    public const KOLOM = ['nama', 'instansi', 'jabatan', 'urusan', 'nomor_kontak', 'email', 'dasar_penggunaan', 'catatan', 'status'];

    protected $fillable = ['token_pembuatan', ...self::KOLOM];

    protected $hidden = ['token_pembuatan', 'nomor_kontak', 'email', 'dasar_penggunaan', 'catatan'];

    protected $casts = ['versi' => 'integer'];
}
