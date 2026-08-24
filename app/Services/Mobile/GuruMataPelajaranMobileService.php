<?php

namespace App\Services\Mobile;

use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuruMataPelajaranMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $tahunPelajaranId = $filter['tahun_pelajaran_id'] ?? null;
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);
        $query = GuruMataPelajaran::query()
            ->with(['tahunPelajaran', 'kelas', 'mataPelajaran', 'pegawai']);
        $paginator = $query
            ->when($tahunPelajaranId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereHas('pegawai', fn (Builder $query) => $query
                        ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(COALESCE(nip, ?)) LIKE ?', ['', $pola]))
                        ->orWhereHas('mataPelajaran', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama) LIKE ?', [$pola])
                            ->orWhereRaw('LOWER(COALESCE(kode, ?)) LIKE ?', ['', $pola]))
                        ->orWhereHas('kelas', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama) LIKE ?', [$pola]));
                });
            })
            ->orderByDesc('aktif')
            ->orderByDesc('tahun_pelajaran_id')
            ->orderBy('kelas_id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())
                ->map(fn (GuruMataPelajaran $item) => $this->ringkas($item))
                ->values(),
            'ringkasan' => [
                'total' => GuruMataPelajaran::count(),
                'aktif' => GuruMataPelajaran::where('aktif', true)->count(),
                'nonaktif' => GuruMataPelajaran::where('aktif', false)->count(),
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
                'cari' => $kataKunci,
                'status' => $status,
                'tahun_pelajaran_id' => $tahunPelajaranId ? (int) $tahunPelajaranId : null,
            ],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
            'hak_akses' => [
                'dapat_kelola' => $pengguna->memilikiIzin('guru_mapel.kelola'),
            ],
        ];
    }

    public function referensi(): array
    {
        $kelas = Kelas::query()
            ->with('tahunPelajaran:id,nama,aktif')
            ->where('aktif', true)
            ->orderByDesc('tahun_pelajaran_id')
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get();
        $mataPelajaran = MataPelajaran::query()
            ->with('pengaturanTingkat')
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();

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
            'kelas' => $kelas->map(fn (Kelas $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama,
                'tingkat' => (int) $item->tingkat,
                'tahun_pelajaran_id' => (int) $item->tahun_pelajaran_id,
                'tahun_pelajaran' => $item->tahunPelajaran?->nama,
            ])->values(),
            'pegawai' => Pegawai::query()
                ->where('aktif', true)
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nip', 'jabatan_utama', 'jenis_pegawai'])
                ->map(fn (Pegawai $item) => [
                    'id' => (int) $item->id,
                    'nama' => $item->nama_lengkap,
                    'nip' => $item->nip,
                    'jabatan' => $item->jabatan_utama ?: $item->jenis_pegawai,
                ])->values(),
            'mata_pelajaran' => $mataPelajaran->map(fn (MataPelajaran $item) => [
                'id' => (int) $item->id,
                'kode' => $item->kode,
                'nama' => $item->nama,
                'kelompok' => $item->kelompok,
                'kelas_ids_tersedia' => $kelas
                    ->filter(fn (Kelas $kelas) => $this->mataPelajaranTersedia($item, $kelas))
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values(),
            ])->values(),
            'jenis_penugasan' => [
                ['kode' => 'pengampu', 'label' => 'Pengampu'],
                ['kode' => 'pendamping', 'label' => 'Pendamping'],
                ['kode' => 'koordinator', 'label' => 'Koordinator'],
            ],
        ];
    }

    public function tambah(array $data): array
    {
        $kelasIds = collect($data['kelas_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $this->pastikanRelasiMassalCocok($data, $kelasIds->all());

        return DB::transaction(function () use ($data, $kelasIds) {
            $jumlahBaru = 0;
            $jumlahDiperbarui = 0;

            foreach ($kelasIds as $kelasId) {
                $kunci = [
                    'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                    'kelas_id' => $kelasId,
                    'mata_pelajaran_id' => $data['mata_pelajaran_id'],
                    'pegawai_id' => $data['pegawai_id'],
                ];
                $penugasan = GuruMataPelajaran::query()->where($kunci)->first();
                $atribut = [
                    'jenis_penugasan' => $data['jenis_penugasan'],
                    'aktif' => $data['aktif'],
                    'keterangan' => $data['keterangan'] ?? null,
                ];

                if ($penugasan) {
                    $penugasan->update($atribut);
                    $jumlahDiperbarui++;
                } else {
                    GuruMataPelajaran::create([...$kunci, ...$atribut]);
                    $jumlahBaru++;
                }
            }

            return [
                'jumlah_baru' => $jumlahBaru,
                'jumlah_diperbarui' => $jumlahDiperbarui,
            ];
        });
    }

    public function ubah(GuruMataPelajaran $penugasan, array $data): void
    {
        $this->pastikanRelasiCocok($data);
        $penugasan->update($data);
    }

    private function ringkas(GuruMataPelajaran $item): array
    {
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
                'kelompok' => $item->mataPelajaran->kelompok,
            ] : null,
            'pegawai' => $item->pegawai ? [
                'id' => (int) $item->pegawai->id,
                'nama' => $item->pegawai->nama_lengkap,
                'nip' => $item->pegawai->nip,
            ] : null,
            'jenis_penugasan' => $item->jenis_penugasan,
            'jenis_penugasan_label' => str($item->jenis_penugasan)->headline()->toString(),
            'aktif' => (bool) $item->aktif,
            'keterangan' => $item->keterangan,
        ];
    }

    private function pastikanRelasiCocok(array $data): void
    {
        $kelas = Kelas::find($data['kelas_id']);
        $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);

        if (! $kelas || ! $kelas->aktif || (int) $kelas->tahun_pelajaran_id !== (int) $data['tahun_pelajaran_id']) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Kelas harus aktif dan berada pada tahun pelajaran yang dipilih.',
            ]);
        }

        if (! $mataPelajaran || ! $mataPelajaran->tersediaUntuk((int) $data['tahun_pelajaran_id'], (int) $kelas->tingkat)) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Mata pelajaran belum diaktifkan untuk tingkat kelas dan tahun pelajaran yang dipilih.',
            ]);
        }
    }

    private function pastikanRelasiMassalCocok(array $data, array $kelasIds): void
    {
        $kelas = Kelas::query()->whereIn('id', $kelasIds)->get();
        $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);

        if ($kelas->count() !== count($kelasIds) || $kelas->contains(fn (Kelas $item) => (
            ! $item->aktif || (int) $item->tahun_pelajaran_id !== (int) $data['tahun_pelajaran_id']
        ))) {
            throw ValidationException::withMessages([
                'kelas_ids' => 'Semua kelas harus aktif dan berada pada tahun pelajaran yang dipilih.',
            ]);
        }

        $tingkatTidakTersedia = $kelas->pluck('tingkat')->unique()->first(fn ($tingkat) => (
            ! $mataPelajaran?->tersediaUntuk((int) $data['tahun_pelajaran_id'], (int) $tingkat)
        ));

        if ($tingkatTidakTersedia) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => "Mata pelajaran belum diaktifkan untuk tingkat {$tingkatTidakTersedia} pada tahun pelajaran yang dipilih.",
            ]);
        }
    }

    private function mataPelajaranTersedia(MataPelajaran $mataPelajaran, Kelas $kelas): bool
    {
        if ($mataPelajaran->pengaturanTingkat->isNotEmpty()) {
            return $mataPelajaran->pengaturanTingkat->contains(fn ($item) => (
                (int) $item->tahun_pelajaran_id === (int) $kelas->tahun_pelajaran_id
                && (int) $item->tingkat === (int) $kelas->tingkat
                && $item->aktif
            ));
        }

        return ! $mataPelajaran->tingkat || (int) $mataPelajaran->tingkat === (int) $kelas->tingkat;
    }
}
