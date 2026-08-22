<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanitiaUjianCbt extends Model
{
    protected $table = 'panitia_ujian_cbt';

    public const DAFTAR_JABATAN = [
        'ketua' => 'Ketua',
        'sekretaris' => 'Sekretaris',
        'proktor' => 'Proktor',
        'teknisi' => 'Teknisi',
        'anggota' => 'Anggota',
    ];

    protected $fillable = [
        'kegiatan_ujian_cbt_id',
        'pegawai_id',
        'jabatan',
        'aktif',
        'catatan',
        'ditugaskan_oleh_pengguna_id',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function kegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(KegiatanUjianCbt::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function ditugaskanOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'ditugaskan_oleh_pengguna_id');
    }

    public function labelJabatan(): string
    {
        return self::DAFTAR_JABATAN[$this->jabatan] ?? str($this->jabatan)->headline()->toString();
    }
}
