<?php

namespace App\Services\Mobile;

use App\Models\Pengguna;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;

class SiswaMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $status = $filter['status'] ?? 'semua';
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $ringkasanQuery = $this->queryDalamCakupan($pengguna);

        $query = $this->queryDalamCakupan($pengguna)
            ->select([
                'id',
                'nama_lengkap',
                'nis',
                'nisn',
                'foto',
                'jenis_kelamin',
                'aktif',
            ])
            ->with($this->relasiKelasAktif())
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $polaNama = '%'.mb_strtolower($kataKunci).'%';
                $polaIdentitas = '%'.$kataKunci.'%';

                $query->where(function (Builder $query) use ($polaNama, $polaIdentitas) {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$polaNama])
                        ->orWhere('nis', 'like', $polaIdentitas)
                        ->orWhere('nisn', 'like', $polaIdentitas)
                        ->orWhere('nik', 'like', $polaIdentitas);
                });
            })
            ->orderBy('nama_lengkap')
            ->orderBy('id');

        $halaman = $query->paginate(
            perPage: $perHalaman,
            pageName: 'halaman',
            page: isset($filter['halaman']) ? (int) $filter['halaman'] : null,
        );

        return [
            'items' => collect($halaman->items())
                ->map(fn (Siswa $siswa) => $this->ringkas($siswa))
                ->values(),
            'ringkasan' => [
                'total' => (clone $ringkasanQuery)->count(),
                'aktif' => (clone $ringkasanQuery)->where('aktif', true)->count(),
                'nonaktif' => (clone $ringkasanQuery)->where('aktif', false)->count(),
            ],
            'filter' => [
                'cari' => $kataKunci,
                'status' => $status,
            ],
            'paginasi' => [
                'halaman' => $halaman->currentPage(),
                'halaman_terakhir' => $halaman->lastPage(),
                'per_halaman' => $halaman->perPage(),
                'total' => $halaman->total(),
                'dari' => $halaman->firstItem(),
                'sampai' => $halaman->lastItem(),
                'ada_halaman_berikutnya' => $halaman->hasMorePages(),
            ],
        ];
    }

    public function detail(Pengguna $pengguna, Siswa $siswa): array
    {
        $this->pastikanDalamCakupan($pengguna, $siswa);
        $siswa->load($this->relasiKelasAktif());

        return [
            ...$this->ringkas($siswa),
            'nik' => $siswa->nik,
            'tempat_lahir' => $siswa->tempat_lahir,
            'tanggal_lahir' => $siswa->tanggal_lahir?->toDateString(),
            'agama' => $siswa->agama,
            'status_dalam_keluarga' => $siswa->status_dalam_keluarga,
            'anak_ke' => $siswa->anak_ke,
            'orang_tua' => [
                'nama_ayah' => $siswa->nama_ayah,
                'nomor_wa_ayah' => $siswa->nomor_wa_ayah,
                'pekerjaan_ayah' => $siswa->pekerjaan_ayah,
                'nama_ibu' => $siswa->nama_ibu,
                'nomor_wa_ibu' => $siswa->nomor_wa_ibu,
                'pekerjaan_ibu' => $siswa->pekerjaan_ibu,
                'nama_wali' => $siswa->nama_wali,
                'hubungan_wali' => $siswa->hubungan_wali,
                'nomor_wa_wali' => $siswa->nomor_wa_wali,
                'kontak_absensi_utama' => $siswa->kontak_absensi_utama,
            ],
            'alamat' => $siswa->alamat,
            'sekolah_asal' => $siswa->sekolah_asal,
            'keterangan' => $siswa->keterangan,
        ];
    }

    private function queryDalamCakupan(Pengguna $pengguna): Builder
    {
        return Siswa::query()
            ->when($pengguna->membatasiCakupanWaliKelas(), function (Builder $query) use ($pengguna) {
                $query->whereHas('anggotaKelas', function (Builder $query) use ($pengguna) {
                    $query->whereIn('kelas_id', $pengguna->kelasWaliIds());
                });
            });
    }

    private function pastikanDalamCakupan(Pengguna $pengguna, Siswa $siswa): void
    {
        if (! $pengguna->membatasiCakupanWaliKelas()) {
            return;
        }

        abort_unless(
            $this->queryDalamCakupan($pengguna)->whereKey($siswa->id)->exists(),
            403,
        );
    }

    private function relasiKelasAktif(): array
    {
        return [
            'anggotaKelas' => fn ($query) => $query
                ->where('status_keanggotaan', 'aktif')
                ->whereHas('tahunPelajaran', fn ($query) => $query->where('aktif', true))
                ->with([
                    'kelas:id,nama,tingkat',
                    'tahunPelajaran:id,nama',
                ])
                ->latest('id'),
        ];
    }

    private function ringkas(Siswa $siswa): array
    {
        $anggotaKelas = $siswa->anggotaKelas->first();

        return [
            'id' => (int) $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'nis' => $siswa->nis,
            'nisn' => $siswa->nisn,
            'jenis_kelamin' => $siswa->jenis_kelamin,
            'foto_url' => $siswa->foto ? asset('storage/'.$siswa->foto) : null,
            'aktif' => (bool) $siswa->aktif,
            'kelas_aktif' => $anggotaKelas ? [
                'id' => (int) $anggotaKelas->kelas_id,
                'nama' => $anggotaKelas->kelas?->nama,
                'tingkat' => $anggotaKelas->kelas?->tingkat,
                'nomor_absen' => $anggotaKelas->nomor_absen,
                'tahun_pelajaran' => $anggotaKelas->tahunPelajaran?->nama,
            ] : null,
        ];
    }
}
