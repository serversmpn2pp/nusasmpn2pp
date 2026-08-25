<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\AkunSiswaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AkunSiswaMobileService
{
    public function __construct(private readonly AkunSiswaService $akunSiswaService) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $statusAkun = $filter['status_akun'] ?? 'semua';
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);
        [$tahunPelajaran, $daftarKelas] = $this->konteksKelas($pengguna);
        $kelasIds = $daftarKelas->pluck('id')->map(fn ($id) => (int) $id);
        $kelasId = (int) ($filter['kelas_id'] ?? 0);

        if ($kelasId && ! $kelasIds->contains($kelasId)) {
            $kelasId = 0;
        }

        $query = AnggotaKelas::query()
            ->with(['siswa.pengguna.daftarPeran', 'kelas.tahunPelajaran'])
            ->whereIn('kelas_id', $kelasIds)
            ->when($kelasId, fn (Builder $query) => $query->where('kelas_id', $kelasId))
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', function (Builder $query) use ($statusAkun, $kataKunci) {
                $query->where('aktif', true)
                    ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                        $pola = '%'.mb_strtolower($kataKunci).'%';
                        $polaIdentitas = '%'.$kataKunci.'%';

                        $query->where(function (Builder $query) use ($pola, $polaIdentitas) {
                            $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                                ->orWhere('nis', 'like', $polaIdentitas)
                                ->orWhere('nisn', 'like', $polaIdentitas)
                                ->orWhereHas('pengguna', fn (Builder $query) => $query
                                    ->whereRaw('LOWER(username) LIKE ?', [$pola]));
                        });
                    })
                    ->when($statusAkun === 'sudah', fn (Builder $query) => $query->whereHas('pengguna'))
                    ->when($statusAkun === 'belum', fn (Builder $query) => $query
                        ->whereDoesntHave('pengguna')
                        ->whereNotNull('nisn')
                        ->where('nisn', '<>', ''))
                    ->when($statusAkun === 'tanpa_nisn', fn (Builder $query) => $query
                        ->where(fn (Builder $query) => $query
                            ->whereNull('nisn')
                            ->orWhere('nisn', '')));
            })
            ->orderBy(Kelas::query()
                ->select('tingkat')
                ->whereColumn('kelas.id', 'anggota_kelas.kelas_id')
                ->limit(1))
            ->orderBy(Kelas::query()
                ->select('nama')
                ->whereColumn('kelas.id', 'anggota_kelas.kelas_id')
                ->limit(1))
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id');

        $paginator = $query->paginate($perHalaman, ['*'], 'halaman', $halaman);
        $dapatMelihatKredensial = $pengguna->memilikiIzin([
            'akun_siswa.kelola',
            'akun_siswa.cetak',
        ]);

        return [
            'items' => collect($paginator->items())
                ->map(fn (AnggotaKelas $anggota) => $this->siapkanAnggota(
                    $anggota,
                    $dapatMelihatKredensial,
                ))
                ->values(),
            'tahun_pelajaran_aktif' => $tahunPelajaran ? [
                'id' => (int) $tahunPelajaran->id,
                'nama' => $tahunPelajaran->nama,
            ] : null,
            'pilihan_kelas' => $daftarKelas->map(fn (Kelas $kelas) => [
                'id' => (int) $kelas->id,
                'nama' => $kelas->nama,
                'tingkat' => (int) $kelas->tingkat,
                'jumlah_siswa_aktif' => (int) $kelas->jumlah_siswa_aktif,
            ])->values(),
            'ringkasan' => $this->ringkasan($kelasIds, $kelasId),
            'filter' => [
                'cari' => $kataKunci,
                'status_akun' => $statusAkun,
                'kelas_id' => $kelasId ?: null,
            ],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function detail(Pengguna $pengguna, Siswa $siswa): array
    {
        [$tahunPelajaran, $daftarKelas] = $this->konteksKelas($pengguna);
        $anggota = AnggotaKelas::query()
            ->with(['siswa.pengguna.daftarPeran', 'kelas.tahunPelajaran'])
            ->where('siswa_id', $siswa->id)
            ->whereIn('kelas_id', $daftarKelas->pluck('id'))
            ->where('status_keanggotaan', 'aktif')
            ->orderByDesc('id')
            ->firstOrFail();

        return [
            ...$this->siapkanAnggota(
                $anggota,
                $pengguna->memilikiIzin(['akun_siswa.kelola', 'akun_siswa.cetak']),
                true,
            ),
            'tahun_pelajaran_aktif' => $tahunPelajaran ? [
                'id' => (int) $tahunPelajaran->id,
                'nama' => $tahunPelajaran->nama,
            ] : null,
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function buat(Siswa $siswa): Pengguna
    {
        return $this->akunSiswaService->buat($siswa);
    }

    public function buatMassal(Pengguna $pengguna, Kelas $kelas): array
    {
        [, $daftarKelas] = $this->konteksKelas($pengguna);
        abort_unless($daftarKelas->contains('id', $kelas->id), 404);

        $ringkasan = ['dibuat' => 0, 'dilewati' => 0, 'catatan' => []];
        $anggota = AnggotaKelas::query()
            ->with('siswa.pengguna')
            ->where('kelas_id', $kelas->id)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn (Builder $query) => $query->where('aktif', true))
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->get();

        foreach ($anggota as $item) {
            $siswa = $item->siswa;
            if (! $siswa || $siswa->pengguna) {
                $ringkasan['dilewati']++;

                continue;
            }

            try {
                $this->akunSiswaService->buat($siswa);
                $ringkasan['dibuat']++;
            } catch (ValidationException $exception) {
                $ringkasan['dilewati']++;
                $ringkasan['catatan'][] = $siswa->nama_lengkap.': '.collect($exception->errors())->flatten()->first();
            }
        }

        return $ringkasan;
    }

    public function resetKataSandi(Siswa $siswa): Pengguna
    {
        $akun = $this->akunYangDapatDikelola($siswa);
        $this->akunSiswaService->resetKataSandi($akun);

        return $akun->refresh();
    }

    public function ubahStatus(Siswa $siswa, bool $aktif): Pengguna
    {
        $akun = $this->akunYangDapatDikelola($siswa);
        $akun->update(['aktif' => $aktif]);

        return $akun;
    }

    private function konteksKelas(Pengguna $pengguna): array
    {
        $dapatMengaksesSemua = $pengguna->administrator()
            || $pengguna->memilikiIzin('akun_siswa.kelola');
        $kelasWaliIds = $pengguna->kelasWaliIds();
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->when(! $dapatMengaksesSemua, fn (Builder $query) => $query
                ->whereHas('kelas', fn (Builder $query) => $query->whereIn('id', $kelasWaliIds)))
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->first();

        $daftarKelas = Kelas::query()
            ->withCount([
                'anggotaKelas as jumlah_siswa_aktif' => fn (Builder $query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->whereHas('siswa', fn (Builder $query) => $query->where('aktif', true)),
            ])
            ->when(! $dapatMengaksesSemua, fn (Builder $query) => $query->whereIn('id', $kelasWaliIds))
            ->when(
                $tahunPelajaran,
                fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaran->id),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            )
            ->where('aktif', true)
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get();

        return [$tahunPelajaran, $daftarKelas];
    }

    private function akunYangDapatDikelola(Siswa $siswa): Pengguna
    {
        $siswa->loadMissing('pengguna');
        if (! $siswa->pengguna) {
            throw ValidationException::withMessages([
                'akun' => 'Siswa ini belum memiliki akun.',
            ]);
        }

        abort_if($siswa->pengguna->akun_sistem || ! $siswa->pengguna->siswa_id, 403);

        return $siswa->pengguna;
    }

    private function siapkanAnggota(
        AnggotaKelas $anggota,
        bool $dapatMelihatKredensial,
        bool $sertakanStatusSandi = false,
    ): array {
        $siswa = $anggota->siswa;
        $akun = $siswa?->pengguna;

        return [
            'anggota_kelas' => [
                'id' => (int) $anggota->id,
                'nomor_absen' => $anggota->nomor_absen,
                'kelas' => [
                    'id' => (int) $anggota->kelas->id,
                    'nama' => $anggota->kelas->nama,
                    'tingkat' => (int) $anggota->kelas->tingkat,
                ],
            ],
            'siswa' => [
                'id' => (int) $siswa->id,
                'nama' => $siswa->nama_lengkap,
                'nis' => $siswa->nis,
                'nisn' => $siswa->nisn,
                'foto_url' => $siswa->foto ? asset('storage/'.$siswa->foto) : null,
                'aktif' => (bool) $siswa->aktif,
            ],
            'status_akun' => match (true) {
                $akun !== null => $akun->aktif ? 'aktif' : 'nonaktif',
                filled($siswa->nisn) => 'belum',
                default => 'tanpa_nisn',
            },
            'akun' => $akun ? [
                'tersedia' => true,
                'id' => (int) $akun->id,
                'username' => $akun->username,
                'aktif' => (bool) $akun->aktif,
                'wajib_ganti_kata_sandi' => $sertakanStatusSandi
                    && $akun->harusMenggantiKataSandi(),
                'kata_sandi_awal_tersedia' => filled($akun->kata_sandi_awal),
                'kata_sandi_awal' => $dapatMelihatKredensial
                    ? $akun->kata_sandi_awal
                    : null,
                'terakhir_login_pada' => $akun->terakhir_login_pada?->toISOString(),
            ] : [
                'tersedia' => false,
                'id' => null,
                'username' => null,
                'aktif' => false,
                'wajib_ganti_kata_sandi' => false,
                'kata_sandi_awal_tersedia' => false,
                'kata_sandi_awal' => null,
                'terakhir_login_pada' => null,
            ],
        ];
    }

    private function ringkasan(Collection $kelasIds, int $kelasId): array
    {
        $kelasUntukRingkasan = $kelasId ? collect([$kelasId]) : $kelasIds;
        $query = Siswa::query()
            ->where('aktif', true)
            ->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->whereIn('kelas_id', $kelasUntukRingkasan)
                ->where('status_keanggotaan', 'aktif'));

        return [
            'jumlah_siswa' => (clone $query)->count(),
            'sudah_akun' => (clone $query)->whereHas('pengguna')->count(),
            'belum_akun' => (clone $query)
                ->whereDoesntHave('pengguna')
                ->whereNotNull('nisn')
                ->where('nisn', '<>', '')
                ->count(),
            'tanpa_nisn' => (clone $query)
                ->where(fn (Builder $query) => $query
                    ->whereNull('nisn')
                    ->orWhere('nisn', ''))
                ->count(),
        ];
    }

    private function hakAkses(Pengguna $pengguna): array
    {
        return [
            'dapat_kelola' => $pengguna->memilikiIzin('akun_siswa.kelola'),
            'dapat_melihat_kredensial' => $pengguna->memilikiIzin([
                'akun_siswa.kelola',
                'akun_siswa.cetak',
            ]),
        ];
    }
}
