<?php

namespace App\Services\Mobile;

use App\Models\SumberPerolehanBarang;
use Illuminate\Database\Eloquent\Builder;

class SumberPerolehanBarangMobileService
{
    public function daftar(array $filter, bool $dapatKelola): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);

        $paginator = SumberPerolehanBarang::query()
            ->withCount('unitBarang')
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(kode) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(deskripsi, '')) LIKE ?", [$pola]);
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'total' => SumberPerolehanBarang::query()->count(),
                'aktif' => SumberPerolehanBarang::query()->where('aktif', true)->count(),
                'nonaktif' => SumberPerolehanBarang::query()->where('aktif', false)->count(),
            ],
            'filter' => [
                'cari' => $cari,
                'status' => $status,
            ],
            'hak_akses' => [
                'dapat_kelola' => $dapatKelola,
            ],
            'items' => collect($paginator->items())
                ->map(fn (SumberPerolehanBarang $item) => $this->ringkas($item))
                ->values(),
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function tambah(array $data): SumberPerolehanBarang
    {
        return SumberPerolehanBarang::create($this->rapikanData($data));
    }

    public function ubah(SumberPerolehanBarang $sumber, array $data): void
    {
        $sumber->update($this->rapikanData($data));
    }

    public function nonaktifkan(SumberPerolehanBarang $sumber): void
    {
        $sumber->update(['aktif' => false]);
    }

    public function rapikanKode(mixed $kode): string
    {
        return str((string) $kode)
            ->trim()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    public function ringkas(SumberPerolehanBarang $sumber): array
    {
        return [
            'id' => (int) $sumber->id,
            'nama' => $sumber->nama,
            'kode' => $sumber->kode,
            'deskripsi' => $sumber->deskripsi,
            'aktif' => (bool) $sumber->aktif,
            'jumlah_unit_aset' => (int) ($sumber->unit_barang_count ?? $sumber->unitBarang()->count()),
            'dibuat_pada' => $sumber->created_at?->toIso8601String(),
            'diperbarui_pada' => $sumber->updated_at?->toIso8601String(),
        ];
    }

    private function rapikanData(array $data): array
    {
        return [
            'nama' => trim($data['nama']),
            'kode' => $this->rapikanKode($data['kode']),
            'deskripsi' => filled($data['deskripsi'] ?? null)
                ? trim($data['deskripsi'])
                : null,
            'aktif' => (bool) $data['aktif'],
        ];
    }
}
