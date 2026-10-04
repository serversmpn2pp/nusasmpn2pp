<?php

namespace App\Http\Controllers;

use App\Models\LampiranPengaduanHumas;
use App\Models\OrangTuaWali;
use App\Models\PengaduanHumas;
use App\Services\Humas\KelolaPengaduanHumasService;
use App\Services\Humas\LampiranPengaduanHumasService;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

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
        $wali = $this->wali($request);
        $request->query->replace([]);
        $request->replace(Arr::only($request->input(), ['token_pembuatan', 'judul', 'jenis', 'kategori', 'isi', 'rahasiakan_identitas', '_token']));
        $data = $request->validate(['token_pembuatan' => ['required', 'uuid'], 'judul' => ['required', 'string', 'max:180'],
            'jenis' => ['required', Rule::in(array_keys(PengaduanHumas::JENIS))], 'kategori' => ['required', Rule::in(array_keys(PengaduanHumas::KATEGORI))],
            'isi' => ['required', 'string', 'min:10', 'max:5000'], 'rahasiakan_identitas' => ['required', 'boolean']] + $this->berkas->aturan());
        $files = $this->berkas->simpan($request, 'orang_tua');
        try {
            $tiket = DB::transaction(function () use ($request, $wali, $data, $files) {
                $tiket = PengaduanHumas::firstOrCreate(['token_pembuatan' => $data['token_pembuatan']], Arr::only($data, ['judul', 'jenis', 'kategori', 'isi']) + [
                    'kanal' => 'akun_orang_tua', 'tanggal_diterima' => today(), 'prioritas' => 'normal', 'anonim' => false,
                    'nama_pelapor' => $wali->nama_lengkap ?: $request->user()->nama, 'kontak_pelapor' => $wali->nomor_wa]);
                if ($tiket->wasRecentlyCreated) {
                    $tiket->forceFill(['pelapor_pengguna_id' => $request->user()->id, 'dibuat_oleh_pengguna_id' => $request->user()->id,
                        'rahasiakan_identitas' => $data['rahasiakan_identitas'], 'status' => 'baru', 'versi' => 0])->save();
                    $tiket->lampiran()->createMany($files);
                    $this->kelola->catat($tiket, $request->user(), 'Laporan orang tua diterima', 'Laporan masuk melalui akun orang tua NUSA.');
                    $this->notifikasi->kirimKeBanyak($this->notifikasi->penggunaDenganIzin('pengaduan_humas.kelola'), 'informasi', 'Laporan Humas baru',
                        $tiket->nomor.' telah diterima.', '/pengaduan-humas/'.$tiket->id, 'pengaduan-'.$tiket->id.'-baru');
                } else {
                    abort_unless($tiket->dariOrangTua() && $tiket->pelapor_pengguna_id === $request->user()->id, 403);
                }

                return $tiket;
            });
        } catch (Throwable $e) {
            $this->berkas->hapus($files);
            throw $e;
        }
        $tersimpan = $tiket->lampiran()->pluck('lokasi_file')->all();
        $this->berkas->hapus(array_filter($files, fn ($f) => ! in_array($f['lokasi_file'], $tersimpan, true)));

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
        $this->wali($request);
        $laporan = $this->milik($request)->findOrFail($tiket);
        $data = $request->validate(['token_pengiriman' => ['required', 'uuid'], 'versi' => ['required', 'integer', 'min:0'], 'isi_pesan' => ['required', 'string', 'min:5', 'max:3000'], 'lampiran' => ['prohibited']]);
        DB::transaction(function () use ($request, $laporan, $data) {
            $laporan = $this->milik($request)->lockForUpdate()->findOrFail($laporan->id);
            $this->kelola->kirimPesan($laporan, $request->user(), $data, 'orang_tua');
        });

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
