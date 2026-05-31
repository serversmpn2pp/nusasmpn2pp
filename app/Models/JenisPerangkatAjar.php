<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisPerangkatAjar extends Model
{
    protected $table = 'jenis_perangkat_ajar';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'wajib',
        'urutan',
        'aktif',
    ];

    protected $casts = [
        'wajib' => 'boolean',
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    public function perangkatAjar(): HasMany
    {
        return $this->hasMany(PerangkatAjar::class);
    }
}
