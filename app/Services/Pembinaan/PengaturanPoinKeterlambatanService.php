<?php

namespace App\Services\Pembinaan;

use App\Models\PengaturanPoinKeterlambatan;
use App\Models\RentangPoinKeterlambatan;
use Illuminate\Support\Collection;

class PengaturanPoinKeterlambatanService
{
    public function nilaiUntukTahun(int $tahunPelajaranId): PengaturanPoinKeterlambatan
    {
        $pengaturan = PengaturanPoinKeterlambatan::query()
            ->with('rentangPoinKeterlambatan')
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->first();

        if ($pengaturan) {
            return $pengaturan;
        }

        $pengaturan = new PengaturanPoinKeterlambatan([
            'tahun_pelajaran_id' => $tahunPelajaranId,
            'aktif' => false,
        ]);
        $pengaturan->setRelation('rentangPoinKeterlambatan', $this->rentangBawaan());

        return $pengaturan;
    }

    public function rentangUntukMenit(int $tahunPelajaranId, int $menit): ?RentangPoinKeterlambatan
    {
        if ($menit < 1) {
            return null;
        }

        return RentangPoinKeterlambatan::query()
            ->whereHas('pengaturanPoinKeterlambatan', fn ($query) => $query
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('aktif', true))
            ->where('menit_mulai', '<=', $menit)
            ->where(fn ($query) => $query->whereNull('menit_selesai')->orWhere('menit_selesai', '>=', $menit))
            ->orderByDesc('menit_mulai')
            ->first();
    }

    /** @return Collection<int, RentangPoinKeterlambatan> */
    private function rentangBawaan(): Collection
    {
        return collect([
            new RentangPoinKeterlambatan(['menit_mulai' => 1, 'menit_selesai' => 10, 'poin' => 0, 'urutan' => 1]),
            new RentangPoinKeterlambatan(['menit_mulai' => 11, 'menit_selesai' => null, 'poin' => 15, 'urutan' => 2]),
        ]);
    }
}
