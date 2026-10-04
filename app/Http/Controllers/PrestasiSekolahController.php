<?php

namespace App\Http\Controllers;

use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\Pegawai;
use App\Models\PesertaPrestasiSekolah;
use App\Models\PrestasiSekolah;
use App\Models\RiwayatDokumenHumas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Humas\KelolaPrestasiSekolahService;
use App\Support\PenulisExcelPrestasiSekolah;
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

class PrestasiSekolahController extends Controller
{
    public function __construct(private readonly KelolaPrestasiSekolahService $kelola) {}

    public function index(Request $request)
    {
        $this->akses($request);
        $filter = $this->filter($request);
        $query = $this->query($filter);

        return $this->halaman('index', ['filter' => $filter, 'daftar' => (clone $query)->with('peserta')->orderByDesc('tanggal_prestasi')->orderByDesc('id')->paginate(20)->withQueryString(),
            'tahun' => PrestasiSekolah::select('tanggal_prestasi')->get()->map(fn ($p) => $p->tanggal_prestasi->year)->unique()->sortDesc()->values(),
            'tahunPelajaran' => TahunPelajaran::orderByDesc('tanggal_mulai')->get(['id', 'nama']), ...$this->statistik($query)]);
    }

    public function create(Request $request)
    {
        $this->akses($request);

        return $this->form($request, new PrestasiSekolah(['kategori' => 'akademik', 'tingkat' => 'kota_kabupaten', 'penerima' => 'siswa', 'bentuk' => 'individu', 'status' => 'draf', 'tanggal_prestasi' => today()]));
    }

