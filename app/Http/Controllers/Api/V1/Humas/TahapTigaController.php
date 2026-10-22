<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Http\Controllers\AlumniHumasController;
use App\Http\Controllers\AsetPromosiHumasController;
use App\Http\Controllers\KlipingBeritaHumasController;
use App\Http\Controllers\MediaResmiHumasController;
use App\Http\Controllers\PrestasiSekolahController;
use App\Models\AlumniHumas;
use App\Models\AsetPromosiHumas;
use App\Models\DokumenHumas;
use App\Models\KlipingBeritaHumas;
use App\Models\MediaResmiHumas;
use App\Models\PrestasiSekolah;
use App\Models\PublikasiHumas;
use App\Models\RiwayatDokumenHumas;
use App\Models\TahunPelajaran;
use App\Services\Mobile\HumasMobileService;
use App\Support\PenulisExcelAlumniHumas;
use App\Support\PenulisExcelPrestasiSekolah;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TahapTigaController extends HumasController
{
    public const MODUL = [
        'aset' => [AsetPromosiHumas::class, 'aset_promosi_humas', AsetPromosiHumasController::class],
        'media' => [MediaResmiHumas::class, 'media_resmi_humas', MediaResmiHumasController::class],
        'kliping' => [KlipingBeritaHumas::class, 'kliping_berita_humas', KlipingBeritaHumasController::class],
        'prestasi' => [PrestasiSekolah::class, 'prestasi_sekolah', PrestasiSekolahController::class],
        'alumni' => [AlumniHumas::class, 'alumni_humas', AlumniHumasController::class],
    ];

    public function __construct(private readonly HumasMobileService $mobile) {}

    private function kind(Request $r): string
    {
        return $r->route('jenis_mobile');
    }

    private function akses(Request $r, bool $write = false): void
    {
        $p = self::MODUL[$this->kind($r)][1];
        $this->staff($r, $write ? $p.'.kelola' : [$p.'.lihat', $p.'.kelola']);
    }

    private function record(Request $r): Model
    {
        return (self::MODUL[$this->kind($r)][0])::findOrFail($r->route('id'));
    }

    private function dokumen(Request $r): bool
    {
        return $r->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
    }

    private function data(Request $r, Model $m, bool $detail = false): array
    {
        $kind = $this->kind($r);
        $manage = $r->user()->memilikiIzin(self::MODUL[$kind][1].'.kelola');
        $columns = match ($kind) {
            'aset' => ['nama', 'kategori', 'tanggal_aset', 'deskripsi', 'kata_kunci', 'kredit', 'ketentuan_penggunaan', 'sumber', 'tautan', 'status'],
            'alumni' => [...AlumniHumas::PUBLIK, ...($detail && $manage ? AlumniHumas::PRIVAT : [])],
            default => $m::KOLOM,
        };
        $out = $m->only(['id', 'versi', ...$columns]);
        foreach ($out as $key => $value) {
            if ($value instanceof CarbonInterface) {
                $out[$key] = $value->format('Y-m-d');
            }
        }
        $out['judul'] = $m->nama ?? $m->judul ?? $m->nama_kegiatan ?? $m->nama_lengkap;
        $out['status_label'] = $m::STATUS[$m->status] ?? $m->status;
        $out['hak_akses'] = ['dapat_kelola' => $manage, 'dapat_lihat_dokumen' => $this->dokumen($r)];
        foreach (['kategori' => 'KATEGORI', 'jenis' => 'JENIS', 'topik' => 'TOPIK', 'tingkat' => 'TINGKAT', 'perolehan' => 'PEROLEHAN', 'penerima' => 'PENERIMA', 'bentuk' => 'BENTUK', 'status_penelusuran' => 'PENELUSURAN', 'jenis_sekolah' => 'SEKOLAH'] as $key => $constant) {
            if (defined($m::class.'::'.$constant) && isset($out[$key])) {
                $out[$key.'_label'] = constant($m::class.'::'.$constant)[$out[$key]] ?? $out[$key];
            }
        }
        if ($kind === 'prestasi') {
            $out['peserta'] = $m->peserta->map(fn ($p) => $p->only(['siswa_id', 'pegawai_id', 'nama', 'kelas']))->all();
            $out['tahun_pelajaran'] = $m->tahunPelajaran?->nama;
            $out['diverifikasi_pada'] = $m->diverifikasi_pada?->toIso8601String();
        }
        if ($detail && in_array($kind, ['aset', 'kliping', 'prestasi']) && $this->dokumen($r)) {
            $out['berkas'] = $m->berkas ? $m->berkas->only(['id', 'dokumen_humas_id', 'versi', 'nama_file_asli', 'tipe_file', 'ukuran_file']) : null;
        }

        return $out;
    }

    public function index(Request $r)
    {
        $this->akses($r);
        $this->halaman($r);
        // Share web filters and statistics without rendering or returning its models.
        $view = app(self::MODUL[$this->kind($r)][2])->index($r);
        $values = is_array($view) ? $view : (method_exists($view, 'getOriginalContent') ? $view->getOriginalContent() : $view)->getData();
        $out = $this->mobile->paginasi($values['daftar'], fn ($m) => $this->data($r, $m));

        return $this->json($out + ['filter' => $values['filter'], 'statistik' => collect($values['statistik'])->map(fn ($v) => is_numeric($v) ? $v + 0 : $v),
            'dapat_kelola' => $r->user()->memilikiIzin(self::MODUL[$this->kind($r)][1].'.kelola')]);
    }

    public function show(Request $r)
    {
        $this->akses($r);

        return $this->json($this->data($r, $this->record($r), true));
    }

    public function referensi(Request $r)
    {
        $this->akses($r);
        $kind = $this->kind($r);
        $class = self::MODUL[$kind][0];
        $f = $r->validate(['cari_dokumen' => ['nullable', 'string', 'max:120']]);
        $manage = $r->user()->memilikiIzin(self::MODUL[$kind][1].'.kelola');
        $out = ['status' => $class::STATUS, 'dapat_kelola' => $manage, 'dapat_lihat_dokumen' => $this->dokumen($r),
            'dapat_unggah' => $r->user()->memilikiIzin('dokumen_humas.kelola')];
        foreach (['KATEGORI' => 'kategori', 'JENIS' => 'jenis', 'TOPIK' => 'topik', 'TINGKAT' => 'tingkat', 'PEROLEHAN' => 'perolehan', 'PENERIMA' => 'penerima', 'BENTUK' => 'bentuk', 'PENELUSURAN' => 'status_penelusuran', 'SEKOLAH' => 'jenis_sekolah'] as $constant => $key) {
            if (defined($class.'::'.$constant)) {
                $out[$key] = constant($class.'::'.$constant);
            }
        }
        if ($kind === 'prestasi') {
            $out['tahun_pelajaran'] = TahunPelajaran::latest('tanggal_mulai')->get(['id', 'nama']);
            $out['tahun'] = PrestasiSekolah::get(['tanggal_prestasi'])->map(fn ($p) => $p->tanggal_prestasi->year)->unique()->sortDesc()->mapWithKeys(fn ($y) => [(string) $y => (string) $y]);
        }
        if ($kind === 'alumni') {
            $out['tahun_lulus'] = AlumniHumas::distinct()->orderByDesc('tahun_lulus')->pluck('tahun_lulus')->mapWithKeys(fn ($y) => [(string) $y => (string) $y]);
        }
        if (in_array($kind, ['aset', 'kliping', 'prestasi']) && $manage && $this->dokumen($r)) {
            $out['dokumen'] = DokumenHumas::where('status', 'aktif')->whereHas('riwayat')
                ->when($kind !== 'aset', fn ($q) => $q->whereIn('tipe_file', KlipingBeritaHumas::MIME_BUKTI))
                ->when($f['cari_dokumen'] ?? null, fn ($q, $s) => $q->whereLike('judul', '%'.$s.'%'))
                ->latest('id')->limit(50)->get(['id', 'judul']);
        }

        return $this->json($out);
    }

    public function riwayat(Request $r)
    {
        $this->akses($r);
        $p = $this->halaman($r);
        $private = $this->kind($r) !== 'alumni' || $r->user()->memilikiIzin('alumni_humas.kelola');
        $q = $this->record($r)->riwayat()->with('pengguna:id,nama');

        return $this->json($this->mobile->paginasi($q->paginate($p['per_halaman'] ?? 20, ['*'], 'halaman', $p['halaman'] ?? 1), fn ($h) => [
            'id' => $h->id, 'versi' => $h->versi, 'aksi' => $h->aksi, 'catatan' => $private ? ($h->catatan ?? $h->catatan_perubahan) : null,
            'pengguna' => $h->pengguna?->nama, 'created_at' => $h->created_at?->toIso8601String(),
        ]));
    }

    public function pemakaian(Request $r)
    {
        $this->akses($r);
        $this->staff($r, PublikasiHumas::IZIN_LIHAT);
        $p = $this->halaman($r);

        return $this->json($this->mobile->paginasi($this->record($r)->pemakaian()->with('publikasi')->paginate($p['per_halaman'] ?? 20, ['*'], 'halaman', $p['halaman'] ?? 1), fn ($m) => [
            'id' => $m->id, 'publikasi_id' => $m->publikasi?->id, 'judul' => $m->publikasi?->judul, 'status' => $m->publikasi?->status,
        ]));
    }

    public function mutation(Request $r)
    {
        $action = $r->route('aksi_mobile');
        $this->akses($r, in_array($action, ['store', 'update', 'pilihan', 'siswa']));
        if ($action === 'berkas') {
            $this->staff($r, ['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        }
        $controller = app(self::MODUL[$this->kind($r)][2]);
        $args = [$r];
        if (in_array($action, ['update', 'berkas'])) {
            $args[] = $this->record($r);
        }
        if ($action === 'berkas') {
            $args[] = RiwayatDokumenHumas::findOrFail($r->route('bukti'));
        }
        if ($action === 'export') {
            $args[] = app($this->kind($r) === 'prestasi' ? PenulisExcelPrestasiSekolah::class : PenulisExcelAlumniHumas::class);
        }

        $response = $controller->$action(...$args);
        if (in_array($action, ['pilihan', 'siswa'])) {
            $data = $response->getData(true);

            return $this->json(['items' => $data['data'], 'next_page' => $data['next_page']]);
        }

        return $response;
    }
}
