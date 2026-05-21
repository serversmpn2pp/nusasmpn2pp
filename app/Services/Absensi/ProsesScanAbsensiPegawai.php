<?php

namespace App\Services\Absensi;

use App\Models\AbsensiPegawai;
use App\Models\LogScanAbsensiPegawai;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ProsesScanAbsensiPegawai
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
                pesan: 'Scan pegawai tidak terbaca. Silakan scan ulang.',
                scannerId: $parsed['scanner_id'],
                nip: $parsed['nip'],
                jenisScan: $jenisScanDiminta,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        $pegawai = Pegawai::query()
            ->where('nip', $parsed['nip'])
            ->first();

        if (! $pegawai) {
            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'pegawai_tidak_ditemukan',
                pesan: 'NIP pegawai tidak ditemukan.',
                scannerId: $parsed['scanner_id'],
                nip: $parsed['nip'],
                jenisScan: $jenisScanDiminta,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        if (! $pegawai->aktif) {
            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'pegawai_nonaktif',
                pesan: 'Pegawai ditemukan, tetapi statusnya nonaktif.',
                scannerId: $parsed['scanner_id'],
                nip: $parsed['nip'],
                jenisScan: $jenisScanDiminta,
                pegawai: $pegawai,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        if ($this->scanDuplikatCepat($parsed['nip'], $waktuScan)) {
            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'duplikat_cepat',
                pesan: 'Scan pegawai sudah diterima beberapa detik lalu.',
                scannerId: $parsed['scanner_id'],
                nip: $parsed['nip'],
                jenisScan: $jenisScanDiminta,
                pegawai: $pegawai,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        $pengaturanAbsensiPegawai = $this->ambilPengaturanAbsensiPegawai($pegawai, $waktuScan);

        if (! $pengaturanAbsensiPegawai) {
            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'jadwal_absensi_tidak_ada',
                pesan: 'Jadwal absensi pegawai untuk hari ini belum ada atau belum aktif.',
                scannerId: $parsed['scanner_id'],
                nip: $parsed['nip'],
                jenisScan: $jenisScanDiminta,
                pegawai: $pegawai,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        $jenisScan = $jenisScanDiminta ?: $this->tentukanJenisScan($waktuScan, $pengaturanAbsensiPegawai);

        if (! $jenisScan) {
            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'di_luar_jadwal',
                pesan: 'Scan pegawai berada di luar jadwal masuk dan pulang.',
                scannerId: $parsed['scanner_id'],
                nip: $parsed['nip'],
                jenisScan: null,
                pegawai: $pegawai,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        if (! $this->beradaDalamJendelaScan($jenisScan, $waktuScan, $pengaturanAbsensiPegawai)) {
            return $this->gagal(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'di_luar_jadwal_' . $jenisScan,
                pesan: 'Scan ' . $jenisScan . ' pegawai berada di luar jadwal yang ditentukan.',
                scannerId: $parsed['scanner_id'],
                nip: $parsed['nip'],
                jenisScan: $jenisScan,
                pegawai: $pegawai,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        }

        return $jenisScan === 'masuk'
            ? $this->prosesMasuk($isiScan, $parsed, $waktuScan, $pegawai, $pengaturanAbsensiPegawai, $ipAddress, $userAgent)
            : $this->prosesPulang($isiScan, $parsed, $waktuScan, $pegawai, $pengaturanAbsensiPegawai, $ipAddress, $userAgent);
    }

    private function prosesMasuk(
        string $isiScan,
        array $parsed,
        CarbonInterface $waktuScan,
        Pegawai $pegawai,
        PengaturanAbsensiPegawai $pengaturanAbsensiPegawai,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        return DB::transaction(function () use ($isiScan, $parsed, $waktuScan, $pegawai, $pengaturanAbsensiPegawai, $ipAddress, $userAgent) {
            $absensi = $this->ambilAtauBuatAbsensi($waktuScan, $pegawai, $pengaturanAbsensiPegawai);

            if ($absensi->jam_masuk) {
                return $this->gagal(
                    isiScan: $isiScan,
                    waktuScan: $waktuScan,
                    statusScan: 'sudah_scan_masuk',
                    pesan: 'Pegawai sudah melakukan scan masuk.',
                    scannerId: $parsed['scanner_id'],
                    nip: $parsed['nip'],
                    jenisScan: 'masuk',
                    pegawai: $pegawai,
                    absensi: $absensi,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                );
            }

            $menitScan = $this->menitDariJam($waktuScan->format('H:i'));
            $menitMasuk = $this->menitDariJam($pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_masuk));
            $menitTerlambat = max(0, $menitScan - $menitMasuk);
            $statusMasuk = $menitTerlambat > 0 ? 'terlambat' : 'tepat_waktu';

            $absensi->update([
                'pengaturan_absensi_pegawai_id' => $pengaturanAbsensiPegawai->id,
                'jam_masuk' => $waktuScan->format('H:i:s'),
                'status_masuk' => $statusMasuk,
                'menit_terlambat' => $menitTerlambat,
                'status_kehadiran' => 'hadir',
                'sumber' => 'scan',
            ]);

            $pesan = $statusMasuk === 'terlambat'
                ? 'Scan masuk berhasil. Pegawai terlambat ' . $menitTerlambat . ' menit.'
                : 'Scan masuk berhasil. Pegawai hadir tepat waktu.';

            $log = $this->catatLog(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'berhasil_masuk',
                pesan: $pesan,
                berhasil: true,
                scannerId: $parsed['scanner_id'],
                nip: $parsed['nip'],
                jenisScan: 'masuk',
                pegawai: $pegawai,
                absensi: $absensi,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            return $this->hasil(true, 'berhasil_masuk', $pesan, $pegawai, $absensi, $log, 'masuk', [
                'status_masuk' => $statusMasuk,
                'menit_terlambat' => $menitTerlambat,
                'scanner_id' => $parsed['scanner_id'],
                'nip' => $parsed['nip'],
                'jadwal' => $pengaturanAbsensiPegawai,
            ]);
        });
    }

    private function prosesPulang(
        string $isiScan,
        array $parsed,
        CarbonInterface $waktuScan,
        Pegawai $pegawai,
        PengaturanAbsensiPegawai $pengaturanAbsensiPegawai,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        return DB::transaction(function () use ($isiScan, $parsed, $waktuScan, $pegawai, $pengaturanAbsensiPegawai, $ipAddress, $userAgent) {
            $absensi = $this->ambilAtauBuatAbsensi($waktuScan, $pegawai, $pengaturanAbsensiPegawai);

            if ($absensi->jam_pulang) {
                return $this->gagal(
                    isiScan: $isiScan,
                    waktuScan: $waktuScan,
                    statusScan: 'sudah_scan_pulang',
                    pesan: 'Pegawai sudah melakukan scan pulang.',
                    scannerId: $parsed['scanner_id'],
                    nip: $parsed['nip'],
                    jenisScan: 'pulang',
                    pegawai: $pegawai,
                    absensi: $absensi,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                );
            }

            $menitScan = $this->menitDariJam($waktuScan->format('H:i'));
            $menitPulang = $this->menitDariJam($pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_pulang));
            $menitPulangCepat = max(0, $menitPulang - $menitScan);
            $statusPulang = $menitPulangCepat > 0 ? 'pulang_cepat' : 'normal';

            $absensi->update([
                'pengaturan_absensi_pegawai_id' => $pengaturanAbsensiPegawai->id,
                'jam_pulang' => $waktuScan->format('H:i:s'),
                'status_pulang' => $statusPulang,
                'menit_pulang_cepat' => $menitPulangCepat,
                'status_kehadiran' => 'hadir',
                'sumber' => 'scan',
            ]);

            $pesan = $statusPulang === 'pulang_cepat'
                ? 'Scan pulang berhasil. Pegawai pulang cepat ' . $menitPulangCepat . ' menit.'
                : 'Scan pulang berhasil.';

            $log = $this->catatLog(
                isiScan: $isiScan,
                waktuScan: $waktuScan,
                statusScan: 'berhasil_pulang',
                pesan: $pesan,
                berhasil: true,
                scannerId: $parsed['scanner_id'],
                nip: $parsed['nip'],
                jenisScan: 'pulang',
                pegawai: $pegawai,
                absensi: $absensi,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            return $this->hasil(true, 'berhasil_pulang', $pesan, $pegawai, $absensi, $log, 'pulang', [
                'status_pulang' => $statusPulang,
                'menit_pulang_cepat' => $menitPulangCepat,
                'scanner_id' => $parsed['scanner_id'],
                'nip' => $parsed['nip'],
                'jadwal' => $pengaturanAbsensiPegawai,
            ]);
        });
    }

    private function ambilAtauBuatAbsensi(
        CarbonInterface $waktuScan,
        Pegawai $pegawai,
        PengaturanAbsensiPegawai $pengaturanAbsensiPegawai,
    ): AbsensiPegawai {
        return AbsensiPegawai::firstOrCreate(
            [
                'tanggal' => $waktuScan->toDateString(),
                'pegawai_id' => $pegawai->id,
            ],
            [
                'pengaturan_absensi_pegawai_id' => $pengaturanAbsensiPegawai->id,
                'status_kehadiran' => 'hadir',
                'sumber' => 'scan',
            ]
        );
    }

    private function gagal(
        string $isiScan,
        CarbonInterface $waktuScan,
        string $statusScan,
        string $pesan,
        ?string $scannerId = null,
        ?string $nip = null,
        ?string $jenisScan = null,
        ?Pegawai $pegawai = null,
        ?AbsensiPegawai $absensi = null,
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
            nip: $nip,
            jenisScan: $jenisScan,
            pegawai: $pegawai,
            absensi: $absensi,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return $this->hasil(false, $statusScan, $pesan, $pegawai, $absensi, $log, $jenisScan, [
            'scanner_id' => $scannerId,
            'nip' => $nip,
        ]);
    }

    private function catatLog(
        string $isiScan,
        CarbonInterface $waktuScan,
        string $statusScan,
        string $pesan,
        bool $berhasil,
        ?string $scannerId = null,
        ?string $nip = null,
        ?string $jenisScan = null,
        ?Pegawai $pegawai = null,
        ?AbsensiPegawai $absensi = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): LogScanAbsensiPegawai {
        return LogScanAbsensiPegawai::create([
            'absensi_pegawai_id' => $absensi?->id,
            'pegawai_id' => $pegawai?->id,
            'isi_scan' => trim($isiScan),
            'nip' => $nip,
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
        ?Pegawai $pegawai,
        ?AbsensiPegawai $absensi,
        LogScanAbsensiPegawai $log,
        ?string $jenisScan,
        array $tambahan = [],
    ): array {
        return array_merge([
            'berhasil' => $berhasil,
            'status' => $status,
            'pesan' => $pesan,
            'jenis_scan' => $jenisScan,
            'pegawai' => $pegawai,
            'absensi' => $absensi,
            'log' => $log,
        ], $tambahan);
    }

    private function ambilPengaturanAbsensiPegawai(
        Pegawai $pegawai,
        CarbonInterface $waktuScan,
    ): ?PengaturanAbsensiPegawai {
        $hari = $this->hariDariTanggal($waktuScan);

        $jadwalPegawai = PengaturanAbsensiPegawai::query()
            ->where('hari', $hari)
            ->where('aktif', true)
            ->where('cakupan', 'pegawai')
            ->where('pegawai_id', $pegawai->id)
            ->first();

        if ($jadwalPegawai) {
            return $jadwalPegawai;
        }

        if (filled($pegawai->jenis_pegawai)) {
            $jadwalJenisPegawai = PengaturanAbsensiPegawai::query()
                ->where('hari', $hari)
                ->where('aktif', true)
                ->where('cakupan', 'jenis_pegawai')
                ->where('jenis_pegawai', $pegawai->jenis_pegawai)
                ->first();

            if ($jadwalJenisPegawai) {
                return $jadwalJenisPegawai;
            }
        }

        return PengaturanAbsensiPegawai::query()
            ->where('hari', $hari)
            ->where('aktif', true)
            ->where('cakupan', 'semua')
            ->first();
    }

    private function parseIsiScan(string $isiScan): array
    {
        $isiScan = trim(preg_replace('/\s+/', '', $isiScan) ?? '');

        if ($isiScan === '') {
            return [
                'valid' => false,
                'scanner_id' => null,
                'nip' => null,
            ];
        }

        if (preg_match('/^(P\d{1,2})[-:](.+)$/i', $isiScan, $matches)) {
            $nip = $matches[2];

            return [
                'valid' => $this->nipValid($nip),
                'scanner_id' => strtoupper($matches[1]),
                'nip' => $this->nipValid($nip) ? $nip : null,
            ];
        }

        if ($this->nipValid($isiScan)) {
            return [
                'valid' => true,
                'scanner_id' => null,
                'nip' => $isiScan,
            ];
        }

        return [
            'valid' => false,
            'scanner_id' => preg_match('/^(P\d{1,2})[-:]/i', $isiScan, $matches) ? strtoupper($matches[1]) : null,
            'nip' => null,
        ];
    }

    private function nipValid(string $nip): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._-]{3,50}$/', $nip);
    }

    private function normalisasiJenisScan(?string $jenisScan): ?string
    {
        return in_array($jenisScan, ['masuk', 'pulang'], true) ? $jenisScan : null;
    }

    private function tentukanJenisScan(
        CarbonInterface $waktuScan,
        PengaturanAbsensiPegawai $pengaturanAbsensiPegawai,
    ): ?string {
        if ($this->beradaDalamJendelaScan('masuk', $waktuScan, $pengaturanAbsensiPegawai)) {
            return 'masuk';
        }

        if ($this->beradaDalamJendelaScan('pulang', $waktuScan, $pengaturanAbsensiPegawai)) {
            return 'pulang';
        }

        return null;
    }

    private function beradaDalamJendelaScan(
        string $jenisScan,
        CarbonInterface $waktuScan,
        PengaturanAbsensiPegawai $pengaturanAbsensiPegawai,
    ): bool {
        $menitScan = $this->menitDariJam($waktuScan->format('H:i'));

        if ($jenisScan === 'masuk') {
            return $menitScan >= $this->menitDariJam($pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_scan_masuk_mulai))
                && $menitScan <= $this->menitDariJam($pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_scan_masuk_selesai));
        }

        return $menitScan >= $this->menitDariJam($pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_scan_pulang_mulai))
            && $menitScan <= $this->menitDariJam($pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_scan_pulang_selesai));
    }

    private function scanDuplikatCepat(string $nip, CarbonInterface $waktuScan): bool
    {
        $batasAwal = Carbon::parse($waktuScan->toDateTimeString())
            ->subSeconds(self::JEDA_DUPLIKAT_DETIK);

        return LogScanAbsensiPegawai::query()
            ->where('nip', $nip)
            ->where('berhasil', true)
            ->where('waktu_scan', '>=', $batasAwal)
            ->exists();
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
