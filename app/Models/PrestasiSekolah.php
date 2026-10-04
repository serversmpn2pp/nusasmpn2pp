<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrestasiSekolah extends Model
{
    protected $table = 'prestasi_sekolah';

    public const KATEGORI = ['akademik' => 'Akademik', 'nonakademik' => 'Nonakademik', 'kelembagaan' => 'Kelembagaan'];

    public const TINGKAT = ['sekolah' => 'Sekolah', 'kecamatan' => 'Kecamatan', 'kota_kabupaten' => 'Kota / kabupaten', 'provinsi' => 'Provinsi', 'nasional' => 'Nasional', 'internasional' => 'Internasional'];

    public const PEROLEHAN = ['juara_1' => 'Juara 1', 'juara_2' => 'Juara 2', 'juara_3' => 'Juara 3', 'juara_umum' => 'Juara umum', 'harapan' => 'Juara harapan', 'medali_emas' => 'Medali emas', 'medali_perak' => 'Medali perak', 'medali_perunggu' => 'Medali perunggu', 'finalis' => 'Finalis', 'penghargaan' => 'Penghargaan lainnya'];

    public const PENERIMA = ['siswa' => 'Siswa', 'pegawai' => 'Guru / pegawai', 'sekolah' => 'Sekolah'];

    public const BENTUK = ['individu' => 'Individu', 'tim' => 'Tim', 'sekolah' => 'Sekolah'];

    public const STATUS = ['draf' => 'Draf', 'terverifikasi' => 'Terverifikasi', 'arsip' => 'Diarsipkan'];

    public const KOLOM = ['tahun_pelajaran_id', 'nama_kegiatan', 'cabang', 'kategori', 'tingkat', 'perolehan', 'capaian', 'tanggal_prestasi', 'penyelenggara', 'tempat', 'pembina', 'penerima', 'bentuk', 'nama_tim', 'catatan', 'tautan', 'status'];

    public const MIME_BUKTI = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    protected $fillable = ['token_pembuatan', ...self::KOLOM];

    protected $hidden = ['token_pembuatan', 'identitas_hash'];

    protected $casts = ['tanggal_prestasi' => 'date', 'diverifikasi_pada' => 'datetime', 'versi' => 'integer'];

    public function peserta(): HasMany
    {
        return $this->hasMany(PesertaPrestasiSekolah::class)->orderBy('id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatPrestasiSekolah::class)->latest('id');
    }

    public function berkas(): BelongsTo
    {
        return $this->belongsTo(RiwayatDokumenHumas::class, 'riwayat_dokumen_humas_id');
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function snapshot(): array
    {
        return array_replace($this->only(self::KOLOM), ['tanggal_prestasi' => $this->tanggal_prestasi->format('Y-m-d'), 'tahun_pelajaran' => $this->tahunPelajaran?->nama,
            'peserta' => $this->peserta->map(fn ($p) => $p->only(['siswa_id', 'pegawai_id', 'nama', 'kelas']))->all(), 'diverifikasi_pada' => $this->diverifikasi_pada?->toIso8601String()]);
    }
}
