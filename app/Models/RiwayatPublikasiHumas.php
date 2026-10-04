<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPublikasiHumas extends Model
{
    protected $table = 'riwayat_publikasi_humas';

    public $timestamps = false;

    protected $fillable = ['aksi', 'versi', 'snapshot', 'catatan', 'pengguna_id', 'created_at'];

    protected $casts = ['snapshot' => 'array', 'created_at' => 'datetime'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
