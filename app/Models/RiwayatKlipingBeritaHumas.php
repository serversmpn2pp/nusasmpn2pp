<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKlipingBeritaHumas extends Model
{
    protected $table = 'riwayat_kliping_berita_humas';

    public $timestamps = false;

    protected $fillable = ['versi', 'aksi', 'snapshot', 'riwayat_dokumen_humas_id', 'catatan_perubahan', 'pengguna_id', 'created_at'];

    protected $casts = ['snapshot' => 'array', 'created_at' => 'datetime'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }

    public function berkas(): BelongsTo
    {
        return $this->belongsTo(RiwayatDokumenHumas::class, 'riwayat_dokumen_humas_id');
    }
}
