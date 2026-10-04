<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatBundelPertemuanHumas extends Model
{
    public $timestamps = false;

    protected $table = 'riwayat_bundel_pertemuan_humas';

    protected $fillable = ['agenda_humas_id', 'pengguna_id', 'ringkasan', 'sha256', 'ukuran_byte', 'created_at'];

    protected $casts = ['ringkasan' => 'array', 'ukuran_byte' => 'integer', 'created_at' => 'datetime'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
