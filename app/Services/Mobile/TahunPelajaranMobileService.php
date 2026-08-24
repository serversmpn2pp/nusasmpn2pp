<?php

namespace App\Services\Mobile;

use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TahunPelajaranMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);

        $paginator = TahunPelajaran::query()
            ->withCount('kelas')
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $query->whereRaw('LOWER(nama) LIKE ?', ['%'.mb_strtolower($kataKunci).'%']);
            })
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        $tahunAktif = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        return [
            'items' => collect($paginator->items())
                ->map(fn (TahunPelajaran $item) => $this->ringkas($item))
                ->values(),
            'ringkasan' => [
                'total' => TahunPelajaran::count(),
                'aktif' => TahunPelajaran::where('aktif', true)->count(),
                'nonaktif' => TahunPelajaran::where('aktif', false)->count(),
            ],
            'tahun_aktif' => $tahunAktif ? $this->ringkas($tahunAktif) : null,
            'filter' => [
                'cari' => $kataKunci,
                'status' => $status,
            ],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
            'hak_akses' => [
                'dapat_kelola' => $pengguna->memilikiIzin('tahun_pelajaran.kelola'),
            ],
        ];
    }

    public function tambah(array $data): TahunPelajaran
    {
        return DB::transaction(function () use ($data) {
            if ($data['aktif']) {
                $this->nonaktifkanTahunLain();
            }

            return TahunPelajaran::create($this->dataSimpan($data));
        });
    }

    public function ubah(TahunPelajaran $tahunPelajaran, array $data): void
    {
        DB::transaction(function () use ($tahunPelajaran, $data) {
            if ($data['aktif']) {
                $this->nonaktifkanTahunLain($tahunPelajaran->id);
            }

            $tahunPelajaran->update($this->dataSimpan($data));
        });
    }

    private function ringkas(TahunPelajaran $item): array
    {
        return [
            'id' => (int) $item->id,
            'nama' => $item->nama,
            'tanggal_mulai' => $item->tanggal_mulai?->format('Y-m-d'),
            'tanggal_selesai' => $item->tanggal_selesai?->format('Y-m-d'),
            'aktif' => (bool) $item->aktif,
            'keterangan' => $item->keterangan,
            'jumlah_kelas' => (int) ($item->kelas_count ?? $item->kelas()->count()),
        ];
    }

    private function dataSimpan(array $data): array
    {
        return [
            'nama' => trim($data['nama']),
            'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
            'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
            'aktif' => (bool) $data['aktif'],
            'keterangan' => filled($data['keterangan'] ?? null)
                ? trim($data['keterangan'])
                : null,
        ];
    }

    private function nonaktifkanTahunLain(?int $kecualiId = null): void
    {
        $query = TahunPelajaran::query()
            ->when($kecualiId, fn (Builder $query) => $query->whereKeyNot($kecualiId));

        $query->lockForUpdate()->get(['id']);
        $query->update(['aktif' => false]);
    }
}
