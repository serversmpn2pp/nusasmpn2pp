<?php

namespace App\Services\Inventaris;

use App\Models\DetailPenerimaanBarang;
use App\Models\PenerimaanBarang;
use App\Models\UnitBarang;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatalkanPenerimaanBarang
{
    public function __construct(private ProsesMutasiStokBarang $prosesMutasiStok) {}

    public function batalkan(PenerimaanBarang $penerimaanBarang, string $alasan, ?int $penggunaId): PenerimaanBarang
    {
        return DB::transaction(function () use ($penerimaanBarang, $alasan, $penggunaId) {
            $penerimaan = PenerimaanBarang::query()
                ->lockForUpdate()
                ->findOrFail($penerimaanBarang->id);

            if ($penerimaan->sudahDibatalkan()) {
                throw ValidationException::withMessages([
                    'pembatalan' => 'Penerimaan ini sudah dibatalkan sebelumnya.',
                ]);
            }

            $penerimaan->load([
                'detailPenerimaanBarang.barang',
                'detailPenerimaanBarang.lokasiBarang',
            ]);

            $this->kunciUnitAset($penerimaan);
            $this->pastikanDapatDibatalkan($penerimaan);

            foreach ($penerimaan->detailPenerimaanBarang as $detail) {
                if ($detail->barang->jenis_barang === 'habis_pakai') {
                    $this->koreksiStok($penerimaan, $detail, $alasan, $penggunaId);

                    continue;
                }

                $this->nonaktifkanUnit($penerimaan, $detail, $alasan);
            }

            $penerimaan->update([
                'status' => PenerimaanBarang::STATUS_DIBATALKAN,
                'alasan_pembatalan' => $alasan,
                'dibatalkan_pada' => now(),
                'dibatalkan_oleh_pengguna_id' => $penggunaId,
            ]);

            return $penerimaan->fresh([
                'sumberPerolehanBarang',
                'dibuatOleh',
                'dibatalkanOleh',
                'detailPenerimaanBarang.barang.satuanBarang',
                'detailPenerimaanBarang.lokasiBarang',
                'detailPenerimaanBarang.mutasiStokBarang',
                'detailPenerimaanBarang.mutasiPembatalanStokBarang',
                'detailPenerimaanBarang.unitBarang',
            ]);
        });
    }

    private function kunciUnitAset(PenerimaanBarang $penerimaan): void
    {
        foreach ($penerimaan->detailPenerimaanBarang as $detail) {
            if ($detail->barang->jenis_barang === 'habis_pakai') {
                continue;
            }

            $unit = UnitBarang::query()
                ->where('detail_penerimaan_barang_id', $detail->id)
                ->lockForUpdate()
                ->with('detailPeminjamanBarang')
                ->orderBy('id')
                ->get();

            $detail->setRelation('unitBarang', $unit);
        }
    }

    private function pastikanDapatDibatalkan(PenerimaanBarang $penerimaan): void
    {
        foreach ($penerimaan->detailPenerimaanBarang as $detail) {
            if ($detail->barang->jenis_barang === 'habis_pakai') {
                if (! $detail->mutasi_stok_barang_id || $detail->mutasi_pembatalan_stok_barang_id) {
                    throw ValidationException::withMessages([
                        'pembatalan' => "Riwayat stok {$detail->barang->nama} tidak lengkap sehingga penerimaan belum aman dibatalkan.",
                    ]);
                }

                continue;
            }

            $jumlahSeharusnya = (int) round((float) $detail->jumlah);
            if ($detail->unitBarang->count() !== $jumlahSeharusnya) {
                throw ValidationException::withMessages([
                    'pembatalan' => "Jumlah unit {$detail->barang->nama} tidak sesuai dengan penerimaan sehingga belum aman dibatalkan.",
                ]);
            }

            foreach ($detail->unitBarang as $unit) {
                if ($unit->detailPeminjamanBarang->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'pembatalan' => "Aset {$unit->kode_inventaris} sudah memiliki riwayat peminjaman dan tidak dapat dibatalkan.",
                    ]);
                }

                if (! $unit->aktif || $unit->status_unit !== 'tersedia') {
                    throw ValidationException::withMessages([
                        'pembatalan' => "Aset {$unit->kode_inventaris} sudah tidak berstatus tersedia dan tidak dapat dibatalkan.",
                    ]);
                }

                if ((int) $unit->lokasi_barang_id !== (int) $detail->lokasi_barang_id) {
                    throw ValidationException::withMessages([
                        'pembatalan' => "Aset {$unit->kode_inventaris} sudah berpindah lokasi dan tidak dapat dibatalkan.",
                    ]);
                }
            }
        }
    }

    private function koreksiStok(
        PenerimaanBarang $penerimaan,
        DetailPenerimaanBarang $detail,
        string $alasan,
        ?int $penggunaId,
    ): void {
        try {
            $mutasi = $this->prosesMutasiStok->catat([
                'barang_id' => $detail->barang_id,
                'lokasi_barang_id' => $detail->lokasi_barang_id,
                'jenis_mutasi' => 'keluar',
                'kategori_mutasi' => 'lainnya',
                'tanggal_mutasi' => now()->toDateString(),
                'jumlah' => $detail->jumlah,
                'referensi' => 'BATAL-'.$penerimaan->nomor_penerimaan,
                'keterangan' => "Pembatalan penerimaan {$penerimaan->nomor_penerimaan}: {$alasan}",
            ], $penggunaId);
        } catch (ValidationException $exception) {
            if ($exception->validator->errors()->has('jumlah')) {
                throw ValidationException::withMessages([
                    'pembatalan' => "Stok {$detail->barang->nama} di {$detail->lokasiBarang->nama} tidak mencukupi untuk membatalkan penerimaan ini.",
                ]);
            }

            throw $exception;
        }

        $detail->update(['mutasi_pembatalan_stok_barang_id' => $mutasi->id]);
    }

    private function nonaktifkanUnit(
        PenerimaanBarang $penerimaan,
        DetailPenerimaanBarang $detail,
        string $alasan,
    ): void {
        foreach ($detail->unitBarang as $unit) {
            $catatanPembatalan = "Dibatalkan dari penerimaan {$penerimaan->nomor_penerimaan}: {$alasan}";

            $unit->update([
                'status_unit' => 'dihapuskan',
                'aktif' => false,
                'keterangan' => filled($unit->keterangan)
                    ? trim($unit->keterangan)."\n\n{$catatanPembatalan}"
                    : $catatanPembatalan,
            ]);
        }
    }
}
