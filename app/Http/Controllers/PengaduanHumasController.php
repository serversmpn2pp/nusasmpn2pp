<?php

namespace App\Http\Controllers;

use App\Models\LampiranPengaduanHumas;
use App\Models\PengaduanHumas;
use App\Services\Humas\KelolaPengaduanHumasService;
use App\Services\Humas\KelolaTiketPengaduanHumasService;
use App\Services\Humas\LampiranPengaduanHumasService;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengaduanHumasController extends Controller
{
    public function __construct(private readonly KelolaPengaduanHumasService $kelola, private readonly NotifikasiPenggunaService $notifikasi, private readonly LampiranPengaduanHumasService $berkas) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in([...array_keys(PengaduanHumas::STATUS), 'aktif', 'semua'])],
            'jenis' => ['nullable', Rule::in(array_keys(PengaduanHumas::JENIS))], 'kategori' => ['nullable', Rule::in(array_keys(PengaduanHumas::KATEGORI))],
            'prioritas' => ['nullable', Rule::in(array_keys(PengaduanHumas::PRIORITAS))], 'tugas' => ['nullable', Rule::in(['semua', 'saya'])],
            'batas' => ['nullable', Rule::in(['lewat'])], 'mulai' => ['nullable', 'date_format:Y-m-d'], 'sampai' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('mulai'), 'after_or_equal:mulai')]]);
        $scope = PengaduanHumas::untukPengguna($request->user());
        $query = (clone $scope)->select(['id', 'judul', 'jenis', 'kategori', 'prioritas', 'status', 'tanggal_diterima', 'batas_tanggal', 'petugas_pengguna_id', 'created_at', 'updated_at'])
            ->with('petugas:id,nama');
        $status = $filter['status'] ?? 'aktif';
        if ($status === 'aktif') {
            $query->whereIn('status', PengaduanHumas::AKTIF);
        } elseif ($status !== 'semua') {
            $query->where('status', $status);
        }
        foreach (['jenis', 'kategori', 'prioritas'] as $kolom) {
            if (! empty($filter[$kolom])) {
                $query->where($kolom, $filter[$kolom]);
            }
        }
        if ($kata = $filter['kata_kunci'] ?? null) {
            $id = preg_match('/^(?:HM-\d{4}-)?(\d+)$/i', $kata, $match) ? (int) $match[1] : null;
            $query->where(fn ($q) => $q->whereLike('judul', '%'.$kata.'%')->when($id, fn ($q) => $q->orWhere('id', $id)));
        }
        if (($filter['tugas'] ?? null) === 'saya') {
            $query->where('petugas_pengguna_id', $request->user()->id);
        }
        if (($filter['batas'] ?? null) === 'lewat') {
            $query->whereIn('status', PengaduanHumas::AKTIF)->whereDate('batas_tanggal', '<', today());
        }
        $query->when($filter['mulai'] ?? null, fn ($q, $date) => $q->whereDate('tanggal_diterima', '>=', $date))->when($filter['sampai'] ?? null, fn ($q, $date) => $q->whereDate('tanggal_diterima', '<=', $date));

        return $this->halaman('index', ['daftar' => $query->latest('updated_at')->orderByDesc('id')->paginate(20)->withQueryString(), 'filter' => $filter,
            'statistik' => ['aktif' => (clone $scope)->whereIn('status', PengaduanHumas::AKTIF)->count(), 'verifikasi' => (clone $scope)->where('status', 'verifikasi')->count(),
                'terlambat' => (clone $scope)->whereIn('status', PengaduanHumas::AKTIF)->whereDate('batas_tanggal', '<', today())->count(), 'selesai' => (clone $scope)->where('status', 'selesai')->count()]]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->aktif && ! $request->user()->akunOrangTua(), 403);

        return $this->form(new PengaduanHumas(['jenis' => 'pengaduan', 'kategori' => 'layanan', 'kanal' => 'tatap_muka', 'tanggal_diterima' => today(), 'prioritas' => 'normal', 'anonim' => false]));
    }

    public function store(Request $request)
    {
        $tiket = app(KelolaTiketPengaduanHumasService::class)->store($request);

        return $this->selesai($request, $tiket, 'Tiket berhasil dicatat.');
    }

    public function show(Request $request, PengaduanHumas $tiket)
    {
        $this->kelola->akses($tiket, $request->user());
        $manager = $request->user()->memilikiIzin('pengaduan_humas.kelola');
        $tiket->load('petugas:id,nama');
        if ($manager) {
            $tiket->load('lampiran');
        }

        return $this->halaman('show', ['tiket' => $tiket, 'manager' => $manager, 'kandidat' => $manager ? $this->kelola->kandidat() : collect(),
            'bolehMenangani' => $manager || ($request->user()->akunPegawai() && $request->user()->memilikiIzin('pengaduan_humas.tangani') && $tiket->petugas_pengguna_id === $request->user()->id),
            'riwayat' => $tiket->riwayat()->with(['pengguna' => fn ($q) => $q->select('id', 'nama')->when(! $manager && $tiket->pelapor_pengguna_id, fn ($q) => $q->where('id', '<>', $tiket->pelapor_pengguna_id))])->paginate(15),
            'pesan' => $tiket->dariOrangTua() ? $tiket->pesan()->latest('id')->paginate(15, ['id', 'asal', 'isi', 'created_at'], 'halaman_pesan') : null,
            'tokenPengiriman' => (string) Str::uuid()]);
    }

    public function edit(Request $request, PengaduanHumas $tiket)
    {
        $this->kelola->akses($tiket, $request->user());
        $this->pastikanEdit($tiket);
        $this->pastikanKoreksi($tiket);

        return $this->form($tiket);
    }

    public function update(Request $request, PengaduanHumas $tiket)
    {
        app(KelolaTiketPengaduanHumasService::class)->update($request, $tiket);

        return $this->selesai($request, $tiket, 'Koreksi tiket berhasil disimpan.');
    }

    public function tindakan(Request $request, PengaduanHumas $tiket, string $aksi)
    {
        app(KelolaTiketPengaduanHumasService::class)->tindakan($request, $tiket, $aksi);

        return $this->selesai($request, $tiket, 'Penanganan tiket berhasil diperbarui.');
    }

    public function tambahLampiran(Request $request, PengaduanHumas $tiket)
    {
        app(KelolaTiketPengaduanHumasService::class)->tambahLampiran($request, $tiket);

        return $this->selesai($request, $tiket, 'Lampiran privat berhasil disimpan.');
    }

    public function berkas(Request $request, PengaduanHumas $tiket, LampiranPengaduanHumas $lampiran)
    {
        $this->kelola->akses($tiket, $request->user());
        abort_unless($lampiran->pengaduan_humas_id === $tiket->id && Storage::disk('local')->exists($lampiran->lokasi_file), 404);
        $headers = ['Content-Type' => $lampiran->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store, max-age=0'];

        return $request->boolean('unduh') ? Storage::disk('local')->download($lampiran->lokasi_file, $lampiran->nama_file_asli, $headers)
            : Storage::disk('local')->response($lampiran->lokasi_file, $lampiran->nama_file_asli, $headers);
    }

    public function balasan(Request $request, PengaduanHumas $tiket)
    {
        app(KelolaTiketPengaduanHumasService::class)->balasan($request, $tiket);

        return $this->selesai($request, $tiket, 'Balasan resmi berhasil dikirim kepada orang tua.');
    }

    private function form(PengaduanHumas $tiket)
    {
        return $this->halaman('form', ['tiket' => $tiket, 'tokenPembuatan' => (string) Str::uuid()]);
    }

    private function halaman(string $view, array $data)
    {
        return response()->view('pengaduan-humas.'.$view, $data)->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function pastikanEdit(PengaduanHumas $tiket): void
    {
        if (! $tiket->aktif()) {
            throw ValidationException::withMessages(['status' => 'Tiket sudah selesai atau ditutup. Buka kembali dengan alasan sebelum mengoreksi data.']);
        }
    }

    private function pastikanKoreksi(PengaduanHumas $tiket): void
    {
        if ($tiket->dariOrangTua()) {
            throw ValidationException::withMessages(['tiket' => 'Laporan asli orang tua tidak dapat diubah. Gunakan balasan resmi untuk meminta klarifikasi.']);
        }
    }

    private function selesai(Request $request, PengaduanHumas $tiket, string $pesan)
    {
        $url = route('pengaduan-humas.show', $tiket);
        $request->session()->flash('berhasil', $pesan);

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'pesan' => $pesan]) : redirect($url);
    }
}
