<?php

namespace App\Services\Inventaris;

use App\Models\Barang;
use App\Models\LokasiBarang;
use App\Models\MutasiStokBarang;
use App\Models\SaldoStokBarang;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProsesMutasiStokBarang
{
    public function catat(array $data, ?int $penggunaId = null): MutasiStokBarang
    {
        return DB::transaction(function () use ($data, $penggunaId) {
            $barang = Barang::query()->lockForUpdate()->findOrFail($data['barang_id']);
            $lokasi = LokasiBarang::query()->findOrFail($data['lokasi_barang_id']);

            $this->pastikanBarangBerbasisStok($barang);
            $this->pastikanKategoriSesuaiJenis($data['jenis_mutasi'], $data['kategori_mutasi']);

            $saldo = SaldoStokBarang::firstOrCreate(
                [
                    'barang_id' => $barang->id,
                    'lokasi_barang_id' => $lokasi->id,
                ],
                ['jumlah' => 0],
            );
            $saldo = SaldoStokBarang::query()->lockForUpdate()->findOrFail($saldo->id);

            $saldoSebelum = $this->keSatuanTerkecil($saldo->jumlah);
            $nilaiInput = $this->keSatuanTerkecil($data['jumlah']);
            $saldoSesudah = $this->hitungSaldoSesudah($data['jenis_mutasi'], $saldoSebelum, $nilaiInput);
            $perubahan = $saldoSesudah - $saldoSebelum;

            if ($perubahan === 0) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Nilai yang dimasukkan tidak mengubah saldo stok.',
                ]);
            }

            $saldo->update(['jumlah' => $this->formatJumlah($saldoSesudah)]);

            return MutasiStokBarang::create([
                'saldo_stok_barang_id' => $saldo->id,
                'barang_id' => $barang->id,
                'lokasi_barang_id' => $lokasi->id,
                'jenis_mutasi' => $data['jenis_mutasi'],
                'kategori_mutasi' => $data['kategori_mutasi'],
                'tanggal_mutasi' => $data['tanggal_mutasi'],
                'jumlah_perubahan' => $this->formatJumlah($perubahan),
                'saldo_sebelum' => $this->formatJumlah($saldoSebelum),
                'saldo_sesudah' => $this->formatJumlah($saldoSesudah),
                'referensi' => $data['referensi'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'dibuat_oleh_pengguna_id' => $penggunaId,
            ]);
        });
    }

    private function hitungSaldoSesudah(string $jenisMutasi, int $saldoSebelum, int $nilaiInput): int
    {
        if ($nilaiInput < 0) {
            throw ValidationException::withMessages([
                'jumlah' => 'Jumlah tidak boleh bernilai negatif.',
            ]);
        }

        $saldoSesudah = match ($jenisMutasi) {
            'masuk' => $saldoSebelum + $nilaiInput,
            'keluar' => $saldoSebelum - $nilaiInput,
            'penyesuaian' => $nilaiInput,
            default => throw ValidationException::withMessages([
                'jenis_mutasi' => 'Jenis mutasi stok tidak dikenali.',
            ]),
        };

        if ($saldoSesudah < 0) {
            throw ValidationException::withMessages([
                'jumlah' => 'Stok tidak mencukupi untuk pengeluaran ini.',
            ]);
        }

        return $saldoSesudah;
    }

    private function pastikanBarangBerbasisStok(Barang $barang): void
    {
        if (! in_array($barang->tipe_pengelolaan, ['stok_dikembalikan', 'habis_pakai'], true)) {
            throw ValidationException::withMessages([
                'barang_id' => 'Mutasi stok hanya dapat dicatat untuk barang berbasis jumlah.',
            ]);
        }
    }

    private function pastikanKategoriSesuaiJenis(string $jenisMutasi, string $kategoriMutasi): void
    {
        if (! in_array($kategoriMutasi, MutasiStokBarang::KATEGORI_PER_JENIS[$jenisMutasi] ?? [], true)) {
            throw ValidationException::withMessages([
                'kategori_mutasi' => 'Kategori mutasi tidak sesuai dengan jenis transaksi yang dipilih.',
            ]);
        }
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
