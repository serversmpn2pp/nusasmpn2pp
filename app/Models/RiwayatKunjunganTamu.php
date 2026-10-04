<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKunjunganTamu extends Model
{
    protected $table = 'riwayat_kunjungan_tamu';

    public $timestamps = false;

    public const AKSI = ['datang' => 'Kedatangan dicatat', 'pulang' => 'Kepulangan dicatat', 'koreksi' => 'Data dikoreksi',
        'batal' => 'Kunjungan dibatalkan', 'lampiran' => 'Lampiran ditambahkan', 'hapus_lampiran' => 'Lampiran dihapus'];

    protected $fillable = ['aksi', 'token_operasi', 'data_sebelum', 'data_sesudah', 'pengguna_id', 'created_at'];

    protected $casts = ['data_sebelum' => 'array', 'data_sesudah' => 'array', 'created_at' => 'datetime'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
