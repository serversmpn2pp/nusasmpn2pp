<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\PengecualianPresensiSiswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\PengecualianPresensiSiswaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengecualianPresensiController extends Controller
{
    public function __construct(private readonly PengecualianPresensiSiswaService $layanan) {}

    public function index(Request $request): JsonResponse
    {
        $this->layanan->pastikanAkses($request->user());
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'dibatalkan'])],
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $q = PengecualianPresensiSiswa::query()
            ->when($filter['tahun_pelajaran_id'] ?? null, fn ($q, $id) => $q->where('tahun_pelajaran_id', $id))
            ->when($filter['kelas_id'] ?? null, fn ($q, $id) => $q->where(fn ($q) => $q->where('kelas_id', $id)->orWhereNull('kelas_id')))
            ->when(($filter['status'] ?? 'semua') !== 'semua', fn ($q) => $q->where('aktif', $filter['status'] === 'aktif'))
            ->when($filter['kata_kunci'] ?? null, fn ($q, $s) => $q->where('alasan', 'like', '%'.$s.'%'));
        $ringkasan = ['total' => (clone $q)->count(), 'aktif' => (clone $q)->where('aktif', true)->count(),
            'dibatalkan' => (clone $q)->where('aktif', false)->count()];
        $page = $q->with(['tahunPelajaran', 'kelas', 'pembuat', 'pembatal'])->orderByDesc('aktif')->orderByDesc('id')
            ->paginate($filter['per_halaman'] ?? 20, ['*'], 'halaman', $filter['halaman'] ?? 1);

        return $this->json(['items' => $page->getCollection()->map(fn ($p) => $this->record($p))->values(),
            'ringkasan' => $ringkasan, 'ringkasan_mengikuti_filter' => true, 'filter' => $filter,
            'paginasi' => ['halaman' => $page->currentPage(), 'halaman_terakhir' => $page->lastPage(),
                'per_halaman' => $page->perPage(), 'total' => $page->total(), 'ada_halaman_berikutnya' => $page->hasMorePages()],
            'hak_akses' => ['dapat_kelola' => true]]);
    }

    public function referensi(Request $request): JsonResponse
    {
        $this->layanan->pastikanAkses($request->user());

        return $this->json([
            'tahun_pelajaran' => TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get()
                ->map(fn ($t) => ['id' => (int) $t->id, 'nama' => $t->nama, 'aktif' => (bool) $t->aktif,
                    'tanggal_mulai' => $t->tanggal_mulai?->toDateString(), 'tanggal_selesai' => $t->tanggal_selesai?->toDateString()]),
            'kelas' => Kelas::orderBy('tahun_pelajaran_id')->orderBy('tingkat')->orderBy('nama')->get()
                ->map(fn ($k) => ['id' => (int) $k->id, 'nama' => $k->nama, 'tingkat' => (int) $k->tingkat,
                    'tahun_pelajaran_id' => (int) $k->tahun_pelajaran_id]),
            'jenis' => PengecualianPresensiSiswa::JENIS,
            'batas_pratinjau_menit' => 15, 'maksimal_hari' => 367,
            'hak_akses' => ['dapat_kelola' => true],
        ]);
    }

    public function show(Request $request, PengecualianPresensiSiswa $pengecualian): JsonResponse
    {
        $this->layanan->pastikanAkses($request->user());

        return $this->json($this->record($pengecualian));
    }

    public function pratinjau(Request $request): JsonResponse
    {
        $this->layanan->pastikanAkses($request->user());
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'jenis' => ['required', Rule::in(array_keys(PengecualianPresensiSiswa::JENIS))],
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $p = $this->layanan->pratinjau($request->user(), $data);

        return $this->json(['isian' => $p['data'], 'tahun_pelajaran' => ['id' => (int) $p['tahun']->id, 'nama' => $p['tahun']->nama],
            'kelas' => $p['kelas'] ? ['id' => (int) $p['kelas']->id, 'nama' => $p['kelas']->nama] : null,
            'cakupan_label' => $p['kelas']?->nama ?? 'Seluruh sekolah',
            'jenis_label' => PengecualianPresensiSiswa::JENIS[$p['data']['jenis']], 'dampak' => $p['dampak'],
            'token' => $p['token'], 'berlaku_sampai' => now()->addMinutes(15)->toIso8601String(),
            'siswa' => $p['perSiswa']->map(fn ($r) => ['anggota_kelas_id' => (int) $r['anggota']->id,
                'nama' => $r['anggota']->siswa->nama_lengkap, 'kelas' => $r['anggota']->kelas->nama,
                'nomor_absen' => $r['anggota']->nomor_absen, 'alfa_dibatalkan' => (int) $r['alfa_dibatalkan']])->values(),
            'peringatan' => [
                'Pengecualian scan bukan bukti hadir. Scan, sakit, izin, alfa manual, dan keterlambatan asli tetap tersimpan.',
                'Poin presensi otomatis mengikuti penetapan atau pembatalan ini.',
                'Rekap rapor yang sudah disimpan tidak ditimpa. Periksa ulang setelah penetapan.',
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->layanan->pastikanAkses($request->user());
        $data = $request->validate(['token' => ['required', 'string', 'max:20000'], 'konfirmasi' => ['required', 'accepted']]);
        $p = $this->layanan->terapkan($request->user(), $data['token']);

        return $this->json($this->record($p), 'Pengecualian diterapkan. Periksa ulang rekap rapor yang telah disimpan.', $p->wasRecentlyCreated ? 201 : 200);
    }

    public function batalkan(Request $request, PengecualianPresensiSiswa $pengecualian): JsonResponse
    {
        $this->layanan->pastikanAkses($request->user());
        $data = $request->validate(['alasan_pembatalan' => ['required', 'string', 'min:10', 'max:1000'],
            'konfirmasi' => ['required', 'accepted']]);
        $this->layanan->batalkan($request->user(), $pengecualian, $data['alasan_pembatalan']);

        return $this->json($this->record($pengecualian->fresh()), 'Pengecualian dibatalkan. Perhitungan alfa otomatis kembali berlaku.');
    }

    private function record(PengecualianPresensiSiswa $p): array
    {
        $p->loadMissing(['tahunPelajaran', 'kelas', 'pembuat', 'pembatal']);

        return ['id' => (int) $p->id, 'tahun_pelajaran_id' => (int) $p->tahun_pelajaran_id,
            'tahun_pelajaran' => ['id' => (int) $p->tahun_pelajaran_id, 'nama' => $p->tahunPelajaran?->nama],
            'kelas_id' => $p->kelas_id ? (int) $p->kelas_id : null,
            'cakupan_label' => $p->kelas?->nama ?? 'Seluruh sekolah', 'jenis' => $p->jenis,
            'jenis_label' => PengecualianPresensiSiswa::JENIS[$p->jenis] ?? $p->jenis,
            'tanggal_mulai' => $p->tanggal_mulai->toDateString(), 'tanggal_selesai' => $p->tanggal_selesai->toDateString(),
            'alasan' => $p->alasan, 'aktif' => (bool) $p->aktif, 'status_label' => $p->aktif ? 'Aktif' : 'Dibatalkan',
            'dampak_pratinjau' => $p->dampak_pratinjau, 'dibuat_pada' => $p->created_at?->toIso8601String(),
            'pembuat' => $p->pembuat ? ['id' => (int) $p->pembuat->id, 'nama' => $p->pembuat->nama] : null,
            'dibatalkan_pada' => $p->dibatalkan_pada?->toIso8601String(), 'alasan_pembatalan' => $p->alasan_pembatalan,
            'pembatal' => $p->pembatal ? ['id' => (int) $p->pembatal->id, 'nama' => $p->pembatal->nama] : null,
            'hak_akses' => ['dapat_batalkan' => (bool) $p->aktif]];
    }

    private function json(array $data, ?string $pesan = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data] + ($pesan ? ['pesan' => $pesan] : []), $status)
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
