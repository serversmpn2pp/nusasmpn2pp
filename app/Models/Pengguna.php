<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use RuntimeException;

class Pengguna extends Authenticatable
{
    use Notifiable;

    protected $table = 'pengguna';

    protected $authPasswordName = 'kata_sandi';

    protected $fillable = [
        'pegawai_id',
        'nama',
        'username',
        'kata_sandi',
        'peran',
        'aktif',
        'akun_sistem',
        'terakhir_login_pada',
    ];

    protected $hidden = [
        'kata_sandi',
        'remember_token',
    ];

    protected $casts = [
        'kata_sandi' => 'hashed',
        'aktif' => 'boolean',
        'akun_sistem' => 'boolean',
        'terakhir_login_pada' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Pengguna $pengguna) {
            if ($pengguna->akun_sistem) {
                throw new RuntimeException('Akun sistem tidak dapat dihapus.');
            }
        });
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function daftarPeran(): BelongsToMany
    {
        return $this->belongsToMany(Peran::class, 'pengguna_peran')
            ->withTimestamps();
    }

    public function memilikiPeran(string|array $kode): bool
    {
        $kode = (array) $kode;

        if ($this->relationLoaded('daftarPeran')) {
            return $this->daftarPeran
                ->whereIn('kode', $kode)
                ->where('aktif', true)
                ->isNotEmpty();
        }

        return $this->daftarPeran()
            ->whereIn('kode', $kode)
            ->where('aktif', true)
            ->exists();
    }

    public function memilikiIzin(string|array $kode): bool
    {
        $kode = (array) $kode;

        if ($this->administrator()) {
            return true;
        }

        return $this->daftarPeran()
            ->where('peran.aktif', true)
            ->whereHas('izin', function ($query) use ($kode) {
                $query->whereIn('izin.kode', $kode)
                    ->where('izin.aktif', true);
            })
            ->exists();
    }

    public function administrator(): bool
    {
        return $this->peran === 'administrator'
            || $this->akun_sistem
            || $this->memilikiPeran('administrator');
    }

    public function akunPegawai(): bool
    {
        return ! $this->akun_sistem && filled($this->pegawai_id);
    }
}
