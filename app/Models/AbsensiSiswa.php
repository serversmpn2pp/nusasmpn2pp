<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsensiSiswa extends Model
{
    public const ALASAN_HADIR_MANUAL = [
        'tidak_membawa_kartu' => 'Tidak membawa kartu pelajar',
        'kartu_hilang' => 'Kartu pelajar hilang',
    ];

    public const SANKSI_MANUAL_MENIT = 5;

    protected $table = 'absensi_siswa';

    protected $fillable = [
        'tanggal',
        'tahun_pelajaran_id',
        'kelas_id',
        'anggota_kelas_id',
        'siswa_id',
        'jam_masuk',
        'status_masuk',
        'menit_terlambat',
        'status_poin_keterlambatan',
        'poin_keterlambatan_terhitung',
        'poin_keterlambatan_diproses_pada',
        'jam_pulang',
        'status_pulang',
        'menit_pulang_cepat',
        'status_kehadiran',
        'sumber',
        'catatan',
        'alasan_hadir_manual',
        'sanksi_manual_menit',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'menit_terlambat' => 'integer',
        'poin_keterlambatan_terhitung' => 'integer',
        'poin_keterlambatan_diproses_pada' => 'datetime',
        'menit_pulang_cepat' => 'integer',
        'sanksi_manual_menit' => 'integer',
    ];

    public function memilikiSanksiManual(): bool
    {
        return $this->status_kehadiran === 'hadir' && (int) $this->sanksi_manual_menit > 0;
    }

    public static function normalisasiAlasanHadirManual(?string $alasan): string
    {
        return isset(self::ALASAN_HADIR_MANUAL[$alasan ?? '']) ? $alasan : 'tidak_membawa_kartu';
    }

    public function keteranganSanksiManual(): ?string
    {
        if (! $this->memilikiSanksiManual()) {
            return null;
        }
        $alasan = self::ALASAN_HADIR_MANUAL[$this->alasan_hadir_manual] ?? 'Kehadiran dicatat manual tanpa scan kartu';
        $poin = (int) $this->poin_keterlambatan_terhitung;
        $keteranganPoin = $poin > 0
            ? "Siswa mendapat {$poin} poin keterlambatan karena hadir dicatat manual tanpa scan kartu."
            : ($this->poin_keterlambatan_diproses_pada
                ? 'Poin keterlambatan tidak diterapkan atau telah dibatalkan sesuai ketentuan presensi.'
                : 'Poin keterlambatan mengikuti pengaturan presensi sekolah.');

        return $alasan.'. Sanksi keterlambatan minimal '.(int) $this->sanksi_manual_menit
            .' menit; keterlambatan tercatat '.(int) $this->menit_terlambat.' menit. '.$keteranganPoin
            .' Segera hubungi admin sekolah untuk mendapatkan atau mengganti kartu pelajar.';
    }

    public function catatanKehadiran(): ?string
    {
        return collect([$this->catatan, $this->keteranganSanksiManual()])->filter()->join("\n") ?: null;
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

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function logScanAbsensi(): HasMany
    {
        return $this->hasMany(LogScanAbsensi::class);
    }

    public function notifikasiAbsensiSiswa(): HasMany
    {
        return $this->hasMany(NotifikasiAbsensiSiswa::class);
    }

    public function laporanKeterlambatan(): HasMany
    {
        return $this->hasMany(LaporanPembinaanSiswa::class);
    }

    public function riwayatPerubahan(): HasMany
    {
        return $this->hasMany(RiwayatPerubahanAbsensiSiswa::class);
    }
}
