<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengaduanHumas extends Model
{
    protected $table = 'pengaduan_humas';

    public const JENIS = ['aspirasi' => 'Aspirasi / saran', 'pengaduan' => 'Pengaduan'];

    public const KATEGORI = ['layanan' => 'Layanan sekolah', 'pembelajaran' => 'Pembelajaran', 'kesiswaan' => 'Kesiswaan', 'sarpras' => 'Sarana prasarana', 'lainnya' => 'Lainnya'];

    public const KANAL = ['tatap_muka' => 'Tatap muka', 'telepon' => 'Telepon', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'surat' => 'Surat', 'lainnya' => 'Lainnya', 'akun_orang_tua' => 'Akun orang tua NUSA'];

    public const PRIORITAS = ['normal' => 'Normal', 'tinggi' => 'Tinggi'];

    public const STATUS = ['baru' => 'Baru', 'ditugaskan' => 'Ditugaskan', 'diproses' => 'Diproses', 'menunggu' => 'Menunggu informasi', 'verifikasi' => 'Menunggu verifikasi', 'selesai' => 'Selesai', 'ditutup' => 'Ditutup dengan alasan'];

    public const AKTIF = ['baru', 'ditugaskan', 'diproses', 'menunggu', 'verifikasi'];

    public const STATUS_ORANG_TUA = ['baru' => 'Laporan diterima', 'ditugaskan' => 'Dalam penanganan', 'diproses' => 'Dalam penanganan', 'menunggu' => 'Menunggu informasi tambahan', 'verifikasi' => 'Pemeriksaan akhir', 'selesai' => 'Selesai', 'ditutup' => 'Ditutup'];

    public const KOLOM = ['judul', 'jenis', 'kategori', 'kanal', 'tanggal_diterima', 'isi', 'anonim', 'nama_pelapor', 'kontak_pelapor', 'prioritas'];

    public const IZIN = ['pengaduan_humas.lihat', 'pengaduan_humas.kelola', 'pengaduan_humas.tangani'];

    protected $fillable = ['token_pembuatan', ...self::KOLOM];

    protected $hidden = ['token_pembuatan', 'nama_pelapor', 'kontak_pelapor', 'pelapor_pengguna_id', 'dibuat_oleh_pengguna_id'];

    protected $casts = ['anonim' => 'boolean', 'rahasiakan_identitas' => 'boolean', 'nama_pelapor' => 'encrypted', 'kontak_pelapor' => 'encrypted', 'tanggal_diterima' => 'date', 'batas_tanggal' => 'date', 'diselesaikan_pada' => 'datetime', 'versi' => 'integer'];

    public function scopeUntukPengguna(Builder $query, Pengguna $pengguna): Builder
    {
        if (! $pengguna->aktif || $pengguna->akunOrangTua()) {
            return $query->whereRaw('1 = 0');
        }
        if ($pengguna->memilikiIzin(['pengaduan_humas.lihat', 'pengaduan_humas.kelola'])) {
            return $query;
        }

        return $pengguna->memilikiIzin('pengaduan_humas.tangani') && $pengguna->akunPegawai()
            ? $query->where('petugas_pengguna_id', $pengguna->id)->whereHas('petugas.pegawai', fn ($q) => $q->where('aktif', true)) : $query->whereRaw('1 = 0');
    }

    public function getNomorAttribute(): string
    {
        return 'HM-'.$this->created_at->format('Y').'-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function aktif(): bool
    {
        return in_array($this->status, self::AKTIF, true);
    }

    public function terlambat(): bool
    {
        return $this->aktif() && $this->batas_tanggal && $this->batas_tanggal->isBefore(today());
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'petugas_pengguna_id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatPengaduanHumas::class)->latest('id');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(LampiranPengaduanHumas::class);
    }

    public function pesan(): HasMany
    {
        return $this->hasMany(PesanPengaduanHumas::class);
    }

    public function dariOrangTua(): bool
    {
        return $this->kanal === 'akun_orang_tua';
    }

    public function snapshot(): array
    {
        return $this->only(['judul', 'jenis', 'kategori', 'kanal', 'isi', 'prioritas', 'status', 'petugas_pengguna_id', 'hasil_penanganan'])
            + ['tanggal_diterima' => $this->tanggal_diterima->format('d-m-Y'), 'batas_tanggal' => $this->batas_tanggal?->format('d-m-Y')];
    }
}
