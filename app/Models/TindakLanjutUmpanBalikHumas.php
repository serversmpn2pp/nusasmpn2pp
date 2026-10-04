<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TindakLanjutUmpanBalikHumas extends Model
{
    public const STATUS = ['belum_mulai' => 'Belum mulai', 'diproses' => 'Diproses', 'selesai' => 'Selesai'];

    protected $table = 'tindak_lanjut_umpan_balik_humas';

    protected $fillable = ['token_pembuatan', 'pertanyaan_umpan_balik_humas_id', 'uraian', 'penanggung_jawab', 'batas_tanggal', 'status', 'hasil', 'bagikan_ringkasan', 'ringkasan_publik'];

    protected $hidden = ['token_pembuatan'];

    protected $casts = ['batas_tanggal' => 'date', 'bagikan_ringkasan' => 'boolean', 'selesai_pada' => 'datetime'];

    public function terlambat(): bool
    {
        return $this->status !== 'selesai' && $this->batas_tanggal->lt(today());
    }
}
