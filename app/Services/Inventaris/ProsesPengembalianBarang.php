<?php

namespace App\Services\Inventaris;

use App\Models\DetailPeminjamanBarang;
use App\Models\PengembalianBarang;
use App\Models\PeminjamanBarang;
use App\Models\UnitBarang;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProsesPengembalianBarang
{
    public function __construct(
        private readonly ProsesMutasiStokBarang $prosesMutasiStokBarang,
    ) {
    }

    public function catat(PeminjamanBarang $peminjamanBarang, array $data, ?int $penggunaId = null): PengembalianBarang
    {
        return DB::transaction(function () use ($peminjamanBarang, $data, $penggunaId) {
            $peminjamanBarang = PeminjamanBarang::query()->lockForUpdate()->findOrFail($peminjamanBarang->id);

            if ($peminjamanBarang->status === 'selesai') {
                throw ValidationException::withMessages([
                    'items' => 'Seluruh barang pada transaksi ini sudah selesai dikembalikan.',
                ]);
            }

            $daftarItem = $this->rapikanDaftarItem($data['items'] ?? []);

            if ($daftarItem === []) {
                throw ValidationException::withMessages([
                    'items' => 'Pilih minimal satu barang yang dikembalikan.',
                ]);
            }

            $pengembalianBarang = PengembalianBarang::create([
                'nomor_pengembalian' => 'TMP-' . Str::uuid(),
                'peminjaman_barang_id' => $peminjamanBarang->id,
                'tanggal_pengembalian' => $data['tanggal_pengembalian'],
                'catatan' => $data['catatan'] ?? null,
                'dibuat_oleh_pengguna_id' => $penggunaId,
            ]);

            $pengembalianBarang->update([
                'nomor_pengembalian' => $this->buatNomor('KMB', $pengembalianBarang->id, $data['tanggal_pengembalian']),
            ]);

            foreach ($daftarItem as $item) {
                $this->catatDetail($peminjamanBarang, $pengembalianBarang, $item, $penggunaId);
            }

            $masihAdaBarangYangHarusKembali = DetailPeminjamanBarang::query()
                ->where('peminjaman_barang_id', $peminjamanBarang->id)
                ->where('wajib_dikembalikan', true)
                ->whereColumn('jumlah_dikembalikan', '<', 'jumlah')
                ->exists();

            $peminjamanBarang->update([
                'status' => $masihAdaBarangYangHarusKembali ? 'sebagian_dikembalikan' : 'selesai',
            ]);

            return $pengembalianBarang->fresh();
        });
    }

    private function rapikanDaftarItem(array $daftarItem): array
    {
        $hasil = [];

        foreach ($daftarItem as $item) {
            $detailId = (int) ($item['detail_peminjaman_barang_id'] ?? 0);
            $jumlah = $this->keSatuanTerkecil($item['jumlah'] ?? 0);

            if (! $detailId || $jumlah <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Barang dan jumlah pengembalian harus diisi dengan benar.',
                ]);
            }

            if (isset($hasil[$detailId])) {
                throw ValidationException::withMessages([
                    'items' => 'Barang yang sama tidak dapat dicatat dua kali dalam satu pengembalian.',
                ]);
            }

            $hasil[$detailId] = [
                'detail_peminjaman_barang_id' => $detailId,
                'jumlah' => $jumlah,
                'kondisi_pengembalian' => $item['kondisi_pengembalian'] ?? null,
                'cara_input_barang' => $item['cara_input_barang'] ?? 'manual',
                'catatan' => $item['catatan'] ?? null,
            ];
        }

        ksort($hasil);

        return array_values($hasil);
    }

    private function catatDetail(
        PeminjamanBarang $peminjamanBarang,
        PengembalianBarang $pengembalianBarang,
        array $item,
        ?int $penggunaId,
    ): void {
        $detail = DetailPeminjamanBarang::query()
            ->with(['barang', 'unitBarang'])
            ->lockForUpdate()
            ->findOrFail($item['detail_peminjaman_barang_id']);

        if ((int) $detail->peminjaman_barang_id !== (int) $peminjamanBarang->id || ! $detail->wajib_dikembalikan) {
            throw ValidationException::withMessages([
                'items' => 'Barang yang dipilih tidak termasuk daftar barang yang perlu dikembalikan.',
            ]);
        }

        $jumlahBelumDikembalikan = $this->keSatuanTerkecil($detail->jumlah) - $this->keSatuanTerkecil($detail->jumlah_dikembalikan);

        if ($item['jumlah'] > $jumlahBelumDikembalikan) {
            throw ValidationException::withMessages([
                'items' => 'Jumlah pengembalian ' . ($detail->barang?->nama ?: '-') . ' melebihi sisa yang belum dikembalikan.',
            ]);
        }

        if ($detail->tipe_pengelolaan === 'aset_individual') {
            $this->kembalikanUnitBarang($detail, $item);
        } else {
            $this->kembalikanStokBarang($peminjamanBarang, $detail, $item, $pengembalianBarang, $penggunaId);
        }

        $detail->update([
            'jumlah_dikembalikan' => $this->formatJumlah($this->keSatuanTerkecil($detail->jumlah_dikembalikan) + $item['jumlah']),
        ]);

        $pengembalianBarang->detailPengembalianBarang()->create([
            'detail_peminjaman_barang_id' => $detail->id,
            'jumlah' => $this->formatJumlah($item['jumlah']),
            'kondisi_pengembalian' => $item['kondisi_pengembalian'],
            'cara_input_barang' => $item['cara_input_barang'],
            'catatan' => $item['catatan'],
        ]);
    }

    private function kembalikanUnitBarang(DetailPeminjamanBarang $detail, array $item): void
    {
        if ($item['jumlah'] !== 100) {
            throw ValidationException::withMessages([
                'items' => 'Jumlah pengembalian untuk satu unit aset harus bernilai 1.',
            ]);
        }

        if (! in_array($item['kondisi_pengembalian'], array_keys(UnitBarang::DAFTAR_KONDISI), true)) {
            throw ValidationException::withMessages([
                'items' => 'Kondisi pengembalian unit aset wajib dipilih.',
            ]);
        }

        $unitBarang = UnitBarang::query()->lockForUpdate()->findOrFail($detail->unit_barang_id);

        $unitBarang->update([
            'kondisi' => $item['kondisi_pengembalian'],
            'status_unit' => $item['kondisi_pengembalian'] === 'baik' ? 'tersedia' : 'dalam_perbaikan',
        ]);
    }

    private function kembalikanStokBarang(
        PeminjamanBarang $peminjamanBarang,
        DetailPeminjamanBarang $detail,
        array $item,
        PengembalianBarang $pengembalianBarang,
        ?int $penggunaId,
    ): void {
        $this->prosesMutasiStokBarang->catat([
            'barang_id' => $detail->barang_id,
            'lokasi_barang_id' => $detail->lokasi_barang_id,
            'jenis_mutasi' => 'masuk',
            'kategori_mutasi' => 'pengembalian',
            'tanggal_mutasi' => $pengembalianBarang->tanggal_pengembalian->toDateString(),
            'jumlah' => $this->formatJumlah($item['jumlah']),
            'referensi' => $peminjamanBarang->nomor_peminjaman,
            'keterangan' => 'Dicatat dari ' . $pengembalianBarang->nomor_pengembalian . '.',
        ], $penggunaId);
    }

    private function buatNomor(string $awalan, int $id, mixed $tanggal): string
    {
        return $awalan . '-' . date('Ymd', strtotime((string) $tanggal)) . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    private function keSatuanTerkecil(mixed $jumlah): int
    {
        return (int) round(((float) $jumlah) * 100);
    }

    private function formatJumlah(int $jumlah): string
    {
        return number_format($jumlah / 100, 2, '.', '');
    }
}
