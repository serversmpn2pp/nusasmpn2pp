<?php

namespace App\Http\Controllers;

use App\Models\BuktiAkreditasiHumas;
use App\Models\ButirAkreditasiHumas;
use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\PortofolioAkreditasiHumas;
use App\Models\RiwayatDokumenHumas;
use App\Models\TahunPelajaran;
use App\Services\Humas\PortofolioAkreditasiHumasService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PortofolioAkreditasiHumasController extends Controller
{
    public function __construct(private readonly PortofolioAkreditasiHumasService $kelola) {}

    public function index(Request $request)
    {
        $this->akses($request);
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'status' => ['nullable', Rule::in(array_keys(PortofolioAkreditasiHumas::STATUS))]]);
        $query = PortofolioAkreditasiHumas::query()->when($filter['tahun_pelajaran_id'] ?? null, fn ($q, $id) => $q->where('tahun_pelajaran_id', $id))
            ->when($filter['kata_kunci'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->whereLike('nama', '%'.$s.'%')->orWhereLike('instrumen', '%'.$s.'%')));
        $jumlah = (clone $query)->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');
        $query->when($filter['status'] ?? null, fn ($q, $s) => $q->where('status', $s));

        return $this->halaman('index', ['filter' => $filter, 'jumlah' => $jumlah, 'tahun' => TahunPelajaran::orderByDesc('nama')->get(['id', 'nama']),
            'daftar' => $query->with('tahunPelajaran:id,nama')->withCount(['butir', 'butir as terpenuhi_count' => fn ($q) => $q->where('status', 'terpenuhi')])->latest('updated_at')->orderByDesc('id')->paginate(20)->withQueryString()]);
    }

    public function create(Request $request)
    {
        $this->akses($request);

        return $this->form(new PortofolioAkreditasiHumas(['tahun_pelajaran_id' => TahunPelajaran::where('aktif', true)->value('id'), 'penanggung_jawab' => $request->user()->nama]));
    }

    public function store(Request $request)
    {
        $this->akses($request);
        $data = $this->validasi($request);
        $p = DB::transaction(function () use ($request, $data) {
            Izin::where('kode', 'akreditasi_humas.kelola')->lockForUpdate()->firstOrFail();
            $p = PortofolioAkreditasiHumas::where('token_pembuatan', $data['token_pembuatan'])->first();
            if ($p) {
                abort_unless($p->dibuat_oleh_pengguna_id === $request->user()->id, 403);

                return $p;
            }
            $p = new PortofolioAkreditasiHumas(Arr::only($data, ['token_pembuatan', ...PortofolioAkreditasiHumas::KOLOM]));
            $p->forceFill(['status' => 'draf', 'versi' => 0, 'dibuat_oleh_pengguna_id' => $request->user()->id])->save();
            $this->kelola->catat($p, $request, 'Portofolio dibuat', null, false);

            return $p;
        });

        return $this->kembali($p, 'Portofolio berhasil dibuat.');
    }

    public function show(Request $request, PortofolioAkreditasiHumas $portofolio)
    {
        $this->akses($request);
        $portofolio->load('tahunPelajaran', 'butir.bukti.berkas');

        return $this->halaman('show', ['portofolio' => $portofolio, 'ringkasan' => $this->kelola->ringkasan($portofolio),
            'riwayat' => $portofolio->riwayat()->with('pengguna:id,nama')->paginate(10, ['*'], 'riwayat')]);
    }

    public function edit(Request $request, PortofolioAkreditasiHumas $portofolio)
    {
        $this->akses($request);
        abort_unless($portofolio->status === 'draf', 403);

        return $this->form($portofolio);
    }

    public function update(Request $request, PortofolioAkreditasiHumas $portofolio)
    {
        $this->akses($request);
        $data = $this->validasi($request, true);
        DB::transaction(function () use ($request, $portofolio, $data) {
            $p = $this->kelola->kunci($portofolio, $data['versi']);
            $p->fill(Arr::only($data, PortofolioAkreditasiHumas::KOLOM));
            if ($p->isDirty(['instrumen', 'tahun_pelajaran_id'])) {
                foreach ($p->butir()->get() as $b) {
                    $this->kelola->resetPemeriksaan($b);
                }
            }
            $p->save();
            $this->kelola->catat($p, $request, 'Identitas portofolio diperbarui', $data['alasan']);
        });

        return $this->kembali($portofolio, 'Perubahan tersimpan.');
    }

    public function status(Request $request, PortofolioAkreditasiHumas $portofolio)
    {
        $this->akses($request);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'status' => ['required', Rule::in(array_keys(PortofolioAkreditasiHumas::STATUS))],
            'alasan' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $portofolio, $data) {
            $p = $this->kelola->kunci($portofolio, $data['versi'], false);
            $tujuan = $data['status'];
            $sah = ['draf' => ['siap', 'arsip'], 'siap' => ['draf', 'arsip'], 'arsip' => ['draf']];
            if (! in_array($tujuan, $sah[$p->status], true)) {
                $this->gagal('Perubahan status tidak sesuai.');
            }
            if ($tujuan === 'siap') {
                $this->aksesDokumen($request);
                $this->kelola->pastikanSiap($p);
            }
            $p->forceFill(['status' => $tujuan])->save();
            $this->kelola->catat($p, $request, 'Status: '.PortofolioAkreditasiHumas::STATUS[$tujuan], $data['alasan']);
        });

        return $this->kembali($portofolio, 'Status portofolio diperbarui.');
    }

    public function tambahButir(Request $request, PortofolioAkreditasiHumas $portofolio)
    {
        $this->akses($request);
        abort_unless($portofolio->status === 'draf', 403);

        return $this->halaman('butir', ['portofolio' => $portofolio, 'butir' => new ButirAkreditasiHumas(['urutan' => ($portofolio->butir()->max('urutan') ?? 0) + 1, 'target_bukti' => 1]), 'dokumen' => null]);
    }

    public function simpanButir(Request $request, PortofolioAkreditasiHumas $portofolio, ?ButirAkreditasiHumas $butir = null)
    {
        $this->akses($request);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'kode' => ['required', 'string', 'max:40'], 'judul' => ['required', 'string', 'max:180'],
            'deskripsi' => ['nullable', 'string', 'max:5000'], 'urutan' => ['required', 'integer', 'min:1', 'max:999'], 'target_bukti' => ['required', 'integer', 'min:1', 'max:200']]);
        $data['kode'] = Str::upper($data['kode']);
        $b = DB::transaction(function () use ($request, $portofolio, $butir, $data) {
            $p = $this->kelola->kunci($portofolio, $data['versi']);
            $b = $butir ? $this->kelola->butir($p, $butir) : new ButirAkreditasiHumas;
            if (! $b->exists && $p->butir()->count() >= 100) {
                $this->gagal('Maksimal 100 butir per portofolio.');
            }
            if ($p->butir()->where('kode', $data['kode'])->when($b->exists, fn ($q) => $q->where('id', '!=', $b->id))->exists()) {
                $this->gagal('Kode butir sudah digunakan dalam portofolio ini.');
            }
            $b->fill(Arr::only($data, ButirAkreditasiHumas::KOLOM));
            $b->forceFill(['portofolio_akreditasi_humas_id' => $p->id])->save();
            $this->kelola->resetPemeriksaan($b);
            $this->kelola->catat($p, $request, 'Butir disimpan: '.$b->kode);

            return $b;
        });

        return redirect()->route('akreditasi-humas.butir', [$portofolio, $b])->with('berhasil', 'Butir tersimpan. Pemeriksaan perlu dilakukan kembali setelah perubahan.');
    }

    public function butir(Request $request, PortofolioAkreditasiHumas $portofolio, ButirAkreditasiHumas $butir)
    {
        $this->akses($request);
        $this->milik($portofolio, $butir);
        $butir->load('bukti.berkas', 'pemeriksa:id,nama');
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'kategori' => ['nullable', Rule::in(array_keys(DokumenHumas::KATEGORI))]]);
        $dokumen = null;
        if ($portofolio->status === 'draf' && $request->user()->memilikiIzin('akreditasi_humas.kelola') && $request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola'])) {
            $dokumen = DokumenHumas::where('status', 'aktif')->when($filter['kata_kunci'] ?? null, fn ($q, $s) => $q->whereLike('judul', '%'.$s.'%'))
                ->when($filter['kategori'] ?? null, fn ($q, $s) => $q->where('kategori', $s))->with(['riwayat' => fn ($q) => $q->limit(1)])->latest('updated_at')->orderByDesc('id')->paginate(10)->withQueryString();
            foreach ($dokumen as $d) {
                try {
                    $d->berkas_tersedia = $d->riwayat->first() && $this->kelola->berkas($d->riwayat->first());
                } catch (ValidationException) {
                    $d->berkas_tersedia = false;
                }
            }
        }

        return $this->halaman('butir', ['portofolio' => $portofolio, 'butir' => $butir, 'dokumen' => $dokumen, 'filter' => $filter, 'tokenBukti' => (string) Str::uuid()]);
    }

    public function hapusButir(Request $request, PortofolioAkreditasiHumas $portofolio, ButirAkreditasiHumas $butir)
    {
        $this->akses($request);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'alasan' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $portofolio, $butir, $data) {
            $p = $this->kelola->kunci($portofolio, $data['versi']);
            $b = $this->kelola->butir($p, $butir);
            $b->forceFill(['dihapus_pada' => now()])->save();
            $this->kelola->catat($p, $request, 'Butir dikeluarkan: '.$b->kode, $data['alasan']);
        });

        return $this->kembali($portofolio, 'Butir dikeluarkan dari portofolio. Dokumen asli tidak dihapus.');
    }

    public function tambahBukti(Request $request, PortofolioAkreditasiHumas $portofolio, ButirAkreditasiHumas $butir)
    {
        $this->akses($request);
        $this->aksesDokumen($request);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'token_pembuatan' => ['required', 'uuid'],
            'riwayat_dokumen_humas_id' => ['required', 'integer', 'exists:riwayat_dokumen_humas,id'], 'catatan' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $portofolio, $butir, $data) {
            $p = PortofolioAkreditasiHumas::lockForUpdate()->findOrFail($portofolio->id);
            $b = $this->kelola->butir($p, $butir);
            $ada = BuktiAkreditasiHumas::where('token_pembuatan', $data['token_pembuatan'])->first();
            if ($ada) {
                abort_unless($ada->butir_akreditasi_humas_id === $b->id && $ada->dibuat_oleh_pengguna_id === $request->user()->id, 403);
                if ($ada->riwayat_dokumen_humas_id !== $data['riwayat_dokumen_humas_id'] || $ada->dilepas_pada) {
                    $this->gagal('Permintaan bukti telah digunakan. Muat ulang sebelum memilih bukti lain.');
                }

                return;
            }
            $p = $this->kelola->kunci($p, $data['versi']);
            $berkas = RiwayatDokumenHumas::findOrFail($data['riwayat_dokumen_humas_id']);
            $dokumen = DokumenHumas::lockForUpdate()->findOrFail($berkas->dokumen_humas_id);
            if ($dokumen->status !== 'aktif') {
                $this->gagal('Dokumen sudah diarsipkan. Pilih dokumen aktif.');
            }
            $this->kelola->berkas($berkas);
            if ($b->bukti()->where('riwayat_dokumen_humas_id', $berkas->id)->exists()) {
                $this->gagal('Versi dokumen ini sudah ditautkan pada butir tersebut.');
            }
            $total = BuktiAkreditasiHumas::whereNull('dilepas_pada')->whereIn('butir_akreditasi_humas_id', $p->butir()->select('id'))->count();
            if ($total >= PortofolioAkreditasiHumasService::MAKS_BERKAS) {
                $this->gagal('Maksimal 200 bukti per portofolio.');
            }
            $b->bukti()->create(['token_pembuatan' => $data['token_pembuatan'], 'riwayat_dokumen_humas_id' => $berkas->id, 'judul' => $dokumen->judul,
                'catatan' => $data['catatan'] ?? null, 'dibuat_oleh_pengguna_id' => $request->user()->id]);
            $this->kelola->resetPemeriksaan($b);
            $this->kelola->catat($p, $request, 'Bukti ditautkan pada butir '.$b->kode);
        });

        return redirect()->route('akreditasi-humas.butir', [$portofolio, $butir])->with('berhasil', 'Bukti ditautkan. Versi dokumen terpilih tetap tersimpan.');
    }

    public function lepasBukti(Request $request, PortofolioAkreditasiHumas $portofolio, ButirAkreditasiHumas $butir, BuktiAkreditasiHumas $bukti)
    {
        $this->akses($request);
        $this->aksesDokumen($request);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'alasan' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $portofolio, $butir, $bukti, $data) {
            $p = $this->kelola->kunci($portofolio, $data['versi']);
            $b = $this->kelola->butir($p, $butir);
            $bukti = BuktiAkreditasiHumas::lockForUpdate()->findOrFail($bukti->id);
            abort_unless($bukti->butir_akreditasi_humas_id === $b->id && ! $bukti->dilepas_pada, 404);
            $bukti->forceFill(['dilepas_pada' => now()])->save();
            $this->kelola->resetPemeriksaan($b);
            $this->kelola->catat($p, $request, 'Bukti dilepas dari butir '.$b->kode, $data['alasan']);
        });

        return redirect()->route('akreditasi-humas.butir', [$portofolio, $butir])->with('berhasil', 'Tautan bukti dilepas. Dokumen asli tidak dihapus.');
    }

    public function periksa(Request $request, PortofolioAkreditasiHumas $portofolio, ButirAkreditasiHumas $butir)
    {
        $this->akses($request);
        $this->aksesDokumen($request);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'status' => ['required', Rule::in(array_keys(ButirAkreditasiHumas::STATUS))],
            'catatan_pemeriksaan' => ['required', 'string', 'min:5', 'max:5000']]);
        DB::transaction(function () use ($request, $portofolio, $butir, $data) {
            $p = $this->kelola->kunci($portofolio, $data['versi']);
            $b = $this->kelola->butir($p, $butir);
            if ($data['status'] === 'terpenuhi') {
                $this->kelola->periksaBukti($b);
            }
            $b->forceFill(['status' => $data['status'], 'catatan_pemeriksaan' => $data['catatan_pemeriksaan'],
                'diperiksa_oleh_pengguna_id' => $request->user()->id, 'diperiksa_pada' => now()])->save();
            $this->kelola->catat($p, $request, 'Butir '.$b->kode.': '.ButirAkreditasiHumas::STATUS[$b->status], $data['catatan_pemeriksaan']);
        });

        return redirect()->route('akreditasi-humas.butir', [$portofolio, $butir])->with('berhasil', 'Hasil pemeriksaan tersimpan.');
    }

    public function berkas(Request $request, PortofolioAkreditasiHumas $portofolio, ButirAkreditasiHumas $butir, BuktiAkreditasiHumas $bukti)
    {
        $this->akses($request);
        $this->aksesDokumen($request);
        $this->milik($portofolio, $butir);
        abort_unless($bukti->butir_akreditasi_humas_id === $butir->id && ! $bukti->dilepas_pada, 404);

        return response()->download($this->kelola->berkas($bukti->berkas), $this->kelola->namaUnduh($bukti->judul, $bukti->berkas), ['Cache-Control' => 'private, no-store']);
    }

    public function cetak(Request $request, PortofolioAkreditasiHumas $portofolio)
    {
        $this->akses($request);
        $portofolio->load('tahunPelajaran', 'butir.bukti.berkas');

        return $this->halaman('cetak', ['portofolio' => $portofolio, 'ringkasan' => $this->kelola->ringkasan($portofolio)]);
    }

    public function export(Request $request, PortofolioAkreditasiHumas $portofolio)
    {
        $this->akses($request);
        $this->aksesDokumen($request);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0']]);
        $path = null;
        try {
            [$path, $nama] = DB::transaction(function () use ($request, $portofolio, $data, &$path) {
                $p = $this->kelola->kunci($portofolio, $data['versi'], false);
                if ($p->status !== 'siap') {
                    $this->gagal('Bundel hanya dapat diunduh untuk portofolio berstatus Siap.');
                }
                [$path, $nama] = $this->kelola->bundel($p);
                $this->kelola->catat($p, $request, 'Bundel ZIP diunduh', null, false);

                return [$path, $nama];
            });

            return response()->download($path, $nama, ['Cache-Control' => 'private, no-store', 'Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if ($path && is_file($path)) {
                unlink($path);
            }
            throw $e;
        }
    }

    private function milik(PortofolioAkreditasiHumas $p, ButirAkreditasiHumas $b): void
    {
        abort_unless($b->portofolio_akreditasi_humas_id === $p->id && ! $b->dihapus_pada, 404);
    }

    private function akses(Request $request): void
    {
        abort_unless($request->user()->aktif && ! $request->user()->akunOrangTua() && ! $request->user()->akunSiswa(), 403);
        abort_unless($request->user()->memilikiIzin(['akreditasi_humas.lihat', 'akreditasi_humas.kelola']), 403);
    }

    private function aksesDokumen(Request $request): void
    {
        abort_unless($request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']), 403);
    }

    private function validasi(Request $request, bool $edit = false): array
    {
        return $request->validate(['tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'], 'nama' => ['required', 'string', 'max:180'],
            'instrumen' => ['required', 'string', 'max:180'], 'penanggung_jawab' => ['required', 'string', 'max:180'], 'catatan' => ['nullable', 'string', 'max:5000'],
            'token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid'], 'versi' => [$edit ? 'required' : 'nullable', 'integer', 'min:0'],
            'alasan' => [$edit ? 'required' : 'nullable', 'string', 'min:5', 'max:2000']]);
    }

    private function form(PortofolioAkreditasiHumas $p)
    {
        return $this->halaman('form', ['portofolio' => $p, 'tahun' => TahunPelajaran::orderByDesc('nama')->get(['id', 'nama']), 'tokenPembuatan' => (string) Str::uuid()]);
    }

    private function halaman(string $view, array $data)
    {
        $data += ['bolehKelola' => auth()->user()->memilikiIzin('akreditasi_humas.kelola'),
            'bolehDokumen' => auth()->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']),
            'bolehEkspor' => auth()->user()->memilikiIzin('akreditasi_humas.ekspor') && auth()->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola'])];

        return response()->view('akreditasi-humas.'.$view, $data)->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function kembali(PortofolioAkreditasiHumas $p, string $pesan)
    {
        return redirect()->route('akreditasi-humas.show', $p)->with('berhasil', $pesan);
    }

    private function gagal(string $pesan): never
    {
        throw ValidationException::withMessages(['portofolio' => $pesan]);
    }
}
