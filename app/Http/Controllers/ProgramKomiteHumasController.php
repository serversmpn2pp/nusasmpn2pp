<?php

namespace App\Http\Controllers;

use App\Models\AgendaHumas;
use App\Models\PeriodeKomiteHumas;
use App\Models\ProgramKomiteHumas;
use App\Services\Humas\KelolaProgramKomiteHumasService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramKomiteHumasController extends Controller
{
    public function __construct(private readonly KelolaProgramKomiteHumasService $kelola) {}

    public function index(Request $request, PeriodeKomiteHumas $periode)
    {
        $this->akses($request, $periode);
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(array_keys(ProgramKomiteHumas::STATUS))], 'terlambat' => ['nullable', 'boolean']]);
        $query = $periode->program()->with(['penanggungJawab:id,nama,aktif'])->withCount('agenda')
            ->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->whereLike('nama', '%'.$kata.'%'))
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($request->boolean('terlambat'), fn ($q) => $q->whereIn('status', ['rencana', 'berjalan'])->whereDate('tanggal_selesai', '<', today()));
        $jumlah = $periode->program()->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');

        return $this->halaman('index', ['periode' => $periode, 'filter' => $filter,
            'daftar' => $query->orderBy('tanggal_selesai')->orderBy('id')->paginate(20)->withQueryString(),
            'statistik' => collect(ProgramKomiteHumas::STATUS)->map(fn ($label, $kode) => (int) ($jumlah[$kode] ?? 0))->all(),
            'terlambat' => $periode->program()->whereIn('status', ['rencana', 'berjalan'])->whereDate('tanggal_selesai', '<', today())->count()]);
    }

    public function create(Request $request, PeriodeKomiteHumas $periode)
    {
        $this->akses($request, $periode, null, true);

        return $this->form($periode, new ProgramKomiteHumas(['status' => 'rencana', 'tanggal_mulai' => $periode->tanggal_mulai, 'tanggal_selesai' => $periode->tanggal_selesai]));
    }

    public function store(Request $request, PeriodeKomiteHumas $periode)
    {
        $this->akses($request, $periode, null, true);
        $data = $this->validasi($request);
        $program = DB::transaction(function () use ($request, $periode, $data) {
            $periode = $this->kelola->kunciPeriode($periode->id);
            $program = ProgramKomiteHumas::where('token_pembuatan', $data['token_pembuatan'])->first();
            if ($program) {
                abort_unless($program->periode_komite_humas_id === $periode->id, 404);
                abort_unless($program->dibuat_oleh_pengguna_id === $request->user()->id, 403);

                return $program;
            }
            $program = $periode->program()->make(Arr::only($data, ['token_pembuatan', ...ProgramKomiteHumas::KOLOM]));
            $this->kelola->validasiProgram($periode, $program);
            $program->forceFill(['versi' => 0, 'dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id,
                'diselesaikan_pada' => $program->status === 'selesai' ? now() : null])->save();
            $this->kelola->catat($program, $request, 'Program ditambahkan');

            return $program;
        });

        return $this->kembali($periode, $program, 'Program kerja berhasil disimpan.');
    }

    public function show(Request $request, PeriodeKomiteHumas $periode, ProgramKomiteHumas $program)
    {
        $this->akses($request, $periode, $program);
        $data = $request->validate(['cari_rapat' => ['nullable', 'string', 'max:120']]);
        $bolehAgenda = $request->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']);
        $bolehHubungkan = $periode->status !== 'arsip' && $request->user()->memilikiIzin('komite_humas.kelola') && $request->user()->memilikiIzin('agenda_humas.kelola');
        $program->load('penanggungJawab:id,nama,aktif');

        return $this->halaman('show', ['periode' => $periode, 'program' => $program, 'bolehAgenda' => $bolehAgenda, 'bolehHubungkan' => $bolehHubungkan,
            'rapat' => $bolehAgenda ? $program->agenda()->withCount(['peserta', 'peserta as hadir_count' => fn ($q) => $q->where('status_kehadiran', 'hadir'),
                'tindakLanjut as tertunda_count' => fn ($q) => $q->where('status', '<>', 'selesai')])->latest('waktu_mulai')->paginate(10, ['agenda_humas.*'], 'halaman_rapat')->withQueryString() : null,
            'pilihanRapat' => $bolehHubungkan && $program->status !== 'dibatalkan' ? AgendaHumas::where('jenis', 'komite')->where('status', '<>', 'dibatalkan')
                ->whereDate('waktu_mulai', '>=', $periode->tanggal_mulai)->whereDate('waktu_selesai', '<=', $periode->tanggal_selesai)
                ->whereDoesntHave('programKomite', fn ($q) => $q->where('program_komite_humas.id', $program->id))
                ->when($data['cari_rapat'] ?? null, fn ($q, $kata) => $q->whereLike('judul', '%'.$kata.'%'))
                ->latest('waktu_mulai')->paginate(10, ['id', 'judul', 'waktu_mulai', 'status'], 'halaman_pilihan')->withQueryString() : null,
            'cariRapat' => $data['cari_rapat'] ?? '', 'riwayat' => $program->riwayat()->with('pengguna:id,nama')->paginate(10, ['*'], 'halaman_riwayat')->withQueryString()]);
    }

    public function edit(Request $request, PeriodeKomiteHumas $periode, ProgramKomiteHumas $program)
    {
        $this->akses($request, $periode, $program, true);

        return $this->form($periode, $program);
    }

    public function update(Request $request, PeriodeKomiteHumas $periode, ProgramKomiteHumas $program)
    {
        $this->akses($request, $periode, $program, true);
        $data = $this->validasi($request, true);
        DB::transaction(function () use ($request, $periode, $program, $data) {
            $periode = $this->kelola->kunciPeriode($periode->id);
            $program = $this->kunciProgram($program, $data['versi']);
            $program->fill(Arr::only($data, ProgramKomiteHumas::KOLOM));
            $this->kelola->validasiProgram($periode, $program);
            if ($program->isDirty()) {
                $program->forceFill(['versi' => $program->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id,
                    'diselesaikan_pada' => $program->status === 'selesai' ? ($program->diselesaikan_pada ?? now()) : null])->save();
                $this->kelola->catat($program, $request, 'Program diperbarui', $data['catatan_perubahan']);
            }
        });

        return $this->kembali($periode, $program, 'Perubahan program berhasil disimpan.');
    }

    public function hubungkan(Request $request, PeriodeKomiteHumas $periode, ProgramKomiteHumas $program)
    {
        $this->akses($request, $periode, $program, true);
        abort_unless($request->user()->memilikiIzin('agenda_humas.kelola'), 403);
        $data = $request->validate(['agenda_humas_id' => ['required', 'integer', 'exists:agenda_humas,id'], 'versi' => ['required', 'integer', 'min:0'], 'catatan_perubahan' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $periode, $program, $data) {
            $this->kelola->kunciPeriode($periode->id);
            $program = $this->kunciProgram($program, $data['versi']);
            $agenda = AgendaHumas::lockForUpdate()->findOrFail($data['agenda_humas_id']);
            $this->kelola->hubungkan($program, $agenda, $request, $data['catatan_perubahan']);
        });

        return $this->kembali($periode, $program, 'Rapat berhasil dihubungkan dengan program.');
    }

    public function lepas(Request $request, PeriodeKomiteHumas $periode, ProgramKomiteHumas $program, AgendaHumas $agendaHumas)
    {
        $this->akses($request, $periode, $program, true);
        abort_unless($request->user()->memilikiIzin('agenda_humas.kelola'), 403);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'catatan_perubahan' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $periode, $program, $agendaHumas, $data) {
            $this->kelola->kunciPeriode($periode->id);
            $program = $this->kunciProgram($program, $data['versi']);
            abort_unless($program->agenda()->whereKey($agendaHumas->id)->exists(), 404);
            $program->agenda()->detach($agendaHumas->id);
            $program->forceFill(['versi' => $program->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
            $this->kelola->catat($program, $request, 'Hubungan rapat dilepas', $data['catatan_perubahan']);
        });

        return $this->kembali($periode, $program, 'Hubungan rapat dilepas. Agenda, presensi, dan notulen tetap tersimpan.');
    }

    private function validasi(Request $request, bool $edit = false): array
    {
        return $request->validate(['nama' => ['required', 'string', 'max:180'], 'tujuan' => ['required', 'string', 'max:5000'], 'target_hasil' => ['required', 'string', 'max:5000'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'], 'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'pengurus_komite_humas_id' => ['nullable', 'required_if:status,berjalan,selesai', 'integer', 'min:1'], 'status' => ['required', Rule::in(array_keys(ProgramKomiteHumas::STATUS))],
            'capaian' => ['nullable', 'required_if:status,selesai', 'string', 'max:10000'], 'catatan_evaluasi' => ['nullable', 'required_if:status,dibatalkan', 'string', 'max:5000'],
            'token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid'], 'versi' => [$edit ? 'required' : 'nullable', 'integer', 'min:0'],
            'catatan_perubahan' => [$edit ? 'required' : 'nullable', 'string', 'min:5', 'max:2000']]);
    }

    private function form(PeriodeKomiteHumas $periode, ProgramKomiteHumas $program)
    {
        return $this->halaman('form', ['periode' => $periode, 'program' => $program, 'tokenPembuatan' => (string) Str::uuid(),
            'pengurus' => $periode->pengurus()->where(fn ($q) => $q->where('aktif', true)->orWhere('id', $program->pengurus_komite_humas_id))->get(['id', 'nama', 'jabatan', 'aktif'])]);
    }

    private function akses(Request $request, PeriodeKomiteHumas $periode, ?ProgramKomiteHumas $program = null, bool $ubah = false): void
    {
        abort_unless($request->user()->aktif && ! $request->user()->akunOrangTua() && ! $request->user()->akunSiswa(), 403);
        if ($program) {
            abort_unless($program->periode_komite_humas_id === $periode->id, 404);
        }
        if ($ubah && $periode->status === 'arsip') {
            throw ValidationException::withMessages(['periode' => 'Kepengurusan diarsipkan. Program kerja hanya dapat dilihat.']);
        }
    }

    private function kunciProgram(ProgramKomiteHumas $program, int $versi): ProgramKomiteHumas
    {
        $program = ProgramKomiteHumas::lockForUpdate()->findOrFail($program->id);
        if ($program->versi !== $versi) {
            throw ValidationException::withMessages(['versi' => 'Program atau hubungan rapat telah berubah. Muat ulang sebelum menyimpan.']);
        }

        return $program;
    }

    private function halaman(string $view, array $data)
    {
        return response()->view('program-komite-humas.'.$view, $data)->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function kembali(PeriodeKomiteHumas $periode, ProgramKomiteHumas $program, string $pesan)
    {
        return redirect()->route('komite-humas.program.show', [$periode, $program])->with('berhasil', $pesan);
    }
}
