<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatMediaResmiHumas extends Model
{
    protected $table = 'riwayat_media_resmi_humas';

    public $timestamps = false;

    protected $fillable = ['versi', 'aksi', 'snapshot', 'catatan_perubahan', 'pengguna_id', 'created_at'];

    protected $casts = ['snapshot' => 'array', 'created_at' => 'datetime'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
