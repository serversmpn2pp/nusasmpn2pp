<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPembukaanSusulanCbt extends Model
{
    protected $table = 'riwayat_pembukaan_susulan_cbt';

    protected $fillable = [
        'peserta_ujian_cbt_id', 'nilai_siswa_id', 'nilai_sebelumnya',
        'keadaan_peserta_sebelumnya', 'alasan', 'dibuka_oleh_pengguna_id',
    ];

    protected $casts = [
        'nilai_sebelumnya' => 'array',
        'keadaan_peserta_sebelumnya' => 'array',
    ];

    public function dibukaOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuka_oleh_pengguna_id');
    }

    public function pesertaUjianCbt(): BelongsTo
    {
        return $this->belongsTo(PesertaUjianCbt::class);
    }
}
