<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\PenerimaanBarang;
use App\Models\PengaturanInventaris;
use App\Models\SaldoStokBarang;
use App\Models\UnitBarang;
use App\Support\BarcodeCode128;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class LabelBarcodeInventarisController extends Controller
{
    public const DAFTAR_UKURAN = [
        'kecil' => '50 x 30 mm',
        'sedang' => '65 x 35 mm',
        'besar' => '80 x 45 mm',
    ];

    public function index(Request $request)
    {
        $data = $request->validate([
            'jenis_label' => ['nullable', Rule::in(['unit', 'stok'])],
            'penerimaan_barang_id' => [
                'nullable',
                'integer',
                Rule::exists('penerimaan_barang', 'id')->where('status', PenerimaanBarang::STATUS_AKTIF),
            ],
            'tahun_perolehan' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'kategori_barang_id' => ['nullable', 'integer', 'exists:kategori_barang,id'],
            'barang_id' => ['nullable', 'integer', 'exists:barang,id'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'unit_barang_id' => ['nullable', 'array', 'max:500'],
            'unit_barang_id.*' => ['integer', 'distinct', 'exists:unit_barang,id'],
            'saldo_stok_barang_id' => ['nullable', 'array', 'max:500'],
            'saldo_stok_barang_id.*' => ['integer', 'distinct', 'exists:saldo_stok_barang,id'],
            'seleksi' => ['nullable', 'boolean'],
            'ukuran' => ['nullable', Rule::in(array_keys(self::DAFTAR_UKURAN))],
            'salinan' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $penerimaanBarangId = $data['penerimaan_barang_id'] ?? null;
        $jenisLabel = $data['jenis_label'] ?? $this->jenisLabelBawaan($penerimaanBarangId);
        $kategoriBarangId = $data['kategori_barang_id'] ?? null;
        $barangId = $data['barang_id'] ?? null;
        $lokasiBarangId = $data['lokasi_barang_id'] ?? null;
        $tahunPerolehan = $data['tahun_perolehan'] ?? null;
        $unitBarangIds = collect($data['unit_barang_id'] ?? [])->map(fn ($id) => (int) $id)->all();
        $saldoStokBarangIds = collect($data['saldo_stok_barang_id'] ?? [])->map(fn ($id) => (int) $id)->all();
        $seleksiDiterapkan = array_key_exists('seleksi', $data) || $unitBarangIds !== [] || $saldoStokBarangIds !== [];
        $ukuran = $data['ukuran'] ?? 'sedang';
        $salinan = (int) ($data['salinan'] ?? 1);

        $fokusUnitLangsung = $unitBarangIds !== [] && ! array_key_exists('seleksi', $data) && ! $penerimaanBarangId;
        $fokusStokLangsung = $saldoStokBarangIds !== [] && ! array_key_exists('seleksi', $data) && ! $penerimaanBarangId;

        $daftarPilihanUnit = $this->daftarUnitAset(
            $penerimaanBarangId,
            $tahunPerolehan,
            $kategoriBarangId,
            $barangId,
            $lokasiBarangId,
            $fokusUnitLangsung ? $unitBarangIds : [],
        );
        $daftarPilihanStok = $this->daftarBarangStok(
            $penerimaanBarangId,
            $kategoriBarangId,
            $barangId,
            $lokasiBarangId,
            $fokusStokLangsung ? $saldoStokBarangIds : [],
        );

        if ($jenisLabel === 'unit') {
            $pilihanUntukLabel = $seleksiDiterapkan
                ? $daftarPilihanUnit->whereIn('id', $unitBarangIds)->values()
                : $daftarPilihanUnit;
            $labelBarcode = $this->labelUnitAset($pilihanUntukLabel, $salinan);
        } else {
            $pilihanUntukLabel = $seleksiDiterapkan
                ? $daftarPilihanStok->whereIn('id', $saldoStokBarangIds)->values()
                : $daftarPilihanStok;
            $labelBarcode = $this->labelBarangStok($pilihanUntukLabel, $salinan);
        }

        return view('label-barcode-inventaris.index', [
            'labelBarcode' => $labelBarcode,
            'jenisLabel' => $jenisLabel,
            'penerimaanBarangId' => $penerimaanBarangId,
            'tahunPerolehan' => $tahunPerolehan,
            'kategoriBarangId' => $kategoriBarangId,
            'barangId' => $barangId,
            'lokasiBarangId' => $lokasiBarangId,
            'unitBarangIds' => $unitBarangIds,
            'saldoStokBarangIds' => $saldoStokBarangIds,
            'seleksiDiterapkan' => $seleksiDiterapkan,
            'ukuran' => $ukuran,
            'salinan' => $salinan,
            'daftarUkuran' => self::DAFTAR_UKURAN,
            'daftarPilihanUnit' => $daftarPilihanUnit,
            'daftarPilihanStok' => $daftarPilihanStok,
            'daftarPenerimaan' => PenerimaanBarang::query()
                ->where('status', PenerimaanBarang::STATUS_AKTIF)
                ->orderByDesc('tanggal_penerimaan')
                ->orderByDesc('id')
                ->get(['id', 'nomor_penerimaan', 'tanggal_penerimaan']),
            'daftarKategori' => KategoriBarang::where('aktif', true)->orderBy('nama')->get(),
            'daftarBarang' => $this->daftarBarang($jenisLabel, $penerimaanBarangId),
            'daftarLokasi' => LokasiBarang::where('aktif', true)->orderBy('nama')->get(),
        ]);
    }

    private function daftarUnitAset(
        ?int $penerimaanBarangId,
        ?int $tahunPerolehan,
        ?int $kategoriBarangId,
        ?int $barangId,
        ?int $lokasiBarangId,
        array $fokusUnitIds,
    ): Collection {
        return UnitBarang::query()
            ->with(['barang.kategoriBarang', 'lokasiBarang', 'sumberPerolehanBarang'])
            ->where('aktif', true)
            ->when($penerimaanBarangId, fn ($query) => $query->whereHas(
                'detailPenerimaanBarang',
                fn ($query) => $query->where('penerimaan_barang_id', $penerimaanBarangId),
            ))
            ->when($tahunPerolehan, fn ($query) => $query->where('tahun_perolehan', $tahunPerolehan))
            ->when($kategoriBarangId, fn ($query) => $query->whereHas('barang', fn ($query) => $query->where('kategori_barang_id', $kategoriBarangId)))
            ->when($barangId, fn ($query) => $query->where('barang_id', $barangId))
            ->when($lokasiBarangId, fn ($query) => $query->where('lokasi_barang_id', $lokasiBarangId))
            ->when($fokusUnitIds !== [], fn ($query) => $query->whereIn('id', $fokusUnitIds))
            ->orderBy('barang_id')
            ->orderBy('detail_penerimaan_barang_id')
            ->orderBy('urutan_dalam_penerimaan')
            ->orderBy('nomor_unit')
            ->get();
    }

    private function daftarBarangStok(
        ?int $penerimaanBarangId,
        ?int $kategoriBarangId,
        ?int $barangId,
        ?int $lokasiBarangId,
        array $fokusSaldoIds,
    ): Collection {
        return SaldoStokBarang::query()
            ->with(['barang.kategoriBarang', 'barang.satuanBarang', 'lokasiBarang'])
            ->whereHas('barang', function ($query) use ($kategoriBarangId) {
                $query->where('aktif', true)
                    ->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai'])
                    ->when($kategoriBarangId, fn ($query) => $query->where('kategori_barang_id', $kategoriBarangId));
            })
            ->when($penerimaanBarangId, function ($query) use ($penerimaanBarangId) {
                $query->whereExists(function ($query) use ($penerimaanBarangId) {
                    $query->selectRaw('1')
                        ->from('detail_penerimaan_barang')
                        ->whereColumn('detail_penerimaan_barang.barang_id', 'saldo_stok_barang.barang_id')
                        ->whereColumn('detail_penerimaan_barang.lokasi_barang_id', 'saldo_stok_barang.lokasi_barang_id')
                        ->where('detail_penerimaan_barang.penerimaan_barang_id', $penerimaanBarangId);
                });
            })
            ->when($barangId, fn ($query) => $query->where('barang_id', $barangId))
            ->when($lokasiBarangId, fn ($query) => $query->where('lokasi_barang_id', $lokasiBarangId))
            ->when($fokusSaldoIds !== [], fn ($query) => $query->whereIn('id', $fokusSaldoIds))
            ->orderBy('barang_id')
            ->orderBy('lokasi_barang_id')
            ->get();
    }

    private function labelUnitAset(Collection $daftarUnit, int $salinan): Collection
    {
        $pemilik = PengaturanInventaris::utama()->nama_pemilik;

        return $daftarUnit->flatMap(function (UnitBarang $unit) use ($salinan, $pemilik) {
            return collect(range(1, $salinan))->map(fn () => [
                'jenis' => 'unit',
                'kode' => $unit->kode_inventaris,
                'nama' => $unit->barang->nama,
                'nomor_aset_resmi' => $unit->nomor_aset_resmi ?: '-',
                'kode_barang' => $unit->kodeBarangUnit(),
                'sumber_tahun' => collect([
                    $unit->sumberPerolehanBarang?->nama ?: $unit->sumber_perolehan,
                    $unit->tahun_perolehan,
                ])->filter()->join(' ') ?: '-',
                'pemilik' => $pemilik,
                'lokasi' => $unit->lokasiBarang?->nama ?: 'Lokasi belum diisi',
                'barcode_svg' => BarcodeCode128::svg($unit->kode_inventaris),
            ]);
        });
    }

    private function labelBarangStok(Collection $daftarSaldo, int $salinan): Collection
    {
        return $daftarSaldo->flatMap(function (SaldoStokBarang $saldo) use ($salinan) {
            return collect(range(1, $salinan))->map(fn () => [
                'jenis' => 'stok',
                'judul' => $saldo->barang->jenis_barang === 'habis_pakai'
                    ? 'BARANG HABIS PAKAI'
                    : 'BARANG BERBASIS STOK',
                'kode' => $saldo->barang->kode,
                'nama' => $saldo->barang->nama,
                'lokasi' => $saldo->lokasiBarang->nama,
                'satuan' => $saldo->barang->satuanBarang->nama,
                'barcode_svg' => BarcodeCode128::svg($saldo->barang->kode),
            ]);
        });
    }

    private function daftarBarang(string $jenisLabel, ?int $penerimaanBarangId): Collection
    {
        return Barang::query()
            ->where('aktif', true)
            ->when(
                $jenisLabel === 'unit',
                fn ($query) => $query->where('jenis_barang', 'tidak_habis_pakai'),
                fn ($query) => $query->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai']),
            )
            ->when($penerimaanBarangId, fn ($query) => $query->whereHas(
                'detailPenerimaanBarang',
                fn ($query) => $query->where('penerimaan_barang_id', $penerimaanBarangId),
            ))
            ->orderBy('nama')
            ->get();
    }

    private function jenisLabelBawaan(?int $penerimaanBarangId): string
    {
        if (! $penerimaanBarangId) {
            return 'unit';
        }

        return UnitBarang::query()
            ->whereHas('detailPenerimaanBarang', fn ($query) => $query->where('penerimaan_barang_id', $penerimaanBarangId))
            ->exists()
                ? 'unit'
                : 'stok';
    }
}
