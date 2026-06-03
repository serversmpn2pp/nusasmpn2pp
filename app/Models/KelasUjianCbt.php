<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasUjianCbt extends Model
{
    protected $table = 'kelas_ujian_cbt';

    protected $fillable = [
        'ujian_cbt_id',
        'kelas_id',
        'komponen_nilai_id',
    ];

    public function ujianCbt(): BelongsTo
    {
        return $this->belongsTo(UjianCbt::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function komponenNilai(): BelongsTo
    {
        return $this->belongsTo(KomponenNilai::class);
    }

    public function pesertaUjianCbt(): HasMany
    {
        return $this->hasMany(PesertaUjianCbt::class);
    }
}
