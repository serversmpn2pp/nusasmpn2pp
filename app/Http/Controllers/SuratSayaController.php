<?php

namespace App\Http\Controllers;

use App\Models\BerkasSuratTu;
use App\Models\SuratTu;
use App\Services\Persuratan\PersuratanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuratSayaController extends Controller
{
    public function __construct(private PersuratanService $service) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['disahkan', 'dikirim', 'dibatalkan'])], 'belum_dibaca' => ['nullable', 'boolean']]);
        $query = $this->service->penugasanSaya($request->user());
        $jumlah = (clone $query)->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');
        $query->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q
            ->whereLike('nomor_surat', '%'.$kata.'%')->orWhereLike('perihal', '%'.$kata.'%')))
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filter['belum_dibaca'] ?? false, fn ($q) => $q->whereIn('status', ['disahkan', 'dikirim'])->whereHas('penugasan', fn ($q) => $q
                ->where('pegawai_id', $request->user()->pegawai_id)->whereNull('dibaca_pada')));

        return response()->view('persuratan.index', ['surat' => $query->with(['penugasan' => fn ($q) => $q
            ->where('pegawai_id', $request->user()->pegawai_id)])->latest('disahkan_pada')->paginate(20)->withQueryString(),
            'filter' => $filter, 'jumlah' => $jumlah, 'pribadi' => true], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function show(Request $request, SuratTu $surat)
    {
        $this->service->penugasanSaya($request->user())->findOrFail($surat->id);
        $surat->load(['pengesah:id,nama', 'berkas' => fn ($q) => $q->where('aktif', true),
            'tindakLanjut' => fn ($q) => $q->where('pegawai_id', $request->user()->pegawai_id),
            'penugasan' => fn ($q) => $q->where('pegawai_id', $request->user()->pegawai_id)]);

        return response()->view('persuratan.show', ['surat' => $surat, 'pribadi' => true, 'asal' => null, 'revisi' => collect(),
            'dokumenTerkait' => $request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']) ? $surat->dokumenHumas()->latest('dokumen_humas.id')->limit(50)->get() : collect()], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function baca(Request $request, SuratTu $surat)
    {
        $this->service->baca($request->user(), $surat);

        return redirect()->route('surat-saya.show', $surat)->with('berhasil', 'Surat ditandai sudah dibaca.');
    }

    public function berkas(Request $request, SuratTu $surat, BerkasSuratTu $berkas)
    {
        return $this->service->unduh($request->user(), $surat, $berkas, true);
    }
}
