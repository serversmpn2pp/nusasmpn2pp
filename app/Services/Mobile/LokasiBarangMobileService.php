<?php

namespace App\Services\Mobile;

use App\Models\LokasiBarang;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Builder;

class LokasiBarangMobileService
{
    public function daftar(array $filter, bool $dapatKelola): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $jenis = $filter['jenis'] ?? 'semua';
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);

        $paginator = LokasiBarang::query()
            ->with('penanggungJawab:id,nama_lengkap,nip')
            ->withCount('barangSebagaiPenyimpanan')
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($jenis !== 'semua', fn (Builder $query) => $query->where('jenis', $jenis))
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(kode) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(deskripsi, '')) LIKE ?", [$pola])
                        ->orWhereHas('penanggungJawab', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola]));
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'total' => LokasiBarang::query()->count(),
                'aktif' => LokasiBarang::query()->where('aktif', true)->count(),
                'dengan_penanggung_jawab' => LokasiBarang::query()
                    ->whereNotNull('penanggung_jawab_pegawai_id')->count(),
            ],
            'filter' => [
                'cari' => $cari,
                'status' => $status,
                'jenis' => $jenis,
            ],
            'pilihan' => [
                'jenis' => collect(LokasiBarang::DAFTAR_JENIS)
                    ->map(fn (string $label, string $nilai) => [
                        'nilai' => $nilai,
                        'label' => $label,
                    ])->values(),
                'pegawai' => Pegawai::query()
                    ->where('aktif', true)
                    ->orderBy('nama_lengkap')
                    ->get(['id', 'nama_lengkap', 'nip'])
                    ->map(fn (Pegawai $pegawai) => [
                        'id' => (int) $pegawai->id,
                        'nama' => $pegawai->nama_lengkap,
                        'nip' => $pegawai->nip,
                    ])->values(),
            ],
            'hak_akses' => [
                'dapat_kelola' => $dapatKelola,
            ],
            'items' => collect($paginator->items())
                ->map(fn (LokasiBarang $item) => $this->ringkas($item))
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

    public function tambah(array $data): LokasiBarang
    {
        return LokasiBarang::create($this->rapikanData($data));
    }

    public function ubah(LokasiBarang $lokasi, array $data): void
    {
        $lokasi->update($this->rapikanData($data));
    }

    public function nonaktifkan(LokasiBarang $lokasi): void
    {
        $lokasi->update(['aktif' => false]);
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

    public function ringkas(LokasiBarang $lokasi): array
    {
        return [
            'id' => (int) $lokasi->id,
            'nama' => $lokasi->nama,
            'kode' => $lokasi->kode,
            'jenis' => $lokasi->jenis,
            'label_jenis' => $lokasi->labelJenis(),
            'penanggung_jawab' => $lokasi->penanggungJawab ? [
                'id' => (int) $lokasi->penanggungJawab->id,
                'nama' => $lokasi->penanggungJawab->nama_lengkap,
                'nip' => $lokasi->penanggungJawab->nip,
            ] : null,
            'deskripsi' => $lokasi->deskripsi,
            'aktif' => (bool) $lokasi->aktif,
            'jumlah_barang' => (int) ($lokasi->barang_sebagai_penyimpanan_count
                ?? $lokasi->barangSebagaiPenyimpanan()->count()),
            'dibuat_pada' => $lokasi->created_at?->toIso8601String(),
            'diperbarui_pada' => $lokasi->updated_at?->toIso8601String(),
        ];
    }

    private function rapikanData(array $data): array
    {
        return [
            'nama' => trim($data['nama']),
            'kode' => $this->rapikanKode($data['kode']),
            'jenis' => $data['jenis'],
            'penanggung_jawab_pegawai_id' => filled($data['penanggung_jawab_pegawai_id'] ?? null)
                ? (int) $data['penanggung_jawab_pegawai_id']
                : null,
            'deskripsi' => filled($data['deskripsi'] ?? null)
                ? trim($data['deskripsi'])
                : null,
            'aktif' => (bool) $data['aktif'],
        ];
    }
}
