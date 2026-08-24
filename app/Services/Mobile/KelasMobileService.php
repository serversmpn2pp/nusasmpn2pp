<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Builder;

class KelasMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $status = $filter['status'] ?? 'semua';
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $tahunPelajaranId = isset($filter['tahun_pelajaran_id'])
            ? (int) $filter['tahun_pelajaran_id']
            : null;
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $ringkasanQuery = $this->queryDalamCakupan($pengguna);

        $query = $this->queryDalamCakupan($pengguna)
            ->with([
                'tahunPelajaran:id,nama,aktif',
                'waliKelas:id,nama_lengkap,nip,foto,jabatan_utama,jenis_pegawai',
            ])
            ->withCount([
                'anggotaKelas as jumlah_siswa_aktif' => fn (Builder $query) => $query
                    ->where('status_keanggotaan', 'aktif'),
            ])
            ->when($tahunPelajaranId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';

                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereHas('waliKelas', function (Builder $query) use ($pola) {
                            $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola]);
                        });
                });
            })
            ->orderByDesc(
                TahunPelajaran::select('aktif')
                    ->whereColumn('tahun_pelajaran.id', 'kelas.tahun_pelajaran_id')
                    ->limit(1)
            )
            ->orderByDesc('tahun_pelajaran_id')
            ->orderBy('tingkat')
            ->orderBy('nama');

        $halaman = $query->paginate(
            perPage: $perHalaman,
            pageName: 'halaman',
            page: isset($filter['halaman']) ? (int) $filter['halaman'] : null,
        );

        return [
            'items' => collect($halaman->items())
                ->map(fn (Kelas $kelas) => $this->ringkas($kelas))
                ->values(),
            'ringkasan' => [
                'total' => (clone $ringkasanQuery)->count(),
                'aktif' => (clone $ringkasanQuery)->where('aktif', true)->count(),
                'nonaktif' => (clone $ringkasanQuery)->where('aktif', false)->count(),
            ],
            'tahun_pelajaran' => $this->tahunPelajaranDalamCakupan($pengguna),
            'filter' => [
                'cari' => $kataKunci,
                'status' => $status,
                'tahun_pelajaran_id' => $tahunPelajaranId,
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

    public function detail(Pengguna $pengguna, Kelas $kelas): array
    {
        $this->pastikanDalamCakupan($pengguna, $kelas);
        $kelas->load([
            'tahunPelajaran:id,nama,aktif,tanggal_mulai,tanggal_selesai',
            'waliKelas:id,nama_lengkap,nip,foto,jabatan_utama,jenis_pegawai',
            'anggotaKelas' => fn ($query) => $query
                ->with(['siswa:id,nama_lengkap,nis,nisn,foto,jenis_kelamin,aktif'])
                ->orderByRaw("CASE WHEN status_keanggotaan = 'aktif' THEN 0 ELSE 1 END")
                ->orderBy('nomor_absen')
                ->orderBy('id'),
        ]);
        $kelas->setAttribute(
            'jumlah_siswa_aktif',
            $kelas->anggotaKelas->where('status_keanggotaan', 'aktif')->count(),
        );
        $dapatMelihatJadwal = $pengguna->memilikiIzin(['jadwal.lihat', 'jadwal.kelola']);
        $dapatKelolaJadwal = $pengguna->memilikiIzin('jadwal.kelola') && $kelas->aktif;

        return [
            ...$this->ringkas($kelas),
            'keterangan' => $kelas->keterangan,
            'tahun_pelajaran' => $kelas->tahunPelajaran ? [
                'id' => (int) $kelas->tahunPelajaran->id,
                'nama' => $kelas->tahunPelajaran->nama,
                'aktif' => (bool) $kelas->tahunPelajaran->aktif,
                'tanggal_mulai' => $kelas->tahunPelajaran->tanggal_mulai?->toDateString(),
                'tanggal_selesai' => $kelas->tahunPelajaran->tanggal_selesai?->toDateString(),
            ] : null,
            'anggota_siswa' => $kelas->anggotaKelas
                ->filter(fn (AnggotaKelas $anggota) => $anggota->siswa !== null)
                ->map(fn (AnggotaKelas $anggota) => [
                    'id' => (int) $anggota->id,
                    'nomor_absen' => $anggota->nomor_absen,
                    'status_keanggotaan' => $anggota->status_keanggotaan,
                    'tanggal_masuk' => $anggota->tanggal_masuk?->toDateString(),
                    'tanggal_keluar' => $anggota->tanggal_keluar?->toDateString(),
                    'keterangan' => $anggota->keterangan,
                    'siswa' => [
                        'id' => (int) $anggota->siswa->id,
                        'nama' => $anggota->siswa->nama_lengkap,
                        'nis' => $anggota->siswa->nis,
                        'nisn' => $anggota->siswa->nisn,
                        'jenis_kelamin' => $anggota->siswa->jenis_kelamin,
                        'foto_url' => $anggota->siswa->foto ? asset('storage/'.$anggota->siswa->foto) : null,
                        'aktif' => (bool) $anggota->siswa->aktif,
                    ],
                ])
                ->values(),
            'jadwal_kelas' => $dapatMelihatJadwal
                ? $this->jadwalKelas($kelas)
                : null,
            'hak_akses' => [
                'dapat_kelola_anggota' => $pengguna->memilikiIzin('kelas.kelola'),
                'dapat_melihat_jadwal' => $dapatMelihatJadwal,
                'dapat_kelola_jadwal' => $dapatKelolaJadwal,
            ],
        ];
    }

    public function calonAnggota(Pengguna $pengguna, Kelas $kelas, string $kataKunci = ''): array
    {
        $this->pastikanDalamCakupan($pengguna, $kelas);
        abort_unless($pengguna->memilikiIzin('kelas.kelola'), 403);

        $siswaSudahDitempatkan = AnggotaKelas::query()
            ->where('tahun_pelajaran_id', $kelas->tahun_pelajaran_id)
            ->select('siswa_id');
        $kataKunci = trim($kataKunci);
        $siswa = Siswa::query()
            ->where('aktif', true)
            ->whereNotIn('id', $siswaSudahDitempatkan)
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';

                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(COALESCE(nis, ?)) LIKE ?', ['', $pola])
                        ->orWhereRaw('LOWER(COALESCE(nisn, ?)) LIKE ?', ['', $pola]);
                });
            })
            ->orderBy('nama_lengkap')
            ->limit(50)
            ->get(['id', 'nama_lengkap', 'nis', 'nisn', 'foto', 'jenis_kelamin', 'aktif']);

        return [
            'items' => $siswa->map(fn (Siswa $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama_lengkap,
                'nis' => $item->nis,
                'nisn' => $item->nisn,
                'jenis_kelamin' => $item->jenis_kelamin,
                'foto_url' => $item->foto ? asset('storage/'.$item->foto) : null,
                'aktif' => (bool) $item->aktif,
            ])->values(),
            'cari' => $kataKunci,
            'jumlah' => $siswa->count(),
            'kapasitas_tersedia' => $kelas->kapasitas === null
                ? null
                : max(0, (int) $kelas->kapasitas - $kelas->anggotaKelas()->count()),
        ];
    }

    private function queryDalamCakupan(Pengguna $pengguna): Builder
    {
        return Kelas::query()
            ->when(
                $pengguna->membatasiCakupanWaliKelas(),
                fn (Builder $query) => $query->whereIn('id', $pengguna->kelasWaliIds()),
            );
    }

    private function pastikanDalamCakupan(Pengguna $pengguna, Kelas $kelas): void
    {
        abort_unless($pengguna->dapatMengaksesKelasSebagaiWali($kelas->id), 403);
    }

    private function tahunPelajaranDalamCakupan(Pengguna $pengguna): array
    {
        return TahunPelajaran::query()
            ->when($pengguna->membatasiCakupanWaliKelas(), function (Builder $query) use ($pengguna) {
                $query->whereHas(
                    'kelas',
                    fn (Builder $query) => $query->whereIn('id', $pengguna->kelasWaliIds()),
                );
            })
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get(['id', 'nama', 'aktif'])
            ->map(fn (TahunPelajaran $tahun) => [
                'id' => (int) $tahun->id,
                'nama' => $tahun->nama,
                'aktif' => (bool) $tahun->aktif,
            ])
            ->values()
            ->all();
    }

    private function ringkas(Kelas $kelas): array
    {
        $jumlahSiswa = (int) ($kelas->jumlah_siswa_aktif ?? 0);
        $kapasitas = $kelas->kapasitas ? (int) $kelas->kapasitas : null;

        return [
            'id' => (int) $kelas->id,
            'nama' => $kelas->nama,
            'tingkat' => $kelas->tingkat,
            'kapasitas' => $kapasitas,
            'jumlah_siswa_aktif' => $jumlahSiswa,
            'kapasitas_tersedia' => $kapasitas === null ? null : max(0, $kapasitas - $jumlahSiswa),
            'aktif' => (bool) $kelas->aktif,
            'tahun_pelajaran' => $kelas->tahunPelajaran ? [
                'id' => (int) $kelas->tahunPelajaran->id,
                'nama' => $kelas->tahunPelajaran->nama,
                'aktif' => (bool) $kelas->tahunPelajaran->aktif,
            ] : null,
            'wali_kelas' => $kelas->waliKelas ? [
                'id' => (int) $kelas->waliKelas->id,
                'nama' => $kelas->waliKelas->nama_lengkap,
                'nip' => $kelas->waliKelas->nip,
                'jabatan' => $kelas->waliKelas->jabatan_utama ?: $kelas->waliKelas->jenis_pegawai,
                'foto_url' => $kelas->waliKelas->foto ? asset('storage/'.$kelas->waliKelas->foto) : null,
            ] : null,
        ];
    }

    private function jadwalKelas(Kelas $kelas): array
    {
        $jadwal = JadwalPelajaran::query()
            ->with([
                'mataPelajaran',
                'guruMataPelajaran.mataPelajaran',
                'guruMataPelajaran.pegawai',
            ])
            ->where('tahun_pelajaran_id', $kelas->tahun_pelajaran_id)
            ->where('kelas_id', $kelas->id)
            ->where('aktif', true)
            ->get()
            ->keyBy('jam_pelajaran_id');
        $jamPelajaran = JamPelajaran::query()
            ->where('aktif', true)
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->orderBy('nomor_jam')
            ->get();
        $hariHariIni = [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][now()->dayOfWeekIso];

        return [
            'hari_ini' => $hariHariIni,
            'jumlah_terisi' => $jadwal->count(),
            'hari' => collect(JamPelajaran::DAFTAR_HARI)
                ->map(function (string $label, string $kode) use ($jamPelajaran, $jadwal) {
                    $slotHari = $jamPelajaran->where('hari', $kode)->values();

                    if ($slotHari->isEmpty()) {
                        return null;
                    }

                    return [
                        'kode' => $kode,
                        'label' => $label,
                        'slots' => $slotHari->map(function (JamPelajaran $jam) use ($jadwal) {
                            $item = $jadwal->get($jam->id);
                            $mataPelajaran = $item?->mataPelajaranTerjadwal();
                            $pegawai = $item?->guruMataPelajaran?->pegawai;

                            return [
                                'id' => (int) $jam->id,
                                'nomor_jam' => (int) $jam->nomor_jam,
                                'label' => $jam->label,
                                'jam_mulai' => $jam->formatJam($jam->jam_mulai),
                                'jam_selesai' => $jam->formatJam($jam->jam_selesai),
                                'jenis' => $jam->jenis,
                                'jenis_label' => $jam->labelJenis(),
                                'terisi' => $item !== null,
                                'pilihan_jadwal' => $item?->guru_mata_pelajaran_id
                                    ? 'guru:'.$item->guru_mata_pelajaran_id
                                    : ($item?->mata_pelajaran_id ? 'kegiatan:'.$item->mata_pelajaran_id : null),
                                'mata_pelajaran' => $mataPelajaran ? [
                                    'id' => (int) $mataPelajaran->id,
                                    'nama' => $mataPelajaran->nama,
                                    'kelompok' => $mataPelajaran->kelompok,
                                ] : null,
                                'guru' => $pegawai ? [
                                    'id' => (int) $pegawai->id,
                                    'nama' => $pegawai->nama_lengkap,
                                    'nip' => $pegawai->nip,
                                ] : null,
                                'keterangan' => $item?->keterangan ?: $jam->keterangan,
                            ];
                        })->values(),
                    ];
                })
                ->filter()
                ->values(),
        ];
    }
}
