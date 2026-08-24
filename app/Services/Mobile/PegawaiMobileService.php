<?php

namespace App\Services\Mobile;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Services\SinkronisasiUsernameAkunService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PegawaiMobileService
{
    public function __construct(
        private readonly SinkronisasiUsernameAkunService $sinkronisasiUsername,
    ) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $jenisPegawai = trim((string) ($filter['jenis_pegawai'] ?? 'semua'));
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);

        $query = Pegawai::query()
            ->select([
                'id',
                'nama_lengkap',
                'nip',
                'nuptk',
                'foto',
                'jenis_kelamin',
                'status_kepegawaian',
                'jenis_pegawai',
                'jabatan_utama',
                'aktif',
            ])
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($jenisPegawai !== 'semua', fn (Builder $query) => $query->where('jenis_pegawai', $jenisPegawai))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $polaNama = '%'.mb_strtolower($kataKunci).'%';
                $polaIdentitas = '%'.$kataKunci.'%';

                $query->where(function (Builder $query) use ($polaNama, $polaIdentitas) {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$polaNama])
                        ->orWhere('nip', 'like', $polaIdentitas)
                        ->orWhere('nuptk', 'like', $polaIdentitas)
                        ->orWhere('nik', 'like', $polaIdentitas)
                        ->orWhereRaw('LOWER(COALESCE(jabatan_utama, \'\')) LIKE ?', [$polaNama]);
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('nama_lengkap')
            ->orderBy('id');

        $paginator = $query->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())
                ->map(fn (Pegawai $pegawai) => $this->ringkas($pegawai))
                ->values(),
            'ringkasan' => [
                'total' => Pegawai::count(),
                'aktif' => Pegawai::where('aktif', true)->count(),
                'nonaktif' => Pegawai::where('aktif', false)->count(),
            ],
            'pilihan_jenis_pegawai' => Pegawai::query()
                ->whereNotNull('jenis_pegawai')
                ->where('jenis_pegawai', '<>', '')
                ->distinct()
                ->orderBy('jenis_pegawai')
                ->pluck('jenis_pegawai')
                ->values(),
            'filter' => [
                'cari' => $kataKunci,
                'status' => $status,
                'jenis_pegawai' => $jenisPegawai,
            ],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
            'hak_akses' => [
                'dapat_kelola' => $pengguna->memilikiIzin('pegawai.kelola'),
            ],
        ];
    }

    public function detail(Pengguna $pengguna, Pegawai $pegawai): array
    {
        $pegawai->loadMissing('pengguna');
        $pegawai->loadCount([
            'kelasSebagaiWali as jumlah_kelas_wali_aktif' => fn (Builder $query) => $query->where('aktif', true),
            'guruMataPelajaran as jumlah_penugasan_mapel_aktif' => fn (Builder $query) => $query->where('aktif', true),
        ]);

        return [
            ...$this->ringkas($pegawai),
            'nik' => $pegawai->nik,
            'tempat_lahir' => $pegawai->tempat_lahir,
            'tanggal_lahir' => $pegawai->tanggal_lahir?->toDateString(),
            'alamat' => $pegawai->alamat,
            'email' => $pegawai->email,
            'no_hp' => $pegawai->no_hp,
            'golongan' => $pegawai->golongan,
            'tanggal_mulai_kerja' => $pegawai->tanggal_mulai_kerja?->toDateString(),
            'tanggal_mulai_bertugas' => $pegawai->tanggal_mulai_bertugas?->toDateString(),
            'sumber_gaji' => $pegawai->sumber_gaji,
            'pendidikan_terakhir' => $pegawai->pendidikan_terakhir,
            'jurusan_pendidikan' => $pegawai->jurusan_pendidikan,
            'tahun_lulus' => $pegawai->tahun_lulus,
            'keterangan' => $pegawai->keterangan,
            'akun' => $pegawai->pengguna ? [
                'tersedia' => true,
                'username' => $pegawai->pengguna->username,
                'aktif' => (bool) $pegawai->pengguna->aktif,
            ] : [
                'tersedia' => false,
                'username' => null,
                'aktif' => false,
            ],
            'ringkasan_penugasan' => [
                'kelas_wali_aktif' => (int) $pegawai->jumlah_kelas_wali_aktif,
                'penugasan_mapel_aktif' => (int) $pegawai->jumlah_penugasan_mapel_aktif,
            ],
            'hak_akses' => [
                'dapat_kelola' => $pengguna->memilikiIzin('pegawai.kelola'),
            ],
        ];
    }

    public function tambah(array $data): Pegawai
    {
        return Pegawai::create($this->dataSimpan($data));
    }

    public function ubah(Pegawai $pegawai, array $data): bool
    {
        $pegawai->loadMissing('pengguna');
        $usernameBaru = $this->sinkronisasiUsername->siapkanUsername(
            $pegawai->pengguna,
            $data['nip'] ?? null,
            'nip',
            'NIP',
        );
        $usernameBerubah = $pegawai->pengguna && $usernameBaru !== $pegawai->pengguna->username;

        DB::transaction(function () use ($pegawai, $data, $usernameBaru) {
            $pegawai->update($this->dataSimpan($data));
            $this->sinkronisasiUsername->sinkronkan($pegawai->pengguna, $usernameBaru);
        });

        return (bool) $usernameBerubah;
    }

    private function ringkas(Pegawai $pegawai): array
    {
        return [
            'id' => (int) $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'nip' => $pegawai->nip,
            'nuptk' => $pegawai->nuptk,
            'foto_url' => $pegawai->foto ? asset('storage/'.$pegawai->foto) : null,
            'jenis_kelamin' => $pegawai->jenis_kelamin,
            'status_kepegawaian' => $pegawai->status_kepegawaian,
            'jenis_pegawai' => $pegawai->jenis_pegawai,
            'jabatan_utama' => $pegawai->jabatan_utama,
            'aktif' => (bool) $pegawai->aktif,
        ];
    }

    private function dataSimpan(array $data): array
    {
        $hasil = [];
        foreach ([
            'nama_lengkap',
            'nip',
            'nuptk',
            'nik',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'alamat',
            'email',
            'no_hp',
            'status_kepegawaian',
            'golongan',
            'tanggal_mulai_kerja',
            'tanggal_mulai_bertugas',
            'jenis_pegawai',
            'jabatan_utama',
            'sumber_gaji',
            'pendidikan_terakhir',
            'jurusan_pendidikan',
            'keterangan',
        ] as $field) {
            $hasil[$field] = $this->teks($data[$field] ?? null);
        }

        $hasil['tahun_lulus'] = $data['tahun_lulus'] ?? null;
        $hasil['aktif'] = (bool) $data['aktif'];

        return $hasil;
    }

    private function teks(mixed $nilai): ?string
    {
        return filled($nilai) ? trim((string) $nilai) : null;
    }
}
