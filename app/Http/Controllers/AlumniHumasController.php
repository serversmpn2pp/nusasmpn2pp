<?php

namespace App\Http\Controllers;

use App\Models\AlumniHumas;
use App\Models\Izin;
use App\Models\Siswa;
use App\Services\Humas\KelolaAlumniHumasService;
use App\Support\PenulisExcelAlumniHumas;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AlumniHumasController extends Controller
{
    public function __construct(private readonly KelolaAlumniHumasService $kelola) {}

    public function index(Request $request)
    {
        $this->akses($request);
        $filter = $this->filter($request);
        $query = $this->query($filter);

        return $this->halaman('index', ['filter' => $filter, 'tahun' => AlumniHumas::distinct()->orderByDesc('tahun_lulus')->pluck('tahun_lulus'),
            'daftar' => (clone $query)->select(['id', ...AlumniHumas::PUBLIK])->orderBy('nama_lengkap')->orderBy('id')->paginate(25)->withQueryString(),
            ...$this->statistik($query)]);
    }

    public function create(Request $request)
    {
        $this->akses($request);

        return $this->form($request, new AlumniHumas(['tahun_lulus' => today()->year, 'status' => 'aktif', 'status_penelusuran' => 'belum_terdata']));
    }

    public function store(Request $request)
    {
        $this->akses($request);
        $data = $this->validasi($request);
        try {
            $alumni = DB::transaction(function () use ($request, $data) {
                Izin::where('kode', 'alumni_humas.kelola')->lockForUpdate()->firstOrFail();
                $alumni = AlumniHumas::where('token_pembuatan', $data['token_pembuatan'])->first();
                if ($alumni) {
                    abort_unless($alumni->dibuat_oleh_pengguna_id === $request->user()->id, 403);

                    return $alumni;
                }
                $alumni = new AlumniHumas(Arr::only($data, ['token_pembuatan', ...AlumniHumas::PUBLIK, ...AlumniHumas::PRIVAT]));
                $this->kelola->validasi($alumni);
                $alumni->forceFill(['kelulusan_dicatat_pada' => now(), 'versi' => 0, 'dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                $this->kelola->catat($alumni, $request, 'Alumni ditambahkan');

                return $alumni;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['nisn' => 'Siswa, NISN, atau NIS pada angkatan ini sudah terdaftar. Periksa data alumni aktif maupun arsip.']);
        }

        return redirect()->route('alumni-humas.show', $alumni)->with('berhasil', 'Alumni berhasil ditambahkan. Status dan akun siswa tidak diubah.');
    }

    public function show(Request $request, AlumniHumas $alumni)
    {
        $this->akses($request);
        $bolehPrivat = $request->user()->memilikiIzin('alumni_humas.kelola');
        $kolom = ['id', 'alumni_humas_id', 'aksi', 'versi', 'snapshot', 'pengguna_id', 'created_at'];
        if ($bolehPrivat) {
            $kolom = [...$kolom, 'snapshot_privat', 'catatan_perubahan'];
        }

        return $this->halaman('show', ['alumni' => $alumni, 'bolehPrivat' => $bolehPrivat,
            'riwayat' => $alumni->riwayat()->select($kolom)->with('pengguna:id,nama')->paginate(10)->withQueryString()]);
    }

    public function edit(Request $request, AlumniHumas $alumni)
    {
        $this->akses($request);

        return $this->form($request, $alumni);
    }

    public function update(Request $request, AlumniHumas $alumni)
    {
        $this->akses($request);
        $data = $this->validasi($request, true);
        try {
            DB::transaction(function () use ($request, $alumni, $data) {
                Izin::where('kode', 'alumni_humas.kelola')->lockForUpdate()->firstOrFail();
                $alumni = AlumniHumas::lockForUpdate()->findOrFail($alumni->id);
                if ($alumni->versi !== $data['versi']) {
                    throw ValidationException::withMessages(['versi' => 'Data alumni telah berubah. Muat ulang sebelum menyimpan.']);
                }
                $alumni->fill(Arr::only($data, [...AlumniHumas::PUBLIK, ...AlumniHumas::PRIVAT]));
                $this->kelola->validasi($alumni);
                if ($alumni->isDirty()) {
                    $alumni->forceFill(['versi' => $alumni->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                    $this->kelola->catat($alumni, $request, 'Alumni diperbarui', $data['catatan_perubahan']);
                }
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['nisn' => 'Identitas ini sudah terdaftar pada alumni lain, termasuk data yang diarsipkan.']);
        }

        return redirect()->route('alumni-humas.show', $alumni)->with('berhasil', 'Perubahan alumni berhasil disimpan.');
    }

    public function siswa(Request $request)
    {
        $this->akses($request);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'alumni_id' => ['nullable', 'integer', 'exists:alumni_humas,id'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $pilihan = Siswa::whereNotIn('id', AlumniHumas::whereNotNull('siswa_id')->when($data['alumni_id'] ?? null, fn ($q, $id) => $q->where('id', '<>', $id))->select('siswa_id'))
            ->when($data['q'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('nama_lengkap', '%'.$kata.'%')->orWhereLike('nis', '%'.$kata.'%')->orWhereLike('nisn', '%'.$kata.'%')))
            ->with(['anggotaKelas' => fn ($q) => $q->whereHas('kelas', fn ($q) => $q->where('tingkat', 9))
                ->with(['kelas:id,nama', 'tahunPelajaran:id,nama,tanggal_selesai'])->orderByDesc('id')->select(['id', 'siswa_id', 'kelas_id', 'tahun_pelajaran_id'])])
            ->orderBy('nama_lengkap')->orderBy('id')->paginate(15, ['id', 'nama_lengkap', 'nis', 'nisn', 'jenis_kelamin']);

        return response()->json(['data' => $pilihan->getCollection()->map(fn ($s) => $this->pilihanSiswa($s))->all(), 'next_page' => $pilihan->hasMorePages() ? $pilihan->currentPage() + 1 : null])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function cetak(Request $request)
    {
        $this->akses($request);
        $filter = $this->filter($request);
        $query = $this->query($filter);
        $this->batasEkspor($query);

        return $this->halaman('cetak', ['filter' => $filter, 'daftar' => $query->select(['id', ...AlumniHumas::PUBLIK])->orderByDesc('tahun_lulus')->orderBy('nama_lengkap')->get(), ...$this->statistik($this->query($filter))]);
    }

    public function export(Request $request, PenulisExcelAlumniHumas $penulis)
    {
        $this->akses($request);
        abort_unless($request->user()->memilikiIzin(['alumni_humas.lihat', 'alumni_humas.kelola']), 403);
        $request->validate(['sertakan_kontak' => ['nullable', 'boolean']]);
        $privat = $request->boolean('sertakan_kontak');
        abort_if($privat && ! $request->user()->memilikiIzin('alumni_humas.kelola'), 403);
        $filter = $this->filter($request);
        $query = $this->query($filter);
        $this->batasEkspor($query);
        $kolom = ['id', ...AlumniHumas::PUBLIK, ...($privat ? ['nomor_wa', 'email'] : [])];
        $path = $penulis->buat($query->select($kolom)->orderByDesc('tahun_lulus')->orderBy('nama_lengkap')->get(), $filter, $privat);

        return response()->download($path, 'alumni-nusa-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => PenulisExcelAlumniHumas::MIME, 'Cache-Control' => 'private, no-store, max-age=0'])
            ->deleteFileAfterSend(true);
    }

    private function filter(Request $request): array
    {
        return $request->validate(['tab' => ['nullable', Rule::in(['data', 'statistik'])], 'kata_kunci' => ['nullable', 'string', 'max:120'],
            'tahun_lulus' => ['nullable', 'integer', 'min:1900', 'max:'.today()->year], 'status' => ['nullable', Rule::in(['semua', ...array_keys(AlumniHumas::STATUS)])],
            'status_penelusuran' => ['nullable', Rule::in(array_keys(AlumniHumas::PENELUSURAN))], 'jenis_sekolah' => ['nullable', Rule::in(array_keys(AlumniHumas::SEKOLAH))], 'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])]]);
    }

    private function query(array $filter)
    {
        $status = $filter['status'] ?? 'aktif';

        return AlumniHumas::query()->when($status !== 'semua', fn ($q) => $q->where('status', $status))
            ->when($filter['tahun_lulus'] ?? null, fn ($q, $tahun) => $q->where('tahun_lulus', $tahun))
            ->when($filter['status_penelusuran'] ?? null, fn ($q, $status) => $q->where('status_penelusuran', $status))
            ->when($filter['jenis_sekolah'] ?? null, fn ($q, $jenis) => $q->where('jenis_sekolah', $jenis))
            ->when($filter['jenis_kelamin'] ?? null, fn ($q, $jenis) => $q->where('jenis_kelamin', $jenis))
            ->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('nama_lengkap', '%'.$kata.'%')->orWhereLike('nis', '%'.$kata.'%')
                ->orWhereLike('nisn', '%'.$kata.'%')->orWhereLike('nama_sekolah', '%'.$kata.'%')->orWhereLike('kota_sekolah', '%'.$kata.'%')));
    }

    private function statistik($query): array
    {
        $jumlah = (clone $query)->selectRaw('status_penelusuran, COUNT(*) AS jumlah')->groupBy('status_penelusuran')->pluck('jumlah', 'status_penelusuran');
        $total = (int) $jumlah->sum();
        $terdata = (int) ($jumlah['melanjutkan'] ?? 0) + (int) ($jumlah['tidak_melanjutkan'] ?? 0);

        return ['statistik' => ['total' => $total, 'melanjutkan' => (int) ($jumlah['melanjutkan'] ?? 0), 'tidak_melanjutkan' => (int) ($jumlah['tidak_melanjutkan'] ?? 0),
            'belum_terdata' => (int) ($jumlah['belum_terdata'] ?? 0), 'persen_terdata' => $total ? round($terdata / $total * 100, 1) : 0],
            'perJenis' => (clone $query)->where('status_penelusuran', 'melanjutkan')->selectRaw('jenis_sekolah, COUNT(*) AS jumlah')->groupBy('jenis_sekolah')->pluck('jumlah', 'jenis_sekolah'),
            'perAngkatan' => (clone $query)->selectRaw("tahun_lulus, COUNT(*) AS jumlah, SUM(CASE WHEN status_penelusuran = 'melanjutkan' THEN 1 ELSE 0 END) AS melanjutkan, SUM(CASE WHEN status_penelusuran = 'tidak_melanjutkan' THEN 1 ELSE 0 END) AS tidak_melanjutkan, SUM(CASE WHEN status_penelusuran = 'belum_terdata' THEN 1 ELSE 0 END) AS belum_terdata")
                ->groupBy('tahun_lulus')->orderByDesc('tahun_lulus')->get(),
            'sekolahTerbanyak' => (clone $query)->where('status_penelusuran', 'melanjutkan')->selectRaw('jenis_sekolah, MIN(nama_sekolah) AS nama_sekolah, MIN(kota_sekolah) AS kota_sekolah, COUNT(*) AS jumlah')
                ->groupBy('jenis_sekolah')->groupByRaw("LOWER(nama_sekolah), LOWER(COALESCE(kota_sekolah, ''))")->orderByDesc('jumlah')->orderBy('nama_sekolah')->limit(10)->get()];
    }

    private function validasi(Request $request, bool $edit = false): array
    {
        $request->validate(['siswa_id' => ['nullable', 'integer', 'exists:siswa,id'], 'anggota_kelas_id' => ['nullable', 'integer', 'exists:anggota_kelas,id']]);
        $this->kelola->normalisasi($request);
        $this->kelola->sumber($request);
        $data = $request->validate(['siswa_id' => ['nullable', 'integer', 'exists:siswa,id'], 'anggota_kelas_id' => ['nullable', 'integer', 'exists:anggota_kelas,id'],
            'nama_lengkap' => ['required', 'string', 'max:180'], 'nis' => ['nullable', 'string', 'max:30'], 'nisn' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'], 'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'tahun_masuk' => ['nullable', 'integer', 'min:1900', 'max:'.today()->year], 'tahun_lulus' => ['required', 'integer', 'min:1900', 'max:'.today()->year],
            'tanggal_lulus' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'kelas_terakhir' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(array_keys(AlumniHumas::STATUS))], 'status_penelusuran' => ['required', Rule::in(array_keys(AlumniHumas::PENELUSURAN))],
            'jenis_sekolah' => ['nullable', 'required_if:status_penelusuran,melanjutkan', Rule::in(array_keys(AlumniHumas::SEKOLAH))],
            'nama_sekolah' => ['nullable', 'required_if:status_penelusuran,melanjutkan', 'string', 'max:180'], 'kota_sekolah' => ['nullable', 'string', 'max:120'], 'jurusan' => ['nullable', 'string', 'max:180'],
            'tanggal_penelusuran' => ['nullable', 'required_unless:status_penelusuran,belum_terdata', 'date_format:Y-m-d', 'before_or_equal:today'],
            'catatan_penelusuran' => ['nullable', 'required_if:status_penelusuran,tidak_melanjutkan', 'string', 'max:3000'],
            'nomor_wa' => ['nullable', 'string', 'regex:/^\+?[0-9]{8,15}$/'], 'email' => ['nullable', 'email', 'max:254'],
            'konfirmasi_lulus' => ['required', 'accepted'], 'token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid'], 'versi' => [$edit ? 'required' : 'nullable', 'integer', 'min:0'],
            'catatan_perubahan' => [$edit ? 'required' : 'nullable', 'string', 'min:5', 'max:2000']]);
        $data['versi'] = (int) ($data['versi'] ?? 0);

        return $data;
    }

    private function form(Request $request, AlumniHumas $alumni)
    {
        $id = old('siswa_id', $alumni->siswa_id);
        $siswa = is_scalar($id) && ctype_digit((string) $id) ? Siswa::find($id, ['id', 'nama_lengkap', 'nis', 'nisn', 'jenis_kelamin']) : null;

        return $this->halaman('form', ['alumni' => $alumni, 'tokenPembuatan' => (string) Str::uuid(), 'sumberSiswa' => $siswa ? $this->pilihanSiswa($siswa) : null]);
    }

    private function pilihanSiswa(Siswa $siswa): array
    {
        $anggota = $siswa->relationLoaded('anggotaKelas') ? $siswa->anggotaKelas : $siswa->anggotaKelas()->whereHas('kelas', fn ($q) => $q->where('tingkat', 9))
            ->with(['kelas:id,nama', 'tahunPelajaran:id,nama,tanggal_selesai'])->orderByDesc('id')->get(['id', 'siswa_id', 'kelas_id', 'tahun_pelajaran_id']);

        return $siswa->only(['id', 'nama_lengkap', 'nis', 'nisn', 'jenis_kelamin']) + ['kelas' => $anggota
            ->map(fn ($a) => ['id' => $a->id, 'nama' => $a->kelas->nama, 'tahun_pelajaran' => $a->tahunPelajaran?->nama, 'tahun_lulus' => $a->tahunPelajaran?->tanggal_selesai?->year])->all()];
    }

    private function batasEkspor($query): void
    {
        if ((clone $query)->count() > 10000) {
            throw ValidationException::withMessages(['tahun_lulus' => 'Maksimal 10.000 alumni per ekspor atau cetak. Pilih angkatan atau persempit filter.']);
        }
    }

    private function akses(Request $request): void
    {
        abort_unless($request->user()->aktif && ! $request->user()->akunOrangTua() && ! $request->user()->akunSiswa(), 403);
    }

    private function halaman(string $view, array $data)
    {
        return response()->view('alumni-humas.'.$view, $data)->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
