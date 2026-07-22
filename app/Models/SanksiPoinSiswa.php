<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SanksiPoinSiswa extends Model
{
    public const DAFTAR_STATUS = [
        'menunggu' => 'Menunggu Penugasan',
        'diproses' => 'Sedang Diproses',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];

    public const STATUS_FINAL = ['selesai', 'dibatalkan'];

    protected $table = 'sanksi_poin_siswa';

    protected $fillable = [
        'siswa_id',
        'tahun_pelajaran_id',
        'aturan_sanksi_poin_id',
        'poin_saat_terpicu',
        'status',
        'terpicu_pada',
        'mulai_diproses_pada',
        'batas_pelaksanaan',
        'dilaksanakan_pada',
        'petugas_pegawai_id',
        'catatan',
        'hasil_pelaksanaan',
        'diperbarui_oleh_pengguna_id',
    ];

    protected $casts = [
        'poin_saat_terpicu' => 'integer',
        'terpicu_pada' => 'datetime',
        'mulai_diproses_pada' => 'datetime',
        'batas_pelaksanaan' => 'date',
        'dilaksanakan_pada' => 'datetime',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function aturanSanksiPoin(): BelongsTo
    {
        return $this->belongsTo(AturanSanksiPoin::class);
    }

    public function petugasPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'petugas_pegawai_id');
    }

    public function diperbaruiOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diperbarui_oleh_pengguna_id');
    }

    public function riwayatSanksiPoinSiswa(): HasMany
    {
        return $this->hasMany(RiwayatSanksiPoinSiswa::class);
    }

    public function buktiPelaksanaanSanksi(): HasMany
    {
        return $this->hasMany(BuktiPelaksanaanSanksi::class);
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function sudahFinal(): bool
    {
        return in_array($this->status, self::STATUS_FINAL, true);
    }

    public function terlambat(): bool
    {
        return ! $this->sudahFinal()
            && $this->batas_pelaksanaan
            && today()->greaterThan($this->batas_pelaksanaan);
    }
}
