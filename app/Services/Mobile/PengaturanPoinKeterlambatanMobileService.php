<?php

namespace App\Services\Mobile;

use App\Models\PengaturanPoinKeterlambatan;
use App\Models\RentangPoinKeterlambatan;
use App\Models\TahunPelajaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PengaturanPoinKeterlambatanMobileService
{
    public function daftar(array $filter): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';

        $query = TahunPelajaran::query()
            ->with([
                'pengaturanPoinKeterlambatan.rentangPoinKeterlambatan',
                'pengaturanPoinKeterlambatan.diperbaruiOlehPengguna:id,nama',
            ])
            ->when($cari !== '', function ($query) use ($cari) {
                $query->whereRaw('LOWER(nama) LIKE ?', ['%'.mb_strtolower($cari).'%']);
            })
            ->when($status === 'aktif', fn ($query) => $query->whereHas(
                'pengaturanPoinKeterlambatan',
                fn ($query) => $query->where('aktif', true),
            ))
            ->when($status === 'nonaktif', fn ($query) => $query->whereDoesntHave(
                'pengaturanPoinKeterlambatan',
                fn ($query) => $query->where('aktif', true),
            ));

        $items = $query
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get()
            ->map(fn (TahunPelajaran $tahun) => $this->ringkasTahun($tahun))
            ->values();

        return [
            'ringkasan' => [
                'jumlah_tahun' => TahunPelajaran::query()->count(),
                'tahun_aktif_id' => TahunPelajaran::query()->where('aktif', true)->value('id'),
                'sudah_diatur' => PengaturanPoinKeterlambatan::query()->count(),
                'otomatis_aktif' => PengaturanPoinKeterlambatan::query()->where('aktif', true)->count(),
            ],
            'filter' => [
                'cari' => $cari,
                'status' => $status,
            ],
            'hak_akses' => ['dapat_kelola' => true],
            'items' => $items,
        ];
    }

    public function simpan(TahunPelajaran $tahun, array $data, ?int $penggunaId): void
    {
        $rentang = collect($data['rentang'])
            ->map(fn (array $item) => [
                'menit_mulai' => (int) $item['menit_mulai'],
                'menit_selesai' => filled($item['menit_selesai'] ?? null)
                    ? (int) $item['menit_selesai']
                    : null,
                'poin' => (int) $item['poin'],
            ])
            ->sortBy('menit_mulai')
            ->values();

        $this->pastikanRentangValid($rentang);

        DB::transaction(function () use ($tahun, $data, $penggunaId, $rentang) {
            $pengaturan = PengaturanPoinKeterlambatan::updateOrCreate(
                ['tahun_pelajaran_id' => $tahun->id],
                [
                    'aktif' => (bool) ($data['aktif'] ?? false),
                    'diperbarui_oleh_pengguna_id' => $penggunaId,
                ],
            );

            $pengaturan->rentangPoinKeterlambatan()->delete();
            $pengaturan->rentangPoinKeterlambatan()->createMany(
                $rentang
                    ->map(fn (array $item, int $index) => $item + ['urutan' => $index + 1])
                    ->all(),
            );
        });
    }

    public function ringkasTahun(TahunPelajaran $tahun): array
    {
        $pengaturan = $tahun->pengaturanPoinKeterlambatan;
        $rentang = $pengaturan?->rentangPoinKeterlambatan ?? $this->rentangBawaan();

        return [
            'tahun_pelajaran' => [
                'id' => (int) $tahun->id,
                'nama' => $tahun->nama,
                'tanggal_mulai' => $tahun->tanggal_mulai?->toDateString(),
                'tanggal_selesai' => $tahun->tanggal_selesai?->toDateString(),
                'aktif' => (bool) $tahun->aktif,
            ],
            'tersimpan' => $pengaturan !== null,
            'otomatis_aktif' => (bool) ($pengaturan?->aktif ?? false),
            'rentang' => $rentang
                ->map(fn ($item) => [
                    'id' => $item->exists ? (int) $item->id : null,
                    'menit_mulai' => (int) $item->menit_mulai,
                    'menit_selesai' => $item->menit_selesai === null ? null : (int) $item->menit_selesai,
                    'poin' => (int) $item->poin,
                    'urutan' => (int) $item->urutan,
                    'label' => $item->labelRentang(),
                ])
                ->values(),
            'diperbarui_oleh' => $pengaturan?->diperbaruiOlehPengguna?->nama,
            'diperbarui_pada' => $pengaturan?->updated_at?->toIso8601String(),
        ];
    }

    private function pastikanRentangValid(Collection $rentang): void
    {
        if ((int) $rentang->first()['menit_mulai'] !== 1) {
            throw ValidationException::withMessages([
                'rentang.0.menit_mulai' => 'Rentang pertama harus dimulai dari menit ke-1.',
            ]);
        }

        foreach ($rentang as $index => $item) {
            $terakhir = $index === $rentang->count() - 1;

            if (! $terakhir && $item['menit_selesai'] === null) {
                throw ValidationException::withMessages([
                    "rentang.{$index}.menit_selesai" => 'Hanya rentang terakhir yang boleh tanpa batas akhir.',
                ]);
            }

            if ($terakhir && $item['menit_selesai'] !== null) {
                throw ValidationException::withMessages([
                    "rentang.{$index}.menit_selesai" => 'Rentang terakhir harus tanpa batas akhir.',
                ]);
            }

            if ($item['menit_selesai'] !== null && $item['menit_selesai'] < $item['menit_mulai']) {
                throw ValidationException::withMessages([
                    "rentang.{$index}.menit_selesai" => 'Batas akhir tidak boleh lebih kecil daripada batas awal.',
                ]);
            }

            if ($index > 0) {
                $sebelumnya = $rentang[$index - 1];
                if ($sebelumnya['menit_selesai'] === null || $item['menit_mulai'] !== $sebelumnya['menit_selesai'] + 1) {
                    throw ValidationException::withMessages([
                        "rentang.{$index}.menit_mulai" => 'Rentang menit harus berurutan tanpa celah atau tumpang tindih.',
                    ]);
                }
            }
        }
    }

    private function rentangBawaan(): Collection
    {
        return collect([
            new RentangPoinKeterlambatan([
                'menit_mulai' => 1,
                'menit_selesai' => 10,
                'poin' => 0,
                'urutan' => 1,
            ]),
            new RentangPoinKeterlambatan([
                'menit_mulai' => 11,
                'menit_selesai' => null,
                'poin' => 15,
                'urutan' => 2,
            ]),
        ]);
    }
}
