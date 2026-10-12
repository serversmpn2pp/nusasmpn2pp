<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaporanTindakLanjutSuratTu extends Model
{
    protected $table = 'laporan_tindak_lanjut_surat_tu';

    protected $guarded = ['id'];

    protected $casts = ['diajukan_pada' => 'datetime', 'diperiksa_pada' => 'datetime'];

    public function bukti(): HasMany
    {
        return $this->hasMany(BuktiTindakLanjutSuratTu::class, 'laporan_id');
    }
}
