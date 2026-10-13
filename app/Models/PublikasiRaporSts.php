<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublikasiRaporSts extends Model
{
    protected $table = 'publikasi_rapor_sts';

    protected $guarded = ['id'];

    protected $casts = ['salinan' => 'array', 'aktif' => 'boolean', 'sertakan_perilaku' => 'boolean',
        'versi' => 'integer', 'dipublikasikan_pada' => 'datetime', 'dibatalkan_pada' => 'datetime'];

    public function raporStsKelas(): BelongsTo
    {
        return $this->belongsTo(RaporStsKelas::class);
    }
}
