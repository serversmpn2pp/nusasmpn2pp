<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
