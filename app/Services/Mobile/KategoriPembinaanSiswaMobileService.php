<?php

namespace App\Services\Mobile;

use App\Models\KategoriPembinaanSiswa;

class KategoriPembinaanSiswaMobileService
{
    public function daftar(array $filter, bool $dapatKelola): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);

        $paginator = KategoriPembinaanSiswa::query()
            ->withCount(['laporanPembinaanSiswa', 'jenisPelanggaranSiswa'])
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($cari !== '', function ($query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function ($query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(kode) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(COALESCE(deskripsi, \'\')) LIKE ?', [$pola]);
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'total' => KategoriPembinaanSiswa::query()->count(),
                'aktif' => KategoriPembinaanSiswa::query()->where('aktif', true)->count(),
                'nonaktif' => KategoriPembinaanSiswa::query()->where('aktif', false)->count(),
            ],
            'filter' => [
                'cari' => $cari,
                'status' => $status,
            ],
            'hak_akses' => [
                'dapat_kelola' => $dapatKelola,
            ],
            'items' => collect($paginator->items())
                ->map(fn (KategoriPembinaanSiswa $item) => $this->ringkas($item))
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

    public function tambah(array $data): KategoriPembinaanSiswa
    {
        return KategoriPembinaanSiswa::create($this->rapikanData($data));
    }

    public function ubah(KategoriPembinaanSiswa $kategori, array $data): void
    {
        $kategori->update($this->rapikanData($data));
    }

    public function nonaktifkan(KategoriPembinaanSiswa $kategori): void
    {
        $kategori->update(['aktif' => false]);
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

    public function ringkas(KategoriPembinaanSiswa $kategori): array
    {
        return [
            'id' => (int) $kategori->id,
            'nama' => $kategori->nama,
            'kode' => $kategori->kode,
            'deskripsi' => $kategori->deskripsi,
            'aktif' => (bool) $kategori->aktif,
            'jumlah_laporan' => (int) ($kategori->laporan_pembinaan_siswa_count ?? 0),
            'jumlah_jenis_pelanggaran' => (int) ($kategori->jenis_pelanggaran_siswa_count ?? 0),
            'dibuat_pada' => $kategori->created_at?->toIso8601String(),
            'diperbarui_pada' => $kategori->updated_at?->toIso8601String(),
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
