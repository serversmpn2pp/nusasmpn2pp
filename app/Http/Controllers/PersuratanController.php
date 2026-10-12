<?php

namespace App\Http\Controllers;

use App\Models\BerkasSuratTu;
use App\Models\SuratTu;
use App\Services\Persuratan\PersuratanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersuratanController extends Controller
{
    public function __construct(private PersuratanService $service) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'],
            'jenis' => ['nullable', Rule::in(array_keys(SuratTu::JENIS))],
            'status' => ['nullable', Rule::in(array_keys(SuratTu::STATUS))],
            'sifat' => ['nullable', Rule::in(array_keys(SuratTu::SIFAT))],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'pemeriksaan_saya' => ['nullable', 'boolean']]);
        $query = $this->service->arsip($request->user());
        $jumlah = (clone $query)->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');
        $query->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q
            ->whereLike('nomor_surat', '%'.$kata.'%')->orWhereLike('nomor_agenda', '%'.$kata.'%')
            ->orWhereLike('perihal', '%'.$kata.'%')->orWhereLike('pihak', '%'.$kata.'%')
            ->orWhereHas('penugasan', fn ($q) => $q->whereLike('nama_pegawai', '%'.$kata.'%'))))
            ->when($filter['jenis'] ?? null, fn ($q, $jenis) => $q->where('jenis', $jenis))
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filter['sifat'] ?? null, fn ($q, $sifat) => $q->where('sifat', $sifat))
            ->when($filter['tahun'] ?? null, fn ($q, $tahun) => $q->whereYear('tanggal_surat', $tahun))
            ->when($filter['pemeriksaan_saya'] ?? false, fn ($q) => $q->where('pengesah_pengguna_id', $request->user()->id)->where('status', 'diajukan'));

        return response()->view('persuratan.index', ['surat' => $query->withCount(['penugasan', 'tindakLanjut',
            'tindakLanjut as selesai_count' => fn ($q) => $q->where('status', 'selesai'),
            'penugasan as dibaca_count' => fn ($q) => $q->whereNotNull('dibaca_pada')])->latest('tanggal_surat')->latest('id')->paginate(20)->withQueryString(),
            'filter' => $filter, 'jumlah' => $jumlah, 'pribadi' => false], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function create(Request $request)
    {
        $this->service->arsip($request->user());
        abort_unless($request->user()->memilikiIzin('surat.kelola'), 403);
        $data = $request->validate(['jenis' => ['nullable', Rule::in(array_keys(SuratTu::JENIS))],
            'revisi_dari_id' => ['nullable', 'integer', 'min:1']]);
        $asal = null;
        $surat = new SuratTu(['jenis' => $data['jenis'] ?? 'masuk', 'sifat' => 'biasa', 'tanggal_surat' => today(), 'tanggal_diterima' => today()]);
        if (! empty($data['revisi_dari_id'])) {
            $asal = $this->service->arsip($request->user())->findOrFail($data['revisi_dari_id']);
            abort_unless($asal->disahkan_pada && $asal->jenis !== 'masuk' && ! $asal->revisi()->whereNotNull('disahkan_pada')->exists(), 404);
            $surat->fill($asal->only(['jenis', 'perihal', 'pihak', 'sifat', 'keterangan', 'pengesah_pengguna_id',
                'mulai_tugas', 'selesai_tugas', 'tempat_tugas', 'uraian_tugas']));
            $surat->setRelation('penugasan', $asal->penugasan);
        }

        return $this->form($request, $surat, $asal);
    }

    public function pemeriksaan(Request $request)
    {
        abort_unless($request->user()->memilikiIzin('surat.sahkan'), 403);
        $request->merge(['pemeriksaan_saya' => true]);

        return $this->index($request);
    }

    public function store(Request $request)
    {
        $surat = $this->service->simpan($request);

        return redirect()->route('persuratan.show', $surat)->with('berhasil', $surat->jenis === 'masuk' ? 'Surat masuk berhasil diarsipkan.' : 'Draf surat berhasil disimpan.');
    }

    public function edit(Request $request, SuratTu $surat)
    {
        $this->service->pastikanArsip($request->user(), $surat, 'surat.kelola');
        abort_unless($surat->dapatDiedit(), 403);

        return $this->form($request, $surat);
    }

    public function update(Request $request, SuratTu $surat)
    {
        $this->service->simpan($request, $surat);

        return redirect()->route('persuratan.show', $surat)->with('berhasil', 'Perubahan surat berhasil disimpan.');
    }

    public function show(Request $request, SuratTu $surat)
    {
        $this->service->pastikanArsip($request->user(), $surat);
        $surat->load(['berkas', 'penugasan', 'pengesah:id,nama', 'riwayat.pengguna:id,nama', 'tindakLanjut.pemeriksa:id,nama']);
        // Judul surat terkait tetap mengikuti izin sifat surat, bukan hanya ID relasi.
        $asal = $surat->revisi_dari_id ? $this->service->arsip($request->user())->find($surat->revisi_dari_id) : null;
        $revisi = $this->service->arsip($request->user())->where('revisi_dari_id', $surat->id)->get();

        return response()->view('persuratan.show', ['surat' => $surat, 'pribadi' => false, 'asal' => $asal, 'revisi' => $revisi,
            'penerimaDisposisi' => $surat->jenis === 'masuk' && $request->user()->memilikiIzin('surat.disposisi')
                ? $this->service->pegawai()->whereNotIn('id', $surat->tindakLanjut->pluck('pegawai_id')->push($request->user()->pegawai_id)) : collect()],
            200, ['Cache-Control' => 'private, no-store']);
    }

    public function tindakan(Request $request, SuratTu $surat, string $aksi)
    {
        $this->service->tindakan($request, $surat, $aksi);

        return redirect()->route('persuratan.show', $surat)->with('berhasil', 'Status surat berhasil diperbarui.');
    }

    public function berkas(Request $request, SuratTu $surat, BerkasSuratTu $berkas)
    {
        return $this->service->unduh($request->user(), $surat, $berkas);
    }

    private function form(Request $request, SuratTu $surat, ?SuratTu $asal = null)
    {
        return response()->view('persuratan.form', ['surat' => $surat, 'asal' => $asal,
            'pegawai' => $this->service->pegawai(), 'pengesah' => $this->service->pengesah()->where('id', '<>', $request->user()->id)],
            200, ['Cache-Control' => 'private, no-store']);
    }
}
