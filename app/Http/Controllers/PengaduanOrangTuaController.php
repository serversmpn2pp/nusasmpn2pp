<?php

namespace App\Http\Controllers;

use App\Models\LampiranPengaduanHumas;
use App\Models\OrangTuaWali;
use App\Models\PengaduanHumas;
use App\Services\Humas\KelolaPengaduanHumasService;
use App\Services\Humas\LampiranPengaduanHumasService;
use App\Services\Humas\PengaduanOrangTuaHumasService;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PengaduanOrangTuaController extends Controller
{
    public function __construct(private readonly KelolaPengaduanHumasService $kelola, private readonly LampiranPengaduanHumasService $berkas, private readonly NotifikasiPenggunaService $notifikasi) {}

    public function index(Request $request)
    {
        $this->wali($request);
        $filter = $request->validate(['status' => ['nullable', Rule::in(['aktif', 'selesai', 'semua'])], 'kata_kunci' => ['nullable', 'string', 'max:120']]);
        $scope = $this->milik($request);
        $query = (clone $scope)->select(['id', 'judul', 'jenis', 'kategori', 'status', 'tanggal_diterima', 'created_at', 'updated_at']);
        if (($filter['status'] ?? 'semua') === 'aktif') {
            $query->whereIn('status', PengaduanHumas::AKTIF);
        } elseif (($filter['status'] ?? null) === 'selesai') {
            $query->whereIn('status', ['selesai', 'ditutup']);
        }
        $query->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->whereLike('judul', '%'.$kata.'%'));

        return $this->halaman('index', ['daftar' => $query->latest('updated_at')->orderByDesc('id')->paginate(15)->withQueryString(), 'filter' => $filter,
            'statistik' => ['total' => (clone $scope)->count(), 'aktif' => (clone $scope)->whereIn('status', PengaduanHumas::AKTIF)->count(), 'selesai' => (clone $scope)->whereIn('status', ['selesai', 'ditutup'])->count()]]);
    }

    public function create(Request $request)
    {
        $wali = $this->wali($request);

        return $this->halaman('form', ['wali' => $wali, 'tokenPembuatan' => (string) Str::uuid()]);
    }

    public function store(Request $request)
    {
        $tiket = app(PengaduanOrangTuaHumasService::class)->store($request);

        return $this->selesai($request, $tiket, 'Laporan berhasil dikirim kepada Humas.');
    }

    public function show(Request $request, int $tiket)
    {
        $this->wali($request);
        $laporan = $this->milik($request)->select(['id', 'judul', 'jenis', 'kategori', 'isi', 'status', 'tanggal_diterima', 'rahasiakan_identitas', 'versi', 'created_at'])->findOrFail($tiket);

        return $this->halaman('show', ['tiket' => $laporan, 'lampiran' => $laporan->lampiran()->where('asal', 'orang_tua')->get(['id', 'pengaduan_humas_id', 'nama_file_asli']),
            'pesan' => $laporan->pesan()->latest('id')->paginate(15, ['id', 'asal', 'isi', 'created_at']), 'tokenPengiriman' => (string) Str::uuid()]);
    }

    public function informasi(Request $request, int $tiket)
    {
        $laporan = app(PengaduanOrangTuaHumasService::class)->informasi($request, $tiket);

        return $this->selesai($request, $laporan, 'Informasi tambahan berhasil dikirim.');
    }

    public function lampiran(Request $request, int $tiket, int $lampiran)
    {
        $this->wali($request);
        $laporan = $this->milik($request)->findOrFail($tiket);
        $file = LampiranPengaduanHumas::where('pengaduan_humas_id', $laporan->id)->where('asal', 'orang_tua')->findOrFail($lampiran);
        abort_unless(Storage::disk('local')->exists($file->lokasi_file), 404);
        $headers = ['Content-Type' => $file->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store, max-age=0'];

        return $request->boolean('unduh') ? Storage::disk('local')->download($file->lokasi_file, $file->nama_file_asli, $headers)
            : Storage::disk('local')->response($file->lokasi_file, $file->nama_file_asli, $headers);
    }

    private function wali(Request $request): OrangTuaWali
    {
        abort_unless($request->user()->aktif && $request->user()->akunOrangTua(), 403, 'Gunakan akun orang tua/wali yang aktif.');
        $wali = $request->user()->orangTuaWali;
        abort_unless($wali->siswa()->exists(), 403, 'Akun orang tua belum terhubung dengan siswa. Hubungi sekolah.');

        return $wali;
    }

    private function milik(Request $request)
    {
        return PengaduanHumas::where('pelapor_pengguna_id', $request->user()->id)->where('kanal', 'akun_orang_tua');
    }

    private function halaman(string $view, array $data)
    {
        return response()->view('pengaduan-saya.'.$view, $data)->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function selesai(Request $request, PengaduanHumas $tiket, string $pesan)
    {
        $url = route('pengaduan-saya.show', $tiket->id);
        $request->session()->flash('berhasil', $pesan);

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'pesan' => $pesan]) : redirect($url);
    }
}
