<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeminjamanBarang extends Model
{
    protected $table = 'peminjaman_barang';

    public const DAFTAR_JENIS_PEMINJAM = [
        'siswa' => 'Siswa',
        'pegawai' => 'Pegawai',
    ];

    public const DAFTAR_STATUS = [
        'dipinjam' => 'Dipinjam',
        'sebagian_dikembalikan' => 'Sebagian dikembalikan',
        'selesai' => 'Selesai',
    ];

    protected $fillable = [
        'nomor_peminjaman',
        'jenis_peminjam',
        'siswa_id',
        'pegawai_id',
        'cara_input_peminjam',
        'tanggal_peminjaman',
        'rencana_kembali',
        'status',
        'catatan',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal_peminjaman' => 'date',
        'rencana_kembali' => 'date',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function detailPeminjamanBarang(): HasMany
    {
        return $this->hasMany(DetailPeminjamanBarang::class);
    }

    public function pengembalianBarang(): HasMany
    {
        return $this->hasMany(PengembalianBarang::class);
    }

    public function namaPeminjam(): string
    {
        return $this->jenis_peminjam === 'siswa'
            ? ($this->siswa?->nama_lengkap ?? 'Siswa tidak ditemukan')
            : ($this->pegawai?->nama_lengkap ?? 'Pegawai tidak ditemukan');
    }

    public function identitasPeminjam(): string
    {
        return $this->jenis_peminjam === 'siswa'
            ? 'NISN ' . ($this->siswa?->nisn ?: '-')
            : 'NIP ' . ($this->pegawai?->nip ?: '-');
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function masihAktif(): bool
    {
        return in_array($this->status, ['dipinjam', 'sebagian_dikembalikan'], true);
    }

    public function terlambat(?CarbonInterface $tanggalAcuan = null): bool
    {
        if (! $this->masihAktif() || ! $this->rencana_kembali) {
            return false;
        }

        return $this->rencana_kembali->startOfDay()->lt(($tanggalAcuan ?? now())->startOfDay());
    }

    public function jumlahHariTerlambat(?CarbonInterface $tanggalAcuan = null): int
    {
        if (! $this->terlambat($tanggalAcuan)) {
            return 0;
        }

        return (int) $this->rencana_kembali
            ->startOfDay()
            ->diffInDays(($tanggalAcuan ?? now())->startOfDay());
    }

    public function labelPemantauan(?CarbonInterface $tanggalAcuan = null): string
    {
        if ($this->status === 'selesai') {
            return 'Selesai';
        }

        if (! $this->rencana_kembali) {
            return 'Belum ada rencana kembali';
        }

        if ($this->terlambat($tanggalAcuan)) {
            return 'Terlambat ' . $this->jumlahHariTerlambat($tanggalAcuan) . ' hari';
        }

        $selisihHari = (int) ($tanggalAcuan ?? now())
            ->startOfDay()
            ->diffInDays($this->rencana_kembali->startOfDay(), false);

        return $selisihHari === 0
            ? 'Jatuh tempo hari ini'
            : 'Jatuh tempo ' . $selisihHari . ' hari lagi';
    }
}
