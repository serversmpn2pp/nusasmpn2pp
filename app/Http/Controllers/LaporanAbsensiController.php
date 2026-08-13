<?php

namespace App\Http\Controllers;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\PengaturanAbsensi;
use App\Models\TahunPelajaran;
use App\Support\PenulisExcelLaporanAbsensi;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LaporanAbsensiController extends Controller
{
    private const DAFTAR_PERIODE = ['harian', 'bulanan', 'semester', 'rentang'];

    public function index(Request $request)
    {
        return view('laporan-absensi.index', $this->bangunDataLaporan($request));
    }

    public function exportExcel(Request $request, PenulisExcelLaporanAbsensi $penulisExcelLaporanAbsensi)
    {
        $laporan = $this->bangunDataLaporan($request);
        $lokasiBerkas = $penulisExcelLaporanAbsensi->buat($laporan);

        return response()
            ->download($lokasiBerkas, $this->namaBerkasExport($laporan), [
                'Content-Type' => PenulisExcelLaporanAbsensi::MIME,
            ])
            ->deleteFileAfterSend(true);
    }

    private function bangunDataLaporan(Request $request): array
    {
        $pengguna = $request->user();
        $cakupanWaliKelas = $pengguna?->membatasiCakupanWaliKelas() ?? false;
        $kelasWaliIds = $cakupanWaliKelas ? $pengguna->kelasWaliIds() : [];

        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'periode' => ['nullable', Rule::in(self::DAFTAR_PERIODE)],
            'tanggal' => ['nullable', 'date'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        $daftarTahunPelajaran = TahunPelajaran::query()
            ->when($cakupanWaliKelas, function ($query) use ($kelasWaliIds) {
                $query->whereHas('kelas', fn ($query) => $query->whereIn('id', $kelasWaliIds));
            })
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->get();
        $tahunPelajaranId = $this->ambilTahunPelajaranId($data['tahun_pelajaran_id'] ?? null, $daftarTahunPelajaran);
        $tahunPelajaran = $daftarTahunPelajaran->firstWhere('id', $tahunPelajaranId);
        $daftarKelas = $tahunPelajaranId
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('aktif', true)
                ->when($cakupanWaliKelas, fn ($query) => $query->whereIn('id', $kelasWaliIds))
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();
        $kelasId = $this->ambilKelasId($data['kelas_id'] ?? null, $daftarKelas);
        $periode = $data['periode'] ?? 'bulanan';
        $semester = $data['semester'] ?? $this->semesterSaatIni();
        $rentang = $this->ambilRentangTanggal($data, $periode, $semester, $tahunPelajaran);
        $hariAktif = PengaturanAbsensi::query()
            ->where('aktif', true)
            ->pluck('hari')
            ->all();
        $tanggalEfektif = $this->ambilTanggalEfektif($rentang['mulai'], $rentang['selesai'], $hariAktif);
        $laporanAbsensi = $tahunPelajaranId
            ? $this->ambilLaporanAbsensi($tahunPelajaranId, $kelasId, $tanggalEfektif, $cakupanWaliKelas ? $kelasWaliIds : null)
            : collect();
        $ringkasan = $this->hitungRingkasan($laporanAbsensi, count($tanggalEfektif));

        $kelasDipilih = $kelasId ? $daftarKelas->firstWhere('id', (int) $kelasId) : null;

        return [
            'daftarTahunPelajaran' => $daftarTahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'tahunPelajaran' => $tahunPelajaran,
            'daftarKelas' => $daftarKelas,
            'kelasId' => $kelasId,
            'kelasDipilih' => $kelasDipilih,
            'periode' => $periode,
            'tanggal' => $rentang['tanggal'],
            'bulan' => $rentang['bulan'],
            'semester' => $semester,
            'tanggalMulai' => $rentang['mulai']->toDateString(),
            'tanggalSelesai' => $rentang['selesai']->toDateString(),
            'labelPeriode' => $rentang['label'],
            'hariAktif' => $hariAktif,
            'jumlahHariEfektif' => count($tanggalEfektif),
            'laporanAbsensi' => $laporanAbsensi,
            'ringkasan' => $ringkasan,
            'cakupanWaliKelas' => $cakupanWaliKelas,
        ];
    }

    private function namaBerkasExport(array $laporan): string
    {
        $kelas = $laporan['kelasDipilih']?->nama ?? 'semua kelas';
        $slugKelas = str($kelas)->slug('-')->toString();

        return 'laporan-presensi-' . $slugKelas . '-' . now()->format('Ymd-His') . '.xlsx';
    }

    private function ambilTahunPelajaranId(?int $tahunPelajaranId, $daftarTahunPelajaran): ?int
    {
        if ($tahunPelajaranId && $daftarTahunPelajaran->contains('id', $tahunPelajaranId)) {
            return $tahunPelajaranId;
        }

        $tahunAktif = $daftarTahunPelajaran->firstWhere('aktif', true);

        return $tahunAktif?->id ?? $daftarTahunPelajaran->first()?->id;
    }

    private function ambilKelasId(?int $kelasId, $daftarKelas): ?int
    {
        if ($kelasId && $daftarKelas->contains('id', $kelasId)) {
            return $kelasId;
        }

        return null;
    }

    private function ambilRentangTanggal(array $data, string $periode, string $semester, ?TahunPelajaran $tahunPelajaran): array
    {
        $tanggal = Carbon::parse($data['tanggal'] ?? now());
        $bulan = $data['bulan'] ?? now()->format('Y-m');

        if ($periode === 'harian') {
            $mulai = $tanggal->copy()->startOfDay();
            $selesai = $tanggal->copy()->endOfDay();
        } elseif ($periode === 'semester') {
            [$mulai, $selesai] = $this->rentangSemester($semester, $tahunPelajaran);
        } elseif ($periode === 'rentang') {
            $mulai = Carbon::parse($data['tanggal_mulai'] ?? now()->startOfMonth())->startOfDay();
            $selesai = Carbon::parse($data['tanggal_selesai'] ?? now())->endOfDay();
        } else {
            $bulanCarbon = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
            $mulai = $bulanCarbon->copy()->startOfMonth();
            $selesai = $bulanCarbon->copy()->endOfMonth();
        }

        return [
            'tanggal' => $tanggal->toDateString(),
            'bulan' => $bulan,
            'mulai' => $mulai,
            'selesai' => $selesai,
            'label' => $this->labelPeriode($periode, $mulai, $selesai, $semester),
        ];
    }

    private function rentangSemester(string $semester, ?TahunPelajaran $tahunPelajaran): array
    {
        if (! $tahunPelajaran?->tanggal_mulai) {
            $tahun = now()->year;

            return $semester === 'ganjil'
                ? [Carbon::create($tahun, 7, 1)->startOfDay(), Carbon::create($tahun, 12, 31)->endOfDay()]
                : [Carbon::create($tahun + 1, 1, 1)->startOfDay(), Carbon::create($tahun + 1, 6, 30)->endOfDay()];
        }

        $mulaiTahun = $tahunPelajaran->tanggal_mulai->copy()->startOfDay();
        $selesaiTahun = $tahunPelajaran->tanggal_selesai?->copy()->endOfDay() ?? $mulaiTahun->copy()->addYear()->subDay()->endOfDay();
        $tahunAwal = $mulaiTahun->year;

        if ($semester === 'ganjil') {
            $mulai = $mulaiTahun;
            $selesai = Carbon::create($tahunAwal, 12, 31)->endOfDay();
        } else {
            $mulai = Carbon::create($tahunAwal + 1, 1, 1)->startOfDay();
            $selesai = $selesaiTahun;
        }

        if ($selesai->greaterThan($selesaiTahun)) {
            $selesai = $selesaiTahun;
        }

        if ($mulai->lessThan($mulaiTahun)) {
            $mulai = $mulaiTahun;
        }

        return [$mulai, $selesai];
    }

    private function labelPeriode(string $periode, Carbon $mulai, Carbon $selesai, string $semester): string
    {
        if ($periode === 'semester') {
            return 'Semester ' . ucfirst($semester) . ' - ' . $this->formatTanggal($mulai) . ' s.d. ' . $this->formatTanggal($selesai);
        }

        if ($mulai->isSameDay($selesai)) {
            return $this->formatTanggal($mulai);
        }

        return $this->formatTanggal($mulai) . ' s.d. ' . $this->formatTanggal($selesai);
    }

    private function ambilTanggalEfektif(Carbon $mulai, Carbon $selesai, array $hariAktif): array
    {
        if ($mulai->greaterThan($selesai) || empty($hariAktif)) {
            return [];
        }

        $hariAktif = array_flip($hariAktif);
        $tanggalEfektif = [];

        foreach (CarbonPeriod::create($mulai->toDateString(), $selesai->toDateString()) as $tanggal) {
            if (isset($hariAktif[$this->hariDariTanggal($tanggal->isoWeekday())])) {
                $tanggalEfektif[] = $tanggal->toDateString();
            }
        }

        return $tanggalEfektif;
    }

    private function ambilLaporanAbsensi(int $tahunPelajaranId, ?int $kelasId, array $tanggalEfektif, ?array $kelasIdsTerjangkau = null)
    {
        $anggotaKelas = AnggotaKelas::query()
            ->with(['kelas', 'siswa'])
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('status_keanggotaan', 'aktif')
            ->when(is_array($kelasIdsTerjangkau), fn ($query) => $query->whereIn('kelas_id', $kelasIdsTerjangkau))
            ->whereHas('siswa', function ($query) {
                $query->where('aktif', true);
            })
            ->when($kelasId, function ($query) use ($kelasId) {
                $query->where('kelas_id', $kelasId);
            })
            ->orderBy('kelas_id')
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->get();

        $absensiPerSiswa = empty($tanggalEfektif)
            ? collect()
            : AbsensiSiswa::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->when(is_array($kelasIdsTerjangkau), fn ($query) => $query->whereIn('kelas_id', $kelasIdsTerjangkau))
                ->when($kelasId, function ($query) use ($kelasId) {
                    $query->where('kelas_id', $kelasId);
                })
                ->whereIn('tanggal', $tanggalEfektif)
                ->get()
                ->groupBy('siswa_id');

        $jumlahHariEfektif = count($tanggalEfektif);

        return $anggotaKelas->map(function (AnggotaKelas $anggota) use ($absensiPerSiswa, $jumlahHariEfektif) {
            $absensi = $absensiPerSiswa->get($anggota->siswa_id, collect());
            $hadir = $absensi->where('status_kehadiran', 'hadir')->count();
            $izin = $absensi->where('status_kehadiran', 'izin')->count();
            $sakit = $absensi->where('status_kehadiran', 'sakit')->count();
            $alfaTercatat = $absensi->where('status_kehadiran', 'alfa')->count();
            $alfaInferensi = max(0, $jumlahHariEfektif - $absensi->count());
            $alfa = $alfaTercatat + $alfaInferensi;
            $terlambat = $absensi->where('menit_terlambat', '>', 0);
            $pulangCepat = $absensi->where('menit_pulang_cepat', '>', 0);

            return [
                'anggota_kelas' => $anggota,
                'hari_efektif' => $jumlahHariEfektif,
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alfa' => $alfa,
                'terlambat' => $terlambat->count(),
                'menit_terlambat' => (int) round((float) $terlambat->sum('menit_terlambat')),
                'pulang_cepat' => $pulangCepat->count(),
                'menit_pulang_cepat' => (int) round((float) $pulangCepat->sum('menit_pulang_cepat')),
                'persentase_hadir' => $jumlahHariEfektif > 0 ? round(($hadir / $jumlahHariEfektif) * 100, 1) : 0,
            ];
        });
    }

    private function hitungRingkasan($laporanAbsensi, int $jumlahHariEfektif): array
    {
        return [
            'siswa' => $laporanAbsensi->count(),
            'hari_efektif' => $jumlahHariEfektif,
            'hadir' => $laporanAbsensi->sum('hadir'),
            'izin' => $laporanAbsensi->sum('izin'),
            'sakit' => $laporanAbsensi->sum('sakit'),
            'alfa' => $laporanAbsensi->sum('alfa'),
            'terlambat' => $laporanAbsensi->sum('terlambat'),
            'menit_terlambat' => (int) round((float) $laporanAbsensi->sum('menit_terlambat')),
            'pulang_cepat' => $laporanAbsensi->sum('pulang_cepat'),
            'menit_pulang_cepat' => (int) round((float) $laporanAbsensi->sum('menit_pulang_cepat')),
            'rata_persentase_hadir' => $laporanAbsensi->count() ? round($laporanAbsensi->avg('persentase_hadir'), 1) : 0,
        ];
    }

    private function semesterSaatIni(): string
    {
        return now()->month >= 7 ? 'ganjil' : 'genap';
    }

    private function formatTanggal(Carbon $tanggal): string
    {
        return $tanggal->copy()->locale('id')->translatedFormat('d F Y');
    }

    private function hariDariTanggal(int $isoWeekday): string
    {
        return [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][$isoWeekday];
    }
}
