<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuktiRuangUjianCbt extends Model
{
    protected $table = 'bukti_ruang_ujian_cbt';

    public const JENIS_DAFTAR_HADIR = 'daftar_hadir';

    public const JENIS_BERITA_ACARA = 'berita_acara';

    protected $fillable = [
        'ruang_ujian_cbt_id',
        'jenis',
        'lokasi_file',
        'nama_file_asli',
        'tipe_file',
        'ukuran_file',
        'diunggah_oleh_pengguna_id',
        'diunggah_pada',
    ];

    protected $casts = [
        'ukuran_file' => 'integer',
        'diunggah_pada' => 'datetime',
    ];

    public function ruangUjianCbt(): BelongsTo
    {
        return $this->belongsTo(RuangUjianCbt::class);
    }

    public function diunggahOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diunggah_oleh_pengguna_id');
    }

    public function labelJenis(): string
    {
        return $this->jenis === self::JENIS_DAFTAR_HADIR ? 'Daftar hadir' : 'Berita acara';
    }

    public function ukuranRingkas(): string
    {
        $ukuran = (int) $this->ukuran_file;

        if ($ukuran >= 1_048_576) {
            return number_format($ukuran / 1_048_576, 1, ',', '.').' MB';
        }

        return number_format(max(1, $ukuran) / 1024, 0, ',', '.').' KB';
    }
}
