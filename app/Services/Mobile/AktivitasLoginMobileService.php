<?php

namespace App\Services\Mobile;

use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\RiwayatLogin;
use Illuminate\Database\Eloquent\Builder;

class AktivitasLoginMobileService
{
    public function daftar(array $filter): array
    {
        $tampilan = $filter['tampilan'] ?? 'pengguna';

        return $tampilan === 'riwayat'
            ? $this->daftarRiwayat($filter)
            : $this->daftarPengguna($filter);
    }

    public function detail(RiwayatLogin $riwayatLogin): array
    {
        $riwayatLogin->loadMissing(['pengguna.daftarPeran', 'pengguna.orangTuaWali']);

        return [
            'riwayat' => $this->siapkanRiwayat($riwayatLogin, sertakanUserAgent: true),
        ];
    }

    private function daftarPengguna(array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $jenisAkun = $filter['jenis_akun'] ?? 'semua';
        $statusLogin = $filter['status_login'] ?? 'semua';
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);

        $query = Pengguna::query()
            ->with(['daftarPeran', 'orangTuaWali', 'loginBerhasilTerbaru'])
            ->withCount([
                'riwayatLogin as jumlah_login_berhasil' => fn (Builder $query) => $query->where('berhasil', true),
                'riwayatLogin as jumlah_login_gagal' => fn (Builder $query) => $query->where('berhasil', false),
            ])
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(username) LIKE ?', [$pola]);
                });
            })
            ->when($jenisAkun !== 'semua', fn (Builder $query) => $this->terapkanJenisAkun($query, $jenisAkun))
            ->when($statusLogin === 'pernah', fn (Builder $query) => $query->whereNotNull('terakhir_login_pada'))
            ->when($statusLogin === 'belum', fn (Builder $query) => $query->whereNull('terakhir_login_pada'))
            ->orderByRaw('terakhir_login_pada IS NULL')
            ->orderByDesc('terakhir_login_pada')
            ->orderBy('nama')
            ->orderBy('id');

        $paginator = $query->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())
                ->map(fn (Pengguna $pengguna) => $this->siapkanPengguna($pengguna))
                ->values(),
            ...$this->metadata($filter, $paginator, 'pengguna'),
        ];
    }

    private function daftarRiwayat(array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $jenisAkun = $filter['jenis_akun'] ?? 'semua';
        $statusPercobaan = $filter['status_percobaan'] ?? 'semua';
        $perangkat = $filter['perangkat'] ?? 'semua';
        $tanggalMulai = $filter['tanggal_mulai'] ?? null;
        $tanggalSelesai = $filter['tanggal_selesai'] ?? null;
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);

        $query = RiwayatLogin::query()
            ->with(['pengguna.daftarPeran', 'pengguna.orangTuaWali'])
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(username) LIKE ?', [$pola])
                        ->orWhereHas('pengguna', fn (Builder $pengguna) => $pengguna
                            ->whereRaw('LOWER(nama) LIKE ?', [$pola]));
                });
            })
            ->when($jenisAkun !== 'semua', function (Builder $query) use ($jenisAkun) {
                $query->whereHas('pengguna', fn (Builder $pengguna) => $this->terapkanJenisAkun($pengguna, $jenisAkun));
            })
            ->when($statusPercobaan === 'berhasil', fn (Builder $query) => $query->where('berhasil', true))
            ->when($statusPercobaan === 'gagal', fn (Builder $query) => $query->where('berhasil', false))
            ->when($perangkat !== 'semua', fn (Builder $query) => $this->terapkanPerangkat($query, $perangkat))
            ->when($tanggalMulai, fn (Builder $query) => $query->whereDate('created_at', '>=', $tanggalMulai))
            ->when($tanggalSelesai, fn (Builder $query) => $query->whereDate('created_at', '<=', $tanggalSelesai))
            ->latest('created_at')
            ->latest('id');

        $paginator = $query->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())
                ->map(fn (RiwayatLogin $riwayat) => $this->siapkanRiwayat($riwayat))
                ->values(),
            ...$this->metadata($filter, $paginator, 'riwayat'),
        ];
    }

    private function metadata(array $filter, $paginator, string $tampilan): array
    {
        return [
            'ringkasan' => $this->ringkasan(),
            'filter' => [
                'tampilan' => $tampilan,
                'cari' => trim((string) ($filter['cari'] ?? '')),
                'jenis_akun' => $filter['jenis_akun'] ?? 'semua',
                'status_login' => $filter['status_login'] ?? 'semua',
                'status_percobaan' => $filter['status_percobaan'] ?? 'semua',
                'perangkat' => $filter['perangkat'] ?? 'semua',
                'tanggal_mulai' => $filter['tanggal_mulai'] ?? null,
                'tanggal_selesai' => $filter['tanggal_selesai'] ?? null,
            ],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    private function ringkasan(): array
    {
        return [
            'jumlah_akun' => Pengguna::count(),
            'login_hari_ini' => Pengguna::whereDate('terakhir_login_pada', today())->count(),
            'belum_pernah_login' => Pengguna::whereNull('terakhir_login_pada')->count(),
            'gagal_hari_ini' => RiwayatLogin::where('berhasil', false)
                ->whereDate('created_at', today())
                ->count(),
        ];
    }

    private function siapkanPengguna(Pengguna $pengguna): array
    {
        return [
            'id' => (int) $pengguna->id,
            'nama' => $pengguna->nama,
            'username' => $pengguna->username,
            'jenis_akun' => $this->jenisAkun($pengguna),
            'peran' => $this->peran($pengguna),
            'aktif' => (bool) $pengguna->aktif,
            'terakhir_login_pada' => $pengguna->terakhir_login_pada?->toISOString(),
            'perangkat_terakhir' => $pengguna->loginBerhasilTerbaru?->labelPerangkat(),
            'jumlah_login_berhasil' => (int) ($pengguna->jumlah_login_berhasil ?? 0),
            'jumlah_login_gagal' => (int) ($pengguna->jumlah_login_gagal ?? 0),
        ];
    }

    private function siapkanRiwayat(RiwayatLogin $riwayat, bool $sertakanUserAgent = false): array
    {
        return [
            'id' => (int) $riwayat->id,
            'username' => $riwayat->username,
            'berhasil' => (bool) $riwayat->berhasil,
            'alamat_ip' => $riwayat->alamat_ip,
            'perangkat' => [
                'kode' => $this->kodePerangkat($riwayat->user_agent),
                'label' => $riwayat->labelPerangkat(),
                'user_agent' => $sertakanUserAgent ? $riwayat->user_agent : null,
            ],
            'waktu' => $riwayat->created_at?->toISOString(),
            'pengguna' => $riwayat->pengguna ? [
                'id' => (int) $riwayat->pengguna->id,
                'nama' => $riwayat->pengguna->nama,
                'username' => $riwayat->pengguna->username,
                'jenis_akun' => $this->jenisAkun($riwayat->pengguna),
                'peran' => $this->peran($riwayat->pengguna),
                'aktif' => (bool) $riwayat->pengguna->aktif,
            ] : null,
        ];
    }

    private function jenisAkun(Pengguna $pengguna): array
    {
        $kode = match (true) {
            $pengguna->akun_sistem => 'administrator',
            filled($pengguna->pegawai_id) => 'pegawai',
            filled($pengguna->siswa_id) => 'siswa',
            $pengguna->akunOrangTua() => 'orang_tua',
            default => 'lainnya',
        };

        return ['kode' => $kode, 'label' => $pengguna->labelJenisAkun()];
    }

    private function peran(Pengguna $pengguna): array
    {
        $peran = $pengguna->daftarPeran
            ->sortBy('nama')
            ->map(fn (Peran $peran) => [
                'id' => (int) $peran->id,
                'kode' => $peran->kode,
                'nama' => $peran->nama,
            ])
            ->values();

        if ($peran->isNotEmpty()) {
            return $peran->all();
        }

        return [[
            'id' => null,
            'kode' => (string) ($pengguna->peran ?: 'tanpa_role'),
            'nama' => str((string) ($pengguna->peran ?: 'tanpa role'))
                ->replace('_', ' ')
                ->title()
                ->toString(),
        ]];
    }

    private function terapkanJenisAkun(Builder $query, string $jenisAkun): Builder
    {
        return match ($jenisAkun) {
            'administrator' => $query->where('akun_sistem', true),
            'pegawai' => $query->whereNotNull('pegawai_id')->where('akun_sistem', false),
            'siswa' => $query->whereNotNull('siswa_id')->where('akun_sistem', false),
            'orang_tua' => $query
                ->whereNull('pegawai_id')
                ->whereNull('siswa_id')
                ->where('akun_sistem', false)
                ->whereHas('orangTuaWali'),
            default => $query,
        };
    }

    private function terapkanPerangkat(Builder $query, string $perangkat): Builder
    {
        $kolom = "LOWER(COALESCE(user_agent, ''))";

        return match ($perangkat) {
            'android' => $query->whereRaw($kolom.' LIKE ?', ['%android%']),
            'ios' => $query->where(fn (Builder $query) => $query
                ->whereRaw($kolom.' LIKE ?', ['%iphone%'])
                ->orWhereRaw($kolom.' LIKE ?', ['%ipad%'])),
            'windows' => $query->whereRaw($kolom.' LIKE ?', ['%windows%']),
            'mac' => $query->where(fn (Builder $query) => $query
                ->whereRaw($kolom.' LIKE ?', ['%macintosh%'])
                ->orWhereRaw($kolom.' LIKE ?', ['%mac os%'])),
            'linux' => $query
                ->whereRaw($kolom.' LIKE ?', ['%linux%'])
                ->whereRaw($kolom.' NOT LIKE ?', ['%android%']),
            'lainnya' => $query
                ->whereRaw($kolom.' NOT LIKE ?', ['%android%'])
                ->whereRaw($kolom.' NOT LIKE ?', ['%iphone%'])
                ->whereRaw($kolom.' NOT LIKE ?', ['%ipad%'])
                ->whereRaw($kolom.' NOT LIKE ?', ['%windows%'])
                ->whereRaw($kolom.' NOT LIKE ?', ['%macintosh%'])
                ->whereRaw($kolom.' NOT LIKE ?', ['%mac os%'])
                ->whereRaw($kolom.' NOT LIKE ?', ['%linux%']),
            default => $query,
        };
    }

    private function kodePerangkat(?string $userAgent): string
    {
        $userAgent = mb_strtolower((string) $userAgent);

        return match (true) {
            str_contains($userAgent, 'android') => 'android',
            str_contains($userAgent, 'iphone'), str_contains($userAgent, 'ipad') => 'ios',
            str_contains($userAgent, 'windows') => 'windows',
            str_contains($userAgent, 'macintosh'), str_contains($userAgent, 'mac os') => 'mac',
            str_contains($userAgent, 'linux') => 'linux',
            default => 'lainnya',
        };
    }
}
