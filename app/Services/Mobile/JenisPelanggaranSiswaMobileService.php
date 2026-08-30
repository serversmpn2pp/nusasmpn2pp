<?php

namespace App\Services\Mobile;

use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use App\Models\LaporanPembinaanSiswa;

class JenisPelanggaranSiswaMobileService
{
    public function daftar(array $filter): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $tingkat = $filter['tingkat'] ?? 'semua';
        $kategoriId = isset($filter['kategori_id']) ? (int) $filter['kategori_id'] : null;
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);

        $paginator = JenisPelanggaranSiswa::query()
            ->with('kategoriPembinaanSiswa:id,nama,kode,aktif')
            ->withCount('butirPelanggaranLaporan')
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($tingkat !== 'semua', fn ($query) => $query->where('tingkat', $tingkat))
            ->when($kategoriId, fn ($query) => $query->where('kategori_pembinaan_siswa_id', $kategoriId))
            ->when($cari !== '', function ($query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function ($query) use ($pola) {
                    $query->whereRaw('LOWER(kode) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(nama) LIKE ?', [$pola]);
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'total' => JenisPelanggaranSiswa::query()->count(),
                'aktif' => JenisPelanggaranSiswa::query()->where('aktif', true)->count(),
                'nonaktif' => JenisPelanggaranSiswa::query()->where('aktif', false)->count(),
                'per_tingkat' => collect(LaporanPembinaanSiswa::DAFTAR_TINGKAT)
                    ->mapWithKeys(fn ($label, $kode) => [
                        $kode => JenisPelanggaranSiswa::query()->where('tingkat', $kode)->count(),
                    ]),
            ],
            'filter' => [
                'cari' => $cari,
                'status' => $status,
                'tingkat' => $tingkat,
                'kategori_id' => $kategoriId,
            ],
            'hak_akses' => ['dapat_kelola' => true],
            'referensi' => [
                'tingkat' => collect(LaporanPembinaanSiswa::DAFTAR_TINGKAT)
                    ->map(fn ($label, $kode) => ['kode' => $kode, 'label' => $label])
                    ->values(),
                'kategori' => KategoriPembinaanSiswa::query()
                    ->orderByDesc('aktif')
                    ->orderBy('nama')
                    ->get(['id', 'nama', 'kode', 'aktif'])
                    ->map(fn (KategoriPembinaanSiswa $kategori) => [
                        'id' => (int) $kategori->id,
                        'nama' => $kategori->nama,
                        'kode' => $kategori->kode,
                        'aktif' => (bool) $kategori->aktif,
                    ])
                    ->values(),
            ],
            'items' => collect($paginator->items())
                ->map(fn (JenisPelanggaranSiswa $item) => $this->ringkas($item))
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

    public function tambah(array $data): JenisPelanggaranSiswa
    {
        return JenisPelanggaranSiswa::create($this->rapikanData($data));
    }

    public function ubah(JenisPelanggaranSiswa $jenis, array $data): void
    {
        $jenis->update($this->rapikanData($data));
    }

    public function nonaktifkan(JenisPelanggaranSiswa $jenis): void
    {
        $jenis->update(['aktif' => false]);
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

    public function ringkas(JenisPelanggaranSiswa $jenis): array
    {
        return [
            'id' => (int) $jenis->id,
            'kode' => $jenis->kode,
            'nama' => $jenis->nama,
            'tingkat' => $jenis->tingkat,
            'tingkat_label' => LaporanPembinaanSiswa::DAFTAR_TINGKAT[$jenis->tingkat] ?? str($jenis->tingkat)->headline()->toString(),
            'poin' => (int) $jenis->poin,
            'urutan' => (int) $jenis->urutan,
            'aktif' => (bool) $jenis->aktif,
            'kategori' => $jenis->kategoriPembinaanSiswa ? [
                'id' => (int) $jenis->kategoriPembinaanSiswa->id,
                'nama' => $jenis->kategoriPembinaanSiswa->nama,
                'kode' => $jenis->kategoriPembinaanSiswa->kode,
                'aktif' => (bool) $jenis->kategoriPembinaanSiswa->aktif,
            ] : null,
            'jumlah_pemakaian' => (int) ($jenis->butir_pelanggaran_laporan_count ?? 0),
            'dibuat_pada' => $jenis->created_at?->toIso8601String(),
            'diperbarui_pada' => $jenis->updated_at?->toIso8601String(),
        ];
    }

    private function rapikanData(array $data): array
    {
        return [
            'kategori_pembinaan_siswa_id' => $data['kategori_pembinaan_siswa_id'] ?? null,
            'kode' => $this->rapikanKode($data['kode']),
            'nama' => trim($data['nama']),
            'tingkat' => $data['tingkat'],
            'poin' => (int) $data['poin'],
            'urutan' => (int) ($data['urutan'] ?? 0),
            'aktif' => (bool) $data['aktif'],
        ];
    }
}
