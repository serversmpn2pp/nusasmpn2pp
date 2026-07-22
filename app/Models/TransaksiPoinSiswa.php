<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaksiPoinSiswa extends Model
{
    protected $table = 'transaksi_poin_siswa';

    protected $fillable = [
        'siswa_id',
        'tahun_pelajaran_id',
        'laporan_pembinaan_siswa_id',
        'pengurangan_poin_siswa_id',
        'kunci_sumber',
        'jenis',
        'poin',
        'keterangan',
        'tercatat_pada',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'poin' => 'integer',
        'tercatat_pada' => 'datetime',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function laporanPembinaanSiswa(): BelongsTo
    {
        return $this->belongsTo(LaporanPembinaanSiswa::class);
    }

    public function penguranganPoinSiswa(): BelongsTo
    {
        return $this->belongsTo(PenguranganPoinSiswa::class);
    }
}
