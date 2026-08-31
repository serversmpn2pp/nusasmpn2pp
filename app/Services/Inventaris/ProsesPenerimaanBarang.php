<?php

namespace App\Services\Inventaris;

use App\Models\Barang;
use App\Models\DetailPenerimaanBarang;
use App\Models\LokasiBarang;
use App\Models\PenerimaanBarang;
use App\Models\SumberPerolehanBarang;
use App\Models\UnitBarang;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProsesPenerimaanBarang
{
    public function __construct(
        private GeneratorIdentitasInventaris $generatorIdentitas,
        private ProsesMutasiStokBarang $prosesMutasiStok,
    ) {}

    public function catat(array $data, ?int $penggunaId = null): PenerimaanBarang
    {
        $tokenPenyimpanan = $data['token_penyimpanan'] ?? null;
        $penerimaanTersimpan = $this->berdasarkanToken($tokenPenyimpanan);

        if ($penerimaanTersimpan) {
            return $this->muatPenerimaan($penerimaanTersimpan);
        }

        try {
            return DB::transaction(function () use ($data, $penggunaId, $tokenPenyimpanan) {
                $tanggal = Carbon::parse($data['tanggal_penerimaan']);
                $sumber = SumberPerolehanBarang::query()->findOrFail($data['sumber_perolehan_barang_id']);

                if (! $sumber->aktif) {
                    throw ValidationException::withMessages([
                        'sumber_perolehan_barang_id' => 'Sumber perolehan yang dipilih sudah tidak aktif.',
                    ]);
                }

                $penerimaan = PenerimaanBarang::create([
                    'token_penyimpanan' => $tokenPenyimpanan,
                    'nomor_penerimaan' => $this->generatorIdentitas->buatNomorPenerimaanBarang($tanggal->year),
                    'tanggal_penerimaan' => $tanggal->toDateString(),
                    'sumber_perolehan_barang_id' => $sumber->id,
                    'cara_perolehan' => $data['cara_perolehan'],
                    'status' => PenerimaanBarang::STATUS_AKTIF,
                    'nomor_dokumen' => $data['nomor_dokumen'] ?? null,
                    'asal_barang' => $data['asal_barang'] ?? null,
                    'catatan' => $data['catatan'] ?? null,
                    'dibuat_oleh_pengguna_id' => $penggunaId,
                ]);

                foreach ($data['rincian'] as $indeks => $rincian) {
                    $this->catatRincian($penerimaan, $sumber, $tanggal, $rincian, $indeks, $penggunaId);
                }

                return $this->muatPenerimaan($penerimaan);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $penerimaanTersimpan = $this->berdasarkanToken($tokenPenyimpanan);

            if (! $penerimaanTersimpan) {
                throw $exception;
            }

            return $this->muatPenerimaan($penerimaanTersimpan);
        }
    }

    private function berdasarkanToken(?string $tokenPenyimpanan): ?PenerimaanBarang
    {
        return filled($tokenPenyimpanan)
            ? PenerimaanBarang::query()->where('token_penyimpanan', $tokenPenyimpanan)->first()
            : null;
    }

    private function muatPenerimaan(PenerimaanBarang $penerimaan): PenerimaanBarang
    {
        return $penerimaan->load([
            'sumberPerolehanBarang',
            'dibuatOleh',
            'detailPenerimaanBarang.barang.satuanBarang',
            'detailPenerimaanBarang.lokasiBarang',
            'detailPenerimaanBarang.unitBarang',
            'detailPenerimaanBarang.mutasiStokBarang',
        ]);
    }

    private function catatRincian(
        PenerimaanBarang $penerimaan,
        SumberPerolehanBarang $sumber,
        Carbon $tanggal,
        array $rincian,
        int $indeks,
        ?int $penggunaId,
    ): void {
        $barang = Barang::query()->lockForUpdate()->findOrFail($rincian['barang_id']);
        $lokasi = LokasiBarang::query()->findOrFail($rincian['lokasi_barang_id']);

        if (! $barang->aktif) {
            throw ValidationException::withMessages([
                "rincian.$indeks.barang_id" => 'Barang yang dipilih sudah tidak aktif.',
            ]);
        }

        if (! $lokasi->aktif) {
            throw ValidationException::withMessages([
                "rincian.$indeks.lokasi_barang_id" => 'Lokasi penyimpanan yang dipilih sudah tidak aktif.',
            ]);
        }

        $jumlah = (float) $rincian['jumlah'];
        $detail = DetailPenerimaanBarang::create([
            'penerimaan_barang_id' => $penerimaan->id,
            'barang_id' => $barang->id,
            'lokasi_barang_id' => $lokasi->id,
            'jumlah' => $jumlah,
            'harga_satuan' => $rincian['harga_satuan'] ?? null,
            'merek' => $barang->jenis_barang === 'tidak_habis_pakai' ? ($rincian['merek'] ?? null) : null,
            'tipe' => $barang->jenis_barang === 'tidak_habis_pakai' ? ($rincian['tipe'] ?? null) : null,
            'kondisi' => $barang->jenis_barang === 'tidak_habis_pakai' ? ($rincian['kondisi'] ?? 'baik') : null,
            'keterangan' => $rincian['keterangan'] ?? null,
        ]);

        if ($barang->jenis_barang === 'habis_pakai') {
            $mutasi = $this->prosesMutasiStok->catat([
                'barang_id' => $barang->id,
                'lokasi_barang_id' => $lokasi->id,
                'jenis_mutasi' => 'masuk',
                'kategori_mutasi' => $this->kategoriMutasi($penerimaan->cara_perolehan),
                'tanggal_mutasi' => $tanggal->toDateString(),
                'jumlah' => $jumlah,
                'referensi' => $penerimaan->nomor_penerimaan,
                'keterangan' => $rincian['keterangan'] ?? 'Penerimaan barang datang.',
            ], $penggunaId);

            $detail->update(['mutasi_stok_barang_id' => $mutasi->id]);

            return;
        }

        if (abs($jumlah - round($jumlah)) > 0.00001) {
            throw ValidationException::withMessages([
                "rincian.$indeks.jumlah" => 'Jumlah barang tidak habis pakai harus berupa unit utuh tanpa desimal.',
            ]);
        }

        if ($jumlah > 200) {
            throw ValidationException::withMessages([
                "rincian.$indeks.jumlah" => 'Maksimal 200 unit aset dalam satu baris penerimaan.',
            ]);
        }

        $nomorTerakhir = (int) UnitBarang::where('barang_id', $barang->id)->max('nomor_unit');

        for ($urutan = 1; $urutan <= (int) round($jumlah); $urutan++) {
            UnitBarang::create([
                'barang_id' => $barang->id,
                'detail_penerimaan_barang_id' => $detail->id,
                'nomor_unit' => $nomorTerakhir + $urutan,
                'kode_inventaris' => $this->generatorIdentitas->buatKodeUnitAset($tanggal->year),
                'nomor_aset_resmi' => $this->generatorIdentitas->buatNomorAsetResmi($tanggal->year),
                'lokasi_barang_id' => $lokasi->id,
                'merek' => $rincian['merek'] ?? null,
                'tipe' => $rincian['tipe'] ?? null,
                'kondisi' => $rincian['kondisi'] ?? 'baik',
                'status_unit' => 'tersedia',
                'tanggal_perolehan' => $tanggal->toDateString(),
                'tahun_perolehan' => $tanggal->year,
                'sumber_perolehan_barang_id' => $sumber->id,
                'sumber_perolehan' => $sumber->nama,
                'harga_perolehan' => $rincian['harga_satuan'] ?? null,
                'keterangan' => $rincian['keterangan'] ?? null,
                'aktif' => true,
            ]);
        }
    }

    private function kategoriMutasi(string $caraPerolehan): string
    {
        return match ($caraPerolehan) {
            'pembelian' => 'pembelian',
            'hibah' => 'hibah',
            default => 'lainnya',
        };
    }
}
