<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BerkasSuratTu extends Model
{
    protected $table = 'berkas_surat_tu';

    protected $guarded = ['id'];

    protected $casts = ['aktif' => 'boolean', 'ukuran_file' => 'integer'];

    public function surat(): BelongsTo
    {
        return $this->belongsTo(SuratTu::class, 'surat_tu_id');
    }
}
