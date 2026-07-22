<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaksiLaporanPembinaanSiswa extends Model
{
    protected $table = 'saksi_laporan_pembinaan_siswa';

    public const DAFTAR_JENIS = ['siswa' => 'Siswa', 'pegawai' => 'Pegawai', 'lainnya' => 'Lainnya'];

    protected $fillable = [
        'laporan_pembinaan_siswa_id', 'jenis_saksi', 'siswa_id', 'pegawai_id',
        'nama_saksi', 'pernyataan', 'dibuat_oleh_pengguna_id',
    ];

    public function laporanPembinaanSiswa(): BelongsTo
    {
        return $this->belongsTo(LaporanPembinaanSiswa::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function dibuatOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function labelJenis(): string
    {
        return self::DAFTAR_JENIS[$this->jenis_saksi] ?? str($this->jenis_saksi)->headline()->toString();
    }
}
