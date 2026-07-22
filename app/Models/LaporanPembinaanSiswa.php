<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaporanPembinaanSiswa extends Model
{
    protected $table = 'laporan_pembinaan_siswa';

    public const DAFTAR_TINGKAT = [
        'ringan' => 'Ringan',
        'sedang' => 'Sedang',
        'berat' => 'Berat',
    ];

    public const DAFTAR_STATUS = [
        'baru' => 'Baru',
        'diproses' => 'Diproses',
        'perlu_tindak_lanjut' => 'Perlu Tindak Lanjut',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];

    public const DAFTAR_JENIS_LAPORAN = [
        'pembinaan' => 'Pembinaan/Konseling',
        'pelanggaran' => 'Pelanggaran Berpoin',
    ];

    public const DAFTAR_STATUS_VERIFIKASI = [
        'tidak_perlu' => 'Tidak Memerlukan Verifikasi',
        'diajukan' => 'Diajukan',
        'pemeriksaan_bk' => 'Pemeriksaan BK',
        'perlu_klarifikasi' => 'Perlu Klarifikasi',
        'menunggu_persetujuan' => 'Menunggu Persetujuan',
        'disetujui_sebagian' => 'Disetujui Sebagian',
        'perlu_musyawarah' => 'Perlu Musyawarah',
        'disahkan' => 'Disahkan',
        'tidak_terbukti' => 'Tidak Terbukti',
        'dibatalkan' => 'Dibatalkan',
    ];

    protected $fillable = [
        'nomor_laporan',
        'jenis_laporan',
        'tanggal_kejadian',
        'waktu_kejadian',
        'tempat_kejadian',
        'siswa_id',
        'kategori_pembinaan_siswa_id',
        'tahun_pelajaran_id',
        'kelas_id',
        'anggota_kelas_id',
        'pelapor_pegawai_id',
        'wali_kelas_pegawai_id',
        'guru_wali_pegawai_id',
        'tingkat',
        'status',
        'status_verifikasi',
        'total_poin',
        'poin_ditetapkan_pada',
        'kronologi',
        'tindakan_awal',
        'catatan_rahasia',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'tanggal_kejadian' => 'date',
        'siswa_id' => 'integer',
        'kategori_pembinaan_siswa_id' => 'integer',
        'tahun_pelajaran_id' => 'integer',
        'kelas_id' => 'integer',
        'anggota_kelas_id' => 'integer',
        'pelapor_pegawai_id' => 'integer',
        'wali_kelas_pegawai_id' => 'integer',
        'guru_wali_pegawai_id' => 'integer',
        'total_poin' => 'integer',
        'poin_ditetapkan_pada' => 'datetime',
        'dibuat_oleh_pengguna_id' => 'integer',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function kategoriPembinaanSiswa(): BelongsTo
    {
        return $this->belongsTo(KategoriPembinaanSiswa::class);
    }

    public function tahunPelajaran(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function anggotaKelas(): BelongsTo
    {
        return $this->belongsTo(AnggotaKelas::class);
    }

    public function pelaporPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pelapor_pegawai_id');
    }

    public function waliKelasPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'wali_kelas_pegawai_id');
    }

    public function guruWaliPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'guru_wali_pegawai_id');
    }

    public function dibuatOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function tindakLanjutPembinaanSiswa(): HasMany
    {
        return $this->hasMany(TindakLanjutPembinaanSiswa::class);
    }

    public function butirPelanggaranLaporan(): HasMany
    {
        return $this->hasMany(ButirPelanggaranLaporan::class);
    }

    public function verifikasiBkPelanggaran(): HasMany
    {
        return $this->hasMany(VerifikasiBkPelanggaran::class);
    }

    public function persetujuanPelanggaran(): HasMany
    {
        return $this->hasMany(PersetujuanPelanggaran::class);
    }

    public function transaksiPoinSiswa(): HasMany
    {
        return $this->hasMany(TransaksiPoinSiswa::class);
    }

    public function labelTingkat(): string
    {
        return self::DAFTAR_TINGKAT[$this->tingkat] ?? str($this->tingkat)->headline()->toString();
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function labelJenisLaporan(): string
    {
        return self::DAFTAR_JENIS_LAPORAN[$this->jenis_laporan] ?? str($this->jenis_laporan)->headline()->toString();
    }

    public function labelStatusVerifikasi(): string
    {
        return self::DAFTAR_STATUS_VERIFIKASI[$this->status_verifikasi] ?? str($this->status_verifikasi)->headline()->toString();
    }

    public function waktuKejadianRingkas(): ?string
    {
        return filled($this->waktu_kejadian) ? substr((string) $this->waktu_kejadian, 0, 5) : null;
    }
}
