<?php

namespace App\Models;

use App\Services\Humas\KlasifikasiDokumenHumasService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DokumenHumas extends Model
{
    protected $table = 'dokumen_humas';

    public const URUSAN = ['kedinasan' => 'Dokumen Kedinasan Humas', 'komite' => 'Dokumen Komite Sekolah',
        'orang_tua' => 'Hubungan Orang Tua/Wali', 'kemitraan' => 'Kemitraan & Kerja Sama',
        'publikasi' => 'Publikasi & Promosi Sekolah', 'alumni' => 'Database & Portofolio Alumni', 'pengaduan' => 'Aspirasi & Pengaduan'];

    public const JENIS_DOKUMEN = ['program_kerja' => 'Program kerja', 'sop' => 'SOP', 'surat_masuk' => 'Surat masuk',
        'surat_keluar' => 'Surat keluar', 'surat_tugas' => 'Surat tugas', 'undangan' => 'Undangan', 'pemberitahuan' => 'Pemberitahuan',
        'sk' => 'Surat keputusan', 'notulen' => 'Notulen', 'daftar_hadir' => 'Daftar hadir', 'laporan_kegiatan' => 'Laporan kegiatan',
        'mou' => 'MoU / perjanjian kerja sama', 'proposal' => 'Proposal', 'berita_acara' => 'Berita acara', 'kuitansi' => 'Kuitansi',
        'bukti_bantuan' => 'Bukti bantuan / sumbangan', 'dokumentasi' => 'Dokumentasi kegiatan', 'materi_publikasi' => 'Materi publikasi',
        'kliping' => 'Kliping berita', 'portofolio_akreditasi' => 'Portofolio akreditasi', 'instrumen' => 'Instrumen / formulir', 'lainnya' => 'Dokumen lainnya'];

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
        'urusan',
        'jenis_dokumen',
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
        'versi_metadata' => 'integer',
        'klasifikasi_pemeriksa_id' => 'integer',
        'klasifikasi_diperiksa_pada' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DokumenHumas $dokumen) {
            $lama = KlasifikasiDokumenHumasService::dariKategori($dokumen->kategori);
            $dokumen->urusan ??= $lama['urusan'];
            $dokumen->jenis_dokumen ??= $lama['jenis_dokumen'];
            $dokumen->sumber_klasifikasi ??= 'kategori_lama';
        });
    }

    public function perluDitinjau(): bool
    {
        return ! $this->urusan || ! $this->jenis_dokumen || ! $this->klasifikasi_diperiksa_pada;
    }

    public function agenda(): BelongsToMany
    {
        return $this->belongsToMany(AgendaHumas::class, 'agenda_humas_dokumen')->withTimestamps();
    }

    public function program(): BelongsToMany
    {
        return $this->belongsToMany(ProgramKerjaHumas::class, 'dokumen_humas_program', 'dokumen_humas_id', 'target_id')->withTimestamps();
    }

    public function programKomite(): BelongsToMany
    {
        return $this->belongsToMany(ProgramKomiteHumas::class, 'dokumen_humas_program_komite', 'dokumen_humas_id', 'target_id')->withTimestamps();
    }

    public function surat(): BelongsToMany
    {
        return $this->belongsToMany(SuratTu::class, 'dokumen_humas_surat', 'dokumen_humas_id', 'target_id')->withTimestamps();
    }

    public function riwayatKlasifikasi(): HasMany
    {
        return $this->hasMany(RiwayatKlasifikasiDokumenHumas::class)->latest('id');
    }

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
