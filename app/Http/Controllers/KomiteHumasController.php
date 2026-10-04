<?php

namespace App\Http\Controllers;

use App\Models\DokumenHumas;
use App\Models\PengurusKomiteHumas;
use App\Models\PeriodeKomiteHumas;
use App\Models\RiwayatDokumenHumas;
use App\Services\Humas\KelolaKomiteHumasService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class KomiteHumasController extends Controller
{
    public function __construct(private readonly KelolaKomiteHumasService $kelola) {}

    public function index(Request $request)
    {
        $this->akses($request);
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in([...array_keys(PeriodeKomiteHumas::STATUS), 'semua'])],
            'masa' => ['nullable', Rule::in(array_keys(PeriodeKomiteHumas::MASA))]]);
        $query = PeriodeKomiteHumas::withCount(['pengurus as jumlah_pengurus' => fn ($q) => $q->where('aktif', true)])
            ->with(['pengurus' => fn ($q) => $q->where('aktif', true)->where('jabatan', 'ketua')->select(['id', 'periode_komite_humas_id', 'nama', 'jabatan', 'aktif'])]);
        $status = $filter['status'] ?? 'semua';
        $query->when($status !== 'semua', fn ($q) => $q->where('status', $status));
        $query->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('nama', '%'.$kata.'%')->orWhereLike('nomor_sk', '%'.$kata.'%')
            ->orWhereHas('pengurus', fn ($q) => $q->whereLike('nama', '%'.$kata.'%'))));
        match ($filter['masa'] ?? '') {
            'berakhir' => $query->whereDate('tanggal_selesai', '<', today()),
            'belum_mulai' => $query->whereDate('tanggal_mulai', '>', today()),
            'segera_berakhir' => $query->whereDate('tanggal_mulai', '<=', today())->whereBetween('tanggal_selesai', [today(), today()->addDays(30)]),
            'berjalan' => $query->whereDate('tanggal_mulai', '<=', today())->whereDate('tanggal_selesai', '>', today()->addDays(30)),
            default => null,
        };
        $berjalan = PeriodeKomiteHumas::where('status', 'aktif')->whereDate('tanggal_mulai', '<=', today())->whereDate('tanggal_selesai', '>=', today());

        return $this->halaman('index', ['daftar' => $query->orderByRaw('CASE WHEN status = ? AND tanggal_mulai <= ? AND tanggal_selesai >= ? THEN 0 ELSE 1 END', ['aktif', today()->format('Y-m-d'), today()->format('Y-m-d')])
            ->latest('tanggal_mulai')->orderByDesc('id')->paginate(20)->withQueryString(), 'filter' => $filter,
            'statistik' => ['berjalan' => (clone $berjalan)->count(), 'pengurus' => PengurusKomiteHumas::where('aktif', true)->whereIn('periode_komite_humas_id', (clone $berjalan)->select('id'))->count(),
                'draf' => PeriodeKomiteHumas::where('status', 'draf')->count(), 'berakhir' => PeriodeKomiteHumas::where('status', 'aktif')->whereDate('tanggal_selesai', '<', today())->count()]]);
    }

    public function create(Request $request)
    {
        $this->akses($request);

        return $this->form($request, new PeriodeKomiteHumas(['status' => 'draf', 'tanggal_mulai' => today(), 'tanggal_selesai' => today()->addYears(3)->subDay()]));
    }

    public function store(Request $request)
    {
        $this->akses($request);
        $data = $this->validasi($request, false);
        $file = $this->simpanBerkas($request);
        try {
            $periode = DB::transaction(function () use ($request, $data, $file) {
                $this->kelola->kunciPeriode();
                $periode = PeriodeKomiteHumas::firstOrCreate(['token_pembuatan' => $data['token_pembuatan']], Arr::only($data, PeriodeKomiteHumas::KOLOM));
                if ($periode->wasRecentlyCreated) {
                    $this->kelola->sk($request, $periode, $data, $file);
                    $this->kelola->pengurus($periode, $data['pengurus'] ?? []);
                    $this->kelola->pastikanAktif($periode);
                    $periode->forceFill(['versi' => 0, 'dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                    $this->kelola->catat($periode, $request, 'Kepengurusan ditambahkan');
                } else {
                    abort_unless($periode->dibuat_oleh_pengguna_id === $request->user()->id, 403);
                }

                return $periode;
            });
        } catch (Throwable $e) {
            $this->hapusBerkas($file);
            throw $e;
        }
        if ($file && $periode->berkas?->lokasi_file !== $file['lokasi_file']) {
            $this->hapusBerkas($file);
        }

        return $this->selesai($request, $periode, 'Kepengurusan komite berhasil disimpan.');
    }

    public function show(Request $request, PeriodeKomiteHumas $periode)
    {
        $this->akses($request);
        $manager = $request->user()->memilikiIzin('komite_humas.kelola');
        $dokumen = $this->bolehDokumen($request);
        $periode->load(['pengurus' => fn ($q) => $q->when(! $manager, fn ($q) => $q->select('id', 'periode_komite_humas_id', 'nama', 'jabatan', 'aktif'))]);
        if ($dokumen) {
            $periode->load('berkas.dokumen');
        }
        $urutan = array_keys(PengurusKomiteHumas::JABATAN);

        return $this->halaman('show', ['periode' => $periode, 'manager' => $manager, 'bolehDokumen' => $dokumen,
            'pengurus' => $periode->pengurus->sortBy(fn ($p) => [! $p->aktif, array_search($p->jabatan, $urutan, true), $p->id]),
            'riwayat' => $periode->riwayat()->with('pengguna:id,nama')->when($dokumen, fn ($q) => $q->with('berkas'))->paginate(15)]);
    }

    public function edit(Request $request, PeriodeKomiteHumas $periode)
    {
        $this->akses($request);

        return $this->form($request, $periode);
    }

    public function update(Request $request, PeriodeKomiteHumas $periode)
    {
        $this->akses($request);
        $data = $this->validasi($request, true);
        $file = $this->simpanBerkas($request);
        try {
            DB::transaction(function () use ($request, $periode, $data, $file) {
                $this->kelola->kunciPeriode();
                $periode = PeriodeKomiteHumas::lockForUpdate()->findOrFail($periode->id);
                if ($periode->versi !== (int) $data['versi']) {
                    throw ValidationException::withMessages(['versi' => 'Kepengurusan telah berubah. Muat ulang dan periksa data terbaru sebelum menyimpan.']);
                }
                $periode->fill(Arr::only($data, PeriodeKomiteHumas::KOLOM));
                $this->kelola->sk($request, $periode, $data, $file);
                $pengurusBerubah = $this->kelola->pengurus($periode, $data['pengurus'] ?? []);
                $this->kelola->pastikanAktif($periode);
                if ($periode->isDirty() || $pengurusBerubah) {
                    $periode->forceFill(['versi' => $periode->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                    $this->kelola->catat($periode, $request, 'Kepengurusan diperbarui', $data['catatan_perubahan']);
                }
            });
        } catch (Throwable $e) {
            $this->hapusBerkas($file);
            throw $e;
        }

        return $this->selesai($request, $periode, 'Perubahan kepengurusan berhasil disimpan.');
    }

    public function dokumen(Request $request)
    {
        $this->akses($request);
        abort_unless($this->bolehDokumen($request), 403);
        $data = $request->validate(['cari' => ['nullable', 'string', 'max:120']]);

        return response()->json(['dokumen' => $this->pilihanDokumen()->when($data['cari'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('judul', '%'.$kata.'%')->orWhereLike('nomor_dokumen', '%'.$kata.'%')))
            ->latest('updated_at')->limit(50)->get(['id', 'judul'])])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function berkas(Request $request, PeriodeKomiteHumas $periode, RiwayatDokumenHumas $berkas)
    {
        $this->akses($request);
        abort_unless($this->bolehDokumen($request), 403);
        abort_unless($periode->riwayat_dokumen_humas_id === $berkas->id || $periode->riwayat()->where('riwayat_dokumen_humas_id', $berkas->id)->exists(), 404);
        abort_unless(Storage::disk('local')->exists($berkas->lokasi_file), 404);
        $headers = ['Content-Type' => $berkas->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store, max-age=0'];

        return $request->boolean('unduh') ? Storage::disk('local')->download($berkas->lokasi_file, $berkas->nama_file_asli, $headers)
            : Storage::disk('local')->response($berkas->lokasi_file, $berkas->nama_file_asli, $headers);
    }

    private function validasi(Request $request, bool $edit): array
    {
        $data = Arr::only($request->input(), [...PeriodeKomiteHumas::KOLOM, 'pengurus', 'metode', 'dokumen_humas_id', 'token_pembuatan', 'versi', 'catatan_perubahan', '_token', '_method']);
        if (is_array($data['pengurus'] ?? null)) {
            $data['pengurus'] = array_values(array_filter(array_map(fn ($row) => is_array($row) ? Arr::only($row, ['id', 'nama', 'jabatan', 'nomor_telepon', 'aktif']) : $row, $data['pengurus']),
                fn ($row) => ! is_array($row) || filled($row['nama'] ?? null) || filled($row['nomor_telepon'] ?? null) || filled($row['id'] ?? null)));
        }
        $request->query->replace([]);
        $request->replace($data);

        return $request->validate(['nama' => ['required', 'string', 'max:180'], 'tanggal_mulai' => ['required', 'date_format:Y-m-d'], 'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'nomor_sk' => ['nullable', 'string', 'max:150'], 'tanggal_sk' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'status' => ['required', Rule::in(array_keys(PeriodeKomiteHumas::STATUS))], 'catatan' => ['nullable', 'string', 'max:2000'],
            'pengurus' => ['nullable', 'array', 'max:50'], 'pengurus.*' => ['required', 'array'], 'pengurus.*.id' => $edit ? ['nullable', 'integer', 'min:1', 'distinct'] : ['prohibited'],
            'pengurus.*.nama' => ['required', 'string', 'max:180'], 'pengurus.*.jabatan' => ['required', Rule::in(array_keys(PengurusKomiteHumas::JABATAN))],
            'pengurus.*.nomor_telepon' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]{6,30}$/'], 'pengurus.*.aktif' => ['required', 'boolean'],
            'metode' => ['required', Rule::in($edit ? ['tetap', 'unggah', 'dokumen', 'lepas'] : ['tanpa', 'unggah', 'dokumen'])],
            'berkas' => ['required_if:metode,unggah', 'nullable', 'prohibited_unless:metode,unggah', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:20480'],
            'dokumen_humas_id' => ['required_if:metode,dokumen', 'nullable', 'prohibited_unless:metode,dokumen', 'integer', 'exists:dokumen_humas,id'],
            'token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid'], 'versi' => [$edit ? 'required' : 'nullable', 'integer', 'min:0'], 'catatan_perubahan' => [$edit ? 'required' : 'nullable', 'string', 'min:5', 'max:2000']]);
    }

    private function form(Request $request, PeriodeKomiteHumas $periode)
    {
        $periode->load('pengurus');
        $dokumen = $this->bolehDokumen($request);
        if ($dokumen) {
            $periode->load('berkas');
        }
        $rows = $periode->exists ? $periode->pengurus->map(fn ($p) => $p->only(['id', 'nama', 'jabatan', 'nomor_telepon', 'aktif']))->all()
            : array_map(fn ($jabatan) => ['id' => null, 'nama' => '', 'jabatan' => $jabatan, 'nomor_telepon' => '', 'aktif' => true], ['ketua', 'sekretaris', 'bendahara']);
        $pilihan = $dokumen ? $this->pilihanDokumen()->latest('updated_at')->limit(50)->get(['id', 'judul']) : collect();
        if ($dokumen && is_scalar(old('dokumen_humas_id')) && old('dokumen_humas_id')) {
            $pilihan = $pilihan->merge($this->pilihanDokumen()->whereKey(old('dokumen_humas_id'))->get(['id', 'judul']))->unique('id');
        }

        $oldRows = old('pengurus', $rows);

        return $this->halaman('form', ['periode' => $periode, 'rows' => is_array($oldRows) ? array_filter($oldRows, 'is_array') : $rows, 'bolehDokumen' => $dokumen, 'dokumen' => $pilihan, 'tokenPembuatan' => (string) Str::uuid()]);
    }

    private function pilihanDokumen()
    {
        return DokumenHumas::where('kategori', 'komite')->where('status', 'aktif')->whereIn('tipe_file', PeriodeKomiteHumas::MIME_SK)->whereHas('riwayat');
    }

    private function akses(Request $request): void
    {
        abort_unless($request->user()->aktif && ! $request->user()->akunOrangTua() && ! $request->user()->akunSiswa(), 403);
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
            throw ValidationException::withMessages(['berkas' => 'SK belum dapat disimpan. Silakan coba kembali.']);
        }

        return ['lokasi_file' => $path, 'nama_file_asli' => $file->getClientOriginalName(), 'tipe_file' => $file->getMimeType(), 'ukuran_file' => $file->getSize()];
    }

    private function hapusBerkas(?array $file): void
    {
        if ($file) {
            Storage::disk('local')->delete($file['lokasi_file']);
        }
    }

    private function halaman(string $view, array $data)
    {
        return response()->view('komite-humas.'.$view, $data)->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function selesai(Request $request, PeriodeKomiteHumas $periode, string $pesan)
    {
        $request->session()->flash('berhasil', $pesan);
        $url = route('komite-humas.show', $periode);

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'pesan' => $pesan]) : redirect($url);
    }
}
