<?php

namespace App\Http\Controllers;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\KerjaSamaHumas;
use App\Models\MitraHumas;
use App\Services\Humas\KelolaKemitraanHumasService;
use App\Support\PenulisExcelKemitraanHumas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KemitraanHumasController extends Controller
{
    public function __construct(private KelolaKemitraanHumasService $kelola) {}

    public function index(Request $request)
    {
        $filter = $this->filter($request);
        $tab = $request->query('tab') === 'mou' ? 'mou' : 'mitra';
        $mitra = MitraHumas::withCount(['kerjaSama', 'agenda'])->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q
            ->where(fn ($q) => $q->whereLike('nama', '%'.$kata.'%')->orWhereLike('nama_kontak', '%'.$kata.'%')))
            ->when($filter['jenis'] ?? null, fn ($q, $jenis) => $q->where('jenis', $jenis))
            ->when($filter['status_mitra'] ?? null, fn ($q, $status) => $q->where('status', $status));

        return view('kemitraan-humas.index', [
            'tab' => $tab, 'filter' => $filter, 'statistik' => $this->statistik(),
            'daftarMitra' => $tab === 'mitra' ? $mitra->orderBy('status')->orderBy('nama')->paginate(20)->withQueryString() : null,
            'daftarMou' => $tab === 'mou' ? $this->queryLaporan($filter)->with('mitra')->orderByRaw('tanggal_selesai IS NULL')->orderBy('tanggal_selesai')->orderByDesc('id')->paginate(20)->withQueryString() : null,
        ]);
    }

    public function create()
    {
        return view('kemitraan-humas.mitra-form', ['mitra' => new MitraHumas(['jenis' => 'pemerintah', 'status' => 'aktif'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturanMitra());
        $mitra = DB::transaction(function () use ($request, $data) {
            $mitra = MitraHumas::create($data + ['diubah_oleh_pengguna_id' => $request->user()->id]);
            $this->kelola->catat($mitra, null, 'Mitra ditambahkan', null, $mitra->attributesToArray(), $request->user());

            return $mitra;
        });

        return redirect()->route('kemitraan-humas.show', $mitra)->with('berhasil', 'Data mitra berhasil disimpan.');
    }

    public function show(Request $request, MitraHumas $mitra)
    {
        $bolehAgenda = $request->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']);
        $tab = in_array($request->query('tab'), ['kegiatan', 'riwayat']) ? $request->query('tab') : 'mou';
        $cari = $request->validate(['cari_agenda' => ['nullable', 'string', 'max:120']]);

        return view('kemitraan-humas.show', [
            'mitra' => $mitra, 'tab' => $tab, 'bolehAgenda' => $bolehAgenda,
            'mou' => $mitra->kerjaSama()->paginate(15, ['*'], 'halaman_mou')->withQueryString(),
            'agenda' => $bolehAgenda ? $mitra->agenda()->orderByDesc('waktu_mulai')->paginate(15, ['agenda_humas.*'], 'halaman_agenda')->withQueryString() : null,
            'pilihanAgenda' => $bolehAgenda && $tab === 'kegiatan' ? AgendaHumas::whereNotIn('id', $mitra->agenda()->pluck('agenda_humas.id'))
                ->when($cari['cari_agenda'] ?? null, fn ($q, $kata) => $q->whereLike('judul', '%'.$kata.'%'))
                ->orderByDesc('waktu_mulai')->limit(50)->get(['id', 'judul', 'waktu_mulai', 'status']) : collect(),
            'riwayat' => $tab === 'riwayat' ? $mitra->riwayat()->with('pengguna:id,nama')->paginate(20)->withQueryString() : null,
        ]);
    }

    public function edit(MitraHumas $mitra)
    {
        return view('kemitraan-humas.mitra-form', compact('mitra'));
    }

    public function update(Request $request, MitraHumas $mitra)
    {
        $data = $request->validate($this->aturanMitra() + ['versi' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($data, $mitra, $request) {
            $mitra = MitraHumas::lockForUpdate()->findOrFail($mitra->id);
            $this->kelola->pastikanVersi((int) $data['versi'], $mitra->versi);
            if ($data['status'] === 'arsip' && $mitra->kerjaSama()->where('status', 'aktif')->whereDate('tanggal_selesai', '>=', today())->exists()) {
                throw ValidationException::withMessages(['status' => 'Masih ada MoU berlaku atau belum mulai. Akhiri perjanjian tersebut sebelum mengarsipkan mitra.']);
            }
            unset($data['versi']);
            $sebelum = $mitra->attributesToArray();
            $mitra->fill($data);
            if ($mitra->isDirty()) {
                $mitra->update(['versi' => $mitra->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id]);
                $this->kelola->catat($mitra, null, 'Data mitra diperbarui', $sebelum, $mitra->attributesToArray(), $request->user());
            }
        });

        return redirect()->route('kemitraan-humas.show', $mitra)->with('berhasil', 'Data mitra berhasil diperbarui.');
    }

    public function createMou(Request $request, MitraHumas $mitra)
    {
        $this->kelola->pastikanMitraAktif($mitra);

        return $this->formMou($request, $mitra, new KerjaSamaHumas(['status' => 'draf', 'bidang' => 'pendidikan', 'ingatkan_hari_sebelum' => 30]));
    }

    public function storeMou(Request $request, MitraHumas $mitra)
    {
        $data = $request->validate($this->aturanMou());
        $mou = DB::transaction(function () use ($data, $mitra, $request) {
            $mitra = MitraHumas::lockForUpdate()->findOrFail($mitra->id);
            $this->kelola->pastikanMitraAktif($mitra);
            $this->pastikanDokumenBolehDiubah($request, $data, null);
            $mou = $mitra->kerjaSama()->make($data);
            $this->pastikanStatusMou($mou);
            $mou->diubah_oleh_pengguna_id = $request->user()->id;
            $mou->save();
            $this->kelola->catat($mitra, $mou, 'MoU ditambahkan', null, $mou->attributesToArray(), $request->user());

            return $mou;
        });

        return redirect()->route('kemitraan-humas.mou.show', [$mitra, $mou])->with('berhasil', 'MoU berhasil disimpan.');
    }

    public function showMou(Request $request, MitraHumas $mitra, KerjaSamaHumas $mou)
    {
        $this->pastikanMouMitra($mitra, $mou);
        $bolehDokumen = $request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        if ($bolehDokumen) {
            $mou->load('dokumen');
        }

        return view('kemitraan-humas.mou-show', [
            'mitra' => $mitra, 'mou' => $mou, 'bolehDokumen' => $bolehDokumen,
            'riwayat' => $mou->riwayat()->with('pengguna:id,nama')->paginate(15),
        ]);
    }

    public function editMou(Request $request, MitraHumas $mitra, KerjaSamaHumas $mou)
    {
        $this->pastikanMouMitra($mitra, $mou);

        return $this->formMou($request, $mitra, $mou);
    }

    public function updateMou(Request $request, MitraHumas $mitra, KerjaSamaHumas $mou)
    {
        $this->pastikanMouMitra($mitra, $mou);
        $data = $request->validate($this->aturanMou() + ['versi' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $data, $mitra, $mou) {
            $mitra = MitraHumas::lockForUpdate()->findOrFail($mitra->id);
            $mou = $mitra->kerjaSama()->lockForUpdate()->findOrFail($mou->id);
            $this->kelola->pastikanVersi((int) $data['versi'], $mou->versi);
            $this->pastikanDokumenBolehDiubah($request, $data, $mou);
            unset($data['versi']);
            $sebelum = $mou->attributesToArray();
            $mou->fill($data);
            if ($mou->status !== 'diakhiri') {
                $mou->alasan_diakhiri = null;
            }
            if ($mou->status === 'aktif') {
                $this->kelola->pastikanMitraAktif($mitra);
            }
            $this->pastikanStatusMou($mou);
            if ($mou->isDirty()) {
                $mou->update(['versi' => $mou->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id]);
                $this->kelola->catat($mitra, $mou, 'MoU diperbarui', $sebelum, $mou->attributesToArray(), $request->user());
            }
        });

        return redirect()->route('kemitraan-humas.mou.show', [$mitra, $mou])->with('berhasil', 'MoU berhasil diperbarui.');
    }

    public function hubungkanAgenda(Request $request, MitraHumas $mitra)
    {
        $data = $request->validate(['agenda_humas_id' => ['required', 'integer', 'exists:agenda_humas,id']]);
        DB::transaction(function () use ($request, $data, $mitra) {
            $mitra = MitraHumas::lockForUpdate()->findOrFail($mitra->id);
            $this->kelola->pastikanMitraAktif($mitra);
            $agenda = AgendaHumas::lockForUpdate()->findOrFail($data['agenda_humas_id']);
            $perubahan = $mitra->agenda()->syncWithoutDetaching([$agenda->id]);
            if ($perubahan['attached']) {
                $this->kelola->catat($mitra, null, 'Agenda dihubungkan', null, ['agenda' => $agenda->judul, 'agenda_humas_id' => $agenda->id], $request->user());
            }
        });

        return redirect()->route('kemitraan-humas.show', [$mitra, 'tab' => 'kegiatan'])->with('berhasil', 'Agenda berhasil dihubungkan.');
    }

    public function lepasAgenda(Request $request, MitraHumas $mitra, AgendaHumas $agenda)
    {
        DB::transaction(function () use ($request, $mitra, $agenda) {
            $mitra = MitraHumas::lockForUpdate()->findOrFail($mitra->id);
            abort_unless($mitra->agenda()->whereKey($agenda->id)->exists(), 404);
            $mitra->agenda()->detach($agenda->id);
            $this->kelola->catat($mitra, null, 'Hubungan agenda dilepas', ['agenda' => $agenda->judul, 'agenda_humas_id' => $agenda->id], null, $request->user());
        });

        return redirect()->route('kemitraan-humas.show', [$mitra, 'tab' => 'kegiatan'])->with('berhasil', 'Hubungan dilepas. Agenda dan dokumen tetap tersimpan.');
    }

    public function cetak(Request $request)
    {
        $filter = $this->filter($request);
        $mou = $this->queryLaporan($filter)->with('mitra')->orderBy('mitra_humas_id')->orderBy('tanggal_selesai')->limit(1001)->get();
        if ($mou->count() > 1000) {
            throw ValidationException::withMessages(['laporan' => 'Cetak maksimal 1.000 MoU. Persempit filter laporan.']);
        }

        return view('kemitraan-humas.cetak', compact('mou', 'filter'));
    }

    public function export(Request $request, PenulisExcelKemitraanHumas $excel)
    {
        $filter = $this->filter($request);
        $mou = $this->queryLaporan($filter)->with('mitra')->orderBy('mitra_humas_id')->orderBy('tanggal_selesai')->limit(10001)->get();
        if ($mou->count() > 10000) {
            throw ValidationException::withMessages(['laporan' => 'Ekspor maksimal 10.000 MoU. Persempit filter laporan.']);
        }

        return response()->download($excel->buat($mou, $filter), 'rekap-kemitraan-'.today()->format('Ymd').'.xlsx', ['Content-Type' => PenulisExcelKemitraanHumas::MIME])->deleteFileAfterSend(true);
    }

    private function formMou(Request $request, MitraHumas $mitra, KerjaSamaHumas $mou)
    {
        $cari = $request->validate(['cari_dokumen' => ['nullable', 'string', 'max:120']]);
        $bolehDokumen = $request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        $dokumen = collect();
        if ($bolehDokumen) {
            $dokumen = DokumenHumas::where('status', 'aktif')->when($cari['cari_dokumen'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('judul', '%'.$kata.'%')->orWhereLike('nomor_dokumen', '%'.$kata.'%')))
                ->latest('updated_at')->limit(100)->get(['id', 'judul', 'nomor_dokumen', 'status']);
            if ($mou->dokumen_humas_id) {
                $dokumen = $dokumen->merge(DokumenHumas::whereKey($mou->dokumen_humas_id)->get(['id', 'judul', 'nomor_dokumen', 'status']))->unique('id');
            }
        }

        return view('kemitraan-humas.mou-form', compact('mitra', 'mou', 'dokumen', 'bolehDokumen'));
    }

    private function pastikanDokumenBolehDiubah(Request $request, array $data, ?KerjaSamaHumas $mou): void
    {
        if (array_key_exists('dokumen_humas_id', $data) && (int) ($data['dokumen_humas_id'] ?? 0) !== (int) ($mou?->dokumen_humas_id ?? 0)) {
            abort_unless($request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']), 403);
            if (! empty($data['dokumen_humas_id'])) {
                $dokumen = DokumenHumas::findOrFail($data['dokumen_humas_id']);
                if ($dokumen->status !== 'aktif') {
                    throw ValidationException::withMessages(['dokumen_humas_id' => 'Pilih dokumen aktif dari Pusat Dokumen Humas.']);
                }
            }
        }
    }

    private function pastikanStatusMou(KerjaSamaHumas $mou): void
    {
        if ($mou->status === 'aktif') {
            if (! $mou->tanggal_mulai || ! $mou->tanggal_selesai) {
                throw ValidationException::withMessages(['tanggal_mulai' => 'MoU aktif memerlukan tanggal mulai dan berakhir.']);
            }
            $dokumen = $mou->dokumen_humas_id ? DokumenHumas::find($mou->dokumen_humas_id) : null;
            if (! $dokumen || $dokumen->status !== 'aktif' || ! Storage::disk('local')->exists($dokumen->lokasi_file)) {
                throw ValidationException::withMessages(['dokumen_humas_id' => 'Hubungkan berkas MoU aktif yang sudah tersimpan sebelum mengaktifkan perjanjian.']);
            }
        }
        if ($mou->status === 'diakhiri' && strlen(trim($mou->alasan_diakhiri ?? '')) < 5) {
            throw ValidationException::withMessages(['alasan_diakhiri' => 'Tuliskan alasan pengakhiran MoU, minimal 5 karakter.']);
        }
    }

    private function pastikanMouMitra(MitraHumas $mitra, KerjaSamaHumas $mou): void
    {
        abort_unless($mou->mitra_humas_id === $mitra->id, 404);
    }

    private function aturanMitra(): array
    {
        return ['nama' => ['required', 'string', 'max:180'], 'jenis' => ['required', Rule::in(array_keys(MitraHumas::JENIS))],
            'alamat' => ['nullable', 'string', 'max:1000'], 'nama_kontak' => ['nullable', 'string', 'max:180'], 'jabatan_kontak' => ['nullable', 'string', 'max:120'],
            'nomor_kontak' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9][0-9\s().-]{5,30}$/'],
            'email' => ['nullable', 'email', 'max:180'], 'website' => ['nullable', 'url:http,https', 'max:1000'],
            'catatan' => ['nullable', 'string', 'max:5000'], 'status' => ['required', Rule::in(array_keys(MitraHumas::STATUS))]];
    }

    private function aturanMou(): array
    {
        return ['judul' => ['required', 'string', 'max:180'], 'nomor' => ['nullable', 'string', 'max:120'], 'bidang' => ['required', Rule::in(array_keys(KerjaSamaHumas::BIDANG))],
            'ruang_lingkup' => ['required', 'string', 'max:5000'], 'penanggung_jawab' => ['nullable', 'string', 'max:180'],
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'], 'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'required_with:tanggal_mulai', Rule::when(filled(request('tanggal_mulai')), 'after_or_equal:tanggal_mulai')],
            'ingatkan_hari_sebelum' => ['required', 'integer', Rule::in(KerjaSamaHumas::PENGINGAT)],
            'status' => ['required', Rule::in(array_keys(KerjaSamaHumas::STATUS))], 'alasan_diakhiri' => ['nullable', 'string', 'max:1000', 'required_if:status,diakhiri'],
            'dokumen_humas_id' => ['nullable', 'integer', 'exists:dokumen_humas,id']];
    }

    private function filter(Request $request): array
    {
        return $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'jenis' => ['nullable', Rule::in(array_keys(MitraHumas::JENIS))],
            'status_mitra' => ['nullable', Rule::in(array_keys(MitraHumas::STATUS))], 'bidang' => ['nullable', Rule::in(array_keys(KerjaSamaHumas::BIDANG))],
            'masa_berlaku' => ['nullable', Rule::in(array_keys(KerjaSamaHumas::STATUS_BERLAKU))],
            'dari' => ['nullable', 'date_format:Y-m-d'], 'sampai' => ['nullable', 'date_format:Y-m-d', Rule::when(filled($request->input('dari')), 'after_or_equal:dari')]]);
    }

    private function queryLaporan(array $filter)
    {
        return KerjaSamaHumas::query()->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q
            ->whereLike('judul', '%'.$kata.'%')->orWhereLike('nomor', '%'.$kata.'%')->orWhereHas('mitra', fn ($q) => $q->whereLike('nama', '%'.$kata.'%'))))
            ->when($filter['jenis'] ?? null, fn ($q, $jenis) => $q->whereHas('mitra', fn ($q) => $q->where('jenis', $jenis)))
            ->when($filter['status_mitra'] ?? null, fn ($q, $status) => $q->whereHas('mitra', fn ($q) => $q->where('status', $status)))
            ->when($filter['bidang'] ?? null, fn ($q, $bidang) => $q->where('bidang', $bidang))
            ->when($filter['masa_berlaku'] ?? null, fn ($q, $status) => $q->denganStatusBerlaku($status))
            ->when($filter['dari'] ?? null, fn ($q, $dari) => $q->whereDate('tanggal_selesai', '>=', $dari))
            ->when($filter['sampai'] ?? null, fn ($q, $sampai) => $q->whereDate('tanggal_selesai', '<=', $sampai));
    }

    private function statistik(): array
    {
        return ['mitra_aktif' => MitraHumas::where('status', 'aktif')->count(),
            'berlaku' => KerjaSamaHumas::where('status', 'aktif')->whereDate('tanggal_mulai', '<=', today())->whereDate('tanggal_selesai', '>=', today())->count(),
            'segera_berakhir' => KerjaSamaHumas::query()->denganStatusBerlaku('segera_berakhir')->count(),
            'kedaluwarsa' => KerjaSamaHumas::query()->denganStatusBerlaku('kedaluwarsa')->count()];
    }
}
