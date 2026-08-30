<?php

namespace App\Services\Mobile;

use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PendampinganSiswa;
use App\Models\Pengguna;
use App\Models\PeringatanDiniSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\AksesRekapPoinSiswaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PendampinganSiswaMobileService
{
    public function __construct(private AksesRekapPoinSiswaService $akses) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $tahunId = isset($filter['tahun_pelajaran_id'])
            ? (int) $filter['tahun_pelajaran_id']
            : TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        $kelasId = isset($filter['kelas_id']) ? (int) $filter['kelas_id'] : null;
        $status = (string) ($filter['status'] ?? 'dalam_proses');
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        $perHalaman = min(30, max(5, (int) ($filter['per_halaman'] ?? 15)));
        $siswaIds = $this->querySiswaCakupan($pengguna, $tahunId, $kelasId, $kataKunci)->pluck('siswa.id');
        $cakupan = PendampinganSiswa::query()
            ->whereIn('siswa_id', $siswaIds)
            ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId));

        $ringkasan = [
            'total' => (clone $cakupan)->count(),
            'dalam_proses' => (clone $cakupan)->where('status', 'dalam_proses')->count(),
            'selesai' => (clone $cakupan)->where('status', 'selesai')->count(),
        ];

        $paginasi = (clone $cakupan)
            ->with($this->relasi($tahunId))
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'dalam_proses' THEN 0 ELSE 1 END")
            ->latest('tanggal_tindak_lanjut')
            ->latest('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginasi->items())->map(fn (PendampinganSiswa $item) => $this->ringkas($item))->values(),
            'ringkasan' => $ringkasan,
            'pilihan' => [
                'status' => $this->pilihan(PendampinganSiswa::DAFTAR_STATUS),
                'jenis_tindakan' => $this->pilihan(PendampinganSiswa::DAFTAR_JENIS),
                'tahun_pelajaran' => TahunPelajaran::query()
                    ->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get()
                    ->map(fn (TahunPelajaran $tahun) => [
                        'id' => (int) $tahun->id,
                        'nama' => $tahun->nama,
                        'aktif' => (bool) $tahun->aktif,
                    ])->values(),
                'kelas' => $this->daftarKelas($pengguna, $tahunId),
            ],
            'filter' => [
                'kata_kunci' => $kataKunci,
                'status' => $status,
                'tahun_pelajaran_id' => $tahunId,
                'kelas_id' => $kelasId,
            ],
            'paginasi' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total' => $paginasi->total(),
                'ada_halaman_berikutnya' => $paginasi->hasMorePages(),
            ],
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function referensi(Pengguna $pengguna, array $filter): array
    {
        $tahunId = isset($filter['tahun_pelajaran_id'])
            ? (int) $filter['tahun_pelajaran_id']
            : TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        $kelasId = isset($filter['kelas_id']) ? (int) $filter['kelas_id'] : null;
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $siswa = $this->querySiswaCakupan($pengguna, $tahunId, $kelasId, $kataKunci)
            ->with(['anggotaKelas' => fn ($query) => $query
                ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
                ->where('status_keanggotaan', 'aktif')
                ->with('kelas:id,nama')])
            ->orderBy('nama_lengkap')
            ->limit(30)
            ->get(['id', 'nama_lengkap', 'nis', 'nisn']);

        return [
            'siswa' => $siswa->map(fn (Siswa $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama_lengkap,
                'nis' => $item->nis,
                'nisn' => $item->nisn,
                'kelas' => $item->anggotaKelas->first()?->kelas ? [
                    'id' => (int) $item->anggotaKelas->first()->kelas->id,
                    'nama' => $item->anggotaKelas->first()->kelas->nama,
                ] : null,
                'memiliki_pendampingan_aktif' => PendampinganSiswa::query()
                    ->where('siswa_id', $item->id)
                    ->where('tahun_pelajaran_id', $tahunId)
                    ->where('status', 'dalam_proses')
                    ->exists(),
            ])->values(),
            'pegawai' => $this->daftarPegawai(),
            'jenis_tindakan' => $this->pilihan(PendampinganSiswa::DAFTAR_JENIS),
            'tahun_pelajaran_id' => $tahunId,
            'kelas_id' => $kelasId,
            'kata_kunci' => $kataKunci,
        ];
    }

    public function rincian(Pengguna $pengguna, PendampinganSiswa $pendampingan): array
    {
        $pendampingan->load($this->relasi($pendampingan->tahun_pelajaran_id));
        abort_unless($pendampingan->siswa && $this->akses->bolehLihat(
            $pengguna,
            $pendampingan->siswa,
            $pendampingan->tahun_pelajaran_id,
        ), 403);

        return [
            'pendampingan' => $this->ringkas($pendampingan),
            'pilihan' => [
                'jenis_tindakan' => $this->pilihan(PendampinganSiswa::DAFTAR_JENIS),
                'status' => $this->pilihan(PendampinganSiswa::DAFTAR_STATUS),
                'pegawai' => $this->daftarPegawai(),
            ],
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function buat(Pengguna $pengguna, array $data): PendampinganSiswa
    {
        $siswa = Siswa::findOrFail((int) $data['siswa_id']);
        abort_unless($this->akses->bolehLihat($pengguna, $siswa, (int) $data['tahun_pelajaran_id']), 403);
        $this->pastikanPeringatanSesuai($data);

        return DB::transaction(function () use ($pengguna, $data): PendampinganSiswa {
            $aktif = PendampinganSiswa::query()
                ->where('siswa_id', $data['siswa_id'])
                ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
                ->where('status', 'dalam_proses')
                ->lockForUpdate()
                ->first();
            if ($aktif) {
                throw ValidationException::withMessages([
                    'siswa_id' => 'Siswa ini sudah memiliki pendampingan yang masih dalam proses.',
                ]);
            }

            return PendampinganSiswa::create($this->rapikan($data) + [
                'status' => 'dalam_proses',
                'kunci_aktif' => PendampinganSiswa::kunciAktif((int) $data['siswa_id'], (int) $data['tahun_pelajaran_id']),
                'dibuat_oleh_pengguna_id' => $pengguna->id,
                'diperbarui_oleh_pengguna_id' => $pengguna->id,
            ]);
        });
    }

    public function perbarui(Pengguna $pengguna, PendampinganSiswa $pendampingan, array $data): PendampinganSiswa
    {
        $pendampingan->loadMissing('siswa');
        abort_unless($pendampingan->siswa && $this->akses->bolehLihat(
            $pengguna,
            $pendampingan->siswa,
            $pendampingan->tahun_pelajaran_id,
        ), 403);

        return DB::transaction(function () use ($pengguna, $pendampingan, $data): PendampinganSiswa {
            $menjadiAktif = $data['status'] === 'dalam_proses';
            if ($menjadiAktif && PendampinganSiswa::query()
                ->where('siswa_id', $pendampingan->siswa_id)
                ->where('tahun_pelajaran_id', $pendampingan->tahun_pelajaran_id)
                ->where('status', 'dalam_proses')
                ->whereKeyNot($pendampingan->id)
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Masih ada pendampingan lain yang sedang diproses untuk siswa ini.',
                ]);
            }

            $pendampingan->update($this->rapikan($data) + [
                'kunci_aktif' => $menjadiAktif
                    ? PendampinganSiswa::kunciAktif($pendampingan->siswa_id, $pendampingan->tahun_pelajaran_id)
                    : null,
                'selesai_pada' => $menjadiAktif ? null : ($pendampingan->selesai_pada ?? now()),
                'diperbarui_oleh_pengguna_id' => $pengguna->id,
            ]);

            return $pendampingan->refresh();
        });
    }

    private function querySiswaCakupan(
        Pengguna $pengguna,
        ?int $tahunId,
        ?int $kelasId = null,
        string $kataKunci = '',
    ): Builder {
        $query = Siswa::query()
            ->where('aktif', true)
            ->when($kelasId, fn (Builder $query) => $query->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->where('kelas_id', $kelasId)
                ->where('status_keanggotaan', 'aktif')
                ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola): void {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]);
                });
            });
        $this->akses->terapkanCakupan($query, $pengguna, $tahunId);

        return $query;
    }

    private function daftarKelas(Pengguna $pengguna, ?int $tahunId): array
    {
        $siswaIds = $this->querySiswaCakupan($pengguna, $tahunId)->pluck('siswa.id');

        return Kelas::query()
            ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->whereIn('siswa_id', $siswaIds)
                ->where('status_keanggotaan', 'aktif'))
            ->orderBy('tingkat')->orderBy('nama')->get()
            ->map(fn (Kelas $kelas) => [
                'id' => (int) $kelas->id,
                'tahun_pelajaran_id' => (int) $kelas->tahun_pelajaran_id,
                'nama' => $kelas->nama,
                'tingkat' => (int) $kelas->tingkat,
            ])->values()->all();
    }

    private function daftarPegawai(): array
    {
        return Pegawai::query()->where('aktif', true)->orderBy('nama_lengkap')->get()
            ->map(fn (Pegawai $pegawai) => [
                'id' => (int) $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nip' => $pegawai->nip,
                'jabatan' => $pegawai->jabatan_utama,
            ])->values()->all();
    }

    private function relasi(?int $tahunId): array
    {
        return [
            'siswa' => fn ($query) => $query->with(['anggotaKelas' => fn ($query) => $query
                ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
                ->where('status_keanggotaan', 'aktif')
                ->with('kelas:id,nama')]),
            'tahunPelajaran:id,nama,aktif',
            'petugasPegawai:id,nama_lengkap,nip,jabatan_utama',
            'peringatanDiniSiswa:id,jenis,judul',
        ];
    }

    private function ringkas(PendampinganSiswa $item): array
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
            'kelas' => $kelas ? ['id' => (int) $kelas->id, 'nama' => $kelas->nama] : null,
            'tahun_pelajaran' => $item->tahunPelajaran ? [
                'id' => (int) $item->tahunPelajaran->id,
                'nama' => $item->tahunPelajaran->nama,
            ] : null,
            'peringatan' => $item->peringatanDiniSiswa ? [
                'id' => (int) $item->peringatanDiniSiswa->id,
                'jenis' => $item->peringatanDiniSiswa->jenis,
                'label_jenis' => $item->peringatanDiniSiswa->labelJenis(),
                'judul' => $item->peringatanDiniSiswa->judul,
            ] : null,
            'petugas' => $item->petugasPegawai ? [
                'id' => (int) $item->petugasPegawai->id,
                'nama' => $item->petugasPegawai->nama_lengkap,
                'nip' => $item->petugasPegawai->nip,
                'jabatan' => $item->petugasPegawai->jabatan_utama,
            ] : null,
            'jenis_tindakan' => $item->jenis_tindakan,
            'label_jenis_tindakan' => $item->labelJenis(),
            'tanggal_tindak_lanjut' => $item->tanggal_tindak_lanjut?->toDateString(),
            'catatan' => $item->catatan,
            'status' => $item->status,
            'label_status' => $item->labelStatus(),
            'hasil' => $item->hasil,
            'selesai_pada' => $item->selesai_pada?->toISOString(),
            'diperbarui_pada' => $item->updated_at?->toISOString(),
        ];
    }

    private function rapikan(array $data): array
    {
        foreach (['catatan', 'hasil'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? trim((string) $data[$field]) : null;
            }
        }

        return $data;
    }

    private function pastikanPeringatanSesuai(array $data): void
    {
        if (empty($data['peringatan_dini_siswa_id'])) {
            return;
        }

        if (! PeringatanDiniSiswa::query()
            ->whereKey($data['peringatan_dini_siswa_id'])
            ->where('siswa_id', $data['siswa_id'])
            ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
            ->exists()) {
            throw ValidationException::withMessages([
                'peringatan_dini_siswa_id' => 'Peringatan tidak sesuai dengan siswa dan tahun pelajaran.',
            ]);
        }
    }

    private function pilihan(array $items): array
    {
        return collect($items)->map(fn (string $label, string $kode) => [
            'kode' => $kode,
            'label' => $label,
        ])->values()->all();
    }

    private function hakAkses(Pengguna $pengguna): array
    {
        return [
            'dapat_kelola' => $pengguna->administrator() || $pengguna->memilikiIzin('poin_siswa.pendampingan_kelola'),
            'cakupan_luas' => $this->akses->aksesLuas($pengguna),
        ];
    }
}
