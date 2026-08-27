<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\SurveiPembelajaran;
use App\Models\TahunPelajaran;
use App\Services\Survei\RekapSurveiPembelajaranService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class MonitoringSurveiMobileService
{
    public function __construct(private RekapSurveiPembelajaranService $rekapSurvei) {}

    public function daftar(array $filter): array
    {
        $daftarTahun = TahunPelajaran::query()
            ->whereHas('guruMataPelajaran')
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'nama', 'aktif']);
        $tahunDipilih = $daftarTahun
            ->firstWhere('id', (int) ($filter['tahun_pelajaran_id'] ?? 0))
            ?: $daftarTahun->firstWhere('aktif', true)
            ?: $daftarTahun->first();
        $semester = $filter['semester'] ?? $this->semesterSaatIni();
        $status = $filter['status'] ?? 'semua';
        $kataKunci = trim((string) ($filter['cari'] ?? ''));

        $penugasan = GuruMataPelajaran::query()
            ->with([
                'kelas:id,nama,tingkat',
                'mataPelajaran:id,nama',
                'pegawai:id,nama_lengkap,nip',
                'tahunPelajaran:id,nama,aktif',
            ])
            ->when(
                $tahunDipilih,
                fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunDipilih->id),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            )
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pencarian = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pencarian): void {
                    $query->whereHas('pegawai', fn (Builder $query) => $query
                        ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pencarian])
                        ->orWhereRaw('LOWER(nip) LIKE ?', [$pencarian]))
                        ->orWhereHas('mataPelajaran', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama) LIKE ?', [$pencarian]))
                        ->orWhereHas('kelas', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama) LIKE ?', [$pencarian]));
                });
            })
            ->get()
            ->sortBy(fn (GuruMataPelajaran $item) => sprintf(
                '%s|%02d|%s|%s|%08d',
                $item->pegawai?->nama_lengkap ?? '',
                $item->kelas?->tingkat ?? 99,
                $item->mataPelajaran?->nama ?? '',
                $item->kelas?->nama ?? '',
                $item->id,
            ))
            ->values();

        $jumlahSiswaPerKelas = AnggotaKelas::query()
            ->when(
                $tahunDipilih,
                fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunDipilih->id),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            )
            ->where('status_keanggotaan', 'aktif')
            ->selectRaw('kelas_id, count(distinct siswa_id) as jumlah')
            ->groupBy('kelas_id')
            ->pluck('jumlah', 'kelas_id');
        $surveiPerPenugasan = SurveiPembelajaran::query()
            ->whereIn('guru_mata_pelajaran_id', $penugasan->pluck('id'))
            ->where('semester', $semester)
            ->get(['id', 'guru_mata_pelajaran_id', 'jawaban', 'snapshot_pertanyaan', 'saran', 'diisi_pada'])
            ->groupBy('guru_mata_pelajaran_id');

        $baris = $penugasan
            ->map(function (GuruMataPelajaran $item) use ($jumlahSiswaPerKelas, $surveiPerPenugasan): array {
                $ringkasan = $this->rekapSurvei->ringkas(
                    $surveiPerPenugasan->get($item->id, collect()),
                    (int) $jumlahSiswaPerKelas->get($item->kelas_id, 0),
                );

                return $this->ringkasPenugasan($item, $ringkasan);
            })
            ->filter(fn (array $item) => match ($status) {
                'belum' => $item['jumlah_pengisi'] === 0,
                'berjalan' => $item['jumlah_pengisi'] > 0 && $item['persentase_pengisian'] < 100,
                'lengkap' => $item['jumlah_siswa'] > 0 && $item['persentase_pengisian'] >= 100,
                default => true,
            })
            ->values();

        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);
        $paginator = new LengthAwarePaginator(
            $baris->forPage($halaman, $perHalaman)->values(),
            $baris->count(),
            $perHalaman,
            $halaman,
        );

        return [
            'items' => collect($paginator->items())->values(),
            'ringkasan' => [
                'penugasan' => $baris->count(),
                'target_respons' => $baris->sum('jumlah_siswa'),
                'respons_masuk' => $baris->sum('jumlah_pengisi'),
                'hasil_terbuka' => $baris->where('hasil_terbuka', true)->count(),
            ],
            'tahun_pelajaran' => $daftarTahun->map(fn (TahunPelajaran $tahun) => [
                'id' => (int) $tahun->id,
                'nama' => $tahun->nama,
                'aktif' => (bool) $tahun->aktif,
            ])->values(),
            'filter' => [
                'tahun_pelajaran_id' => $tahunDipilih ? (int) $tahunDipilih->id : null,
                'semester' => $semester,
                'status' => $status,
                'cari' => $kataKunci,
            ],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
            'minimal_responden' => RekapSurveiPembelajaranService::MINIMAL_RESPONDEN,
        ];
    }

    public function rincian(GuruMataPelajaran $penugasan, string $semester): array
    {
        $penugasan->loadMissing([
            'kelas:id,nama,tingkat',
            'mataPelajaran:id,nama',
            'pegawai:id,nama_lengkap,nip',
            'tahunPelajaran:id,nama,aktif',
        ]);
        $hasil = $this->rekapSurvei->untukPenugasan($penugasan, $semester);

        return [
            'penugasan' => $this->ringkasPenugasan($penugasan, $hasil),
            'semester' => $semester,
            'minimal_responden' => RekapSurveiPembelajaranService::MINIMAL_RESPONDEN,
            'skala' => collect(SurveiPembelajaran::PILIHAN)
                ->map(fn (string $label, int $nilai) => ['nilai' => $nilai, 'label' => $label])
                ->values(),
            'rincian_pertanyaan' => collect($hasil['rincianPertanyaan'])->map(fn (array $item) => [
                'kode' => $item['kode'],
                'pernyataan' => $item['pernyataan'],
                'urutan' => (int) $item['urutan'],
                'jumlah_jawaban' => (int) $item['jumlah_jawaban'],
                'rata_rata' => $item['rata_rata'] !== null ? (float) $item['rata_rata'] : null,
                'distribusi' => collect($item['distribusi'])->map(fn (array $distribusi, int $nilai) => [
                    'nilai' => $nilai,
                    'jumlah' => (int) $distribusi['jumlah'],
                    'persentase' => (float) $distribusi['persentase'],
                ])->values(),
            ])->values(),
            'saran' => collect($hasil['daftarSaran'])->map(fn (array $item) => [
                'saran' => $item['saran'],
                'diisi_pada' => $item['diisi_pada']?->toISOString(),
            ])->values(),
        ];
    }

    private function ringkasPenugasan(GuruMataPelajaran $penugasan, array $ringkasan): array
    {
        $persentase = (float) $ringkasan['persentasePengisian'];
        $jumlahPengisi = (int) $ringkasan['jumlahPengisi'];

        return [
            'id' => (int) $penugasan->id,
            'guru' => [
                'nama' => $penugasan->pegawai?->nama_lengkap ?? '-',
                'nip' => $penugasan->pegawai?->nip,
            ],
            'mata_pelajaran' => [
                'id' => $penugasan->mataPelajaran ? (int) $penugasan->mataPelajaran->id : null,
                'nama' => $penugasan->mataPelajaran?->nama ?? '-',
            ],
            'kelas' => [
                'id' => $penugasan->kelas ? (int) $penugasan->kelas->id : null,
                'nama' => $penugasan->kelas?->nama ?? '-',
                'tingkat' => $penugasan->kelas?->tingkat !== null ? (int) $penugasan->kelas->tingkat : null,
            ],
            'tahun_pelajaran' => [
                'id' => $penugasan->tahunPelajaran ? (int) $penugasan->tahunPelajaran->id : null,
                'nama' => $penugasan->tahunPelajaran?->nama ?? '-',
            ],
            'aktif' => (bool) $penugasan->aktif,
            'jumlah_siswa' => (int) $ringkasan['jumlahSiswa'],
            'jumlah_pengisi' => $jumlahPengisi,
            'persentase_pengisian' => $persentase,
            'status_pengisian' => $jumlahPengisi === 0
                ? 'belum'
                : ($persentase >= 100 ? 'lengkap' : 'berjalan'),
            'hasil_terbuka' => (bool) $ringkasan['hasilTerbuka'],
            'rata_rata_keseluruhan' => $ringkasan['rataRataKeseluruhan'] !== null
                ? (float) $ringkasan['rataRataKeseluruhan']
                : null,
        ];
    }

    private function semesterSaatIni(): string
    {
        return now()->month >= 7 ? 'ganjil' : 'genap';
    }
}
