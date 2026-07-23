<?php

namespace App\Services\Pembinaan;

use App\Models\PengaturanPeringatanDiniPoin;

class PengaturanPeringatanDiniPoinService
{
    public function nilaiUntukTahun(int $tahunPelajaranId): PengaturanPeringatanDiniPoin
    {
        return PengaturanPeringatanDiniPoin::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->first()
            ?? new PengaturanPeringatanDiniPoin([
                'tahun_pelajaran_id' => $tahunPelajaranId,
                'aktif' => true,
                'persentase_mendekati_ambang' => 80,
                'jumlah_pelanggaran_berulang' => 3,
                'periode_pelanggaran_hari' => 30,
                'jumlah_keterlambatan_berulang' => 3,
                'periode_keterlambatan_hari' => 30,
                'notifikasi_aktif' => true,
            ]);
    }
}
