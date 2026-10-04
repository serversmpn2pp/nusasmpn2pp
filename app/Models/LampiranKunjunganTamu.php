<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LampiranKunjunganTamu extends Model
{
    protected $table = 'lampiran_kunjungan_tamu';

    public const JENIS = ['surat_tugas' => 'Surat tugas', 'dokumentasi' => 'Dokumentasi kunjungan'];

    protected $fillable = ['jenis', 'lokasi_file', 'nama_file_asli', 'tipe_file', 'ukuran_file', 'diunggah_oleh_pengguna_id'];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(KunjunganTamu::class, 'kunjungan_tamu_id');
    }
}
