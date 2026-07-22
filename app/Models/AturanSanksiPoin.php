<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AturanSanksiPoin extends Model
{
    protected $table = 'aturan_sanksi_poin';

    protected $fillable = ['batas_poin', 'nama', 'deskripsi', 'urutan', 'aktif'];

    protected $casts = [
        'batas_poin' => 'integer',
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    public function sanksiPoinSiswa(): HasMany
    {
        return $this->hasMany(SanksiPoinSiswa::class);
    }
}
