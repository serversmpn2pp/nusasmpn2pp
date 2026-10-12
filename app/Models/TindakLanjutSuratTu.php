<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TindakLanjutSuratTu extends Model
{
    protected $table = 'tindak_lanjut_surat_tu';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'belum_mulai', 'versi' => 1];

    public const STATUS = ['belum_mulai' => 'Belum mulai', 'diproses' => 'Diproses',
        'menunggu_pemeriksaan' => 'Menunggu pemeriksaan', 'perlu_perbaikan' => 'Perlu perbaikan',
        'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];

    protected $casts = ['batas_tanggal' => 'date', 'dibaca_pada' => 'datetime', 'selesai_pada' => 'datetime', 'versi' => 'integer'];

    public function surat(): BelongsTo
    {
        return $this->belongsTo(SuratTu::class, 'surat_tu_id');
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pemeriksa_pengguna_id');
    }

    public function laporan(): HasMany
    {
        return $this->hasMany(LaporanTindakLanjutSuratTu::class, 'tindak_lanjut_id')->latest('nomor');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatTindakLanjutSuratTu::class, 'tindak_lanjut_id')->latest('id');
    }

    public function dapatDilaporkan(): bool
    {
        return in_array($this->status, ['belum_mulai', 'diproses', 'perlu_perbaikan'], true)
            && $this->surat->status !== 'dibatalkan';
    }

    public function terlambat(): bool
    {
        return ! in_array($this->status, ['selesai', 'dibatalkan', 'menunggu_pemeriksaan'], true)
            && $this->surat->status !== 'dibatalkan' && $this->batas_tanggal->lt(today());
    }
}
