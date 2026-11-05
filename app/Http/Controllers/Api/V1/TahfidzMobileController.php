<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KelompokTahfidz;
use App\Models\KelulusanTahfidz;
use App\Models\PertemuanTahfidz;
use App\Models\PublikasiRaporTahfidz;
use App\Models\SurahTahfidz;
use App\Models\TahunPelajaran;
use App\Models\TargetRaporTahfidz;
use App\Models\WisudaTahfidz;
use App\Services\Tahfidz\AksesTahfidz;
use App\Services\Tahfidz\CapaianTahfidzService;
use App\Services\Tahfidz\KelompokTahfidzService;
use App\Services\Tahfidz\RaporTahfidzService;
use App\Services\Tahfidz\RekapTahfidzService;
use App\Services\Tahfidz\SetoranTahfidzService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TahfidzMobileController extends Controller
{
    public function __construct(private readonly AksesTahfidz $akses) {}

    public function index(Request $request)
    {
        $scope = $this->akses->cakupan($request->user());
        $tahun = TahunPelajaran::when(! $this->akses->kelola($request->user()), fn ($q) => $q
            ->whereIn('id', (clone $scope)->select('tahun_pelajaran_id')))->orderByDesc('aktif')->orderByDesc('id')->get();
        $data = $request->validate(['tahun' => ['nullable', 'integer', Rule::in($tahun->modelKeys())],
            'status' => ['nullable', Rule::in(['aktif', 'nonaktif'])], 'cari' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $id = $data['tahun'] ?? $tahun->first()?->id;
        $items = $scope->where('tahun_pelajaran_id', $id)->with(['pembimbing', 'tahunPelajaran'])->withCount('anggotaAktif')
            ->when($data['status'] ?? null, fn ($q, $v) => $q->where('aktif', $v === 'aktif'))
            ->when($data['cari'] ?? null, fn ($q, $v) => $q->where('nama', 'like', '%'.$v.'%'))
            ->orderByDesc('aktif')->orderBy('nama')->orderBy('id')->paginate(20);

        return $this->json(['items' => $items->map(fn ($g) => $this->group($g)), 'tahun' => $tahun->map->only(['id', 'nama', 'aktif']),
            'tahun_id' => $id, 'kelola' => $this->akses->kelola($request->user()), 'paginasi' => $this->pages($items)]);
    }

    public function referensi(Request $request, KelompokTahfidzService $layanan)
    {
        abort_unless($this->akses->lihat($request->user()), 403);
        $kelola = $this->akses->kelola($request->user());

        return $this->json(['surah' => SurahTahfidz::orderBy('nomor')->get(['nomor', 'nama']),
            'tahun' => $kelola ? TahunPelajaran::orderByDesc('aktif')->orderByDesc('id')->get(['id', 'nama']) : [],
            'pembimbing' => $kelola ? $layanan->pembimbing()->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']) : []]);
    }

    public function show(Request $request, int $kelompok, RekapTahfidzService $rekap)
    {
        $g = $this->kelompok($request, $kelompok);
        $data = $rekap->kelompok($g);
        $meetings = $g->pertemuan()->withCount('setoran')->latest('tanggal')->latest('id')->paginate(20);

        return $this->json(['kelompok' => $this->group($g), 'kelola' => $this->akses->kelola($request->user()),
            'catat' => $this->akses->catat($request->user(), $g), 'ringkasan' => ['target' => $data['target'], 'mulai' => $data['sudahMulai'], 'lengkap' => $data['lengkap']],
            'anggota' => $data['baris']->map(fn ($r) => ['id' => $r['anggota']->id, 'siswa_id' => $r['anggota']->siswa_id,
                'nama' => $r['anggota']->siswa->nama_lengkap, 'kelas' => $r['anggota']->siswa->anggotaKelas->first()?->kelas?->nama,
                'sudah' => $r['sudah'], 'setoran' => $r['setoran'], 'terakhir' => $r['terakhir']]),
            'pertemuan' => $meetings->map(fn ($p) => $this->meeting($p)), 'paginasi' => $this->pages($meetings)]);
    }

    public function anggota(Request $request, int $kelompok, KelompokTahfidzService $layanan)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $g = $this->kelompok($request, $kelompok);
        abort_unless($g->aktif, 403);
        $data = $layanan->pilihanAnggota($g);

        return $this->json(['kelompok' => $this->group($g), 'terpilih' => $data['terpilih']->values(),
            'items' => $data['pilihan']->map(fn ($s) => ['id' => $s->id, 'nama' => $s->nama_lengkap,
                'kelas' => $s->anggotaKelas->first()?->kelas?->nama, 'kelompok_lain' => $data['kelompokLain']->get($s->id),
                'dapat_dipilih' => ! $data['kelompokLain']->has($s->id) && ($data['calon']->has($s->id) || $data['terpilih']->contains($s->id))])]);
    }

    public function raporKelompok(Request $request, int $kelompok, RaporTahfidzService $rapor)
    {
        $g = $this->kelompok($request, $kelompok);
        abort_unless($g->aktif || $this->akses->kelola($request->user()), 404);
        $daftar = $rapor->daftar($g);
        if ($daftar->isEmpty() || $daftar->contains(fn ($item) => ! $item['target'])) {
            throw ValidationException::withMessages(['rapor' => 'Lengkapi anggota, kelas, dan target rapor seluruh kelompok sebelum mencetak.']);
        }

        return $this->json(['kelompok' => $this->group($g), 'rapor' => $rapor->dokumenKelompok($g, $daftar)->values()]);
    }

    public function pertemuan(Request $request, int $kelompok, int $pertemuan, SetoranTahfidzService $layanan)
    {
        $g = $this->kelompok($request, $kelompok);
        $p = $g->pertemuan()->withCount('setoran')->findOrFail($pertemuan);
        $catat = ! $p->selesai_pada && $this->akses->catat($request->user(), $g);
        $setoran = $p->setoran()->with(['anggota.siswa', 'surah', 'penilai', 'pertemuan'])->latest('id')->paginate(20);

        return $this->json(['kelompok' => $this->group($g), 'pertemuan' => $this->meeting($p), 'catat' => $catat,
            'peserta' => $catat ? $layanan->peserta($g)->with('siswa')->get()->map(fn ($a) => ['id' => $a->id, 'nama' => $a->siswa->nama_lengkap])->sortBy('nama')->values() : [],
            'surah' => $catat ? SurahTahfidz::orderBy('nomor')->get(['nomor', 'nama']) : [],
            'items' => $setoran->map(fn ($s) => $this->deposit($s)), 'paginasi' => $this->pages($setoran)]);
    }

    public function siswa(Request $request, int $kelompok, int $anggota, RekapTahfidzService $rekap, RaporTahfidzService $rapor, CapaianTahfidzService $capaian)
    {
        $g = $this->kelompok($request, $kelompok);
        $a = $g->anggota()->with('siswa')->findOrFail($anggota);
        $data = $rekap->siswa($g, $a);
        $history = $a->setoran()->whereHas('pertemuan', fn ($q) => $q->where('kelompok_tahfidz_id', $g->id))
            ->with(['surah', 'pertemuan', 'penilai', 'anggota.siswa'])
            ->orderByDesc(PertemuanTahfidz::select('tanggal')->whereColumn('pertemuan_tahfidz.id', 'setoran_tahfidz.pertemuan_tahfidz_id'))
            ->latest('id')->paginate(20);
        $dokumen = $this->akses->dokumen($request->user(), $g, $a) ? $rapor->siswa($g, $a) : ['pesan' => 'Dokumen hanya tersedia untuk anggota aktif yang berwenang.'];
        $terbit = PublikasiRaporTahfidz::where('siswa_id', $a->siswa_id)->where('tahun_pelajaran_id', $g->tahun_pelajaran_id)->whereNotNull('dipublikasikan_pada')->exists();
        $lulus = $this->akses->lihatWisuda($request->user(), $g, $a) ? KelulusanTahfidz::with('wisuda')->where('siswa_id', $a->siswa_id)->first() : null;

        return $this->json(['kelompok' => $this->group($g), 'anggota' => ['id' => $a->id, 'nama' => $a->siswa->nama_lengkap],
            'sudah' => $data['sudah'], 'target' => $data['target'], 'baris' => $this->progress($data['baris']),
            'riwayat' => $history->map(fn ($s) => $this->deposit($s)), 'paginasi' => $this->pages($history),
            'rapor' => $dokumen['dokumen'] ?? null, 'pesan_rapor' => $dokumen['pesan'] ?? null,
            'sidik' => isset($dokumen['dokumen']) ? hash('sha256', json_encode($dokumen['dokumen'], JSON_THROW_ON_ERROR)) : null,
            'dipublikasikan' => $terbit, 'kelola' => $this->akses->kelola($request->user()),
            'dapat_verifikasi' => ! $lulus && $this->akses->verifikasiWisuda($request->user(), $g, $a) && $capaian->jumlahSurah($a->siswa_id) === 37,
            'sertifikat' => $lulus?->diterbitkan_pada ? $this->certificate($lulus) : null, 'terverifikasi' => (bool) $lulus]);
    }

    public function target(Request $request)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $data = $request->validate(['tahun' => ['nullable', 'integer', 'exists:tahun_pelajaran,id']]);
        $tahun = TahunPelajaran::orderByDesc('aktif')->orderByDesc('id')->get(['id', 'nama']);
        $id = $data['tahun'] ?? $tahun->first()?->id;

        return $this->json(['tahun' => $tahun, 'tahun_id' => $id, 'surah' => SurahTahfidz::orderBy('nomor')->get(['nomor', 'nama']),
            'items' => TargetRaporTahfidz::where('tahun_pelajaran_id', $id)->get()->map(fn ($t) => [...$t->only($t->getFillable()), 'tanggal_rapor' => $t->tanggal_rapor->toDateString()])]);
    }

    public function wisuda(Request $request)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $graduates = KelulusanTahfidz::with(['siswa', 'wisuda'])->latest('diverifikasi_pada')->latest('id')->paginate(20);

        return $this->json(['acara' => WisudaTahfidz::withCount('kelulusan')->latest('tanggal')->get()->map(fn ($e) => [
            ...$e->only(['id', 'nama', 'lokasi', 'kepala_sekolah', 'kelulusan_count']), 'tanggal' => $e->tanggal->toDateString()]),
            'items' => $graduates->map(fn ($l) => ['id' => $l->id, 'nama' => $l->siswa->nama_lengkap, 'diterbitkan' => (bool) $l->diterbitkan_pada,
                'dibagikan' => (bool) $l->dibagikan_pada, 'sertifikat' => $l->diterbitkan_pada ? $this->certificate($l) : null]),
            'paginasi' => $this->pages($graduates)]);
    }

    public function anak(Request $request, CapaianTahfidzService $capaian)
    {
        $parent = $this->parent($request);
        $filter = $request->validate(['siswa' => ['nullable', 'integer', 'min:1'], 'riwayat' => ['nullable', 'integer', 'min:1']]);
        $children = $parent->siswa()->orderBy('nama_lengkap')->get(['siswa.id', 'siswa.nama_lengkap']);
        $siswa = isset($filter['siswa']) ? $children->firstWhere('id', (int) $filter['siswa'])
            : ($children->firstWhere('id', $parent->siswa_acuan_username_id) ?? $children->first());
        abort_if(isset($filter['siswa']) && ! $siswa, 404);
        $data = $siswa ? $capaian->perkembanganAnak($siswa->id) : null;

        return $this->json(['anak' => $children->map(fn ($s) => ['id' => $s->id, 'nama' => $s->nama_lengkap]), 'siswa_id' => $siswa?->id,
            'sudah' => $data['sudah'] ?? 0, 'target' => $data['target'] ?? 37, 'baris' => $data ? $this->progress($data['baris']) : [],
            'riwayat' => $data ? $data['riwayat']->map(fn ($s) => ['id' => $s->id, 'surah' => $s->surah->nama, 'nilai' => $s->labelNilai(), 'tanggal' => $s->tanggal_setoran]) : [],
            'paginasi' => $data ? $this->pages($data['riwayat']) : null,
            'rapor' => $siswa ? PublikasiRaporTahfidz::where('siswa_id', $siswa->id)->whereNotNull('dipublikasikan_pada')->latest('dipublikasikan_pada')->get()
                ->map(fn ($r) => ['id' => $r->id, 'tahun' => $r->salinan['tahun'] ?? '-', 'versi' => $r->versi]) : [],
            'sertifikat' => $siswa ? KelulusanTahfidz::where('siswa_id', $siswa->id)->whereNotNull('diterbitkan_pada')->whereNotNull('dibagikan_pada')->get()->map->only(['id', 'nomor_sertifikat']) : []]);
    }

    public function saya(Request $request, CapaianTahfidzService $capaian)
    {
        $akun = $request->user();
        abort_unless($akun->aktif && $akun->akunSiswa(), 403);
        $request->validate([
            'riwayat' => ['nullable', 'integer', 'min:1'],
            'siswa' => ['prohibited'],
            'siswa_id' => ['prohibited'],
            'anggota_tahfidz_id' => ['prohibited'],
        ]);
        // The authenticated identity is the only source of the student ID.
        $siswa = $akun->siswa()->firstOrFail();
        $data = $capaian->perkembanganAnak($siswa->id);

        return $this->json([
            'siswa' => ['id' => $siswa->id, 'nama' => $siswa->nama_lengkap],
            'sudah' => $data['sudah'], 'target' => $data['target'],
            'baris' => $this->progress($data['baris']),
            'riwayat' => $data['riwayat']->map(fn ($s) => ['id' => $s->id,
                'surah' => $s->surah->nama, 'nilai' => $s->labelNilai(), 'tanggal' => $s->tanggal_setoran]),
            'paginasi' => $this->pages($data['riwayat']),
        ]);
    }

    public function raporAnak(Request $request, int $id)
    {
        $ids = $this->parent($request)->siswa()->pluck('siswa.id');
        $r = PublikasiRaporTahfidz::whereIn('siswa_id', $ids)->whereNotNull('dipublikasikan_pada')->findOrFail($id);

        return $this->json(['rapor' => $r->salinan, 'versi' => $r->versi]);
    }

    public function sertifikatAnak(Request $request, int $id)
    {
        $ids = $this->parent($request)->siswa()->pluck('siswa.id');
        $r = KelulusanTahfidz::whereIn('siswa_id', $ids)->whereNotNull('diterbitkan_pada')->whereNotNull('dibagikan_pada')->with('wisuda')->findOrFail($id);

        return $this->json(['sertifikat' => $this->certificate($r)]);
    }

    private function parent(Request $request)
    {
        abort_unless($request->user()->aktif && $request->user()->akunOrangTua(), 403);

        return $request->user()->orangTuaWali()->firstOrFail();
    }

    private function kelompok(Request $request, int $id): KelompokTahfidz
    {
        return $this->akses->cakupan($request->user())->with(['tahunPelajaran', 'pembimbing'])->withCount('anggotaAktif')->findOrFail($id);
    }

    private function group($g): array
    {
        return ['id' => $g->id, 'nama' => $g->nama, 'aktif' => $g->aktif, 'versi' => $g->versi,
            'tahun_id' => $g->tahun_pelajaran_id, 'tahun' => $g->tahunPelajaran->nama,
            'pembimbing_id' => $g->pembimbing_pegawai_id, 'pembimbing' => $g->pembimbing->nama_lengkap,
            'jumlah_anggota' => (int) ($g->anggota_aktif_count ?? 0)];
    }

    private function meeting($p): array
    {
        return ['id' => $p->id, 'tanggal' => $p->tanggal->toDateString(), 'selesai' => (bool) $p->selesai_pada, 'jumlah_setoran' => (int) $p->setoran_count];
    }

    private function deposit($s): array
    {
        return ['id' => $s->id, 'nama' => $s->anggota->siswa->nama_lengkap, 'surah' => $s->surah->nama,
            'nilai' => $s->labelNilai(), 'catatan' => $s->catatan, 'tanggal' => $s->pertemuan?->tanggal?->toDateString(), 'penilai' => $s->penilai?->nama];
    }

    private function progress($rows)
    {
        return $rows->map(fn ($r) => ['nomor' => $r['surah']->nomor, 'nama' => $r['surah']->nama,
            'nilai' => $r['terbaru']?->labelNilai(), 'tanggal' => $r['terbaru']?->tanggal_setoran])->values();
    }

    private function certificate($l): array
    {
        return ['nama' => $l->nama_penerima, 'nomor' => $l->nomor_sertifikat, 'pembimbing' => $l->nama_pembimbing,
            'acara' => $l->wisuda?->nama, 'tanggal' => $l->wisuda?->tanggal?->toDateString(), 'lokasi' => $l->wisuda?->lokasi, 'kepala_sekolah' => $l->wisuda?->kepala_sekolah];
    }

    private function pages($p): array
    {
        return ['halaman' => $p->currentPage(), 'terakhir' => $p->lastPage(), 'total' => $p->total()];
    }

    private function json(array $data)
    {
        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
