<?php

namespace App\Services\Ibadah;

use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\LogScanBerhalanganIbadah;
use App\Models\PengaturanBerhalanganIbadah;
use App\Models\Pengguna;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\PresensiBerhalanganIbadah;
use App\Models\PresensiKegiatanIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ProsesScanBerhalanganIbadah
{
    public function __construct(
        private AksesBerhalanganIbadah $akses,
        private NotifikasiPenggunaService $notifikasi,
    ) {}

    public function proses(
        JadwalKegiatanIbadah $jadwal,
        string $isiScan,
        Pengguna $petugas,
        ?CarbonInterface $waktuScan = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $waktuScan = $waktuScan ? Carbon::instance($waktuScan) : now();
        $tahunPelajaran = TahunPelajaran::query()->where('aktif', true)->orderByDesc('tanggal_mulai')->first();

        abort_unless($this->akses->dapatMemindai($petugas, $tahunPelajaran), 403);

        if (! $tahunPelajaran) {
            return $this->gagal($jadwal, $isiScan, $waktuScan, $petugas, 'tahun_pelajaran_tidak_ada', 'Tahun pelajaran aktif belum diatur.', null, null, $ipAddress, $userAgent);
        }

        $jadwal->loadMissing('kegiatanIbadah');

        if (! $jadwal->aktif || ! $jadwal->kegiatanIbadah?->aktif || (int) $jadwal->tahun_pelajaran_id !== (int) $tahunPelajaran->id) {
            return $this->gagal($jadwal, $isiScan, $waktuScan, $petugas, 'jadwal_tidak_aktif', 'Jadwal kegiatan tidak aktif untuk tahun pelajaran ini.', null, null, $ipAddress, $userAgent);
        }

        if ($jadwal->hari !== $this->hariDariTanggal($waktuScan)) {
            return $this->gagal($jadwal, $isiScan, $waktuScan, $petugas, 'hari_tidak_sesuai', 'Jadwal kegiatan ini tidak berlaku hari ini.', null, null, $ipAddress, $userAgent);
        }

        if (! $this->beradaDalamJendelaScan($jadwal, $waktuScan)) {
            return $this->gagal($jadwal, $isiScan, $waktuScan, $petugas, 'di_luar_jadwal', 'Scan hanya dapat dilakukan pukul '.$jadwal->rentangScan().'.', null, null, $ipAddress, $userAgent);
        }

        $nisn = $this->ambilNisn($isiScan);

        if (! $nisn) {
            return $this->gagal($jadwal, $isiScan, $waktuScan, $petugas, 'format_tidak_valid', 'QR tidak terbaca sebagai NISN. Arahkan kamera kembali ke QR kartu pelajar.', null, null, $ipAddress, $userAgent);
        }

        $siswa = Siswa::query()->where('nisn', $nisn)->first();

        if (! $siswa) {
            return $this->gagal($jadwal, $isiScan, $waktuScan, $petugas, 'siswa_tidak_ditemukan', 'NISN tidak ditemukan dalam data siswa.', $nisn, null, $ipAddress, $userAgent);
        }

        if (! $siswa->aktif) {
            return $this->gagal($jadwal, $isiScan, $waktuScan, $petugas, 'siswa_nonaktif', 'Siswa ditemukan, tetapi statusnya tidak aktif.', $nisn, $siswa, $ipAddress, $userAgent);
        }

        if ($siswa->jenis_kelamin !== 'P') {
            return $this->gagal($jadwal, $isiScan, $waktuScan, $petugas, 'bukan_siswi', 'Halaman ini khusus untuk presensi berhalangan siswi.', $nisn, $siswa, $ipAddress, $userAgent);
        }

        $anggotaKelas = AnggotaKelas::query()
            ->with('kelas:id,nama')
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('siswa_id', $siswa->id)
            ->where('status_keanggotaan', 'aktif')
            ->first();

        if (! $anggotaKelas) {
            return $this->gagal($jadwal, $isiScan, $waktuScan, $petugas, 'kelas_tidak_ditemukan', 'Siswi belum ditempatkan pada kelas aktif tahun pelajaran ini.', $nisn, $siswa, $ipAddress, $userAgent);
        }

        if (! $this->akses->dapatMemindaiKelas($petugas, $tahunPelajaran, $anggotaKelas->kelas_id)) {
            return $this->gagal($jadwal, $isiScan, $waktuScan, $petugas, 'di_luar_cakupan', 'Siswi berada di luar cakupan kelas pendamping.', $nisn, $siswa, $ipAddress, $userAgent);
        }

        return DB::transaction(function () use ($jadwal, $isiScan, $waktuScan, $petugas, $tahunPelajaran, $nisn, $siswa, $anggotaKelas, $ipAddress, $userAgent) {
            Siswa::query()->whereKey($siswa->id)->lockForUpdate()->first();

            if (PresensiKegiatanIbadah::query()
                ->where('kegiatan_ibadah_id', $jadwal->kegiatan_ibadah_id)
                ->where('siswa_id', $siswa->id)
                ->whereDate('tanggal', $waktuScan->toDateString())
                ->exists()) {
                return $this->gagal($jadwal, $isiScan, $waktuScan, $petugas, 'presensi_ibadah_sudah_ada', 'Siswi sudah tercatat melaksanakan kegiatan ibadah hari ini.', $nisn, $siswa, $ipAddress, $userAgent);
            }

            $pengaturan = PengaturanBerhalanganIbadah::query()
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->first();
            $batasHari = $pengaturan?->batas_hari_konfirmasi ?? 7;
            $periode = PeriodeBerhalanganIbadah::query()
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->where('siswa_id', $siswa->id)
                ->whereIn('status', [PeriodeBerhalanganIbadah::STATUS_AKTIF, PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI])
                ->latest('tanggal_mulai')
                ->first();
            $periodeBaru = ! $periode;

            if (! $periode) {
                $periode = PeriodeBerhalanganIbadah::create([
                    'tahun_pelajaran_id' => $tahunPelajaran->id,
                    'siswa_id' => $siswa->id,
                    'kelas_id' => $anggotaKelas->kelas_id,
                    'anggota_kelas_id' => $anggotaKelas->id,
                    'tanggal_mulai' => $waktuScan->toDateString(),
                    'status' => PeriodeBerhalanganIbadah::STATUS_AKTIF,
                    'batas_hari_konfirmasi' => $batasHari,
                    'dimulai_oleh_pengguna_id' => $petugas->id,
                ]);
            }

            $presensi = PresensiBerhalanganIbadah::query()
                ->where('kegiatan_ibadah_id', $jadwal->kegiatan_ibadah_id)
                ->where('siswa_id', $siswa->id)
                ->whereDate('tanggal', $waktuScan->toDateString())
                ->first();
            $baru = ! $presensi;

            if (! $presensi) {
                $presensi = PresensiBerhalanganIbadah::create([
                    'periode_berhalangan_ibadah_id' => $periode->id,
                    'jadwal_kegiatan_ibadah_id' => $jadwal->id,
                    'kegiatan_ibadah_id' => $jadwal->kegiatan_ibadah_id,
                    'tahun_pelajaran_id' => $tahunPelajaran->id,
                    'kelas_id' => $anggotaKelas->kelas_id,
                    'anggota_kelas_id' => $anggotaKelas->id,
                    'siswa_id' => $siswa->id,
                    'dipindai_oleh_pengguna_id' => $petugas->id,
                    'tanggal' => $waktuScan->toDateString(),
                    'waktu_scan' => $waktuScan->format('H:i:s'),
                    'sumber' => 'kamera',
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);
            }

            $hariKe = ((int) $periode->tanggal_mulai->copy()->startOfDay()->diffInDays($waktuScan->copy()->startOfDay())) + 1;
            $tanggalScan = $waktuScan->copy()->startOfDay();
            $melewatiBatas = $periode->konfirmasi_berikutnya_pada
                ? $tanggalScan->greaterThanOrEqualTo($periode->konfirmasi_berikutnya_pada->copy()->startOfDay())
                : $hariKe > $periode->batas_hari_konfirmasi;

            if (($pengaturan?->aktif ?? true) && $melewatiBatas && $periode->status === PeriodeBerhalanganIbadah::STATUS_AKTIF) {
                $periode->update([
                    'status' => PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI,
                    'perlu_konfirmasi_sejak' => $waktuScan->toDateString(),
                ]);

                $this->notifikasi->kirimKeBanyak(
                    $this->akses->penggunaPendampingUntukKelas($tahunPelajaran, $anggotaKelas->kelas_id),
                    'penting',
                    'Konfirmasi privat diperlukan',
                    'Ada catatan siswi kelas '.($anggotaKelas->kelas?->nama ?? '-').' yang memerlukan konfirmasi privat.',
                    route('konfirmasi-berhalangan-ibadah.show', $periode),
                    'konfirmasi-berhalangan-'.$periode->id.'-'.$waktuScan->toDateString(),
                    ['periode_berhalangan_ibadah_id' => $periode->id],
                );
            }

            $pesan = $baru
                ? ($periodeBaru ? 'Presensi berhalangan berhasil dicatat dan periode dimulai.' : 'Presensi berhalangan hari ini berhasil dicatat.')
                : 'Presensi berhalangan sudah tercatat pukul '.substr((string) $presensi->waktu_scan, 0, 5).'. Tidak perlu scan ulang.';

            $log = $this->catatLog(
                jadwal: $jadwal,
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                petugas: $petugas,
                status: $baru ? 'berhasil' : 'sudah_tercatat',
                pesan: $pesan,
                berhasil: true,
                nisn: $nisn,
                siswa: $siswa,
                periode: $periode,
                presensi: $presensi,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            return [
                'berhasil' => true,
                'baru' => $baru,
                'status' => $log->status_scan,
                'pesan' => $pesan,
                'siswa' => $siswa,
                'anggota_kelas' => $anggotaKelas,
                'periode' => $periode->fresh(),
                'presensi' => $presensi,
                'hari_ke' => $hariKe,
                'log' => $log,
            ];
        });
    }

    private function gagal(
        JadwalKegiatanIbadah $jadwal,
        string $isiScan,
        CarbonInterface $waktuScan,
        Pengguna $petugas,
        string $status,
        string $pesan,
        ?string $nisn,
        ?Siswa $siswa,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $log = $this->catatLog($jadwal, $isiScan, $waktuScan, $petugas, $status, $pesan, false, $nisn, $siswa, null, null, $ipAddress, $userAgent);

        return [
            'berhasil' => false,
            'baru' => false,
            'status' => $status,
            'pesan' => $pesan,
            'siswa' => $siswa,
            'anggota_kelas' => null,
            'periode' => null,
            'presensi' => null,
            'hari_ke' => null,
            'log' => $log,
        ];
    }

    private function catatLog(
        JadwalKegiatanIbadah $jadwal,
        string $isiScan,
        CarbonInterface $waktuScan,
        Pengguna $petugas,
        string $status,
        string $pesan,
        bool $berhasil,
        ?string $nisn = null,
        ?Siswa $siswa = null,
        ?PeriodeBerhalanganIbadah $periode = null,
        ?PresensiBerhalanganIbadah $presensi = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): LogScanBerhalanganIbadah {
        return LogScanBerhalanganIbadah::create([
            'presensi_berhalangan_ibadah_id' => $presensi?->id,
            'periode_berhalangan_ibadah_id' => $periode?->id,
            'jadwal_kegiatan_ibadah_id' => $jadwal->id,
            'kegiatan_ibadah_id' => $jadwal->kegiatan_ibadah_id,
            'siswa_id' => $siswa?->id,
            'dipindai_oleh_pengguna_id' => $petugas->id,
            'isi_scan' => trim($isiScan),
            'nisn' => $nisn,
            'waktu_scan' => $waktuScan,
            'tanggal' => $waktuScan->toDateString(),
            'berhasil' => $berhasil,
            'status_scan' => $status,
            'pesan' => $pesan,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    private function ambilNisn(string $isiScan): ?string
    {
        $isiScan = trim(preg_replace('/\s+/', '', $isiScan) ?? '');

        return preg_match('/^[0-9]{4,30}$/', $isiScan) ? $isiScan : null;
    }

    private function beradaDalamJendelaScan(JadwalKegiatanIbadah $jadwal, CarbonInterface $waktu): bool
    {
        $menit = $this->menit($waktu->format('H:i'));

        return $menit >= $this->menit($jadwal->formatJam($jadwal->jam_scan_mulai))
            && $menit <= $this->menit($jadwal->formatJam($jadwal->jam_scan_selesai));
    }

    private function hariDariTanggal(CarbonInterface $waktu): string
    {
        return array_keys(JadwalKegiatanIbadah::DAFTAR_HARI)[$waktu->dayOfWeekIso - 1] ?? 'minggu';
    }

    private function menit(string $jam): int
    {
        [$jamAngka, $menit] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($jamAngka * 60) + $menit;
    }
}
