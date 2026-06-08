<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UjianCbt extends Model
{
    protected $table = 'ujian_cbt';

    public const DAFTAR_STATUS = [
        'draft' => 'Draft',
        'terjadwal' => 'Terjadwal',
        'berlangsung' => 'Berlangsung',
        'selesai' => 'Selesai',
        'nonaktif' => 'Nonaktif',
    ];

    protected $fillable = [
        'jenis_ujian_cbt_id',
        'tahun_pelajaran_id',
        'mata_pelajaran_id',
        'kode',
        'nama',
        'semester',
        'tingkat',
        'tanggal_mulai',
        'tanggal_selesai',
        'durasi_menit',
        'jumlah_soal',
        'kkm',
        'token',
        'acak_soal',
        'acak_jawaban',
        'batasi_satu_perangkat',
        'deteksi_pindah_tab',
        'wajib_fullscreen',
        'tampilkan_hasil',
        'status',
        'petunjuk',
        'keterangan',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tingkat' => 'integer',
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
        'durasi_menit' => 'integer',
        'jumlah_soal' => 'integer',
        'kkm' => 'integer',
        'acak_soal' => 'boolean',
        'acak_jawaban' => 'boolean',
        'batasi_satu_perangkat' => 'boolean',
        'deteksi_pindah_tab' => 'boolean',
        'wajib_fullscreen' => 'boolean',
        'tampilkan_hasil' => 'boolean',
    ];

    public function jenisUjianCbt(): BelongsTo
    {
        return $this->belongsTo(JenisUjianCbt::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function kelasUjianCbt(): HasMany
    {
        return $this->hasMany(KelasUjianCbt::class);
    }

    public function soalUjianCbt(): HasMany
    {
        return $this->hasMany(SoalUjianCbt::class);
    }

    public function sesiUjianCbt(): HasMany
    {
        return $this->hasMany(SesiUjianCbt::class);
    }

    public function ruangUjianCbt(): HasMany
    {
        return $this->hasMany(RuangUjianCbt::class);
    }

    public function pesertaUjianCbt(): HasMany
    {
        return $this->hasMany(PesertaUjianCbt::class);
    }

    public function jadwalUjianCbt(): HasMany
    {
        return $this->hasMany(JadwalUjianCbt::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function labelWaktu(): string
    {
        if (! $this->tanggal_mulai && ! $this->tanggal_selesai) {
            return 'Belum dijadwalkan';
        }

        $mulai = $this->tanggal_mulai?->format('d-m-Y H:i') ?? '-';
        $selesai = $this->tanggal_selesai?->format('d-m-Y H:i') ?? '-';

        return "{$mulai} sampai {$selesai}";
    }
}
