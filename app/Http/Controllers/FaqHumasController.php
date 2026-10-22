<?php

namespace App\Http\Controllers;

use App\Models\DokumenHumas;
use App\Models\FaqHumas;
use App\Services\Humas\FaqHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FaqHumasController extends Controller
{
    public function __construct(private readonly FaqHumasService $service) {}

    public function index(Request $r)
    {
        $kelola = $this->service->kelola($r->user());
        $filter = $r->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'urusan' => ['nullable', Rule::in(array_keys(DokumenHumas::URUSAN))],
            'status' => ['nullable', Rule::in(array_keys(FaqHumas::STATUS))], 'sasaran' => ['nullable', Rule::in(array_keys(FaqHumas::SASARAN))]]);
        $q = $this->service->query($r->user())->when($filter['urusan'] ?? null, fn ($q, $v) => $q->where('urusan', $v));
        if (filled($filter['kata_kunci'] ?? null)) {
            $q->where(fn ($q) => $q->whereLike('pertanyaan', '%'.$filter['kata_kunci'].'%')->orWhereLike('jawaban', '%'.$filter['kata_kunci'].'%'));
        }
        if ($kelola) {
            foreach (['status', 'sasaran'] as $k) {
                if (filled($filter[$k] ?? null)) {
                    $q->where($k, $filter[$k]);
                }
            }
        }
        $p = app(HumasMobileService::class)->halaman($r);
        $daftar = $q->orderBy('urutan')->orderBy('pertanyaan')->orderBy('id')->paginate($p['per_halaman'], ['*'], $p['nama_halaman'], $p['halaman'])->withQueryString();
        if ($r->routeIs('api.v1.*')) {
            return $this->json(app(HumasMobileService::class)->paginasi($daftar, fn ($f) => $this->data($r, $f)) + ['dapat_kelola' => $kelola]);
        }

        return response()->view('faq-humas.index', compact('daftar', 'filter', 'kelola'), 200, ['Cache-Control' => 'private, no-store']);
    }

    public function referensi(Request $r)
    {
        $this->service->query($r->user());
        $kelola = $this->service->kelola($r->user());

        return $this->json(['urusan' => DokumenHumas::URUSAN, 'dapat_kelola' => $kelola]
            + ($kelola ? ['sasaran' => FaqHumas::SASARAN, 'status' => FaqHumas::STATUS, 'token_pembuatan' => (string) Str::uuid()] : []));
    }

    public function create(Request $r)
    {
        abort_unless($this->service->kelola($r->user()), 403);

        return response()->view('faq-humas.form', ['faq' => new FaqHumas(['sasaran' => 'semua', 'urusan' => 'kedinasan', 'urutan' => 0]), 'token' => (string) Str::uuid()], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function edit(Request $r, FaqHumas $faq)
    {
        abort_unless($this->service->kelola($r->user()), 403);

        return response()->view('faq-humas.form', compact('faq'), 200, ['Cache-Control' => 'private, no-store']);
    }

    public function show(Request $r, FaqHumas $faq)
    {
        $faq = $this->service->query($r->user())->findOrFail($faq->id);
        if ($r->routeIs('api.v1.*')) {
            return $this->json($this->data($r, $faq));
        }

        return response()->view('faq-humas.show', ['faq' => $faq, 'kelola' => $this->service->kelola($r->user()),
            'riwayat' => $this->service->kelola($r->user()) ? $this->audit($faq)->paginate(15) : null], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function store(Request $r)
    {
        return $this->selesai($r, $this->service->simpan($r));
    }

    public function update(Request $r, FaqHumas $faq)
    {
        return $this->selesai($r, $this->service->simpan($r, $faq));
    }

    public function status(Request $r, FaqHumas $faq)
    {
        return $this->selesai($r, $this->service->status($r, $faq));
    }

    public function riwayat(Request $r, FaqHumas $faq)
    {
        abort_unless($this->service->kelola($r->user()), 403);
        $page = app(HumasMobileService::class)->halaman($r);
        $p = $this->audit($faq)->paginate($page['per_halaman'], ['*'], $page['nama_halaman'], $page['halaman']);

        return $this->json(app(HumasMobileService::class)->paginasi($p, fn ($a) => (array) $a));
    }

    private function audit(FaqHumas $f)
    {
        return DB::table('riwayat_faq_humas')->where('induk_id', $f->id)->orderByDesc('id')->select(['id', 'aksi', 'versi', 'alasan', 'created_at']);
    }

    private function selesai(Request $r, FaqHumas $f)
    {
        return $r->routeIs('api.v1.*') ? $this->json($this->data($r, $f), $f->wasRecentlyCreated ? 201 : 200)
            : redirect()->route('faq-humas.show', $f)->with('berhasil', 'FAQ berhasil disimpan.');
    }

    private function json(array $data, int $status = 200)
    {
        return response()->json(['data' => $data], $status)->header('Cache-Control', 'private, no-store');
    }

    private function data(Request $r, FaqHumas $f): array
    {
        return $this->service->data($f) + ['status_label' => FaqHumas::STATUS[$f->status], 'urusan_label' => DokumenHumas::URUSAN[$f->urusan],
            'sasaran_label' => FaqHumas::SASARAN[$f->sasaran], 'dapat_kelola' => $this->service->kelola($r->user())];
    }
}
