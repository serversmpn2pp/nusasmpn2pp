<?php

namespace App\Http\Controllers;

use App\Models\DokumenHumas;
use App\Models\KontakHumas;
use App\Services\Humas\DirektoriKontakHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KontakHumasController extends Controller
{
    public function __construct(private readonly DirektoriKontakHumasService $service) {}

    public function index(Request $r)
    {
        [$halaman, $filter] = $this->service->daftar($r);
        $data = $halaman->getCollection()->map(fn ($k) => $this->service->data($k, $r->user()));
        if ($r->routeIs('api.v1.*')) {
            return $this->json(app(HumasMobileService::class)->paginasi($halaman, fn ($k) => $this->service->data($k, $r->user())) + ['filter' => $filter]);
        }
        $halaman->setCollection($data);

        return response()->view('kontak-humas.index', ['daftar' => $halaman, 'filter' => $filter,
            'statistik' => KontakHumas::selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status'),
            'rinci' => $this->service->rinci($r->user())], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function referensi(Request $r)
    {
        $this->service->akses($r->user());

        return $this->json(['urusan' => DokumenHumas::URUSAN, 'status' => KontakHumas::STATUS, 'token_pembuatan' => (string) Str::uuid(),
            'hak_akses' => ['dapat_kelola' => $r->user()->memilikiIzin('kontak_humas.kelola'), 'dapat_lihat_kontak' => $this->service->rinci($r->user())]]);
    }

    public function create(Request $r)
    {
        $this->service->akses($r->user(), true);

        return response()->view('kontak-humas.form', ['kontak' => new KontakHumas(['status' => 'aktif']), 'token' => (string) Str::uuid()], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function edit(Request $r, KontakHumas $kontak)
    {
        $this->service->akses($r->user(), true);

        return response()->view('kontak-humas.form', ['kontak' => $kontak, 'token' => null], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function store(Request $r)
    {
        return $this->selesai($r, $this->service->simpan($r));
    }

    public function update(Request $r, KontakHumas $kontak)
    {
        return $this->selesai($r, $this->service->simpan($r, $kontak));
    }

    public function show(Request $r, KontakHumas $kontak)
    {
        $data = $this->service->data($kontak, $r->user());
        if ($r->routeIs('api.v1.*')) {
            return $this->json($data);
        }

        return response()->view('kontak-humas.show', ['kontak' => $data, 'riwayat' => $this->service->rinci($r->user())
            ? DB::table('riwayat_kontak_humas')->where('kontak_humas_id', $kontak->id)->orderByDesc('id')->paginate(15) : null],
            200, ['Cache-Control' => 'private, no-store']);
    }

    public function riwayat(Request $r, KontakHumas $kontak)
    {
        $this->service->akses($r->user());
        abort_unless($this->service->rinci($r->user()), 403);
        $filter = $r->validate(['halaman' => ['nullable', 'integer', 'min:1'], 'per_halaman' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $page = DB::table('riwayat_kontak_humas')->where('kontak_humas_id', $kontak->id)->orderByDesc('id')
            ->paginate($filter['per_halaman'] ?? 20, ['id', 'aksi', 'kolom_berubah', 'alasan', 'created_at'], 'halaman', $filter['halaman'] ?? 1);

        return $this->json(app(HumasMobileService::class)->paginasi($page, fn ($row) => [
            'id' => $row->id, 'aksi' => $row->aksi, 'kolom_berubah' => json_decode($row->kolom_berubah, true),
            'alasan' => $row->alasan, 'created_at' => $row->created_at,
        ]));
    }

    private function selesai(Request $r, KontakHumas $k)
    {
        return $r->routeIs('api.v1.*') ? $this->json($this->service->data($k, $r->user()), $k->wasRecentlyCreated ? 201 : 200)
            : redirect()->route('kontak-humas.show', $k)->with('berhasil', 'Data kontak berhasil disimpan.');
    }

    private function json(array $data, int $status = 200)
    {
        return response()->json(['data' => $data], $status)->header('Cache-Control', 'private, no-store');
    }
}
