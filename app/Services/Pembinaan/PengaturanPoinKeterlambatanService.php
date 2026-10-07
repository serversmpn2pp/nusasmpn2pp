<?php

namespace App\Services\Pembinaan;

use App\Models\PengaturanPoinKeterlambatan;
use App\Models\RentangPoinKeterlambatan;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PengaturanPoinKeterlambatanService
{
    public function langsungPada(int $tahunPelajaranId, string $tanggal): bool
    {
        return PengaturanPoinKeterlambatan::where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('aktif', true)->where('otomatis_langsung', true)
            ->whereDate('berlaku_mulai', '<=', $tanggal)->exists();
    }

    public function simpanModeLangsung(PengaturanPoinKeterlambatan $pengaturan, array $data): void
    {
        if (! array_key_exists('otomatis_langsung', $data)) {
            if (! $pengaturan->aktif && $pengaturan->otomatis_langsung) {
                $pengaturan->update(['otomatis_langsung' => false]);
            }

            return;
        }
        $aktif = (bool) $data['otomatis_langsung'];
        if ($aktif && ! $pengaturan->aktif) {
            throw ValidationException::withMessages(['aktif' => 'Aktifkan otomatisasi untuk menggunakan poin langsung.']);
        }
        $nilai = [
            'otomatis_langsung' => $aktif,
            'poin_terlambat' => (int) ($data['poin_terlambat'] ?? $pengaturan->poin_terlambat ?? 15),
            'poin_alfa' => (int) ($data['poin_alfa'] ?? $pengaturan->poin_alfa ?? 25),
        ];
        if ($aktif && (! $pengaturan->otomatis_langsung || ! $pengaturan->berlaku_mulai)) {
            // The server sets the activation date; neither web nor API may backdate it.
            $nilai['berlaku_mulai'] = now()->toDateString();
            $nilai['alfa_diproses_sampai'] = now()->subDay()->toDateString();
        }
        $pengaturan->update($nilai);
    }

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
