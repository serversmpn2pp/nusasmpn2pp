<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\SaldoStokBarang;
use App\Models\UnitBarang;
use App\Support\BarcodeCode128;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LabelBarcodeInventarisController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'jenis_label' => ['nullable', Rule::in(['unit', 'stok'])],
            'kategori_barang_id' => ['nullable', 'integer', 'exists:kategori_barang,id'],
            'barang_id' => ['nullable', 'integer', 'exists:barang,id'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'unit_barang_id' => ['nullable', 'array', 'max:100'],
            'unit_barang_id.*' => ['integer', 'distinct', 'exists:unit_barang,id'],
            'ukuran' => ['nullable', Rule::in(['kecil', 'sedang', 'besar'])],
            'salinan' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $jenisLabel = $data['jenis_label'] ?? 'unit';
        $kategoriBarangId = $data['kategori_barang_id'] ?? null;
        $barangId = $data['barang_id'] ?? null;
        $lokasiBarangId = $data['lokasi_barang_id'] ?? null;
        $unitBarangIds = $data['unit_barang_id'] ?? [];
        $ukuran = $data['ukuran'] ?? 'sedang';
        $salinan = (int) ($data['salinan'] ?? 1);

        $labelBarcode = $jenisLabel === 'unit'
            ? $this->labelUnitAset($kategoriBarangId, $barangId, $lokasiBarangId, $unitBarangIds, $salinan)
            : $this->labelBarangStok($kategoriBarangId, $barangId, $lokasiBarangId, $salinan);

        return view('label-barcode-inventaris.index', [
            'labelBarcode' => $labelBarcode,
            'jenisLabel' => $jenisLabel,
            'kategoriBarangId' => $kategoriBarangId,
            'barangId' => $barangId,
            'lokasiBarangId' => $lokasiBarangId,
            'unitBarangIds' => $unitBarangIds,
            'ukuran' => $ukuran,
            'salinan' => $salinan,
            'daftarKategori' => KategoriBarang::where('aktif', true)->orderBy('nama')->get(),
            'daftarBarang' => Barang::query()
                ->where('aktif', true)
                ->when(
                    $jenisLabel === 'unit',
                    fn ($query) => $query->where('tipe_pengelolaan', 'aset_individual'),
                    fn ($query) => $query->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai']),
                )
                ->orderBy('nama')
                ->get(),
            'daftarLokasi' => LokasiBarang::where('aktif', true)->orderBy('nama')->get(),
        ]);
    }

    private function labelUnitAset(?int $kategoriBarangId, ?int $barangId, ?int $lokasiBarangId, array $unitBarangIds, int $salinan)
    {
        return UnitBarang::query()
            ->with(['barang.kategoriBarang', 'lokasiBarang'])
            ->where('aktif', true)
            ->when($kategoriBarangId, fn ($query) => $query->whereHas('barang', fn ($query) => $query->where('kategori_barang_id', $kategoriBarangId)))
            ->when($barangId, fn ($query) => $query->where('barang_id', $barangId))
            ->when($lokasiBarangId, fn ($query) => $query->where('lokasi_barang_id', $lokasiBarangId))
            ->when($unitBarangIds !== [], fn ($query) => $query->whereIn('id', $unitBarangIds))
            ->orderBy('barang_id')
            ->orderBy('nomor_unit')
            ->get()
            ->flatMap(function (UnitBarang $unit) use ($salinan) {
                return collect(range(1, $salinan))->map(fn () => [
                    'kode' => $unit->kode_inventaris,
                    'nama' => $unit->barang->nama,
                    'lokasi' => $unit->lokasiBarang?->nama ?: 'Lokasi belum diisi',
                    'barcode_svg' => BarcodeCode128::svg($unit->kode_inventaris),
                ]);
            });
    }

    private function labelBarangStok(?int $kategoriBarangId, ?int $barangId, ?int $lokasiBarangId, int $salinan)
    {
        return SaldoStokBarang::query()
            ->with(['barang.kategoriBarang', 'lokasiBarang'])
            ->whereHas('barang', function ($query) use ($kategoriBarangId) {
                $query->where('aktif', true)
                    ->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai'])
                    ->when($kategoriBarangId, fn ($query) => $query->where('kategori_barang_id', $kategoriBarangId));
            })
            ->when($barangId, fn ($query) => $query->where('barang_id', $barangId))
            ->when($lokasiBarangId, fn ($query) => $query->where('lokasi_barang_id', $lokasiBarangId))
            ->orderBy('barang_id')
            ->orderBy('lokasi_barang_id')
            ->get()
            ->flatMap(function (SaldoStokBarang $saldo) use ($salinan) {
                return collect(range(1, $salinan))->map(fn () => [
                    'kode' => $saldo->barang->kode,
                    'nama' => $saldo->barang->nama,
                    'lokasi' => $saldo->lokasiBarang->nama,
                    'barcode_svg' => BarcodeCode128::svg($saldo->barang->kode),
                ]);
            });
    }
}
