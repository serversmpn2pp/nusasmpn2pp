<?php

namespace App\Services\Inventaris;

use App\Models\Barang;
use App\Models\PengaturanInventaris;
use App\Models\UnitBarang;
use App\Models\UrutanKodeInventaris;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GeneratorIdentitasInventaris
{
    public const JENIS_BARANG_HABIS_PAKAI = 'barang_habis_pakai';

    public const JENIS_UNIT_ASET = 'unit_aset';

    public const JENIS_PENERIMAAN_BARANG = 'penerimaan_barang';

    public function buatKodeBarangHabisPakai(): string
    {
        return DB::transaction(function () {
            $pengaturan = PengaturanInventaris::utama();
            $nomor = $this->nomorBerikutnya(self::JENIS_BARANG_HABIS_PAKAI, 0);
            $kode = $this->formatNomor('BHP-', $nomor, $pengaturan->jumlah_digit_id_internal);

            while (Barang::where('kode', $kode)->exists()) {
                $nomor = $this->nomorBerikutnya(self::JENIS_BARANG_HABIS_PAKAI, 0);
                $kode = $this->formatNomor('BHP-', $nomor, $pengaturan->jumlah_digit_id_internal);
            }

            return $kode;
        });
    }

    public function buatKodeUnitAset(int $tahun): string
    {
        $this->pastikanTahunValid($tahun);

        return DB::transaction(function () use ($tahun) {
            $pengaturan = PengaturanInventaris::utama();
            $nomor = $this->nomorBerikutnya(self::JENIS_UNIT_ASET, $tahun);
            $kode = $this->formatNomor('AST-'.$tahun.'-', $nomor, $pengaturan->jumlah_digit_id_internal);

            while (UnitBarang::where('kode_inventaris', $kode)->exists()) {
                $nomor = $this->nomorBerikutnya(self::JENIS_UNIT_ASET, $tahun);
                $kode = $this->formatNomor('AST-'.$tahun.'-', $nomor, $pengaturan->jumlah_digit_id_internal);
            }

            return $kode;
        });
    }

    public function buatNomorAsetResmi(int $tahun): string
    {
        $this->pastikanTahunValid($tahun);

        return PengaturanInventaris::utama()->contohNomorAset($tahun);
    }

    public function buatNomorPenerimaanBarang(int $tahun): string
    {
        $this->pastikanTahunValid($tahun);

        return DB::transaction(function () use ($tahun) {
            $nomor = $this->nomorBerikutnya(self::JENIS_PENERIMAAN_BARANG, $tahun);

            return $this->formatNomor('BRG-MSK-'.$tahun.'-', $nomor, 6);
        });
    }

    private function nomorBerikutnya(string $jenis, int $tahun): int
    {
        UrutanKodeInventaris::query()->insertOrIgnore([
            [
                'jenis' => $jenis,
                'tahun' => $tahun,
                'nomor_terakhir' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $urutan = UrutanKodeInventaris::query()
            ->where('jenis', $jenis)
            ->where('tahun', $tahun)
            ->lockForUpdate()
            ->firstOrFail();

        $nomorBerikutnya = (int) $urutan->nomor_terakhir + 1;
        $urutan->update(['nomor_terakhir' => $nomorBerikutnya]);

        return $nomorBerikutnya;
    }

    private function formatNomor(string $awalan, int $nomor, int $jumlahDigit): string
    {
        return $awalan.str_pad((string) $nomor, $jumlahDigit, '0', STR_PAD_LEFT);
    }

    private function pastikanTahunValid(int $tahun): void
    {
        if ($tahun < 1900 || $tahun > 2100) {
            throw new InvalidArgumentException('Tahun perolehan harus berada antara 1900 dan 2100.');
        }
    }
}
