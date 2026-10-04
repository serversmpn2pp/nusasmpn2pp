<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DokumenHumas extends Model
{
    protected $table = 'dokumen_humas';

    public const KATEGORI = [
        'program_kerja' => 'Program kerja',
        'sop' => 'SOP Humas',
        'surat_masuk' => 'Surat masuk',
        'surat_keluar' => 'Surat keluar',
        'notulen' => 'Notulen rapat',
        'laporan_kegiatan' => 'Laporan kegiatan',
        'iasp' => 'Portofolio IASP',
        'hubungan_orang_tua' => 'Hubungan orang tua/wali',
        'komite' => 'Komite sekolah',
        'kemitraan' => 'Kemitraan dan kerja sama',
        'publikasi' => 'Publikasi dan promosi',
        'prestasi' => 'Prestasi sekolah',
        'kliping_media' => 'Kliping media',
        'aset_digital' => 'Aset digital',
        'alumni' => 'Alumni',
        'lainnya' => 'Dokumen lainnya',
    ];

    public const STATUS = [
        'aktif' => 'Aktif',
        'arsip' => 'Diarsipkan',
    ];

    protected $fillable = [
        'kategori',
        'judul',
        'nomor_dokumen',
        'deskripsi',
        'lokasi_file',
        'nama_file_asli',
        'tipe_file',
        'ukuran_file',
        'berlaku_mulai',
        'berlaku_sampai',
        'ingatkan_hari_sebelum',
        'status',
        'dibuat_oleh_pengguna_id',
        'diubah_oleh_pengguna_id',
    ];

    protected $casts = [
        'ukuran_file' => 'integer',
        'berlaku_mulai' => 'date',
        'berlaku_sampai' => 'date',
        'ingatkan_hari_sebelum' => 'integer',
    ];

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatDokumenHumas::class)->orderByDesc('versi');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diubah_oleh_pengguna_id');
    }

    public function statusMasaBerlaku(): string
    {
        if (! $this->berlaku_sampai) {
            return 'Tanpa batas berlaku';
        }

        if ($this->berlaku_sampai->isBefore(today())) {
            return 'Kedaluwarsa';
        }

        if ($this->berlaku_sampai->lte(today()->addDays(30))) {
            return 'Segera berakhir';
        }

        return 'Masih berlaku';
    }

    public function ukuranFileTampil(): string
    {
        return number_format($this->ukuran_file / 1024 / 1024, 2, ',', '.').' MB';
    }
}
