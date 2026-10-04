<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatAkreditasiHumas extends Model
{
    protected $table = 'riwayat_akreditasi_humas';

    public $timestamps = false;

    protected $fillable = ['aksi', 'versi', 'catatan', 'snapshot', 'pengguna_id', 'created_at'];

    protected $casts = ['snapshot' => 'array', 'created_at' => 'datetime'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
