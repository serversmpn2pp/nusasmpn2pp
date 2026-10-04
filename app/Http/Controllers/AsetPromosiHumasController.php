<?php

namespace App\Http\Controllers;

use App\Models\AsetPromosiHumas;
use App\Models\DokumenHumas;
use App\Models\PublikasiHumas;
use App\Models\RiwayatDokumenHumas;
use App\Services\Humas\KelolaAsetPromosiHumasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AsetPromosiHumasController extends Controller
{
    public function __construct(private readonly KelolaAsetPromosiHumasService $kelola) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'kategori' => ['nullable', Rule::in(array_keys(AsetPromosiHumas::KATEGORI))],
            'status' => ['nullable', Rule::in(array_keys(AsetPromosiHumas::STATUS))], 'sumber' => ['nullable', Rule::in(['berkas', 'tautan'])]]);
        $bolehDokumen = $this->bolehDokumen($request);
        $query = AsetPromosiHumas::withCount('pemakaian')->when($bolehDokumen, fn ($q) => $q->with('berkas'))
            ->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('nama', '%'.$kata.'%')->orWhereLike('deskripsi', '%'.$kata.'%')->orWhereLike('kata_kunci', '%'.$kata.'%')));
        foreach (['kategori', 'status', 'sumber'] as $kolom) {
            if (! empty($filter[$kolom])) {
                $query->where($kolom, $filter[$kolom]);
            }
        }
        if (! isset($filter['status'])) {
            $query->where('status', 'aktif');
        }

        return view('aset-promosi-humas.index', ['daftar' => $query->latest('updated_at')->orderByDesc('id')->paginate(18)->withQueryString(),
            'filter' => $filter, 'bolehDokumen' => $bolehDokumen,
            'statistik' => ['aktif' => AsetPromosiHumas::where('status', 'aktif')->count(), 'arsip' => AsetPromosiHumas::where('status', 'arsip')->count(),
                'foto' => AsetPromosiHumas::where('status', 'aktif')->whereIn('kategori', ['foto', 'logo'])->count(),
                'tautan' => AsetPromosiHumas::where('status', 'aktif')->where('sumber', 'tautan')->count()]]);
    }

    public function create(Request $request)
    {
        return $this->form($request, new AsetPromosiHumas(['kategori' => 'logo', 'tanggal_aset' => today()]));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturan(false));
        $file = $this->simpanBerkas($request);
        try {
            $aset = DB::transaction(function () use ($request, $data, $file) {
                $aset = AsetPromosiHumas::firstOrCreate(['token_pembuatan' => $data['token_pembuatan']],
                    $this->metadata($data) + ['dibuat_oleh_pengguna_id' => $request->user()->id]);
                abort_unless($aset->dibuat_oleh_pengguna_id === $request->user()->id, 403);
                if ($aset->wasRecentlyCreated) {
                    $aset->forceFill($this->kelola->sumber($request, $data, $file, null) + ['diubah_oleh_pengguna_id' => $request->user()->id])->save();
                    $aset->refresh();
                    $this->kelola->catat($aset, $request->user(), 'Aset ditambahkan');
                }

                return $aset;
            });
        } catch (Throwable $e) {
            $this->hapusBerkas($file);
            throw $e;
        }
        if ($file && $aset->berkas?->lokasi_file !== $file['lokasi_file']) {
            $this->hapusBerkas($file);
        }

        return $this->selesai($request, $aset, 'Aset promosi berhasil disimpan.');
    }

    public function show(Request $request, AsetPromosiHumas $aset)
    {
        $bolehDokumen = $this->bolehDokumen($request);
        if ($bolehDokumen) {
            $aset->load('berkas.dokumen');
        }
        $bolehPublikasi = $request->user()->memilikiIzin(PublikasiHumas::IZIN_LIHAT);

        return view('aset-promosi-humas.show', ['aset' => $aset, 'bolehDokumen' => $bolehDokumen, 'bolehPublikasi' => $bolehPublikasi,
            'pemakaian' => $bolehPublikasi ? $aset->pemakaian()->with('publikasi')->paginate(10, ['*'], 'pemakaian') : collect(),
            'riwayat' => $aset->riwayat()->with('pengguna:id,nama')->when($bolehDokumen, fn ($q) => $q->with('berkas'))->paginate(15, ['*'], 'riwayat')]);
    }

    public function edit(Request $request, AsetPromosiHumas $aset)
    {
        return $this->form($request, $aset);
    }

    public function update(Request $request, AsetPromosiHumas $aset)
    {
        $data = $request->validate($this->aturan(true));
        $file = $this->simpanBerkas($request);
        try {
            DB::transaction(function () use ($request, $aset, $data, $file) {
                $aset = AsetPromosiHumas::lockForUpdate()->findOrFail($aset->id);
                if ($aset->versi !== (int) $data['versi']) {
                    throw ValidationException::withMessages(['versi' => 'Aset telah berubah. Muat ulang halaman untuk menggunakan data terbaru.']);
                }
                $aset->fill(collect($this->metadata($data))->except('token_pembuatan')->all());
                $aset->forceFill($this->kelola->sumber($request, $data, $file, $aset) + ['status' => $data['status']]);
                if ($aset->isDirty()) {
                    $aset->forceFill(['versi' => $aset->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                    $this->kelola->catat($aset, $request->user(), 'Aset diperbarui', $data['catatan_revisi'] ?? null);
                }
            });
        } catch (Throwable $e) {
            $this->hapusBerkas($file);
            throw $e;
        }

        return $this->selesai($request, $aset, 'Perubahan aset berhasil disimpan.');
    }

    public function dokumen(Request $request)
    {
        abort_unless($this->bolehDokumen($request), 403);
        $data = $request->validate(['cari' => ['nullable', 'string', 'max:120']]);

        return response()->json(['dokumen' => DokumenHumas::where('status', 'aktif')->whereHas('riwayat')
            ->when($data['cari'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('judul', '%'.$kata.'%')->orWhereLike('nomor_dokumen', '%'.$kata.'%')))
            ->latest('updated_at')->limit(50)->get(['id', 'judul', 'nomor_dokumen'])]);
    }

    public function berkas(Request $request, AsetPromosiHumas $aset, RiwayatDokumenHumas $berkas)
    {
        abort_unless($this->bolehDokumen($request), 403);
        abort_unless($aset->riwayat_dokumen_humas_id === $berkas->id || $aset->riwayat()->where('riwayat_dokumen_humas_id', $berkas->id)->exists(), 404);
        abort_unless(Storage::disk('local')->exists($berkas->lokasi_file), 404);
        $inline = ! $request->boolean('unduh') && in_array($berkas->tipe_file, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
        $headers = ['Content-Type' => $berkas->tipe_file, 'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff'];

        return $inline ? Storage::disk('local')->response($berkas->lokasi_file, $berkas->nama_file_asli, $headers)
            : Storage::disk('local')->download($berkas->lokasi_file, $berkas->nama_file_asli, $headers);
    }

    private function form(Request $request, AsetPromosiHumas $aset)
    {
        $bolehDokumen = $this->bolehDokumen($request);
        if ($bolehDokumen) {
            $aset->load('berkas');
        }
        $dokumen = $bolehDokumen ? DokumenHumas::where('status', 'aktif')->whereHas('riwayat')->latest('updated_at')->limit(50)->get(['id', 'judul']) : collect();
        $pilihanLama = old('dokumen_humas_id');
        if ($bolehDokumen && $pilihanLama) {
            $dokumen = $dokumen->merge(DokumenHumas::whereKey($pilihanLama)->get(['id', 'judul']))->unique('id');
        }

        return view('aset-promosi-humas.form', ['aset' => $aset, 'bolehDokumen' => $bolehDokumen, 'dokumen' => $dokumen, 'tokenPembuatan' => (string) Str::uuid()]);
    }

    private function aturan(bool $edit): array
    {
        return ['nama' => ['required', 'string', 'max:180'], 'kategori' => ['required', Rule::in(array_keys(AsetPromosiHumas::KATEGORI))],
            'tanggal_aset' => ['required', 'date_format:Y-m-d'], 'deskripsi' => ['nullable', 'string', 'max:5000'], 'kata_kunci' => ['nullable', 'string', 'max:200'],
            'kredit' => ['nullable', 'string', 'max:180'], 'ketentuan_penggunaan' => ['nullable', 'string', 'max:1000'],
            'metode' => ['required', Rule::in($edit ? ['tetap', 'unggah', 'dokumen', 'tautan'] : ['unggah', 'dokumen', 'tautan'])],
            'berkas' => ['required_if:metode,unggah', 'nullable', 'prohibited_unless:metode,unggah', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp', 'extensions:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp', 'max:20480'],
            'dokumen_humas_id' => ['required_if:metode,dokumen', 'nullable', 'prohibited_unless:metode,dokumen', 'integer', 'exists:dokumen_humas,id'],
            'tautan' => ['required_if:metode,tautan', 'nullable', 'prohibited_unless:metode,tautan', 'url:http,https', 'max:2000'],
            'catatan_revisi' => [Rule::requiredIf($edit && request('metode') !== 'tetap'), 'nullable', 'string', 'min:5', 'max:2000'],
            'status' => [$edit ? 'required' : 'nullable', Rule::in(array_keys(AsetPromosiHumas::STATUS))],
            'versi' => [$edit ? 'required' : 'nullable', 'integer', 'min:0'], 'token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid']];
    }

    private function metadata(array $data): array
    {
        return collect($data)->only(['token_pembuatan', 'nama', 'kategori', 'tanggal_aset', 'deskripsi', 'kata_kunci', 'kredit', 'ketentuan_penggunaan'])->all();
    }

    private function simpanBerkas(Request $request): ?array
    {
        if (! $request->hasFile('berkas')) {
            return null;
        }
        abort_unless($request->user()->memilikiIzin('dokumen_humas.kelola'), 403);
        $file = $request->file('berkas');
        $path = $file->storeAs('dokumen-humas', Str::uuid().'.'.$file->extension(), 'local');
        if (! $path) {
            throw ValidationException::withMessages(['berkas' => 'Berkas belum dapat disimpan. Silakan coba kembali.']);
        }

        return ['lokasi_file' => $path, 'nama_file_asli' => $file->getClientOriginalName(), 'tipe_file' => $file->getMimeType(), 'ukuran_file' => $file->getSize()];
    }

    private function hapusBerkas(?array $file): void
    {
        if ($file) {
            Storage::disk('local')->delete($file['lokasi_file']);
        }
    }

    private function bolehDokumen(Request $request): bool
    {
        return $request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
    }

    private function selesai(Request $request, AsetPromosiHumas $aset, string $pesan)
    {
        $request->session()->flash('berhasil', $pesan);
        $url = route('aset-promosi-humas.show', $aset);

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'pesan' => $pesan]) : redirect($url);
    }
}
