<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KegiatanUjianCbt extends Model
{
    protected $table = 'kegiatan_ujian_cbt';

    public const DAFTAR_STATUS = [
        'draft' => 'Draft',
        'aktif' => 'Aktif',
        'selesai' => 'Selesai',
        'nonaktif' => 'Nonaktif',
    ];

    protected $fillable = [
        'jenis_ujian_cbt_id',
        'tahun_pelajaran_id',
        'kode',
        'nama',
        'semester',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'keterangan',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function jenisUjianCbt(): BelongsTo
    {
        return $this->belongsTo(JenisUjianCbt::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function jadwalUjianCbt(): HasMany
    {
        return $this->hasMany(JadwalUjianCbt::class);
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function labelPeriode(): string
    {
        if (! $this->tanggal_mulai && ! $this->tanggal_selesai) {
            return 'Belum diatur';
        }

        $mulai = $this->tanggal_mulai?->format('d-m-Y') ?? '-';
        $selesai = $this->tanggal_selesai?->format('d-m-Y') ?? '-';

        return "{$mulai} sampai {$selesai}";
    }
}
