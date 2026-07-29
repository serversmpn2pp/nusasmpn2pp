<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanMataPelajaran extends Model
{
    protected $table = 'pengaturan_mata_pelajaran';

    protected $fillable = [
        'tahun_pelajaran_id',
        'mata_pelajaran_id',
        'tingkat',
        'kode',
        'kkm',
        'aktif',
    ];

    protected $casts = [
        'tingkat' => 'integer',
        'kkm' => 'integer',
        'aktif' => 'boolean',
    ];

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }
}
