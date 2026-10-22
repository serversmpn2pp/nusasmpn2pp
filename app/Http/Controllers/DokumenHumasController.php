<?php

namespace App\Http\Controllers;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\KerjaSamaHumas;
use App\Models\RiwayatDokumenHumas;
use App\Services\Humas\KaitanDokumenHumasService;
use App\Services\Humas\KelolaDokumenHumasService;
use App\Services\Humas\KelolaKemitraanHumasService;
use App\Services\Humas\KlasifikasiDokumenHumasService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DokumenHumasController extends Controller
{
    private const BATAS_BERKAS_KB = KelolaDokumenHumasService::BATAS_BERKAS_KB;

    public function index(Request $request)
    {
        $this->akses($request);
        $klasifikasi = app(KlasifikasiDokumenHumasService::class);
        $data = $request->validate($klasifikasi->aturanFilter() + [
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'kategori' => ['nullable', Rule::in(array_keys(DokumenHumas::KATEGORI))],
            'status' => ['nullable', Rule::in(array_keys(DokumenHumas::STATUS))],
        ]);

        $query = DokumenHumas::query()
            ->with(['pembuat:id,nama', 'pengubah:id,nama'])
            ->when($data['kata_kunci'] ?? null, function ($query, string $kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->whereLike('judul', '%'.$kataKunci.'%')
                        ->orWhereLike('nomor_dokumen', '%'.$kataKunci.'%')
                        ->orWhereLike('deskripsi', '%'.$kataKunci.'%');
                });
            })
            ->when($data['kategori'] ?? null, fn ($query, string $kategori) => $query->where('kategori', $kategori))
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw('berlaku_sampai IS NULL')
            ->orderBy('berlaku_sampai')
            ->orderByDesc('updated_at');

        $dokumen = $klasifikasi->filter($query, $data)->paginate(20)->withQueryString();
        $aktif = DokumenHumas::query()->where('status', 'aktif');

        return view('dokumen-humas.index', [
            'dokumen' => $dokumen,
            'kategori' => DokumenHumas::KATEGORI,
            'status' => DokumenHumas::STATUS,
            'filter' => $data,
            'jumlahPerluTinjau' => $klasifikasi->filter(DokumenHumas::query(), ['klasifikasi' => 'perlu_tinjau'])->count(),
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
        $this->akses($request);
        $data = $request->validate($this->aturanKonteks() + app(KlasifikasiDokumenHumasService::class)->aturanFilter());
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
            'dokumenHumas' => new DokumenHumas(['ingatkan_hari_sebelum' => 30, 'kategori' => $mou ? 'kemitraan' : null, 'judul' => $mou?->judul, 'nomor_dokumen' => $mou?->nomor,
                'urusan' => $mou ? 'kemitraan' : ($data['urusan'] ?? (in_array($agenda?->jenis, ['orang_tua', 'komite', 'kemitraan'], true) ? $agenda->jenis : null)),
                'jenis_dokumen' => $mou ? 'mou' : ($data['jenis_dokumen'] ?? null)]),
            'kategori' => DokumenHumas::KATEGORI,
            'batasBerkasMb' => self::BATAS_BERKAS_KB / 1024,
        ]);
    }

    public function store(Request $request)
    {
        $this->akses($request);
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
        $this->akses($request);
        $dokumenHumas->load([
            'pembuat:id,nama',
            'pengubah:id,nama',
            'riwayat.pengunggah:id,nama',
        ]);

        $mouTerhubung = $request->user()->memilikiIzin(['kemitraan_humas.lihat', 'kemitraan_humas.kelola'])
            ? KerjaSamaHumas::with('mitra')->where('dokumen_humas_id', $dokumenHumas->id)->get() : collect();

        $kaitan = app(KaitanDokumenHumasService::class);
        $jenisKaitan = $request->user()->memilikiIzin('dokumen_humas.kelola') ? $kaitan->jenis($request->user(), true) : [];
        $filterKaitan = $request->validate(['jenis_kaitan' => ['nullable', 'string', Rule::in(['agenda', 'program', 'program_komite', 'surat'])], 'cari_kaitan' => ['nullable', 'string', 'max:120']]);
        $pilihanJenisKaitan = $filterKaitan['jenis_kaitan'] ?? array_key_first($jenisKaitan);

        return view('dokumen-humas.show', compact('dokumenHumas', 'mouTerhubung', 'jenisKaitan', 'pilihanJenisKaitan', 'filterKaitan') + [
            'hubungan' => $kaitan->hubungan($request->user(), $dokumenHumas),
            'pilihanKaitan' => $jenisKaitan ? $kaitan->pilihan($request->user(), $pilihanJenisKaitan, $filterKaitan['cari_kaitan'] ?? null) : [],
            'sidik' => app(KelolaDokumenHumasService::class)->sidik($dokumenHumas),
            'riwayatKlasifikasi' => $dokumenHumas->riwayatKlasifikasi()->with('pengguna:id,nama')->paginate(20, ['*'], 'halaman_klasifikasi'),
        ]);
    }

    public function edit(DokumenHumas $dokumenHumas)
    {
        $this->akses(request());

        return view('dokumen-humas.form', [
            'dokumenHumas' => $dokumenHumas,
            'kategori' => DokumenHumas::KATEGORI,
            'batasBerkasMb' => self::BATAS_BERKAS_KB / 1024,
            'sidik' => app(KelolaDokumenHumasService::class)->sidik($dokumenHumas),
        ]);
    }

    public function update(Request $request, DokumenHumas $dokumenHumas)
    {
        $this->akses($request);
        $dokumenHumas = app(KelolaDokumenHumasService::class)->update($request, $dokumenHumas);

        return redirect()
            ->route('dokumen-humas.show', $dokumenHumas)
            ->with('berhasil', $request->hasFile('berkas') ? 'Informasi dan revisi dokumen berhasil disimpan.' : 'Informasi dokumen berhasil diperbarui.');
    }

    public function unduh(DokumenHumas $dokumenHumas): StreamedResponse
    {
        $this->akses(request());

        return app(KelolaDokumenHumasService::class)->unduh($dokumenHumas);
    }

    public function unduhRiwayat(DokumenHumas $dokumenHumas, RiwayatDokumenHumas $riwayatDokumenHumas): StreamedResponse
    {
        $this->akses(request());
        abort_unless($riwayatDokumenHumas->dokumen_humas_id === $dokumenHumas->id, 404);

        return app(KelolaDokumenHumasService::class)->unduh($riwayatDokumenHumas);
    }

    public function ubahStatus(Request $request, DokumenHumas $dokumenHumas)
    {
        $this->akses($request);
        $data = $request->validate(['sidik' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
        $dokumenHumas = app(KelolaDokumenHumasService::class)->ubahStatus($request, $dokumenHumas, $data['sidik'] ?? null);

        return redirect()
            ->route('dokumen-humas.show', $dokumenHumas)
            ->with('berhasil', $dokumenHumas->status === 'arsip' ? 'Dokumen dipindahkan ke arsip.' : 'Dokumen diaktifkan kembali.');
    }

    public function klasifikasiKolektif(Request $request)
    {
        $jumlah = app(KlasifikasiDokumenHumasService::class)->kolektif($request);

        return back()->with('berhasil', $jumlah.' dokumen berhasil diklasifikasikan dan dikonfirmasi.');
    }

    public function kaitan(Request $request, DokumenHumas $dokumenHumas)
    {
        app(KaitanDokumenHumasService::class)->ubah($request, $dokumenHumas);

        return redirect()->route('dokumen-humas.show', $dokumenHumas)->with('berhasil', 'Keterkaitan dokumen berhasil diperbarui. Berkas asli tetap tersimpan.');
    }

    private function aturanKonteks(): array
    {
        return ['agenda_humas_id' => ['nullable', 'integer', 'exists:agenda_humas,id', 'prohibits:kerja_sama_humas_id'],
            'kerja_sama_humas_id' => ['nullable', 'integer', 'exists:kerja_sama_humas,id', 'prohibits:agenda_humas_id'],
            'token_unggahan_mou' => ['nullable', 'uuid']];
    }

    private function akses(Request $request): void
    {
        $akun = $request->user();
        abort_unless($akun?->aktif && ! $akun->akunSiswa() && ! $akun->akunOrangTua(), 403);
    }
}
