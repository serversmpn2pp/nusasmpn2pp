<?php

namespace App\Http\Controllers;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\KerjaSamaHumas;
use App\Models\RiwayatDokumenHumas;
use App\Services\Humas\KelolaDokumenHumasService;
use App\Services\Humas\KelolaKemitraanHumasService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DokumenHumasController extends Controller
{
    private const BATAS_BERKAS_KB = KelolaDokumenHumasService::BATAS_BERKAS_KB;

    public function index(Request $request)
    {
        $data = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'kategori' => ['nullable', Rule::in(array_keys(DokumenHumas::KATEGORI))],
            'status' => ['nullable', Rule::in(array_keys(DokumenHumas::STATUS))],
        ]);

        $query = DokumenHumas::query()
            ->with(['pembuat:id,nama', 'pengubah:id,nama'])
            ->when($data['kata_kunci'] ?? null, function ($query, string $kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('judul', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('nomor_dokumen', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('deskripsi', 'ilike', '%'.$kataKunci.'%');
                });
            })
            ->when($data['kategori'] ?? null, fn ($query, string $kategori) => $query->where('kategori', $kategori))
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw('berlaku_sampai IS NULL')
            ->orderBy('berlaku_sampai')
            ->orderByDesc('updated_at');

        $dokumen = $query->paginate(20)->withQueryString();
        $aktif = DokumenHumas::query()->where('status', 'aktif');

        return view('dokumen-humas.index', [
            'dokumen' => $dokumen,
            'kategori' => DokumenHumas::KATEGORI,
            'status' => DokumenHumas::STATUS,
            'filter' => $data,
            'jumlahAktif' => (clone $aktif)->count(),
            'jumlahSegeraBerakhir' => (clone $aktif)
                ->whereNotNull('berlaku_sampai')
                ->whereBetween('berlaku_sampai', [today(), today()->addDays(30)])
                ->count(),
            'jumlahKedaluwarsa' => (clone $aktif)
                ->whereNotNull('berlaku_sampai')
                ->whereDate('berlaku_sampai', '<', today())
                ->count(),
        ]);
    }

    public function create(Request $request)
    {
        $data = $request->validate($this->aturanKonteks());
        $agenda = isset($data['agenda_humas_id']) ? AgendaHumas::findOrFail($data['agenda_humas_id']) : null;
        if ($agenda) {
            abort_unless($request->user()->memilikiIzin('agenda_humas.kelola'), 403);
        }

        $mou = ! empty($data['kerja_sama_humas_id']) ? KerjaSamaHumas::with('mitra')->findOrFail($data['kerja_sama_humas_id']) : null;
        if ($mou) {
            abort_unless($request->user()->memilikiIzin('kemitraan_humas.kelola'), 403);
            app(KelolaKemitraanHumasService::class)->pastikanMitraAktif($mou->mitra);
        }

        return view('dokumen-humas.form', [
            'agendaTerkait' => $agenda,
            'mouTerkait' => $mou,
            'tokenUnggahanMou' => (string) Str::uuid(),
            'dokumenHumas' => new DokumenHumas(['ingatkan_hari_sebelum' => 30, 'kategori' => $mou ? 'kemitraan' : null, 'judul' => $mou?->judul, 'nomor_dokumen' => $mou?->nomor]),
            'kategori' => DokumenHumas::KATEGORI,
            'batasBerkasMb' => self::BATAS_BERKAS_KB / 1024,
        ]);
    }

    public function store(Request $request)
    {
        $dokumenHumas = app(KelolaDokumenHumasService::class)->store($request);
        $mouId = $request->input('kerja_sama_humas_id');
        $agendaId = $request->input('agenda_humas_id');
        if ($mouId) {
            $mou = KerjaSamaHumas::findOrFail($mouId);
            $url = route('kemitraan-humas.mou.show', [$mou->mitra_humas_id, $mou]);
            $request->session()->flash('berhasil', 'Berkas MoU berhasil diunggah dan dihubungkan.');

            return $request->expectsJson() ? response()->json(['redirect' => $url, 'pesan' => 'Berkas MoU berhasil disimpan.']) : redirect($url);
        }
        if ($agendaId) {
            return redirect()->route('agenda-humas.show', [$agendaId, 'tab' => 'dokumen'])
                ->with('berhasil', 'Dokumen berhasil diunggah dan dihubungkan dengan agenda.');
        }

        return redirect()
            ->route('dokumen-humas.show', $dokumenHumas)
            ->with('berhasil', 'Dokumen Humas berhasil disimpan.');
    }

    public function show(Request $request, DokumenHumas $dokumenHumas)
    {
        $dokumenHumas->load([
            'pembuat:id,nama',
            'pengubah:id,nama',
            'riwayat.pengunggah:id,nama',
        ]);

        $mouTerhubung = $request->user()->memilikiIzin(['kemitraan_humas.lihat', 'kemitraan_humas.kelola'])
            ? KerjaSamaHumas::with('mitra')->where('dokumen_humas_id', $dokumenHumas->id)->get() : collect();

        return view('dokumen-humas.show', compact('dokumenHumas', 'mouTerhubung'));
    }

    public function edit(DokumenHumas $dokumenHumas)
    {
        return view('dokumen-humas.form', [
            'dokumenHumas' => $dokumenHumas,
            'kategori' => DokumenHumas::KATEGORI,
            'batasBerkasMb' => self::BATAS_BERKAS_KB / 1024,
        ]);
    }

    public function update(Request $request, DokumenHumas $dokumenHumas)
    {
        $dokumenHumas = app(KelolaDokumenHumasService::class)->update($request, $dokumenHumas);

        return redirect()
            ->route('dokumen-humas.show', $dokumenHumas)
            ->with('berhasil', $request->hasFile('berkas') ? 'Informasi dan revisi dokumen berhasil disimpan.' : 'Informasi dokumen berhasil diperbarui.');
    }

    public function unduh(DokumenHumas $dokumenHumas): StreamedResponse
    {
        return app(KelolaDokumenHumasService::class)->unduh($dokumenHumas);
    }

    public function unduhRiwayat(DokumenHumas $dokumenHumas, RiwayatDokumenHumas $riwayatDokumenHumas): StreamedResponse
    {
        abort_unless($riwayatDokumenHumas->dokumen_humas_id === $dokumenHumas->id, 404);

        return app(KelolaDokumenHumasService::class)->unduh($riwayatDokumenHumas);
    }

    public function ubahStatus(Request $request, DokumenHumas $dokumenHumas)
    {
        $dokumenHumas = app(KelolaDokumenHumasService::class)->ubahStatus($request, $dokumenHumas);

        return redirect()
            ->route('dokumen-humas.show', $dokumenHumas)
            ->with('berhasil', $dokumenHumas->status === 'arsip' ? 'Dokumen dipindahkan ke arsip.' : 'Dokumen diaktifkan kembali.');
    }

    private function aturanKonteks(): array
    {
        return ['agenda_humas_id' => ['nullable', 'integer', 'exists:agenda_humas,id', 'prohibits:kerja_sama_humas_id'],
            'kerja_sama_humas_id' => ['nullable', 'integer', 'exists:kerja_sama_humas,id', 'prohibits:agenda_humas_id'],
            'token_unggahan_mou' => ['nullable', 'uuid']];
    }
}
