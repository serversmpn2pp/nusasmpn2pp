<?php

namespace App\Models;

use App\Services\Absensi\PenugasanPresensiSiswaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsensiSiswa extends Model
{
    protected static function booted(): void
    {
        static::saving(fn (self $absensi) => app(PenugasanPresensiSiswaService::class)->lindungi($absensi));
    }

    public const SCAN_BERMASALAH = 'scan_bermasalah';

    public const GANGGUAN_SCAN_TERVERIFIKASI = 'gangguan_scan_terverifikasi';

    public const HASIL_VERIFIKASI_SCAN = [
        'scan_ditemukan' => 'Scan masuk ditemukan',
        'gangguan_terkonfirmasi' => 'Gangguan scan terkonfirmasi',
        'tidak_membawa_kartu' => 'Terkonfirmasi tidak membawa kartu',
        'kartu_hilang' => 'Terkonfirmasi kartu hilang',
    ];

    public const ALASAN_HADIR_MANUAL = [
        'tidak_membawa_kartu' => 'Tidak membawa kartu pelajar',
        'kartu_hilang' => 'Kartu pelajar hilang',
        self::SCAN_BERMASALAH => 'Scan bermasalah',
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
        'aturan_poin_hadir_manual',
        'penugasan_presensi_id',
        'sebelum_penugasan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'menit_terlambat' => 'integer',
        'poin_keterlambatan_terhitung' => 'integer',
        'poin_keterlambatan_diproses_pada' => 'datetime',
        'menit_pulang_cepat' => 'integer',
        'sanksi_manual_menit' => 'integer',
        'aturan_poin_hadir_manual' => 'array',
        'sebelum_penugasan' => 'array',
    ];

    public function memilikiSanksiManual(): bool
    {
        return $this->status_kehadiran === 'hadir' && ! $this->menungguVerifikasiScan() && (int) $this->sanksi_manual_menit > 0;
    }

    public function menungguVerifikasiScan(): bool
    {
        return $this->status_kehadiran === 'hadir' && $this->alasan_hadir_manual === self::SCAN_BERMASALAH;
    }

    public function dapatDicatatPiket(): bool
    {
        return ! $this->menungguVerifikasiScan() && ! $this->penugasan_presensi_id
            && ($this->sumber === 'guru_piket' || $this->hanyaTercatatPulang());
    }

    public function hanyaTercatatPulang(): bool
    {
        return $this->sumber === 'scan' && ! $this->jam_masuk && (bool) $this->jam_pulang;
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
            ? "Siswa mendapat {$poin} poin pelanggaran karena hadir dicatat manual tanpa scan kartu."
            : ($this->poin_keterlambatan_diproses_pada
                ? 'Poin keterlambatan tidak diterapkan atau telah dibatalkan sesuai ketentuan presensi.'
                : 'Poin keterlambatan mengikuti pengaturan presensi sekolah.');

        $jenis = $this->aturan_poin_hadir_manual['nama'] ?? null;

        return $alasan.'. '.($jenis ? 'Pelanggaran: '.$jenis.'. ' : '').'Sanksi keterlambatan minimal '.(int) $this->sanksi_manual_menit
            .' menit; keterlambatan tercatat '.(int) $this->menit_terlambat.' menit. '.$keteranganPoin
            .' Segera hubungi admin sekolah untuk mendapatkan atau mengganti kartu pelajar.';
    }

    public function catatanKehadiran(): ?string
    {
        $verifikasi = match ($this->alasan_hadir_manual) {
            self::SCAN_BERMASALAH => 'Scan bermasalah. Perlu verifikasi; poin ditahan.',
            self::GANGGUAN_SCAN_TERVERIFIKASI => 'Gangguan scan terkonfirmasi. Tidak ada sanksi kartu.',
            default => null,
        };

        return collect([$this->catatan, $verifikasi, $this->keteranganSanksiManual(), $this->keteranganPenugasan()])->filter()->join("\n") ?: null;
    }

    public function penugasanPresensi(): BelongsTo
    {
        return $this->belongsTo(PengecualianPresensiSiswa::class, 'penugasan_presensi_id');
    }

    public function keteranganPenugasan(): ?string
    {
        return $this->penugasan_presensi_id && $this->penugasanPresensi?->aktif
            ? $this->penugasanPresensi->label().'. Dikecualikan dari sanksi keterlambatan dan kewajiban scan kartu.' : null;
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
