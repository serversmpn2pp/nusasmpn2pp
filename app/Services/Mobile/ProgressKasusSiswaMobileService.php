<?php

namespace App\Services\Mobile;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PresentasiProgressKasusService;
use Illuminate\Database\Eloquent\Builder;

class ProgressKasusSiswaMobileService
{
    public function __construct(private PresentasiProgressKasusService $presentasiProgress) {}

    public function daftar(Pengguna $pengguna, int $halaman, int $perHalaman): array
    {
        $siswa = $this->siswaDariPengguna($pengguna);
        $tahunAktif = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first(['id', 'nama']);

        if (! $siswa) {
            return [
                'siswa' => null,
                'tahun_pelajaran_aktif' => $tahunAktif ? $this->tahun($tahunAktif) : null,
                'ringkasan' => $this->ringkasanKosong(),
                'items' => [],
                'paginasi' => $this->paginasiKosong($halaman, $perHalaman),
            ];
        }

        $query = LaporanPembinaanSiswa::query()
            ->with([
                'tahunPelajaran:id,nama',
                'kelas:id,nama',
                'kategoriPembinaanSiswa:id,nama',
            ])
            ->where('siswa_id', $siswa->id);
        $ringkasan = $this->ringkasan(clone $query);
        $paginator = $query
            ->latest('tanggal_kejadian')
            ->latest('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'siswa' => $this->siswa($siswa),
            'tahun_pelajaran_aktif' => $tahunAktif ? $this->tahun($tahunAktif) : null,
            'ringkasan' => $ringkasan,
            'items' => $paginator->getCollection()
                ->map(fn (LaporanPembinaanSiswa $laporan) => $this->ringkasLaporan($laporan))
                ->values(),
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function detail(Pengguna $pengguna, LaporanPembinaanSiswa $laporan): array
    {
        $siswa = $this->siswaDariPengguna($pengguna);
        abort_unless($siswa && (int) $laporan->siswa_id === (int) $siswa->id, 404);

        return $this->detailUntukSiswa(
            $siswa,
            $laporan,
            'Rincian pemeriksaan internal dikelola oleh sekolah dan tidak ditampilkan pada akun siswa.',
        );
    }

    public function detailUntukSiswa(
        Siswa $siswa,
        LaporanPembinaanSiswa $laporan,
        string $pesanPrivasi,
    ): array {

        $laporan->load([
            'tahunPelajaran:id,nama',
            'kelas:id,nama',
            'kategoriPembinaanSiswa:id,nama',
            'butirPelanggaranLaporan' => fn ($query) => $query->orderBy('id'),
            'riwayatProsesPembinaanSiswa' => fn ($query) => $query
                ->oldest('terjadi_pada')
                ->oldest('id'),
            'tindakLanjutPembinaanSiswa' => fn ($query) => $query
                ->oldest('tanggal_tindak_lanjut')
                ->oldest('waktu_tindak_lanjut')
                ->oldest('id'),
        ]);
        $status = $this->presentasiProgress->status($laporan);

        return [
            'siswa' => $this->siswa($siswa),
            'laporan' => array_merge($this->ringkasLaporan($laporan), [
                'jenis_laporan' => $laporan->jenis_laporan,
                'label_jenis_laporan' => $laporan->labelJenisLaporan(),
                'waktu_kejadian' => $laporan->waktuKejadianRingkas(),
                'sumber' => $laporan->berasalDariAbsensi()
                    ? 'Presensi keterlambatan'
                    : 'Laporan pegawai',
                'kronologi' => $laporan->kronologi,
            ]),
            'tahapan' => collect([
                'Laporan diterima',
                'Pemeriksaan BK',
                'Keputusan sekolah',
                'Selesai',
            ])->map(fn (string $label, int $index) => [
                'nomor' => $index + 1,
                'label' => $label,
                'selesai' => (int) $status['langkah'] >= $index + 1,
            ])->values(),
            'keputusan' => [
                'status' => $status['label'],
                'deskripsi' => $status['deskripsi'],
                'final' => (bool) $status['final'],
                'total_poin_resmi' => $laporan->status_verifikasi === 'disahkan'
                    ? (int) $laporan->total_poin
                    : null,
                'butir_pelanggaran' => $laporan->status_verifikasi === 'disahkan'
                    ? $laporan->butirPelanggaranLaporan->map(fn ($butir) => [
                        'nama' => $butir->nama_pelanggaran,
                        'poin' => (int) $butir->poin,
                    ])->values()
                    : [],
                'rekomendasi_belum_resmi' => ! $status['final'],
            ],
            'linimasa' => $this->presentasiProgress
                ->linimasaPublik($laporan)
                ->map(fn (array $item) => [
                    'judul' => $item['judul'],
                    'deskripsi' => $item['deskripsi'],
                    'tanggal' => $item['tanggal']?->toISOString(),
                ])->values(),
            'tindak_lanjut' => $laporan->tindakLanjutPembinaanSiswa
                ->map(fn ($item) => [
                    'jenis' => $item->labelJenis(),
                    'tanggal' => $item->tanggal_tindak_lanjut?->toDateString(),
                    'status' => $item->labelStatusLaporan(),
                ])->values(),
            'privasi' => $pesanPrivasi,
        ];
    }

    private function siswaDariPengguna(Pengguna $pengguna): ?Siswa
    {
        abort_unless($pengguna->akunSiswa(), 403);

        return $pengguna->siswa()->first();
    }

    private function ringkasan(Builder $query): array
    {
        return [
            'semua' => (clone $query)->count(),
            'diproses' => (clone $query)
                ->where(function ($query) {
                    $query->whereIn('status_verifikasi', [
                        'diajukan',
                        'pemeriksaan_bk',
                        'perlu_klarifikasi',
                        'dikembalikan_bk',
                        'menunggu_pengesahan_wakil',
                        'menunggu_persetujuan',
                        'disetujui_sebagian',
                        'perlu_musyawarah',
                    ])->orWhere(function ($query) {
                        $query->where('status_verifikasi', 'tidak_perlu')
                            ->whereNotIn('status', ['selesai', 'dibatalkan']);
                    });
                })
                ->count(),
            'pembinaan' => (clone $query)
                ->where('status_verifikasi', 'ditetapkan_pembinaan')
                ->count(),
            'poin_resmi' => (int) (clone $query)
                ->where('status_verifikasi', 'disahkan')
                ->sum('total_poin'),
        ];
    }

    private function ringkasanKosong(): array
    {
        return ['semua' => 0, 'diproses' => 0, 'pembinaan' => 0, 'poin_resmi' => 0];
    }

    private function paginasiKosong(int $halaman, int $perHalaman): array
    {
        return [
            'halaman' => $halaman,
            'per_halaman' => $perHalaman,
            'total' => 0,
            'ada_halaman_berikutnya' => false,
        ];
    }

    public function ringkasLaporan(LaporanPembinaanSiswa $laporan): array
    {
        $status = $this->presentasiProgress->status($laporan);

        return [
            'id' => (int) $laporan->id,
            'nomor_laporan' => $laporan->nomor_laporan,
            'judul' => $laporan->berasalDariAbsensi()
                ? 'Catatan keterlambatan dari presensi'
                : 'Laporan kejadian siswa',
            'tanggal_kejadian' => $laporan->tanggal_kejadian?->toDateString(),
            'tempat_kejadian' => $laporan->tempat_kejadian,
            'kelas' => $laporan->kelas ? [
                'id' => (int) $laporan->kelas->id,
                'nama' => $laporan->kelas->nama,
            ] : null,
            'tahun_pelajaran' => $laporan->tahunPelajaran
                ? $this->tahun($laporan->tahunPelajaran)
                : null,
            'status' => [
                'label' => $status['label'],
                'deskripsi' => $status['deskripsi'],
                'warna' => $status['warna'],
                'langkah' => (int) $status['langkah'],
                'final' => (bool) $status['final'],
                'status_penanganan' => $status['status_penanganan'],
            ],
        ];
    }

    private function siswa(Siswa $siswa): array
    {
        return [
            'id' => (int) $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'nis' => $siswa->nis,
            'nisn' => $siswa->nisn,
        ];
    }

    private function tahun(TahunPelajaran $tahun): array
    {
        return ['id' => (int) $tahun->id, 'nama' => $tahun->nama];
    }
}
