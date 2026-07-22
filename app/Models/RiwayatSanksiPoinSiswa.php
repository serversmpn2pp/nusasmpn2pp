<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatSanksiPoinSiswa extends Model
{
    protected $table = 'riwayat_sanksi_poin_siswa';

    protected $fillable = [
        'sanksi_poin_siswa_id',
        'jenis_kegiatan',
        'judul',
        'status_sebelum',
        'status_sesudah',
        'catatan',
        'data_tambahan',
        'dibuat_oleh_pengguna_id',
        'terjadi_pada',
    ];

    protected $casts = [
        'data_tambahan' => 'array',
        'terjadi_pada' => 'datetime',
    ];

    public function sanksiPoinSiswa(): BelongsTo
    {
        return $this->belongsTo(SanksiPoinSiswa::class);
    }

    public function dibuatOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }
}
