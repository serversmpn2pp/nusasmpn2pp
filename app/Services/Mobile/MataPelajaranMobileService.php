<?php

namespace App\Services\Mobile;

use App\Models\MataPelajaran;
use App\Models\PengaturanMataPelajaran;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MataPelajaranMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $tingkat = $filter['tingkat'] ?? 'semua';
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);
        [$tahunPelajaran, $tahunPelajaranId] = $this->tahunPelajaranDanPilihan(
            $filter['tahun_pelajaran_id'] ?? null,
        );

        $paginator = MataPelajaran::query()
            ->with([
                'pengaturanTingkat' => fn ($query) => $query
                    ->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->orderBy('tingkat'),
            ])
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($tingkat !== 'semua', fn (Builder $query) => $query
                ->whereHas('pengaturanTingkat', fn (Builder $query) => $query
                    ->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->where('tingkat', (int) $tingkat)
                    ->where('aktif', true)))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci, $tahunPelajaranId) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola, $tahunPelajaranId) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(kelompok, '')) LIKE ?", [$pola])
                        ->orWhereHas('pengaturanTingkat', fn (Builder $query) => $query
                            ->where('tahun_pelajaran_id', $tahunPelajaranId)
                            ->whereRaw('LOWER(kode) LIKE ?', [$pola]));
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())
                ->map(fn (MataPelajaran $item) => $this->ringkas($item))
                ->values(),
            'ringkasan' => [
                'total' => MataPelajaran::count(),
                'aktif' => MataPelajaran::where('aktif', true)->count(),
                'nonaktif' => MataPelajaran::where('aktif', false)->count(),
            ],
            'tahun_pelajaran' => $tahunPelajaran->map(fn (TahunPelajaran $tahun) => [
                'id' => (int) $tahun->id,
                'nama' => $tahun->nama,
                'aktif' => (bool) $tahun->aktif,
            ])->values(),
            'filter' => [
                'cari' => $kataKunci,
                'status' => $status,
                'tingkat' => $tingkat,
                'tahun_pelajaran_id' => $tahunPelajaranId,
            ],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
            'hak_akses' => [
                'dapat_kelola' => $pengguna->memilikiIzin('mata_pelajaran.kelola'),
            ],
        ];
    }

    public function referensi(): array
    {
        return [
            'tahun_pelajaran' => TahunPelajaran::query()
                ->orderByDesc('aktif')
                ->orderByDesc('nama')
                ->get(['id', 'nama', 'aktif'])
                ->map(fn (TahunPelajaran $tahun) => [
                    'id' => (int) $tahun->id,
                    'nama' => $tahun->nama,
                    'aktif' => (bool) $tahun->aktif,
                ])->values(),
            'kelompok' => collect([
                'Umum',
                'Agama dan Budi Pekerti',
                'Muatan Lokal',
                'Pilihan',
                'Kokurikuler',
                'Ekstrakurikuler',
                'Pengembangan Diri',
            ])->map(fn (string $nama) => [
                'nama' => $nama,
                'menggunakan_predikat' => MataPelajaran::kelompokMenggunakanPredikat($nama),
            ]),
            'tingkat' => [
                ['nilai' => 7, 'label' => 'VII'],
                ['nilai' => 8, 'label' => 'VIII'],
                ['nilai' => 9, 'label' => 'IX'],
            ],
        ];
    }

    public function tambah(array $data): MataPelajaran
    {
        $this->pastikanAdaTingkatAktif($data);

        return DB::transaction(function () use ($data) {
            $mataPelajaran = MataPelajaran::create($this->dataUtama($data));
            $this->sinkronkanPengaturan($mataPelajaran, $data);

            return $mataPelajaran;
        });
    }

    public function ubah(MataPelajaran $mataPelajaran, array $data): void
    {
        $this->pastikanAdaTingkatAktif($data);

        DB::transaction(function () use ($mataPelajaran, $data) {
            $mataPelajaran->update($this->dataUtama($data));
            $this->sinkronkanPengaturan($mataPelajaran, $data);
        });
    }

    private function ringkas(MataPelajaran $item): array
    {
        return [
            'id' => (int) $item->id,
            'nama' => $item->nama,
            'kelompok' => $item->kelompok,
            'jenis_penilaian' => $item->menggunakanPredikat() ? 'predikat' : 'angka',
            'jenis_penilaian_label' => $item->labelJenisPenilaian(),
            'urutan' => (int) $item->urutan,
            'aktif' => (bool) $item->aktif,
            'keterangan' => $item->keterangan,
            'pengaturan' => $item->pengaturanTingkat
                ->map(fn (PengaturanMataPelajaran $pengaturan) => [
                    'id' => (int) $pengaturan->id,
                    'tingkat' => (int) $pengaturan->tingkat,
                    'kode' => $pengaturan->kode,
                    'kkm' => $pengaturan->kkm === null ? null : (int) $pengaturan->kkm,
                    'aktif' => (bool) $pengaturan->aktif,
                ])->values(),
        ];
    }

    private function tahunPelajaranDanPilihan(mixed $tahunPelajaranId): array
    {
        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get(['id', 'nama', 'aktif']);
        $pilihan = (int) $tahunPelajaranId;

        if (! $tahunPelajaran->contains('id', $pilihan)) {
            $pilihan = (int) ($tahunPelajaran->firstWhere('aktif', true)?->id
                ?? $tahunPelajaran->first()?->id);
        }

        return [$tahunPelajaran, $pilihan];
    }

    private function pastikanAdaTingkatAktif(array $data): void
    {
        if (! collect($data['pengaturan'])->contains(
            fn (array $pengaturan) => (bool) ($pengaturan['aktif'] ?? false),
        )) {
            throw ValidationException::withMessages([
                'pengaturan' => 'Aktifkan minimal satu tingkat untuk mata pelajaran ini.',
            ]);
        }
    }

    private function dataUtama(array $data): array
    {
        return [
            'nama' => trim($data['nama']),
            'kelompok' => filled($data['kelompok'] ?? null)
                ? trim($data['kelompok'])
                : null,
            'tingkat' => null,
            'kkm' => null,
            'urutan' => (int) ($data['urutan'] ?? 0),
            'aktif' => (bool) $data['aktif'],
            'keterangan' => filled($data['keterangan'] ?? null)
                ? trim($data['keterangan'])
                : null,
        ];
    }

    private function sinkronkanPengaturan(MataPelajaran $mataPelajaran, array $data): void
    {
        $menggunakanPredikat = MataPelajaran::kelompokMenggunakanPredikat(
            $data['kelompok'] ?? null,
        );

        foreach ([7, 8, 9] as $tingkat) {
            $nilai = $data['pengaturan'][$tingkat] ?? [];
            $aktif = (bool) ($nilai['aktif'] ?? false);
            $pengaturan = $mataPelajaran->pengaturanTingkat()
                ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
                ->where('tingkat', $tingkat)
                ->first();

            if (! $aktif && ! $pengaturan) {
                continue;
            }

            $mataPelajaran->pengaturanTingkat()->updateOrCreate(
                [
                    'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                    'tingkat' => $tingkat,
                ],
                [
                    'kode' => mb_strtoupper(trim((string) ($nilai['kode'] ?? $pengaturan?->kode))),
                    'kkm' => $menggunakanPredikat
                        ? null
                        : (filled($nilai['kkm'] ?? null)
                            ? (int) $nilai['kkm']
                            : $pengaturan?->kkm),
                    'aktif' => $aktif,
                ],
            );
        }
    }
}
