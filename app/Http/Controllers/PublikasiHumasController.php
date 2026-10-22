<?php

namespace App\Http\Controllers;

use App\Models\AgendaHumas;
use App\Models\AsetPromosiHumas;
use App\Models\LampiranPublikasiHumas;
use App\Models\PublikasiHumas;
use App\Services\Humas\AksesPublikasiHumasService;
use App\Services\Humas\KelolaAsetPromosiHumasService;
use App\Services\Humas\KelolaPublikasiHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublikasiHumasController extends Controller
{
    public function __construct(private readonly KelolaPublikasiHumasService $kelola, private readonly KelolaAsetPromosiHumasService $bankAset) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_keys(PublikasiHumas::STATUS))],
            'jenis' => ['nullable', Rule::in(array_keys(PublikasiHumas::JENIS))],
            'kanal' => ['nullable', Rule::in(array_keys(PublikasiHumas::KANAL))]]);
        $akses = app(AksesPublikasiHumasService::class);
        $query = $akses->query($request->user())->with('pembuat:id,nama')->withCount(['lampiran as jumlah_foto' => fn ($q) => $q->where('jenis', 'foto')->whereNull('dihapus_pada')])
            ->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('judul', '%'.$kata.'%')->orWhereLike('ringkasan', '%'.$kata.'%')));
        foreach (['status', 'jenis', 'kanal'] as $kolom) {
            if (! empty($filter[$kolom])) {
                $query->where($kolom, $filter[$kolom]);
            }
        }
        $statistik = $akses->query($request->user())->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');
        $page = $request->routeIs('api.v1.*') ? $request->validate(['halaman' => ['nullable', 'integer', 'min:1'], 'per_halaman' => ['nullable', 'integer', 'min:1', 'max:50']]) : [];
        $daftar = $query->latest('updated_at')->orderByDesc('id')->paginate($page['per_halaman'] ?? 20, ['*'], $request->routeIs('api.v1.*') ? 'halaman' : 'page', $page['halaman'] ?? null)->withQueryString();
        if ($request->routeIs('api.v1.*')) {
            return response()->json(['data' => app(HumasMobileService::class)->paginasi($daftar, fn ($p) => $this->dataApi($request, $p))
                + ['filter' => $filter, 'statistik' => (object) $statistik->all(), 'cakupan' => $akses->sendiri($request->user()) ? 'milik_sendiri' : 'seluruh']])->header('Cache-Control', 'private, no-store');
        }

        return view('publikasi-humas.index', ['daftar' => $daftar, 'filter' => $filter, 'statistik' => $statistik,
            'cakupanSendiri' => $akses->sendiri($request->user()), 'dapatBuat' => $akses->dapatKelola($request->user())]);
    }

    public function create(Request $request)
    {
        app(AksesPublikasiHumasService::class)->pastikan($request->user(), null, true);

        return $this->form($request, new PublikasiHumas(['jenis' => 'berita', 'kanal' => 'website']));
    }

    public function pilihanAset(Request $request)
    {
        app(AksesPublikasiHumasService::class)->pastikan($request->user(), null, true);
        abort_unless($request->user()->memilikiIzin(AsetPromosiHumas::IZIN_LIHAT), 403);
        $data = $request->validate(['cari' => ['nullable', 'string', 'max:120']]);
        $bolehDokumen = $request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);

        $aset = AsetPromosiHumas::where('status', 'aktif')
            ->when(! $bolehDokumen, fn ($q) => $q->where('sumber', 'tautan'))
            ->when($data['cari'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('nama', '%'.$kata.'%')->orWhereLike('kata_kunci', '%'.$kata.'%')))
            ->latest('updated_at')->limit(100)->get(['id', 'nama', 'kategori', 'versi'])->map(fn ($item) => ['id' => $item->id, 'nama' => $item->nama,
                'kategori' => AsetPromosiHumas::KATEGORI[$item->kategori], 'versi' => $item->versi + 1]);

        return response()->json($request->routeIs('api.v1.*') ? ['data' => ['items' => $aset]] : ['aset' => $aset])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        app(AksesPublikasiHumasService::class)->pastikan($request->user(), null, true);
        $data = $request->validate($this->aturanKonten() + ['token_pembuatan' => ['required', 'uuid']]);
        $this->pastikanAgenda($request, $data, null);
        $files = $this->simpanFoto($request);
        try {
            $publikasi = DB::transaction(function () use ($request, $data, $files) {
                $konten = collect($data)->except(['foto', 'hapus_foto', 'aset_dikirim', 'aset_ids', 'perbarui_aset'])->all();
                $publikasi = PublikasiHumas::firstOrCreate(['token_pembuatan' => $data['token_pembuatan']],
                    $konten + ['dibuat_oleh_pengguna_id' => $request->user()->id]);
                abort_unless($publikasi->dibuat_oleh_pengguna_id === $request->user()->id, 403);
                if ($publikasi->wasRecentlyCreated) {
                    $publikasi->lampiran()->createMany($files);
                    $this->bankAset->sinkronkan($publikasi, $request, $data);
                    $publikasi->refresh();
                    $this->kelola->catat($publikasi, $request->user(), 'Draf dibuat');
                }

                return $publikasi;
            });
        } catch (Throwable $e) {
            $this->hapusBerkasBaru($files);
            throw $e;
        }
        $tersimpan = $publikasi->lampiran()->pluck('lokasi_file')->all();
        $this->hapusBerkasBaru(array_filter($files, fn ($f) => ! in_array($f['lokasi_file'], $tersimpan, true)));

        return $this->selesai($request, $publikasi, 'Draf publikasi berhasil disimpan.');
    }

    public function show(Request $request, PublikasiHumas $publikasi)
    {
        app(AksesPublikasiHumasService::class)->pastikan($request->user(), $publikasi);
        if ($request->routeIs('api.v1.*')) {
            return response()->json(['data' => $this->dataApi($request, $publikasi, true)])->header('Cache-Control', 'private, no-store');
        }
        $publikasi->load(['pembuat:id,nama', 'pemeriksa:id,nama', 'lampiran']);
        $bolehAgenda = $request->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']);
        if ($bolehAgenda) {
            $publikasi->load('agenda');
        }
        $bolehAset = $request->user()->memilikiIzin(AsetPromosiHumas::IZIN_LIHAT);
        $bolehDokumen = $request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        if ($bolehAset) {
            $publikasi->load($bolehDokumen ? 'aset.berkas' : 'aset');
        }

        return view('publikasi-humas.show', ['publikasi' => $publikasi, 'bolehAgenda' => $bolehAgenda, 'bolehAset' => $bolehAset, 'bolehDokumen' => $bolehDokumen,
            'riwayat' => $publikasi->riwayat()->with('pengguna:id,nama')->paginate(15)]);
    }

    public function edit(Request $request, PublikasiHumas $publikasi)
    {
        app(AksesPublikasiHumasService::class)->pastikan($request->user(), $publikasi, true);
        $this->kelola->pastikanBolehEdit($publikasi);

        return $this->form($request, $publikasi);
    }

    public function update(Request $request, PublikasiHumas $publikasi)
    {
        app(AksesPublikasiHumasService::class)->pastikan($request->user(), $publikasi, true);
        $data = $request->validate($this->aturanKonten() + ['versi' => ['required', 'integer', 'min:0']]);
        $files = $this->simpanFoto($request);
        try {
            DB::transaction(function () use ($request, $publikasi, $data, $files) {
                $publikasi = PublikasiHumas::lockForUpdate()->findOrFail($publikasi->id);
                $this->kelola->pastikanVersi($publikasi, (int) $data['versi']);
                $this->kelola->pastikanBolehEdit($publikasi);
                $this->pastikanAgenda($request, $data, $publikasi);
                $foto = $publikasi->lampiran()->where('jenis', 'foto')->whereNull('dihapus_pada')->get();
                $hapus = array_map('intval', $data['hapus_foto'] ?? []);
                if (array_diff($hapus, $foto->modelKeys())) {
                    throw ValidationException::withMessages(['hapus_foto' => 'Foto tidak ditemukan pada draf ini.']);
                }
                if ($foto->count() - count($hapus) + count($files) > 5) {
                    throw ValidationException::withMessages(['foto' => 'Maksimal 5 foto aktif per konten.']);
                }
                $publikasi->fill(collect($data)->except(['foto', 'hapus_foto', 'versi', 'aset_dikirim', 'aset_ids', 'perbarui_aset'])->all());
                $asetBerubah = $this->bankAset->sinkronkan($publikasi, $request, $data);
                if ($publikasi->isDirty() || $hapus || $files || $asetBerubah) {
                    // Keep removed photos for the immutable review snapshots.
                    $publikasi->lampiran()->whereIn('id', $hapus)->update(['dihapus_pada' => now()]);
                    $publikasi->lampiran()->createMany($files);
                    $publikasi->forceFill(['versi' => $publikasi->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                    $this->kelola->catat($publikasi, $request->user(), 'Draf diperbarui');
                }
            });
        } catch (Throwable $e) {
            $this->hapusBerkasBaru($files);
            throw $e;
        }

        return $this->selesai($request, $publikasi, 'Perubahan draf berhasil disimpan.');
    }

    public function ubahStatus(Request $request, PublikasiHumas $publikasi, string $aksi)
    {
        app(AksesPublikasiHumasService::class)->pastikan($request->user(), $publikasi);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'],
            'catatan' => [in_array($aksi, ['minta-revisi', 'buka-revisi']) ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
            'url_tayang' => [$aksi === 'tayang' ? 'required' : 'nullable', 'url:http,https', 'max:2000'],
            'waktu_tayang' => [$aksi === 'tayang' ? 'required' : 'nullable', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            'bukti' => ['nullable', Rule::prohibitedIf($aksi !== 'tayang'), 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:5120']]);
        $files = $request->hasFile('bukti') ? [$this->simpanBerkas($request->file('bukti'), 'bukti')] : [];
        try {
            DB::transaction(function () use ($request, $publikasi, $aksi, $data, $files) {
                $publikasi = PublikasiHumas::lockForUpdate()->findOrFail($publikasi->id);
                $publikasi->lampiran()->createMany($files);
                $this->kelola->transisi($publikasi, $request->user(), $aksi, $data);
            });
        } catch (Throwable $e) {
            $this->hapusBerkasBaru($files);
            throw $e;
        }

        return $this->selesai($request, $publikasi, 'Status publikasi berhasil diperbarui.');
    }

    public function berkas(PublikasiHumas $publikasi, LampiranPublikasiHumas $lampiran, Request $request)
    {
        app(AksesPublikasiHumasService::class)->pastikan($request->user(), $publikasi);
        abort_unless($lampiran->publikasi_humas_id === $publikasi->id, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($lampiran->lokasi_file), 404);
        $headers = ['Content-Type' => $lampiran->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store, max-age=0'];

        return $request->boolean('unduh') ? $disk->download($lampiran->lokasi_file, $lampiran->nama_file_asli, $headers)
            : $disk->response($lampiran->lokasi_file, $lampiran->nama_file_asli, $headers);
    }

    public function naskah(Request $request, PublikasiHumas $publikasi)
    {
        app(AksesPublikasiHumasService::class)->pastikan($request->user(), $publikasi);

        return response()->streamDownload(function () use ($publikasi) {
            echo $publikasi->judul."\r\n\r\n".$publikasi->isi;
        }, 'naskah-publikasi-'.$publikasi->id.'.txt', ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function form(Request $request, PublikasiHumas $publikasi)
    {
        $cari = $request->validate(['cari_agenda' => ['nullable', 'string', 'max:120'], 'aset_awal' => ['nullable', 'integer', 'exists:aset_promosi_humas,id']]);
        $bolehAgenda = $request->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']);
        $agenda = collect();
        if ($bolehAgenda) {
            $agenda = AgendaHumas::where('status', '!=', 'dibatalkan')->when($cari['cari_agenda'] ?? null, fn ($q, $kata) => $q->whereLike('judul', '%'.$kata.'%'))
                ->latest('waktu_mulai')->limit(100)->get(['id', 'judul', 'waktu_mulai']);
            if ($publikasi->agenda_humas_id) {
                $agenda = $agenda->merge(AgendaHumas::whereKey($publikasi->agenda_humas_id)->get(['id', 'judul', 'waktu_mulai']))->unique('id');
            }
        }

        $bolehAset = $request->user()->memilikiIzin(AsetPromosiHumas::IZIN_LIHAT);
        $bolehDokumen = $request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        $asetTerpakai = $bolehAset && $publikasi->exists ? $publikasi->aset()->get()->keyBy('aset_promosi_humas_id') : collect();
        $pilihanAset = collect();
        if ($bolehAset) {
            $pilihanAset = AsetPromosiHumas::where('status', 'aktif')->when(! $bolehDokumen, fn ($q) => $q->where('sumber', 'tautan'))->latest('updated_at')->limit(100)->get();
            $ids = array_merge($asetTerpakai->keys()->all(), old('aset_ids', []), empty($cari['aset_awal']) ? [] : [$cari['aset_awal']]);
            $pilihanAset = $pilihanAset->merge(AsetPromosiHumas::whereIn('id', $ids)->get())->unique('id');
        }

        return view('publikasi-humas.form', ['publikasi' => $publikasi, 'agenda' => $agenda, 'bolehAgenda' => $bolehAgenda,
            'bolehAset' => $bolehAset, 'bolehDokumen' => $bolehDokumen, 'pilihanAset' => $pilihanAset, 'asetTerpakai' => $asetTerpakai,
            'foto' => $publikasi->exists ? $publikasi->lampiran()->where('jenis', 'foto')->whereNull('dihapus_pada')->get() : collect(),
            'tokenPembuatan' => (string) Str::uuid()]);
    }

    private function pastikanAgenda(Request $request, array $data, ?PublikasiHumas $publikasi): void
    {
        if (! array_key_exists('agenda_humas_id', $data) || (int) ($data['agenda_humas_id'] ?? 0) === (int) ($publikasi?->agenda_humas_id ?? 0)) {
            return;
        }
        abort_unless($request->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']), 403);
        if (! empty($data['agenda_humas_id']) && AgendaHumas::findOrFail($data['agenda_humas_id'])->status === 'dibatalkan') {
            throw ValidationException::withMessages(['agenda_humas_id' => 'Pilih agenda yang tidak dibatalkan.']);
        }
    }

    private function aturanKonten(): array
    {
        return ['judul' => ['required', 'string', 'max:180'], 'jenis' => ['required', Rule::in(array_keys(PublikasiHumas::JENIS))],
            'ringkasan' => ['nullable', 'string', 'max:500'], 'isi' => ['required', 'string', 'min:10', 'max:20000'],
            'kanal' => ['required', Rule::in(array_keys(PublikasiHumas::KANAL))], 'rencana_tayang' => ['nullable', 'date_format:Y-m-d'],
            'agenda_humas_id' => ['nullable', 'integer', 'exists:agenda_humas,id'],
            'foto' => ['nullable', 'array', 'max:5'], 'foto.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'dimensions:max_width=6000,max_height=6000', 'max:2048'],
            'hapus_foto' => ['nullable', 'array', 'max:5'], 'hapus_foto.*' => ['required', 'integer', 'distinct'],
            'aset_dikirim' => ['sometimes', 'accepted'], 'aset_ids' => ['nullable', 'array', 'max:10'],
            'aset_ids.*' => ['required', 'integer', 'distinct', 'exists:aset_promosi_humas,id'],
            'perbarui_aset' => ['nullable', 'array', 'max:10'], 'perbarui_aset.*' => ['required', 'integer', 'distinct']];
    }

    private function simpanFoto(Request $request): array
    {
        $files = [];
        try {
            foreach ($request->file('foto', []) as $foto) {
                $files[] = $this->simpanBerkas($foto, 'foto');
            }
        } catch (Throwable $e) {
            $this->hapusBerkasBaru($files);
            throw $e;
        }

        return $files;
    }

    private function simpanBerkas($file, string $jenis): array
    {
        $path = $file->storeAs('publikasi-humas', Str::uuid().'.'.$file->extension(), 'local');
        if (! $path) {
            throw ValidationException::withMessages([$jenis => 'Berkas belum dapat disimpan. Coba kembali.']);
        }

        return ['jenis' => $jenis, 'lokasi_file' => $path, 'nama_file_asli' => $file->getClientOriginalName(),
            'tipe_file' => $file->getMimeType(), 'ukuran_file' => $file->getSize()];
    }

    private function hapusBerkasBaru(array $files): void
    {
        foreach ($files as $file) {
            Storage::disk('local')->delete($file['lokasi_file']);
        }
    }

    private function selesai(Request $request, PublikasiHumas $publikasi, string $pesan)
    {
        if ($request->routeIs('api.v1.*')) {
            $publikasi->refresh();

            return response()->json(['data' => $this->dataApi($request, $publikasi, true), 'pesan' => $pesan], $publikasi->wasRecentlyCreated ? 201 : 200)
                ->header('Cache-Control', 'private, no-store');
        }
        $url = route('publikasi-humas.show', $publikasi);
        $request->session()->flash('berhasil', $pesan);

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'pesan' => $pesan]) : redirect($url);
    }

    public function referensi(Request $request)
    {
        $akses = app(AksesPublikasiHumasService::class);
        $akses->pastikan($request->user());

        return response()->json(['data' => ['jenis' => PublikasiHumas::JENIS, 'kanal' => PublikasiHumas::KANAL, 'status' => PublikasiHumas::STATUS,
            'token_pembuatan' => (string) Str::uuid(), 'dapat_buat' => $akses->dapatKelola($request->user()), 'cakupan' => $akses->sendiri($request->user()) ? 'milik_sendiri' : 'seluruh']])
            ->header('Cache-Control', 'private, no-store');
    }

    private function dataApi(Request $r, PublikasiHumas $p, bool $detail = false): array
    {
        $akses = app(AksesPublikasiHumasService::class);
        $ubah = $akses->dapatKelola($r->user(), $p);
        $data = $p->only(['id', 'judul', 'jenis', 'ringkasan', 'kanal', 'status', 'versi']) + ['rencana_tayang' => $p->rencana_tayang?->format('Y-m-d'),
            'hak_akses' => ['dapat_edit' => $ubah && $p->bolehDiedit(), 'dapat_ajukan' => $ubah && $p->bolehDiedit(), 'dapat_tarik' => $ubah && $p->status === 'diajukan',
                'dapat_periksa' => $r->user()->memilikiIzin('publikasi_humas.periksa') && $p->status === 'diajukan' && ! in_array($r->user()->id, [$p->dibuat_oleh_pengguna_id, $p->diajukan_oleh_pengguna_id], true),
                'dapat_tayang' => $r->user()->memilikiIzin('publikasi_humas.kelola') && $p->status === 'disetujui']];
        if ($detail) {
            $data += $p->only(['isi', 'catatan_pemeriksaan', 'url_tayang']);
            $data['waktu_tayang'] = $p->waktu_tayang?->toIso8601String();
            $data['lampiran'] = $p->lampiran()->whereNull('dihapus_pada')->get()->map(fn ($l) => ['id' => $l->id, 'jenis' => $l->jenis, 'nama' => $l->nama_file_asli,
                'url' => route('api.v1.humas.publikasi.berkas', [$p, $l])])->all();
            $data['agenda_humas_id'] = $r->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']) ? $p->agenda_humas_id : null;
        }

        return $data;
    }
}
