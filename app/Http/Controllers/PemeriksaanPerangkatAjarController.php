<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\JenisPerangkatAjar;
use App\Models\Pegawai;
use App\Models\PerangkatAjar;
use App\Models\TahunPelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\PerangkatAjar\PenugasanPerangkatAjarService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PemeriksaanPerangkatAjarController extends Controller
{
    public function __construct(
        private NotifikasiPenggunaService $notifikasiPenggunaService,
        private PenugasanPerangkatAjarService $penugasanPerangkatAjarService,
    ) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'semester' => ['nullable', Rule::in(['1', '2'])],
            'kelengkapan' => ['nullable', Rule::in(['semua', 'lengkap', 'belum_lengkap'])],
            'status_dokumen' => ['nullable', Rule::in(['semua', 'belum_diunggah', 'menunggu_pemeriksaan', 'perlu_perbaikan', 'sudah_diperiksa'])],
            'kata_kunci' => ['nullable', 'string', 'max:120'],
        ]);

        $tahunPelajaran = $this->daftarTahunPelajaran();
        $tahunPelajaranId = $this->ambilTahunPelajaranId($data['tahun_pelajaran_id'] ?? null, $tahunPelajaran);
        $semester = (int) ($data['semester'] ?? 1);
        $kelengkapan = $data['kelengkapan'] ?? 'semua';
        $statusDokumen = $data['status_dokumen'] ?? 'semua';
        $kataKunci = trim((string) ($data['kata_kunci'] ?? ''));
        $monitoring = $this->buatMonitoringGuru($tahunPelajaranId, $semester);
        $ringkasan = [
            'jumlah_guru' => $monitoring->count(),
            'lengkap' => $monitoring->where('lengkap', true)->count(),
            'belum_lengkap' => $monitoring->where('lengkap', false)->count(),
            'menunggu_pemeriksaan' => $monitoring->sum('jumlah_menunggu'),
            'perlu_perbaikan' => $monitoring->sum('jumlah_perlu_perbaikan'),
        ];

        $monitoring = $monitoring
            ->when($kelengkapan === 'lengkap', fn (Collection $items) => $items->where('lengkap', true))
            ->when($kelengkapan === 'belum_lengkap', fn (Collection $items) => $items->where('lengkap', false))
            ->when($statusDokumen === 'belum_diunggah', fn (Collection $items) => $items->filter(
                fn (array $item) => $item['jumlah_terunggah_wajib'] < $item['jumlah_wajib']
            ))
            ->when($statusDokumen === 'menunggu_pemeriksaan', fn (Collection $items) => $items->where('jumlah_menunggu', '>', 0))
            ->when($statusDokumen === 'perlu_perbaikan', fn (Collection $items) => $items->where('jumlah_perlu_perbaikan', '>', 0))
            ->when($statusDokumen === 'sudah_diperiksa', fn (Collection $items) => $items->where('jumlah_sudah_diperiksa', '>', 0))
            ->when($kataKunci !== '', fn (Collection $items) => $items->filter(function (array $item) use ($kataKunci) {
                $haystack = collect([
                    $item['pegawai']->nama_lengkap,
                    $item['pegawai']->nip,
                    $item['mata_pelajaran']->pluck('nama')->implode(' '),
                ])->filter()->implode(' ');

                return Str::contains(Str::lower($haystack), Str::lower($kataKunci));
            }))
            ->values();

        return view('pemeriksaan-perangkat-ajar.index', [
            'monitoringGuru' => $this->paginate($request, $monitoring),
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'semester' => $semester,
            'kelengkapan' => $kelengkapan,
            'statusDokumen' => $statusDokumen,
            'kataKunci' => $kataKunci,
            'ringkasan' => $ringkasan,
        ]);
    }

    public function show(Request $request, Pegawai $pegawai)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'semester' => ['nullable', Rule::in(['1', '2'])],
        ]);

        $tahunPelajaran = $this->daftarTahunPelajaran();
        $tahunPelajaranId = $this->ambilTahunPelajaranId($data['tahun_pelajaran_id'] ?? null, $tahunPelajaran);
        $semester = (int) ($data['semester'] ?? 1);
        $penugasanPerTingkat = $this->penugasanPerangkatAjarService
            ->untukGuru($pegawai->id, $tahunPelajaranId);
        abort_if($penugasanPerTingkat->isEmpty(), 404);
        $mataPelajaran = $penugasanPerTingkat
            ->pluck('mata_pelajaran')
            ->unique('id')
            ->values();

        $jenisPerangkatAjar = $this->jenisPerangkatAktif();
        $semuaPerangkatAjar = PerangkatAjar::query()
            ->with(['mataPelajaran', 'jenisPerangkatAjar', 'pemeriksa'])
            ->where('pegawai_id', $pegawai->id)
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->where('semester', $semester)
            ->whereIn('mata_pelajaran_id', $mataPelajaran->pluck('id'))
            ->whereIn('jenis_perangkat_ajar_id', $jenisPerangkatAjar->pluck('id'))
            ->get();
        $kunciPenugasan = $penugasanPerTingkat->pluck('kunci');
        $perangkatTanpaTingkat = $semuaPerangkatAjar
            ->whereNull('tingkat')
            ->values();
        $perangkatAjar = $semuaPerangkatAjar
            ->filter(fn (PerangkatAjar $item) => (
                $item->tingkat
                && $kunciPenugasan->contains(
                    $this->penugasanPerangkatAjarService->kunci($item->mata_pelajaran_id, $item->tingkat)
                )
            ))
            ->keyBy(fn (PerangkatAjar $item) => $this->kunciPerangkat(
                $item->mata_pelajaran_id,
                $item->tingkat,
                $item->jenis_perangkat_ajar_id,
            ));
        $jenisWajibIds = $jenisPerangkatAjar->where('wajib', true)->pluck('id');
        $jumlahWajib = $penugasanPerTingkat->count() * $jenisWajibIds->count();
        $jumlahTerunggahWajib = $perangkatAjar->whereIn('jenis_perangkat_ajar_id', $jenisWajibIds)->count();

        return view('pemeriksaan-perangkat-ajar.show', [
            'pegawai' => $pegawai,
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'semester' => $semester,
            'mataPelajaran' => $mataPelajaran,
            'penugasanPerTingkat' => $penugasanPerTingkat,
            'jenisPerangkatAjar' => $jenisPerangkatAjar,
            'perangkatAjar' => $perangkatAjar,
            'perangkatTanpaTingkat' => $perangkatTanpaTingkat,
            'jumlahWajib' => $jumlahWajib,
            'jumlahTerunggahWajib' => $jumlahTerunggahWajib,
            'jumlahMenunggu' => $perangkatAjar->where('status', 'menunggu_pemeriksaan')->count(),
            'jumlahPerluPerbaikan' => $perangkatAjar->where('status', 'perlu_perbaikan')->count(),
            'jumlahSudahDiperiksa' => $perangkatAjar->where('status', 'sudah_diperiksa')->count(),
        ]);
    }

    public function edit(PerangkatAjar $perangkatAjar)
    {
        $perangkatAjar->load([
            'pegawai',
            'tahunPelajaran',
            'mataPelajaran',
            'jenisPerangkatAjar',
            'pemeriksa',
        ]);

        return view('pemeriksaan-perangkat-ajar.edit', compact('perangkatAjar'));
    }

    public function preview(PerangkatAjar $perangkatAjar)
    {
        abort_unless(Storage::disk('local')->exists($perangkatAjar->lokasi_file), 404);

        return Storage::disk('local')->response(
            $perangkatAjar->lokasi_file,
            $perangkatAjar->nama_file_asli,
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    public function update(Request $request, PerangkatAjar $perangkatAjar)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['perlu_perbaikan', 'sudah_diperiksa'])],
            'catatan_pemeriksa' => ['nullable', 'required_if:status,perlu_perbaikan', 'string'],
        ]);
        $data['catatan_pemeriksa'] = filled($data['catatan_pemeriksa'] ?? null)
            ? trim($data['catatan_pemeriksa'])
            : null;
        $data['pemeriksa_pegawai_id'] = $request->user()?->pegawai_id;
        $data['diperiksa_pada'] = now();
        $perangkatAjar->update($data);
        $perangkatAjar->loadMissing(['mataPelajaran', 'jenisPerangkatAjar']);

        $statusDisetujui = $perangkatAjar->status === 'sudah_diperiksa';
        $this->notifikasiPenggunaService->kirimKeBanyak(
            $this->notifikasiPenggunaService->penggunaUntukPegawai($perangkatAjar->pegawai_id),
            $statusDisetujui ? 'berhasil' : 'penting',
            $statusDisetujui ? 'Perangkat ajar sudah diperiksa' : 'Perangkat ajar perlu diperbaiki',
            sprintf(
                '%s untuk mata pelajaran %s tingkat %s telah diperiksa.%s',
                $perangkatAjar->jenisPerangkatAjar?->nama ?? 'Perangkat ajar',
                $perangkatAjar->mataPelajaran?->nama ?? '-',
                $perangkatAjar->tingkatTampil(),
                $perangkatAjar->catatan_pemeriksa ? ' Catatan: '.$perangkatAjar->catatan_pemeriksa : '',
            ),
            route('perangkat-ajar-saya.show', $perangkatAjar, false),
            "perangkat-ajar-diperiksa:{$perangkatAjar->id}:{$perangkatAjar->status}:{$perangkatAjar->diperiksa_pada->format('Uv')}",
        );

        return redirect()
            ->route('pemeriksaan-perangkat-ajar.show', [
                'pegawai' => $perangkatAjar->pegawai_id,
                'tahun_pelajaran_id' => $perangkatAjar->tahun_pelajaran_id,
                'semester' => $perangkatAjar->semester,
            ])
            ->with('berhasil', 'Hasil pemeriksaan perangkat ajar berhasil disimpan.');
    }

    private function buatMonitoringGuru(?int $tahunPelajaranId, int $semester): Collection
    {
        if (! $tahunPelajaranId) {
            return collect();
        }

        $jenisPerangkatAjar = $this->jenisPerangkatAktif();
        $jenisWajibIds = $jenisPerangkatAjar->where('wajib', true)->pluck('id');
        $penugasan = GuruMataPelajaran::query()
            ->with(['pegawai', 'mataPelajaran', 'kelas'])
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('aktif', true)
            ->whereHas('pegawai', fn ($query) => $query->where('aktif', true))
            ->whereHas('mataPelajaran', fn ($query) => $query->where('aktif', true))
            ->whereHas('kelas', fn ($query) => $query
                ->where('aktif', true)
                ->whereIn('tingkat', [7, 8, 9]))
            ->get()
            ->groupBy('pegawai_id');
        $perangkatAjar = PerangkatAjar::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('semester', $semester)
            ->whereIn('jenis_perangkat_ajar_id', $jenisPerangkatAjar->pluck('id'))
            ->get()
            ->groupBy('pegawai_id');

        return $penugasan
            ->map(function (Collection $penugasanGuru, $pegawaiId) use ($perangkatAjar, $jenisWajibIds) {
                $penugasanPerTingkat = $this->penugasanPerangkatAjarService->ringkas($penugasanGuru);
                $mataPelajaran = $penugasanGuru
                    ->pluck('mataPelajaran')
                    ->filter()
                    ->unique('id')
                    ->sortBy('nama')
                    ->values();
                $mapelIds = $mataPelajaran->pluck('id');
                $kunciPenugasan = $penugasanPerTingkat->pluck('kunci');
                $dokumenGuru = $perangkatAjar
                    ->get($pegawaiId, collect())
                    ->whereIn('mata_pelajaran_id', $mapelIds)
                    ->filter(fn (PerangkatAjar $item) => (
                        $item->tingkat
                        && $kunciPenugasan->contains(
                            $this->penugasanPerangkatAjarService->kunci($item->mata_pelajaran_id, $item->tingkat)
                        )
                    ));
                $jumlahWajib = $penugasanPerTingkat->count() * $jenisWajibIds->count();
                $jumlahTerunggahWajib = $dokumenGuru->whereIn('jenis_perangkat_ajar_id', $jenisWajibIds)->count();

                return [
                    'pegawai' => $penugasanGuru->first()->pegawai,
                    'mata_pelajaran' => $mataPelajaran,
                    'penugasan_per_tingkat' => $penugasanPerTingkat,
                    'jumlah_wajib' => $jumlahWajib,
                    'jumlah_terunggah_wajib' => $jumlahTerunggahWajib,
                    'persentase' => $jumlahWajib > 0 ? min(100, round($jumlahTerunggahWajib / $jumlahWajib * 100)) : 0,
                    'lengkap' => $jumlahWajib > 0 && $jumlahTerunggahWajib >= $jumlahWajib,
                    'jumlah_menunggu' => $dokumenGuru->where('status', 'menunggu_pemeriksaan')->count(),
                    'jumlah_perlu_perbaikan' => $dokumenGuru->where('status', 'perlu_perbaikan')->count(),
                    'jumlah_sudah_diperiksa' => $dokumenGuru->where('status', 'sudah_diperiksa')->count(),
                ];
            })
            ->sortBy(fn (array $item) => $item['pegawai']->nama_lengkap)
            ->values();
    }

    private function jenisPerangkatAktif(): Collection
    {
        return JenisPerangkatAjar::query()
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
    }

    private function daftarTahunPelajaran(): Collection
    {
        return TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
    }

    private function ambilTahunPelajaranId(?int $tahunPelajaranId, Collection $tahunPelajaran): ?int
    {
        if ($tahunPelajaranId && $tahunPelajaran->contains('id', $tahunPelajaranId)) {
            return $tahunPelajaranId;
        }

        return $tahunPelajaran->firstWhere('aktif', true)?->id ?? $tahunPelajaran->first()?->id;
    }

    private function paginate(Request $request, Collection $items): LengthAwarePaginator
    {
        $halaman = max(1, (int) $request->input('page', 1));
        $perHalaman = 10;

        return new LengthAwarePaginator(
            $items->forPage($halaman, $perHalaman)->values(),
            $items->count(),
            $perHalaman,
            $halaman,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    private function kunciPerangkat(int $mataPelajaranId, int $tingkat, int $jenisPerangkatAjarId): string
    {
        return $mataPelajaranId.'-'.$tingkat.'-'.$jenisPerangkatAjarId;
    }
}
