<?php

namespace App\Services\Mobile;

use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeranMobileService
{
    private const URUTAN_KELOMPOK = [
        'Umum',
        'Akun',
        'Pegawai',
        'Siswa',
        'Akademik',
        'Nilai',
        'Presensi',
        'Laporan',
        'BK',
        'Kurikulum',
        'Sarpras',
        'Keamanan',
        'Kebersihan',
    ];

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $perHalaman = (int) ($filter['per_halaman'] ?? 12);
        $halaman = (int) ($filter['halaman'] ?? 1);
        $jumlahIzinAktif = Izin::where('aktif', true)->count();

        $query = Peran::query()
            ->withCount([
                'pengguna',
                'izin' => fn (Builder $query) => $query->where('izin.aktif', true),
            ])
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(kode) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(deskripsi, '')) LIKE ?", [$pola]);
                });
            })
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->orderByDesc('sistem')
            ->orderBy('nama')
            ->orderBy('id');

        $paginator = $query->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())
                ->map(fn (Peran $peran) => $this->siapkanPeran($peran, $jumlahIzinAktif))
                ->values(),
            'ringkasan' => $this->ringkasan(),
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
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function referensi(Pengguna $pengguna): array
    {
        return [
            'kelompok_izin' => $this->kelompokIzin(),
            'jumlah_izin' => Izin::where('aktif', true)->count(),
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function detail(Pengguna $pengguna, Peran $peran): array
    {
        $peran->loadMissing(['izin' => fn ($query) => $query->where('izin.aktif', true)]);
        $peran->loadCount([
            'pengguna',
            'izin' => fn (Builder $query) => $query->where('izin.aktif', true),
        ]);

        $izinAktifIds = Izin::where('aktif', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return [
            'peran' => [
                ...$this->siapkanPeran($peran, $izinAktifIds->count()),
                'izin_ids' => $peran->kode === 'administrator'
                    ? $izinAktifIds
                    : $peran->izin->pluck('id')->map(fn ($id) => (int) $id)->values(),
            ],
            'kelompok_izin' => $this->kelompokIzin(),
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function buat(array $data): Peran
    {
        $kode = $this->buatKode($data['kode'] ?? null, $data['nama']);
        $this->pastikanKodeUnik($kode);

        return DB::transaction(function () use ($data, $kode) {
            $peran = Peran::create([
                'nama' => $data['nama'],
                'kode' => $kode,
                'deskripsi' => $data['deskripsi'] ?? null,
                'sistem' => false,
                'aktif' => (bool) ($data['aktif'] ?? false),
            ]);
            $peran->izin()->sync($data['izin_ids'] ?? []);

            return $peran;
        });
    }

    public function ubah(Peran $peran, array $data): Peran
    {
        $kode = $peran->sistem
            ? $peran->kode
            : $this->buatKode($data['kode'] ?? null, $data['nama']);
        $this->pastikanKodeUnik($kode, $peran);

        return DB::transaction(function () use ($peran, $data, $kode) {
            $peran->update([
                'nama' => $data['nama'],
                'kode' => $kode,
                'deskripsi' => $data['deskripsi'] ?? null,
                'aktif' => $peran->sistem ? true : (bool) ($data['aktif'] ?? false),
            ]);
            $peran->izin()->sync($this->izinUntukDisimpan($peran, $data['izin_ids'] ?? []));

            return $peran;
        });
    }

    public function nonaktifkan(Peran $peran): void
    {
        if ($peran->sistem) {
            throw ValidationException::withMessages([
                'peran' => 'Peran sistem tidak dapat dinonaktifkan.',
            ]);
        }

        $peran->update(['aktif' => false]);
    }

    private function ringkasan(): array
    {
        return [
            'total' => Peran::count(),
            'aktif' => Peran::where('aktif', true)->count(),
            'sistem' => Peran::where('sistem', true)->count(),
            'tambahan' => Peran::where('sistem', false)->count(),
            'izin_aktif' => Izin::where('aktif', true)->count(),
            'pengguna_terhubung' => DB::table('pengguna_peran')
                ->distinct()
                ->count('pengguna_id'),
        ];
    }

    private function siapkanPeran(Peran $peran, int $jumlahIzinAktif): array
    {
        $jumlahIzin = $peran->kode === 'administrator'
            ? $jumlahIzinAktif
            : (int) ($peran->izin_count ?? 0);

        return [
            'id' => (int) $peran->id,
            'nama' => $peran->nama,
            'kode' => $peran->kode,
            'deskripsi' => $peran->deskripsi,
            'sistem' => (bool) $peran->sistem,
            'aktif' => (bool) $peran->aktif,
            'jumlah_izin' => $jumlahIzin,
            'jumlah_pengguna' => (int) ($peran->pengguna_count ?? 0),
            'persentase_izin' => $jumlahIzinAktif > 0
                ? (int) round(($jumlahIzin / $jumlahIzinAktif) * 100)
                : 0,
        ];
    }

    private function kelompokIzin(): array
    {
        $urutan = array_flip(self::URUTAN_KELOMPOK);

        return Izin::query()
            ->where('aktif', true)
            ->orderBy('nama')
            ->get()
            ->groupBy('kelompok')
            ->sortBy(fn ($izin, $kelompok) => $urutan[$kelompok] ?? 999)
            ->map(fn ($izin, $kelompok) => [
                'nama' => $kelompok,
                'izin' => $izin->map(fn (Izin $izin) => [
                    'id' => (int) $izin->id,
                    'nama' => $izin->nama,
                    'kode' => $izin->kode,
                    'deskripsi' => $izin->deskripsi,
                ])->values(),
            ])
            ->values()
            ->all();
    }

    private function hakAkses(Pengguna $pengguna): array
    {
        return ['dapat_kelola' => $pengguna->memilikiIzin('peran.kelola')];
    }

    private function buatKode(?string $kode, string $nama): string
    {
        return str(filled($kode) ? $kode : $nama)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    private function pastikanKodeUnik(string $kode, ?Peran $kecuali = null): void
    {
        $query = Peran::where('kode', $kode);
        if ($kecuali) {
            $query->whereKeyNot($kecuali->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'kode' => 'Kode peran sudah digunakan.',
            ]);
        }
    }

    private function izinUntukDisimpan(Peran $peran, array $izinIds): array
    {
        if ($peran->kode === 'administrator') {
            return Izin::where('aktif', true)->pluck('id')->all();
        }

        return $izinIds;
    }
}
