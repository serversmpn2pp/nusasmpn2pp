<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use RuntimeException;

class Izin extends Model
{
    protected $table = 'izin';

    protected $fillable = [
        'nama',
        'kode',
        'kelompok',
        'deskripsi',
        'sistem',
        'aktif',
    ];

    protected $casts = [
        'sistem' => 'boolean',
        'aktif' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Izin $izin) {
            if ($izin->sistem) {
                throw new RuntimeException('Izin sistem tidak dapat dihapus.');
            }
        });
    }

    public function peran(): BelongsToMany
    {
        return $this->belongsToMany(Peran::class, 'peran_izin')
            ->withTimestamps();
    }
}
