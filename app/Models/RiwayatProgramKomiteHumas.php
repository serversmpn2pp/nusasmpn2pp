<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatProgramKomiteHumas extends Model
{
    protected $table = 'riwayat_program_komite_humas';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array', 'created_at' => 'datetime', 'versi' => 'integer'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }
}
