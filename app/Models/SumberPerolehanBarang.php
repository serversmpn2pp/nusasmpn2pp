<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SumberPerolehanBarang extends Model
{
    protected $table = 'sumber_perolehan_barang';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function unitBarang(): HasMany
    {
        return $this->hasMany(UnitBarang::class);
    }
}
