<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenugasanSuratTu extends Model
{
    protected $table = 'penugasan_surat_tu';

    protected $guarded = ['id'];

    protected $casts = ['dibaca_pada' => 'datetime'];

    public function surat(): BelongsTo
    {
        return $this->belongsTo(SuratTu::class, 'surat_tu_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }
}
