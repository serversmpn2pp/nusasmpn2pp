<?php

namespace App\Services\Mobile;

use App\Models\KegiatanIbadah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class KegiatanIbadahMobileService
{
    public function daftar(array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);

        $paginator = KegiatanIbadah::query()
            ->withCount([
                'jadwal',
                'jadwal as jumlah_jadwal_aktif' => fn (Builder $query) => $query->where('aktif', true),
            ])
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(kode) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(keterangan, '')) LIKE ?", [$pola]);
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())
                ->map(fn (KegiatanIbadah $item) => $this->ringkas($item))
                ->values(),
            'ringkasan' => [
                'total' => KegiatanIbadah::count(),
                'aktif' => KegiatanIbadah::where('aktif', true)->count(),
                'nonaktif' => KegiatanIbadah::where('aktif', false)->count(),
            ],
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
        ];
    }

    public function tambah(array $data): KegiatanIbadah
    {
        return KegiatanIbadah::create($this->rapikan($data));
    }

    public function ubah(KegiatanIbadah $kegiatan, array $data): void
    {
        $kegiatan->update($this->rapikan($data));
    }

    public function nonaktifkan(KegiatanIbadah $kegiatan): void
    {
        DB::transaction(function () use ($kegiatan) {
            $kegiatan->update(['aktif' => false]);
            $kegiatan->jadwal()->update(['aktif' => false]);
        });
    }

    public function ringkas(KegiatanIbadah $item): array
    {
        return [
            'id' => (int) $item->id,
            'kode' => $item->kode,
            'nama' => $item->nama,
            'aktif' => (bool) $item->aktif,
            'keterangan' => $item->keterangan,
            'khusus_laki_laki' => $item->khususLakiLaki(),
            'cakupan_peserta' => $item->labelCakupanPeserta(),
            'jumlah_jadwal' => (int) ($item->jadwal_count ?? $item->jadwal()->count()),
            'jumlah_jadwal_aktif' => (int) ($item->jumlah_jadwal_aktif ?? $item->jadwal()->where('aktif', true)->count()),
        ];
    }

    public function rapikanKode(mixed $kode): string
    {
        return str((string) $kode)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    private function rapikan(array $data): array
    {
        return [
            'kode' => $this->rapikanKode($data['kode']),
            'nama' => trim($data['nama']),
            'aktif' => (bool) $data['aktif'],
            'keterangan' => filled($data['keterangan'] ?? null)
                ? trim($data['keterangan'])
                : null,
        ];
    }
}
