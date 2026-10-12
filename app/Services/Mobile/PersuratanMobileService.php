<?php

namespace App\Services\Mobile;

use App\Models\Pengguna;
use App\Models\SuratTu;
use App\Models\TindakLanjutSuratTu;
use App\Services\Persuratan\PersuratanService;
use App\Services\Persuratan\TindakLanjutSuratService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersuratanMobileService
{
    public function __construct(private PersuratanService $suratService, private TindakLanjutSuratService $tindakService) {}

    public function daftarSurat(Request $request, bool $pribadi = false): array
    {
        $akun = $request->user();
        $query = $pribadi ? $this->suratService->penugasanSaya($akun) : $this->suratService->arsip($akun);
        $status = $pribadi ? ['disahkan', 'dikirim', 'dibatalkan'] : array_keys(SuratTu::STATUS);
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in($status)],
            'jenis' => [$pribadi ? 'prohibited' : 'nullable', Rule::in(array_keys(SuratTu::JENIS))],
            'sifat' => [$pribadi ? 'prohibited' : 'nullable', Rule::in(array_keys(SuratTu::SIFAT))],
            'tahun' => [$pribadi ? 'prohibited' : 'nullable', 'integer', 'min:2000', 'max:2100'],
            'pemeriksaan_saya' => [$pribadi ? 'prohibited' : 'nullable', 'boolean'],
            'belum_dibaca' => [$pribadi ? 'nullable' : 'prohibited', 'boolean'],
        ]) + $this->halaman($request);
        $jumlah = (clone $query)->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');
        $query->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(function ($q) use ($kata, $pribadi) {
            $q->whereLike('nomor_surat', '%'.$kata.'%')->orWhereLike('perihal', '%'.$kata.'%');
            if (! $pribadi) {
                $q->orWhereLike('nomor_agenda', '%'.$kata.'%')->orWhereLike('pihak', '%'.$kata.'%')
                    ->orWhereHas('penugasan', fn ($q) => $q->whereLike('nama_pegawai', '%'.$kata.'%'));
            }
        }))->when($filter['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
        if ($pribadi) {
            $query->with(['penugasan' => fn ($q) => $q->where('pegawai_id', $akun->pegawai_id)])
                ->when($filter['belum_dibaca'] ?? false, fn ($q) => $q->whereIn('status', ['disahkan', 'dikirim'])
                    ->whereHas('penugasan', fn ($q) => $q->where('pegawai_id', $akun->pegawai_id)->whereNull('dibaca_pada')))
                ->latest('disahkan_pada');
        } else {
            $query->when($filter['jenis'] ?? null, fn ($q, $v) => $q->where('jenis', $v))
                ->when($filter['sifat'] ?? null, fn ($q, $v) => $q->where('sifat', $v))
                ->when($filter['tahun'] ?? null, fn ($q, $v) => $q->whereYear('tanggal_surat', $v))
                ->when($filter['pemeriksaan_saya'] ?? false, fn ($q) => $q->where('pengesah_pengguna_id', $akun->id)->where('status', 'diajukan'))
                ->withCount(['penugasan', 'tindakLanjut', 'tindakLanjut as selesai_count' => fn ($q) => $q->where('status', 'selesai'),
                    'penugasan as dibaca_count' => fn ($q) => $q->whereNotNull('dibaca_pada')])->latest('tanggal_surat');
        }

        return $this->paginasi($query->latest('id')->paginate($filter['per_halaman'] ?? 20, ['*'], 'halaman', $filter['halaman'] ?? 1),
            fn ($s) => $this->surat($s, $pribadi)) + [
                'filter' => $filter, 'statistik' => array_replace(array_fill_keys($status, 0), $jumlah->map(fn ($v) => (int) $v)->all()),
                'statistik_mengikuti_filter' => false, 'hak_akses' => ['dapat_kelola' => ! $pribadi && $akun->memilikiIzin('surat.kelola')],
                'referensi' => ['status' => array_intersect_key(SuratTu::STATUS, array_flip($status))],
            ];
    }

    public function referensi(Pengguna $akun): array
    {
        $this->suratService->arsip($akun);
        $kelola = $akun->memilikiIzin('surat.kelola');

        return ['jenis' => SuratTu::JENIS, 'status' => SuratTu::STATUS,
            'sifat' => array_filter(SuratTu::SIFAT, fn ($k) => $k !== 'rahasia' || $akun->memilikiIzin('surat.rahasia'), ARRAY_FILTER_USE_KEY),
            'batas_berkas_mb' => 10, 'maksimal_lampiran' => 5, 'maksimal_penerima' => 100,
            'format_utama' => ['pdf', 'jpg', 'jpeg', 'png'], 'format_lampiran' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
            'pegawai' => $kelola ? $this->suratService->pegawai()->map(fn ($p) => $p->only(['id', 'nama_lengkap', 'jabatan_utama']))->values()->all() : [],
            'pengesah' => $kelola ? $this->suratService->pengesah()->where('id', '<>', $akun->id)
                ->map(fn ($p) => ['id' => $p->id, 'nama' => $p->nama, 'dapat_surat_rahasia' => $p->memilikiIzin('surat.rahasia')])->values()->all() : [],
            'hak_akses' => ['dapat_kelola' => $kelola, 'dapat_sahkan' => $akun->memilikiIzin('surat.sahkan'), 'dapat_disposisi' => $akun->memilikiIzin('surat.disposisi')]];
    }

    public function detailSurat(Pengguna $akun, SuratTu $surat, bool $pribadi = false): array
    {
        if ($pribadi) {
            $this->suratService->penugasanSaya($akun)->findOrFail($surat->id);
            $surat->load(['berkas' => fn ($q) => $q->where('aktif', true), 'pengesah:id,nama',
                'penugasan' => fn ($q) => $q->where('pegawai_id', $akun->pegawai_id),
                'tindakLanjut' => fn ($q) => $q->where('pegawai_id', $akun->pegawai_id)]);
        } else {
            $this->suratService->pastikanArsip($akun, $surat);
            $surat->load(['berkas', 'pengesah:id,nama', 'penugasan', 'tindakLanjut', 'riwayat.pengguna:id,nama']);
        }
        $data = $this->surat($surat, $pribadi) + $surat->only(['keterangan', 'uraian_tugas', 'catatan_status', 'pengesah_pengguna_id']);
        foreach (['diajukan_pada', 'disahkan_pada', 'dikirim_pada', 'dibatalkan_pada'] as $kolom) {
            $data[$kolom] = $surat->{$kolom}?->toIso8601String();
        }
        $data['pengesah'] = $surat->pengesah ? $surat->pengesah->only(['id', 'nama']) : null;
        $data['berkas'] = $surat->berkas->map(fn ($b) => $this->berkas($b, route($pribadi ? 'api.v1.surat-saya.berkas' : 'api.v1.persuratan.berkas', [$surat->id, $b->id]))
            + ['jenis' => $b->jenis, 'aktif' => (bool) $b->aktif])->values()->all();
        $data['penugasan'] = $surat->penugasan->map(fn ($p) => ['id' => $p->id, 'pegawai_id' => $p->pegawai_id,
            'nama' => $p->nama_pegawai, 'dibaca_pada' => $p->dibaca_pada?->toIso8601String()])->values()->all();
        $data['tindak_lanjut'] = $surat->tindakLanjut->map(fn ($t) => ['id' => $t->id, 'pegawai_id' => $t->pegawai_id, 'nama' => $t->nama_pegawai,
            'status' => $t->status, 'status_label' => TindakLanjutSuratTu::STATUS[$t->status], 'batas_tanggal' => $t->batas_tanggal->toDateString(),
            'url' => route('api.v1.tindak-lanjut-surat.show', $t->id)])->values()->all();
        $data['hak_akses'] = $this->hakSurat($akun, $surat, $pribadi);
        if (! $pribadi) {
            $data['riwayat'] = $this->riwayat($surat->riwayat);
            $terkait = $this->suratService->arsip($akun)->where(fn ($q) => $q->where('revisi_dari_id', $surat->id)
                ->when($surat->revisi_dari_id, fn ($q) => $q->orWhere('id', $surat->revisi_dari_id)))->get();
            $data['surat_terkait'] = $terkait->map(fn ($s) => $this->surat($s))->values()->all();
            if ($data['hak_akses']['dapat_disposisi']) {
                $data['penerima_disposisi'] = $this->suratService->pegawai()->whereNotIn('id', $surat->tindakLanjut->pluck('pegawai_id')->push($akun->pegawai_id))
                    ->map(fn ($p) => $p->only(['id', 'nama_lengkap', 'jabatan_utama']))->values()->all();
            }
        }

        return $data;
    }

    private function surat(SuratTu $surat, bool $pribadi = false): array
    {
        $data = $surat->only(['id', 'jenis', 'nomor_surat', 'nomor_agenda', 'perihal', 'pihak', 'sifat', 'status', 'versi', 'tempat_tugas']);
        $data += ['jenis_label' => SuratTu::JENIS[$surat->jenis], 'sifat_label' => SuratTu::SIFAT[$surat->sifat], 'status_label' => SuratTu::STATUS[$surat->status],
            'url' => route($pribadi ? 'api.v1.surat-saya.show' : 'api.v1.persuratan.show', $surat->id)];
        foreach (['tanggal_surat', 'tanggal_diterima', 'mulai_tugas', 'selesai_tugas'] as $kolom) {
            $data[$kolom] = $surat->{$kolom}?->toDateString();
        }
        if ($pribadi) {
            $data['dibaca_pada'] = $surat->penugasan->first()?->dibaca_pada?->toIso8601String();
        } elseif (isset($surat->penugasan_count)) {
            $data['jumlah'] = ['penerima_tugas' => (int) $surat->penugasan_count, 'sudah_membaca' => (int) $surat->dibaca_count,
                'tindak_lanjut' => (int) $surat->tindak_lanjut_count, 'laporan_selesai' => (int) $surat->selesai_count];
        }

        return $data;
    }

    private function hakSurat(Pengguna $akun, SuratTu $s, bool $pribadi): array
    {
        $kelola = ! $pribadi && $akun->memilikiIzin('surat.kelola');
        $pemeriksa = ! $pribadi && $akun->memilikiIzin('surat.sahkan') && $s->pengesah_pengguna_id === $akun->id;
        $belumDiganti = ! $pribadi && $s->disahkan_pada && ! $s->revisi()->whereNotNull('disahkan_pada')->exists();

        return ['dapat_edit' => $kelola && $s->dapatDiedit(), 'dapat_revisi' => $kelola && (bool) $belumDiganti && $s->jenis !== 'masuk',
            'dapat_ajukan' => $kelola && $s->jenis !== 'masuk' && in_array($s->status, ['draf', 'dikembalikan']) && $s->pengesah_pengguna_id !== $akun->id,
            'dapat_sahkan' => $pemeriksa && $s->status === 'diajukan' && $s->dibuat_oleh_pengguna_id !== $akun->id,
            'dapat_kembalikan' => $pemeriksa && $s->status === 'diajukan', 'dapat_tarik' => $kelola && $s->status === 'diajukan',
            'dapat_kirim' => $kelola && $s->status === 'disahkan',
            'dapat_batal' => ! $pribadi && $s->status !== 'dibatalkan' && ($s->disahkan_pada ? ($pemeriksa || $akun->administrator()) : $kelola),
            'dapat_disposisi' => ! $pribadi && $akun->memilikiIzin('surat.disposisi') && $s->jenis === 'masuk' && $s->status === 'tercatat',
            'dapat_konfirmasi_baca' => $pribadi && in_array($s->status, ['disahkan', 'dikirim']) && ! $s->penugasan->first()?->dibaca_pada];
    }

    public function daftarTindak(Request $request, bool $monitoring = false): array
    {
        $akun = $request->user();
        $query = $this->tindakService->daftar($akun, $monitoring)->with('surat', 'pemeriksa:id,nama');
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(array_keys(TindakLanjutSuratTu::STATUS))],
            'jenis' => ['nullable', Rule::in(['disposisi', 'tugas'])], 'terlambat' => ['nullable', 'boolean'],
            'pemeriksaan_saya' => [$monitoring ? 'nullable' : 'prohibited', 'boolean']]) + $this->halaman($request);
        $query->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('nama_pegawai', '%'.$kata.'%')
            ->orWhereHas('surat', fn ($q) => $q->whereLike('nomor_surat', '%'.$kata.'%')->orWhereLike('perihal', '%'.$kata.'%'))))
            ->when($filter['jenis'] ?? null, fn ($q, $v) => $q->where('jenis', $v))
            ->when($filter['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filter['terlambat'] ?? false, fn ($q) => $q->whereDate('batas_tanggal', '<', today())
                ->whereNotIn('status', ['selesai', 'dibatalkan', 'menunggu_pemeriksaan'])->whereHas('surat', fn ($q) => $q->where('status', '<>', 'dibatalkan')))
            ->when($filter['pemeriksaan_saya'] ?? false, fn ($q) => $q->where('pemeriksa_pengguna_id', $akun->id)->where('status', 'menunggu_pemeriksaan'));
        $jumlah = (clone $query)->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');

        return $this->paginasi($query->orderBy('batas_tanggal')->orderBy('id')->paginate($filter['per_halaman'] ?? 20, ['*'], 'halaman', $filter['halaman'] ?? 1),
            fn ($t) => $this->tindak($t)) + ['filter' => $filter, 'statistik' => array_replace(array_fill_keys(array_keys(TindakLanjutSuratTu::STATUS), 0), $jumlah->map(fn ($v) => (int) $v)->all()),
                'statistik_mengikuti_filter' => true, 'referensi' => ['status' => TindakLanjutSuratTu::STATUS, 'jenis' => ['disposisi' => 'Disposisi surat masuk', 'tugas' => 'Pelaksanaan surat tugas']]];
    }

    public function detailTindak(Pengguna $akun, TindakLanjutSuratTu $tugas): array
    {
        $arsip = $this->tindakService->pastikanAkses($akun, $tugas);
        $tugas->load(['surat.berkas' => fn ($q) => $q->where('aktif', true), 'pemeriksa:id,nama', 'laporan.bukti', 'riwayat.pengguna:id,nama']);
        $milik = $akun->akunPegawai() && $akun->pegawai_id === $tugas->pegawai_id;
        $aktif = $tugas->surat->status !== 'dibatalkan' && ! in_array($tugas->status, ['selesai', 'dibatalkan']);
        $pemeriksa = $this->tindakService->dapatMemeriksa($akun, $tugas);
        $pengatur = $this->tindakService->dapatMengatur($akun, $tugas);
        $data = $this->tindak($tugas) + ['instruksi' => $tugas->instruksi, 'riwayat' => $this->riwayat($tugas->riwayat)];
        $data['berkas_surat'] = $tugas->surat->berkas->map(fn ($b) => $this->berkas($b, route('api.v1.tindak-lanjut-surat.berkas', [$tugas->id, $b->id])))->values()->all();
        $data['laporan'] = $tugas->laporan->map(fn ($l) => $l->only(['id', 'nomor', 'hasil', 'keputusan', 'catatan_pemeriksa']) + [
            'disimpan_pada' => $l->created_at?->toIso8601String(), 'diajukan_pada' => $l->diajukan_pada?->toIso8601String(),
            'diperiksa_pada' => $l->diperiksa_pada?->toIso8601String(),
            'bukti' => $l->bukti->map(fn ($b) => $this->berkas($b, route('api.v1.tindak-lanjut-surat.bukti', [$tugas->id, $b->id])))->values()->all(),
        ])->values()->all();
        $data['hak_akses'] = ['akses_arsip' => $arsip, 'milik_saya' => $milik,
            'dapat_baca' => $milik && $aktif && ! $tugas->dibaca_pada, 'dapat_mulai' => $milik && $aktif && $tugas->status === 'belum_mulai',
            'dapat_laporkan' => $milik && $tugas->dapatDilaporkan(),
            'dapat_periksa' => $arsip && $pemeriksa && $aktif && $tugas->status === 'menunggu_pemeriksaan',
            'dapat_ubah' => $arsip && $pengatur && $aktif && $tugas->status !== 'menunggu_pemeriksaan',
            'dapat_batal' => $arsip && $pengatur && $aktif];
        if ($data['hak_akses']['dapat_ubah']) {
            $data['calon_pemeriksa'] = $this->tindakService->calonPemeriksa($tugas)->map(fn ($p) => $p->only(['id', 'nama']))->values()->all();
        }

        return $data;
    }

    private function tindak(TindakLanjutSuratTu $t): array
    {
        return $t->only(['id', 'jenis', 'pegawai_id', 'nama_pegawai', 'status', 'versi']) + [
            'surat' => $t->surat->only(['id', 'nomor_surat', 'perihal', 'status']),
            'status_label' => TindakLanjutSuratTu::STATUS[$t->status], 'batas_tanggal' => $t->batas_tanggal->toDateString(),
            'dibaca_pada' => $t->dibaca_pada?->toIso8601String(), 'selesai_pada' => $t->selesai_pada?->toIso8601String(), 'terlambat' => $t->terlambat(),
            'pemeriksa' => $t->pemeriksa ? $t->pemeriksa->only(['id', 'nama']) : null,
            'url' => route('api.v1.tindak-lanjut-surat.show', $t->id)];
    }

    private function berkas($b, string $url): array
    {
        return ['id' => $b->id, 'nama' => $b->nama_file_asli, 'tipe' => $b->tipe_file, 'ukuran_byte' => (int) $b->ukuran_file, 'url' => $url];
    }

    private function riwayat($items): array
    {
        // Raw audit snapshots may contain unrelated recipients or private file paths.
        return $items->map(fn ($r) => $r->only(['id', 'aksi', 'versi', 'catatan']) + [
            'pengguna' => $r->pengguna ? $r->pengguna->only(['id', 'nama']) : null, 'waktu' => $r->created_at?->toIso8601String(),
        ])->values()->all();
    }

    private function halaman(Request $request): array
    {
        return $request->validate(['halaman' => ['nullable', 'integer', 'min:1'], 'per_halaman' => ['nullable', 'integer', 'min:1', 'max:50']]);
    }

    private function paginasi(LengthAwarePaginator $p, callable $map): array
    {
        return ['items' => collect($p->items())->map($map)->values()->all(), 'paginasi' => [
            'halaman' => $p->currentPage(), 'halaman_terakhir' => $p->lastPage(), 'per_halaman' => $p->perPage(),
            'total' => $p->total(), 'ada_halaman_berikutnya' => $p->hasMorePages()]];
    }
}
