<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanMapelRaporSts extends Model
{
    protected $table = 'pengaturan_mapel_rapor_sts';

    protected $guarded = ['id'];

    protected $casts = ['mapel_dikecualikan' => 'array', 'tingkat' => 'integer', 'versi' => 'integer'];

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diubah_oleh_pengguna_id');
    }
}
