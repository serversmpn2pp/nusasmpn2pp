<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKlasifikasiDokumenHumas extends Model
{
    protected $table = 'riwayat_klasifikasi_dokumen_humas';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['sebelum' => 'array', 'sesudah' => 'array', 'created_at' => 'datetime'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
