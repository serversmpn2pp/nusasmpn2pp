<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\OrangTuaWali;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PembinaanPoinAnakMobileService
{
    public function __construct(private ProgressKasusSiswaMobileService $progressKasus) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $orangTua = $this->orangTua($pengguna);
        $pilihanSiswa = $orangTua?->siswa ?? collect();
        $siswa = $this->pilihSiswa(
            $orangTua,
            $pilihanSiswa,
            isset($filter['siswa_id']) ? (int) $filter['siswa_id'] : null,
        );
        $tab = $filter['tab'] ?? 'laporan';
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 10);
        $tahunPelajaran = $siswa ? $this->tahunPelajaran($siswa) : collect();
        $tahunDipilih = $this->pilihTahunPelajaran(
            $tahunPelajaran,
            isset($filter['tahun_pelajaran_id']) ? (int) $filter['tahun_pelajaran_id'] : null,
        );
        $anggotaKelas = $siswa && $tahunDipilih
            ? $this->anggotaKelas($siswa, $tahunDipilih)
            : null;

        if (! $siswa) {
            return $this->kosong($tab, $halaman, $perHalaman);
        }

        $queryLaporan = LaporanPembinaanSiswa::query()
            ->with(['kelas:id,nama', 'tahunPelajaran:id,nama'])
            ->where('siswa_id', $siswa->id)
            ->when($tahunDipilih, fn ($query) => $query->where('tahun_pelajaran_id', $tahunDipilih->id));
        $queryPoin = TransaksiPoinSiswa::query()
            ->with([
                'laporanPembinaanSiswa:id,nomor_laporan',
                'penguranganPoinSiswa:id,jenis_kegiatan',
            ])
            ->where('siswa_id', $siswa->id)
            ->when($tahunDipilih, fn ($query) => $query->where('tahun_pelajaran_id', $tahunDipilih->id));
        $ringkasan = $this->ringkasan(clone $queryLaporan, clone $queryPoin);
        $laporan = [];
        $poin = [];

        if ($tab === 'poin') {
            $paginator = $queryPoin
                ->latest('tercatat_pada')
                ->latest('id')
                ->paginate($perHalaman, ['*'], 'halaman', $halaman);
            $poin = $paginator->getCollection()
                ->map(fn (TransaksiPoinSiswa $item) => $this->ringkasPoin($item))
                ->values();
        } else {
            $paginator = $queryLaporan
                ->latest('tanggal_kejadian')
                ->latest('id')
                ->paginate($perHalaman, ['*'], 'halaman', $halaman);
            $laporan = $paginator->getCollection()
                ->map(fn (LaporanPembinaanSiswa $item) => $this->progressKasus->ringkasLaporan($item))
                ->values();
        }

        return [
            'mode' => 'orang_tua',
            'siswa' => $this->ringkasSiswa($siswa),
            'pilihan_siswa' => $pilihanSiswa
                ->map(fn (Siswa $item) => $this->ringkasSiswa($item))
                ->values(),
            'tahun_pelajaran' => $tahunPelajaran
                ->map(fn (TahunPelajaran $item) => $this->ringkasTahun($item))
                ->values(),
            'tahun_pelajaran_dipilih' => $tahunDipilih
                ? $this->ringkasTahun($tahunDipilih)
                : null,
            'kelas' => $anggotaKelas?->kelas ? [
                'id' => (int) $anggotaKelas->kelas->id,
                'nama' => $anggotaKelas->kelas->nama,
                'tingkat' => (int) $anggotaKelas->kelas->tingkat,
            ] : null,
            'filter' => [
                'tab' => $tab,
                'siswa_id' => (int) $siswa->id,
                'tahun_pelajaran_id' => $tahunDipilih ? (int) $tahunDipilih->id : null,
            ],
            'ringkasan' => $ringkasan,
            'laporan' => $laporan,
            'riwayat_poin' => $poin,
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
            'privasi' => 'Halaman ini hanya menampilkan perkembangan yang dapat diketahui orang tua. Catatan pemeriksaan internal tetap dikelola oleh sekolah.',
        ];
    }

    public function detail(Pengguna $pengguna, LaporanPembinaanSiswa $laporan): array
    {
        $orangTua = $this->orangTua($pengguna);
        $siswa = $orangTua?->siswa->firstWhere('id', $laporan->siswa_id);
        abort_unless($siswa, 404);

        return $this->progressKasus->detailUntukSiswa(
            $siswa,
            $laporan,
            'Rincian pemeriksaan internal dikelola oleh sekolah dan tidak ditampilkan pada akun orang tua.',
        );
    }

    private function orangTua(Pengguna $pengguna): ?OrangTuaWali
    {
        abort_unless($pengguna->akunOrangTua(), 403);

        return $pengguna->orangTuaWali()
            ->with(['siswa' => fn ($query) => $query->orderBy('nama_lengkap')])
            ->first();
    }

    private function pilihSiswa(
        ?OrangTuaWali $orangTua,
        Collection $pilihan,
        ?int $siswaId,
    ): ?Siswa {
        if (! $orangTua || $pilihan->isEmpty()) {
            abort_if($siswaId !== null, 403);

            return null;
        }

        $siswa = $siswaId !== null
            ? $pilihan->firstWhere('id', $siswaId)
            : ($pilihan->firstWhere('id', $orangTua->siswa_acuan_username_id) ?: $pilihan->first());
        abort_unless($siswa, 403);

        return $siswa;
    }

    private function tahunPelajaran(Siswa $siswa): Collection
    {
        $ids = AnggotaKelas::query()
            ->where('siswa_id', $siswa->id)
            ->pluck('tahun_pelajaran_id')
            ->merge(LaporanPembinaanSiswa::query()
                ->where('siswa_id', $siswa->id)
                ->pluck('tahun_pelajaran_id'))
            ->merge(TransaksiPoinSiswa::query()
                ->where('siswa_id', $siswa->id)
                ->pluck('tahun_pelajaran_id'))
            ->filter()
            ->unique();

        return TahunPelajaran::query()
            ->whereIn('id', $ids)
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'nama', 'aktif', 'tanggal_mulai']);
    }

    private function pilihTahunPelajaran(Collection $pilihan, ?int $tahunId): ?TahunPelajaran
    {
        if ($tahunId !== null) {
            $tahun = $pilihan->firstWhere('id', $tahunId);
            abort_unless($tahun, 403);

            return $tahun;
        }

        return $pilihan->firstWhere('aktif', true) ?: $pilihan->first();
    }

    private function anggotaKelas(Siswa $siswa, TahunPelajaran $tahun): ?AnggotaKelas
    {
        return AnggotaKelas::query()
            ->with('kelas:id,nama,tingkat')
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahun->id)
            ->orderByRaw("CASE WHEN status_keanggotaan = 'aktif' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();
    }

    private function ringkasan(Builder $queryLaporan, Builder $queryPoin): array
    {
        $statusDiproses = [
            'diajukan',
            'pemeriksaan_bk',
            'perlu_klarifikasi',
            'dikembalikan_bk',
            'menunggu_pengesahan_wakil',
            'menunggu_persetujuan',
            'disetujui_sebagian',
            'perlu_musyawarah',
        ];

        return [
            'laporan' => (clone $queryLaporan)->count(),
            'diproses' => (clone $queryLaporan)
                ->where(function ($query) use ($statusDiproses) {
                    $query->whereIn('status_verifikasi', $statusDiproses)
                        ->orWhere(function ($query) {
                            $query->where('status_verifikasi', 'tidak_perlu')
                                ->whereNotIn('status', ['selesai', 'dibatalkan']);
                        });
                })
                ->count(),
            'poin_pelanggaran' => (int) (clone $queryPoin)
                ->where('jenis', 'pelanggaran')
                ->sum('poin'),
            'pengurangan' => abs((int) (clone $queryPoin)
                ->where('jenis', 'pengurangan')
                ->sum('poin')),
            'saldo' => (int) (clone $queryPoin)->sum('poin'),
        ];
    }

    private function ringkasPoin(TransaksiPoinSiswa $item): array
    {
        $pengurangan = $item->jenis === 'pengurangan' || $item->poin < 0;
        $sumber = $item->laporanPembinaanSiswa?->nomor_laporan
            ?: $item->penguranganPoinSiswa?->jenis_kegiatan;

        return [
            'id' => (int) $item->id,
            'jenis' => $item->jenis,
            'label_jenis' => $pengurangan ? 'Reward / pengurangan poin' : 'Poin pelanggaran resmi',
            'poin' => (int) $item->poin,
            'keterangan' => $item->keterangan ?: ($pengurangan ? 'Pengurangan poin' : 'Pelanggaran berpoin'),
            'tercatat_pada' => $item->tercatat_pada?->toISOString(),
            'sumber' => $sumber,
            'laporan_id' => $item->laporan_pembinaan_siswa_id
                ? (int) $item->laporan_pembinaan_siswa_id
                : null,
        ];
    }

    private function ringkasSiswa(Siswa $siswa): array
    {
        return [
            'id' => (int) $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'nis' => $siswa->nis,
            'nisn' => $siswa->nisn,
        ];
    }

    private function ringkasTahun(TahunPelajaran $tahun): array
    {
        return [
            'id' => (int) $tahun->id,
            'nama' => $tahun->nama,
            'aktif' => (bool) $tahun->aktif,
        ];
    }

    private function kosong(string $tab, int $halaman, int $perHalaman): array
    {
        return [
            'mode' => 'orang_tua',
            'siswa' => null,
            'pilihan_siswa' => [],
            'tahun_pelajaran' => [],
            'tahun_pelajaran_dipilih' => null,
            'kelas' => null,
            'filter' => [
                'tab' => $tab,
                'siswa_id' => null,
                'tahun_pelajaran_id' => null,
            ],
            'ringkasan' => [
                'laporan' => 0,
                'diproses' => 0,
                'poin_pelanggaran' => 0,
                'pengurangan' => 0,
                'saldo' => 0,
            ],
            'laporan' => [],
            'riwayat_poin' => [],
            'paginasi' => [
                'halaman' => $halaman,
                'per_halaman' => $perHalaman,
                'total' => 0,
                'ada_halaman_berikutnya' => false,
            ],
            'privasi' => 'Hubungi administrator sekolah agar akun orang tua dihubungkan dengan data anak yang benar.',
        ];
    }
}
