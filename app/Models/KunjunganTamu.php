<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KunjunganTamu extends Model
{
    protected $table = 'kunjungan_tamu';

    public const KATEGORI = ['dinas' => 'Dinas', 'penawaran' => 'Penawaran produk / sales', 'pengaduan' => 'Pengaduan',
        'kunjungan_kerja' => 'Kunjungan kerja', 'orang_tua' => 'Orang tua / wali', 'alumni' => 'Alumni', 'lainnya' => 'Lainnya'];

    public const STATUS = ['berkunjung' => 'Masih berkunjung', 'selesai' => 'Sudah pulang', 'dibatalkan' => 'Dibatalkan'];

    protected $fillable = ['nama_tamu', 'instansi', 'alamat_instansi', 'jabatan', 'nomor_wa', 'kategori', 'keperluan',
        'pegawai_tujuan_id', 'nama_tujuan', 'waktu_datang', 'waktu_pulang', 'status', 'catatan', 'alasan_pembatalan',
        'dicatat_oleh_pengguna_id', 'diubah_oleh_pengguna_id', 'versi'];

    protected $hidden = ['token_pencatatan'];

    protected $casts = ['waktu_datang' => 'datetime', 'waktu_pulang' => 'datetime', 'versi' => 'integer'];

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dicatat_oleh_pengguna_id');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(LampiranKunjunganTamu::class)->orderBy('id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatKunjunganTamu::class)->latest('id');
    }

    public function durasiMenit(): ?int
    {
        return $this->status === 'dibatalkan' ? null : max(0, (int) $this->waktu_datang->diffInMinutes($this->waktu_pulang ?? now()));
    }

    public function dataAudit(): array
    {
        return array_intersect_key($this->attributesToArray(), array_flip($this->fillable));
    }
}
