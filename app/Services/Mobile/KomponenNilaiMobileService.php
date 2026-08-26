<?php

namespace App\Services\Mobile;

use App\Models\GuruMataPelajaran;
use App\Models\KomponenNilai;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use App\Services\Nilai\KomponenNilaiService;
use Illuminate\Database\Eloquent\Builder;

class KomponenNilaiMobileService
{
    public function __construct(private readonly KomponenNilaiService $komponenNilai) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $semester = $filter['semester'] ?? 'semua';
        $jenis = $filter['jenis_komponen'] ?? 'semua';
        $tahunPelajaranId = isset($filter['tahun_pelajaran_id'])
            ? (int) $filter['tahun_pelajaran_id']
            : null;
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $cakupan = $this->komponenNilai->queryKomponenDalamCakupan($pengguna);

        $paginator = (clone $cakupan)
            ->with([
                'guruMataPelajaran.tahunPelajaran:id,nama,aktif',
                'guruMataPelajaran.kelas:id,nama,tingkat',
                'guruMataPelajaran.mataPelajaran:id,kode,nama',
                'guruMataPelajaran.pegawai:id,nama_lengkap,nip',
            ])
            ->when($tahunPelajaranId, fn (Builder $query) => $query->whereHas(
                'guruMataPelajaran',
                fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId),
            ))
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($semester !== 'semua', fn (Builder $query) => $query->where('semester', $semester))
            ->when($jenis !== 'semua', fn (Builder $query) => $query->where('jenis_komponen', $jenis))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereHas('guruMataPelajaran.pegawai', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola]))
                        ->orWhereHas('guruMataPelajaran.mataPelajaran', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama) LIKE ?', [$pola])
                            ->orWhereRaw('LOWER(COALESCE(kode, ?)) LIKE ?', ['', $pola]))
                        ->orWhereHas('guruMataPelajaran.kelas', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama) LIKE ?', [$pola]));
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('semester')
            ->orderBy('jenis_komponen')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())
                ->map(fn (KomponenNilai $item) => $this->ringkas($item))
                ->values(),
            'ringkasan' => [
                'total' => (clone $cakupan)->count(),
                'aktif' => (clone $cakupan)->where('aktif', true)->count(),
                'nonaktif' => (clone $cakupan)->where('aktif', false)->count(),
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
            'guru_mata_pelajaran' => $this->referensiPenugasan($pengguna),
            'filter' => [
                'cari' => $kataKunci,
                'tahun_pelajaran_id' => $tahunPelajaranId,
                'semester' => $semester,
                'jenis_komponen' => $jenis,
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
                'dapat_kelola' => $pengguna->memilikiIzin('nilai.komponen_kelola'),
            ],
        ];
    }

    private function referensiPenugasan(Pengguna $pengguna)
    {
        return $this->komponenNilai
            ->queryGuruMataPelajaranDalamCakupan($pengguna)
            ->with([
                'tahunPelajaran:id,nama,aktif',
                'kelas:id,nama,tingkat',
                'mataPelajaran:id,kode,nama',
                'pegawai:id,nama_lengkap,nip',
            ])
            ->where('aktif', true)
            ->orderByDesc(
                TahunPelajaran::select('aktif')
                    ->whereColumn('tahun_pelajaran.id', 'guru_mata_pelajaran.tahun_pelajaran_id')
                    ->limit(1),
            )
            ->orderByDesc('tahun_pelajaran_id')
            ->orderBy('kelas_id')
            ->get()
            ->map(fn (GuruMataPelajaran $item) => $this->ringkasPenugasan($item))
            ->values();
    }

    private function ringkas(KomponenNilai $item): array
    {
        return [
            'id' => (int) $item->id,
            'guru_mata_pelajaran' => $this->ringkasPenugasan($item->guruMataPelajaran),
            'semester' => $item->semester,
            'semester_label' => ucfirst($item->semester),
            'jenis_komponen' => $item->jenis_komponen,
            'jenis_label' => $item->labelJenis(),
            'nama' => $item->nama,
            'tanggal_penilaian' => $item->tanggal_penilaian?->toDateString(),
            'tanggal_label' => $item->tanggal_penilaian?->format('d-m-Y'),
            'urutan' => (int) $item->urutan,
            'aktif' => (bool) $item->aktif,
            'keterangan' => $item->keterangan,
        ];
    }

    private function ringkasPenugasan(?GuruMataPelajaran $item): ?array
    {
        if (! $item) {
            return null;
        }

        return [
            'id' => (int) $item->id,
            'tahun_pelajaran' => $item->tahunPelajaran ? [
                'id' => (int) $item->tahunPelajaran->id,
                'nama' => $item->tahunPelajaran->nama,
                'aktif' => (bool) $item->tahunPelajaran->aktif,
            ] : null,
            'kelas' => $item->kelas ? [
                'id' => (int) $item->kelas->id,
                'nama' => $item->kelas->nama,
                'tingkat' => (int) $item->kelas->tingkat,
            ] : null,
            'mata_pelajaran' => $item->mataPelajaran ? [
                'id' => (int) $item->mataPelajaran->id,
                'kode' => $item->mataPelajaran->kode,
                'nama' => $item->mataPelajaran->nama,
            ] : null,
            'pegawai' => $item->pegawai ? [
                'id' => (int) $item->pegawai->id,
                'nama' => $item->pegawai->nama_lengkap,
                'nip' => $item->pegawai->nip,
            ] : null,
        ];
    }
}
