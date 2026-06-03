<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasUjianOmr extends Model
{
    protected $table = 'kelas_ujian_omr';

    protected $fillable = [
        'ujian_omr_id',
        'kelas_id',
        'komponen_nilai_id',
    ];

    public function ujianOmr(): BelongsTo
    {
        return $this->belongsTo(UjianOmr::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function komponenNilai(): BelongsTo
    {
        return $this->belongsTo(KomponenNilai::class);
    }

    public function lembarJawabUjianOmr(): HasMany
    {
        return $this->hasMany(LembarJawabUjianOmr::class);
    }
}
