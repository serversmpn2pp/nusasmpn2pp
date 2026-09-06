<?php

namespace App\Services\Mobile;

use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Services\Cbt\FinalisasiHasilUjianTerpusatService;
use App\Services\Cbt\SinkronkanPelaksanaanUjianTerpusat;
use App\Services\Cbt\TerapkanNilaiCbtService;
use Illuminate\Support\Collection;

class HasilUjianTerpusatMobileService
{
    public function __construct(
        private readonly SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
        private readonly MonitoringHasilAsesmenKelasMobileService $perhitungan,
        private readonly TerapkanNilaiCbtService $penerapanNilai,
        private readonly FinalisasiHasilUjianTerpusatService $finalisasiHasil,
    ) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = mb_strtolower(trim((string) ($filter['kata_kunci'] ?? '')));
        $status = (string) ($filter['status'] ?? 'semua');
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        $perHalaman = min(30, max(5, (int) ($filter['per_halaman'] ?? 12)));

        $cakupan = KegiatanUjianCbt::query()
            ->where('status', '!=', 'nonaktif')
            ->with([
                'jenisUjianCbt:id,nama',
                'tahunPelajaran:id,nama',
                'jadwalUjianCbt' => fn ($query) => $query
                    ->with(['mataPelajaran:id,nama', 'ujianCbt']),
            ])
            ->orderByDesc('tanggal_mulai')->orderByDesc('id')->get()
            ->filter(fn (KegiatanUjianCbt $kegiatan) => $this->jadwalDalamCakupan($pengguna, $kegiatan)->isNotEmpty())
            ->values();
        $semua = $cakupan
            ->when($status !== 'semua', fn (Collection $items) => $items->where('status', $status))
            ->when($kataKunci !== '', fn (Collection $items) => $items->filter(function (KegiatanUjianCbt $item) use ($kataKunci): bool {
                $teks = mb_strtolower(implode(' ', [
                    $item->nama, $item->kode, $item->jenisUjianCbt?->nama, $item->tahunPelajaran?->nama,
                ]));

                return str_contains($teks, $kataKunci);
            }))->values();

        $total = $semua->count();
        $items = $semua->slice(($halaman - 1) * $perHalaman, $perHalaman)->values();
        $paketIds = $items->flatMap(fn (KegiatanUjianCbt $item) => $this
            ->jadwalDalamCakupan($pengguna, $item)->pluck('ujian_cbt_id'))->filter()->unique();
        $ringkasanPaket = PesertaUjianCbt::query()
            ->whereIn('ujian_cbt_id', $paketIds)
            ->selectRaw("ujian_cbt_id, count(*) as total,
                sum(case when status = 'selesai' then 1 else 0 end) as peserta_selesai,
                sum(case when nilai_diterapkan_pada is not null then 1 else 0 end) as diterapkan")
            ->groupBy('ujian_cbt_id')->get()->keyBy('ujian_cbt_id');

        return [
            'ringkasan' => [
                'total' => $cakupan->count(),
                'aktif' => $cakupan->where('status', 'aktif')->count(),
                'selesai' => $cakupan->where('status', 'selesai')->count(),
            ],
            'items' => $items->map(function (KegiatanUjianCbt $item) use ($pengguna, $ringkasanPaket): array {
                $jadwal = $this->jadwalDalamCakupan($pengguna, $item);
                $paketIds = $jadwal->pluck('ujian_cbt_id')->filter();

                return [
                    'id' => (int) $item->id,
                    'kode' => $item->kode,
                    'nama' => $item->nama,
                    'jenis' => $item->jenisUjianCbt?->nama,
                    'tahun_pelajaran' => $item->tahunPelajaran?->nama,
                    'semester' => ucfirst((string) $item->semester),
                    'periode' => $item->labelPeriode(),
                    'status' => $item->status,
                    'label_status' => $item->labelStatus(),
                    'jumlah_jadwal' => $jadwal->count(),
                    'jumlah_peserta' => $paketIds->sum(fn ($id) => (int) ($ringkasanPaket->get($id)?->total ?? 0)),
                    'peserta_selesai' => $paketIds->sum(fn ($id) => (int) ($ringkasanPaket->get($id)?->peserta_selesai ?? 0)),
                    'sudah_masuk_nilai' => $paketIds->sum(fn ($id) => (int) ($ringkasanPaket->get($id)?->diterapkan ?? 0)),
                ];
            }),
            'referensi' => ['status' => collect(['semua' => 'Semua status'] + KegiatanUjianCbt::DAFTAR_STATUS)
                ->map(fn (string $label, string $kode) => ['kode' => $kode, 'label' => $label])->values()],
            'filter' => ['kata_kunci' => $filter['kata_kunci'] ?? '', 'status' => $status],
            'paginasi' => [
                'halaman' => $halaman,
                'halaman_terakhir' => max(1, (int) ceil($total / $perHalaman)),
                'total' => $total,
                'ada_halaman_berikutnya' => $halaman * $perHalaman < $total,
            ],
        ];
    }

