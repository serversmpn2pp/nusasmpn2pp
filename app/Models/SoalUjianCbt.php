<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoalUjianCbt extends Model
{
    protected $table = 'soal_ujian_cbt';

    protected $fillable = [
        'ujian_cbt_id',
        'soal_cbt_id',
        'nomor_urut',
        'bobot',
    ];

    protected $casts = [
        'nomor_urut' => 'integer',
        'bobot' => 'decimal:2',
    ];

    public function ujianCbt(): BelongsTo
    {
        return $this->belongsTo(UjianCbt::class);
    }

    public function soalCbt(): BelongsTo
    {
        return $this->belongsTo(SoalCbt::class);
    }

    public function jawabanPesertaUjianCbt(): HasMany
    {
        return $this->hasMany(JawabanPesertaUjianCbt::class);
    }
}
