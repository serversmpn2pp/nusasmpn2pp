<?php

namespace App\Services\Mobile;

use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\PengaturanBerhalanganIbadah;
use App\Models\Pengguna;
use App\Models\PresensiBerhalanganIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesBerhalanganIbadah;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ScanBerhalanganIbadahMobileService
{
    public function __construct(private AksesBerhalanganIbadah $akses) {}

    public function dashboard(Pengguna $pengguna, ?int $jadwalId = null, ?CarbonInterface $waktu = null): array
    {
        $waktu ??= now();
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        abort_unless(
            $this->akses->dapatMemindai($pengguna, $tahunPelajaran),
            403,
            'Halaman privat ini hanya dapat dibuka oleh pendamping ibadah siswi yang ditugaskan.',
        );

        $jadwal = $this->jadwalHariIni($tahunPelajaran, $waktu);
        $dipilih = $jadwal->firstWhere('id', $jadwalId)
            ?? $jadwal->first(fn (JadwalKegiatanIbadah $item) => $this->scanDibuka($item, $waktu))
            ?? $jadwal->first();
        $status = $this->statusJadwal($dipilih, $waktu);
        $pengaturan = $tahunPelajaran
            ? PengaturanBerhalanganIbadah::query()
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->first()
            : null;
        $kelas = $tahunPelajaran
            ? $this->akses->kelasTercakup($pengguna, $tahunPelajaran)
            : collect();

        return [
            'mode_privat' => true,
            'tahun_pelajaran' => $tahunPelajaran ? [
                'id' => $tahunPelajaran->id,
                'nama' => $tahunPelajaran->nama,
            ] : null,
            'tanggal_label' => $waktu->locale('id')->translatedFormat('l, d F Y'),
            'waktu_server' => $waktu->toIso8601String(),
            'scan_dibuka' => $status['kode'] === 'aktif',
            'status_jadwal' => $status,
            'jadwal_dipilih_id' => $dipilih?->id,
            'jadwal' => $jadwal->map(fn (JadwalKegiatanIbadah $item) => $this->dataJadwal($item, $waktu))->values(),
            'jumlah_hari_ini' => $dipilih
                ? PresensiBerhalanganIbadah::query()
                    ->where('kegiatan_ibadah_id', $dipilih->kegiatan_ibadah_id)
                    ->whereDate('tanggal', $waktu->toDateString())
                    ->count()
                : 0,
            'cakupan_kelas' => $kelas->map(fn ($item) => [
                'id' => $item->id,
                'nama' => $item->nama,
            ])->values(),
            'batas_hari_konfirmasi' => $pengaturan?->batas_hari_konfirmasi ?? 7,
            'pengaturan_aktif' => $pengaturan?->aktif ?? true,
            'pesan_privasi' => 'Identitas hasil scan hanya ditampilkan sesaat dan tidak disimpan sebagai riwayat terbuka di perangkat.',
        ];
    }

    public function dataHasil(array $hasil, JadwalKegiatanIbadah $jadwal, CarbonInterface $waktu): array
    {
        $presensi = $hasil['presensi'];

        if ($presensi) {
            $presensi->loadMissing(['siswa:id,nama_lengkap,nisn,foto', 'kelas:id,nama']);
        }

        return [
            'berhasil' => (bool) $hasil['berhasil'],
            'baru' => (bool) $hasil['baru'],
            'status' => $hasil['status'],
            'pesan' => $hasil['pesan'],
            'waktu_server' => $waktu->format('H:i:s'),
            'presensi' => $presensi ? $this->dataPresensi($presensi, $hasil['hari_ke']) : null,
            'siswa' => $this->dataSiswa($hasil['siswa'], $hasil['anggota_kelas'], $hasil['hari_ke']),
            'jumlah_hari_ini' => PresensiBerhalanganIbadah::query()
                ->where('kegiatan_ibadah_id', $jadwal->kegiatan_ibadah_id)
                ->whereDate('tanggal', $waktu->toDateString())
                ->count(),
        ];
    }

    private function jadwalHariIni(?TahunPelajaran $tahunPelajaran, CarbonInterface $waktu): Collection
    {
        $hari = $this->hariDariTanggal($waktu->dayOfWeekIso);

        if (! $tahunPelajaran || $hari === 'minggu') {
            return collect();
        }

        return JadwalKegiatanIbadah::query()
            ->with('kegiatanIbadah:id,nama,kode,aktif')
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('hari', $hari)
            ->where('aktif', true)
            ->whereHas('kegiatanIbadah', fn ($query) => $query
                ->where('aktif', true)
                ->where('kode', '!=', KegiatanIbadah::KODE_SHOLAT_JUMAT))
            ->orderBy('jam_pelaksanaan')
            ->get();
    }

    private function dataJadwal(JadwalKegiatanIbadah $jadwal, CarbonInterface $waktu): array
    {
        return [
            'id' => $jadwal->id,
            'kegiatan_id' => $jadwal->kegiatan_ibadah_id,
            'kegiatan' => $jadwal->kegiatanIbadah?->nama,
            'kode_kegiatan' => $jadwal->kegiatanIbadah?->kode,
            'jam_scan_mulai' => $jadwal->formatJam($jadwal->jam_scan_mulai),
            'jam_pelaksanaan' => $jadwal->formatJam($jadwal->jam_pelaksanaan),
            'jam_scan_selesai' => $jadwal->formatJam($jadwal->jam_scan_selesai),
            'rentang_scan' => $jadwal->rentangScan(),
            'keterangan' => $jadwal->keterangan,
            'scan_dibuka' => $this->scanDibuka($jadwal, $waktu),
        ];
    }

    private function dataPresensi(PresensiBerhalanganIbadah $presensi, ?int $hariKe): array
    {
        return [
            'id' => $presensi->id,
            'nama_lengkap' => $presensi->siswa?->nama_lengkap,
            'nisn' => $presensi->siswa?->nisn,
            'kelas' => $presensi->kelas?->nama,
            'foto_url' => $this->fotoUrl($presensi->siswa),
            'waktu_scan' => substr((string) $presensi->waktu_scan, 0, 8),
            'hari_ke' => $hariKe,
        ];
    }

    private function dataSiswa(?Siswa $siswa, $anggotaKelas, ?int $hariKe): ?array
    {
        if (! $siswa) {
            return null;
        }

        return [
            'nama_lengkap' => $siswa->nama_lengkap,
            'nisn' => $siswa->nisn,
            'kelas' => $anggotaKelas?->kelas?->nama,
            'foto_url' => $this->fotoUrl($siswa),
            'hari_ke' => $hariKe,
        ];
    }

    private function fotoUrl(?Siswa $siswa): ?string
    {
        return $siswa?->foto && Storage::disk('public')->exists($siswa->foto)
            ? asset('storage/'.$siswa->foto)
            : null;
    }

    private function scanDibuka(JadwalKegiatanIbadah $jadwal, CarbonInterface $waktu): bool
    {
        $menit = $this->menit($waktu->format('H:i'));

        return $menit >= $this->menit($jadwal->formatJam($jadwal->jam_scan_mulai))
            && $menit <= $this->menit($jadwal->formatJam($jadwal->jam_scan_selesai));
    }

    private function statusJadwal(?JadwalKegiatanIbadah $jadwal, CarbonInterface $waktu): array
    {
        if (! $jadwal) {
            return [
                'kode' => 'tidak_ada',
                'label' => 'Tidak ada jadwal',
                'pesan' => 'Belum ada jadwal kegiatan ibadah aktif untuk hari ini.',
            ];
        }

        $menit = $this->menit($waktu->format('H:i'));
        $mulai = $this->menit($jadwal->formatJam($jadwal->jam_scan_mulai));
        $selesai = $this->menit($jadwal->formatJam($jadwal->jam_scan_selesai));

        if ($menit < $mulai) {
            return [
                'kode' => 'belum',
                'label' => 'Belum dibuka',
                'pesan' => 'Scan dibuka pukul '.$jadwal->formatJam($jadwal->jam_scan_mulai).'.',
            ];
        }

        if ($menit > $selesai) {
            return [
                'kode' => 'selesai',
                'label' => 'Sudah ditutup',
                'pesan' => 'Batas scan hari ini pukul '.$jadwal->formatJam($jadwal->jam_scan_selesai).'.',
            ];
        }

        return [
            'kode' => 'aktif',
            'label' => 'Scan privat aktif',
            'pesan' => 'Kamera siap digunakan oleh petugas pendamping.',
        ];
    }

    private function hariDariTanggal(int $hariIso): string
    {
        return array_keys(JadwalKegiatanIbadah::DAFTAR_HARI)[$hariIso - 1] ?? 'minggu';
    }

    private function menit(string $jam): int
    {
        [$jamAngka, $menit] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($jamAngka * 60) + $menit;
    }
}
