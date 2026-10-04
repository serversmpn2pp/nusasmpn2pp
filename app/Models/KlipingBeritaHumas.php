<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KlipingBeritaHumas extends Model
{
    protected $table = 'kliping_berita_humas';

    public const JENIS = ['online' => 'Media online', 'cetak' => 'Media cetak', 'televisi' => 'Televisi', 'radio' => 'Radio', 'media_sosial' => 'Media sosial luar', 'lainnya' => 'Media lainnya'];

    public const TOPIK = ['prestasi' => 'Prestasi sekolah', 'kegiatan' => 'Kegiatan sekolah', 'kemitraan' => 'Kemitraan', 'layanan' => 'Layanan sekolah', 'lainnya' => 'Pemberitaan lainnya'];

    public const STATUS = ['aktif' => 'Aktif', 'arsip' => 'Diarsipkan'];

    public const IZIN_LIHAT = ['kliping_berita_humas.lihat', 'kliping_berita_humas.kelola'];

    public const KOLOM = ['judul', 'nama_media', 'jenis', 'topik', 'tanggal_terbit', 'penulis', 'rujukan', 'tautan', 'ringkasan', 'catatan', 'status'];

    public const MIME_BUKTI = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    protected $fillable = ['token_pembuatan', 'tautan_hash', ...self::KOLOM];

    protected $hidden = ['token_pembuatan', 'tautan_hash'];

    protected $casts = ['tanggal_terbit' => 'date', 'versi' => 'integer'];

    public function berkas(): BelongsTo
    {
        return $this->belongsTo(RiwayatDokumenHumas::class, 'riwayat_dokumen_humas_id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatKlipingBeritaHumas::class)->latest('id');
    }

    public function snapshot(): array
    {
        return array_replace($this->only(self::KOLOM), ['tanggal_terbit' => $this->tanggal_terbit->format('d-m-Y')]);
    }
}
