<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\PengajuanBarang;
use App\Models\SaldoStokBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesPengajuanBarang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengajuanBarangSayaController extends Controller
{
    public function index(Request $request)
    {
        $pegawaiId = $this->pegawaiId($request);
        $status = $request->validate([
            'status' => ['nullable', Rule::in(['semua', ...array_keys(PengajuanBarang::DAFTAR_STATUS)])],
        ])['status'] ?? 'semua';

        $pengajuanBarang = PengajuanBarang::query()
            ->where('pegawai_id', $pegawaiId)
            ->with(['barang.satuanBarang', 'peminjamanBarang'])
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->orderByDesc('tanggal_pengajuan')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('pengajuan-barang-saya.index', [
            'pengajuanBarang' => $pengajuanBarang,
            'status' => $status,
            'daftarStatus' => PengajuanBarang::DAFTAR_STATUS,
            'ringkasan' => [
                'semua' => PengajuanBarang::where('pegawai_id', $pegawaiId)->count(),
                'menunggu' => PengajuanBarang::where('pegawai_id', $pegawaiId)->where('status', 'menunggu')->count(),
                'dipenuhi' => PengajuanBarang::where('pegawai_id', $pegawaiId)->where('status', 'dipenuhi')->count(),
                'selesai' => PengajuanBarang::where('pegawai_id', $pegawaiId)->whereIn('status', ['ditolak', 'dibatalkan'])->count(),
            ],
        ]);
    }

    public function create(Request $request, Barang $barang)
    {
        $this->pegawaiId($request);
        abort_unless($barang->aktif, 404);

        $barang->load(['kategoriBarang', 'satuanBarang']);
        $ketersediaan = $this->jumlahTersedia($barang);

        return view('pengajuan-barang-saya.create', compact('barang', 'ketersediaan'));
    }

    public function store(Request $request, ProsesPengajuanBarang $prosesPengajuan)
    {
        $pegawaiId = $this->pegawaiId($request);
        $data = $request->validate([
            'barang_id' => ['required', 'integer', 'exists:barang,id'],
            'jumlah' => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'tanggal_dibutuhkan' => ['required', 'date', 'after_or_equal:today'],
            'rencana_kembali' => ['nullable', 'date', 'after_or_equal:tanggal_dibutuhkan'],
            'tujuan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $pengajuan = $prosesPengajuan->ajukan($pegawaiId, $data, $request->user()?->id);

        return redirect()
            ->route('pengajuan-barang-saya.show', $pengajuan)
            ->with('berhasil', 'Pengajuan berhasil dikirim kepada petugas inventaris.');
    }

    public function show(Request $request, PengajuanBarang $pengajuanBarang)
    {
        $this->pastikanMilikPegawai($request, $pengajuanBarang);
        $pengajuanBarang->load(['barang.satuanBarang', 'diprosesOleh', 'peminjamanBarang']);

        return view('pengajuan-barang-saya.show', compact('pengajuanBarang'));
    }

    public function batalkan(
        Request $request,
        PengajuanBarang $pengajuanBarang,
        ProsesPengajuanBarang $prosesPengajuan,
    ) {
        $pegawaiId = $this->pegawaiId($request);
        $this->pastikanMilikPegawai($request, $pengajuanBarang);
        $prosesPengajuan->batalkan($pengajuanBarang, $pegawaiId, $request->user()?->id);

        return redirect()
            ->route('pengajuan-barang-saya.show', $pengajuanBarang)
            ->with('berhasil', 'Pengajuan berhasil dibatalkan.');
    }

    private function pegawaiId(Request $request): int
    {
        abort_unless($request->user()?->akunPegawai() && $request->user()->pegawai_id, 403);

        return (int) $request->user()->pegawai_id;
    }

    private function pastikanMilikPegawai(Request $request, PengajuanBarang $pengajuanBarang): void
    {
        abort_unless($pengajuanBarang->pegawai_id === $this->pegawaiId($request), 403);
    }

    private function jumlahTersedia(Barang $barang): float|int
    {
        if ($barang->tipe_pengelolaan === 'aset_individual') {
            return UnitBarang::query()
                ->where('barang_id', $barang->id)
                ->where('aktif', true)
                ->where('status_unit', 'tersedia')
                ->count();
        }

        return (float) SaldoStokBarang::query()
            ->where('barang_id', $barang->id)
            ->sum('jumlah');
    }
}
