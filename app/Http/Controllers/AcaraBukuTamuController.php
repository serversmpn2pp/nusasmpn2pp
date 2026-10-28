<?php

namespace App\Http\Controllers;

use App\Models\AcaraBukuTamu;
use App\Models\ResponAcaraBukuTamu;
use App\Services\Humas\AcaraBukuTamuService;
use App\Support\PenulisExcelAcaraBukuTamu;
use App\Support\QrCodeSvg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcaraBukuTamuController extends Controller
{
    public function __construct(private readonly AcaraBukuTamuService $service) {}

    public function index(Request $r)
    {
        $this->service->akses($r->user());
        $f = $r->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(array_keys(AcaraBukuTamu::STATUS))],
            'halaman' => ['nullable', 'integer', 'min:1', 'max:100000'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $q = AcaraBukuTamu::withCount(['respon as jumlah_respon' => fn ($q) => $q->where('status', 'valid')]);
        if (! empty($f['q'])) {
            $q->where(fn ($q) => $q->whereLike('nama', '%'.$f['q'].'%')->orWhereLike('lokasi', '%'.$f['q'].'%'));
        }
        if (! empty($f['status'])) {
            $q->where('status', $f['status']);
        }
        $daftar = $q->orderByDesc('mulai_pada')->orderByDesc('id')->paginate(15, ['*'], 'page', $r->integer('halaman') ?: $r->integer('page', 1))->withQueryString();
        $data = ['daftar' => $daftar, 'filter' => $f, 'hak_akses' => $this->hak($r),
            'ringkasan' => AcaraBukuTamu::selectRaw('status, COUNT(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status')];

        return $this->api($r) ? $this->json(['items' => $daftar->getCollection()->map(fn ($a) => $this->dataAcara($a, $r))->all(), 'pagination' => $this->paginasi($daftar), 'hak_akses' => $data['hak_akses']])
            : response()->view('acara-buku-tamu.index', $data)->header('Cache-Control', 'private, no-store');
    }

    public function referensi(Request $r)
    {
        $this->service->akses($r->user());

        return $this->json(['status' => AcaraBukuTamu::STATUS, 'kategori' => ResponAcaraBukuTamu::KATEGORI,
            'minat' => ResponAcaraBukuTamu::MINAT, 'pilihan_minat_default' => AcaraBukuTamu::pilihanMinatDefault(),
            'ikon_minat' => AcaraBukuTamu::IKON_MINAT, 'maksimal_pilihan_minat' => 30, 'kepuasan' => ResponAcaraBukuTamu::KEPUASAN,
            'syarat_banner' => AcaraBukuTamu::syaratBanner(),
            'kunci_pembuatan' => (string) Str::uuid(), 'hak_akses' => $this->hak($r)]);
    }

    public function create(Request $r)
    {
        $this->service->akses($r->user(), true);

        return view('acara-buku-tamu.form', ['acara' => new AcaraBukuTamu(['status' => 'draf', 'mulai_pada' => now()->startOfDay()->addHours(8),
            'selesai_pada' => now()->startOfDay()->addHours(17)]), 'kunci' => (string) Str::uuid()]);
    }

    public function edit(Request $r, AcaraBukuTamu $acara)
    {
        $this->service->akses($r->user(), true);

        return view('acara-buku-tamu.form', ['acara' => $acara, 'kunci' => null, 'minatTerpakai' => $this->service->minatTerpakai($acara)]);
    }

    public function store(Request $r)
    {
        $a = $this->service->simpan($r);

        return $this->api($r) ? $this->json($this->dataAcara($a, $r), 201) : redirect()->route('acara-buku-tamu.show', $a)->with('berhasil', 'Acara berhasil dibuat.');
    }

    public function update(Request $r, AcaraBukuTamu $acara)
    {
        $a = $this->service->simpan($r, $acara);

        return $this->api($r) ? $this->json($this->dataAcara($a, $r)) : redirect()->route('acara-buku-tamu.show', $a)->with('berhasil', 'Pengaturan acara disimpan.');
    }

    public function show(Request $r, AcaraBukuTamu $acara)
    {
        $this->service->akses($r->user());
        $f = $this->service->filter($r, $acara);
        $daftar = $this->service->query($acara, $f)->orderByDesc('created_at')->orderByDesc('id')->paginate(25, ['*'], 'page', $r->integer('halaman') ?: $r->integer('page', 1))->withQueryString();
        $s = $this->service->statistik($acara, $f);

        return $this->api($r) ? $this->json($this->dataAcara($acara, $r) + ['statistik' => $s, 'filter' => $f, 'minat_terpakai' => $this->service->minatTerpakai($acara),
            'items' => $daftar->items(), 'pagination' => $this->paginasi($daftar)])
            : response()->view('acara-buku-tamu.show', ['acara' => $acara, 'daftar' => $daftar, 'statistik' => $s, 'filter' => $f, 'hak_akses' => $this->hak($r)])
                ->header('Cache-Control', 'private, no-store');
    }

    public function statistik(Request $r, AcaraBukuTamu $acara)
    {
        $this->service->akses($r->user());

        return $this->json($this->service->statistik($acara, $this->service->filter($r, $acara)));
    }

    public function statusRespon(Request $r, AcaraBukuTamu $acara, ResponAcaraBukuTamu $respon)
    {
        $this->service->akses($r->user(), true);
        abort_unless($respon->acara_buku_tamu_id === $acara->id, 404);
        $d = $r->validate(['status' => ['required', Rule::in(['valid', 'dibatalkan'])], 'alasan' => ['required', 'string', 'min:5', 'max:1000'], 'versi' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($r, $acara, $respon, $d) {
            AcaraBukuTamu::lockForUpdate()->findOrFail($acara->id);
            $row = ResponAcaraBukuTamu::lockForUpdate()->findOrFail($respon->id);
            if ($row->versi !== (int) $d['versi']) {
                throw ValidationException::withMessages(['versi' => 'Respons telah berubah. Muat ulang halaman.']);
            }
            if ($row->status !== $d['status']) {
                $row->update(['status' => $d['status'], 'alasan_status' => $d['alasan'], 'versi' => $row->versi + 1]);
                $this->service->riwayat($acara, $r->user()->id, 'status_respon_diubah', [$d['status']], $row->id, $d['alasan']);
            }
        });

        return $this->api($r) ? $this->json(['pesan' => 'Status respons disimpan.']) : back()->with('berhasil', 'Status respons disimpan dan statistik disesuaikan.');
    }

    public function poster(Request $r, AcaraBukuTamu $acara)
    {
        $this->service->akses($r->user());
        $d = $r->validate(['ukuran' => ['nullable', Rule::in(['A4', 'A5'])]]);
        $url = $acara->urlPublik();

        return response()->view('acara-buku-tamu.poster', ['acara' => $acara, 'ukuran' => $d['ukuran'] ?? 'A4', 'urlPublik' => $url,
            'qr' => QrCodeSvg::svg($url, 600), 'alamatLokal' => in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function pratinjau(Request $r, AcaraBukuTamu $acara)
    {
        $this->service->akses($r->user());

        return response()->view('acara-buku-tamu.publik', ['acara' => $acara, 'tiket' => [], 'sukses' => false, 'pratinjau' => true])
            ->header('Cache-Control', 'private, no-store')->header('X-Frame-Options', 'DENY');
    }

    public function qr(Request $r, AcaraBukuTamu $acara)
    {
        $this->service->akses($r->user());

        return response(QrCodeSvg::svg($acara->urlPublik(), 800), 200, ['Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-acara-'.$acara->id.'.svg"', 'Cache-Control' => 'private, no-store']);
    }

    public function banner(Request $r, AcaraBukuTamu $acara)
    {
        $this->service->akses($r->user());
        abort_unless($acara->banner && Storage::disk('local')->exists($acara->banner), 404);
        $mime = Storage::disk('local')->mimeType($acara->banner);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response()->file(Storage::disk('local')->path($acara->banner), ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function export(Request $r, AcaraBukuTamu $acara, PenulisExcelAcaraBukuTamu $excel)
    {
        $this->service->akses($r->user());
        $f = $this->service->filter($r, $acara);
        $rows = $this->service->query($acara, $f)->orderBy('created_at')->orderBy('id')->limit(10001)->get();
        if ($rows->count() > 10000) {
            throw ValidationException::withMessages(['dari' => 'Ekspor maksimal 10.000 respons. Persempit tanggal.']);
        }

        return response()->download($excel->buat($acara, $rows, $this->service->statistik($acara, $f)), 'pengunjung-acara-'.$acara->id.'.xlsx',
            ['Content-Type' => PenulisExcelAcaraBukuTamu::MIME, 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }

    public function cetak(Request $r, AcaraBukuTamu $acara)
    {
        $this->service->akses($r->user());
        $f = $this->service->filter($r, $acara);
        $rows = $this->service->query($acara, $f)->orderBy('created_at')->orderBy('id')->limit(1001)->get();
        if ($rows->count() > 1000) {
            throw ValidationException::withMessages(['dari' => 'Cetak maksimal 1.000 respons. Persempit tanggal.']);
        }

        return response()->view('acara-buku-tamu.cetak', ['acara' => $acara, 'daftar' => $rows,
            'statistik' => $this->service->statistik($acara, $f), 'filter' => $f])->header('Cache-Control', 'private, no-store');
    }

    private function dataAcara(AcaraBukuTamu $a, Request $r): array
    {
        $prefix = $this->api($r) ? 'api.v1.humas.acara-buku-tamu.' : 'acara-buku-tamu.';

        return ['acara' => array_replace(collect($a->toArray())->except('banner')->all(), ['pilihan_minat' => $a->pilihanMinat()]), 'menerima' => $a->menerima(), 'url_publik' => $a->urlPublik(),
            'url_banner' => $a->banner ? route($prefix.'banner', $a) : asset(AcaraBukuTamu::BANNER_DEFAULT),
            'url_poster' => route($prefix.'poster', $a), 'hak_akses' => $this->hak($r)];
    }

    private function hak(Request $r): array
    {
        return ['dapat_kelola' => $r->user()->memilikiIzin('buku_tamu.kelola')];
    }

    private function api(Request $r): bool
    {
        return $r->routeIs('api.v1.*');
    }

    private function json(array $data, int $status = 200)
    {
        return response()->json(['data' => $data], $status)->header('Cache-Control', 'private, no-store');
    }

    private function paginasi($p): array
    {
        return ['halaman' => $p->currentPage(), 'terakhir' => $p->lastPage(), 'per_halaman' => $p->perPage(), 'total' => $p->total()];
    }
}
