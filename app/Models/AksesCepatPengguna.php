<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AksesCepatPengguna extends Model
{
    protected $table = 'akses_cepat_pengguna';

    protected $fillable = [
        'pengguna_id',
        'kode_menu',
        'urutan',
    ];

    protected $casts = [
        'urutan' => 'integer',
    ];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
