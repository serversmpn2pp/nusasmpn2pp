<?php

namespace App\Services\Mobile;

use App\Models\JenisPerangkatAjar;
use Illuminate\Database\Eloquent\Builder;

class JenisPerangkatAjarMobileService
{
    public function daftar(array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $kewajiban = $filter['kewajiban'] ?? 'semua';
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);

        $paginator = JenisPerangkatAjar::query()
            ->withCount('perangkatAjar')
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($kewajiban === 'wajib', fn (Builder $query) => $query->where('wajib', true))
            ->when($kewajiban === 'opsional', fn (Builder $query) => $query->where('wajib', false))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(kode) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(deskripsi, '')) LIKE ?", [$pola]);
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())
                ->map(fn (JenisPerangkatAjar $item) => $this->ringkas($item))
                ->values(),
            'ringkasan' => [
                'total' => JenisPerangkatAjar::count(),
                'aktif' => JenisPerangkatAjar::where('aktif', true)->count(),
                'wajib' => JenisPerangkatAjar::where('wajib', true)->count(),
            ],
            'filter' => [
                'cari' => $kataKunci,
                'status' => $status,
                'kewajiban' => $kewajiban,
            ],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
            'urutan_berikutnya' => ((int) JenisPerangkatAjar::max('urutan')) + 1,
        ];
    }

    public function tambah(array $data): JenisPerangkatAjar
    {
        return JenisPerangkatAjar::create($this->rapikan($data));
    }

    public function ubah(JenisPerangkatAjar $jenis, array $data): void
    {
        $jenis->update($this->rapikan($data));
    }

    public function nonaktifkan(JenisPerangkatAjar $jenis): void
    {
        $jenis->update(['aktif' => false]);
    }

    public function ringkas(JenisPerangkatAjar $item): array
    {
        return [
            'id' => (int) $item->id,
            'kode' => $item->kode,
            'nama' => $item->nama,
            'deskripsi' => $item->deskripsi,
            'wajib' => (bool) $item->wajib,
            'urutan' => (int) $item->urutan,
            'aktif' => (bool) $item->aktif,
            'jumlah_dokumen' => (int) ($item->perangkat_ajar_count ?? $item->perangkatAjar()->count()),
        ];
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

    private function rapikan(array $data): array
    {
        return [
            'nama' => trim($data['nama']),
            'kode' => $this->rapikanKode($data['kode']),
            'deskripsi' => filled($data['deskripsi'] ?? null)
                ? trim($data['deskripsi'])
                : null,
            'wajib' => (bool) $data['wajib'],
            'urutan' => (int) ($data['urutan'] ?? 0),
            'aktif' => (bool) $data['aktif'],
        ];
    }
}
