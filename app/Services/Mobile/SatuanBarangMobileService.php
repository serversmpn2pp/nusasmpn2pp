<?php

namespace App\Services\Mobile;

use App\Models\SatuanBarang;
use Illuminate\Database\Eloquent\Builder;

class SatuanBarangMobileService
{
    public function daftar(array $filter, bool $dapatKelola): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);

        $paginator = SatuanBarang::query()
            ->withCount('barang')
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
                'total' => SatuanBarang::query()->count(),
                'aktif' => SatuanBarang::query()->where('aktif', true)->count(),
                'nonaktif' => SatuanBarang::query()->where('aktif', false)->count(),
            ],
            'filter' => [
                'cari' => $cari,
                'status' => $status,
            ],
            'hak_akses' => [
                'dapat_kelola' => $dapatKelola,
            ],
            'items' => collect($paginator->items())
                ->map(fn (SatuanBarang $item) => $this->ringkas($item))
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

    public function tambah(array $data): SatuanBarang
    {
        return SatuanBarang::create($this->rapikanData($data));
    }

    public function ubah(SatuanBarang $satuan, array $data): void
    {
        $satuan->update($this->rapikanData($data));
    }

    public function nonaktifkan(SatuanBarang $satuan): void
    {
        $satuan->update(['aktif' => false]);
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

    public function ringkas(SatuanBarang $satuan): array
    {
        return [
            'id' => (int) $satuan->id,
            'nama' => $satuan->nama,
            'kode' => $satuan->kode,
            'deskripsi' => $satuan->deskripsi,
            'aktif' => (bool) $satuan->aktif,
            'jumlah_barang' => (int) ($satuan->barang_count ?? $satuan->barang()->count()),
            'dibuat_pada' => $satuan->created_at?->toIso8601String(),
            'diperbarui_pada' => $satuan->updated_at?->toIso8601String(),
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
