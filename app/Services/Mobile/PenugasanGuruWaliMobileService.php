<?php

namespace App\Services\Mobile;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Peran;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PenugasanGuruWaliMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $guruWaliId = isset($filter['guru_wali_pegawai_id'])
            ? (int) $filter['guru_wali_pegawai_id'] : null;
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        $perHalaman = min(30, max(5, (int) ($filter['per_halaman'] ?? 15)));
        $dasar = PenugasanGuruWaliSiswa::query()
            ->where('aktif', true)
            ->when($guruWaliId, fn (Builder $query) => $query->where('guru_wali_pegawai_id', $guruWaliId))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola): void {
                    $query->whereHas('siswa', fn (Builder $query) => $query
                        ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]))
                        ->orWhereHas('guruWali', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                            ->orWhereRaw("LOWER(COALESCE(nip, '')) LIKE ?", [$pola]));
                });
            });
        $paginasi = (clone $dasar)
            ->with($this->relasi())
            ->latest('tanggal_mulai')->latest('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);
        $jumlahSiswaAktif = Siswa::query()->where('aktif', true)->count();
        $jumlahDitugaskan = PenugasanGuruWaliSiswa::query()
            ->where('aktif', true)
            ->whereHas('siswa', fn (Builder $query) => $query->where('aktif', true))
            ->count();
        $daftarSiswa = $this->daftarSiswa();

        return [
            'items' => collect($paginasi->items())
                ->map(fn (PenugasanGuruWaliSiswa $item) => $this->ringkas($item))
                ->values(),
            'ringkasan' => [
                'jumlah_siswa_aktif' => $jumlahSiswaAktif,
                'jumlah_ditugaskan' => $jumlahDitugaskan,
                'jumlah_belum_ditugaskan' => max(0, $jumlahSiswaAktif - $jumlahDitugaskan),
                'jumlah_guru_wali' => PenugasanGuruWaliSiswa::query()
                    ->where('aktif', true)->distinct()->count('guru_wali_pegawai_id'),
            ],
            'pilihan' => [
                'pegawai' => $this->daftarPegawai(),
                'siswa' => $daftarSiswa,
                'kelas' => $this->daftarKelas($daftarSiswa),
            ],
            'filter' => [
                'kata_kunci' => $kataKunci,
                'guru_wali_pegawai_id' => $guruWaliId,
            ],
            'paginasi' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total' => $paginasi->total(),
                'ada_halaman_berikutnya' => $paginasi->hasMorePages(),
            ],
            'hak_akses' => [
                'dapat_kelola' => $pengguna->administrator() || $pengguna->memilikiIzin('guru_wali.kelola'),
            ],
        ];
    }

    public function simpan(Pengguna $pengguna, array $data): array
    {
        $hasil = DB::transaction(function () use ($pengguna, $data): array {
            $hasil = ['baru' => 0, 'dipindahkan' => 0, 'tetap' => 0];

            foreach ($data['siswa_ids'] as $siswaId) {
                $penugasanAktif = PenugasanGuruWaliSiswa::query()
                    ->where('siswa_id', $siswaId)
                    ->where('aktif', true)
                    ->lockForUpdate()
                    ->first();

                if ((int) $penugasanAktif?->guru_wali_pegawai_id === (int) $data['guru_wali_pegawai_id']) {
                    $hasil['tetap']++;

                    continue;
                }

                if ($penugasanAktif) {
                    $penugasanAktif->update([
                        'aktif' => false,
                        'tanggal_selesai' => $data['tanggal_mulai'],
                    ]);
                    $hasil['dipindahkan']++;
                } else {
                    $hasil['baru']++;
                }

                PenugasanGuruWaliSiswa::create([
                    'siswa_id' => $siswaId,
                    'guru_wali_pegawai_id' => $data['guru_wali_pegawai_id'],
                    'tanggal_mulai' => $data['tanggal_mulai'],
                    'nomor_sk' => filled($data['nomor_sk'] ?? null) ? trim($data['nomor_sk']) : null,
                    'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
                    'aktif' => true,
                    'dibuat_oleh_pengguna_id' => $pengguna->id,
                ]);
            }

            $this->pasangPeranGuruWali((int) $data['guru_wali_pegawai_id']);

            return $hasil;
        });

        return $hasil + ['pesan' => $this->pesanHasil($hasil)];
    }

    public function akhiri(Pengguna $pengguna, PenugasanGuruWaliSiswa $penugasan): PenugasanGuruWaliSiswa
    {
        abort_unless($pengguna->administrator() || $pengguna->memilikiIzin('guru_wali.kelola'), 403);
        abort_unless($penugasan->aktif, 422, 'Penugasan Guru Wali ini sudah berakhir.');
        $penugasan->update([
            'aktif' => false,
            'tanggal_selesai' => now()->toDateString(),
        ]);

        return $penugasan->refresh()->load($this->relasi());
    }

    public function ringkas(PenugasanGuruWaliSiswa $item): array
    {
        $kelas = $item->siswa?->anggotaKelas?->first()?->kelas;

        return [
            'id' => (int) $item->id,
            'siswa' => $item->siswa ? [
                'id' => (int) $item->siswa->id,
                'nama' => $item->siswa->nama_lengkap,
                'nis' => $item->siswa->nis,
                'nisn' => $item->siswa->nisn,
            ] : null,
            'kelas' => $kelas ? [
                'id' => (int) $kelas->id,
                'nama' => $kelas->nama,
                'tingkat' => (int) $kelas->tingkat,
            ] : null,
            'guru_wali' => $item->guruWali ? [
                'id' => (int) $item->guruWali->id,
                'nama' => $item->guruWali->nama_lengkap,
                'nip' => $item->guruWali->nip,
                'jabatan' => $item->guruWali->jabatan_utama,
            ] : null,
            'tanggal_mulai' => $item->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $item->tanggal_selesai?->toDateString(),
            'nomor_sk' => $item->nomor_sk,
            'catatan' => $item->catatan,
            'aktif' => (bool) $item->aktif,
            'dibuat_oleh' => $item->dibuatOlehPengguna?->nama,
        ];
    }

    private function relasi(): array
    {
        return [
            'siswa' => fn ($query) => $query->with(['anggotaKelas' => fn ($query) => $query
                ->where('status_keanggotaan', 'aktif')
                ->latest('tanggal_masuk')
                ->with('kelas:id,nama,tingkat')]),
            'guruWali:id,nama_lengkap,nip,jabatan_utama',
            'dibuatOlehPengguna:id,nama',
        ];
    }

    private function daftarPegawai(): array
    {
        return Pegawai::query()
            ->withCount(['penugasanGuruWaliSiswa as jumlah_siswa_wali_aktif' => fn ($query) => $query->where('aktif', true)])
            ->where('aktif', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip', 'jabatan_utama'])
            ->map(fn (Pegawai $pegawai) => [
                'id' => (int) $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nip' => $pegawai->nip,
                'jabatan' => $pegawai->jabatan_utama,
                'jumlah_siswa_aktif' => (int) $pegawai->jumlah_siswa_wali_aktif,
            ])->values()->all();
    }

    private function daftarSiswa(): array
    {
        return Siswa::query()
            ->with([
                'anggotaKelas' => fn ($query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->latest('tanggal_masuk')
                    ->with('kelas:id,nama,tingkat'),
                'penugasanGuruWaliSiswa' => fn ($query) => $query
                    ->where('aktif', true)
                    ->with('guruWali:id,nama_lengkap,nip'),
            ])
            ->where('aktif', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nis', 'nisn'])
            ->map(function (Siswa $siswa): array {
                $kelas = $siswa->anggotaKelas->first()?->kelas;
                $penugasan = $siswa->penugasanGuruWaliSiswa->first();

                return [
                    'id' => (int) $siswa->id,
                    'nama' => $siswa->nama_lengkap,
                    'nis' => $siswa->nis,
                    'nisn' => $siswa->nisn,
                    'kelas' => $kelas ? [
                        'id' => (int) $kelas->id,
                        'nama' => $kelas->nama,
                        'tingkat' => (int) $kelas->tingkat,
                    ] : null,
                    'penugasan_aktif' => $penugasan ? [
                        'id' => (int) $penugasan->id,
                        'guru_wali' => [
                            'id' => (int) $penugasan->guruWali?->id,
                            'nama' => $penugasan->guruWali?->nama_lengkap ?? '-',
                            'nip' => $penugasan->guruWali?->nip,
                        ],
                    ] : null,
                ];
            })
            ->sortBy(fn (array $siswa) => sprintf(
                '%02d|%s|%s',
                $siswa['kelas']['tingkat'] ?? 99,
                mb_strtolower($siswa['kelas']['nama'] ?? 'zzzz'),
                mb_strtolower($siswa['nama']),
            ))
            ->values()->all();
    }

    private function daftarKelas(array $daftarSiswa): array
    {
        return collect($daftarSiswa)
            ->pluck('kelas')->filter()->unique('id')
            ->sortBy(fn (array $kelas) => sprintf('%02d|%s', $kelas['tingkat'], mb_strtolower($kelas['nama'])))
            ->values()->all();
    }

    private function pasangPeranGuruWali(int $pegawaiId): void
    {
        $pengguna = Pengguna::query()->where('pegawai_id', $pegawaiId)->first();
        $peran = Peran::query()->where('kode', 'guru_wali')->first();
        if ($pengguna && $peran) {
            $pengguna->daftarPeran()->syncWithoutDetaching([$peran->id]);
        }
    }

    private function pesanHasil(array $hasil): string
    {
        $bagian = [];
        $berubah = $hasil['baru'] + $hasil['dipindahkan'];
        if ($berubah > 0) {
            $bagian[] = $berubah.' siswa berhasil ditugaskan';
        }
        if ($hasil['dipindahkan'] > 0) {
            $bagian[] = $hasil['dipindahkan'].' di antaranya dipindahkan dari Guru Wali sebelumnya';
        }
        if ($hasil['tetap'] > 0) {
            $bagian[] = $hasil['tetap'].' siswa sudah berada pada Guru Wali yang dipilih sehingga tidak diubah';
        }

        return implode('. ', $bagian).'.';
    }
}
