<?php

namespace App\Services\Mobile;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AkunPegawaiMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $statusAkun = $filter['status_akun'] ?? 'semua';
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);

        $query = Pegawai::query()
            ->with('pengguna.daftarPeran')
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $polaIdentitas = '%'.$kataKunci.'%';

                $query->where(function (Builder $query) use ($pola, $polaIdentitas) {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhere('nip', 'like', $polaIdentitas)
                        ->orWhereRaw('LOWER(COALESCE(jabatan_utama, \'\')) LIKE ?', [$pola])
                        ->orWhereHas('pengguna', fn (Builder $query) => $query
                            ->whereRaw('LOWER(username) LIKE ?', [$pola]));
                });
            })
            ->when($statusAkun === 'sudah', fn (Builder $query) => $query->whereHas('pengguna'))
            ->when($statusAkun === 'belum', fn (Builder $query) => $query
                ->whereDoesntHave('pengguna')
                ->whereNotNull('nip')
                ->where('nip', '<>', ''))
            ->when($statusAkun === 'tanpa_nip', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereNull('nip')
                    ->orWhere('nip', '')))
            ->orderByDesc('aktif')
            ->orderBy('nama_lengkap')
            ->orderBy('id');

        $paginator = $query->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())
                ->map(fn (Pegawai $pegawai) => $this->siapkanPegawai($pegawai))
                ->values(),
            'ringkasan' => $this->ringkasan(),
            'pilihan_peran' => $this->pilihanPeran(),
            'filter' => [
                'cari' => $kataKunci,
                'status_akun' => $statusAkun,
            ],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
            'hak_akses' => [
                'dapat_kelola' => $pengguna->memilikiIzin('akun.kelola'),
            ],
        ];
    }

    public function detail(Pengguna $pengguna, Pegawai $pegawai): array
    {
        $pegawai->loadMissing('pengguna.daftarPeran');

        return [
            ...$this->siapkanPegawai($pegawai, sertakanStatusSandi: true),
            'pilihan_peran' => $this->pilihanPeran(),
            'hak_akses' => [
                'dapat_kelola' => $pengguna->memilikiIzin('akun.kelola'),
            ],
        ];
    }

    public function buat(Pegawai $pegawai): Pengguna
    {
        $pegawai->loadMissing('pengguna');

        if ($pegawai->pengguna) {
            throw ValidationException::withMessages([
                'akun' => 'Pegawai ini sudah memiliki akun.',
            ]);
        }

        $username = $this->usernameDariNip($pegawai->nip);
        if (! $username) {
            throw ValidationException::withMessages([
                'nip' => 'Akun belum bisa dibuat karena NIP pegawai masih kosong.',
            ]);
        }

        if (Pengguna::where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'username' => 'Username '.$username.' sudah dipakai akun lain.',
            ]);
        }

        return DB::transaction(function () use ($pegawai, $username) {
            $akun = Pengguna::create([
                'pegawai_id' => $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'username' => $username,
                'kata_sandi' => Hash::make(config('nusa.kata_sandi_default_pegawai')),
                'peran' => 'pegawai',
                'aktif' => (bool) $pegawai->aktif,
                'akun_sistem' => false,
            ]);
            $this->pasangPeranPegawai($akun);

            return $akun;
        });
    }

    public function buatMassal(): array
    {
        $ringkasan = ['dibuat' => 0, 'dilewati' => 0, 'catatan' => []];

        Pegawai::query()
            ->where('aktif', true)
            ->whereNotNull('nip')
            ->where('nip', '<>', '')
            ->whereDoesntHave('pengguna')
            ->orderBy('nama_lengkap')
            ->get()
            ->each(function (Pegawai $pegawai) use (&$ringkasan) {
                try {
                    $this->buat($pegawai);
                    $ringkasan['dibuat']++;
                } catch (ValidationException $exception) {
                    $ringkasan['dilewati']++;
                    $pesan = collect($exception->errors())->flatten()->first();
                    $ringkasan['catatan'][] = $pegawai->nama_lengkap.': '.$pesan;
                }
            });

        return $ringkasan;
    }

    public function resetKataSandi(Pegawai $pegawai): Pengguna
    {
        $akun = $this->akunYangDapatDikelola($pegawai);
        $akun->forceFill([
            'kata_sandi' => Hash::make(config('nusa.kata_sandi_default_pegawai')),
        ])->save();

        return $akun;
    }

    public function ubahStatus(Pegawai $pegawai, bool $aktif): Pengguna
    {
        $akun = $this->akunYangDapatDikelola($pegawai);
        $akun->update(['aktif' => $aktif]);

        return $akun;
    }

    public function ubahPeran(Pegawai $pegawai, array $peranIds): Pengguna
    {
        $akun = $this->akunYangDapatDikelola($pegawai);
        $ids = collect($peranIds)->map(fn ($id) => (int) $id)->filter();
        $peranPegawai = Peran::where('kode', 'pegawai')->where('aktif', true)->first();

        if ($peranPegawai) {
            $ids->push((int) $peranPegawai->id);
        }

        $akun->daftarPeran()->sync($ids->unique()->values()->all());
        $akun->load('daftarPeran');

        return $akun;
    }

    private function akunYangDapatDikelola(Pegawai $pegawai): Pengguna
    {
        $pegawai->loadMissing('pengguna');
        if (! $pegawai->pengguna) {
            throw ValidationException::withMessages([
                'akun' => 'Pegawai ini belum memiliki akun.',
            ]);
        }

        abort_if($pegawai->pengguna->akun_sistem, 403, 'Akun sistem tidak dapat diubah.');

        return $pegawai->pengguna;
    }

    private function siapkanPegawai(
        Pegawai $pegawai,
        bool $sertakanStatusSandi = false,
    ): array {
        $akun = $pegawai->pengguna;

        return [
            'pegawai' => [
                'id' => (int) $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nip' => $pegawai->nip,
                'jabatan_utama' => $pegawai->jabatan_utama,
                'foto_url' => $pegawai->foto ? asset('storage/'.$pegawai->foto) : null,
                'aktif' => (bool) $pegawai->aktif,
            ],
            'status_akun' => match (true) {
                $akun !== null => $akun->aktif ? 'aktif' : 'nonaktif',
                filled($pegawai->nip) => 'belum',
                default => 'tanpa_nip',
            },
            'akun' => $akun ? [
                'tersedia' => true,
                'id' => (int) $akun->id,
                'username' => $akun->username,
                'aktif' => (bool) $akun->aktif,
                'akun_sistem' => (bool) $akun->akun_sistem,
                'wajib_ganti_kata_sandi' => $sertakanStatusSandi
                    && $akun->harusMenggantiKataSandi(),
                'terakhir_login_pada' => $akun->terakhir_login_pada?->toISOString(),
                'peran' => $akun->daftarPeran
                    ->sortBy('nama')
                    ->map(fn (Peran $peran) => $this->siapkanPeran($peran))
                    ->values(),
            ] : [
                'tersedia' => false,
                'id' => null,
                'username' => null,
                'aktif' => false,
                'akun_sistem' => false,
                'wajib_ganti_kata_sandi' => false,
                'terakhir_login_pada' => null,
                'peran' => [],
            ],
        ];
    }

    private function ringkasan(): array
    {
        return [
            'pegawai_aktif' => Pegawai::where('aktif', true)->count(),
            'punya_nip' => Pegawai::whereNotNull('nip')->where('nip', '<>', '')->count(),
            'akun_pegawai' => Pengguna::whereNotNull('pegawai_id')->count(),
            'belum_akun' => Pegawai::where('aktif', true)
                ->whereNotNull('nip')
                ->where('nip', '<>', '')
                ->whereDoesntHave('pengguna')
                ->count(),
        ];
    }

    private function pilihanPeran(): array
    {
        return Peran::query()
            ->where('aktif', true)
            ->orderByDesc('sistem')
            ->orderBy('nama')
            ->get()
            ->map(fn (Peran $peran) => $this->siapkanPeran($peran))
            ->values()
            ->all();
    }

    private function siapkanPeran(Peran $peran): array
    {
        return [
            'id' => (int) $peran->id,
            'kode' => $peran->kode,
            'nama' => $peran->nama,
            'deskripsi' => $peran->deskripsi,
            'sistem' => (bool) $peran->sistem,
        ];
    }

    private function usernameDariNip(?string $nip): ?string
    {
        $username = preg_replace('/\s+/', '', trim((string) $nip));

        return $username === '' ? null : $username;
    }

    private function pasangPeranPegawai(Pengguna $pengguna): void
    {
        $peranPegawai = Peran::where('kode', 'pegawai')->where('aktif', true)->first();

        if ($peranPegawai) {
            $pengguna->daftarPeran()->syncWithoutDetaching([$peranPegawai->id]);
        }
    }
}
