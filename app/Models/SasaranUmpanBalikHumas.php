<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SasaranUmpanBalikHumas extends Model
{
    protected $table = 'sasaran_umpan_balik_humas';

    protected $fillable = ['orang_tua_wali_id', 'siswa_ids'];

    protected $hidden = ['token_pengiriman', 'orang_tua_wali_id', 'siswa_ids'];

    protected $casts = ['siswa_ids' => 'array', 'dikirim_pada' => 'datetime'];

    public function formulir(): BelongsTo
    {
        return $this->belongsTo(UmpanBalikHumas::class, 'umpan_balik_humas_id');
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(JawabanUmpanBalikHumas::class);
    }
}
