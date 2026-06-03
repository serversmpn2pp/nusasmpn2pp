<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PesertaUjianCbt extends Model
{
    protected $table = 'peserta_ujian_cbt';

    public const DAFTAR_STATUS = [
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
        'sedang_mengerjakan' => 'Sedang mengerjakan',
        'selesai' => 'Selesai',
        'terblokir' => 'Terblokir',
    ];

    protected $fillable = [
        'ujian_cbt_id',
        'sesi_ujian_cbt_id',
        'kelas_ujian_cbt_id',
        'anggota_kelas_id',
        'akun_peserta_cbt_id',
        'nomor_peserta',
        'username',
        'kata_sandi',
        'token_akses',
        'status',
        'waktu_mulai',
        'waktu_selesai',
        'menit_tersisa',
        'ip_terakhir',
        'perangkat_terakhir',
        'user_agent_terakhir',
        'catatan',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'menit_tersisa' => 'integer',
    ];

    public function ujianCbt(): BelongsTo
    {
        return $this->belongsTo(UjianCbt::class);
    }

    public function sesiUjianCbt(): BelongsTo
    {
        return $this->belongsTo(SesiUjianCbt::class);
    }

    public function kelasUjianCbt(): BelongsTo
    {
        return $this->belongsTo(KelasUjianCbt::class);
    }

    public function anggotaKelas(): BelongsTo
    {
        return $this->belongsTo(AnggotaKelas::class);
    }

    public function akunPesertaCbt(): BelongsTo
    {
        return $this->belongsTo(AkunPesertaCbt::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function jawabanPesertaUjianCbt(): HasMany
    {
        return $this->hasMany(JawabanPesertaUjianCbt::class);
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }
}
