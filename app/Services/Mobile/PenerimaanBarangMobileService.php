<?php

namespace App\Services\Mobile;

use App\Models\Barang;
use App\Models\DetailPenerimaanBarang;
use App\Models\LokasiBarang;
use App\Models\PenerimaanBarang;
use App\Models\SumberPerolehanBarang;
use App\Models\UnitBarang;
use Illuminate\Database\Eloquent\Builder;

class PenerimaanBarangMobileService
{
    public function daftar(array $filter, bool $dapatKelola): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $sumberId = isset($filter['sumber_perolehan_barang_id'])
            ? (int) $filter['sumber_perolehan_barang_id']
            : null;
        $tanggalMulai = $filter['tanggal_mulai'] ?? null;
        $tanggalSelesai = $filter['tanggal_selesai'] ?? null;
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);

        $paginator = PenerimaanBarang::query()
            ->with([
                'sumberPerolehanBarang',
                'dibuatOleh',
                'detailPenerimaanBarang:id,penerimaan_barang_id,jumlah,harga_satuan',
            ])
            ->withCount('detailPenerimaanBarang')
            ->when($sumberId, fn (Builder $query) => $query->where('sumber_perolehan_barang_id', $sumberId))
            ->when($tanggalMulai, fn (Builder $query) => $query->whereDate('tanggal_penerimaan', '>=', $tanggalMulai))
            ->when($tanggalSelesai, fn (Builder $query) => $query->whereDate('tanggal_penerimaan', '<=', $tanggalSelesai))
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nomor_penerimaan) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nomor_dokumen, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(asal_barang, '')) LIKE ?", [$pola])
                        ->orWhereHas('sumberPerolehanBarang', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama) LIKE ?', [$pola]))
                        ->orWhereHas('detailPenerimaanBarang.barang', function (Builder $query) use ($pola) {
                            $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                                ->orWhereRaw("LOWER(COALESCE(kode, '')) LIKE ?", [$pola]);
                        });
                });
            })
            ->orderByDesc('tanggal_penerimaan')
            ->orderByDesc('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'total' => PenerimaanBarang::query()->count(),
                'hari_ini' => PenerimaanBarang::query()
                    ->where('status', PenerimaanBarang::STATUS_AKTIF)
                    ->whereDate('tanggal_penerimaan', now()->toDateString())
                    ->count(),
                'unit_aset_dibuat' => UnitBarang::query()
                    ->whereNotNull('detail_penerimaan_barang_id')
                    ->where('aktif', true)
                    ->count(),
                'jenis_stok_masuk' => PenerimaanBarang::query()
                    ->join('detail_penerimaan_barang', 'detail_penerimaan_barang.penerimaan_barang_id', '=', 'penerimaan_barang.id')
                    ->join('barang', 'barang.id', '=', 'detail_penerimaan_barang.barang_id')
                    ->where('penerimaan_barang.status', PenerimaanBarang::STATUS_AKTIF)
                    ->where('barang.jenis_barang', 'habis_pakai')
                    ->count('detail_penerimaan_barang.id'),
            ],
            'filter' => [
                'cari' => $cari,
                'sumber_perolehan_barang_id' => $sumberId,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
            ],
            'pilihan' => $this->pilihanForm(),
            'hak_akses' => ['dapat_kelola' => $dapatKelola],
            'items' => collect($paginator->items())
                ->map(fn (PenerimaanBarang $item) => $this->ringkas($item))
                ->values(),
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function detail(PenerimaanBarang $penerimaan): array
    {
        $penerimaan->load([
            'sumberPerolehanBarang',
            'dibuatOleh',
            'dibatalkanOleh',
            'detailPenerimaanBarang.barang.satuanBarang',
            'detailPenerimaanBarang.lokasiBarang',
            'detailPenerimaanBarang.mutasiStokBarang',
            'detailPenerimaanBarang.mutasiPembatalanStokBarang',
            'detailPenerimaanBarang.unitBarang.barang',
        ]);

        return $this->ringkas($penerimaan) + [
            'catatan' => $penerimaan->catatan,
            'alasan_pembatalan' => $penerimaan->alasan_pembatalan,
            'dibatalkan_pada' => $penerimaan->dibatalkan_pada?->toIso8601String(),
            'dibatalkan_pada_label' => $penerimaan->dibatalkan_pada?->locale('id')->translatedFormat('d M Y H.i'),
            'dibatalkan_oleh' => $penerimaan->dibatalkanOleh?->nama,
            'rincian' => $penerimaan->detailPenerimaanBarang
                ->map(fn (DetailPenerimaanBarang $detail) => $this->rincian($detail))
                ->values(),
        ];
    }

    public function pilihanForm(): array
    {
        return [
            'sumber_perolehan' => SumberPerolehanBarang::query()
                ->orderByDesc('aktif')->orderBy('nama')->get()
                ->map(fn (SumberPerolehanBarang $item) => $this->pilihan($item))->values(),
            'barang' => Barang::query()
                ->with('satuanBarang')
                ->where('aktif', true)
                ->orderBy('nama')->get()
                ->map(fn (Barang $item) => [
                    'id' => (int) $item->id,
                    'nama' => $item->nama,
                    'kode' => $item->kodeKlasifikasi(),
                    'jenis_barang' => $item->jenis_barang,
                    'jenis_label' => $item->labelJenisBarang(),
                    'tipe_pengelolaan' => $item->tipe_pengelolaan,
                    'satuan' => $item->satuanBarang?->nama ?? 'unit',
                ])->values(),
            'lokasi' => LokasiBarang::query()->where('aktif', true)->orderBy('nama')->get()
                ->map(fn (LokasiBarang $item) => $this->pilihan($item))->values(),
            'cara_perolehan' => $this->pilihanLabel(PenerimaanBarang::DAFTAR_CARA_PEROLEHAN),
            'kondisi' => $this->pilihanLabel(UnitBarang::DAFTAR_KONDISI),
        ];
    }

    private function ringkas(PenerimaanBarang $item): array
    {
        return [
            'id' => (int) $item->id,
            'nomor' => $item->nomor_penerimaan,
            'tanggal' => $item->tanggal_penerimaan?->toDateString(),
            'tanggal_label' => $item->tanggal_penerimaan?->locale('id')->translatedFormat('d M Y'),
            'sumber_perolehan' => $item->sumberPerolehanBarang
                ? $this->pilihan($item->sumberPerolehanBarang)
                : null,
            'cara_perolehan' => $item->cara_perolehan,
            'cara_perolehan_label' => $item->labelCaraPerolehan(),
            'status' => $item->status,
            'status_label' => $item->sudahDibatalkan() ? 'Dibatalkan' : 'Aktif',
            'nomor_dokumen' => $item->nomor_dokumen,
            'asal_barang' => $item->asal_barang,
            'dibuat_oleh' => $item->dibuatOleh?->nama,
            'jumlah_rincian' => (int) ($item->detail_penerimaan_barang_count
                ?? $item->detailPenerimaanBarang->count()),
            'nilai_total' => $item->relationLoaded('detailPenerimaanBarang')
                ? $item->nilaiTotal()
                : 0,
            'dibuat_pada' => $item->created_at?->toIso8601String(),
        ];
    }

    private function rincian(DetailPenerimaanBarang $detail): array
    {
        $barang = $detail->barang;

        return [
            'id' => (int) $detail->id,
            'barang' => [
                'id' => (int) $barang->id,
                'nama' => $barang->nama,
                'kode' => $barang->kodeKlasifikasi(),
                'jenis_barang' => $barang->jenis_barang,
                'jenis_label' => $barang->labelJenisBarang(),
                'satuan' => $barang->satuanBarang?->nama ?? 'unit',
            ],
            'lokasi' => $detail->lokasiBarang ? $this->pilihan($detail->lokasiBarang) : null,
            'jumlah' => (float) $detail->jumlah,
            'harga_satuan' => $detail->harga_satuan !== null ? (float) $detail->harga_satuan : null,
            'nilai_subtotal' => $detail->nilaiSubtotal(),
            'merek' => $detail->merek,
            'tipe' => $detail->tipe,
            'kondisi' => $detail->kondisi,
            'kondisi_label' => $detail->kondisi
                ? (UnitBarang::DAFTAR_KONDISI[$detail->kondisi] ?? str($detail->kondisi)->headline()->toString())
                : null,
            'keterangan' => $detail->keterangan,
            'mutasi_stok_id' => $detail->mutasi_stok_barang_id ? (int) $detail->mutasi_stok_barang_id : null,
            'mutasi_pembatalan_id' => $detail->mutasi_pembatalan_stok_barang_id
                ? (int) $detail->mutasi_pembatalan_stok_barang_id
                : null,
            'unit_aset' => $detail->unitBarang->map(fn (UnitBarang $unit) => [
                'id' => (int) $unit->id,
                'kode_barang_unit' => $unit->kodeBarangUnit(),
                'kode_inventaris' => $unit->kode_inventaris,
                'nomor_aset_resmi' => $unit->nomor_aset_resmi,
                'aktif' => (bool) $unit->aktif,
            ])->values(),
        ];
    }

    private function pilihan(object $item): array
    {
        return [
            'id' => (int) $item->id,
            'nama' => $item->nama,
            'kode' => $item->kode,
            'aktif' => (bool) $item->aktif,
        ];
    }

    private function pilihanLabel(array $items): array
    {
        return collect($items)
            ->map(fn (string $label, string $nilai) => ['nilai' => $nilai, 'label' => $label])
            ->values()->all();
    }
}
