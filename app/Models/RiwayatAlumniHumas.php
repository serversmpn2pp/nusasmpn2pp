<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatAlumniHumas extends Model
{
    protected $table = 'riwayat_alumni_humas';

    public $timestamps = false;

    protected $fillable = ['aksi', 'versi', 'snapshot', 'snapshot_privat', 'catatan_perubahan', 'pengguna_id', 'created_at'];

    protected $hidden = ['snapshot_privat', 'catatan_perubahan'];

    protected $casts = ['versi' => 'integer', 'snapshot' => 'array', 'snapshot_privat' => 'encrypted:array', 'catatan_perubahan' => 'encrypted', 'created_at' => 'datetime'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
