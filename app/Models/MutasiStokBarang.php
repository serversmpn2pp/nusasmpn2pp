<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutasiStokBarang extends Model
{
    protected $table = 'mutasi_stok_barang';

    public const DAFTAR_JENIS = [
        'masuk' => 'Stok masuk',
        'keluar' => 'Stok keluar',
        'penyesuaian' => 'Penyesuaian stok',
    ];

    public const DAFTAR_KATEGORI = [
        'stok_awal' => 'Stok awal',
        'pembelian' => 'Pembelian',
        'hibah' => 'Hibah',
        'pengembalian' => 'Pengembalian',
        'pengeluaran_pemakaian' => 'Pengeluaran pemakaian',
        'peminjaman' => 'Peminjaman',
        'rusak' => 'Barang rusak',
        'hilang' => 'Barang hilang',
        'penyesuaian_fisik' => 'Penyesuaian hasil cek fisik',
        'lainnya' => 'Lainnya',
    ];

    public const KATEGORI_PER_JENIS = [
        'masuk' => ['stok_awal', 'pembelian', 'hibah', 'pengembalian', 'lainnya'],
        'keluar' => ['pengeluaran_pemakaian', 'peminjaman', 'rusak', 'hilang', 'lainnya'],
        'penyesuaian' => ['penyesuaian_fisik', 'lainnya'],
    ];

    protected $fillable = [
        'saldo_stok_barang_id',
        'barang_id',
        'lokasi_barang_id',
        'jenis_mutasi',
        'kategori_mutasi',
        'tanggal_mutasi',
        'jumlah_perubahan',
        'saldo_sebelum',
        'saldo_sesudah',
        'referensi',
        'keterangan',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal_mutasi' => 'date',
        'jumlah_perubahan' => 'decimal:2',
        'saldo_sebelum' => 'decimal:2',
        'saldo_sesudah' => 'decimal:2',
    ];

    public function saldoStokBarang(): BelongsTo
    {
        return $this->belongsTo(SaldoStokBarang::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function lokasiBarang(): BelongsTo
    {
        return $this->belongsTo(LokasiBarang::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function labelJenis(): string
    {
        return self::DAFTAR_JENIS[$this->jenis_mutasi] ?? str($this->jenis_mutasi)->headline()->toString();
    }

    public function labelKategori(): string
    {
        return self::DAFTAR_KATEGORI[$this->kategori_mutasi] ?? str($this->kategori_mutasi)->headline()->toString();
    }
}
