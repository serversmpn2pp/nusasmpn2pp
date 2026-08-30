<?php

namespace App\Services\Mobile;

use App\Models\AturanSanksiPoin;

class AturanSanksiPoinMobileService
{
    public function daftar(array $filter): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';

        $items = AturanSanksiPoin::query()
            ->withCount('sanksiPoinSiswa')
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($cari !== '', function ($query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function ($query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(deskripsi) LIKE ?', [$pola]);
                });
            })
            ->orderBy('batas_poin')
            ->get();

        return [
            'ringkasan' => [
                'total' => AturanSanksiPoin::query()->count(),
                'aktif' => AturanSanksiPoin::query()->where('aktif', true)->count(),
                'nonaktif' => AturanSanksiPoin::query()->where('aktif', false)->count(),
                'jumlah_sanksi_terpicu' => AturanSanksiPoin::query()
                    ->withCount('sanksiPoinSiswa')
                    ->get()
                    ->sum('sanksi_poin_siswa_count'),
            ],
            'filter' => [
                'cari' => $cari,
                'status' => $status,
            ],
            'hak_akses' => ['dapat_kelola' => true],
            'items' => $items
                ->map(fn (AturanSanksiPoin $item) => $this->ringkas($item))
                ->values(),
        ];
    }

    public function tambah(array $data): AturanSanksiPoin
    {
        return AturanSanksiPoin::create($this->rapikanData($data));
    }

    public function ubah(AturanSanksiPoin $aturan, array $data): void
    {
        $aturan->update($this->rapikanData($data));
    }

    public function nonaktifkan(AturanSanksiPoin $aturan): void
    {
        $aturan->update(['aktif' => false]);
    }

    public function ringkas(AturanSanksiPoin $aturan): array
    {
        return [
            'id' => (int) $aturan->id,
            'batas_poin' => (int) $aturan->batas_poin,
            'nama' => $aturan->nama,
            'deskripsi' => $aturan->deskripsi,
            'urutan' => (int) $aturan->urutan,
            'aktif' => (bool) $aturan->aktif,
            'jumlah_sanksi_terpicu' => (int) ($aturan->sanksi_poin_siswa_count ?? 0),
            'dibuat_pada' => $aturan->created_at?->toIso8601String(),
            'diperbarui_pada' => $aturan->updated_at?->toIso8601String(),
        ];
    }

    private function rapikanData(array $data): array
    {
        return [
            'batas_poin' => (int) $data['batas_poin'],
            'nama' => trim($data['nama']),
            'deskripsi' => trim($data['deskripsi']),
            'urutan' => (int) ($data['urutan'] ?? 0),
            'aktif' => (bool) $data['aktif'],
        ];
    }
}
