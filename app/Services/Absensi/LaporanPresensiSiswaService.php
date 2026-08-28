<?php

namespace App\Services\Absensi;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\PengaturanAbsensi;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LaporanPresensiSiswaService
{
    private const DAFTAR_PERIODE = ['harian', 'bulanan', 'semester', 'rentang'];

    public function bangun(Request $request): array
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
            ->when($cakupanWaliKelas, fn ($query) => $query->whereHas('kelas', fn ($query) => $query->whereIn('id', $kelasWaliIds)))
            ->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->orderByDesc('id')->get();
        $tahunPelajaranId = $this->ambilTahunPelajaranId($data['tahun_pelajaran_id'] ?? null, $daftarTahunPelajaran);
        $tahunPelajaran = $daftarTahunPelajaran->firstWhere('id', $tahunPelajaranId);
        $daftarKelas = $tahunPelajaranId
            ? Kelas::query()->where('tahun_pelajaran_id', $tahunPelajaranId)->where('aktif', true)
                ->when($cakupanWaliKelas, fn ($query) => $query->whereIn('id', $kelasWaliIds))
                ->orderBy('tingkat')->orderBy('nama')->get()
            : collect();
        $kelasId = $this->ambilKelasId($data['kelas_id'] ?? null, $daftarKelas);
        $periode = $data['periode'] ?? 'bulanan';
        $semester = $data['semester'] ?? $this->semesterSaatIni();
        $rentang = $this->ambilRentangTanggal($data, $periode, $semester, $tahunPelajaran);
        $hariAktif = PengaturanAbsensi::query()->where('aktif', true)->pluck('hari')->all();
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
            'tanggalEfektif' => $tanggalEfektif,
            'jumlahHariEfektif' => count($tanggalEfektif),
            'laporanAbsensi' => $laporanAbsensi,
            'ringkasan' => $ringkasan,
            'cakupanWaliKelas' => $cakupanWaliKelas,
        ];
    }

    public function namaBerkas(array $laporan): string
    {
        $kelas = $laporan['kelasDipilih']?->nama ?? 'semua kelas';

        return 'laporan-presensi-'.str($kelas)->slug('-')->toString().'-'.now()->format('Ymd-His').'.xlsx';
    }

    private function ambilTahunPelajaranId(?int $id, $daftar): ?int
    {
        if ($id && $daftar->contains('id', $id)) {
            return $id;
        }

        return $daftar->firstWhere('aktif', true)?->id ?? $daftar->first()?->id;
    }

    private function ambilKelasId(?int $id, $daftar): ?int
    {
        return $id && $daftar->contains('id', $id) ? $id : null;
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
            'tanggal' => $tanggal->toDateString(), 'bulan' => $bulan, 'mulai' => $mulai, 'selesai' => $selesai,
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
        [$mulai, $selesai] = $semester === 'ganjil'
            ? [$mulaiTahun, Carbon::create($tahunAwal, 12, 31)->endOfDay()]
            : [Carbon::create($tahunAwal + 1, 1, 1)->startOfDay(), $selesaiTahun];

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
            return 'Semester '.ucfirst($semester).' - '.$this->formatTanggal($mulai).' s.d. '.$this->formatTanggal($selesai);
        }

        return $mulai->isSameDay($selesai) ? $this->formatTanggal($mulai) : $this->formatTanggal($mulai).' s.d. '.$this->formatTanggal($selesai);
    }

    private function ambilTanggalEfektif(Carbon $mulai, Carbon $selesai, array $hariAktif): array
    {
        if ($mulai->greaterThan($selesai) || empty($hariAktif)) {
            return [];
        }

        $hariAktif = array_flip($hariAktif);
        $hasil = [];
        foreach (CarbonPeriod::create($mulai->toDateString(), $selesai->toDateString()) as $tanggal) {
            if (isset($hariAktif[$this->hariDariTanggal($tanggal->isoWeekday())])) {
                $hasil[] = $tanggal->toDateString();
            }
        }

        return $hasil;
    }

    private function ambilLaporanAbsensi(int $tahunId, ?int $kelasId, array $tanggalEfektif, ?array $kelasIds = null)
    {
        $anggota = AnggotaKelas::query()->with(['kelas', 'siswa'])
            ->where('tahun_pelajaran_id', $tahunId)->where('status_keanggotaan', 'aktif')
            ->when(is_array($kelasIds), fn ($query) => $query->whereIn('kelas_id', $kelasIds))
            ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
            ->orderBy('kelas_id')->orderByRaw('nomor_absen IS NULL')->orderBy('nomor_absen')->orderBy('id')->get();
        $perSiswa = collect();
        if (! empty($tanggalEfektif)) {
            $tanggalEfektifLookup = array_flip($tanggalEfektif);
            $perSiswa = AbsensiSiswa::query()
                ->where('tahun_pelajaran_id', $tahunId)
                ->when(is_array($kelasIds), fn ($query) => $query->whereIn('kelas_id', $kelasIds))
                ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
                ->whereDate('tanggal', '>=', reset($tanggalEfektif))
                ->whereDate('tanggal', '<=', end($tanggalEfektif))
                ->get()
                ->filter(fn (AbsensiSiswa $item) => isset($tanggalEfektifLookup[$item->tanggal->toDateString()]))
                ->groupBy('siswa_id');
        }
        $jumlahHari = count($tanggalEfektif);

        return $anggota->map(function (AnggotaKelas $item) use ($perSiswa, $jumlahHari) {
            $absensi = $perSiswa->get($item->siswa_id, collect());
            $hadir = $absensi->where('status_kehadiran', 'hadir')->count();
            $terlambat = $absensi->where('menit_terlambat', '>', 0);
            $pulangCepat = $absensi->where('menit_pulang_cepat', '>', 0);

            return [
                'anggota_kelas' => $item, 'hari_efektif' => $jumlahHari, 'hadir' => $hadir,
                'izin' => $absensi->where('status_kehadiran', 'izin')->count(),
                'sakit' => $absensi->where('status_kehadiran', 'sakit')->count(),
                'alfa' => $absensi->where('status_kehadiran', 'alfa')->count() + max(0, $jumlahHari - $absensi->count()),
                'terlambat' => $terlambat->count(), 'menit_terlambat' => (int) round((float) $terlambat->sum('menit_terlambat')),
                'pulang_cepat' => $pulangCepat->count(), 'menit_pulang_cepat' => (int) round((float) $pulangCepat->sum('menit_pulang_cepat')),
                'persentase_hadir' => $jumlahHari > 0 ? round(($hadir / $jumlahHari) * 100, 1) : 0,
            ];
        });
    }

    private function hitungRingkasan($laporan, int $hari): array
    {
        return [
            'siswa' => $laporan->count(), 'hari_efektif' => $hari, 'hadir' => $laporan->sum('hadir'),
            'izin' => $laporan->sum('izin'), 'sakit' => $laporan->sum('sakit'), 'alfa' => $laporan->sum('alfa'),
            'terlambat' => $laporan->sum('terlambat'), 'menit_terlambat' => (int) round((float) $laporan->sum('menit_terlambat')),
            'pulang_cepat' => $laporan->sum('pulang_cepat'), 'menit_pulang_cepat' => (int) round((float) $laporan->sum('menit_pulang_cepat')),
            'rata_persentase_hadir' => $laporan->count() ? round($laporan->avg('persentase_hadir'), 1) : 0,
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

    private function hariDariTanggal(int $hari): string
    {
        return [1 => 'senin', 2 => 'selasa', 3 => 'rabu', 4 => 'kamis', 5 => 'jumat', 6 => 'sabtu', 7 => 'minggu'][$hari];
    }
}
