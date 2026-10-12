<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatSuratTu extends Model
{
    protected $table = 'riwayat_surat_tu';

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
