<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuktiAkreditasiHumas extends Model
{
    protected $table = 'bukti_akreditasi_humas';

    protected $fillable = ['token_pembuatan', 'riwayat_dokumen_humas_id', 'judul', 'catatan', 'dibuat_oleh_pengguna_id'];

    protected $hidden = ['token_pembuatan'];

    protected $casts = ['dilepas_pada' => 'datetime'];

    public function berkas(): BelongsTo
    {
        return $this->belongsTo(RiwayatDokumenHumas::class, 'riwayat_dokumen_humas_id');
    }
}
