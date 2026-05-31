<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\SaldoStokBarang;
use Illuminate\Http\Request;

class SaldoStokBarangController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $statusStok = $this->pilihanValid($request->input('status_stok', 'semua'), ['semua', 'aman', 'menipis', 'habis']);
        $kategoriBarangId = $request->input('kategori_barang_id', 'semua');
        $lokasiBarangId = $request->input('lokasi_barang_id', 'semua');

        $saldoStokBarang = SaldoStokBarang::query()
            ->select('saldo_stok_barang.*')
            ->join('barang', 'barang.id', '=', 'saldo_stok_barang.barang_id')
            ->with(['barang.kategoriBarang', 'barang.satuanBarang', 'lokasiBarang'])
            ->when($statusStok === 'aman', function ($query) {
                $query->whereColumn('saldo_stok_barang.jumlah', '>', 'barang.stok_minimum');
            })
            ->when($statusStok === 'menipis', function ($query) {
                $query->where('saldo_stok_barang.jumlah', '>', 0)
                    ->whereColumn('saldo_stok_barang.jumlah', '<=', 'barang.stok_minimum');
            })
            ->when($statusStok === 'habis', fn ($query) => $query->where('saldo_stok_barang.jumlah', '<=', 0))
            ->when($kategoriBarangId !== 'semua', fn ($query) => $query->where('barang.kategori_barang_id', $kategoriBarangId))
            ->when($lokasiBarangId !== 'semua', fn ($query) => $query->where('saldo_stok_barang.lokasi_barang_id', $lokasiBarangId))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('barang.nama', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('barang.kode', 'ilike', '%' . $kataKunci . '%');
                });
            })
            ->orderBy('barang.nama')
            ->orderBy('saldo_stok_barang.lokasi_barang_id')
            ->paginate(15)
            ->withQueryString();

        return view('saldo-stok-barang.index', [
            'saldoStokBarang' => $saldoStokBarang,
            'kataKunci' => $kataKunci,
            'statusStok' => $statusStok,
            'kategoriBarangId' => $kategoriBarangId,
            'lokasiBarangId' => $lokasiBarangId,
            'daftarKategori' => KategoriBarang::where('aktif', true)->orderBy('nama')->get(),
            'daftarLokasi' => LokasiBarang::where('aktif', true)->orderBy('nama')->get(),
            'jumlahBarisSaldo' => SaldoStokBarang::count(),
            'jumlahLokasiStok' => SaldoStokBarang::distinct('lokasi_barang_id')->count('lokasi_barang_id'),
            'jumlahSaldoHabis' => SaldoStokBarang::where('jumlah', '<=', 0)->count(),
            'jumlahSaldoMenipis' => SaldoStokBarang::query()
                ->join('barang', 'barang.id', '=', 'saldo_stok_barang.barang_id')
                ->where('saldo_stok_barang.jumlah', '>', 0)
                ->whereColumn('saldo_stok_barang.jumlah', '<=', 'barang.stok_minimum')
                ->count(),
        ]);
    }

    private function pilihanValid(mixed $nilai, array $daftar): string
    {
        return in_array($nilai, $daftar, true) ? $nilai : 'semua';
    }
}
