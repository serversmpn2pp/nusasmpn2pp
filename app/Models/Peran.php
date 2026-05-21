<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use RuntimeException;

class Peran extends Model
{
    protected $table = 'peran';

    protected $fillable = [
        'nama',
        'kode',
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
        static::deleting(function (Peran $peran) {
            if ($peran->sistem) {
                throw new RuntimeException('Peran sistem tidak dapat dihapus.');
            }
        });
    }

    public function pengguna(): BelongsToMany
    {
        return $this->belongsToMany(Pengguna::class, 'pengguna_peran')
            ->withTimestamps();
    }

    public function izin(): BelongsToMany
    {
        return $this->belongsToMany(Izin::class, 'peran_izin')
            ->withTimestamps();
    }

    public function memilikiIzin(string|array $kode): bool
    {
        $kode = (array) $kode;

        if ($this->relationLoaded('izin')) {
            return $this->izin
                ->whereIn('kode', $kode)
                ->where('aktif', true)
                ->isNotEmpty();
        }

        return $this->izin()
            ->whereIn('kode', $kode)
            ->where('aktif', true)
            ->exists();
    }
}
