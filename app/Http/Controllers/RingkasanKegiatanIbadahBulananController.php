<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\PresensiKegiatanIbadah;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesScanKegiatanIbadah;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RingkasanKegiatanIbadahBulananController extends Controller
{
    public function index(Request $request, AksesScanKegiatanIbadah $akses)
    {
        $data = $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
            'kegiatan_ibadah_id' => ['nullable', 'integer', 'exists:kegiatan_ibadah,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);
        $bulan = Carbon::createFromFormat('Y-m', $data['bulan'] ?? now()->format('Y-m'))->startOfMonth();

        if ($bulan->gt(now()->startOfMonth())) {
            throw ValidationException::withMessages(['bulan' => 'Bulan laporan tidak boleh melewati bulan berjalan.']);
        }

        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();
        abort_unless(
            $akses->dapatMelihatRingkasanBulanan($request->user(), $tahunPelajaran),
            403,
            'Ringkasan bulanan hanya dapat dibuka oleh Guru PAI, guru piket, atau pengelola kesiswaan.',
        );

        $daftarKegiatan = KegiatanIbadah::query()->orderByDesc('aktif')->orderBy('nama')->get();
        $kegiatanId = isset($data['kegiatan_ibadah_id']) && $daftarKegiatan->contains('id', (int) $data['kegiatan_ibadah_id'])
            ? (int) $data['kegiatan_ibadah_id']
            : $daftarKegiatan->firstWhere('aktif', true)?->id;
        $kegiatanDipilih = $daftarKegiatan->firstWhere('id', $kegiatanId);
        $daftarKelas = $tahunPelajaran
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->where('aktif', true)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(['id', 'nama', 'tingkat'])
            : collect();
        $kelasId = isset($data['kelas_id']) && $daftarKelas->contains('id', (int) $data['kelas_id'])
            ? (int) $data['kelas_id']
            : null;
        $kelasDipilih = $daftarKelas->firstWhere('id', $kelasId);

        $anggotaKelas = $tahunPelajaran
            ? AnggotaKelas::query()
                ->with('siswa:id,nama_lengkap,nis,nisn,foto,aktif,jenis_kelamin')
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->where('status_keanggotaan', 'aktif')
                ->whereIn('kelas_id', $daftarKelas->pluck('id'))
                ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
                ->orderBy('kelas_id')
                ->orderByRaw('nomor_absen IS NULL')
                ->orderBy('nomor_absen')
                ->orderBy('id')
                ->get()
            : collect();

        if ($kegiatanDipilih?->khususLakiLaki()) {
            $anggotaKelas = $anggotaKelas
                ->reject(fn (AnggotaKelas $anggota) => $anggota->siswa?->jenis_kelamin === 'P')
                ->values();
        }

        [$tanggalMulai, $tanggalSelesai] = $this->batasPeriode($bulan, $tahunPelajaran);
        $tanggalKegiatan = $this->tanggalKegiatan(
            $tahunPelajaran,
            $kegiatanId,
            $tanggalMulai,
            $tanggalSelesai,
        );
        $presensi = ($tahunPelajaran && $kegiatanId && $tanggalMulai && $tanggalSelesai)
            ? PresensiKegiatanIbadah::query()
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->where('kegiatan_ibadah_id', $kegiatanId)
                ->whereDate('tanggal', '>=', $tanggalMulai->toDateString())
                ->whereDate('tanggal', '<=', $tanggalSelesai->toDateString())
                ->whereIn('anggota_kelas_id', $anggotaKelas->pluck('id'))
                ->get()
            : collect();
        $anggotaPerKelas = $anggotaKelas->groupBy('kelas_id');
        $presensiPerAnggota = $presensi->groupBy('anggota_kelas_id');

        $ringkasanKelas = $daftarKelas->map(function (Kelas $kelas) use ($anggotaPerKelas, $presensiPerAnggota, $tanggalKegiatan) {
            $anggota = $anggotaPerKelas->get($kelas->id, collect());
            $target = $anggota->sum(fn (AnggotaKelas $item) => $this->jumlahTanggalBerlaku($tanggalKegiatan, $item));
            $tercatat = $anggota->sum(fn (AnggotaKelas $item) => $presensiPerAnggota->get($item->id, collect())->count());

            return [
                'kelas' => $kelas,
                'siswa' => $anggota->count(),
                'target' => $target,
                'tercatat' => $tercatat,
                'belum' => max($target - $tercatat, 0),
                'persentase' => $target > 0 ? round(($tercatat / $target) * 100, 1) : 0,
            ];
        });
        $ringkasan = [
            'kelas' => $kelasId ? 1 : $daftarKelas->count(),
            'siswa' => $kelasId
                ? $anggotaPerKelas->get($kelasId, collect())->count()
                : $anggotaKelas->count(),
            'hari_kegiatan' => $tanggalKegiatan->count(),
            'target' => $kelasId
                ? (int) ($ringkasanKelas->firstWhere('kelas.id', $kelasId)['target'] ?? 0)
                : $ringkasanKelas->sum('target'),
            'tercatat' => $kelasId
                ? (int) ($ringkasanKelas->firstWhere('kelas.id', $kelasId)['tercatat'] ?? 0)
                : $ringkasanKelas->sum('tercatat'),
        ];
        $ringkasan['belum'] = max($ringkasan['target'] - $ringkasan['tercatat'], 0);
        $ringkasan['persentase'] = $ringkasan['target'] > 0
            ? round(($ringkasan['tercatat'] / $ringkasan['target']) * 100, 1)
            : 0;

        $detailSiswa = collect();

        if ($kelasId) {
            $detailSiswa = $anggotaPerKelas->get($kelasId, collect())->map(function (AnggotaKelas $anggota) use ($presensiPerAnggota, $tanggalKegiatan) {
                $daftarPresensi = $presensiPerAnggota->get($anggota->id, collect())->sortBy('tanggal');
                $target = $this->jumlahTanggalBerlaku($tanggalKegiatan, $anggota);
                $tercatat = $daftarPresensi->count();

                return [
                    'anggota' => $anggota,
                    'target' => $target,
                    'tercatat' => $tercatat,
                    'belum' => max($target - $tercatat, 0),
                    'manual' => $daftarPresensi->where('sumber', 'manual')->count(),
                    'terakhir' => $daftarPresensi->last()?->tanggal,
                    'persentase' => $target > 0 ? round(($tercatat / $target) * 100, 1) : 0,
                ];
            })->values();
        }

        return view('rekap-kegiatan-ibadah.bulanan', [
            'bulan' => $bulan->format('Y-m'),
            'bulanLabel' => $bulan->locale('id')->translatedFormat('F Y'),
            'bulanMinimum' => $tahunPelajaran?->tanggal_mulai?->format('Y-m'),
            'bulanMaksimum' => now()->format('Y-m'),
            'tahunPelajaran' => $tahunPelajaran,
            'daftarKegiatan' => $daftarKegiatan,
            'kegiatanId' => $kegiatanId,
            'kegiatanDipilih' => $kegiatanDipilih,
            'daftarKelas' => $daftarKelas,
            'kelasId' => $kelasId,
            'kelasDipilih' => $kelasDipilih,
            'tanggalKegiatan' => $tanggalKegiatan,
            'ringkasanKelas' => $ringkasanKelas,
            'ringkasan' => $ringkasan,
            'detailSiswa' => $detailSiswa,
        ]);
    }

    private function batasPeriode(Carbon $bulan, ?TahunPelajaran $tahunPelajaran): array
    {
        if (! $tahunPelajaran) {
            return [null, null];
        }

        $mulai = $bulan->copy()->startOfMonth()->max($tahunPelajaran->tanggal_mulai->copy()->startOfDay());
        $selesai = $bulan->copy()->endOfMonth()
            ->min($tahunPelajaran->tanggal_selesai->copy()->endOfDay())
            ->min(now()->endOfDay());

        return $mulai->gt($selesai) ? [null, null] : [$mulai, $selesai];
    }

    private function tanggalKegiatan(
        ?TahunPelajaran $tahunPelajaran,
        ?int $kegiatanId,
        ?Carbon $mulai,
        ?Carbon $selesai,
    ): Collection {
        if (! $tahunPelajaran || ! $kegiatanId || ! $mulai || ! $selesai) {
            return collect();
        }

        $hariAktif = JadwalKegiatanIbadah::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('kegiatan_ibadah_id', $kegiatanId)
            ->where('aktif', true)
            ->pluck('hari')
            ->flip();
        $tanggal = collect();

        foreach (CarbonPeriod::create($mulai, $selesai) as $item) {
            $hari = array_keys(JadwalKegiatanIbadah::DAFTAR_HARI)[$item->dayOfWeekIso - 1] ?? 'minggu';

            if ($hariAktif->has($hari)) {
                $tanggal->push($item->toDateString());
            }
        }

        PresensiKegiatanIbadah::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('kegiatan_ibadah_id', $kegiatanId)
            ->whereDate('tanggal', '>=', $mulai->toDateString())
            ->whereDate('tanggal', '<=', $selesai->toDateString())
            ->distinct()
            ->pluck('tanggal')
            ->each(fn ($item) => $tanggal->push(Carbon::parse($item)->toDateString()));

        return $tanggal->unique()->sort()->values();
    }

    private function jumlahTanggalBerlaku(Collection $tanggalKegiatan, AnggotaKelas $anggota): int
    {
        $tanggalMasuk = $anggota->tanggal_masuk?->toDateString();
        $tanggalKeluar = $anggota->tanggal_keluar?->toDateString();

        return $tanggalKegiatan->filter(fn (string $tanggal) => (! $tanggalMasuk || $tanggal >= $tanggalMasuk)
            && (! $tanggalKeluar || $tanggal <= $tanggalKeluar)
        )->count();
    }
}
