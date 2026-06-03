<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function guruMataPelajaran(): HasMany
    {
        return $this->hasMany(GuruMataPelajaran::class);
    }

    public function perangkatAjar(): HasMany
    {
        return $this->hasMany(PerangkatAjar::class);
    }

    public function ujianOmr(): HasMany
    {
        return $this->hasMany(UjianOmr::class);
    }

    public function ujianCbt(): HasMany
    {
        return $this->hasMany(UjianCbt::class);
    }

    public function soalCbt(): HasMany
    {
        return $this->hasMany(SoalCbt::class);
    }
}
