<?php

namespace App\Http\Controllers;

use App\Models\DokumenHumas;
use App\Models\KlipingBeritaHumas;
use App\Models\RiwayatDokumenHumas;
use App\Services\Humas\KelolaKlipingBeritaHumasService;
use App\Support\TautanPublikHumas;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class KlipingBeritaHumasController extends Controller
{
    public function __construct(private readonly KelolaKlipingBeritaHumasService $kelola) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'jenis' => ['nullable', Rule::in(array_keys(KlipingBeritaHumas::JENIS))],
            'topik' => ['nullable', Rule::in(array_keys(KlipingBeritaHumas::TOPIK))], 'status' => ['nullable', Rule::in(['aktif', 'arsip', 'semua'])],
            'nama_media' => ['nullable', 'string', 'max:180'], 'mulai' => ['nullable', 'date_format:Y-m-d'], 'sampai' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('mulai'), 'after_or_equal:mulai')]]);
        $bolehDokumen = $this->bolehDokumen($request);
        $status = $filter['status'] ?? 'aktif';
        $query = KlipingBeritaHumas::when($bolehDokumen, fn ($q) => $q->with('berkas'))->when($status !== 'semua', fn ($q) => $q->where('status', $status))
            ->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('judul', '%'.$kata.'%')->orWhereLike('nama_media', '%'.$kata.'%')->orWhereLike('ringkasan', '%'.$kata.'%')->orWhereLike('penulis', '%'.$kata.'%')))
            ->when($filter['mulai'] ?? null, fn ($q, $date) => $q->whereDate('tanggal_terbit', '>=', $date))->when($filter['sampai'] ?? null, fn ($q, $date) => $q->whereDate('tanggal_terbit', '<=', $date));
        foreach (['jenis', 'topik', 'nama_media'] as $kolom) {
            if (! empty($filter[$kolom])) {
                $query->where($kolom, $filter[$kolom]);
            }
        }
        $aktif = KlipingBeritaHumas::where('status', 'aktif');

        return view('kliping-berita-humas.index', ['daftar' => $query->latest('tanggal_terbit')->orderByDesc('id')->paginate(20)->withQueryString(), 'filter' => $filter, 'bolehDokumen' => $bolehDokumen,
            'daftarMedia' => KlipingBeritaHumas::distinct()->orderBy('nama_media')->pluck('nama_media'),
            'statistik' => ['aktif' => (clone $aktif)->count(), 'bulan_ini' => (clone $aktif)->whereBetween('tanggal_terbit', [today()->startOfMonth(), today()->endOfMonth()])->count(),
                'media' => (clone $aktif)->distinct()->count('nama_media'), 'arsip' => KlipingBeritaHumas::where('status', 'arsip')->count()]]);
    }

    public function create(Request $request)
    {
        return $this->form($request, new KlipingBeritaHumas(['jenis' => 'online', 'topik' => 'kegiatan', 'tanggal_terbit' => today(), 'status' => 'aktif']));
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request, false);
        $file = $this->simpanBerkas($request);
        try {
            $kliping = DB::transaction(function () use ($request, $data, $file) {
                $kliping = KlipingBeritaHumas::firstOrCreate(['token_pembuatan' => $data['token_pembuatan']], collect($data)->only(KlipingBeritaHumas::KOLOM)->all() + ['tautan_hash' => TautanPublikHumas::hash($data['tautan'] ?? null)]);
                if ($kliping->wasRecentlyCreated) {
                    $this->kelola->bukti($request, $kliping, $data, $file);
                    $this->kelola->pastikanSumber($kliping);
                    $kliping->forceFill(['dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                    $kliping->refresh();
                    $this->kelola->catat($request, $kliping, 'Kliping berita ditambahkan');
                } else {
                    abort_unless($kliping->dibuat_oleh_pengguna_id === $request->user()->id, 403);
                }

                return $kliping;
            });
        } catch (Throwable $e) {
            $this->hapusBerkas($file);
            if ($e instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['tautan' => 'Tautan berita ini sudah diarsipkan. Periksa kliping yang sudah tersedia.']);
            }
            throw $e;
        }
        if ($file && $kliping->berkas?->lokasi_file !== $file['lokasi_file']) {
            $this->hapusBerkas($file);
        }

        return $this->selesai($request, $kliping, 'Kliping berita berhasil disimpan.');
    }

    public function show(Request $request, KlipingBeritaHumas $kliping)
    {
        $bolehDokumen = $this->bolehDokumen($request);
        if ($bolehDokumen) {
            $kliping->load('berkas.dokumen');
        }

        return view('kliping-berita-humas.show', ['kliping' => $kliping, 'bolehDokumen' => $bolehDokumen,
            'riwayat' => $kliping->riwayat()->with('pengguna:id,nama')->when($bolehDokumen, fn ($q) => $q->with('berkas'))->paginate(15)]);
    }

    public function edit(Request $request, KlipingBeritaHumas $kliping)
    {
        return $this->form($request, $kliping);
    }

    public function update(Request $request, KlipingBeritaHumas $kliping)
    {
        $data = $this->validasi($request, true);
        $file = $this->simpanBerkas($request);
        try {
            DB::transaction(function () use ($request, $kliping, $data, $file) {
                $kliping = KlipingBeritaHumas::lockForUpdate()->findOrFail($kliping->id);
                if ($kliping->versi !== (int) $data['versi']) {
                    throw ValidationException::withMessages(['versi' => 'Kliping telah berubah. Muat ulang dan periksa data terbaru sebelum menyimpan.']);
                }
                $kliping->fill(collect($data)->only(KlipingBeritaHumas::KOLOM)->all());
                $kliping->forceFill(['tautan_hash' => TautanPublikHumas::hash($kliping->tautan)]);
                $this->kelola->bukti($request, $kliping, $data, $file);
                $this->kelola->pastikanSumber($kliping);
                if ($kliping->isDirty()) {
                    $kliping->forceFill(['versi' => $kliping->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                    $this->kelola->catat($request, $kliping, 'Kliping berita diperbarui', $data['catatan_perubahan']);
                }
            });
        } catch (Throwable $e) {
            $this->hapusBerkas($file);
            if ($e instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['tautan' => 'Tautan berita ini sudah dipakai kliping lain.']);
            }
            throw $e;
        }

        return $this->selesai($request, $kliping, 'Perubahan kliping berhasil disimpan.');
    }

    public function dokumen(Request $request)
    {
        abort_unless($this->bolehDokumen($request), 403);
        $data = $request->validate(['cari' => ['nullable', 'string', 'max:120']]);

        return response()->json(['dokumen' => $this->pilihanDokumen()->when($data['cari'] ?? null, fn ($q, $kata) => $q->whereLike('judul', '%'.$kata.'%'))->latest('updated_at')->limit(50)->get(['id', 'judul'])]);
    }

    public function berkas(Request $request, KlipingBeritaHumas $kliping, RiwayatDokumenHumas $berkas)
    {
        abort_unless($this->bolehDokumen($request), 403);
        abort_unless($kliping->riwayat_dokumen_humas_id === $berkas->id || $kliping->riwayat()->where('riwayat_dokumen_humas_id', $berkas->id)->exists(), 404);
        abort_unless(Storage::disk('local')->exists($berkas->lokasi_file), 404);
        $headers = ['Content-Type' => $berkas->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store, max-age=0'];
        $inline = ! $request->boolean('unduh') && in_array($berkas->tipe_file, KlipingBeritaHumas::MIME_BUKTI);

        return $inline ? Storage::disk('local')->response($berkas->lokasi_file, $berkas->nama_file_asli, $headers)
            : Storage::disk('local')->download($berkas->lokasi_file, $berkas->nama_file_asli, $headers);
    }

    private function validasi(Request $request, bool $edit): array
    {
        $rahasia = array_intersect(array_map('strtolower', array_keys($request->input())), TautanPublikHumas::KREDENSIAL);
        $data = Arr::only($request->input(), [...KlipingBeritaHumas::KOLOM, 'metode', 'dokumen_humas_id', 'token_pembuatan', 'versi', 'catatan_perubahan', '_token', '_method']);
        $request->query->replace([]);
        $request->replace($data);
        $tautanRahasia = is_string($data['tautan'] ?? null) && TautanPublikHumas::berkredensial($data['tautan']);
        if ($tautanRahasia) {
            $request->merge(['tautan' => null]);
        }
        if ($rahasia || $tautanRahasia) {
            throw ValidationException::withMessages(['tautan' => 'Gunakan tautan berita publik, tanpa kata sandi atau token akses.']);
        }

        return $request->validate(['judul' => ['required', 'string', 'max:180'], 'nama_media' => ['required', 'string', 'max:180'],
            'jenis' => ['required', Rule::in(array_keys(KlipingBeritaHumas::JENIS))], 'topik' => ['required', Rule::in(array_keys(KlipingBeritaHumas::TOPIK))],
            'tanggal_terbit' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'penulis' => ['nullable', 'string', 'max:180'], 'rujukan' => ['nullable', 'string', 'max:250'],
            'tautan' => ['nullable', 'bail', 'string', 'url:http,https', 'max:2000'], 'ringkasan' => ['nullable', 'string', 'max:3000'], 'catatan' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(KlipingBeritaHumas::STATUS))], 'metode' => ['required', Rule::in($edit ? ['tetap', 'unggah', 'dokumen', 'hapus'] : ['tanpa', 'unggah', 'dokumen'])],
            'berkas' => ['required_if:metode,unggah', 'nullable', 'prohibited_unless:metode,unggah', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:20480'],
            'dokumen_humas_id' => ['required_if:metode,dokumen', 'nullable', 'prohibited_unless:metode,dokumen', 'integer', 'exists:dokumen_humas,id'],
            'token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid'], 'versi' => [$edit ? 'required' : 'nullable', 'integer', 'min:0'],
            'catatan_perubahan' => [$edit ? 'required' : 'nullable', 'string', 'min:5', 'max:2000']]);
    }

    private function form(Request $request, KlipingBeritaHumas $kliping)
    {
        $bolehDokumen = $this->bolehDokumen($request);
        if ($bolehDokumen) {
            $kliping->load('berkas');
        }
        $dokumen = $bolehDokumen ? $this->pilihanDokumen()->latest('updated_at')->limit(50)->get(['id', 'judul']) : collect();
        if ($bolehDokumen && old('dokumen_humas_id')) {
            $dokumen = $dokumen->merge(DokumenHumas::whereKey(old('dokumen_humas_id'))->get(['id', 'judul']))->unique('id');
        }

        return view('kliping-berita-humas.form', ['kliping' => $kliping, 'bolehDokumen' => $bolehDokumen, 'dokumen' => $dokumen, 'tokenPembuatan' => (string) Str::uuid()]);
    }

    private function pilihanDokumen()
    {
        return DokumenHumas::where('status', 'aktif')->whereIn('tipe_file', KlipingBeritaHumas::MIME_BUKTI)->whereHas('riwayat');
    }

    private function bolehDokumen(Request $request): bool
    {
        return $request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
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
            throw ValidationException::withMessages(['berkas' => 'Bukti kliping belum dapat disimpan. Silakan coba kembali.']);
        }

        return ['lokasi_file' => $path, 'nama_file_asli' => $file->getClientOriginalName(), 'tipe_file' => $file->getMimeType(), 'ukuran_file' => $file->getSize()];
    }

    private function hapusBerkas(?array $file): void
    {
        if ($file) {
            Storage::disk('local')->delete($file['lokasi_file']);
        }
    }

    private function selesai(Request $request, KlipingBeritaHumas $kliping, string $pesan)
    {
        $request->session()->flash('berhasil', $pesan);
        $url = route('kliping-berita-humas.show', $kliping);

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'pesan' => $pesan]) : redirect($url);
    }
}
