<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstrumenSupervisi extends Model
{
    protected $table = 'instrumen_supervisi';

    protected $guarded = ['id'];

    protected $casts = ['aktif' => 'boolean'];

    public function versiInstrumen()
    {
        return $this->hasMany(VersiInstrumenSupervisi::class)->orderByDesc('nomor');
    }
}
