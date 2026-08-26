<?php

namespace App\Services\Mobile;

use App\Models\Pengguna;
use App\Models\SkemaBobotNilai;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Builder;

class SkemaBobotNilaiMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $status = $filter['status'] ?? 'semua';
        $semester = $filter['semester'] ?? 'semua';
        $tingkat = $filter['tingkat'] ?? 'semua';
        $tahunPelajaranId = isset($filter['tahun_pelajaran_id'])
            ? (int) $filter['tahun_pelajaran_id']
            : null;
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);

        $paginator = SkemaBobotNilai::query()
            ->with('tahunPelajaran:id,nama,aktif')
            ->when($tahunPelajaranId, fn (Builder $query) => $query
                ->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($semester !== 'semua', fn (Builder $query) => $query
                ->where('semester', $semester))
            ->when($tingkat !== 'semua', fn (Builder $query) => $query
                ->where('tingkat', (int) $tingkat))
            ->orderByDesc(
                TahunPelajaran::select('aktif')
                    ->whereColumn('tahun_pelajaran.id', 'skema_bobot_nilai.tahun_pelajaran_id')
                    ->limit(1),
            )
            ->orderByDesc(
                TahunPelajaran::select('nama')
                    ->whereColumn('tahun_pelajaran.id', 'skema_bobot_nilai.tahun_pelajaran_id')
                    ->limit(1),
            )
            ->orderBy('semester')
            ->orderByRaw('COALESCE(tingkat, 0)')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())
                ->map(fn (SkemaBobotNilai $item) => $this->ringkas($item))
                ->values(),
            'ringkasan' => [
                'total' => SkemaBobotNilai::count(),
                'aktif' => SkemaBobotNilai::where('aktif', true)->count(),
                'nonaktif' => SkemaBobotNilai::where('aktif', false)->count(),
            ],
            'tahun_pelajaran' => TahunPelajaran::query()
                ->orderByDesc('aktif')
                ->orderByDesc('nama')
                ->get(['id', 'nama', 'aktif'])
                ->map(fn (TahunPelajaran $tahun) => [
                    'id' => (int) $tahun->id,
                    'nama' => $tahun->nama,
                    'aktif' => (bool) $tahun->aktif,
                ])->values(),
            'filter' => [
                'tahun_pelajaran_id' => $tahunPelajaranId,
                'semester' => $semester,
                'tingkat' => $tingkat,
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
                'dapat_kelola' => $pengguna->memilikiIzin('nilai.skema_kelola'),
            ],
        ];
    }

    private function ringkas(SkemaBobotNilai $item): array
    {
        return [
            'id' => (int) $item->id,
            'tahun_pelajaran' => $item->tahunPelajaran ? [
                'id' => (int) $item->tahunPelajaran->id,
                'nama' => $item->tahunPelajaran->nama,
                'aktif' => (bool) $item->tahunPelajaran->aktif,
            ] : null,
            'semester' => $item->semester,
            'semester_label' => ucfirst($item->semester),
            'tingkat' => $item->tingkat,
            'tingkat_label' => $item->labelTingkat(),
            'bobot_formatif' => (int) $item->bobot_formatif,
            'bobot_sumatif' => (int) $item->bobot_sumatif,
            'bobot_sts' => (int) $item->bobot_sts,
            'bobot_sas_saj' => (int) $item->bobot_sas_saj,
            'label_nilai_akhir' => $item->labelNilaiAkhir(),
            'total_bobot' => $item->totalBobot(),
            'aktif' => (bool) $item->aktif,
            'keterangan' => $item->keterangan,
        ];
    }
}
