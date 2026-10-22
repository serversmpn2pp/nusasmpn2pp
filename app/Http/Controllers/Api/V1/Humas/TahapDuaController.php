<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Http\Controllers\KemitraanHumasController;
use App\Http\Controllers\KomiteHumasController;
use App\Http\Controllers\LaporanPelaksanaanHumasController;
use App\Http\Controllers\PortofolioAkreditasiHumasController;
use App\Http\Controllers\ProgramKerjaHumasController;
use App\Http\Controllers\ProgramKomiteHumasController;
use App\Models\AgendaHumas;
use App\Models\BuktiAkreditasiHumas;
use App\Models\BuktiLaporanHumas;
use App\Models\DokumenHumas;
use App\Models\KerjaSamaHumas;
use App\Models\MitraHumas;
use App\Models\PengurusKomiteHumas;
use App\Models\PeriodeKomiteHumas;
use App\Models\PortofolioAkreditasiHumas;
use App\Models\ProgramKerjaHumas;
use App\Models\RiwayatDokumenHumas;
use App\Models\TahunPelajaran;
use App\Services\Mobile\HumasMobileService;
use App\Services\Mobile\HumasTahapDuaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TahapDuaController extends HumasController
{
    public function __construct(private readonly HumasTahapDuaService $data, private readonly HumasMobileService $mobile) {}

    private function kind(Request $r): string
    {
        return $r->route('jenis_mobile');
    }

    private function akses(Request $r, bool $write = false): void
    {
        $p = HumasTahapDuaService::MODUL[$this->kind($r)][1];
        $this->staff($r, $write ? $p.'.kelola' : [$p.'.lihat', $p.'.kelola']);
    }

    private function parent(Request $r): ?Model
    {
        $class = match ($this->kind($r)) {
            'laporan' => ProgramKerjaHumas::class, 'program-komite' => PeriodeKomiteHumas::class,
            'mou' => MitraHumas::class, 'butir' => PortofolioAkreditasiHumas::class, default => null,
        };

        return $class ? $class::findOrFail($r->route('id')) : null;
    }

    private function record(Request $r): Model
    {
        $kind = $this->kind($r);
        $parent = $this->parent($r);
        $class = HumasTahapDuaService::MODUL[$kind][0];
        $m = $class::findOrFail($r->route($parent ? 'anak' : 'id'));
        if ($parent) {
            $key = match ($kind) {
                'laporan' => 'program_kerja_humas_id', 'program-komite' => 'periode_komite_humas_id',
                'mou' => 'mitra_humas_id', 'butir' => 'portofolio_akreditasi_humas_id',
            };
            abort_unless($parent->id === $m->$key, 404);
        }
        abort_if($kind === 'butir' && $m->dihapus_pada, 404);

        return $m;
    }

    public function index(Request $r)
    {
        $this->akses($r);
        $kind = $this->kind($r);
        $class = HumasTahapDuaService::MODUL[$kind][0];
        $parent = $this->parent($r);
        $f = $r->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(array_keys($class::STATUS))],
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'], 'semester' => ['nullable', Rule::in(array_keys(ProgramKerjaHumas::SEMESTER))],
            'bidang' => ['nullable', Rule::in(array_keys($kind === 'mou' ? KerjaSamaHumas::BIDANG : ProgramKerjaHumas::BIDANG))],
            'jenis' => ['nullable', Rule::in(array_keys(MitraHumas::JENIS))], 'terlambat' => ['nullable', 'boolean'],
            'masa_berlaku' => ['nullable', Rule::in(array_keys(KerjaSamaHumas::STATUS_BERLAKU))]]) + $this->halaman($r);
        $q = $parent ? $parent->{match ($kind) {
            'laporan' => 'laporan', 'program-komite' => 'program', 'mou' => 'kerjaSama', 'butir' => 'butir'
        }}() : $class::query();
        $title = in_array($kind, ['laporan', 'mou', 'butir']) ? 'judul' : 'nama';
        $q->when($f['kata_kunci'] ?? null, fn ($q, $s) => $q->whereLike($title, '%'.$s.'%'));
        foreach (['status', 'tahun_pelajaran_id', 'semester', 'bidang', 'jenis'] as $key) {
            $valid = match ($key) {
                'status' => true, 'tahun_pelajaran_id' => in_array($kind, ['program', 'akreditasi']), 'semester' => $kind === 'program',
                'bidang' => in_array($kind, ['program', 'mou']), 'jenis' => $kind === 'mitra'
            };
            if ($valid && filled($f[$key] ?? null)) {
                $q->where($key, $f[$key]);
            }
        }
        if (in_array($kind, ['program', 'program-komite']) && $r->boolean('terlambat')) {
            $q->whereIn('status', ['rencana', 'berjalan'])->whereDate('tanggal_selesai', '<', today());
        }
        if ($kind === 'mou' && ! empty($f['masa_berlaku'])) {
            $q->denganStatusBerlaku($f['masa_berlaku']);
        }
        match ($kind) {
            'program' => $q->with('tahunPelajaran')->withCount('laporanFinal')->orderBy('tanggal_selesai'),
            'akreditasi' => $q->with('tahunPelajaran')->withCount(['butir', 'butir as terpenuhi_count' => fn ($b) => $b->where('status', 'terpenuhi')])->latest('updated_at'),
            'laporan' => $q->with('program')->latest('tanggal_mulai'),
            'program-komite' => $q->with(['periode', 'penanggungJawab'])->orderBy('tanggal_selesai'),
            'mou' => $q->with($this->data->dokumen($r) ? ['mitra', 'dokumen'] : ['mitra']),
            'butir' => $q->with('portofolio')->orderBy('urutan'),
            'mitra' => $q->orderBy('status')->orderBy('nama'),
            default => $q,
        };
        $page = $q->orderByDesc('id')->paginate($f['per_halaman'] ?? 20, ['*'], 'halaman', $f['halaman'] ?? 1);

        return $this->json($this->mobile->paginasi($page, fn ($m) => $this->data->data($r, $kind, $m)) + ['filter' => $f,
            'dapat_kelola' => $r->user()->memilikiIzin(HumasTahapDuaService::MODUL[$kind][1].'.kelola') && (! $parent || match ($kind) {
                'laporan' => $parent->terbuka(), 'program-komite' => $parent->status !== 'arsip', 'mou' => $parent->status === 'aktif', 'butir' => $parent->status === 'draf',
            }), 'versi_induk' => $parent?->versi]);
    }

    public function show(Request $r)
    {
        $this->akses($r);

        return $this->json($this->data->data($r, $this->kind($r), $this->record($r), true));
    }

    public function referensi(Request $r)
    {
        $this->akses($r);
        $kind = $this->kind($r);
        $class = HumasTahapDuaService::MODUL[$kind][0];
        $f = $r->validate(['record_id' => ['nullable', 'integer', 'min:1'], 'cari_dokumen' => ['nullable', 'string', 'max:120'], 'cari_agenda' => ['nullable', 'string', 'max:120']]);
        $parent = $this->parent($r);
        $selected = ! empty($f['record_id']) ? $class::findOrFail($f['record_id']) : null;
        abort_if($kind === 'butir' && $selected?->dihapus_pada, 404);
        if ($selected && $parent) {
            $key = match ($kind) {
                'laporan' => 'program_kerja_humas_id', 'program-komite' => 'periode_komite_humas_id', 'mou' => 'mitra_humas_id', 'butir' => 'portofolio_akreditasi_humas_id'
            };
            abort_unless($parent->id === $selected->$key, 404);
        }
        $manage = $r->user()->memilikiIzin(HumasTahapDuaService::MODUL[$kind][1].'.kelola');
        if ($parent) {
            $manage = $manage && match ($kind) {
                'laporan' => $parent->terbuka(), 'program-komite' => $parent->status !== 'arsip',
                'butir' => $parent->status === 'draf', 'mou' => $selected || $parent->status === 'aktif',
            };
        }
        if ($selected) {
            $manage = $manage && $this->data->data($r, $kind, $selected)['hak_akses']['dapat_kelola'];
        }
        $doc = $this->data->dokumen($r);
        $agenda = $r->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']);
        $out = ['status' => $class::STATUS, 'dapat_kelola' => $manage, 'dapat_lihat_dokumen' => $doc, 'dapat_unggah' => $r->user()->memilikiIzin('dokumen_humas.kelola'),
            'dapat_pilih_agenda' => $agenda, 'versi_induk' => $parent?->versi];
        if (in_array($kind, ['program', 'akreditasi'])) {
            $out += ['tahun_pelajaran' => TahunPelajaran::latest('nama')->get(['id', 'nama'])->all()];
        }
        if ($kind === 'program') {
            $out += ['semester' => ProgramKerjaHumas::SEMESTER, 'bidang' => ProgramKerjaHumas::BIDANG];
        }
        if ($kind === 'komite') {
            $out += ['jabatan' => PengurusKomiteHumas::JABATAN, 'metode' => ['tanpa' => 'Tanpa SK', 'tetap' => 'Pertahankan SK', 'dokumen' => 'Pilih dari arsip', 'unggah' => 'Unggah SK', 'lepas' => 'Lepas SK']];
        }
        if ($kind === 'mitra') {
            $out += ['jenis' => MitraHumas::JENIS];
        }
        if ($kind === 'mou') {
            $out += ['bidang' => KerjaSamaHumas::BIDANG, 'masa_berlaku' => KerjaSamaHumas::STATUS_BERLAKU, 'pengingat' => array_combine(KerjaSamaHumas::PENGINGAT, array_map(fn ($d) => $d.' hari', KerjaSamaHumas::PENGINGAT))];
        }
        if ($kind === 'program-komite') {
            $out['pengurus'] = $parent->pengurus()->get(['id', 'nama', 'jabatan', 'aktif'])->all();
        }
        if ($doc && $manage) {
            $q = DokumenHumas::where('status', 'aktif')->when($f['cari_dokumen'] ?? null, fn ($q, $s) => $q->whereLike('judul', '%'.$s.'%'));
            if ($kind === 'komite') {
                $q->where('kategori', 'komite');
            }
            if (in_array($kind, ['komite', 'laporan'])) {
                $q->whereIn('tipe_file', PeriodeKomiteHumas::MIME_SK);
            }
            $docs = $q->with(['riwayat' => fn ($q) => $q->limit(1)])->latest('id')->limit(100)->get();
            if ($kind === 'mou' && $selected?->dokumen_humas_id) {
                $docs = $docs->merge(DokumenHumas::with('riwayat')->whereKey($selected->dokumen_humas_id)->get())->unique('id');
            }
            $out['dokumen'] = $docs->map(fn ($d) => ['id' => $d->id, 'judul' => $d->judul, 'id_versi' => $d->riwayat->first()?->id, 'versi' => $d->riwayat->first()?->versi])->values()->all();
        }
        if ($agenda && $manage && in_array($kind, ['laporan', 'mitra', 'program-komite'])) {
            $q = AgendaHumas::where('status', '!=', 'dibatalkan')->when($kind === 'program-komite', fn ($q) => $q->where('jenis', 'komite'))
                ->when($f['cari_agenda'] ?? null, fn ($q, $s) => $q->whereLike('judul', '%'.$s.'%'));
            $items = $q->latest('waktu_mulai')->limit(100)->get(['id', 'judul']);
            if ($kind === 'laporan' && $selected?->agenda_humas_id) {
                $items = $items->merge(AgendaHumas::whereKey($selected->agenda_humas_id)->get(['id', 'judul']))->unique('id');
            }
            $out['agenda'] = $items->values()->all();
        }

        return $this->json($out);
    }

    public function riwayat(Request $r)
    {
        $this->akses($r);
        $m = $this->record($r);
        $p = $this->halaman($r);
        $q = $m->riwayat()->with('pengguna:id,nama');
        if ($this->kind($r) === 'program') {
            $q->whereNull('laporan_pelaksanaan_humas_id');
        }

        return $this->json($this->mobile->paginasi($q->paginate($p['per_halaman'] ?? 20, ['*'], 'halaman', $p['halaman'] ?? 1), fn ($h) => [
            'id' => $h->id, 'aksi' => $h->aksi, 'versi' => $h->versi, 'catatan' => $h->catatan ?? $h->catatan_perubahan,
            'pengguna' => $h->pengguna?->nama, 'created_at' => $h->created_at?->toIso8601String(),
        ]));
    }

    public function kegiatan(Request $r)
    {
        $this->akses($r);
        $this->staff($r, ['agenda_humas.lihat', 'agenda_humas.kelola']);
        $m = $this->record($r);
        $p = $this->halaman($r);
        $kind = $this->kind($r);

        return $this->json($this->mobile->paginasi($m->agenda()->latest('waktu_mulai')->paginate($p['per_halaman'] ?? 20, ['agenda_humas.*'], 'halaman', $p['halaman'] ?? 1), fn ($a) => [
            'id' => $a->id, 'judul' => $a->judul, 'status' => $a->status, 'waktu_mulai' => $a->waktu_mulai?->toIso8601String(),
        ]) + ['versi' => $m->versi, 'dapat_kelola' => $r->user()->memilikiIzin(HumasTahapDuaService::MODUL[$kind][1].'.kelola')
            && ($kind !== 'program-komite' || ($m->periode->status !== 'arsip' && $r->user()->memilikiIzin('agenda_humas.kelola')))
            && ($kind !== 'mitra' || $m->status === 'aktif')]);
    }

    public function mutation(Request $r)
    {
        $kind = $this->kind($r);
        $action = $r->route('aksi_mobile');
        $read = in_array($action, ['cetak', 'berkas']);
        $this->akses($r, ! $read && $action !== 'export');
        if (in_array($action, ['hubungkanAgenda', 'lepasAgenda'])) {
            $this->staff($r, ['agenda_humas.lihat', 'agenda_humas.kelola']);
        }
        if ($action === 'export') {
            $this->staff($r, 'akreditasi_humas.ekspor');
        }
        $parent = $this->parent($r);
        $record = $action === 'store' ? null : $this->record($r);
        $controller = match ($kind) {
            'program' => ProgramKerjaHumasController::class, 'laporan' => LaporanPelaksanaanHumasController::class,
            'komite' => KomiteHumasController::class, 'program-komite' => ProgramKomiteHumasController::class,
            'mitra', 'mou' => KemitraanHumasController::class, 'akreditasi', 'butir' => PortofolioAkreditasiHumasController::class,
        };
        $args = [$r];
        if ($parent) {
            $args[] = $parent;
        }
        if ($record) {
            $args[] = $record;
        }
        $method = $action;
        if ($kind === 'mou') {
            $method = $action.'Mou';
        }
        if ($kind === 'butir') {
            $method = match ($action) {
                'store', 'update' => 'simpanButir', 'destroy' => 'hapusButir', default => $action
            };
        }
        if ($action === 'tindakan') {
            $args[] = $r->route('aksi');
        }
        if (in_array($action, ['lepasBukti', 'berkas']) && in_array($kind, ['laporan', 'butir'])) {
            $args[] = ($kind === 'laporan' ? BuktiLaporanHumas::class : BuktiAkreditasiHumas::class)::findOrFail($r->route('bukti'));
        }
        if ($action === 'berkas' && $kind === 'komite') {
            $args[] = RiwayatDokumenHumas::findOrFail($r->route('bukti'));
        }
        if (in_array($action, ['lepasAgenda', 'lepas'])) {
            $args[] = AgendaHumas::findOrFail($r->route('agenda'));
        }

        return app($controller)->$method(...$args);
    }
}
