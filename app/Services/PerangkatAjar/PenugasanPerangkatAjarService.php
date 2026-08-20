<?php

namespace App\Services\PerangkatAjar;

use App\Models\GuruMataPelajaran;
use Illuminate\Support\Collection;

class PenugasanPerangkatAjarService
{
    public function untukGuru(?int $pegawaiId, ?int $tahunPelajaranId): Collection
    {
        if (! $pegawaiId || ! $tahunPelajaranId) {
            return collect();
        }

        $penugasan = GuruMataPelajaran::query()
            ->with(['mataPelajaran', 'kelas'])
            ->where('pegawai_id', $pegawaiId)
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('aktif', true)
            ->whereHas('mataPelajaran', fn ($query) => $query->where('aktif', true))
            ->whereHas('kelas', fn ($query) => $query
                ->where('aktif', true)
                ->whereIn('tingkat', [7, 8, 9]))
            ->get();

        return $this->ringkas($penugasan);
    }

    public function ringkas(Collection $penugasan): Collection
    {
        return $penugasan
            ->filter(fn (GuruMataPelajaran $item) => (
                $item->mataPelajaran
                && $item->mataPelajaran->aktif
                && $item->kelas
                && $item->kelas->aktif
                && in_array((int) $item->kelas->tingkat, [7, 8, 9], true)
            ))
            ->map(function (GuruMataPelajaran $item) {
                $tingkat = (int) $item->kelas->tingkat;

                return [
                    'mata_pelajaran' => $item->mataPelajaran,
                    'mata_pelajaran_id' => (int) $item->mata_pelajaran_id,
                    'tingkat' => $tingkat,
                    'label_tingkat' => $this->labelTingkat($tingkat),
                    'kunci' => $this->kunci((int) $item->mata_pelajaran_id, $tingkat),
                ];
            })
            ->unique('kunci')
            ->sortBy(fn (array $item) => sprintf(
                '%05d-%s-%02d',
                (int) ($item['mata_pelajaran']->urutan ?? 0),
                mb_strtolower((string) $item['mata_pelajaran']->nama),
                $item['tingkat'],
            ))
            ->values();
    }

    public function kunci(int $mataPelajaranId, int $tingkat): string
    {
        return $mataPelajaranId.'-'.$tingkat;
    }

    public function labelTingkat(int $tingkat): string
    {
        return match ($tingkat) {
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            default => (string) $tingkat,
        };
    }
}
