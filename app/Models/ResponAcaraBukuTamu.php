<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponAcaraBukuTamu extends Model
{
    protected $table = 'respon_acara_buku_tamu';

    public const KATEGORI = ['pelajar' => 'Pelajar', 'pendidik' => 'Pendidik / tenaga kependidikan', 'orang_tua' => 'Orang tua / wali', 'umum' => 'Masyarakat umum'];

    public const MINAT = ['karya_siswa' => 'Karya siswa', 'literasi' => 'Literasi', 'numerasi' => 'Numerasi', 'nusa' => 'NUSA', 'lainnya' => 'Lainnya'];

    public const KEPUASAN = [5 => 'Sangat puas', 4 => 'Puas', 3 => 'Netral', 2 => 'Kurang puas', 1 => 'Tidak puas'];

    protected $guarded = ['id'];

    protected $hidden = ['kunci_kiriman', 'sidik_kiriman'];

    protected $casts = ['minat' => 'array', 'kepuasan' => 'integer', 'nilai_nusa' => 'integer', 'versi' => 'integer'];

    public function acaraBukuTamu(): BelongsTo
    {
        return $this->belongsTo(AcaraBukuTamu::class);
    }
}
