<?php

namespace App\Http\Controllers;

use App\Models\AgendaHumas;
use App\Models\JawabanUmpanBalikHumas;
use App\Models\Kelas;
use App\Models\TahunPelajaran;
use App\Models\TindakLanjutUmpanBalikHumas;
use App\Models\UmpanBalikHumas;
use App\Services\Humas\KelolaInstrumenUmpanBalikHumasService;
use App\Services\Humas\UmpanBalikHumasService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UmpanBalikHumasController extends Controller
{
    public function __construct(private readonly UmpanBalikHumasService $kelola) {}

    public function index(Request $request)
    {
        $this->akses($request);
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'], 'status' => ['nullable', Rule::in(array_keys(UmpanBalikHumas::STATUS))]]);
        $q = UmpanBalikHumas::when($filter['kata_kunci'] ?? null, fn ($q, $s) => $q->whereLike('judul', '%'.$s.'%'))
            ->when($filter['tahun_pelajaran_id'] ?? null, fn ($q, $id) => $q->where('tahun_pelajaran_id', $id));
        $jumlah = (clone $q)->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');
        $q->when($filter['status'] ?? null, fn ($q, $s) => $q->where('status', $s));

        return $this->halaman('index', ['filter' => $filter, 'jumlah' => $jumlah, 'tahun' => TahunPelajaran::orderByDesc('nama')->get(['id', 'nama']),
            'daftar' => $q->with('tahunPelajaran:id,nama')->withCount(['sasaran', 'sasaran as respons_count' => fn ($q) => $q->whereNotNull('dikirim_pada'), 'tindakLanjut as tertunda_count' => fn ($q) => $q->where('status', '!=', 'selesai')])->latest('updated_at')->orderByDesc('id')->paginate(20)->withQueryString()]);
    }

    public function create(Request $request)
    {
        $this->akses($request);
        $pertanyaan = collect(['Kejelasan informasi yang disampaikan sekolah.', 'Kemudahan berkomunikasi dengan pihak sekolah.', 'Kecepatan tanggapan sekolah terhadap kebutuhan orang tua.', 'Pelayanan sekolah kepada orang tua/wali.'])
            ->map(fn ($teks) => ['jenis' => 'skala', 'teks' => $teks, 'wajib' => true])->push(['jenis' => 'teks', 'teks' => 'Saran perbaikan untuk sekolah.', 'wajib' => false])->all();

        return $this->form(new UmpanBalikHumas(['tahun_pelajaran_id' => TahunPelajaran::where('aktif', true)->value('id'), 'cakupan' => 'seluruh', 'penanggung_jawab' => $request->user()->nama,
            'mulai_pada' => now()->startOfMinute(), 'selesai_pada' => now()->addWeek()->startOfMinute(), 'pengantar' => 'Mohon berikan penilaian berdasarkan pengalaman Anda. Masukan ini menjadi bahan perbaikan layanan sekolah.']), $pertanyaan);
    }

    public function store(Request $request)
    {
        $f = app(KelolaInstrumenUmpanBalikHumasService::class)->store($request);

        return $this->kembali($f, 'Draf formulir tersimpan.');
    }

    public function edit(Request $request, UmpanBalikHumas $formulir)
    {
        $this->akses($request, $formulir);
        abort_unless($formulir->status === 'draf' && ! $formulir->dibuka_pada, 403);

        return $this->form($formulir, $formulir->pertanyaan()->get()->map(fn ($p) => $p->only(['jenis', 'teks', 'wajib']))->all());
    }

    public function update(Request $request, UmpanBalikHumas $formulir)
    {
        app(KelolaInstrumenUmpanBalikHumasService::class)->update($request, $formulir);

        return $this->kembali($formulir, 'Draf diperbarui.');
    }

    public function show(Request $request, UmpanBalikHumas $formulir)
    {
        $this->akses($request, $formulir);
        $formulir->load('tahunPelajaran', 'pertanyaan', 'tindakLanjut');
        $preview = ! $formulir->dibuka_pada && $formulir->status === 'draf' ? $this->kelola->pratinjau($formulir) : null;
        $teks = $request->validate(['pertanyaan_id' => ['nullable', 'integer']]);
        $pilihanTeks = null;
        $jawabanTeks = null;
        if ($teks['pertanyaan_id'] ?? null) {
            $pilihanTeks = $formulir->pertanyaan->firstWhere('id', $teks['pertanyaan_id']);
            abort_unless($pilihanTeks && $pilihanTeks->jenis === 'teks', 404);
            $jawabanTeks = JawabanUmpanBalikHumas::where('pertanyaan_umpan_balik_humas_id', $pilihanTeks->id)->whereNotNull('teks')->orderBy('id')->paginate(15, ['teks'])->withQueryString();
        }

        return $this->halaman('show', ['formulir' => $formulir, 'rekap' => $this->kelola->rekap($formulir), 'preview' => $preview,
            'pilihanTeks' => $pilihanTeks, 'jawabanTeks' => $jawabanTeks, 'tokenTindak' => (string) Str::uuid(),
            'riwayat' => $formulir->riwayat()->with('pengguna:id,nama')->paginate(10, ['*'], 'riwayat')]);
    }

    public function status(Request $request, UmpanBalikHumas $formulir)
    {
        app(KelolaInstrumenUmpanBalikHumasService::class)->status($request, $formulir);

        return $this->kembali($formulir, 'Status formulir diperbarui.');
    }

    public function tindak(Request $request, UmpanBalikHumas $formulir, ?TindakLanjutUmpanBalikHumas $tindak = null)
    {
        app(KelolaInstrumenUmpanBalikHumasService::class)->tindak($request, $formulir, $tindak);

        return $this->kembali($formulir, 'Tindak lanjut tersimpan.');
    }

    public function cetak(Request $request, UmpanBalikHumas $formulir)
    {
        $this->akses($request, $formulir);
        $formulir->load('tahunPelajaran', 'pertanyaan', 'tindakLanjut');

        return $this->halaman('cetak', ['formulir' => $formulir, 'rekap' => $this->kelola->rekap($formulir)]);
    }

    private function akses(Request $r, ?UmpanBalikHumas $formulir = null): void
    {
        abort_unless($r->user()->aktif && ! $r->user()->akunOrangTua() && ! $r->user()->akunSiswa() && $r->user()->memilikiIzin(['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola']), 403);
        if ($formulir?->cakupan === 'agenda') {
            $this->aksesAgenda($r);
        }
    }

    private function aksesAgenda(Request $r): void
    {
        abort_unless($r->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']), 403);
    }

    private function form(UmpanBalikHumas $f, array $pertanyaan)
    {
        return $this->halaman('form', ['formulir' => $f, 'pertanyaan' => $pertanyaan, 'tokenPembuatan' => (string) Str::uuid(),
            'tahun' => TahunPelajaran::orderByDesc('nama')->get(['id', 'nama']), 'kelas' => Kelas::where('aktif', true)->with('tahunPelajaran:id,nama')->orderBy('nama')->get(['id', 'nama', 'tingkat', 'tahun_pelajaran_id']),
            'agenda' => auth()->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']) ? AgendaHumas::where('status', '!=', 'dibatalkan')->orderByDesc('waktu_mulai')->get(['id', 'judul', 'waktu_mulai']) : collect()]);
    }

    private function halaman(string $view, array $data)
    {
        return response()->view('umpan-balik-humas.'.$view, $data + ['bolehKelola' => auth()->user()->memilikiIzin('umpan_balik_humas.kelola')])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function kembali(UmpanBalikHumas $f, string $pesan)
    {
        return redirect()->route('umpan-balik-humas.show', $f)->with('berhasil', $pesan);
    }
}
