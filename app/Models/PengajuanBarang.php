<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanBarang extends Model
{
    protected $table = 'pengajuan_barang';

    public const DAFTAR_JENIS = [
        'peminjaman' => 'Peminjaman aset',
        'permintaan' => 'Permintaan barang habis pakai',
    ];

    public const DAFTAR_STATUS = [
        'menunggu' => 'Menunggu petugas',
        'dipenuhi' => 'Dipenuhi',
        'ditolak' => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
    ];

    protected $fillable = [
        'nomor_pengajuan',
        'pegawai_id',
        'barang_id',
        'jenis_pengajuan',
        'jumlah',
        'tanggal_pengajuan',
        'tanggal_dibutuhkan',
        'rencana_kembali',
        'tujuan',
        'status',
        'catatan_petugas',
        'diproses_oleh_pengguna_id',
        'diproses_pada',
        'peminjaman_barang_id',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'tanggal_pengajuan' => 'date',
        'tanggal_dibutuhkan' => 'date',
        'rencana_kembali' => 'date',
        'diproses_pada' => 'datetime',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diproses_oleh_pengguna_id');
    }

    public function peminjamanBarang(): BelongsTo
    {
        return $this->belongsTo(PeminjamanBarang::class);
    }

    public function labelJenis(): string
    {
        return self::DAFTAR_JENIS[$this->jenis_pengajuan]
            ?? str($this->jenis_pengajuan)->headline()->toString();
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status]
            ?? str($this->status)->headline()->toString();
    }

    public function masihMenunggu(): bool
    {
        return $this->status === 'menunggu';
    }
}
