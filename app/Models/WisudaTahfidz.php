<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WisudaTahfidz extends Model
{
    protected $table = 'wisuda_tahfidz';

    protected $fillable = ['nama', 'tanggal', 'lokasi', 'kepala_sekolah'];

    protected $casts = ['tanggal' => 'date'];

    public function kelulusan(): HasMany
    {
        return $this->hasMany(KelulusanTahfidz::class);
    }
}
