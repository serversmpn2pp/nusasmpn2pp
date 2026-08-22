<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisUjianCbt extends Model
{
    protected $table = 'jenis_ujian_cbt';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'memerlukan_token',
        'dapat_diterapkan_ke_nilai',
        'urutan',
        'aktif',
    ];

    protected $casts = [
        'memerlukan_token' => 'boolean',
        'dapat_diterapkan_ke_nilai' => 'boolean',
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    public function ujianCbt(): HasMany
    {
        return $this->hasMany(UjianCbt::class);
    }
}
