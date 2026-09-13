<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerangkatNotifikasiPush extends Model
{
    protected $table = 'perangkat_notifikasi_push';

    protected $fillable = [
        'pengguna_id',
        'token',
        'platform',
        'nama_perangkat',
        'versi_aplikasi',
        'aktif',
        'terakhir_terlihat_pada',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'terakhir_terlihat_pada' => 'datetime',
    ];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