    public function rincian(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, array $filter): array
    {
        $kegiatan->load('jadwalUjianCbt.ujianCbt');
        abort_if($this->jadwalDalamCakupan($pengguna, $kegiatan)->isEmpty(), 403);

        $this->sinkronisasi->sinkronkanKegiatan($kegiatan, $pengguna);
        $kegiatan->load(['jenisUjianCbt:id,nama', 'tahunPelajaran:id,nama', 'jadwalUjianCbt' => fn ($query) => $query
            ->with(['mataPelajaran:id,nama', 'sesiKegiatanUjianCbt:id,nama', 'kelas:id,nama', 'ujianCbt'])
            ->orderBy('tanggal')->orderBy('waktu_mulai')->orderBy('tingkat')]);
        $jadwal = $this->jadwalDalamCakupan($pengguna, $kegiatan);

        $jadwalId = filled($filter['jadwal_id'] ?? null) ? (int) $filter['jadwal_id'] : null;
        $terpilih = $jadwalId ? $jadwal->firstWhere('id', $jadwalId) : $jadwal->first(fn ($item) => $item->ujianCbt);
        abort_if($jadwalId && ! $terpilih, 404);
        $hasil = $terpilih?->ujianCbt
            ? $this->perhitungan->hasilUjianTerpusat($terpilih->ujianCbt, $filter)
            : $this->hasilKosong();

        return [
            'dihasilkan_pada' => now()->toISOString(),
            'kegiatan' => [
                'id' => (int) $kegiatan->id,
                'kode' => $kegiatan->kode,
                'nama' => $kegiatan->nama,
                'jenis' => $kegiatan->jenisUjianCbt?->nama,
                'tahun_pelajaran' => $kegiatan->tahunPelajaran?->nama,
                'semester' => ucfirst((string) $kegiatan->semester),
                'periode' => $kegiatan->labelPeriode(),
                'status' => $kegiatan->status,
                'label_status' => $kegiatan->labelStatus(),
            ],
            'jadwal' => $jadwal->map(fn (JadwalUjianCbt $item) => [
                'id' => (int) $item->id,
                'label' => implode(' · ', array_filter([
                    $item->tanggal?->format('d-m-Y'), substr((string) $item->waktu_mulai, 0, 5),
                    $item->mataPelajaran?->nama, $item->tingkat ? 'Kelas '.$item->tingkat : null,
                ])),
                'mata_pelajaran' => $item->mataPelajaran?->nama ?? '-',
                'tanggal' => $item->tanggal?->toDateString(),
                'waktu' => $item->labelWaktu(),
                'tingkat' => (int) $item->tingkat,
                'jumlah_peserta' => $item->ujianCbt?->pesertaUjianCbt()->count() ?? 0,
                'dapat_menerapkan_nilai' => $item->ujianCbt?->dapatDikelolaOleh($pengguna) ?? false,
                'status_hasil' => $item->ujianCbt?->tampilkan_hasil
                    ? 'dipublikasikan'
                    : ($item->ujianCbt?->hasil_difinalisasi_pada ? 'final' : 'draf'),
                'paket_tersedia' => (bool) $item->ujianCbt,
            ])->values(),
            'jadwal_terpilih_id' => $terpilih ? (int) $terpilih->id : null,
            'dapat_menerapkan_nilai' => $terpilih?->ujianCbt?->dapatDikelolaOleh($pengguna) ?? false,
            'finalisasi' => $terpilih?->ujianCbt
                ? $this->finalisasiHasil->ringkasan($pengguna, $terpilih->ujianCbt)
                : $this->finalisasiKosong(),
            'hasil' => $hasil,
        ];
    }

