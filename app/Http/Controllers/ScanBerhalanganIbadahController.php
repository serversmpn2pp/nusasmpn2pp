<?php

namespace App\Http\Controllers;

use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\PresensiBerhalanganIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesBerhalanganIbadah;
use App\Services\Ibadah\ProsesScanBerhalanganIbadah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ScanBerhalanganIbadahController extends Controller
{
    public function index(Request $request, AksesBerhalanganIbadah $akses)
    {
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();
        abort_unless($akses->dapatMemindai($request->user(), $tahunPelajaran), 403, 'Halaman privat ini hanya dapat dibuka oleh pendamping ibadah siswi yang ditugaskan.');

        $hari = $this->hariDariTanggal(now()->dayOfWeekIso);
        $daftarJadwal = collect();

        if ($tahunPelajaran && $hari !== 'minggu') {
            $daftarJadwal = JadwalKegiatanIbadah::query()
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

        $jadwalId = $request->integer('jadwal_id');
        $jadwalDipilih = $daftarJadwal->firstWhere('id', $jadwalId)
            ?? $daftarJadwal->first(fn (JadwalKegiatanIbadah $jadwal) => $this->scanSedangDibuka($jadwal))
            ?? $daftarJadwal->first();
        $tanggal = now()->toDateString();
        $jumlahHariIni = $jadwalDipilih
            ? PresensiBerhalanganIbadah::query()
                ->where('kegiatan_ibadah_id', $jadwalDipilih->kegiatan_ibadah_id)
                ->whereDate('tanggal', $tanggal)
                ->count()
            : 0;

        return view('scan-berhalangan-ibadah.index', [
            'tahunPelajaran' => $tahunPelajaran,
            'daftarJadwal' => $daftarJadwal,
            'jadwalDipilih' => $jadwalDipilih,
            'scanDibuka' => $jadwalDipilih ? $this->scanSedangDibuka($jadwalDipilih) : false,
            'statusJadwal' => $this->statusJadwal($jadwalDipilih),
            'tanggalLabel' => now()->locale('id')->translatedFormat('l, d F Y'),
            'waktuServerIso' => now()->toIso8601String(),
            'jumlahHariIni' => $jumlahHariIni,
        ]);
    }

    public function store(Request $request, ProsesScanBerhalanganIbadah $prosesScan): JsonResponse
    {
        $data = $request->validate([
            'jadwal_kegiatan_ibadah_id' => ['required', 'integer', 'exists:jadwal_kegiatan_ibadah,id'],
            'isi_scan' => ['required', 'string', 'max:100'],
        ]);
        $jadwal = JadwalKegiatanIbadah::query()->with('kegiatanIbadah')->findOrFail($data['jadwal_kegiatan_ibadah_id']);
        $hasil = $prosesScan->proses(
            jadwal: $jadwal,
            isiScan: $data['isi_scan'],
            petugas: $request->user(),
            waktuScan: now(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
        $presensi = $hasil['presensi'];

        if ($presensi) {
            $presensi->loadMissing(['siswa:id,nama_lengkap,nisn,foto', 'kelas:id,nama']);
        }

        return response()->json([
            'berhasil' => $hasil['berhasil'],
            'baru' => $hasil['baru'],
            'status' => $hasil['status'],
            'pesan' => $hasil['pesan'],
            'waktu_server' => now()->format('H:i:s'),
            'presensi' => $presensi ? $this->dataPresensi($presensi, $hasil['hari_ke']) : null,
            'siswa' => $this->dataSiswa($hasil['siswa'], $hasil['anggota_kelas'], $hasil['hari_ke']),
            'jumlah_hari_ini' => PresensiBerhalanganIbadah::query()
                ->where('kegiatan_ibadah_id', $jadwal->kegiatan_ibadah_id)
                ->whereDate('tanggal', now()->toDateString())
                ->count(),
        ], $hasil['berhasil'] ? 200 : 422);
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

    private function scanSedangDibuka(JadwalKegiatanIbadah $jadwal): bool
    {
        $menit = $this->menit(now()->format('H:i'));

        return $menit >= $this->menit($jadwal->formatJam($jadwal->jam_scan_mulai))
            && $menit <= $this->menit($jadwal->formatJam($jadwal->jam_scan_selesai));
    }

    private function statusJadwal(?JadwalKegiatanIbadah $jadwal): array
    {
        if (! $jadwal) {
            return ['kode' => 'tidak_ada', 'label' => 'Tidak ada jadwal', 'pesan' => 'Belum ada jadwal kegiatan ibadah aktif untuk hari ini.'];
        }

        $menit = $this->menit(now()->format('H:i'));
        $mulai = $this->menit($jadwal->formatJam($jadwal->jam_scan_mulai));
        $selesai = $this->menit($jadwal->formatJam($jadwal->jam_scan_selesai));

        if ($menit < $mulai) {
            return ['kode' => 'belum', 'label' => 'Belum dibuka', 'pesan' => 'Scan dibuka pukul '.$jadwal->formatJam($jadwal->jam_scan_mulai).'.'];
        }

        if ($menit > $selesai) {
            return ['kode' => 'selesai', 'label' => 'Sudah ditutup', 'pesan' => 'Batas scan hari ini pukul '.$jadwal->formatJam($jadwal->jam_scan_selesai).'.'];
        }

        return ['kode' => 'aktif', 'label' => 'Scan privat aktif', 'pesan' => 'Kamera siap digunakan oleh petugas pendamping.'];
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
