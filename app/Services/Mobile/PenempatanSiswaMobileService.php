<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PenempatanSiswaMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $kelasWaliIds = $pengguna->membatasiCakupanWaliKelas()
            ? $pengguna->kelasWaliIds()
            : null;
        $tahunPelajaran = $this->tahunPelajaran($kelasWaliIds);
        $tahunPelajaranId = (int) ($filter['tahun_pelajaran_id'] ?? 0);

        if (! $tahunPelajaran->contains('id', $tahunPelajaranId)) {
            $tahunPelajaranId = (int) ($tahunPelajaran->firstWhere('aktif', true)?->id
                ?? $tahunPelajaran->first()?->id);
        }

        $kelas = $this->kelas($tahunPelajaranId, $kelasWaliIds);
        $kelasId = (int) ($filter['kelas_id'] ?? 0);
        if (! $kelas->contains('id', $kelasId)) {
            $kelasId = (int) ($kelas->firstWhere('aktif', true)?->id ?? $kelas->first()?->id);
        }
        /** @var Kelas|null $kelasDipilih */
        $kelasDipilih = $kelas->firstWhere('id', $kelasId);
        $dapatKelola = $kelasDipilih !== null
            && $pengguna->memilikiIzin('kelas.kelola')
            && $pengguna->dapatMengaksesKelasSebagaiWali($kelasDipilih->id);

        $anggota = $kelasDipilih
            ? AnggotaKelas::query()
                ->with('siswa:id,nama_lengkap,nis,nisn,foto,jenis_kelamin,aktif')
                ->where('kelas_id', $kelasDipilih->id)
                ->orderByRaw("CASE WHEN status_keanggotaan = 'aktif' THEN 0 ELSE 1 END")
                ->orderByRaw('nomor_absen IS NULL')
                ->orderBy('nomor_absen')
                ->orderBy('id')
                ->get()
                ->filter(fn (AnggotaKelas $item) => $item->siswa !== null)
                ->map(fn (AnggotaKelas $item) => $this->anggota($item))
                ->values()
            : collect();
        $siswaTersedia = $dapatKelola
            ? $this->siswaTersedia($tahunPelajaranId, $kataKunci)
            : collect();
        $jumlahSiswaAktif = Siswa::query()
            ->where('aktif', true)
            ->when($kelasWaliIds !== null, fn (Builder $query) => $query->whereHas(
                'anggotaKelas',
                fn (Builder $query) => $query->whereIn('kelas_id', $kelasWaliIds),
            ))
            ->count();
        $jumlahDitempatkan = $tahunPelajaranId
            ? AnggotaKelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->when($kelasWaliIds !== null, fn (Builder $query) => $query->whereIn('kelas_id', $kelasWaliIds))
                ->distinct('siswa_id')
                ->count('siswa_id')
            : 0;

        return [
            'tahun_pelajaran' => $tahunPelajaran->map(fn (TahunPelajaran $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama,
                'aktif' => (bool) $item->aktif,
                'jumlah_kelas' => (int) $item->kelas_count,
            ])->values(),
            'kelas' => $kelas->map(fn (Kelas $item) => $this->kelasRingkas($item))->values(),
            'kelas_dipilih' => $kelasDipilih ? $this->kelasRingkas($kelasDipilih) : null,
            'anggota' => $anggota,
            'siswa_tersedia' => $siswaTersedia,
            'ringkasan' => [
                'siswa_aktif' => $jumlahSiswaAktif,
                'ditempatkan' => $jumlahDitempatkan,
                'belum_ditempatkan' => max($jumlahSiswaAktif - $jumlahDitempatkan, 0),
            ],
            'filter' => [
                'tahun_pelajaran_id' => $tahunPelajaranId ?: null,
                'kelas_id' => $kelasId ?: null,
                'cari' => $kataKunci,
            ],
            'hak_akses' => [
                'dapat_kelola' => $dapatKelola,
                'cakupan_wali_kelas' => $kelasWaliIds !== null,
            ],
        ];
    }

    public function tempatkan(Pengguna $pengguna, array $data): int
    {
        $kelasAwal = Kelas::findOrFail($data['kelas_id']);
        abort_unless($pengguna->memilikiIzin('kelas.kelola'), 403);
        abort_unless($pengguna->dapatMengaksesKelasSebagaiWali($kelasAwal->id), 403);

        $siswaIds = collect($data['siswa_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return DB::transaction(function () use ($kelasAwal, $siswaIds, $data) {
            TahunPelajaran::query()
                ->whereKey($kelasAwal->tahun_pelajaran_id)
                ->lockForUpdate()
                ->firstOrFail();
            $kelas = Kelas::query()->lockForUpdate()->findOrFail($kelasAwal->id);
            $sudahDitempatkan = AnggotaKelas::query()
                ->with('kelas:id,nama')
                ->where('tahun_pelajaran_id', $kelas->tahun_pelajaran_id)
                ->whereIn('siswa_id', $siswaIds)
                ->get();

            if ($sudahDitempatkan->isNotEmpty()) {
                $daftarKelas = $sudahDitempatkan
                    ->map(fn (AnggotaKelas $item) => $item->kelas?->nama ?? 'kelas lain')
                    ->unique()
                    ->join(', ');
                throw ValidationException::withMessages([
                    'siswa_ids' => 'Ada siswa yang sudah ditempatkan pada tahun pelajaran ini di '.$daftarKelas.'.',
                ]);
            }

            $jumlahAnggota = $kelas->anggotaKelas()->count();
            if ($kelas->kapasitas !== null && $jumlahAnggota + $siswaIds->count() > $kelas->kapasitas) {
                throw ValidationException::withMessages([
                    'siswa_ids' => 'Jumlah siswa yang dipilih melebihi sisa kapasitas kelas.',
                ]);
            }

            foreach ($siswaIds as $siswaId) {
                AnggotaKelas::create([
                    'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id,
                    'kelas_id' => $kelas->id,
                    'siswa_id' => $siswaId,
                    'nomor_absen' => null,
                    'status_keanggotaan' => 'aktif',
                    'tanggal_masuk' => $data['tanggal_masuk'] ?? $kelas->tahunPelajaran()->value('tanggal_mulai'),
                    'keterangan' => filled($data['keterangan'] ?? null)
                        ? trim($data['keterangan'])
                        : 'Penempatan siswa dari NUSA Mobile',
                ]);
            }

            return $siswaIds->count();
        });
    }

    private function tahunPelajaran(?array $kelasWaliIds)
    {
        return TahunPelajaran::query()
            ->withCount([
                'kelas' => fn (Builder $query) => $kelasWaliIds === null
                    ? $query
                    : $query->whereIn('id', $kelasWaliIds),
            ])
            ->when($kelasWaliIds !== null, fn (Builder $query) => $query->whereHas(
                'kelas',
                fn (Builder $query) => $query->whereIn('id', $kelasWaliIds),
            ))
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get(['id', 'nama', 'aktif']);
    }

    private function kelas(int $tahunPelajaranId, ?array $kelasWaliIds)
    {
        if (! $tahunPelajaranId) {
            return collect();
        }

        return Kelas::query()
            ->with('waliKelas:id,nama_lengkap')
            ->withCount([
                'anggotaKelas as jumlah_siswa_aktif' => fn (Builder $query) => $query
                    ->where('status_keanggotaan', 'aktif'),
            ])
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->when($kelasWaliIds !== null, fn (Builder $query) => $query->whereIn('id', $kelasWaliIds))
            ->orderByDesc('aktif')
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get();
    }

    private function siswaTersedia(int $tahunPelajaranId, string $kataKunci)
    {
        if (! $tahunPelajaranId) {
            return collect();
        }

        $sudahDitempatkan = AnggotaKelas::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->select('siswa_id');
        $pola = '%'.mb_strtolower($kataKunci).'%';

        return Siswa::query()
            ->where('aktif', true)
            ->whereNotIn('id', $sudahDitempatkan)
            ->when($kataKunci !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($pola) {
                $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                    ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                    ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]);
            }))
            ->orderBy('nama_lengkap')
            ->limit(150)
            ->get(['id', 'nama_lengkap', 'nis', 'nisn', 'foto', 'jenis_kelamin'])
            ->map(fn (Siswa $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama_lengkap,
                'nis' => $item->nis,
                'nisn' => $item->nisn,
                'jenis_kelamin' => $item->jenis_kelamin,
                'foto_url' => $item->foto ? asset('storage/'.$item->foto) : null,
            ])
            ->values();
    }

    private function kelasRingkas(Kelas $kelas): array
    {
        $jumlah = (int) ($kelas->jumlah_siswa_aktif ?? 0);
        $kapasitas = $kelas->kapasitas === null ? null : (int) $kelas->kapasitas;

        return [
            'id' => (int) $kelas->id,
            'nama' => $kelas->nama,
            'tingkat' => $kelas->tingkat === null ? null : (int) $kelas->tingkat,
            'aktif' => (bool) $kelas->aktif,
            'kapasitas' => $kapasitas,
            'jumlah_anggota' => $jumlah,
            'sisa_kursi' => $kapasitas === null ? null : max($kapasitas - $jumlah, 0),
            'wali_kelas' => $kelas->waliKelas?->nama_lengkap,
        ];
    }

    private function anggota(AnggotaKelas $anggota): array
    {
        return [
            'id' => (int) $anggota->id,
            'nomor_absen' => $anggota->nomor_absen === null ? null : (int) $anggota->nomor_absen,
            'status' => $anggota->status_keanggotaan,
            'tanggal_masuk' => $anggota->tanggal_masuk?->toDateString(),
            'siswa' => [
                'id' => (int) $anggota->siswa->id,
                'nama' => $anggota->siswa->nama_lengkap,
                'nis' => $anggota->siswa->nis,
                'nisn' => $anggota->siswa->nisn,
                'jenis_kelamin' => $anggota->siswa->jenis_kelamin,
                'foto_url' => $anggota->siswa->foto ? asset('storage/'.$anggota->siswa->foto) : null,
                'aktif' => (bool) $anggota->siswa->aktif,
            ],
        ];
    }
}
