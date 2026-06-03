<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AkunPesertaCbt extends Model
{
    protected $table = 'akun_peserta_cbt';

    public const DAFTAR_STATUS = [
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
    ];

    protected $fillable = [
        'jenis_ujian_cbt_id',
        'tahun_pelajaran_id',
        'semester',
        'anggota_kelas_id',
        'nomor_peserta',
        'username',
        'kata_sandi',
        'kode_qr',
        'status',
    ];

    public function jenisUjianCbt(): BelongsTo
    {
        return $this->belongsTo(JenisUjianCbt::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function anggotaKelas(): BelongsTo
    {
        return $this->belongsTo(AnggotaKelas::class);
    }

    public function pesertaUjianCbt(): HasMany
    {
        return $this->hasMany(PesertaUjianCbt::class);
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }
}
