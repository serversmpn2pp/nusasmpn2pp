<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatTindakLanjutSuratTu extends Model
{
    protected $table = 'riwayat_tindak_lanjut_surat_tu';

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }
}
