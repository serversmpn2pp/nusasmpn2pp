<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\PresensiKegiatanIbadah;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesScanKegiatanIbadah;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RingkasanKegiatanIbadahBulananMobileService
{
    public function __construct(private AksesScanKegiatanIbadah $akses) {}

    public function ringkasan(Pengguna $pengguna, array $filter): array
    {
        $bulan = Carbon::createFromFormat('Y-m', $filter['bulan'] ?? now()->format('Y-m'))->startOfMonth();

        if ($bulan->gt(now()->startOfMonth())) {
            throw ValidationException::withMessages([
                'bulan' => 'Bulan laporan tidak boleh melewati bulan berjalan.',
            ]);
        }

        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();
        abort_unless(
            $this->akses->dapatMelihatRingkasanBulanan($pengguna, $tahunPelajaran),
            403,
            'Ringkasan bulanan hanya dapat dibuka oleh guru PAI, guru piket, atau pengelola kesiswaan.',
        );

        $daftarKegiatan = KegiatanIbadah::query()
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->get();
        $kegiatanId = filled($filter['kegiatan_ibadah_id'] ?? null)
            && $daftarKegiatan->contains('id', (int) $filter['kegiatan_ibadah_id'])
                ? (int) $filter['kegiatan_ibadah_id']
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
        $kelasId = filled($filter['kelas_id'] ?? null)
            && $daftarKelas->contains('id', (int) $filter['kelas_id'])
                ? (int) $filter['kelas_id']
                : null;
        $kelasDipilih = $daftarKelas->firstWhere('id', $kelasId);

        $anggotaKelas = $tahunPelajaran
            ? AnggotaKelas::query()
                ->with('siswa:id,nama_lengkap,nis,nisn,foto,aktif')
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
                'kelas' => $this->dataKelas($kelas),
                'siswa' => $anggota->count(),
                'target' => $target,
                'tercatat' => $tercatat,
                'belum' => max($target - $tercatat, 0),
                'persentase' => $target > 0 ? round(($tercatat / $target) * 100, 1) : 0,
            ];
        });
        $ringkasanKelasDipilih = $kelasId
            ? $ringkasanKelas->first(fn (array $item) => (int) $item['kelas']['id'] === $kelasId)
            : null;
        $ringkasan = [
            'kelas' => $kelasId ? 1 : $daftarKelas->count(),
            'siswa' => $kelasId
                ? $anggotaPerKelas->get($kelasId, collect())->count()
                : $anggotaKelas->count(),
            'hari_kegiatan' => $tanggalKegiatan->count(),
            'target' => $kelasId
                ? (int) ($ringkasanKelasDipilih['target'] ?? 0)
                : (int) $ringkasanKelas->sum('target'),
            'tercatat' => $kelasId
                ? (int) ($ringkasanKelasDipilih['tercatat'] ?? 0)
                : (int) $ringkasanKelas->sum('tercatat'),
        ];
        $ringkasan['belum'] = max($ringkasan['target'] - $ringkasan['tercatat'], 0);
        $ringkasan['persentase'] = $ringkasan['target'] > 0
            ? round(($ringkasan['tercatat'] / $ringkasan['target']) * 100, 1)
            : 0;

        $detailSiswa = $kelasId
            ? $anggotaPerKelas->get($kelasId, collect())->map(function (AnggotaKelas $anggota) use ($presensiPerAnggota, $tanggalKegiatan) {
                $daftarPresensi = $presensiPerAnggota->get($anggota->id, collect())->sortBy('tanggal');
                $target = $this->jumlahTanggalBerlaku($tanggalKegiatan, $anggota);
                $tercatat = $daftarPresensi->count();
                $terakhir = $daftarPresensi->last()?->tanggal;

                return [
                    'anggota_kelas_id' => (int) $anggota->id,
                    'nomor_absen' => $anggota->nomor_absen ? (int) $anggota->nomor_absen : null,
                    'siswa' => [
                        'id' => (int) $anggota->siswa_id,
                        'nama' => $anggota->siswa?->nama_lengkap,
                        'nis' => $anggota->siswa?->nis,
                        'nisn' => $anggota->siswa?->nisn,
                        'foto_url' => $this->fotoUrl($anggota->siswa?->foto),
                    ],
                    'target' => $target,
                    'tercatat' => $tercatat,
                    'belum' => max($target - $tercatat, 0),
                    'manual' => $daftarPresensi->where('sumber', 'manual')->count(),
                    'terakhir' => $terakhir?->toDateString(),
                    'terakhir_label' => $terakhir?->locale('id')->translatedFormat('d M Y'),
                    'persentase' => $target > 0 ? round(($tercatat / $target) * 100, 1) : 0,
                ];
            })->values()
            : collect();

        return [
            'tersedia' => (bool) ($tahunPelajaran && $kegiatanDipilih),
            'bulan' => $bulan->format('Y-m'),
            'bulan_label' => $bulan->locale('id')->translatedFormat('F Y'),
            'bulan_minimum' => $tahunPelajaran?->tanggal_mulai?->format('Y-m'),
            'bulan_maksimum' => now()->format('Y-m'),
            'tahun_pelajaran' => $tahunPelajaran ? [
                'id' => (int) $tahunPelajaran->id,
                'nama' => $tahunPelajaran->nama,
            ] : null,
            'kegiatan_dipilih' => $kegiatanDipilih ? [
                'id' => (int) $kegiatanDipilih->id,
                'nama' => $kegiatanDipilih->nama,
                'kode' => $kegiatanDipilih->kode,
                'aktif' => (bool) $kegiatanDipilih->aktif,
            ] : null,
            'kelas_dipilih' => $kelasDipilih ? $this->dataKelas($kelasDipilih) : null,
            'referensi' => [
                'kegiatan' => $daftarKegiatan->map(fn (KegiatanIbadah $item) => [
                    'id' => (int) $item->id,
                    'nama' => $item->nama,
                    'aktif' => (bool) $item->aktif,
                ])->values(),
                'kelas' => $daftarKelas->map(fn (Kelas $item) => $this->dataKelas($item))->values(),
            ],
            'tanggal_kegiatan' => $tanggalKegiatan->map(fn (string $tanggal) => [
                'tanggal' => $tanggal,
                'label' => Carbon::parse($tanggal)->locale('id')->translatedFormat('d M'),
            ])->values(),
            'ringkasan' => $ringkasan,
            'ringkasan_kelas' => $ringkasanKelas->values(),
            'items' => $detailSiswa,
            'catatan_perhitungan' => 'Tanggal kegiatan dihitung sampai hari ini. Tanggal libur belum dikecualikan karena kalender akademik libur belum tersedia.',
            'pesan_privasi' => 'Status dan catatan berhalangan tidak ditampilkan pada ringkasan umum dan tetap dikelola melalui ruang privat pendamping.',
        ];
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

    private function dataKelas(Kelas $kelas): array
    {
        return [
            'id' => (int) $kelas->id,
            'nama' => $kelas->nama,
            'tingkat' => (int) $kelas->tingkat,
        ];
    }

    private function fotoUrl(?string $foto): ?string
    {
        return $foto && Storage::disk('public')->exists($foto) ? asset('storage/'.$foto) : null;
    }
}
