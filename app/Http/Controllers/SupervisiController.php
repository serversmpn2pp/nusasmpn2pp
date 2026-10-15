<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\Pegawai;
use App\Models\ProgramSupervisi;
use App\Models\RiwayatSupervisi;
use App\Models\SesiSupervisi;
use App\Models\TahunPelajaran;
use App\Models\VersiInstrumenSupervisi;
use App\Services\Supervisi\AksesSupervisi;
use App\Services\Supervisi\InstrumenSupervisiAwal;
use App\Services\Supervisi\InstrumenSupervisiService;
use App\Services\Supervisi\JadwalSupervisiService;
use App\Services\Supervisi\PengisianSupervisiService;
use App\Services\Supervisi\RekapSupervisiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupervisiController extends Controller
{
    public function __construct(private AksesSupervisi $akses, private InstrumenSupervisiService $instrumen, private JadwalSupervisiService $jadwal) {}

    public function index(Request $r)
    {
        $this->akses->staff($r->user());
        $filter = $r->validate(['status' => ['nullable', Rule::in(array_keys(SesiSupervisi::STATUS))], 'program_id' => ['nullable', 'integer'],
            'dari' => ['nullable', 'date_format:Y-m-d'], 'sampai' => ['nullable', 'date_format:Y-m-d', Rule::when($r->filled('dari'), 'after_or_equal:dari')],
            'kata_kunci' => ['nullable', 'string', 'max:120'], 'halaman' => ['nullable', 'integer', 'min:1']]);
        $q = $this->akses->sesi(SesiSupervisi::query(), $r->user())
            ->when($filter['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filter['program_id'] ?? null, fn ($q, $v) => $q->where('program_supervisi_id', $v))
            ->when($filter['dari'] ?? null, fn ($q, $v) => $q->whereDate('waktu_mulai', '>=', $v))
            ->when($filter['sampai'] ?? null, fn ($q, $v) => $q->whereDate('waktu_mulai', '<=', $v))
            ->when($filter['kata_kunci'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->whereLike('topik', '%'.$v.'%')->orWhereHas('guru', fn ($q) => $q->whereLike('nama_lengkap', '%'.$v.'%'))));
        $stat = (clone $q)->selectRaw('status, COUNT(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status');
        $daftar = $q->with(['praobservasi:id,sesi_supervisi_id,status', 'penilaian:id,sesi_supervisi_id,status,versi', 'pascaobservasi:id,sesi_supervisi_id,status,penilaian_versi', 'supervisor:id,nama_lengkap', 'program:id,nama,semester,tahun_pelajaran_id,status'])->orderBy('waktu_mulai')->orderBy('id')->paginate(20, ['*'], 'halaman')->withQueryString();
        $daftar->getCollection()->transform(fn ($s) => $this->dataSesi($s));

        return $this->hasil($r, 'index', ['daftar' => $daftar, 'statistik' => $stat, 'filter' => $filter, 'program' => $this->programQuery($r)->orderByDesc('id')->get(['id', 'nama']), 'hak_akses' => $this->hak($r)]);
    }

    public function pilihan(Request $r)
    {
        $this->akses->kelola($r->user());
        $data = $r->validate(['tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id']]);

        return response()->json(['data' => $this->referensi((int) $data['tahun_pelajaran_id'])])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function programIndex(Request $r)
    {
        $this->akses->staff($r->user());
        $r->validate(['halaman' => ['nullable', 'integer', 'min:1']]);
        $q = $this->programQuery($r)->with('tahunPelajaran:id,nama')->withCount(['sesi' => fn ($q) => $this->akses->sesi($q, $r->user())]);
        $daftar = $q->latest('id')->paginate(20, ['*'], 'halaman')->withQueryString();
        $daftar->getCollection()->transform(fn ($p) => [...$this->dataProgram($p), 'jumlah_sesi' => $p->sesi_count]);

        return $this->hasil($r, 'program-index', ['daftar' => $daftar, 'hak_akses' => $this->hak($r)]);
    }

    public function programForm(Request $r, ?ProgramSupervisi $program = null)
    {
        $this->akses->kelola($r->user());
        $program = $program?->exists ? $program : new ProgramSupervisi(['semester' => 'ganjil', 'status' => 'draf']);

        return $this->hasil($r, 'program-form', ['program' => $program, 'tahun' => TahunPelajaran::orderByDesc('nama')->get(), 'token' => (string) Str::uuid()]);
    }

    public function programSimpan(Request $r, ?ProgramSupervisi $program = null)
    {
        $p = $this->jadwal->program($r->user(), $r->all(), $program?->exists ? $program : null);

        return $this->tersimpan($r, $this->dataProgram($p), route('supervisi.program.show', $p), 'Program supervisi disimpan.');
    }

    public function programShow(Request $r, ProgramSupervisi $program)
    {
        $this->akses->staff($r->user());
        $this->programQuery($r)->findOrFail($program->id);
        $daftar = $this->akses->sesi($program->sesi()->getQuery(), $r->user())->with(['supervisor:id,nama_lengkap', 'praobservasi:id,sesi_supervisi_id,status', 'penilaian:id,sesi_supervisi_id,status,versi', 'pascaobservasi:id,sesi_supervisi_id,status,penilaian_versi'])->orderBy('waktu_mulai')->orderBy('id')->get()->map(fn ($s) => $this->dataSesi($s));

        return $this->hasil($r, 'program-show', ['program' => $this->dataProgram($program), 'sesi' => $daftar, 'hak_akses' => $this->hak($r),
            'riwayat' => $this->akses->global($r->user()) ? $this->riwayat('program_supervisi_id', $program->id) : []]);
    }

    public function tambahForm(Request $r, ProgramSupervisi $program)
    {
        $this->akses->kelola($r->user());

        return $this->hasil($r, 'jadwal-form', ['program' => $program, 'pilihan' => $this->referensi($program->tahun_pelajaran_id), 'sesi' => null]);
    }

    public function tambah(Request $r, ProgramSupervisi $program)
    {
        $sesi = $this->jadwal->tambah($r->user(), $program, $r->all());

        return $this->tersimpan($r, ['items' => array_map(fn ($s) => $this->dataSesi($s), $sesi)], route('supervisi.program.show', $program), 'Draf jadwal disimpan. Terbitkan untuk memberi tahu guru dan supervisor.');
    }

    public function sesiShow(Request $r, SesiSupervisi $sesi)
    {
        $this->akses->sesi(SesiSupervisi::query(), $r->user())->findOrFail($sesi->id);

        return $this->hasil($r, 'sesi-show', ['sesi' => $this->dataSesi($sesi), 'instrumen' => $this->instrumen->data($sesi->instrumen),
            'bisa_cetak' => app(RekapSupervisiService::class)->baris($sesi)['bisa_cetak'],
            'pengisian' => ['praobservasi' => app(PengisianSupervisiService::class)->dataPra($r->user(), $sesi), 'penilaian' => app(PengisianSupervisiService::class)->dataPenilaian($r->user(), $sesi)],
            'riwayat' => $this->riwayat('sesi_supervisi_id', $sesi->id), 'hak_akses' => $this->hak($r)]);
    }

    public function sesiEdit(Request $r, SesiSupervisi $sesi)
    {
        $this->akses->kelola($r->user());

        return $this->hasil($r, 'jadwal-form', ['program' => $sesi->program, 'pilihan' => $this->referensi($sesi->program->tahun_pelajaran_id), 'sesi' => $sesi]);
    }

    public function sesiUpdate(Request $r, SesiSupervisi $sesi)
    {
        $s = $this->jadwal->ubah($r->user(), $sesi, $r->all());

        return $this->tersimpan($r, $this->dataSesi($s), route('supervisi.sesi.show', $s), 'Perubahan jadwal disimpan.');
    }

    public function sesiTindakan(Request $r, ProgramSupervisi $program, string $aksi)
    {
        $sesi = $this->jadwal->tindakan($r->user(), $program, $aksi, $r->all());

        return $this->tersimpan($r, ['items' => array_map(fn ($s) => $this->dataSesi($s), $sesi)], route('supervisi.program.show', $program), $aksi === 'terbitkan' ? 'Jadwal diterbitkan dan notifikasi dibuat.' : 'Jadwal terpilih dibatalkan.');
    }

    public function instrumenIndex(Request $r)
    {
        $this->akses->kelola($r->user(), true);
        $r->validate(['halaman' => ['nullable', 'integer', 'min:1']]);
        $daftar = VersiInstrumenSupervisi::with('instrumen')->orderByDesc('instrumen_supervisi_id')->orderByDesc('nomor')->paginate(20, ['*'], 'halaman');
        $daftar->getCollection()->transform(fn ($v) => $this->instrumen->data($v));

        return $this->hasil($r, 'instrumen-index', ['daftar' => $daftar]);
    }

    public function instrumenForm(Request $r, ?VersiInstrumenSupervisi $instrumen = null)
    {
        $this->akses->kelola($r->user(), true);
        $instrumen = $instrumen?->exists ? $instrumen : null;
        abort_if($instrumen && $instrumen->status !== 'draf', 403);

        return $this->hasil($r, 'instrumen-form', ['data' => $instrumen ? $this->instrumen->data($instrumen) : ['nama' => '', 'catatan' => '', 'skala' => InstrumenSupervisiAwal::skala(), 'kelompok' => [], 'pertanyaan_refleksi' => InstrumenSupervisiAwal::pertanyaanRefleksi()], 'token' => (string) Str::uuid()]);
    }

    public function instrumenShow(Request $r, VersiInstrumenSupervisi $instrumen)
    {
        $this->akses->kelola($r->user(), true);
        $data = $this->instrumen->data($instrumen);
        $riwayat = $this->riwayat('versi_instrumen_supervisi_id', $instrumen->id);

        return $this->hasil($r, 'instrumen-show', $r->routeIs('api.*') ? [...$data, 'riwayat' => $riwayat] : compact('data', 'riwayat'));
    }

    public function instrumenSimpan(Request $r, ?VersiInstrumenSupervisi $instrumen = null)
    {
        $v = $this->instrumen->simpan($r->user(), $r->all(), $instrumen?->exists ? $instrumen : null);

        return $this->tersimpan($r, $this->instrumen->data($v), route('supervisi.instrumen.show', $v), 'Draf instrumen disimpan.');
    }

    public function instrumenTindakan(Request $r, VersiInstrumenSupervisi $instrumen, string $aksi)
    {
        $v = $this->instrumen->tindakan($r->user(), $instrumen, $aksi, $r->all());

        return $this->tersimpan($r, $this->instrumen->data($v), route($aksi === 'revisi' ? 'supervisi.instrumen.edit' : 'supervisi.instrumen.show', $v), 'Perubahan instrumen disimpan.');
    }

    private function programQuery(Request $r)
    {
        return ProgramSupervisi::query()->when(! $this->akses->global($r->user()), fn ($q) => $q->whereHas('sesi', fn ($q) => $this->akses->sesi($q, $r->user())));
    }

    private function hak(Request $r): array
    {
        return ['kelola' => $r->user()->memilikiIzin('supervisi.kelola'), 'instrumen' => $r->user()->memilikiIzin('supervisi.instrumen_kelola'), 'monitor' => $this->akses->global($r->user()), 'tahap_tersedia' => ['jadwal', 'praobservasi', 'penilaian', 'pascaobservasi']];
    }

    private function dataProgram(ProgramSupervisi $p): array
    {
        return [...$p->only(['id', 'nama', 'tujuan', 'tahun_pelajaran_id', 'semester', 'status', 'versi']), 'tahun_pelajaran' => $p->tahunPelajaran->nama,
            'tanggal_mulai' => $p->tanggal_mulai->toDateString(), 'tanggal_selesai' => $p->tanggal_selesai->toDateString()];
    }

    private function dataSesi(SesiSupervisi $s): array
    {
        return [...$s->only(['id', 'program_supervisi_id', 'versi_instrumen_supervisi_id', 'guru_mata_pelajaran_id', 'guru_pegawai_id', 'supervisor_pegawai_id', 'kelas_id', 'status', 'versi', 'tempat', 'topik', 'alasan_di_luar_jadwal', 'konteks']),
            'program' => $s->program->nama, 'program_status' => $s->program->status, 'supervisor' => $s->supervisor->nama_lengkap,
            'waktu_mulai' => $s->waktu_mulai->toIso8601String(), 'waktu_selesai' => $s->waktu_selesai->toIso8601String(), 'diterbitkan_pada' => $s->diterbitkan_pada?->toIso8601String(),
            'praobservasi_status' => $s->praobservasi?->status ?? 'belum_diisi', 'penilaian_status' => $s->penilaian?->status ?? 'belum_diisi',
            'pascaobservasi_status' => $s->pascaobservasi?->status ?? 'belum_diisi',
            'pascaobservasi_terbaru' => $s->penilaian?->status === 'final' && (! $s->pascaobservasi || $s->pascaobservasi->penilaian_versi === $s->penilaian->versi),
            'jadwal_terlewat' => $s->status === 'terjadwal' && $s->waktu_selesai->isPast()];
    }

    private function referensi(int $tahun): array
    {
        $penugasan = GuruMataPelajaran::with(['pegawai:id,nama_lengkap', 'kelas:id,nama', 'mataPelajaran:id,nama'])->where('tahun_pelajaran_id', $tahun)->where('aktif', true)
            ->whereHas('pegawai', fn ($q) => $q->where('aktif', true))->whereHas('kelas', fn ($q) => $q->where('aktif', true))->whereHas('mataPelajaran', fn ($q) => $q->where('aktif', true))->get();
        $jadwal = JadwalPelajaran::with('jamPelajaran')->where('tahun_pelajaran_id', $tahun)->where('aktif', true)->whereHas('jamPelajaran', fn ($q) => $q->where('aktif', true)->where('jenis', 'pelajaran'))->get();

        return ['penugasan' => $penugasan->map(fn ($g) => ['id' => $g->id, 'label' => $g->pegawai->nama_lengkap.' - '.$g->mataPelajaran->nama.' - '.$g->kelas->nama, 'kelas' => $g->kelas->nama,
            'jadwal' => $jadwal->where('guru_mata_pelajaran_id', $g->id)->map(fn ($j) => ['hari' => $j->hari, 'mulai' => substr($j->jamPelajaran->jam_mulai, 0, 5), 'selesai' => substr($j->jamPelajaran->jam_selesai, 0, 5)])->values()->all()])->values()->all(),
            'supervisor' => Pegawai::where('aktif', true)->whereHas('pengguna', fn ($q) => $q->where('aktif', true)->whereNull('siswa_id')->where('peran', '!=', 'orang_tua'))->orderBy('nama_lengkap')->get(['id', 'nama_lengkap'])->toArray(),
            'instrumen' => VersiInstrumenSupervisi::with('instrumen')->where('status', 'terbit')->whereHas('instrumen', fn ($q) => $q->where('aktif', true))->orderByDesc('id')->get()->map(fn ($v) => collect($this->instrumen->data($v))->except(['kelompok', 'skala', 'catatan'])->all())->all(),
            'timezone' => config('app.timezone')];
    }

    private function riwayat(string $kolom, int $id): array
    {
        return RiwayatSupervisi::with('pengguna:id,nama')->where($kolom, $id)->latest('id')->limit(50)->get()->map(fn ($r) => ['aksi' => $r->aksi, 'alasan' => $r->alasan, 'oleh' => $r->pengguna?->nama ?? 'Akun tidak tersedia', 'waktu' => $r->created_at->toIso8601String()])->all();
    }

    private function hasil(Request $r, string $view, array $data)
    {
        return ($r->routeIs('api.*') ? response()->json(['data' => $data]) : response()->view('supervisi.'.$view, $data))->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function tersimpan(Request $r, array $data, string $url, string $pesan)
    {
        return $r->routeIs('api.*') ? response()->json(['data' => $data, 'message' => $pesan])->header('Cache-Control', 'private, no-store, max-age=0') : redirect($url)->with('berhasil', $pesan);
    }
}
