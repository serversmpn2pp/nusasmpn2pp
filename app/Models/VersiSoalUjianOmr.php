<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VersiSoalUjianOmr extends Model
{
    protected $table = 'versi_soal_ujian_omr';

    protected $fillable = [
        'ujian_omr_id',
        'kode',
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function ujianOmr(): BelongsTo
    {
        return $this->belongsTo(UjianOmr::class);
    }

    public function kunciJawaban(): HasMany
    {
        return $this->hasMany(KunciJawabanUjianOmr::class);
    }

    public function lembarJawabUjianOmr(): HasMany
    {
        return $this->hasMany(LembarJawabUjianOmr::class);
    }
}
