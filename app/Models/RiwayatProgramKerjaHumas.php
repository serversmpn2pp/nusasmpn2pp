<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatProgramKerjaHumas extends Model
{
    protected $table = 'riwayat_program_kerja_humas';

    public $timestamps = false;

    protected $fillable = ['laporan_pelaksanaan_humas_id', 'aksi', 'versi', 'snapshot', 'catatan_perubahan', 'pengguna_id', 'created_at'];

    protected $casts = ['snapshot' => 'array', 'created_at' => 'datetime'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
