<?php

namespace App\Http\Controllers;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\PresensiBerhalanganIbadah;
use App\Models\PresensiKegiatanIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PresensiAnakController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'tab' => ['nullable', Rule::in(['sekolah', 'ibadah'])],
            'bulan' => ['nullable', 'date_format:Y-m'],
        ]);
        $tab = $data['tab'] ?? 'sekolah';
        $bulan = Carbon::createFromFormat('Y-m', $data['bulan'] ?? now()->format('Y-m'))->startOfMonth();

        if ($bulan->gt(now()->startOfMonth())) {
            throw ValidationException::withMessages([
                'bulan' => 'Bulan presensi tidak boleh melewati bulan berjalan.',
            ]);
        }

        [$orangTua, $siswa] = $this->orangTuaDanSiswa($request->user());
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();
        $anggotaKelas = $this->anggotaKelasAktif($siswa, $tahunPelajaran);
        [$tanggalMulai, $tanggalSelesai] = $this->rentangBerlaku(
            $bulan,
            $tahunPelajaran,
            $anggotaKelas,
        );

        [$riwayatSekolah, $ringkasanSekolah] = $this->dataPresensiSekolah(
            $siswa,
            $tahunPelajaran,
            $tanggalMulai,
            $tanggalSelesai,
        );
        [$riwayatIbadah, $ringkasanIbadah] = $this->dataPresensiIbadah(
            $siswa,
            $tahunPelajaran,
            $tanggalMulai,
            $tanggalSelesai,
        );

        return view('presensi-anak.index', [
            'orangTua' => $orangTua,
            'siswa' => $siswa,
            'tahunPelajaran' => $tahunPelajaran,
            'anggotaKelas' => $anggotaKelas,
            'tab' => $tab,
            'bulan' => $bulan->format('Y-m'),
            'bulanLabel' => $bulan->locale('id')->translatedFormat('F Y'),
            'bulanMinimum' => $tahunPelajaran?->tanggal_mulai?->format('Y-m'),
            'bulanMaksimum' => now()->format('Y-m'),
            'riwayatSekolah' => $riwayatSekolah,
            'ringkasanSekolah' => $ringkasanSekolah,
            'riwayatIbadah' => $riwayatIbadah,
            'ringkasanIbadah' => $ringkasanIbadah,
        ]);
    }

    private function orangTuaDanSiswa(?Pengguna $pengguna): array
    {
        abort_unless($pengguna?->akunOrangTua() || $pengguna?->memilikiPeran('orang_tua'), 403);

        $orangTua = $pengguna->orangTuaWali()
            ->with(['siswa' => fn ($query) => $query->orderBy('nama_lengkap')])
            ->first();
        $siswa = $orangTua?->siswa
            ->firstWhere('id', $orangTua->siswa_acuan_username_id)
            ?: $orangTua?->siswa->first();

        return [$orangTua, $siswa];
    }

    private function anggotaKelasAktif(?Siswa $siswa, ?TahunPelajaran $tahunPelajaran): ?AnggotaKelas
    {
        if (! $siswa) {
            return null;
        }

        $query = AnggotaKelas::query()
            ->with('kelas:id,nama,tingkat')
            ->where('siswa_id', $siswa->id)
            ->where('status_keanggotaan', 'aktif');

        if ($tahunPelajaran) {
            $anggota = (clone $query)
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->latest('id')
                ->first();

            if ($anggota) {
                return $anggota;
            }
        }

        return $query->latest('tahun_pelajaran_id')->latest('id')->first();
    }

    private function rentangBerlaku(
        Carbon $bulan,
        ?TahunPelajaran $tahunPelajaran,
        ?AnggotaKelas $anggotaKelas,
    ): array {
        if (! $tahunPelajaran || ! $anggotaKelas) {
            return [null, null];
        }

        $mulai = $bulan->copy()->startOfMonth()
            ->max($tahunPelajaran->tanggal_mulai->copy()->startOfDay());
        $selesai = $bulan->copy()->endOfMonth()
            ->min($tahunPelajaran->tanggal_selesai->copy()->endOfDay())
            ->min(now()->endOfDay());

        if ($anggotaKelas->tanggal_masuk) {
            $mulai = $mulai->max($anggotaKelas->tanggal_masuk->copy()->startOfDay());
        }

        if ($anggotaKelas->tanggal_keluar) {
            $selesai = $selesai->min($anggotaKelas->tanggal_keluar->copy()->endOfDay());
        }

        return $mulai->gt($selesai) ? [null, null] : [$mulai, $selesai];
    }

    private function dataPresensiSekolah(
        ?Siswa $siswa,
        ?TahunPelajaran $tahunPelajaran,
        ?Carbon $tanggalMulai,
        ?Carbon $tanggalSelesai,
    ): array {
        $ringkasanKosong = [
            'hari_terjadwal' => 0,
            'hadir' => 0,
            'sakit' => 0,
            'izin' => 0,
            'alfa' => 0,
            'belum_tercatat' => 0,
            'terlambat' => 0,
            'menit_terlambat' => 0,
            'pulang_cepat' => 0,
        ];

        if (! $siswa || ! $tahunPelajaran || ! $tanggalMulai || ! $tanggalSelesai) {
            return [collect(), $ringkasanKosong];
        }

        $absensi = AbsensiSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->whereDate('tanggal', '>=', $tanggalMulai->toDateString())
            ->whereDate('tanggal', '<=', $tanggalSelesai->toDateString())
            ->orderBy('tanggal')
            ->get()
            ->keyBy(fn (AbsensiSiswa $item) => $item->tanggal->toDateString());
        $hariAktif = PengaturanAbsensi::query()
            ->where('aktif', true)
            ->pluck('hari')
            ->flip();
        $tanggalTerjadwal = collect();

        if ($hariAktif->isNotEmpty()) {
            foreach (CarbonPeriod::create($tanggalMulai, $tanggalSelesai) as $tanggal) {
                if ($hariAktif->has($this->kodeHari($tanggal->isoWeekday()))) {
                    $tanggalTerjadwal->push($tanggal->toDateString());
                }
            }
        }

        $absensi->keys()->each(fn (string $tanggal) => $tanggalTerjadwal->push($tanggal));

        $riwayat = $tanggalTerjadwal
            ->unique()
            ->sortDesc()
            ->map(function (string $tanggal) use ($absensi) {
                $catatan = $absensi->get($tanggal);
                $status = $catatan?->status_kehadiran ?: 'belum_tercatat';

                return [
                    'tanggal' => Carbon::parse($tanggal),
                    'absensi' => $catatan,
                    'status' => $status,
                    'label_status' => $this->labelStatusSekolah($status),
                ];
            })
            ->values();

        $ringkasan = [
            'hari_terjadwal' => $riwayat->count(),
            'hadir' => $riwayat->where('status', 'hadir')->count(),
            'sakit' => $riwayat->where('status', 'sakit')->count(),
            'izin' => $riwayat->where('status', 'izin')->count(),
            'alfa' => $riwayat->where('status', 'alfa')->count(),
            'belum_tercatat' => $riwayat->where('status', 'belum_tercatat')->count(),
            'terlambat' => $absensi->where('menit_terlambat', '>', 0)->count(),
            'menit_terlambat' => (int) $absensi->sum('menit_terlambat'),
            'pulang_cepat' => $absensi->where('menit_pulang_cepat', '>', 0)->count(),
        ];

        return [$riwayat, $ringkasan];
    }

    private function dataPresensiIbadah(
        ?Siswa $siswa,
        ?TahunPelajaran $tahunPelajaran,
        ?Carbon $tanggalMulai,
        ?Carbon $tanggalSelesai,
    ): array {
        $ringkasanKosong = [
            'kegiatan_terjadwal' => 0,
            'tercatat' => 0,
            'berhalangan' => 0,
            'belum_tercatat' => 0,
        ];

        if (! $siswa || ! $tahunPelajaran || ! $tanggalMulai || ! $tanggalSelesai) {
            return [collect(), $ringkasanKosong];
        }

        $jadwalPerHari = JadwalKegiatanIbadah::query()
            ->with('kegiatanIbadah:id,nama,kode,aktif')
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('aktif', true)
            ->whereHas('kegiatanIbadah', fn ($query) => $query->where('aktif', true))
            ->orderBy('urutan_hari')
            ->get()
            ->groupBy('hari');
        $presensi = PresensiKegiatanIbadah::query()
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->whereDate('tanggal', '>=', $tanggalMulai->toDateString())
            ->whereDate('tanggal', '<=', $tanggalSelesai->toDateString())
            ->get()
            ->keyBy(fn (PresensiKegiatanIbadah $item) => $this->kunciIbadah($item->tanggal, $item->kegiatan_ibadah_id));
        $presensiBerhalangan = PresensiBerhalanganIbadah::query()
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->whereDate('tanggal', '>=', $tanggalMulai->toDateString())
            ->whereDate('tanggal', '<=', $tanggalSelesai->toDateString())
            ->get()
            ->keyBy(fn (PresensiBerhalanganIbadah $item) => $this->kunciIbadah($item->tanggal, $item->kegiatan_ibadah_id));
        $riwayat = collect();

        foreach (CarbonPeriod::create($tanggalMulai, $tanggalSelesai) as $tanggal) {
            foreach ($jadwalPerHari->get($this->kodeHari($tanggal->isoWeekday()), collect()) as $jadwal) {
                $kunci = $this->kunciIbadah($tanggal, $jadwal->kegiatan_ibadah_id);
                $catatan = $presensi->get($kunci);
                $catatanBerhalangan = $presensiBerhalangan->get($kunci);
                $status = $catatan ? 'tercatat' : ($catatanBerhalangan ? 'berhalangan' : 'belum_tercatat');

                $riwayat->push([
                    'tanggal' => $tanggal->copy(),
                    'jadwal' => $jadwal,
                    'presensi' => $catatan,
                    'presensi_berhalangan' => $catatanBerhalangan,
                    'status' => $status,
                    'label_status' => match ($status) {
                        'tercatat' => 'Tercatat',
                        'berhalangan' => 'Berhalangan',
                        default => 'Belum tercatat',
                    },
                ]);
            }
        }

        $riwayat = $riwayat
            ->sortByDesc(fn (array $item) => $item['tanggal']->timestamp)
            ->values();
        $ringkasan = [
            'kegiatan_terjadwal' => $riwayat->count(),
            'tercatat' => $riwayat->where('status', 'tercatat')->count(),
            'berhalangan' => $riwayat->where('status', 'berhalangan')->count(),
            'belum_tercatat' => $riwayat->where('status', 'belum_tercatat')->count(),
        ];

        return [$riwayat, $ringkasan];
    }

    private function kodeHari(int $isoWeekday): string
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

    private function kunciIbadah($tanggal, int $kegiatanIbadahId): string
    {
        return Carbon::parse($tanggal)->toDateString().'|'.$kegiatanIbadahId;
    }

    private function labelStatusSekolah(string $status): string
    {
        return match ($status) {
            'hadir' => 'Hadir',
            'sakit' => 'Sakit',
            'izin' => 'Izin',
            'alfa' => 'Alfa',
            default => 'Belum tercatat',
        };
    }
}
