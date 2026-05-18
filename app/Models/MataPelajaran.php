<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MataPelajaran extends Model
{
    protected $table = 'mata_pelajaran';

    protected $fillable = [
        'kode',
        'nama',
        'kelompok',
        'tingkat',
        'kkm',
        'urutan',
        'aktif',
        'keterangan',
    ];

    protected $casts = [
        'tingkat' => 'integer',
        'kkm' => 'integer',
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];
}
