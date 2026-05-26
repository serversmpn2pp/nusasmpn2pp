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

    protected $fillable = [
        'nomor_laporan',
        'tanggal_kejadian',
        'waktu_kejadian',
        'tempat_kejadian',
        'siswa_id',
        'kategori_pembinaan_siswa_id',
        'tahun_pelajaran_id',
        'kelas_id',
        'anggota_kelas_id',
        'pelapor_pegawai_id',
        'tingkat',
        'status',
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

    public function dibuatOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function tindakLanjutPembinaanSiswa(): HasMany
    {
        return $this->hasMany(TindakLanjutPembinaanSiswa::class);
    }

    public function labelTingkat(): string
    {
        return self::DAFTAR_TINGKAT[$this->tingkat] ?? str($this->tingkat)->headline()->toString();
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function waktuKejadianRingkas(): ?string
    {
        return filled($this->waktu_kejadian) ? substr((string) $this->waktu_kejadian, 0, 5) : null;
    }
}
