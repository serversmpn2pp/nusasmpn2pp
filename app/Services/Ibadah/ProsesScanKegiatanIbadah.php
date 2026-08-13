<?php

namespace App\Services\Ibadah;

use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\LogScanKegiatanIbadah;
use App\Models\Pengguna;
use App\Models\PresensiKegiatanIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ProsesScanKegiatanIbadah
{
    public function __construct(private AksesScanKegiatanIbadah $aksesScan)
    {
    }

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

        abort_unless($this->aksesScan->dapatMemindai($petugas, $tahunPelajaran, $waktuScan), 403);

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
            return $this->gagal(
                $jadwal,
                $isiScan,
                $waktuScan,
                $petugas,
                'di_luar_jadwal',
                'Scan hanya dapat dilakukan pukul '.$jadwal->rentangScan().'.',
                null,
                null,
                $ipAddress,
                $userAgent,
            );
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

        $anggotaKelas = AnggotaKelas::query()
            ->with('kelas:id,nama')
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('siswa_id', $siswa->id)
            ->where('status_keanggotaan', 'aktif')
            ->first();

        if (! $anggotaKelas) {
            return $this->gagal($jadwal, $isiScan, $waktuScan, $petugas, 'kelas_tidak_ditemukan', 'Siswa belum ditempatkan pada kelas aktif tahun pelajaran ini.', $nisn, $siswa, $ipAddress, $userAgent);
        }

        return DB::transaction(function () use ($jadwal, $isiScan, $waktuScan, $petugas, $tahunPelajaran, $nisn, $siswa, $anggotaKelas, $ipAddress, $userAgent) {
            Siswa::query()->whereKey($siswa->id)->lockForUpdate()->first();
            $presensi = PresensiKegiatanIbadah::query()
                ->where('kegiatan_ibadah_id', $jadwal->kegiatan_ibadah_id)
                ->where('siswa_id', $siswa->id)
                ->whereDate('tanggal', $waktuScan->toDateString())
                ->first();
            $baru = ! $presensi;

            if (! $presensi) {
                $presensi = PresensiKegiatanIbadah::create([
                    'kegiatan_ibadah_id' => $jadwal->kegiatan_ibadah_id,
                    'siswa_id' => $siswa->id,
                    'tanggal' => $waktuScan->toDateString(),
                    'jadwal_kegiatan_ibadah_id' => $jadwal->id,
                    'tahun_pelajaran_id' => $tahunPelajaran->id,
                    'kelas_id' => $anggotaKelas->kelas_id,
                    'anggota_kelas_id' => $anggotaKelas->id,
                    'dipindai_oleh_pengguna_id' => $petugas->id,
                    'waktu_scan' => $waktuScan->format('H:i:s'),
                    'sumber' => 'kamera',
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);
            }
            $pesan = $baru
                ? 'Presensi ibadah berhasil dicatat.'
                : 'Presensi sudah tercatat pukul '.substr((string) $presensi->waktu_scan, 0, 5).'. Tidak perlu scan ulang.';

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
                'presensi' => $presensi,
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
        $log = $this->catatLog(
            jadwal: $jadwal,
            isiScan: $isiScan,
            waktuScan: $waktuScan,
            petugas: $petugas,
            status: $status,
            pesan: $pesan,
            berhasil: false,
            nisn: $nisn,
            siswa: $siswa,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return [
            'berhasil' => false,
            'baru' => false,
            'status' => $status,
            'pesan' => $pesan,
            'siswa' => $siswa,
            'anggota_kelas' => null,
            'presensi' => null,
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
        ?PresensiKegiatanIbadah $presensi = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): LogScanKegiatanIbadah {
        return LogScanKegiatanIbadah::create([
            'presensi_kegiatan_ibadah_id' => $presensi?->id,
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
