<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ButirAkreditasiHumas extends Model
{
    public const STATUS = ['belum_diperiksa' => 'Belum diperiksa', 'perlu_perbaikan' => 'Perlu perbaikan', 'terpenuhi' => 'Terpenuhi', 'tidak_berlaku' => 'Tidak berlaku'];

    public const KOLOM = ['kode', 'judul', 'deskripsi', 'urutan', 'target_bukti'];

    protected $table = 'butir_akreditasi_humas';

    protected $fillable = self::KOLOM;

    protected $casts = ['urutan' => 'integer', 'target_bukti' => 'integer', 'diperiksa_pada' => 'datetime', 'dihapus_pada' => 'datetime'];

    public function portofolio(): BelongsTo
    {
        return $this->belongsTo(PortofolioAkreditasiHumas::class, 'portofolio_akreditasi_humas_id');
    }

    public function bukti(): HasMany
    {
        return $this->hasMany(BuktiAkreditasiHumas::class)->whereNull('dilepas_pada')->orderBy('id');
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diperiksa_oleh_pengguna_id');
    }
}
