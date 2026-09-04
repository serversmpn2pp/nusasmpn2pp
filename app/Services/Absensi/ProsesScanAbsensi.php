<?php

namespace App\Services\Absensi;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\LogScanAbsensi;
use App\Models\PengaturanAbsensi;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Notifikasi\NotifikasiAbsensiSiswaService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ProsesScanAbsensi
{
    private const JEDA_DUPLIKAT_DETIK = 10;

    private const HARI = [
        1 => 'senin',
        2 => 'selasa',
        3 => 'rabu',
        4 => 'kamis',
        5 => 'jumat',
        6 => 'sabtu',
        7 => 'minggu',
    ];

    public function __construct(private NotifikasiAbsensiSiswaService $notifikasiAbsensiSiswaService) {}

    public function proses(
        string $isiScan,
        ?CarbonInterface $waktuScan = null,
        ?string $jenisScanDiminta = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $waktuScan = $waktuScan ? Carbon::instance($waktuScan) : now();
        $jenisScanDiminta = $this->normalisasiJenisScan($jenisScanDiminta);
        $parsed = $this->parseIsiScan($isiScan);

        if (! $parsed['valid']) {
            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'format_tidak_valid',
                pesan: 'Scan tidak terbaca. Silakan scan ulang.',
                scannerId: $parsed['scanner_id'],
                nisn: $parsed['nisn'],
                jenisScan: $jenisScanDiminta,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        $siswa = Siswa::query()
            ->where('nisn', $parsed['nisn'])
            ->first();

        if (! $siswa) {
            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'siswa_tidak_ditemukan',
                pesan: 'NISN tidak ditemukan.',
                scannerId: $parsed['scanner_id'],
                nisn: $parsed['nisn'],
                jenisScan: $jenisScanDiminta,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        if (! $siswa->aktif) {
            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'siswa_nonaktif',
                pesan: 'Siswa ditemukan, tetapi statusnya nonaktif.',
                scannerId: $parsed['scanner_id'],
                nisn: $parsed['nisn'],
                jenisScan: $jenisScanDiminta,
                siswa: $siswa,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        $scanBerhasilTerbaru = $this->scanBerhasilTerbaru($parsed['nisn'], $waktuScan);

        if ($scanBerhasilTerbaru) {
            $absensi = $scanBerhasilTerbaru->absensiSiswa;
            $jenisScan = $jenisScanDiminta ?: $scanBerhasilTerbaru->jenis_scan;

            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'duplikat_cepat',
                pesan: $this->pesanSudahTercatat(
                    $jenisScan,
                    $jenisScan === 'pulang' ? $absensi?->jam_pulang : $absensi?->jam_masuk,
                ),
                scannerId: $parsed['scanner_id'],
                nisn: $parsed['nisn'],
                jenisScan: $jenisScan,
                siswa: $siswa,
                absensi: $absensi,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->first();

        if (! $tahunPelajaran) {
            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'tahun_pelajaran_tidak_ada',
                pesan: 'Tahun pelajaran aktif belum diatur.',
                scannerId: $parsed['scanner_id'],
                nisn: $parsed['nisn'],
                jenisScan: $jenisScanDiminta,
                siswa: $siswa,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        $anggotaKelas = AnggotaKelas::query()
            ->with(['kelas', 'tahunPelajaran'])
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('siswa_id', $siswa->id)
            ->where('status_keanggotaan', 'aktif')
            ->first();

        if (! $anggotaKelas) {
            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'anggota_kelas_tidak_ada',
                pesan: 'Siswa belum terdaftar pada kelas aktif tahun pelajaran ini.',
                scannerId: $parsed['scanner_id'],
                nisn: $parsed['nisn'],
                jenisScan: $jenisScanDiminta,
                siswa: $siswa,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        $pengaturanAbsensi = PengaturanAbsensi::query()
            ->where('hari', $this->hariDariTanggal($waktuScan))
            ->where('aktif', true)
            ->first();

        if (! $pengaturanAbsensi) {
            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'jadwal_absensi_tidak_ada',
                pesan: 'Pengaturan presensi untuk hari ini belum ada atau belum aktif.',
                scannerId: $parsed['scanner_id'],
                nisn: $parsed['nisn'],
                jenisScan: $jenisScanDiminta,
                siswa: $siswa,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        $jenisScan = $jenisScanDiminta ?: $this->tentukanJenisScan($waktuScan, $pengaturanAbsensi, $siswa);

        if (! $jenisScan) {
            if ($pesanJumat = $this->pesanPulangJumatBelumDibuka($waktuScan, $pengaturanAbsensi, $siswa)) {
                return $this->gagal(
                    isiScan: $isiScan,
                    waktuScan: $waktuScan,
                    statusScan: 'pulang_jumat_belum_dibuka',
                    pesan: $pesanJumat,
                    scannerId: $parsed['scanner_id'],
                    nisn: $parsed['nisn'],
                    jenisScan: 'pulang',
                    siswa: $siswa,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                );
            }

            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'di_luar_jadwal',
                pesan: 'Scan berada di luar jadwal masuk dan pulang.',
                scannerId: $parsed['scanner_id'],
                nisn: $parsed['nisn'],
                jenisScan: null,
                siswa: $siswa,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        if (! $this->beradaDalamJendelaScan($jenisScan, $waktuScan, $pengaturanAbsensi, $siswa)) {
            if ($jenisScan === 'pulang'
                && ($pesanJumat = $this->pesanPulangJumatBelumDibuka($waktuScan, $pengaturanAbsensi, $siswa))) {
                return $this->gagal(
                    isiScan: $isiScan,
                    waktuScan: $waktuScan,
                    statusScan: 'pulang_jumat_belum_dibuka',
                    pesan: $pesanJumat,
                    scannerId: $parsed['scanner_id'],
                    nisn: $parsed['nisn'],
                    jenisScan: 'pulang',
                    siswa: $siswa,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                );
            }

            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'di_luar_jadwal_'.$jenisScan,
                pesan: 'Scan '.$jenisScan.' berada di luar jadwal yang ditentukan.',
                scannerId: $parsed['scanner_id'],
                nisn: $parsed['nisn'],
                jenisScan: $jenisScan,
                siswa: $siswa,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        return $jenisScan === 'masuk'
            ? $this->prosesMasuk($isiScan, $parsed, $waktuScan, $siswa, $tahunPelajaran, $anggotaKelas, $pengaturanAbsensi, $ipAddress, $userAgent)
            : $this->prosesPulang($isiScan, $parsed, $waktuScan, $siswa, $tahunPelajaran, $anggotaKelas, $pengaturanAbsensi, $ipAddress, $userAgent);
    }

    private function prosesMasuk(
        string $isiScan,
        array $parsed,
        CarbonInterface $waktuScan,
        Siswa $siswa,
        TahunPelajaran $tahunPelajaran,
        AnggotaKelas $anggotaKelas,
        PengaturanAbsensi $pengaturanAbsensi,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        return DB::transaction(function () use ($isiScan, $parsed, $waktuScan, $siswa, $tahunPelajaran, $anggotaKelas, $pengaturanAbsensi, $ipAddress, $userAgent) {
            $absensi = $this->ambilAtauBuatAbsensi($waktuScan, $siswa, $tahunPelajaran, $anggotaKelas);

            if (in_array($absensi->status_kehadiran, ['sakit', 'izin'], true) && ! $absensi->jam_masuk) {
                return $this->gagal(
                    isiScan: $isiScan,
                    waktuScan: $waktuScan,
                    statusScan: 'kehadiran_manual_aktif',
                    pesan: 'Kehadiran sudah dicatat '.ucfirst($absensi->status_kehadiran).' oleh petugas. Hubungi guru piket jika catatan perlu dikoreksi.',
                    scannerId: $parsed['scanner_id'],
                    nisn: $parsed['nisn'],
                    jenisScan: 'masuk',
                    siswa: $siswa,
                    absensi: $absensi,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                );
            }

            if ($absensi->jam_masuk) {
                return $this->gagal(
                    isiScan: $isiScan,
                    waktuScan: $waktuScan,
                    statusScan: 'sudah_scan_masuk',
                    pesan: $this->pesanSudahTercatat('masuk', $absensi->jam_masuk),
                    scannerId: $parsed['scanner_id'],
                    nisn: $parsed['nisn'],
                    jenisScan: 'masuk',
                    siswa: $siswa,
                    absensi: $absensi,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                );
            }

            $menitScan = $this->menitDariJam($waktuScan->format('H:i'));
            $menitMasuk = $this->menitDariJam($pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_masuk));
            $menitTerlambat = max(0, $menitScan - $menitMasuk);
            $statusMasuk = $menitTerlambat > 0 ? 'terlambat' : 'tepat_waktu';

            $absensi->update([
                'jam_masuk' => $waktuScan->format('H:i:s'),
                'status_masuk' => $statusMasuk,
                'menit_terlambat' => $menitTerlambat,
                'status_kehadiran' => 'hadir',
                'sumber' => 'scan',
            ]);

            $pesan = $statusMasuk === 'terlambat'
                ? 'Scan masuk berhasil. Siswa terlambat '.$menitTerlambat.' menit.'
                : 'Scan masuk berhasil. Siswa hadir tepat waktu.';

            $log = $this->catatLog(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'berhasil_masuk',
                pesan: $pesan,
                berhasil: true,
                scannerId: $parsed['scanner_id'],
                nisn: $parsed['nisn'],
                jenisScan: 'masuk',
                siswa: $siswa,
                absensi: $absensi,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            $notifikasi = $this->notifikasiAbsensiSiswaService->jadwalkanScanMasuk($absensi, $log);

            return $this->hasil(true, 'berhasil_masuk', $pesan, $siswa, $absensi, $log, 'masuk', [
                'status_masuk' => $statusMasuk,
                'menit_terlambat' => $menitTerlambat,
                'scanner_id' => $parsed['scanner_id'],
                'nisn' => $parsed['nisn'],
                'notifikasi_absensi_id' => $notifikasi?->id,
            ]);
        });
    }

    private function prosesPulang(
        string $isiScan,
        array $parsed,
        CarbonInterface $waktuScan,
        Siswa $siswa,
        TahunPelajaran $tahunPelajaran,
        AnggotaKelas $anggotaKelas,
        PengaturanAbsensi $pengaturanAbsensi,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        return DB::transaction(function () use ($isiScan, $parsed, $waktuScan, $siswa, $tahunPelajaran, $anggotaKelas, $pengaturanAbsensi, $ipAddress, $userAgent) {
            $absensi = $this->ambilAtauBuatAbsensi($waktuScan, $siswa, $tahunPelajaran, $anggotaKelas);

            if (in_array($absensi->status_kehadiran, ['sakit', 'izin'], true) && ! $absensi->jam_masuk) {
                return $this->gagal(
                    isiScan: $isiScan,
                    waktuScan: $waktuScan,
                    statusScan: 'kehadiran_manual_aktif',
                    pesan: 'Kehadiran sudah dicatat '.ucfirst($absensi->status_kehadiran).' oleh petugas. Hubungi guru piket jika catatan perlu dikoreksi.',
                    scannerId: $parsed['scanner_id'],
                    nisn: $parsed['nisn'],
                    jenisScan: 'pulang',
                    siswa: $siswa,
                    absensi: $absensi,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                );
            }

            if ($absensi->jam_pulang) {
                return $this->gagal(
                    isiScan: $isiScan,
                    waktuScan: $waktuScan,
                    statusScan: 'sudah_scan_pulang',
                    pesan: $this->pesanSudahTercatat('pulang', $absensi->jam_pulang),
                    scannerId: $parsed['scanner_id'],
                    nisn: $parsed['nisn'],
                    jenisScan: 'pulang',
                    siswa: $siswa,
                    absensi: $absensi,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                );
            }

            $jadwalPulang = $pengaturanAbsensi->jadwalPulangUntuk($siswa->jenis_kelamin);
            $menitScan = $this->menitDariJam($waktuScan->format('H:i'));
            $menitPulang = $this->menitDariJam($pengaturanAbsensi->formatJam($jadwalPulang['jam_pulang']));
            $menitPulangCepat = max(0, $menitPulang - $menitScan);
            $statusPulang = $menitPulangCepat > 0 ? 'pulang_cepat' : 'normal';

            $absensi->update([
                'jam_pulang' => $waktuScan->format('H:i:s'),
                'status_pulang' => $statusPulang,
                'menit_pulang_cepat' => $menitPulangCepat,
                'status_kehadiran' => 'hadir',
                'sumber' => 'scan',
            ]);

            $pesan = $statusPulang === 'pulang_cepat'
                ? 'Scan pulang berhasil. Siswa pulang cepat '.$menitPulangCepat.' menit.'
                : 'Scan pulang berhasil.';

            $log = $this->catatLog(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'berhasil_pulang',
                pesan: $pesan,
                berhasil: true,
                scannerId: $parsed['scanner_id'],
                nisn: $parsed['nisn'],
                jenisScan: 'pulang',
                siswa: $siswa,
                absensi: $absensi,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            return $this->hasil(true, 'berhasil_pulang', $pesan, $siswa, $absensi, $log, 'pulang', [
                'status_pulang' => $statusPulang,
                'menit_pulang_cepat' => $menitPulangCepat,
                'scanner_id' => $parsed['scanner_id'],
                'nisn' => $parsed['nisn'],
            ]);
        });
    }

    private function ambilAtauBuatAbsensi(
        CarbonInterface $waktuScan,
        Siswa $siswa,
        TahunPelajaran $tahunPelajaran,
        AnggotaKelas $anggotaKelas,
    ): AbsensiSiswa {
        $absensi = AbsensiSiswa::query()
            ->whereDate('tanggal', $waktuScan->toDateString())
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($absensi) {
            return $absensi;
        }

        return AbsensiSiswa::create([
            'tanggal' => $waktuScan->toDateString(),
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'kelas_id' => $anggotaKelas->kelas_id,
            'anggota_kelas_id' => $anggotaKelas->id,
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);
    }

    private function gagal(
        string $isiScan,
        CarbonInterface $waktuScan,
        string $statusScan,
        string $pesan,
        ?string $scannerId = null,
        ?string $nisn = null,
        ?string $jenisScan = null,
        ?Siswa $siswa = null,
        ?AbsensiSiswa $absensi = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $log = $this->catatLog(
            isiScan: $isiScan,
            waktuScan: $waktuScan,
            statusScan: $statusScan,
            pesan: $pesan,
            berhasil: false,
            scannerId: $scannerId,
            nisn: $nisn,
            jenisScan: $jenisScan,
            siswa: $siswa,
            absensi: $absensi,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return $this->hasil(false, $statusScan, $pesan, $siswa, $absensi, $log, $jenisScan, [
            'scanner_id' => $scannerId,
            'nisn' => $nisn,
        ]);
    }

    private function catatLog(
        string $isiScan,
        CarbonInterface $waktuScan,
        string $statusScan,
        string $pesan,
        bool $berhasil,
        ?string $scannerId = null,
        ?string $nisn = null,
        ?string $jenisScan = null,
        ?Siswa $siswa = null,
        ?AbsensiSiswa $absensi = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): LogScanAbsensi {
        return LogScanAbsensi::create([
            'absensi_siswa_id' => $absensi?->id,
            'siswa_id' => $siswa?->id,
            'isi_scan' => trim($isiScan),
            'nisn' => $nisn,
            'scanner_id' => $scannerId,
            'jenis_scan' => $jenisScan,
            'waktu_scan' => $waktuScan,
            'tanggal' => $waktuScan->toDateString(),
            'berhasil' => $berhasil,
            'status_scan' => $statusScan,
            'pesan' => $pesan,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    private function hasil(
        bool $berhasil,
        string $status,
        string $pesan,
        ?Siswa $siswa,
        ?AbsensiSiswa $absensi,
        LogScanAbsensi $log,
        ?string $jenisScan,
        array $tambahan = [],
    ): array {
        return array_merge([
            'berhasil' => $berhasil,
            'status' => $status,
            'pesan' => $pesan,
            'jenis_scan' => $jenisScan,
            'siswa' => $siswa,
            'absensi' => $absensi,
            'log' => $log,
        ], $tambahan);
    }

    private function parseIsiScan(string $isiScan): array
    {
        $isiScan = trim(preg_replace('/\s+/', '', $isiScan) ?? '');

        if ($isiScan === '') {
            return [
                'valid' => false,
                'scanner_id' => null,
                'nisn' => null,
            ];
        }

        if (preg_match('/^(S\d{1,2})[-:]([0-9]{4,30})$/i', $isiScan, $matches)) {
            return [
                'valid' => true,
                'scanner_id' => strtoupper($matches[1]),
                'nisn' => $matches[2],
            ];
        }

        if (preg_match('/^[0-9]{4,30}$/', $isiScan)) {
            return [
                'valid' => true,
                'scanner_id' => null,
                'nisn' => $isiScan,
            ];
        }

        return [
            'valid' => false,
            'scanner_id' => preg_match('/^(S\d{1,2})[-:]/i', $isiScan, $matches) ? strtoupper($matches[1]) : null,
            'nisn' => null,
        ];
    }

    private function normalisasiJenisScan(?string $jenisScan): ?string
    {
        return in_array($jenisScan, ['masuk', 'pulang'], true) ? $jenisScan : null;
    }

    private function tentukanJenisScan(
        CarbonInterface $waktuScan,
        PengaturanAbsensi $pengaturanAbsensi,
        Siswa $siswa,
    ): ?string {
        if ($this->beradaDalamJendelaScan('masuk', $waktuScan, $pengaturanAbsensi, $siswa)) {
            return 'masuk';
        }

        if ($this->beradaDalamJendelaScan('pulang', $waktuScan, $pengaturanAbsensi, $siswa)) {
            return 'pulang';
        }

        return null;
    }

    private function beradaDalamJendelaScan(
        string $jenisScan,
        CarbonInterface $waktuScan,
        PengaturanAbsensi $pengaturanAbsensi,
        Siswa $siswa,
    ): bool {
        $menitScan = $this->menitDariJam($waktuScan->format('H:i'));

        if ($jenisScan === 'masuk') {
            return $menitScan >= $this->menitDariJam($pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_scan_masuk_mulai))
                && $menitScan <= $this->menitDariJam($pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_scan_masuk_selesai));
        }

        $jadwalPulang = $pengaturanAbsensi->jadwalPulangUntuk($siswa->jenis_kelamin);

        return $menitScan >= $this->menitDariJam($pengaturanAbsensi->formatJam($jadwalPulang['jam_scan_pulang_mulai']))
            && $menitScan <= $this->menitDariJam($pengaturanAbsensi->formatJam($jadwalPulang['jam_scan_pulang_selesai']));
    }

    private function pesanPulangJumatBelumDibuka(
        CarbonInterface $waktuScan,
        PengaturanAbsensi $pengaturanAbsensi,
        Siswa $siswa,
    ): ?string {
        if (! $pengaturanAbsensi->pulangJumatDibedakan()
            || mb_strtoupper(trim((string) $siswa->jenis_kelamin)) === 'P') {
            return null;
        }

        $jadwalPerempuan = $pengaturanAbsensi->jadwalPulangUntuk('P');
        $jadwalLakiLaki = $pengaturanAbsensi->jadwalPulangUntuk('L');
        $menitScan = $this->menitDariJam($waktuScan->format('H:i'));
        $mulaiPerempuan = $this->menitDariJam($pengaturanAbsensi->formatJam($jadwalPerempuan['jam_scan_pulang_mulai']));
        $mulaiLakiLaki = $this->menitDariJam($pengaturanAbsensi->formatJam($jadwalLakiLaki['jam_scan_pulang_mulai']));

        if ($menitScan < $mulaiPerempuan || $menitScan >= $mulaiLakiLaki) {
            return null;
        }

        $jamMulai = $pengaturanAbsensi->formatJam($jadwalLakiLaki['jam_scan_pulang_mulai']);

        if (mb_strtoupper(trim((string) $siswa->jenis_kelamin)) === 'L') {
            return 'Scan pulang siswa laki-laki belum dibuka. Silakan scan mulai pukul '
                .$jamMulai.' setelah salat Jumat.';
        }

        return 'Jenis kelamin siswa belum dilengkapi. Untuk keamanan, jadwal pulang mengikuti siswa laki-laki dan baru dibuka pukul '
            .$jamMulai.'.';
    }

    private function scanBerhasilTerbaru(string $nisn, CarbonInterface $waktuScan): ?LogScanAbsensi
    {
        $batasAwal = Carbon::parse($waktuScan->toDateTimeString())
            ->subSeconds(self::JEDA_DUPLIKAT_DETIK);

        return LogScanAbsensi::query()
            ->with('absensiSiswa')
            ->where('nisn', $nisn)
            ->where('berhasil', true)
            ->where('waktu_scan', '>=', $batasAwal)
            ->latest('waktu_scan')
            ->first();
    }

    private function pesanSudahTercatat(?string $jenisScan, ?string $jam): string
    {
        $jenis = $jenisScan ? ' '.$jenisScan : '';
        $waktu = $jam ? ' pukul '.substr($jam, 0, 5) : '';

        return 'Presensi'.$jenis.' sudah tercatat'.$waktu.'. Tidak perlu scan ulang.';
    }

    private function hariDariTanggal(CarbonInterface $tanggal): string
    {
        return self::HARI[$tanggal->isoWeekday()];
    }

    private function menitDariJam(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($hour * 60) + $minute;
    }
}
