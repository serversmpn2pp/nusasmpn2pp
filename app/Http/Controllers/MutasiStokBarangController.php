<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\LokasiBarang;
use App\Models\MutasiStokBarang;
use App\Services\Inventaris\ProsesMutasiStokBarang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MutasiStokBarangController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $jenisMutasi = $this->pilihanValid($request->input('jenis_mutasi', 'semua'), array_merge(['semua'], array_keys(MutasiStokBarang::DAFTAR_JENIS)));
        $barangId = $request->input('barang_id', 'semua');
        $lokasiBarangId = $request->input('lokasi_barang_id', 'semua');
        $tanggalMulai = $request->input('tanggal_mulai');
        $tanggalSelesai = $request->input('tanggal_selesai');

        $request->validate([
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        $mutasiStokBarang = MutasiStokBarang::query()
            ->with(['barang.satuanBarang', 'lokasiBarang', 'dibuatOleh'])
            ->when($jenisMutasi !== 'semua', fn ($query) => $query->where('jenis_mutasi', $jenisMutasi))
            ->when($barangId !== 'semua', fn ($query) => $query->where('barang_id', $barangId))
            ->when($lokasiBarangId !== 'semua', fn ($query) => $query->where('lokasi_barang_id', $lokasiBarangId))
            ->when($tanggalMulai, fn ($query) => $query->whereDate('tanggal_mutasi', '>=', $tanggalMulai))
            ->when($tanggalSelesai, fn ($query) => $query->whereDate('tanggal_mutasi', '<=', $tanggalSelesai))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('referensi', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('keterangan', 'ilike', '%' . $kataKunci . '%')
                        ->orWhereHas('barang', function ($query) use ($kataKunci) {
                            $query->where('nama', 'ilike', '%' . $kataKunci . '%')
                                ->orWhere('kode', 'ilike', '%' . $kataKunci . '%');
                        });
                });
            })
            ->orderByDesc('tanggal_mutasi')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $hariIni = now()->toDateString();

        return view('mutasi-stok-barang.index', [
            'mutasiStokBarang' => $mutasiStokBarang,
            'kataKunci' => $kataKunci,
            'jenisMutasi' => $jenisMutasi,
            'barangId' => $barangId,
            'lokasiBarangId' => $lokasiBarangId,
            'tanggalMulai' => $tanggalMulai,
            'tanggalSelesai' => $tanggalSelesai,
            'daftarJenis' => MutasiStokBarang::DAFTAR_JENIS,
            'daftarBarang' => $this->daftarBarang(),
            'daftarLokasi' => LokasiBarang::orderBy('nama')->get(),
            'jumlahMutasi' => MutasiStokBarang::count(),
            'jumlahMutasiHariIni' => MutasiStokBarang::whereDate('tanggal_mutasi', $hariIni)->count(),
            'jumlahMasukHariIni' => (float) MutasiStokBarang::whereDate('tanggal_mutasi', $hariIni)
                ->where('jenis_mutasi', 'masuk')
                ->sum('jumlah_perubahan'),
            'jumlahKeluarHariIni' => abs((float) MutasiStokBarang::whereDate('tanggal_mutasi', $hariIni)
                ->where('jenis_mutasi', 'keluar')
                ->sum('jumlah_perubahan')),
        ]);
    }

    public function create(Request $request)
    {
        return view('mutasi-stok-barang.create', [
            'daftarBarang' => $this->daftarBarang(aktifSaja: true),
            'daftarLokasi' => LokasiBarang::where('aktif', true)->orderBy('nama')->get(),
            'daftarJenis' => MutasiStokBarang::DAFTAR_JENIS,
            'daftarKategori' => MutasiStokBarang::DAFTAR_KATEGORI,
            'kategoriPerJenis' => MutasiStokBarang::KATEGORI_PER_JENIS,
            'barangTerpilihId' => $request->integer('barang_id') ?: null,
        ]);
    }

    public function store(Request $request, ProsesMutasiStokBarang $prosesMutasi)
    {
        $data = $this->rapikanData($request->validate([
            'barang_id' => ['required', 'integer', 'exists:barang,id'],
            'lokasi_barang_id' => ['required', 'integer', 'exists:lokasi_barang,id'],
            'jenis_mutasi' => ['required', Rule::in(array_keys(MutasiStokBarang::DAFTAR_JENIS))],
            'kategori_mutasi' => ['required', Rule::in(array_keys(MutasiStokBarang::DAFTAR_KATEGORI))],
            'tanggal_mutasi' => ['required', 'date'],
            'jumlah' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'referensi' => ['nullable', 'string', 'max:120'],
            'keterangan' => ['nullable', 'string'],
        ]));

        $mutasiStokBarang = $prosesMutasi->catat($data, $request->user()?->id);

        return redirect()
            ->route('mutasi-stok-barang.show', $mutasiStokBarang)
            ->with('berhasil', 'Mutasi stok berhasil dicatat.');
    }

    public function show(MutasiStokBarang $mutasiStokBarang)
    {
        $mutasiStokBarang->load(['barang.satuanBarang', 'barang.kategoriBarang', 'lokasiBarang', 'dibuatOleh']);

        return view('mutasi-stok-barang.show', compact('mutasiStokBarang'));
    }

    private function daftarBarang(bool $aktifSaja = false)
    {
        return Barang::query()
            ->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai'])
            ->when($aktifSaja, fn ($query) => $query->where('aktif', true))
            ->orderBy('nama')
            ->get();
    }

    private function rapikanData(array $data): array
    {
        $data['referensi'] = filled($data['referensi'] ?? null) ? trim($data['referensi']) : null;
        $data['keterangan'] = filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null;

        return $data;
    }

    private function pilihanValid(mixed $nilai, array $daftar): string
    {
        return in_array($nilai, $daftar, true) ? $nilai : 'semua';
    }
}
