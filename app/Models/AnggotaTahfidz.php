<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnggotaTahfidz extends Model
{
    protected $table = 'anggota_tahfidz';

    protected $casts = ['aktif' => 'boolean'];

    public function kelompok(): BelongsTo
    {
        return $this->belongsTo(KelompokTahfidz::class, 'kelompok_tahfidz_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function setoran(): HasMany
    {
        return $this->hasMany(SetoranTahfidz::class);
    }
}
