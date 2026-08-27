<?php

namespace App\Services\Mobile;

use App\Models\GuruMataPelajaran;
use App\Models\JenisPerangkatAjar;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PerangkatAjar;
use App\Models\TahunPelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\PerangkatAjar\PenugasanPerangkatAjarService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PemeriksaanPerangkatAjarMobileService
{
    public function __construct(
        private PenugasanPerangkatAjarService $penugasanService,
        private NotifikasiPenggunaService $notifikasiService,
    ) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $tahunPelajaran = $this->daftarTahunPelajaran();
        $tahunPelajaranId = $this->ambilTahunPelajaranId(
            $filter['tahun_pelajaran_id'] ?? null,
            $tahunPelajaran,
        );
        $semester = (int) ($filter['semester'] ?? 1);
        $kelengkapan = $filter['kelengkapan'] ?? 'semua';
        $statusDokumen = $filter['status_dokumen'] ?? 'semua';
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        $perHalaman = min(30, max(5, (int) ($filter['per_halaman'] ?? 15)));
        $monitoringSemua = $this->buatMonitoringGuru($tahunPelajaranId, $semester);
        $ringkasan = [
            'jumlah_guru' => $monitoringSemua->count(),
            'lengkap' => $monitoringSemua->where('lengkap', true)->count(),
            'belum_lengkap' => $monitoringSemua->where('lengkap', false)->count(),
            'menunggu_pemeriksaan' => $monitoringSemua->sum('jumlah_menunggu'),
            'perlu_perbaikan' => $monitoringSemua->sum('jumlah_perlu_perbaikan'),
        ];
        $monitoring = $monitoringSemua
            ->when($kelengkapan === 'lengkap', fn (Collection $items) => $items->where('lengkap', true))
            ->when($kelengkapan === 'belum_lengkap', fn (Collection $items) => $items->where('lengkap', false))
            ->when($statusDokumen === 'belum_diunggah', fn (Collection $items) => $items->filter(
                fn (array $item) => $item['jumlah_terunggah_wajib'] < $item['jumlah_wajib']
            ))
            ->when($statusDokumen === 'menunggu_pemeriksaan', fn (Collection $items) => $items->where('jumlah_menunggu', '>', 0))
            ->when($statusDokumen === 'perlu_perbaikan', fn (Collection $items) => $items->where('jumlah_perlu_perbaikan', '>', 0))
            ->when($statusDokumen === 'sudah_diperiksa', fn (Collection $items) => $items->where('jumlah_sudah_diperiksa', '>', 0))
            ->when($kataKunci !== '', fn (Collection $items) => $items->filter(function (array $item) use ($kataKunci): bool {
                $haystack = collect([
                    $item['pegawai']->nama_lengkap,
                    $item['pegawai']->nip,
                    $item['mata_pelajaran']->pluck('nama')->implode(' '),
                ])->filter()->implode(' ');

                return Str::contains(Str::lower($haystack), Str::lower($kataKunci));
            }))
            ->values();

        return [
            'items' => $monitoring
                ->forPage($halaman, $perHalaman)
                ->map(fn (array $item) => $this->ringkasMonitoring($item))
                ->values(),
            'ringkasan' => $ringkasan,
            'tahun_pelajaran' => $tahunPelajaran->map(fn (TahunPelajaran $tahun) => [
                'id' => (int) $tahun->id,
                'nama' => $tahun->nama,
                'aktif' => (bool) $tahun->aktif,
            ])->values(),
            'filter' => [
                'tahun_pelajaran_id' => $tahunPelajaranId,
                'semester' => $semester,
                'kelengkapan' => $kelengkapan,
                'status_dokumen' => $statusDokumen,
                'kata_kunci' => $kataKunci,
            ],
            'paginasi' => [
                'halaman' => $halaman,
                'per_halaman' => $perHalaman,
                'total' => $monitoring->count(),
                'ada_halaman_berikutnya' => $halaman * $perHalaman < $monitoring->count(),
            ],
            'hak_akses' => [
                'dapat_memeriksa' => $pengguna->memilikiIzin('perangkat_ajar.periksa'),
            ],
        ];
    }

    public function rincianGuru(Pengguna $pengguna, Pegawai $pegawai, array $filter): array
    {
        $tahunPelajaran = $this->daftarTahunPelajaran();
        $tahunPelajaranId = $this->ambilTahunPelajaranId(
            $filter['tahun_pelajaran_id'] ?? null,
            $tahunPelajaran,
        );
        $semester = (int) ($filter['semester'] ?? 1);
        $penugasan = $this->penugasanService->untukGuru($pegawai->id, $tahunPelajaranId);
        abort_if($penugasan->isEmpty(), 404, 'Guru tidak memiliki penugasan pada tahun pelajaran ini.');

        $jenis = $this->jenisPerangkatAktif();
        $dokumen = PerangkatAjar::query()
            ->with(['mataPelajaran:id,nama', 'jenisPerangkatAjar:id,kode,nama,wajib', 'pemeriksa:id,nama_lengkap'])
            ->where('pegawai_id', $pegawai->id)
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->where('semester', $semester)
            ->whereIn('mata_pelajaran_id', $penugasan->pluck('mata_pelajaran_id'))
            ->whereIn('jenis_perangkat_ajar_id', $jenis->pluck('id'))
            ->get();
        $kunciPenugasan = $penugasan->pluck('kunci');
        $dokumenTerpetakan = $dokumen
            ->filter(fn (PerangkatAjar $item) => $item->tingkat
                && $kunciPenugasan->contains(
                    $this->penugasanService->kunci($item->mata_pelajaran_id, $item->tingkat),
                ))
            ->keyBy(fn (PerangkatAjar $item) => $this->kunciDokumen(
                $item->mata_pelajaran_id,
                $item->tingkat,
                $item->jenis_perangkat_ajar_id,
            ));
        $jenisWajibIds = $jenis->where('wajib', true)->pluck('id');
        $jumlahWajib = $penugasan->count() * $jenisWajibIds->count();
        $jumlahTerunggah = $dokumenTerpetakan->whereIn('jenis_perangkat_ajar_id', $jenisWajibIds)->count();

        return [
            'pegawai' => $this->ringkasPegawai($pegawai),
            'tahun_pelajaran' => $tahunPelajaran->map(fn (TahunPelajaran $tahun) => [
                'id' => (int) $tahun->id,
                'nama' => $tahun->nama,
                'aktif' => (bool) $tahun->aktif,
            ])->values(),
            'filter' => [
                'tahun_pelajaran_id' => $tahunPelajaranId,
                'semester' => $semester,
            ],
            'ringkasan' => [
                'wajib' => $jumlahWajib,
                'terunggah' => $jumlahTerunggah,
                'kelengkapan' => $jumlahWajib > 0 ? min(100, (int) round($jumlahTerunggah / $jumlahWajib * 100)) : 0,
                'menunggu' => $dokumenTerpetakan->where('status', 'menunggu_pemeriksaan')->count(),
                'perlu_perbaikan' => $dokumenTerpetakan->where('status', 'perlu_perbaikan')->count(),
                'sudah_diperiksa' => $dokumenTerpetakan->where('status', 'sudah_diperiksa')->count(),
            ],
            'penugasan' => $penugasan->map(function (array $item) use ($jenis, $dokumenTerpetakan): array {
                $mataPelajaran = $item['mata_pelajaran'];

                return [
                    'mata_pelajaran' => [
                        'id' => (int) $mataPelajaran->id,
                        'nama' => $mataPelajaran->nama,
                    ],
                    'tingkat' => (int) $item['tingkat'],
                    'label_tingkat' => $item['label_tingkat'],
                    'dokumen' => $jenis->map(function (JenisPerangkatAjar $jenis) use ($dokumenTerpetakan, $mataPelajaran, $item): array {
                        $dokumen = $dokumenTerpetakan->get($this->kunciDokumen(
                            $mataPelajaran->id,
                            $item['tingkat'],
                            $jenis->id,
                        ));

                        return [
                            'jenis' => $this->ringkasJenis($jenis),
                            'perangkat_ajar' => $dokumen ? $this->ringkasDokumen($dokumen) : null,
                        ];
                    })->values(),
                ];
            })->values(),
            'dokumen_tanpa_tingkat' => $dokumen
                ->whereNull('tingkat')
                ->map(fn (PerangkatAjar $item) => $this->ringkasDokumen($item))
                ->values(),
            'hak_akses' => [
                'dapat_memeriksa' => $pengguna->memilikiIzin('perangkat_ajar.periksa'),
            ],
        ];
    }

    public function rincianDokumen(Pengguna $pengguna, PerangkatAjar $perangkatAjar): array
    {
        $perangkatAjar->load([
            'pegawai:id,nama_lengkap,nip',
            'tahunPelajaran:id,nama,aktif',
            'mataPelajaran:id,nama',
            'jenisPerangkatAjar:id,kode,nama,wajib,deskripsi',
            'pemeriksa:id,nama_lengkap',
            'riwayatFile.pengunggah:id,nama',
        ]);

        return [
            'perangkat_ajar' => $this->ringkasDokumen($perangkatAjar) + [
                'pegawai' => $this->ringkasPegawai($perangkatAjar->pegawai),
                'tahun_pelajaran' => [
                    'id' => (int) $perangkatAjar->tahunPelajaran->id,
                    'nama' => $perangkatAjar->tahunPelajaran->nama,
                    'aktif' => (bool) $perangkatAjar->tahunPelajaran->aktif,
                ],
                'mata_pelajaran' => [
                    'id' => (int) $perangkatAjar->mataPelajaran->id,
                    'nama' => $perangkatAjar->mataPelajaran->nama,
                ],
                'jenis' => $this->ringkasJenis($perangkatAjar->jenisPerangkatAjar),
                'catatan_guru' => $perangkatAjar->catatan_guru,
                'catatan_pemeriksa' => $perangkatAjar->catatan_pemeriksa,
                'pemeriksa' => $perangkatAjar->pemeriksa?->nama_lengkap,
                'diperiksa_pada' => $perangkatAjar->diperiksa_pada?->toISOString(),
            ],
            'riwayat' => $perangkatAjar->riwayatFile->map(fn ($item) => [
                'id' => (int) $item->id,
                'nama_file' => $item->nama_file_asli,
                'ukuran_file' => (int) $item->ukuran_file,
                'catatan' => $item->catatan,
                'diunggah_pada' => $item->diunggah_pada?->toISOString(),
                'pengunggah' => $item->pengunggah?->nama,
            ])->values(),
            'hak_akses' => [
                'dapat_memeriksa' => $pengguna->memilikiIzin('perangkat_ajar.periksa'),
            ],
        ];
    }

    public function simpanPemeriksaan(
        Pengguna $pengguna,
        PerangkatAjar $perangkatAjar,
        array $data,
    ): void {
        $catatan = filled($data['catatan_pemeriksa'] ?? null)
            ? trim($data['catatan_pemeriksa'])
            : null;
        $perangkatAjar->update([
            'status' => $data['status'],
            'catatan_pemeriksa' => $catatan,
            'pemeriksa_pegawai_id' => $pengguna->pegawai_id,
            'diperiksa_pada' => now(),
        ]);
        $perangkatAjar->loadMissing(['mataPelajaran', 'jenisPerangkatAjar']);
        $disetujui = $perangkatAjar->status === 'sudah_diperiksa';
        $this->notifikasiService->kirimKeBanyak(
            $this->notifikasiService->penggunaUntukPegawai($perangkatAjar->pegawai_id),
            $disetujui ? 'berhasil' : 'penting',
            $disetujui ? 'Perangkat ajar sudah diperiksa' : 'Perangkat ajar perlu diperbaiki',
            sprintf(
                '%s untuk mata pelajaran %s tingkat %s telah diperiksa.%s',
                $perangkatAjar->jenisPerangkatAjar?->nama ?? 'Perangkat ajar',
                $perangkatAjar->mataPelajaran?->nama ?? '-',
                $perangkatAjar->tingkatTampil(),
                $catatan ? ' Catatan: '.$catatan : '',
            ),
            route('perangkat-ajar-saya.show', $perangkatAjar, false),
            "perangkat-ajar-diperiksa:{$perangkatAjar->id}:{$perangkatAjar->status}:{$perangkatAjar->diperiksa_pada->format('Uv')}",
        );
    }

    private function buatMonitoringGuru(?int $tahunPelajaranId, int $semester): Collection
    {
        if (! $tahunPelajaranId) {
            return collect();
        }
        $jenis = $this->jenisPerangkatAktif();
        $jenisWajibIds = $jenis->where('wajib', true)->pluck('id');
        $penugasan = GuruMataPelajaran::query()
            ->with(['pegawai', 'mataPelajaran', 'kelas'])
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('aktif', true)
            ->whereHas('pegawai', fn ($query) => $query->where('aktif', true))
            ->whereHas('mataPelajaran', fn ($query) => $query->where('aktif', true))
            ->whereHas('kelas', fn ($query) => $query->where('aktif', true)->whereIn('tingkat', [7, 8, 9]))
            ->get()
            ->groupBy('pegawai_id');
        $dokumen = PerangkatAjar::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('semester', $semester)
            ->whereIn('jenis_perangkat_ajar_id', $jenis->pluck('id'))
            ->get()
            ->groupBy('pegawai_id');

        return $penugasan->map(function (Collection $penugasanGuru, $pegawaiId) use ($dokumen, $jenisWajibIds): array {
            $penugasanPerTingkat = $this->penugasanService->ringkas($penugasanGuru);
            $mataPelajaran = $penugasanGuru->pluck('mataPelajaran')->filter()->unique('id')->sortBy('nama')->values();
            $mapelIds = $mataPelajaran->pluck('id');
            $kunciPenugasan = $penugasanPerTingkat->pluck('kunci');
            $dokumenGuru = $dokumen->get($pegawaiId, collect())
                ->whereIn('mata_pelajaran_id', $mapelIds)
                ->filter(fn (PerangkatAjar $item) => $item->tingkat
                    && $kunciPenugasan->contains(
                        $this->penugasanService->kunci($item->mata_pelajaran_id, $item->tingkat),
                    ));
            $jumlahWajib = $penugasanPerTingkat->count() * $jenisWajibIds->count();
            $jumlahTerunggah = $dokumenGuru->whereIn('jenis_perangkat_ajar_id', $jenisWajibIds)->count();

            return [
                'pegawai' => $penugasanGuru->first()->pegawai,
                'mata_pelajaran' => $mataPelajaran,
                'penugasan_per_tingkat' => $penugasanPerTingkat,
                'jumlah_wajib' => $jumlahWajib,
                'jumlah_terunggah_wajib' => $jumlahTerunggah,
                'persentase' => $jumlahWajib > 0 ? min(100, (int) round($jumlahTerunggah / $jumlahWajib * 100)) : 0,
                'lengkap' => $jumlahWajib > 0 && $jumlahTerunggah >= $jumlahWajib,
                'jumlah_menunggu' => $dokumenGuru->where('status', 'menunggu_pemeriksaan')->count(),
                'jumlah_perlu_perbaikan' => $dokumenGuru->where('status', 'perlu_perbaikan')->count(),
                'jumlah_sudah_diperiksa' => $dokumenGuru->where('status', 'sudah_diperiksa')->count(),
            ];
        })->sortBy(fn (array $item) => $item['pegawai']->nama_lengkap)->values();
    }

    private function ringkasMonitoring(array $item): array
    {
        return [
            'pegawai' => $this->ringkasPegawai($item['pegawai']),
            'mata_pelajaran' => $item['mata_pelajaran']->map(fn ($mapel) => [
                'id' => (int) $mapel->id,
                'nama' => $mapel->nama,
            ])->values(),
            'tingkat' => $item['penugasan_per_tingkat']->pluck('label_tingkat')->unique()->values(),
            'jumlah_wajib' => $item['jumlah_wajib'],
            'jumlah_terunggah_wajib' => $item['jumlah_terunggah_wajib'],
            'persentase' => $item['persentase'],
            'lengkap' => $item['lengkap'],
            'jumlah_menunggu' => $item['jumlah_menunggu'],
            'jumlah_perlu_perbaikan' => $item['jumlah_perlu_perbaikan'],
            'jumlah_sudah_diperiksa' => $item['jumlah_sudah_diperiksa'],
        ];
    }

    private function ringkasDokumen(PerangkatAjar $item): array
    {
        return [
            'id' => (int) $item->id,
            'judul' => $item->judul,
            'tingkat' => $item->tingkat ? (int) $item->tingkat : null,
            'label_tingkat' => $item->tingkatTampil(),
            'nama_file' => $item->nama_file_asli,
            'ukuran_file' => (int) $item->ukuran_file,
            'status' => $item->status,
            'label_status' => $item->labelStatus(),
            'diunggah_pada' => $item->diunggah_pada?->toISOString(),
            'catatan_pemeriksa' => $item->catatan_pemeriksa,
        ];
    }

    private function ringkasJenis(JenisPerangkatAjar $jenis): array
    {
        return [
            'id' => (int) $jenis->id,
            'kode' => $jenis->kode,
            'nama' => $jenis->nama,
            'deskripsi' => $jenis->deskripsi,
            'wajib' => (bool) $jenis->wajib,
        ];
    }

    private function ringkasPegawai(Pegawai $pegawai): array
    {
        return [
            'id' => (int) $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'nip' => $pegawai->nip,
        ];
    }

    private function jenisPerangkatAktif(): Collection
    {
        return JenisPerangkatAjar::query()->where('aktif', true)->orderBy('urutan')->orderBy('nama')->get();
    }

    private function daftarTahunPelajaran(): Collection
    {
        return TahunPelajaran::query()->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();
    }

    private function ambilTahunPelajaranId(?int $tahunId, Collection $daftar): ?int
    {
        if ($tahunId && $daftar->contains('id', $tahunId)) {
            return $tahunId;
        }

        return $daftar->firstWhere('aktif', true)?->id ?? $daftar->first()?->id;
    }

    private function kunciDokumen(int $mapelId, int $tingkat, int $jenisId): string
    {
        return "{$mapelId}-{$tingkat}-{$jenisId}";
    }
}
