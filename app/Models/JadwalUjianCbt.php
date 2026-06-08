<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JadwalUjianCbt extends Model
{
    protected $table = 'jadwal_ujian_cbt';

    public const DAFTAR_STATUS = [
        'draft' => 'Draft',
        'siap' => 'Siap',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];

    protected $fillable = [
        'kegiatan_ujian_cbt_id',
        'ujian_cbt_id',
        'mata_pelajaran_id',
        'tanggal',
        'waktu_mulai',
        'waktu_selesai',
        'label_sesi',
        'tingkat',
        'urutan',
        'status',
        'keterangan',
        'dikunci_pada',
        'dikunci_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tingkat' => 'integer',
        'urutan' => 'integer',
        'dikunci_pada' => 'datetime',
    ];

    public function kegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(KegiatanUjianCbt::class);
    }

    public function ujianCbt(): BelongsTo
    {
        return $this->belongsTo(UjianCbt::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function dikunciOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dikunci_oleh_pengguna_id');
    }

    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'jadwal_ujian_cbt_kelas')
            ->withTimestamps();
    }

    public function terkunci(): bool
    {
        return filled($this->dikunci_pada);
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function labelWaktu(): string
    {
        return substr((string) $this->waktu_mulai, 0, 5) . ' - ' . substr((string) $this->waktu_selesai, 0, 5);
    }
}
