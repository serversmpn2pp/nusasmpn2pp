<?php

namespace App\Http\Controllers;

use App\Models\SasaranUmpanBalikHumas;
use App\Models\UmpanBalikHumas;
use App\Services\Humas\KirimUmpanBalikOrangTuaHumasService;
use App\Services\Humas\UmpanBalikHumasService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UmpanBalikOrangTuaController extends Controller
{
    public function __construct(private readonly UmpanBalikHumasService $kelola) {}

    public function index(Request $request)
    {
        $wali = $this->kelola->wali($request);
        $filter = $request->validate(['tab' => ['nullable', Rule::in(['aktif', 'riwayat', 'semua'])]]);
        $siswaIds = $wali->siswa()->pluck('siswa.id')->map(fn ($id) => (int) $id)->all();
        $q = SasaranUmpanBalikHumas::where('orang_tua_wali_id', $wali->id)->where(function ($q) use ($siswaIds) {
            foreach ($siswaIds as $id) {
                $q->orWhereJsonContains('siswa_ids', $id);
            }
        })->whereHas('formulir', function ($q) {
            $q->where('status', '!=', 'draf')->where(fn ($q) => $q->where('status', '!=', 'arsip')->orWhereNotNull('sasaran_umpan_balik_humas.dikirim_pada'));
        });
        $tab = $filter['tab'] ?? 'aktif';
        if ($tab === 'aktif') {
            $q->whereNull('dikirim_pada')->whereHas('formulir', fn ($q) => $q->where('status', 'aktif')->where('selesai_pada', '>', now()));
        } elseif ($tab === 'riwayat') {
            $q->where(fn ($q) => $q->whereNotNull('dikirim_pada')->orWhereHas('formulir', fn ($q) => $q->where('status', '!=', 'aktif')->orWhere('selesai_pada', '<=', now())));
        }

        return $this->halaman('index', ['tab' => $tab, 'daftar' => $q->with('formulir')->orderByDesc('id')->paginate(15)->withQueryString()]);
    }

    public function show(Request $request, UmpanBalikHumas $formulir)
    {
        $wali = $this->kelola->wali($request);
        $sasaran = $this->kelola->sasaran($formulir, $wali);
        $formulir->load('pertanyaan');

        return $this->halaman('show', ['formulir' => $formulir, 'sasaran' => $sasaran, 'jawaban' => $sasaran->jawaban()->get()->keyBy('pertanyaan_umpan_balik_humas_id'), 'tokenPengiriman' => (string) Str::uuid(),
            'ringkasanPublik' => $formulir->tindakLanjut()->where('status', 'selesai')->where('bagikan_ringkasan', true)->get(['ringkasan_publik'])]);
    }

    public function store(Request $request, UmpanBalikHumas $formulir)
    {
        app(KirimUmpanBalikOrangTuaHumasService::class)->store($request, $formulir);

        return redirect()->route('umpan-balik-saya.show', $formulir)->with('berhasil', 'Jawaban berhasil dikirim. Terima kasih atas masukan Anda.');
    }

    private function halaman(string $view, array $data)
    {
        return response()->view('umpan-balik-saya.'.$view, $data)->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
