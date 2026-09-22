<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PesertaUjianCbt extends Model
{
    protected $table = 'peserta_ujian_cbt';

    protected static function booted(): void
    {
        static::saving(function (self $peserta) {
            if (($peserta->isDirty('waktu_mulai') && $peserta->waktu_mulai !== null)
                || ($peserta->isDirty('status') && in_array($peserta->status, ['sedang_mengerjakan', 'selesai'], true))) {
                // The activity row also serializes this lock with administrator changes.
                KegiatanUjianCbt::query()->whereIn('id', JadwalUjianCbt::query()
                    ->where('ujian_cbt_id', $peserta->ujian_cbt_id)->select('kegiatan_ujian_cbt_id'))
                    ->whereNull('penilaian_pgk_dikunci_pada')
                    ->update(['penilaian_pgk_dikunci_pada' => now()]);
            }
        });
    }

    public const DAFTAR_STATUS = [
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
        'sedang_mengerjakan' => 'Sedang mengerjakan',
        'selesai' => 'Selesai',
        'terblokir' => 'Terblokir',
    ];

    public const DAFTAR_STATUS_KEHADIRAN = [
        'belum_absen' => 'Belum hadir',
        'hadir' => 'Hadir',
        'terlambat' => 'Terlambat',
        'sakit' => 'Sakit',
        'izin' => 'Izin',
        'alfa' => 'Alfa',
    ];

    public const DAFTAR_STATUS_PELAKSANAAN = [
        'belum_hadir' => 'Belum hadir',
        'hadir_belum_mulai' => 'Hadir, belum mulai',
        'tidak_hadir' => 'Tidak hadir',
        'sedang_mengerjakan' => 'Sedang mengerjakan',
        'selesai' => 'Selesai',
        'nonaktif' => 'Nonaktif',
        'terblokir' => 'Terblokir',
    ];

    public const DAFTAR_STATUS_SUSULAN = [
        'dijadwalkan' => 'Dijadwalkan',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];

    protected $fillable = [
        'ujian_cbt_id',
        'sesi_ujian_cbt_id',
        'kelas_ujian_cbt_id',
        'ruang_ujian_cbt_id',
        'nomor_meja',
        'kode_meja',
        'anggota_kelas_id',
        'nomor_peserta',
        'status',
        'status_kehadiran_ujian',
        'status_susulan',
        'kelompok_susulan',
        'susulan_mulai',
        'susulan_selesai',
        'token_susulan',
        'ruang_susulan',
        'ruang_susulan_kegiatan_ujian_cbt_id',
        'pengawas_susulan_pegawai_id',
        'catatan_susulan',
        'susulan_ditetapkan_pada',
        'susulan_ditetapkan_oleh_pengguna_id',
        'absen_ujian_pada',
        'absen_ujian_oleh_pengguna_id',
        'waktu_mulai',
        'waktu_selesai',
        'menit_tersisa',
        'jumlah_pindah_aplikasi',
        'durasi_di_luar_aplikasi_detik',
        'heartbeat_terakhir_pada',
        'ditahan_mode_aman_pada',
        'dibuka_mode_aman_pada',
        'dibuka_mode_aman_oleh_pengguna_id',
        'ip_terakhir',
        'perangkat_terakhir',
        'user_agent_terakhir',
        'catatan',
        'catatan_kehadiran_ujian',
        'nilai_siswa_id',
        'nilai_diterapkan_pada',
        'nilai_diterapkan_oleh_pengguna_id',
        'dibuat_oleh_pengguna_id',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'menit_tersisa' => 'integer',
        'jumlah_pindah_aplikasi' => 'integer',
        'durasi_di_luar_aplikasi_detik' => 'integer',
        'heartbeat_terakhir_pada' => 'datetime',
        'ditahan_mode_aman_pada' => 'datetime',
        'dibuka_mode_aman_pada' => 'datetime',
        'nomor_meja' => 'integer',
        'absen_ujian_pada' => 'datetime',
        'susulan_mulai' => 'datetime',
        'susulan_selesai' => 'datetime',
        'susulan_ditetapkan_pada' => 'datetime',
        'nilai_diterapkan_pada' => 'datetime',
    ];

    public function ujianCbt(): BelongsTo
    {
        return $this->belongsTo(UjianCbt::class);
    }

    public function sesiUjianCbt(): BelongsTo
    {
        return $this->belongsTo(SesiUjianCbt::class);
    }

    public function kelasUjianCbt(): BelongsTo
    {
        return $this->belongsTo(KelasUjianCbt::class);
    }

    public function ruangUjianCbt(): BelongsTo
    {
        return $this->belongsTo(RuangUjianCbt::class);
    }

    public function anggotaKelas(): BelongsTo
    {
        return $this->belongsTo(AnggotaKelas::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh_pengguna_id');
    }

    public function nilaiSiswa(): BelongsTo
    {
        return $this->belongsTo(NilaiSiswa::class);
    }

    public function nilaiDiterapkanOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'nilai_diterapkan_oleh_pengguna_id');
    }

    public function absenUjianOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'absen_ujian_oleh_pengguna_id');
    }

    public function pengawasSusulan(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pengawas_susulan_pegawai_id');
    }

    public function ruangSusulanKegiatanUjianCbt(): BelongsTo
    {
        return $this->belongsTo(RuangKegiatanUjianCbt::class, 'ruang_susulan_kegiatan_ujian_cbt_id');
    }

    public function susulanDitetapkanOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'susulan_ditetapkan_oleh_pengguna_id');
    }

    public function jawabanPesertaUjianCbt(): HasMany
    {
        return $this->hasMany(JawabanPesertaUjianCbt::class);
    }

    public function aktivitasKeamananUjianCbt(): HasMany
    {
        return $this->hasMany(AktivitasKeamananUjianCbt::class);
    }

    public function dibukaModeAmanOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuka_mode_aman_oleh_pengguna_id');
    }

    public function labelStatus(): string
    {
        return self::DAFTAR_STATUS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function labelStatusKehadiranUjian(): string
    {
        return self::DAFTAR_STATUS_KEHADIRAN[$this->status_kehadiran_ujian] ?? str($this->status_kehadiran_ujian)->headline()->toString();
    }

    public function susulanDijadwalkan(): bool
    {
        return $this->status_susulan === 'dijadwalkan'
            && $this->susulan_mulai
            && $this->susulan_selesai;
    }

    public function labelStatusSusulan(): string
    {
        return self::DAFTAR_STATUS_SUSULAN[$this->status_susulan] ?? 'Belum dijadwalkan';
    }

    public function tokenUjianAktif(): ?string
    {
        return $this->susulanDijadwalkan()
            ? $this->token_susulan
            : $this->ujianCbt?->token;
    }

    public function statusPelaksanaan(): string
    {
        if ($this->status !== 'aktif') {
            return $this->status;
        }

        if (in_array($this->status_kehadiran_ujian, ['hadir', 'terlambat'], true)) {
            return 'hadir_belum_mulai';
        }

        if (in_array($this->status_kehadiran_ujian, ['sakit', 'izin', 'alfa'], true)) {
            return 'tidak_hadir';
        }

        return 'belum_hadir';
    }

    public function labelStatusPelaksanaan(): string
    {
        $status = $this->statusPelaksanaan();

        return self::DAFTAR_STATUS_PELAKSANAAN[$status] ?? str($status)->headline()->toString();
    }
}
