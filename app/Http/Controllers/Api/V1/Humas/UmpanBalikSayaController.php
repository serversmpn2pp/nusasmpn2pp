<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Models\SasaranUmpanBalikHumas;
use App\Models\UmpanBalikHumas;
use App\Services\Humas\KirimUmpanBalikOrangTuaHumasService;
use App\Services\Humas\UmpanBalikHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UmpanBalikSayaController extends HumasController
{
    public function __construct(private readonly UmpanBalikHumasService $kelola, private readonly HumasMobileService $mobile) {}

    public function index(Request $r)
    {
        $wali = $this->kelola->wali($r);
        $filter = $r->validate(['tab' => ['nullable', Rule::in(['aktif', 'riwayat', 'semua'])]]) + $this->halaman($r);
        $ids = $wali->siswa()->pluck('siswa.id')->map(fn ($id) => (int) $id)->all();
        $q = SasaranUmpanBalikHumas::where('orang_tua_wali_id', $wali->id)->where(function ($q) use ($ids) {
            foreach ($ids as $id) {
                $q->orWhereJsonContains('siswa_ids', $id);
            }
        })->whereHas('formulir', fn ($q) => $q->where('status', '!=', 'draf')->where(fn ($q) => $q->where('status', '!=', 'arsip')->orWhereNotNull('sasaran_umpan_balik_humas.dikirim_pada')));
        $tab = $filter['tab'] ?? 'aktif';
        if ($tab === 'aktif') {
            $q->whereNull('dikirim_pada')->whereHas('formulir', fn ($q) => $q->where('status', 'aktif')->where('selesai_pada', '>', now()));
        } elseif ($tab === 'riwayat') {
            $q->where(fn ($q) => $q->whereNotNull('dikirim_pada')->orWhereHas('formulir', fn ($q) => $q->where('status', '!=', 'aktif')->orWhere('selesai_pada', '<=', now())));
        }

        return $this->json($this->mobile->paginasi($q->with('formulir')->orderByDesc('id')->paginate($filter['per_halaman'] ?? 15, ['*'], 'halaman', $filter['halaman'] ?? 1), fn ($s) => $this->mobile->formulir($s->formulir) + ['dikirim_pada' => $s->dikirim_pada?->toIso8601String()]) + ['tab' => $tab]);
    }

    public function show(Request $r, UmpanBalikHumas $formulir)
    {
        $s = $this->kelola->sasaran($formulir, $this->kelola->wali($r));
        $data = $this->mobile->formulir($formulir) + ['dikirim_pada' => $s->dikirim_pada?->toIso8601String(),
            'dapat_mengirim' => ! $s->dikirim_pada && $formulir->menerimaJawaban(), 'token_pengiriman' => (string) Str::uuid(),
            'pertanyaan' => $formulir->pertanyaan()->get()->map(fn ($p) => $p->only(['id', 'urutan', 'jenis', 'teks', 'wajib'])),
            'jawaban' => $s->jawaban()->get()->map(fn ($j) => $j->only(['pertanyaan_umpan_balik_humas_id', 'nilai', 'teks'])),
            'ringkasan_publik' => $formulir->tindakLanjut()->where('status', 'selesai')->where('bagikan_ringkasan', true)->pluck('ringkasan_publik')];

        return $this->json($data);
    }

    public function store(Request $r, UmpanBalikHumas $formulir, KirimUmpanBalikOrangTuaHumasService $service)
    {
        $s = $service->store($r, $formulir);

        return $this->json(['id' => $formulir->id, 'dikirim_pada' => $s->dikirim_pada->toIso8601String()], 'Jawaban berhasil dikirim.');
    }
}
