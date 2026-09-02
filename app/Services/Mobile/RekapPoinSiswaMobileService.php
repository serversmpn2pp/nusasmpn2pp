<?php

namespace App\Services\Mobile;

use App\Models\AbsensiSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Pembinaan\AksesLaporanPembinaanService;
use App\Services\Pembinaan\AksesRekapPoinSiswaService;
use App\Services\Pembinaan\MonitoringPoinSiswaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RekapPoinSiswaMobileService
{
    private const STATUS_PERHATIAN = [
        'berpoin' => 'Memiliki Poin',
        'mendekati_sanksi' => 'Mendekati Sanksi',
        'menunggu_verifikasi' => 'Menunggu Verifikasi',
        'sanksi_aktif' => 'Sanksi Aktif',
    ];

    public function __construct(
        private AksesRekapPoinSiswaService $akses,
        private MonitoringPoinSiswaService $monitoring,
    ) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $tahunId = isset($filter['tahun_pelajaran_id'])
            ? (int) $filter['tahun_pelajaran_id']
            : TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        $kelasId = isset($filter['kelas_id']) ? (int) $filter['kelas_id'] : null;
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $status = (string) ($filter['status_perhatian'] ?? 'semua');
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        $perHalaman = min(30, max(5, (int) ($filter['per_halaman'] ?? 15)));
        $aturan = $this->monitoring->aturanAktif();
        $cakupan = $this->querySiswaCakupan($pengguna, $tahunId, $kelasId, $kataKunci);
        $siswaIds = (clone $cakupan)->pluck('siswa.id');
        $ringkasan = $this->monitoring->ringkasan($siswaIds, $tahunId, $aturan);
        $ringkasanKelas = $this->monitoring->ringkasanKelas($siswaIds, $tahunId)
            ->map(fn (array $item) => [
                'kelas' => [
                    'id' => (int) $item['kelas']->id,
                    'nama' => $item['kelas']->nama,
                ],
                'jumlah_siswa' => (int) $item['jumlah_siswa'],
                'siswa_berpoin' => (int) $item['siswa_berpoin'],
                'total_poin' => (int) $item['total_poin'],
                'menunggu' => (int) $item['menunggu'],
                'sanksi_aktif' => (int) $item['sanksi_aktif'],
            ])->values();

        $query = (clone $cakupan)
            ->with($this->relasiSiswa($tahunId))
            ->withSum([
                'transaksiPoinSiswa as total_poin' => fn ($query) => $query
                    ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId)),
            ], 'poin')
            ->withCount([
                'laporanPembinaanSiswa as laporan_menunggu_count' => fn ($query) => $query
                    ->where('jenis_laporan', 'pelanggaran')
                    ->whereNotIn('status_verifikasi', AksesLaporanPembinaanService::STATUS_FINAL)
                    ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId)),
                'sanksiPoinSiswa as sanksi_aktif_count' => fn ($query) => $query
                    ->whereNotIn('status', SanksiPoinSiswa::STATUS_FINAL)
                    ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId)),
            ]);
        $this->terapkanStatusPerhatian($query, $status, $tahunId, $siswaIds, $aturan);

        $paginasi = $query->orderBy('nama_lengkap')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginasi->items())->map(function (Siswa $siswa) use ($aturan): array {
                $totalPoin = max(0, (int) $siswa->total_poin);
                $indikator = $this->monitoring->indikator(
                    $totalPoin,
                    (int) $siswa->laporan_menunggu_count,
                    (int) $siswa->sanksi_aktif_count,
                    $aturan,
                );

                return $this->ringkasSiswa($siswa) + [
                    'total_poin' => $totalPoin,
                    'laporan_menunggu' => (int) $siswa->laporan_menunggu_count,
                    'sanksi_aktif' => (int) $siswa->sanksi_aktif_count,
                    'indikator' => $this->normalisasiIndikator($indikator),
                ];
            })->values(),
            'ringkasan' => $ringkasan,
            'ringkasan_kelas' => $ringkasanKelas,
            'pilihan' => [
                'status_perhatian' => $this->pilihan(self::STATUS_PERHATIAN),
                'tahun_pelajaran' => TahunPelajaran::query()
                    ->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get()
                    ->map(fn (TahunPelajaran $tahun) => [
                        'id' => (int) $tahun->id,
                        'nama' => $tahun->nama,
                        'aktif' => (bool) $tahun->aktif,
                    ])->values(),
                'kelas' => $this->daftarKelas($pengguna, $tahunId),
            ],
            'filter' => [
                'kata_kunci' => $kataKunci,
                'status_perhatian' => $status,
                'tahun_pelajaran_id' => $tahunId,
                'kelas_id' => $kelasId,
            ],
            'paginasi' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total' => $paginasi->total(),
                'ada_halaman_berikutnya' => $paginasi->hasMorePages(),
            ],
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function rincian(Pengguna $pengguna, Siswa $siswa, ?int $tahunId): array
    {
        $tahunId ??= TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        abort_unless($this->akses->bolehLihat($pengguna, $siswa, $tahunId), 403);
        $tahun = $tahunId ? TahunPelajaran::find($tahunId) : null;
        $siswa->load($this->relasiSiswa($tahunId));
        $aturan = $this->monitoring->aturanAktif();

        $queryTransaksi = $siswa->transaksiPoinSiswa()
            ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId));
        $totalPoin = max(0, (int) (clone $queryTransaksi)->sum('poin'));
        $transaksi = $queryTransaksi
            ->with([
                'laporanPembinaanSiswa:id,nomor_laporan',
                'penguranganPoinSiswa:id,jenis_kegiatan',
            ])
            ->latest('tercatat_pada')->limit(100)->get();

        $queryLaporan = $siswa->laporanPembinaanSiswa()
            ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId));
        $queryLaporanMenunggu = (clone $queryLaporan)
            ->where('jenis_laporan', 'pelanggaran')
            ->whereNotIn('status_verifikasi', AksesLaporanPembinaanService::STATUS_FINAL);
        $jumlahLaporanMenunggu = (clone $queryLaporanMenunggu)->count();
        $poinDalamProses = (int) (clone $queryLaporanMenunggu)->sum('total_poin');
        $laporan = $queryLaporan
            ->with([
                'kategoriPembinaanSiswa:id,nama',
                'kelas:id,nama',
                'butirPelanggaranLaporan:id,laporan_pembinaan_siswa_id,kode_pelanggaran,nama_pelanggaran,poin',
            ])
            ->latest('tanggal_kejadian')->latest('id')->limit(50)->get();

        $sanksi = $siswa->sanksiPoinSiswa()
            ->with(['aturanSanksiPoin:id,batas_poin,nama', 'petugasPegawai:id,nama_lengkap'])
            ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->latest('terpicu_pada')->get();
        $sanksiAktif = $sanksi->whereNotIn('status', SanksiPoinSiswa::STATUS_FINAL)->count();

        $peringatan = $siswa->peringatanDiniSiswa()
            ->where('status', 'aktif')
            ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->orderByRaw("CASE WHEN tingkat = 'penting' THEN 0 ELSE 1 END")
            ->latest('terakhir_terdeteksi_pada')->get();
        $pendampingan = $siswa->pendampinganSiswa()
            ->with(['petugasPegawai:id,nama_lengkap', 'peringatanDiniSiswa:id,jenis,judul'])
            ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->orderByRaw("CASE WHEN status = 'dalam_proses' THEN 0 ELSE 1 END")
            ->latest('tanggal_tindak_lanjut')->latest('id')->limit(30)->get();
        $pengurangan = $siswa->penguranganPoinSiswa()
            ->with('disetujuiOlehPegawai:id,nama_lengkap')
            ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->latest('tanggal_kegiatan')->limit(30)->get();

        $queryTerlambat = AbsensiSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->where('menit_terlambat', '>', 0)
            ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId));
        $rekapTerlambat = [
            'jumlah' => (clone $queryTerlambat)->count(),
            'total_menit' => (int) (clone $queryTerlambat)->sum('menit_terlambat'),
        ];
        $terlambat = $queryTerlambat->with('kelas:id,nama')
            ->latest('tanggal')->limit(12)->get();
        $indikator = $this->monitoring->indikator(
            $totalPoin,
            $jumlahLaporanMenunggu,
            $sanksiAktif,
            $aturan,
        );

        return [
            'siswa' => $this->ringkasSiswa($siswa),
            'tahun_pelajaran' => $tahun ? ['id' => (int) $tahun->id, 'nama' => $tahun->nama] : null,
            'ringkasan' => [
                'total_poin' => $totalPoin,
                'peringatan_aktif' => $peringatan->count(),
                'peringatan_penting' => $peringatan->where('tingkat', 'penting')->count(),
                'laporan_menunggu' => $jumlahLaporanMenunggu,
                'poin_dalam_proses' => $poinDalamProses,
                'sanksi_aktif' => $sanksiAktif,
                'keterlambatan' => $rekapTerlambat,
                'indikator' => $this->normalisasiIndikator($indikator),
            ],
            'perkembangan_bulanan' => $this->monitoring->perkembanganBulanan($siswa->id, $tahun)->values(),
            'transaksi' => $transaksi->map(fn (TransaksiPoinSiswa $item) => [
                'id' => (int) $item->id,
                'jenis' => $item->jenis,
                'label_jenis' => str($item->jenis)->replace('_', ' ')->title()->toString(),
                'poin' => (int) $item->poin,
                'keterangan' => $item->keterangan,
                'tercatat_pada' => $item->tercatat_pada?->toISOString(),
                'sumber' => $item->laporanPembinaanSiswa ? [
                    'jenis' => 'laporan',
                    'id' => (int) $item->laporanPembinaanSiswa->id,
                    'label' => $item->laporanPembinaanSiswa->nomor_laporan,
                ] : ($item->penguranganPoinSiswa ? [
                    'jenis' => 'pengurangan',
                    'id' => (int) $item->penguranganPoinSiswa->id,
                    'label' => $item->penguranganPoinSiswa->jenis_kegiatan,
                ] : null),
            ])->values(),
            'laporan' => $laporan->map(fn (LaporanPembinaanSiswa $item) => [
                'id' => (int) $item->id,
                'nomor' => $item->nomor_laporan,
                'tanggal' => $item->tanggal_kejadian?->toDateString(),
                'jenis' => $item->jenis_laporan,
                'label_jenis' => $item->labelJenisLaporan(),
                'kategori' => $item->kategoriPembinaanSiswa?->nama,
                'kode_pelanggaran' => $item->butirPelanggaranLaporan->pluck('kode_pelanggaran')->values(),
                'status' => $item->status_verifikasi,
                'label_status' => $item->labelStatusVerifikasi(),
                'poin' => (int) $item->total_poin,
            ])->values(),
            'peringatan' => $peringatan->map(fn ($item) => [
                'id' => (int) $item->id,
                'jenis' => $item->jenis,
                'label_jenis' => $item->labelJenis(),
                'tingkat' => $item->tingkat,
                'label_tingkat' => $item->labelTingkat(),
                'pesan' => $item->pesan,
                'siklus' => (int) $item->siklus,
                'terakhir_terdeteksi_pada' => $item->terakhir_terdeteksi_pada?->toISOString(),
            ])->values(),
            'pendampingan' => $pendampingan->map(fn ($item) => [
                'id' => (int) $item->id,
                'tanggal' => $item->tanggal_tindak_lanjut?->toDateString(),
                'jenis' => $item->jenis_tindakan,
                'label_jenis' => $item->labelJenis(),
                'status' => $item->status,
                'label_status' => $item->labelStatus(),
                'petugas' => $item->petugasPegawai?->nama_lengkap,
                'ringkasan' => $item->hasil ?: $item->catatan,
                'peringatan_id' => $item->peringatan_dini_siswa_id ? (int) $item->peringatan_dini_siswa_id : null,
            ])->values(),
            'sanksi' => $sanksi->map(fn (SanksiPoinSiswa $item) => [
                'id' => (int) $item->id,
                'nama' => $item->aturanSanksiPoin?->nama ?? 'Sanksi poin',
                'ambang_poin' => $item->aturanSanksiPoin?->batas_poin,
                'poin_saat_terpicu' => (int) $item->poin_saat_terpicu,
                'status' => $item->status,
                'label_status' => $item->labelStatus(),
                'terpicu_pada' => $item->terpicu_pada?->toISOString(),
                'batas_pelaksanaan' => $item->batas_pelaksanaan?->toDateString(),
                'petugas' => $item->petugasPegawai?->nama_lengkap,
                'terlambat' => $item->terlambat(),
            ])->values(),
            'pengurangan' => $pengurangan->map(fn ($item) => [
                'id' => (int) $item->id,
                'tanggal' => $item->tanggal_kegiatan?->toDateString(),
                'jenis_kegiatan' => $item->jenis_kegiatan,
                'deskripsi' => $item->deskripsi,
                'poin' => (int) $item->poin_pengurangan,
                'status' => $item->status,
                'label_status' => $item::DAFTAR_STATUS[$item->status] ?? str($item->status)->headline()->toString(),
                'disetujui_oleh' => $item->disetujuiOlehPegawai?->nama_lengkap,
            ])->values(),
            'keterlambatan' => $terlambat->map(fn (AbsensiSiswa $item) => [
                'id' => (int) $item->id,
                'tanggal' => $item->tanggal?->toDateString(),
                'kelas' => $item->kelas?->nama,
                'menit' => (int) $item->menit_terlambat,
                'poin' => (int) $item->poin_keterlambatan_terhitung,
                'status_poin' => $item->status_poin_keterlambatan,
            ])->values(),
            'pilihan_tahun' => TahunPelajaran::query()
                ->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get()
                ->map(fn (TahunPelajaran $item) => [
                    'id' => (int) $item->id,
                    'nama' => $item->nama,
                    'aktif' => (bool) $item->aktif,
                ])->values(),
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    private function querySiswaCakupan(
        Pengguna $pengguna,
        ?int $tahunId,
        ?int $kelasId = null,
        string $kataKunci = '',
    ): Builder {
        $query = Siswa::query()
            ->where('aktif', true)
            ->when($tahunId, fn (Builder $query) => $query->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->where('tahun_pelajaran_id', $tahunId)
                ->where('status_keanggotaan', 'aktif')))
            ->when($kelasId, fn (Builder $query) => $query->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->where('kelas_id', $kelasId)
                ->where('status_keanggotaan', 'aktif')
                ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola): void {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]);
                });
            });
        $this->akses->terapkanCakupan($query, $pengguna, $tahunId);

        return $query;
    }

    private function relasiSiswa(?int $tahunId): array
    {
        return [
            'anggotaKelas' => fn ($query) => $query
                ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
                ->where('status_keanggotaan', 'aktif')
                ->with('kelas:id,nama,tingkat'),
            'penugasanGuruWaliSiswa' => fn ($query) => $query
                ->where('aktif', true)->latest('tanggal_mulai')
                ->with('guruWali:id,nama_lengkap,nip'),
        ];
    }

    private function ringkasSiswa(Siswa $siswa): array
    {
        $anggota = $siswa->anggotaKelas->first();
        $guruWali = $siswa->penugasanGuruWaliSiswa->first()?->guruWali;

        return [
            'siswa' => [
                'id' => (int) $siswa->id,
                'nama' => $siswa->nama_lengkap,
                'nis' => $siswa->nis,
                'nisn' => $siswa->nisn,
            ],
            'kelas' => $anggota?->kelas ? [
                'id' => (int) $anggota->kelas->id,
                'nama' => $anggota->kelas->nama,
            ] : null,
            'guru_wali' => $guruWali ? [
                'id' => (int) $guruWali->id,
                'nama' => $guruWali->nama_lengkap,
                'nip' => $guruWali->nip,
            ] : null,
        ];
    }

    private function normalisasiIndikator(array $indikator): array
    {
        $aturan = $indikator['aturanBerikutnya'];

        return [
            'kode' => $indikator['kode'],
            'label' => $indikator['label'],
            'jarak' => $indikator['jarak'] !== null ? (int) $indikator['jarak'] : null,
            'persentase' => (int) $indikator['persentase'],
            'ambang_berikutnya' => $aturan ? [
                'id' => (int) $aturan->id,
                'nama' => $aturan->nama,
                'batas_poin' => (int) $aturan->batas_poin,
            ] : null,
        ];
    }

    private function terapkanStatusPerhatian(
        Builder $query,
        string $status,
        ?int $tahunId,
        Collection $siswaIds,
        Collection $aturan,
    ): void {
        if ($status === 'berpoin') {
            $query->whereIn('siswa.id', TransaksiPoinSiswa::query()
                ->select('siswa_id')
                ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
                ->groupBy('siswa_id')->havingRaw('SUM(poin) > 0'));
        } elseif ($status === 'menunggu_verifikasi') {
            $query->whereHas('laporanPembinaanSiswa', fn ($query) => $query
                ->where('jenis_laporan', 'pelanggaran')
                ->whereNotIn('status_verifikasi', AksesLaporanPembinaanService::STATUS_FINAL)
                ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId)));
        } elseif ($status === 'sanksi_aktif') {
            $query->whereHas('sanksiPoinSiswa', fn ($query) => $query
                ->whereNotIn('status', SanksiPoinSiswa::STATUS_FINAL)
                ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId)));
        } elseif ($status === 'mendekati_sanksi') {
            $ids = $this->monitoring->saldoPoinPerSiswa($siswaIds, $tahunId)
                ->filter(fn ($poin) => $this->monitoring->indikator((int) $poin, 0, 0, $aturan)['kode'] === 'mendekati_sanksi')
                ->keys();
            $query->whereIn('siswa.id', $ids);
        }
    }

    private function daftarKelas(Pengguna $pengguna, ?int $tahunId): array
    {
        $siswaIds = $this->querySiswaCakupan($pengguna, $tahunId)->pluck('siswa.id');

        return Kelas::query()
            ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->whereIn('siswa_id', $siswaIds)->where('status_keanggotaan', 'aktif'))
            ->orderBy('tingkat')->orderBy('nama')->get()
            ->map(fn (Kelas $kelas) => [
                'id' => (int) $kelas->id,
                'tahun_pelajaran_id' => (int) $kelas->tahun_pelajaran_id,
                'nama' => $kelas->nama,
                'tingkat' => (int) $kelas->tingkat,
            ])->values()->all();
    }

    private function pilihan(array $items): array
    {
        return collect($items)->map(fn (string $label, string $kode) => [
            'kode' => $kode,
            'label' => $label,
        ])->values()->all();
    }

    private function hakAkses(Pengguna $pengguna): array
    {
        return [
            'cakupan_luas' => $this->akses->aksesLuas($pengguna),
            'dapat_kelola_pendampingan' => $pengguna->administrator()
                || $pengguna->memilikiIzin('poin_siswa.pendampingan_kelola'),
        ];
    }
}
