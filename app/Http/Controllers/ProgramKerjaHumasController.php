<?php

namespace App\Http\Controllers;

use App\Models\Izin;
use App\Models\ProgramKerjaHumas;
use App\Models\TahunPelajaran;
use App\Services\Humas\KelolaProgramKerjaHumasService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProgramKerjaHumasController extends Controller
{
    public function __construct(private readonly KelolaProgramKerjaHumasService $kelola) {}

    public function index(Request $request)
    {
        $this->akses($request);
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'semester' => ['nullable', Rule::in(array_keys(ProgramKerjaHumas::SEMESTER))], 'bidang' => ['nullable', Rule::in(array_keys(ProgramKerjaHumas::BIDANG))],
            'status' => ['nullable', Rule::in(array_keys(ProgramKerjaHumas::STATUS))], 'terlambat' => ['nullable', 'boolean']]);
        $query = ProgramKerjaHumas::query()->when($filter['tahun_pelajaran_id'] ?? null, fn ($q, $id) => $q->where('tahun_pelajaran_id', $id))
            ->when($filter['semester'] ?? null, fn ($q, $s) => $q->where('semester', $s))->when($filter['bidang'] ?? null, fn ($q, $b) => $q->where('bidang', $b))
            ->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->whereLike('nama', '%'.$kata.'%'));
        $jumlah = (clone $query)->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');
        $terlambat = (clone $query)->whereIn('status', ['rencana', 'berjalan'])->whereDate('tanggal_selesai', '<', today())->count();
        $query->when($filter['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('terlambat'), fn ($q) => $q->whereIn('status', ['rencana', 'berjalan'])->whereDate('tanggal_selesai', '<', today()));

        return $this->halaman('index', ['filter' => $filter, 'tahun' => TahunPelajaran::orderByDesc('nama')->get(['id', 'nama']), 'terlambat' => $terlambat,
            'statistik' => collect(ProgramKerjaHumas::STATUS)->map(fn ($label, $kode) => (int) ($jumlah[$kode] ?? 0))->all(),
            'daftar' => $query->with('tahunPelajaran:id,nama')->withCount('laporanFinal')->orderBy('tanggal_selesai')->orderBy('id')->paginate(20)->withQueryString()]);
    }

    public function create(Request $request)
    {
        $this->akses($request);
        $tahun = TahunPelajaran::where('aktif', true)->first();

        return $this->form(new ProgramKerjaHumas(['tahun_pelajaran_id' => $tahun?->id, 'semester' => 'tahunan', 'bidang' => 'orang_tua',
            'status' => 'rencana', 'target_kegiatan' => 1, 'penanggung_jawab' => $request->user()->nama,
            'tanggal_mulai' => $tahun?->tanggal_mulai, 'tanggal_selesai' => $tahun?->tanggal_selesai]));
    }

    public function store(Request $request)
    {
        $this->akses($request);
        $data = $this->validasi($request);
        $program = DB::transaction(function () use ($request, $data) {
            Izin::where('kode', 'program_kerja_humas.kelola')->lockForUpdate()->firstOrFail();
            $program = ProgramKerjaHumas::where('token_pembuatan', $data['token_pembuatan'])->first();
            if ($program) {
                abort_unless($program->dibuat_oleh_pengguna_id === $request->user()->id, 403);

                return $program;
            }
            $program = new ProgramKerjaHumas(Arr::only($data, ['token_pembuatan', ...ProgramKerjaHumas::KOLOM]));
            $this->kelola->validasiProgram($program);
            $program->forceFill(['dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id, 'versi' => 0])->save();
            $this->kelola->catat($program, $request, 'Program ditambahkan');

            return $program;
        });

        return redirect()->route('program-kerja-humas.show', $program)->with('berhasil', 'Program kerja berhasil disimpan.');
    }

    public function show(Request $request, ProgramKerjaHumas $program)
    {
        $this->akses($request);
        $program->load('tahunPelajaran')->loadCount(['laporanFinal', 'laporan as draf_count' => fn ($q) => $q->where('status', 'draf')]);

        return $this->halaman('show', ['program' => $program,
            'laporan' => $program->laporan()->withCount('bukti')->latest('tanggal_mulai')->paginate(15)->withQueryString(),
            'riwayat' => $program->riwayat()->whereNull('laporan_pelaksanaan_humas_id')->with('pengguna:id,nama')->paginate(10, ['*'], 'riwayat')]);
    }

    public function edit(Request $request, ProgramKerjaHumas $program)
    {
        $this->akses($request);

        return $this->form($program);
    }

    public function update(Request $request, ProgramKerjaHumas $program)
    {
        $this->akses($request);
        $data = $this->validasi($request, true);
        DB::transaction(function () use ($request, $program, $data) {
            $program = $this->kelola->kunci($program, $data['versi']);
            $program->fill(Arr::only($data, ProgramKerjaHumas::KOLOM));
            $this->kelola->validasiProgram($program);
            if ($program->isDirty()) {
                $program->forceFill(['versi' => $program->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                $this->kelola->catat($program, $request, 'Program diperbarui', $data['catatan_perubahan']);
            }
        });

        return redirect()->route('program-kerja-humas.show', $program)->with('berhasil', 'Perubahan program berhasil disimpan.');
    }

    public function cetak(Request $request, ProgramKerjaHumas $program)
    {
        $this->akses($request);
        $program->load('tahunPelajaran');

        return $this->halaman('cetak-program', ['program' => $program, 'laporan' => $program->laporan()->orderBy('tanggal_mulai')->get()]);
    }

    private function akses(Request $request): void
    {
        abort_unless($request->user()->aktif && ! $request->user()->akunOrangTua() && ! $request->user()->akunSiswa(), 403);
    }

    private function validasi(Request $request, bool $edit = false): array
    {
        return $request->validate(['tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'], 'semester' => ['required', Rule::in(array_keys(ProgramKerjaHumas::SEMESTER))],
            'bidang' => ['required', Rule::in(array_keys(ProgramKerjaHumas::BIDANG))], 'nama' => ['required', 'string', 'max:180'],
            'tujuan' => ['required', 'string', 'max:5000'], 'sasaran' => ['required', 'string', 'max:5000'], 'target_hasil' => ['required', 'string', 'max:5000'],
            'target_kegiatan' => ['required', 'integer', 'min:1', 'max:10000'], 'penanggung_jawab' => ['required', 'string', 'max:180'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'], 'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'status' => ['required', Rule::in(array_keys(ProgramKerjaHumas::STATUS))], 'evaluasi' => ['nullable', 'required_if:status,selesai,dibatalkan', 'string', 'max:10000'],
            'token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid'], 'versi' => [$edit ? 'required' : 'nullable', 'integer', 'min:0'],
            'catatan_perubahan' => [$edit ? 'required' : 'nullable', 'string', 'min:5', 'max:2000']]);
    }

    private function form(ProgramKerjaHumas $program)
    {
        return $this->halaman('form', ['program' => $program, 'tahun' => TahunPelajaran::orderByDesc('nama')->get(), 'tokenPembuatan' => (string) Str::uuid()]);
    }

    private function halaman(string $view, array $data)
    {
        return response()->view('program-kerja-humas.'.$view, $data)->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
