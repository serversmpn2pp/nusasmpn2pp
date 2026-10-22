<?php

namespace App\Http\Controllers;

use App\Models\KinerjaSemesterHumas;
use App\Models\TahunPelajaran;
use App\Services\Humas\KinerjaSemesterHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KinerjaSemesterHumasController extends Controller
{
    public function __construct(private readonly KinerjaSemesterHumasService $service) {}

    public function index(Request $r)
    {
        $this->service->akses($r->user());
        $filter = $r->validate(['tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'semester' => ['nullable', Rule::in(array_keys(KinerjaSemesterHumas::SEMESTER))], 'status' => ['nullable', Rule::in(array_keys(KinerjaSemesterHumas::STATUS))]]);
        $q = KinerjaSemesterHumas::query();
        foreach ($filter as $k => $v) {
            if (filled($v)) {
                $q->where($k, $v);
            }
        }
        $daftar = $q->orderByDesc('tanggal_laporan')->orderByDesc('id')->paginate(15)->withQueryString();
        if ($r->routeIs('api.v1.*')) {
            return $this->json(app(HumasMobileService::class)->paginasi($daftar, fn ($l) => $this->data($r, $l, false)));
        }

        return response()->view('kinerja-humas.index', ['daftar' => $daftar, 'filter' => $filter, 'tahun' => $this->tahun(),
            'kelola' => $r->user()->memilikiIzin('kinerja_humas.kelola')], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function referensi(Request $r)
    {
        $this->service->akses($r->user());

        return $this->json(['tahun_pelajaran' => $this->tahun(), 'semester' => KinerjaSemesterHumas::SEMESTER, 'status' => KinerjaSemesterHumas::STATUS,
            'dapat_kelola' => $r->user()->memilikiIzin('kinerja_humas.kelola')]
            + ($r->user()->memilikiIzin('kinerja_humas.kelola') ? ['token_pembuatan' => (string) Str::uuid()] : []));
    }

    public function pratinjau(Request $r)
    {
        return $this->json($this->service->pratinjau($r));
    }

    public function create(Request $r)
    {
        $this->service->akses($r->user(), true);

        return response()->view('kinerja-humas.form', ['laporan' => new KinerjaSemesterHumas(['judul' => 'Laporan Kinerja Humas', 'semester' => 'ganjil',
            'tanggal_laporan' => today(), 'nama_penyusun' => $r->user()->nama]), 'tahun' => $this->tahun(), 'token' => (string) Str::uuid()], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function edit(Request $r, KinerjaSemesterHumas $kinerja)
    {
        $this->service->akses($r->user(), true);
        abort_unless($kinerja->status === 'draf' && ! $this->service->tampil($kinerja->snapshot, $r->user())['akses_dibatasi'], 403);

        return response()->view('kinerja-humas.form', ['laporan' => $kinerja, 'tahun' => $this->tahun()], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function show(Request $r, KinerjaSemesterHumas $kinerja)
    {
        $this->service->akses($r->user());
        $data = $this->data($r, $kinerja);
        if ($r->routeIs('api.v1.*')) {
            return $this->json($data);
        }

        return response()->view('kinerja-humas.show', ['laporan' => $kinerja, 'snapshot' => $data['snapshot'], 'kelola' => $data['dapat_kelola'],
            'riwayat' => $this->audit($kinerja)->paginate(15)], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function cetak(Request $r, KinerjaSemesterHumas $kinerja)
    {
        $this->service->akses($r->user());

        return response()->view('kinerja-humas.cetak', ['laporan' => $kinerja, 'snapshot' => $this->service->tampil($kinerja->snapshot, $r->user())],
            200, ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function store(Request $r)
    {
        return $this->selesai($r, $this->service->simpan($r));
    }

    public function update(Request $r, KinerjaSemesterHumas $kinerja)
    {
        return $this->selesai($r, $this->service->simpan($r, $kinerja));
    }

    public function tindakan(Request $r, KinerjaSemesterHumas $kinerja, string $aksi)
    {
        return $this->selesai($r, $this->service->tindakan($r, $kinerja, $aksi));
    }

    public function riwayat(Request $r, KinerjaSemesterHumas $kinerja)
    {
        $this->service->akses($r->user());

        return $this->json(app(HumasMobileService::class)->paginasi($this->audit($kinerja)->paginate(15), fn ($a) => (array) $a));
    }

    private function tahun()
    {
        return TahunPelajaran::orderByDesc('tanggal_mulai')->get(['id', 'nama', 'aktif']);
    }

    private function audit(KinerjaSemesterHumas $l)
    {
        return DB::table('riwayat_kinerja_semester_humas')->where('induk_id', $l->id)->orderByDesc('id')->select(['id', 'aksi', 'versi', 'alasan', 'created_at']);
    }

    private function data(Request $r, KinerjaSemesterHumas $l, bool $rinci = true): array
    {
        $s = $this->service->tampil($l->snapshot, $r->user());

        return $l->only(['id', 'tahun_pelajaran_id', 'semester', 'judul', 'status', 'versi']) + ['tahun_pelajaran' => $l->snapshot['tahun_pelajaran'],
            'tanggal_laporan' => $l->tanggal_laporan->toDateString(), 'dapat_kelola' => $r->user()->memilikiIzin('kinerja_humas.kelola') && ! $s['akses_dibatasi']]
            + ($rinci ? $l->only(KinerjaSemesterHumas::KOLOM) + ['snapshot' => $s] : []);
    }

    private function selesai(Request $r, KinerjaSemesterHumas $l)
    {
        return $r->routeIs('api.v1.*') ? $this->json($this->data($r, $l), $l->wasRecentlyCreated ? 201 : 200)
            : redirect()->route('kinerja-humas.show', $l)->with('berhasil', 'Laporan kinerja berhasil disimpan.');
    }

    private function json(array $data, int $status = 200)
    {
        return response()->json(['data' => $data], $status)->header('Cache-Control', 'private, no-store');
    }
}
