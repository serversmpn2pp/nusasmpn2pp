<?php

namespace App\Services\Mobile;

use App\Models\KonfirmasiBerhalanganIbadah;
use App\Models\Pengguna;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesBerhalanganIbadah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class KonfirmasiBerhalanganIbadahMobileService
{
    public function __construct(private AksesBerhalanganIbadah $akses) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $tahunPelajaran = $this->tahunPelajaranAktif();
        $this->pastikanDapatMengonfirmasi($pengguna, $tahunPelajaran);

        $daftarKelas = $this->akses->kelasTercakup($pengguna, $tahunPelajaran);
        $kelasId = filled($filter['kelas_id'] ?? null) ? (int) $filter['kelas_id'] : null;
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $halaman = (int) ($filter['halaman'] ?? 1);

        if ($kelasId && ! $daftarKelas->contains('id', $kelasId)) {
            abort(403, 'Kelas berada di luar cakupan pendampingan Anda.');
        }

        $dasarPeriode = PeriodeBerhalanganIbadah::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id);
        $this->akses->batasiPeriodeSesuaiCakupan($dasarPeriode, $pengguna, $tahunPelajaran);

        $paginator = (clone $dasarPeriode)
            ->with(['siswa:id,nama_lengkap,nisn,foto', 'kelas:id,nama'])
            ->withCount('presensiHarian')
            ->where('status', PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI)
            ->when($kelasId, fn (Builder $query) => $query->where('kelas_id', $kelasId))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->whereHas('siswa', fn (Builder $query) => $query
                    ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                    ->orWhere('nisn', 'like', "%{$kataKunci}%"));
            })
            ->orderBy('perlu_konfirmasi_sejak')
            ->orderBy('id')
            ->paginate(12, ['*'], 'halaman', $halaman);

        return [
            'mode_privat' => true,
            'pesan_privasi' => 'Lakukan percakapan secara pribadi tanpa pemeriksaan fisik. Catatan hanya boleh berisi informasi seperlunya.',
            'tahun_pelajaran' => [
                'id' => (int) $tahunPelajaran->id,
                'nama' => $tahunPelajaran->nama,
            ],
            'ringkasan' => [
                'perlu_konfirmasi' => (clone $dasarPeriode)
                    ->where('status', PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI)
                    ->count(),
                'dipantau' => (clone $dasarPeriode)
                    ->where('status', PeriodeBerhalanganIbadah::STATUS_AKTIF)
                    ->count(),
                'selesai_bulan_ini' => (clone $dasarPeriode)
                    ->where('status', PeriodeBerhalanganIbadah::STATUS_SELESAI)
                    ->whereBetween('tanggal_selesai', [
                        now()->startOfMonth()->toDateString(),
                        now()->endOfMonth()->toDateString(),
                    ])
                    ->count(),
            ],
            'filter' => [
                'kelas_id' => $kelasId,
                'cari' => $kataKunci,
            ],
            'referensi' => [
                'kelas' => $daftarKelas->map(fn ($kelas) => [
                    'id' => (int) $kelas->id,
                    'nama' => $kelas->nama,
                ])->values(),
            ],
            'items' => collect($paginator->items())
                ->map(fn (PeriodeBerhalanganIbadah $periode) => $this->ringkasPeriode($periode))
                ->values(),
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function detail(Pengguna $pengguna, PeriodeBerhalanganIbadah $periode): array
    {
        $tahunPelajaran = $this->tahunPelajaranAktif();
        $this->pastikanDapatMengaksesPeriode($pengguna, $periode, $tahunPelajaran);

        $periode->load([
            'siswa:id,nama_lengkap,nisn,foto',
            'kelas:id,nama',
            'presensiHarian' => fn ($query) => $query
                ->with('kegiatanIbadah:id,nama')
                ->latest('tanggal')
                ->latest('waktu_scan'),
            'riwayatKonfirmasi' => fn ($query) => $query
                ->with('dikonfirmasiOlehPengguna:id,nama')
                ->latest('dikonfirmasi_pada'),
        ]);

        return [
            'mode_privat' => true,
            'pesan_privasi' => 'Percakapan privat, bukan pemeriksaan. Hindari mencatat detail medis atau informasi yang tidak diperlukan.',
            'dapat_dikonfirmasi' => $periode->status === PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI,
            'jeda_awal_hari' => min(3, max(1, (int) $periode->batas_hari_konfirmasi)),
            'periode' => [
                'id' => (int) $periode->id,
                'status' => $periode->status,
                'status_label' => $this->labelStatus($periode->status),
                'tanggal_mulai' => $periode->tanggal_mulai?->toDateString(),
                'tanggal_mulai_label' => $periode->tanggal_mulai?->locale('id')->translatedFormat('d M Y'),
                'tanggal_selesai' => $periode->tanggal_selesai?->toDateString(),
                'hari_ke' => $periode->tanggal_mulai
                    ? (int) $periode->tanggal_mulai->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1
                    : null,
                'batas_hari_konfirmasi' => (int) $periode->batas_hari_konfirmasi,
                'perlu_konfirmasi_sejak' => $periode->perlu_konfirmasi_sejak?->toDateString(),
                'konfirmasi_berikutnya_pada' => $periode->konfirmasi_berikutnya_pada?->toDateString(),
                'catatan_privat_awal' => $periode->catatan_privat,
                'siswa' => [
                    'nama_lengkap' => $periode->siswa?->nama_lengkap,
                    'nisn' => $periode->siswa?->nisn,
                    'foto_url' => $this->fotoUrl($periode->siswa?->foto),
                ],
                'kelas' => [
                    'id' => $periode->kelas?->id ? (int) $periode->kelas->id : null,
                    'nama' => $periode->kelas?->nama,
                ],
            ],
            'presensi_harian' => $periode->presensiHarian->map(fn ($presensi) => [
                'id' => (int) $presensi->id,
                'tanggal' => $presensi->tanggal?->toDateString(),
                'tanggal_label' => $presensi->tanggal?->locale('id')->translatedFormat('d M Y'),
                'waktu_scan' => substr((string) $presensi->waktu_scan, 0, 8),
                'kegiatan' => $presensi->kegiatanIbadah?->nama,
            ])->values(),
            'riwayat_konfirmasi' => $periode->riwayatKonfirmasi->map(fn (KonfirmasiBerhalanganIbadah $konfirmasi) => [
                'id' => (int) $konfirmasi->id,
                'hasil' => $konfirmasi->hasil,
                'hasil_label' => $konfirmasi->labelHasil(),
                'dikonfirmasi_pada' => $konfirmasi->dikonfirmasi_pada?->toIso8601String(),
                'dikonfirmasi_pada_label' => $konfirmasi->dikonfirmasi_pada?->locale('id')->translatedFormat('d M Y, H:i'),
                'dikonfirmasi_oleh' => $konfirmasi->dikonfirmasiOlehPengguna?->nama,
                'konfirmasi_berikutnya_pada' => $konfirmasi->konfirmasi_berikutnya_pada?->toDateString(),
                'catatan_privat' => $konfirmasi->catatan_privat,
            ])->values(),
        ];
    }

    public function pastikanDapatMengaksesPeriode(
        Pengguna $pengguna,
        PeriodeBerhalanganIbadah $periode,
        ?TahunPelajaran $tahunPelajaran = null,
    ): void {
        $tahunPelajaran ??= $this->tahunPelajaranAktif();
        abort_unless(
            (int) $periode->tahun_pelajaran_id === (int) $tahunPelajaran->id
                && $periode->kelas_id
                && $this->akses->dapatMengonfirmasiKelas($pengguna, $tahunPelajaran, (int) $periode->kelas_id),
            403,
            'Data berada di luar cakupan pendampingan Anda.',
        );
    }

    private function ringkasPeriode(PeriodeBerhalanganIbadah $periode): array
    {
        return [
            'id' => (int) $periode->id,
            'tanggal_mulai' => $periode->tanggal_mulai?->toDateString(),
            'tanggal_mulai_label' => $periode->tanggal_mulai?->locale('id')->translatedFormat('d M Y'),
            'perlu_konfirmasi_sejak' => $periode->perlu_konfirmasi_sejak?->toDateString(),
            'hari_ke' => $periode->tanggal_mulai
                ? (int) $periode->tanggal_mulai->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1
                : null,
            'jumlah_presensi' => (int) ($periode->presensi_harian_count ?? 0),
            'siswa' => [
                'nama_lengkap' => $periode->siswa?->nama_lengkap,
                'nisn' => $periode->siswa?->nisn,
                'foto_url' => $this->fotoUrl($periode->siswa?->foto),
            ],
            'kelas' => [
                'id' => $periode->kelas?->id ? (int) $periode->kelas->id : null,
                'nama' => $periode->kelas?->nama,
            ],
        ];
    }

    private function pastikanDapatMengonfirmasi(Pengguna $pengguna, TahunPelajaran $tahunPelajaran): void
    {
        abort_unless(
            $this->akses->dapatMengonfirmasi($pengguna, $tahunPelajaran),
            403,
            'Halaman privat ini hanya dapat dibuka oleh pendamping ibadah siswi yang ditugaskan.',
        );
    }

    private function tahunPelajaranAktif(): TahunPelajaran
    {
        return TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->firstOrFail();
    }

    private function labelStatus(string $status): string
    {
        return match ($status) {
            PeriodeBerhalanganIbadah::STATUS_AKTIF => 'Dipantau',
            PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI => 'Perlu konfirmasi',
            PeriodeBerhalanganIbadah::STATUS_SELESAI => 'Selesai',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function fotoUrl(?string $foto): ?string
    {
        return $foto && Storage::disk('public')->exists($foto)
            ? asset('storage/'.$foto)
            : null;
    }
}
