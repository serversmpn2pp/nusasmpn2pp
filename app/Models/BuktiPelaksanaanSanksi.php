<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuktiPelaksanaanSanksi extends Model
{
    protected $table = 'bukti_pelaksanaan_sanksi';

    protected $fillable = [
        'sanksi_poin_siswa_id',
        'nama_file_asli',
        'lokasi_file',
        'tipe_file',
        'ukuran_file',
        'keterangan',
        'diunggah_oleh_pengguna_id',
        'diunggah_pada',
    ];

    protected $casts = [
        'ukuran_file' => 'integer',
        'diunggah_pada' => 'datetime',
    ];

    public function sanksiPoinSiswa(): BelongsTo
    {
        return $this->belongsTo(SanksiPoinSiswa::class);
    }

    public function diunggahOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diunggah_oleh_pengguna_id');
    }

    public function ukuranRingkas(): string
    {
        if ($this->ukuran_file >= 1048576) {
            return number_format($this->ukuran_file / 1048576, 1, ',', '.').' MB';
        }

        return max(1, (int) ceil($this->ukuran_file / 1024)).' KB';
    }
}
