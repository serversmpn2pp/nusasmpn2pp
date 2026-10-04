<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengurusKomiteHumas extends Model
{
    protected $table = 'pengurus_komite_humas';

    public const JABATAN = ['ketua' => 'Ketua', 'wakil_ketua' => 'Wakil ketua', 'sekretaris' => 'Sekretaris', 'bendahara' => 'Bendahara', 'anggota' => 'Anggota'];

    protected $fillable = ['nama', 'jabatan', 'nomor_telepon', 'aktif'];

    protected $hidden = ['nomor_telepon'];

    protected $casts = ['nomor_telepon' => 'encrypted', 'aktif' => 'boolean'];
}