    public function terapkanNilai(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
    ): array {
        abort_unless((int) $jadwal->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
        $jadwal->loadMissing('ujianCbt');
        abort_unless($jadwal->ujianCbt?->ujianTerpusat(), 404);
        abort_unless($jadwal->ujianCbt->dapatDikelolaOleh($pengguna), 403);
        abort_unless(
            $jadwal->ujianCbt->hasil_difinalisasi_pada,
            422,
            'Finalisasi hasil ujian terlebih dahulu sebelum menerapkannya ke nilai siswa.',
        );

        return $this->penerapanNilai->terapkan($jadwal->ujianCbt, $pengguna->id);
    }

    public function ubahFinalisasi(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
        bool $final,
    ): array {
        return $final
            ? $this->finalisasiHasil->finalisasi($pengguna, $kegiatan, $jadwal)
            : $this->finalisasiHasil->batalkanFinalisasi($pengguna, $kegiatan, $jadwal);
    }

    public function ubahPublikasi(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
        bool $dipublikasikan,
    ): array {
        return $dipublikasikan
            ? $this->finalisasiHasil->publikasikan($pengguna, $kegiatan, $jadwal)
            : $this->finalisasiHasil->batalkanPublikasi($pengguna, $kegiatan, $jadwal);
    }

    private function jadwalDalamCakupan(Pengguna $pengguna, KegiatanUjianCbt $kegiatan): Collection
    {
        $aksesPenuh = $kegiatan->dapatDiaksesOleh($pengguna);

        return $kegiatan->jadwalUjianCbt
            ->filter(fn (JadwalUjianCbt $item) => $item->ujianCbt
                && ($aksesPenuh || $item->ujianCbt->dapatDikelolaOleh($pengguna)))
            ->values();
    }

    private function hasilKosong(): array
    {
        return [
            'asesmen' => null, 'jumlah_soal' => 0, 'bobot_total' => 0,
            'ringkasan' => [
                'total_peserta' => 0, 'selesai' => 0, 'hasil_final' => 0,
                'rata_rata_final' => null, 'nilai_tertinggi_final' => null,
                'nilai_terendah_final' => null, 'tuntas' => 0, 'belum_tuntas' => 0,
                'perlu_koreksi' => 0, 'belum_selesai' => 0, 'sudah_masuk_nilai' => 0,
            ],
            'referensi' => ['kelas' => [], 'status' => []],
            'filter' => ['kelas_id' => null, 'status' => 'semua'], 'items' => [],
        ];
    }

    private function finalisasiKosong(): array
    {
        return [
            'status' => 'draf', 'label_status' => 'Draf hasil',
            'dapat_mengelola' => false, 'siap_difinalisasi' => false,
            'dapat_finalisasi' => false, 'dapat_batalkan_finalisasi' => false,
            'dapat_publikasi' => false, 'dapat_batalkan_publikasi' => false,
            'difinalisasi_pada' => null, 'difinalisasi_oleh' => null,
            'dipublikasikan_pada' => null, 'dipublikasikan_oleh' => null,
            'kesiapan' => [
                'siap' => false, 'total_peserta' => 0, 'peserta_wajib_selesai' => 0,
                'peserta_selesai' => 0, 'peserta_belum_selesai' => 0,
                'peserta_tidak_hadir' => 0, 'perlu_koreksi_manual' => 0,
                'jumlah_soal' => 0,
            ],
        ];
    }
}