    public function store(Request $request)
    {
        $this->akses($request);
        $data = $this->validasi($request);
        $file = $this->simpanBerkas($request);
        try {
            $prestasi = DB::transaction(function () use ($request, $data, $file) {
                Izin::where('kode', 'prestasi_sekolah.kelola')->lockForUpdate()->firstOrFail();
                $lama = PrestasiSekolah::where('token_pembuatan', $data['token_pembuatan'])->first();
                if ($lama) {
                    abort_unless($lama->dibuat_oleh_pengguna_id === $request->user()->id, 403);

                    return $lama;
                }
                $prestasi = new PrestasiSekolah(Arr::only($data, ['token_pembuatan', ...PrestasiSekolah::KOLOM]));
                $peserta = $this->kelola->peserta($data);
                $this->kelola->tahun($prestasi);
                $this->kelola->bukti($request, $prestasi, $data, $file);
                $this->kelola->verifikasi($request, $prestasi);
                $prestasi->forceFill(['identitas_hash' => $this->kelola->hash($prestasi, $peserta), 'versi' => 0, 'dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                $prestasi->peserta()->createMany($peserta);
                $this->kelola->catat($request, $prestasi, 'Prestasi ditambahkan');

                return $prestasi;
            });
        } catch (Throwable $e) {
            $this->gagalSimpan($file, $e);
        }
        if ($file && $prestasi->berkas()->value('lokasi_file') !== $file['lokasi_file']) {
            Storage::disk('local')->delete($file['lokasi_file']);
        }

        return $this->selesai($request, $prestasi, 'Prestasi berhasil disimpan. Draf tidak dihitung dalam statistik prestasi terverifikasi.');
    }

    public function show(Request $request, PrestasiSekolah $prestasi)
    {
        $this->akses($request);
        $bolehDokumen = $this->bolehDokumen($request);
        $prestasi->load(['peserta', 'tahunPelajaran']);
        if ($bolehDokumen) {
            $prestasi->load('berkas');
        }

        return $this->halaman('show', ['prestasi' => $prestasi, 'bolehDokumen' => $bolehDokumen,
            'riwayat' => $prestasi->riwayat()->with('pengguna:id,nama')->when($bolehDokumen, fn ($q) => $q->with('berkas'))->paginate(10)->withQueryString()]);
    }

    public function edit(Request $request, PrestasiSekolah $prestasi)
    {
        $this->akses($request);

        return $this->form($request, $prestasi);
    }

    public function update(Request $request, PrestasiSekolah $prestasi)
    {
        $this->akses($request);
        $data = $this->validasi($request, true);
        $file = $this->simpanBerkas($request);
        try {
            DB::transaction(function () use ($request, $prestasi, $data, $file) {
                Izin::where('kode', 'prestasi_sekolah.kelola')->lockForUpdate()->firstOrFail();
                $prestasi = PrestasiSekolah::lockForUpdate()->findOrFail($prestasi->id);
                if ($prestasi->versi !== (int) $data['versi']) {
                    throw ValidationException::withMessages(['versi' => 'Prestasi telah berubah. Muat ulang sebelum menyimpan koreksi.']);
                }
                $peserta = $this->kelola->peserta($data);
                $lama = $prestasi->peserta->map(fn ($p) => $p->only(['siswa_id', 'pegawai_id', 'nama', 'kelas']))->all();
                $prestasi->fill(Arr::only($data, PrestasiSekolah::KOLOM));
                $this->kelola->tahun($prestasi);
                $this->kelola->bukti($request, $prestasi, $data, $file);
                $prestasi->forceFill(['identitas_hash' => $this->kelola->hash($prestasi, $peserta)]);
                $berubah = $prestasi->isDirty() || $peserta !== $lama;
                $this->kelola->verifikasi($request, $prestasi, $berubah);
                if ($berubah) {
                    $prestasi->forceFill(['versi' => $prestasi->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                    if ($peserta !== $lama) {
                        $prestasi->peserta()->delete();
                        $prestasi->peserta()->createMany($peserta);
                    }
                    $this->kelola->catat($request, $prestasi, 'Prestasi diperbarui', $data['catatan_perubahan']);
                }
            });
        } catch (Throwable $e) {
            $this->gagalSimpan($file, $e);
        }

        return $this->selesai($request, $prestasi, 'Koreksi prestasi berhasil disimpan beserta riwayatnya.');
    }

    public function pilihan(Request $request)
    {
        $this->akses($request);
        $data = $request->validate(['jenis' => ['required', Rule::in(['siswa', 'pegawai'])], 'q' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $query = $data['jenis'] === 'siswa' ? Siswa::query() : Pegawai::query();
        $query->when($data['q'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('nama_lengkap', '%'.$kata.'%')->when($data['jenis'] === 'siswa', fn ($q) => $q->orWhereLike('nis', '%'.$kata.'%')->orWhereLike('nisn', '%'.$kata.'%'))));
        $pilihan = $query->orderBy('nama_lengkap')->orderBy('id')->paginate(15, ['id', 'nama_lengkap']);

        return response()->json(['data' => $pilihan->items(), 'next_page' => $pilihan->hasMorePages() ? $pilihan->currentPage() + 1 : null])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function dokumen(Request $request)
    {
        $this->akses($request);
        abort_unless($this->bolehDokumen($request), 403);
        $data = $request->validate(['cari' => ['nullable', 'string', 'max:120']]);

        return response()->json(['dokumen' => $this->pilihanDokumen()->when($data['cari'] ?? null, fn ($q, $kata) => $q->whereLike('judul', '%'.$kata.'%'))->latest('id')->limit(50)->get(['id', 'judul'])])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function berkas(Request $request, PrestasiSekolah $prestasi, RiwayatDokumenHumas $berkas)
    {
        $this->akses($request);
        abort_unless($this->bolehDokumen($request), 403);
        abort_unless($prestasi->riwayat_dokumen_humas_id === $berkas->id || $prestasi->riwayat()->where('riwayat_dokumen_humas_id', $berkas->id)->exists(), 404);
        abort_unless(Storage::disk('local')->exists($berkas->lokasi_file), 404);
        $headers = ['Content-Type' => $berkas->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store, max-age=0'];

        return $request->boolean('unduh') ? Storage::disk('local')->download($berkas->lokasi_file, $berkas->nama_file_asli, $headers) : Storage::disk('local')->response($berkas->lokasi_file, $berkas->nama_file_asli, $headers);
    }

    public function cetak(Request $request)
    {
        $this->akses($request);
        $filter = $this->filter($request);
        $query = $this->query($filter);
        $this->batas($query);

        return $this->halaman('cetak', ['filter' => $filter, 'daftar' => $query->with('peserta')->orderByDesc('tanggal_prestasi')->orderByDesc('id')->get(), ...$this->statistik($this->query($filter))]);
    }

    public function export(Request $request, PenulisExcelPrestasiSekolah $penulis)
    {
        $this->akses($request);
        abort_unless($request->user()->memilikiIzin(['prestasi_sekolah.lihat', 'prestasi_sekolah.kelola']), 403);
        $filter = $this->filter($request);
        $query = $this->query($filter);
        $this->batas($query);
        $path = $penulis->buat($query->with(['peserta', 'tahunPelajaran'])->orderByDesc('tanggal_prestasi')->orderByDesc('id')->get(), $filter);

        return response()->download($path, 'prestasi-sekolah-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => PenulisExcelPrestasiSekolah::MIME, 'Cache-Control' => 'private, no-store, max-age=0'])->deleteFileAfterSend(true);
    }

    private function validasi(Request $request, bool $edit = false): array
    {
        $data = Arr::only($request->input(), [...PrestasiSekolah::KOLOM, 'peserta', 'metode', 'dokumen_humas_id', 'konfirmasi_prestasi', 'token_pembuatan', 'versi', 'catatan_perubahan', '_token', '_method']);
        foreach ($data as $key => $value) {
            if (is_string($value) && in_array($key, ['nama_kegiatan', 'cabang', 'capaian', 'penyelenggara', 'tempat', 'pembina', 'nama_tim'], true)) {
                $data[$key] = Str::squish($value);
            }
        }
        $data['peserta'] ??= [];
        if (($data['bentuk'] ?? null) !== 'tim') {
            $data['nama_tim'] = null;
        }
        $request->query->replace([]);
        $request->replace($data);
        if (is_string($data['tautan'] ?? null) && TautanPublikHumas::berkredensial($data['tautan'])) {
            $request->merge(['tautan' => null]);
            throw ValidationException::withMessages(['tautan' => 'Gunakan tautan publik tanpa kata sandi atau token akses.']);
        }

        return $request->validate(['nama_kegiatan' => ['required', 'string', 'max:180'], 'cabang' => ['nullable', 'string', 'max:120'], 'capaian' => ['required', 'string', 'max:180'], 'tanggal_prestasi' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'], 'kategori' => ['required', Rule::in(array_keys(PrestasiSekolah::KATEGORI))], 'tingkat' => ['required', Rule::in(array_keys(PrestasiSekolah::TINGKAT))], 'perolehan' => ['required', Rule::in(array_keys(PrestasiSekolah::PEROLEHAN))],
            'penerima' => ['required', Rule::in(array_keys(PrestasiSekolah::PENERIMA))], 'bentuk' => ['required', Rule::in(array_keys(PrestasiSekolah::BENTUK))], 'nama_tim' => ['nullable', 'required_if:bentuk,tim', 'string', 'max:180'],
            'peserta' => ['array', 'max:100'], 'peserta.*' => ['array:siswa_id,pegawai_id,nama,kelas'], 'peserta.*.siswa_id' => ['nullable', 'integer', 'exists:siswa,id'], 'peserta.*.pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'], 'peserta.*.nama' => ['nullable', 'string', 'max:180'], 'peserta.*.kelas' => ['nullable', 'string', 'max:80'],
            'penyelenggara' => ['nullable', 'string', 'max:180'], 'tempat' => ['nullable', 'string', 'max:180'], 'pembina' => ['nullable', 'string', 'max:180'], 'catatan' => ['nullable', 'string', 'max:3000'], 'tautan' => ['nullable', 'bail', 'string', 'url:http,https', 'max:2000'], 'status' => ['required', Rule::in(array_keys(PrestasiSekolah::STATUS))],
            'konfirmasi_prestasi' => ['nullable', 'required_if:status,terverifikasi', 'accepted_if:status,terverifikasi'], 'metode' => ['required', Rule::in($edit ? ['tetap', 'unggah', 'dokumen', 'hapus'] : ['tanpa', 'unggah', 'dokumen'])],
            'berkas' => ['nullable', 'required_if:metode,unggah', 'prohibited_unless:metode,unggah', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:10240'], 'dokumen_humas_id' => ['nullable', 'required_if:metode,dokumen', 'prohibited_unless:metode,dokumen', 'integer', 'exists:dokumen_humas,id'],
            'token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid'], 'versi' => [$edit ? 'required' : 'nullable', 'integer', 'min:0'], 'catatan_perubahan' => [$edit ? 'required' : 'nullable', 'string', 'min:5', 'max:2000']]);
    }

    private function filter(Request $request): array
    {
        return $request->validate(['tab' => ['nullable', Rule::in(['data', 'statistik'])], 'kata_kunci' => ['nullable', 'string', 'max:120'], 'tahun' => ['nullable', 'integer', 'min:1900', 'max:'.today()->year], 'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kategori' => ['nullable', Rule::in(array_keys(PrestasiSekolah::KATEGORI))], 'tingkat' => ['nullable', Rule::in(array_keys(PrestasiSekolah::TINGKAT))], 'perolehan' => ['nullable', Rule::in(array_keys(PrestasiSekolah::PEROLEHAN))], 'penerima' => ['nullable', Rule::in(array_keys(PrestasiSekolah::PENERIMA))], 'status' => ['nullable', Rule::in(['aktif', 'semua', ...array_keys(PrestasiSekolah::STATUS)])]]);
    }

    private function query(array $filter)
    {
        $query = PrestasiSekolah::query();
        $status = $filter['status'] ?? 'aktif';
        if ($status === 'aktif') {
            $query->whereIn('status', ['draf', 'terverifikasi']);
        } elseif ($status !== 'semua') {
            $query->where('status', $status);
        }
        foreach (['tahun_pelajaran_id', 'kategori', 'tingkat', 'perolehan', 'penerima'] as $key) {
            $query->when($filter[$key] ?? null, fn ($q, $v) => $q->where($key, $v));
        }

        return $query->when($filter['tahun'] ?? null, fn ($q, $v) => $q->whereBetween('tanggal_prestasi', [$v.'-01-01', $v.'-12-31']))
            ->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('nama_kegiatan', '%'.$kata.'%')->orWhereLike('capaian', '%'.$kata.'%')->orWhereLike('cabang', '%'.$kata.'%')->orWhereLike('nama_tim', '%'.$kata.'%')->orWhereLike('penyelenggara', '%'.$kata.'%')->orWhereHas('peserta', fn ($q) => $q->whereLike('nama', '%'.$kata.'%'))));
    }

    private function statistik($query): array
    {
        $verified = (clone $query)->where('status', 'terverifikasi');
        $ids = (clone $verified)->select('id');

        return ['statistik' => ['total' => (clone $query)->count(), 'terverifikasi' => (clone $verified)->count(), 'siswa' => PesertaPrestasiSekolah::whereIn('prestasi_sekolah_id', clone $ids)->whereNotNull('siswa_id')->distinct()->count('siswa_id'), 'sekolah' => (clone $verified)->where('penerima', 'sekolah')->count()],
            'perTingkat' => (clone $verified)->selectRaw('tingkat, COUNT(*) AS jumlah')->groupBy('tingkat')->pluck('jumlah', 'tingkat'),
            'perKategori' => (clone $verified)->selectRaw('kategori, COUNT(*) AS jumlah')->groupBy('kategori')->pluck('jumlah', 'kategori'),
            'perPenerima' => (clone $verified)->selectRaw('penerima, COUNT(*) AS jumlah')->groupBy('penerima')->pluck('jumlah', 'penerima'),
            'siswaTerbanyak' => PesertaPrestasiSekolah::whereIn('prestasi_sekolah_id', clone $ids)->whereNotNull('siswa_id')->selectRaw('siswa_id, MIN(nama) AS nama, COUNT(*) AS jumlah')->groupBy('siswa_id')->orderByDesc('jumlah')->orderBy('nama')->limit(10)->get()];
    }

    private function form(Request $request, PrestasiSekolah $prestasi)
    {
        $prestasi->load('peserta');
        $bolehDokumen = $this->bolehDokumen($request);
        if ($bolehDokumen) {
            $prestasi->load('berkas');
        }
        $dokumen = $bolehDokumen ? $this->pilihanDokumen()->latest('id')->limit(50)->get(['id', 'judul']) : collect();
        if ($bolehDokumen && is_scalar(old('dokumen_humas_id'))) {
            $dokumen = $dokumen->merge($this->pilihanDokumen()->whereKey(old('dokumen_humas_id'))->get(['id', 'judul']))->unique('id');
        }

        return $this->halaman('form', ['prestasi' => $prestasi, 'bolehDokumen' => $bolehDokumen, 'dokumen' => $dokumen, 'tahunPelajaran' => TahunPelajaran::orderByDesc('tanggal_mulai')->get(), 'tokenPembuatan' => (string) Str::uuid()]);
    }

    private function pilihanDokumen()
    {
        return DokumenHumas::where('status', 'aktif')->whereIn('tipe_file', PrestasiSekolah::MIME_BUKTI)->whereHas('riwayat');
    }

    private function bolehDokumen(Request $request): bool
    {
        return $request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
    }

    private function akses(Request $request): void
    {
        abort_unless($request->user()->aktif && ! $request->user()->akunSiswa() && ! $request->user()->akunOrangTua(), 403);
    }

    private function halaman(string $view, array $data)
    {
        return response()->view('prestasi-sekolah.'.$view, $data)->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function simpanBerkas(Request $request): ?array
    {
        if (! $request->hasFile('berkas')) {
            return null;
        }
        abort_unless($request->user()->memilikiIzin('dokumen_humas.kelola'), 403);
        $file = $request->file('berkas');
        $path = $file->storeAs('dokumen-humas', Str::uuid().'.'.$file->guessExtension(), 'local');
        if (! $path) {
            throw ValidationException::withMessages(['berkas' => 'Berkas belum dapat disimpan. Periksa ruang penyimpanan.']);
        }

        return ['lokasi_file' => $path, 'nama_file_asli' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 240, ''), 'tipe_file' => $file->getMimeType(), 'ukuran_file' => $file->getSize()];
    }

    private function gagalSimpan(?array $file, Throwable $e): never
    {
        if ($file) {
            Storage::disk('local')->delete($file['lokasi_file']);
        }
        if ($e instanceof UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nama_kegiatan' => 'Prestasi dengan kegiatan, tanggal, capaian, dan penerima yang sama sudah tercatat, termasuk pada arsip.']);
        }
        throw $e;
    }

    private function batas($query): void
    {
        if ((clone $query)->count() > 5000) {
            throw ValidationException::withMessages(['tahun' => 'Maksimal 5.000 prestasi per cetak atau ekspor. Persempit filter.']);
        }
    }

    private function selesai(Request $request, PrestasiSekolah $prestasi, string $pesan)
    {
        $request->session()->flash('berhasil', $pesan);
        $url = route('prestasi-sekolah.show', $prestasi);

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'pesan' => $pesan]) : redirect($url);
    }
}
