<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KlarifikasiSiswaPembinaan extends Model
{
    protected $table = 'klarifikasi_siswa_pembinaan';

    public const DAFTAR_METODE = [
        'langsung' => 'Disampaikan langsung',
        'tertulis' => 'Pernyataan tertulis',
        'didampingi' => 'Disampaikan dengan pendamping',
    ];

    protected $fillable = [
        'laporan_pembinaan_siswa_id', 'isi_klarifikasi', 'metode', 'pendamping',
        'disampaikan_pada', 'dicatat_oleh_pengguna_id',
    ];

    protected $casts = ['disampaikan_pada' => 'datetime'];

    public function laporanPembinaanSiswa(): BelongsTo
    {
        return $this->belongsTo(LaporanPembinaanSiswa::class);
    }

    public function dicatatOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dicatat_oleh_pengguna_id');
    }

    public function labelMetode(): string
    {
        return self::DAFTAR_METODE[$this->metode] ?? str($this->metode)->headline()->toString();
    }
}
