<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsetPublikasiHumas extends Model
{
    protected $table = 'aset_publikasi_humas';

    public $timestamps = false;

    protected $fillable = ['aset_promosi_humas_id', 'riwayat_dokumen_humas_id', 'snapshot'];

    protected $casts = ['snapshot' => 'array'];

    public function berkas(): BelongsTo
    {
        return $this->belongsTo(RiwayatDokumenHumas::class, 'riwayat_dokumen_humas_id');
    }

    public function publikasi(): BelongsTo
    {
        return $this->belongsTo(PublikasiHumas::class, 'publikasi_humas_id');
    }

    public function aset(): BelongsTo
    {
        return $this->belongsTo(AsetPromosiHumas::class, 'aset_promosi_humas_id');
    }
}
