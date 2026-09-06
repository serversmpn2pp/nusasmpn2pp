<?php

namespace App\Services\Mobile;

use App\Http\Controllers\LabelBarcodeInventarisController;
use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\PenerimaanBarang;
use App\Models\PengaturanInventaris;
use App\Models\SaldoStokBarang;
use App\Models\UnitBarang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LabelInventarisMobileService
{
    public function siapkan(array $filter): array
    {
        $penerimaanId = isset($filter['penerimaan_barang_id'])
            ? (int) $filter['penerimaan_barang_id']
            : null;
        $jenis = $filter['jenis_label'] ?? $this->jenisLabelBawaan($penerimaanId);
        $tahun = isset($filter['tahun_perolehan']) ? (int) $filter['tahun_perolehan'] : null;
        $kategoriId = isset($filter['kategori_barang_id']) ? (int) $filter['kategori_barang_id'] : null;
        $barangId = isset($filter['barang_id']) ? (int) $filter['barang_id'] : null;
        $lokasiId = isset($filter['lokasi_barang_id']) ? (int) $filter['lokasi_barang_id'] : null;

        $pemilik = PengaturanInventaris::utama()->nama_pemilik;
        $items = $jenis === 'unit'
            ? $this->daftarUnit($penerimaanId, $tahun, $kategoriId, $barangId, $lokasiId)
                ->map(fn (UnitBarang $unit) => $this->labelUnit($unit, $pemilik))
            : $this->daftarStok($penerimaanId, $kategoriId, $barangId, $lokasiId)
                ->map(fn (SaldoStokBarang $saldo) => $this->labelStok($saldo));

        return [
            'filter' => [
                'jenis_label' => $jenis,
                'penerimaan_barang_id' => $penerimaanId,
                'tahun_perolehan' => $tahun,
                'kategori_barang_id' => $kategoriId,
                'barang_id' => $barangId,
                'lokasi_barang_id' => $lokasiId,
            ],
            'aturan_cetak' => [
                'format_kertas' => 'A4',
                'margin_mm' => 8,
                'jarak_label_mm' => 3,
                'maksimal_pilihan' => 500,
                'maksimal_salinan' => 20,
            ],
            'pilihan' => [
                'jenis_label' => [
                    ['nilai' => 'unit', 'label' => 'Barang tidak habis pakai'],
                    ['nilai' => 'stok', 'label' => 'Barang habis pakai'],
                ],
                'ukuran' => collect(LabelBarcodeInventarisController::DAFTAR_UKURAN)
                    ->map(fn (string $label, string $nilai) => [
                        'nilai' => $nilai,
                        'label' => $label,
                        'lebar_mm' => match ($nilai) {
                            'kecil' => 50,
                            'sedang' => 65,
                            default => 80,
                        },
                        'tinggi_mm' => match ($nilai) {
                            'kecil' => 30,
                            'sedang' => 35,
                            default => 45,
                        },
                    ])->values(),
                'penerimaan' => PenerimaanBarang::query()
                    ->where('status', PenerimaanBarang::STATUS_AKTIF)
                    ->orderByDesc('tanggal_penerimaan')
                    ->orderByDesc('id')
                    ->get(['id', 'nomor_penerimaan', 'tanggal_penerimaan'])
                    ->map(fn (PenerimaanBarang $item) => [
                        'id' => (int) $item->id,
                        'nomor' => $item->nomor_penerimaan,
                        'tanggal' => $item->tanggal_penerimaan?->toDateString(),
                        'label' => $item->nomor_penerimaan.' - '.($item->tanggal_penerimaan?->format('d-m-Y') ?? '-'),
                    ])->values(),
                'kategori' => KategoriBarang::query()->where('aktif', true)->orderBy('nama')->get()
                    ->map(fn (KategoriBarang $item) => $this->pilihan($item))->values(),
                'barang' => $this->daftarBarang($jenis, $penerimaanId)
                    ->map(fn (Barang $item) => [
                        'id' => (int) $item->id,
                        'nama' => $item->nama,
                        'kode' => $item->kodeKlasifikasi(),
                        'label' => $item->nama.' - '.$item->kodeKlasifikasi(),
                    ])->values(),
                'lokasi' => LokasiBarang::query()->where('aktif', true)->orderBy('nama')->get()
                    ->map(fn (LokasiBarang $item) => $this->pilihan($item))->values(),
            ],
            'ringkasan' => [
                'jumlah_pilihan' => $items->count(),
                'jenis_label' => $jenis,
            ],
            'items' => $items->values(),
        ];
    }

    private function daftarUnit(
        ?int $penerimaanId,
        ?int $tahun,
        ?int $kategoriId,
        ?int $barangId,
        ?int $lokasiId,
    ): Collection {
        return UnitBarang::query()
            ->with(['barang.kategoriBarang', 'lokasiBarang', 'sumberPerolehanBarang'])
            ->where('aktif', true)
            ->when($penerimaanId, fn (Builder $query) => $query->whereHas(
                'detailPenerimaanBarang',
                fn (Builder $query) => $query->where('penerimaan_barang_id', $penerimaanId),
            ))
            ->when($tahun, fn (Builder $query) => $query->where('tahun_perolehan', $tahun))
            ->when($kategoriId, fn (Builder $query) => $query->whereHas(
                'barang',
                fn (Builder $query) => $query->where('kategori_barang_id', $kategoriId),
            ))
            ->when($barangId, fn (Builder $query) => $query->where('barang_id', $barangId))
            ->when($lokasiId, fn (Builder $query) => $query->where('lokasi_barang_id', $lokasiId))
            ->orderBy('barang_id')
            ->orderBy('detail_penerimaan_barang_id')
            ->orderBy('urutan_dalam_penerimaan')
            ->orderBy('nomor_unit')
            ->get();
    }

    private function daftarStok(
        ?int $penerimaanId,
        ?int $kategoriId,
        ?int $barangId,
        ?int $lokasiId,
    ): Collection {
        return SaldoStokBarang::query()
            ->with(['barang.kategoriBarang', 'barang.satuanBarang', 'lokasiBarang'])
            ->whereHas('barang', function (Builder $query) use ($kategoriId) {
                $query->where('aktif', true)
                    ->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai'])
                    ->when($kategoriId, fn (Builder $query) => $query->where('kategori_barang_id', $kategoriId));
            })
            ->when($penerimaanId, function (Builder $query) use ($penerimaanId) {
                $query->whereExists(function ($query) use ($penerimaanId) {
                    $query->selectRaw('1')
                        ->from('detail_penerimaan_barang')
                        ->whereColumn('detail_penerimaan_barang.barang_id', 'saldo_stok_barang.barang_id')
                        ->whereColumn('detail_penerimaan_barang.lokasi_barang_id', 'saldo_stok_barang.lokasi_barang_id')
                        ->where('detail_penerimaan_barang.penerimaan_barang_id', $penerimaanId);
                });
            })
            ->when($barangId, fn (Builder $query) => $query->where('barang_id', $barangId))
            ->when($lokasiId, fn (Builder $query) => $query->where('lokasi_barang_id', $lokasiId))
            ->orderBy('barang_id')
            ->orderBy('lokasi_barang_id')
            ->get();
    }

    private function labelUnit(UnitBarang $unit, string $pemilik): array
    {
        return [
            'id' => (int) $unit->id,
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
            'ringkasan' => $unit->barang->nama.' - '.($unit->lokasiBarang?->nama ?: 'Tanpa lokasi'),
        ];
    }

    private function labelStok(SaldoStokBarang $saldo): array
    {
        return [
            'id' => (int) $saldo->id,
            'jenis' => 'stok',
            'judul' => $saldo->barang->jenis_barang === 'habis_pakai'
                ? 'BARANG HABIS PAKAI'
                : 'BARANG BERBASIS STOK',
            'kode' => $saldo->barang->kode,
            'nama' => $saldo->barang->nama,
            'lokasi' => $saldo->lokasiBarang->nama,
            'satuan' => $saldo->barang->satuanBarang->nama,
            'ringkasan' => $saldo->barang->kodeKlasifikasi().' - '.$saldo->lokasiBarang->nama,
        ];
    }

    private function daftarBarang(string $jenis, ?int $penerimaanId): Collection
    {
        return Barang::query()
            ->where('aktif', true)
            ->when(
                $jenis === 'unit',
                fn (Builder $query) => $query->where('jenis_barang', 'tidak_habis_pakai'),
                fn (Builder $query) => $query->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai']),
            )
            ->when($penerimaanId, fn (Builder $query) => $query->whereHas(
                'detailPenerimaanBarang',
                fn (Builder $query) => $query->where('penerimaan_barang_id', $penerimaanId),
            ))
            ->orderBy('nama')
            ->get();
    }

    private function jenisLabelBawaan(?int $penerimaanId): string
    {
        if (! $penerimaanId) {
            return 'unit';
        }

        return UnitBarang::query()
            ->whereHas(
                'detailPenerimaanBarang',
                fn (Builder $query) => $query->where('penerimaan_barang_id', $penerimaanId),
            )->exists() ? 'unit' : 'stok';
    }

    private function pilihan(object $item): array
    {
        return [
            'id' => (int) $item->id,
            'nama' => $item->nama,
            'kode' => $item->kode,
        ];
    }
}
