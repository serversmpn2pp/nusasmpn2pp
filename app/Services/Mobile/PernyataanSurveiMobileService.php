<?php

namespace App\Services\Mobile;

use App\Models\PertanyaanSurveiPembelajaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PernyataanSurveiMobileService
{
    public function daftar(array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);

        $paginator = PertanyaanSurveiPembelajaran::query()
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pencarian = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pencarian): void {
                    $query->whereRaw('LOWER(pernyataan) LIKE ?', [$pencarian])
                        ->orWhereRaw('LOWER(kode) LIKE ?', [$pencarian]);
                });
            })
            ->terurut()
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())
                ->map(fn (PertanyaanSurveiPembelajaran $item) => $this->ringkas($item))
                ->values(),
            'ringkasan' => [
                'total' => PertanyaanSurveiPembelajaran::count(),
                'aktif' => PertanyaanSurveiPembelajaran::aktif()->count(),
                'nonaktif' => PertanyaanSurveiPembelajaran::where('aktif', false)->count(),
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
            'urutan_berikutnya' => ((int) PertanyaanSurveiPembelajaran::max('urutan')) + 1,
        ];
    }

    public function tambah(array $data): PertanyaanSurveiPembelajaran
    {
        return PertanyaanSurveiPembelajaran::create([
            'kode' => $this->kodeBaru(),
            'pernyataan' => trim($data['pernyataan']),
            'urutan' => (int) $data['urutan'],
            'aktif' => (bool) $data['aktif'],
        ]);
    }

    public function ubah(PertanyaanSurveiPembelajaran $pertanyaan, array $data): void
    {
        $pertanyaan->update([
            'pernyataan' => trim($data['pernyataan']),
            'urutan' => (int) $data['urutan'],
        ]);
    }

    public function ubahStatus(PertanyaanSurveiPembelajaran $pertanyaan, bool $aktif): void
    {
        DB::transaction(function () use ($pertanyaan, $aktif): void {
            $terkunci = PertanyaanSurveiPembelajaran::query()
                ->lockForUpdate()
                ->findOrFail($pertanyaan->id);

            if (! $aktif && $terkunci->aktif && PertanyaanSurveiPembelajaran::aktif()->count() <= 1) {
                throw ValidationException::withMessages([
                    'aktif' => 'Minimal satu pernyataan survei harus tetap aktif.',
                ]);
            }

            $terkunci->update(['aktif' => $aktif]);
        });
    }

    private function ringkas(PertanyaanSurveiPembelajaran $item): array
    {
        return [
            'id' => (int) $item->id,
            'kode' => $item->kode,
            'pernyataan' => $item->pernyataan,
            'urutan' => (int) $item->urutan,
            'aktif' => (bool) $item->aktif,
        ];
    }

    private function kodeBaru(): string
    {
        do {
            $kode = 'survei_'.Str::lower(Str::random(12));
        } while (PertanyaanSurveiPembelajaran::where('kode', $kode)->exists());

        return $kode;
    }
}
