<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\FotoProfilService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Throwable;

class FotoIdentitasMobileService
{
    public function __construct(private readonly FotoProfilService $fotoProfilService) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $bolehSiswa = $pengguna->memilikiIzin('siswa.kelola');
        $bolehPegawai = $pengguna->memilikiIzin('pegawai.kelola');
        $tab = $filter['tab'] ?? null;

        if (! in_array($tab, ['siswa', 'pegawai'], true)
            || ($tab === 'siswa' && ! $bolehSiswa)
            || ($tab === 'pegawai' && ! $bolehPegawai)) {
            $tab = $bolehSiswa ? 'siswa' : 'pegawai';
        }

        $data = $tab === 'siswa'
            ? $this->daftarSiswa($pengguna, $filter)
            : $this->daftarPegawai($filter);

        return $data + [
            'tab' => $tab,
            'hak_akses' => [
                'dapat_kelola_siswa' => $bolehSiswa,
                'dapat_kelola_pegawai' => $bolehPegawai,
            ],
        ];
    }

    public function simpanFotoSiswa(Pengguna $pengguna, Siswa $siswa, UploadedFile $foto): string
    {
        abort_unless($pengguna->memilikiIzin('siswa.kelola'), 403);
        if ($pengguna->membatasiCakupanWaliKelas()) {
            abort_unless(
                $siswa->anggotaKelas()->whereIn('kelas_id', $pengguna->kelasWaliIds())->exists(),
                403,
            );
        }

        return $this->gantiFoto($siswa, $foto, 'siswa/foto');
    }

    public function simpanFotoPegawai(Pengguna $pengguna, Pegawai $pegawai, UploadedFile $foto): string
    {
        abort_unless($pengguna->memilikiIzin('pegawai.kelola'), 403);

        return $this->gantiFoto($pegawai, $foto, 'pegawai/foto');
    }

    private function daftarSiswa(Pengguna $pengguna, array $filter): array
    {
        $kelasWaliIds = $pengguna->membatasiCakupanWaliKelas()
            ? $pengguna->kelasWaliIds()
            : null;
        $tahunPelajaran = TahunPelajaran::query()
            ->when($kelasWaliIds !== null, fn (Builder $query) => $query->whereHas(
                'kelas',
                fn (Builder $query) => $query->whereIn('id', $kelasWaliIds),
            ))
            ->whereHas('kelas')
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->get(['id', 'nama', 'aktif']);
        $tahunPelajaranId = (int) ($filter['tahun_pelajaran_id'] ?? 0);
        if (! $tahunPelajaran->contains('id', $tahunPelajaranId)) {
            $tahunPelajaranId = (int) ($tahunPelajaran->firstWhere('aktif', true)?->id
                ?? $tahunPelajaran->first()?->id);
        }
        $kelas = Kelas::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('aktif', true)
            ->when($kelasWaliIds !== null, fn (Builder $query) => $query->whereIn('id', $kelasWaliIds))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get(['id', 'tahun_pelajaran_id', 'nama', 'tingkat']);
        $kelasId = (int) ($filter['kelas_id'] ?? 0);
        if (! $kelas->contains('id', $kelasId)) {
            $kelasId = (int) $kelas->first()?->id;
        }

        $dasar = AnggotaKelas::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('kelas_id', $kelasId)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn (Builder $query) => $query->where('aktif', true));
        $ringkasan = $this->ringkasanAnggota($dasar);
        $paginator = (clone $dasar)
            ->with('siswa:id,nama_lengkap,nis,nisn,foto,jenis_kelamin,aktif')
            ->when(($filter['status_foto'] ?? 'semua') === 'sudah', fn (Builder $query) => $query
                ->whereHas('siswa', fn (Builder $query) => $query->whereNotNull('foto')->where('foto', '<>', '')))
            ->when(($filter['status_foto'] ?? 'semua') === 'belum', fn (Builder $query) => $query
                ->whereHas('siswa', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->whereNull('foto')->orWhere('foto', ''))))
            ->when(trim((string) ($filter['cari'] ?? '')) !== '', function (Builder $query) use ($filter) {
                $pola = '%'.mb_strtolower(trim((string) $filter['cari'])).'%';
                $query->whereHas('siswa', fn (Builder $query) => $query
                    ->where(function (Builder $query) use ($pola) {
                        $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                            ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                            ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]);
                    }));
            })
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->paginate(
                (int) ($filter['per_halaman'] ?? 20),
                ['*'],
                'halaman',
                (int) ($filter['halaman'] ?? 1),
            );

        return [
            'items' => collect($paginator->items())
                ->filter(fn (AnggotaKelas $item) => $item->siswa !== null)
                ->map(fn (AnggotaKelas $item) => $this->orang(
                    id: $item->siswa->id,
                    nama: $item->siswa->nama_lengkap,
                    identitas: 'NISN '.($item->siswa->nisn ?: '-'),
                    detail: collect([
                        $item->nomor_absen ? 'Absen '.$item->nomor_absen : null,
                        $item->siswa->nis ? 'NIS '.$item->siswa->nis : null,
                    ])->filter()->join(' · '),
                    foto: $item->siswa->foto,
                    jenisKelamin: $item->siswa->jenis_kelamin,
                    aktif: $item->siswa->aktif,
                ))
                ->values(),
            'ringkasan' => $ringkasan,
            'tahun_pelajaran' => $tahunPelajaran->map(fn (TahunPelajaran $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama,
                'aktif' => (bool) $item->aktif,
            ])->values(),
            'kelas' => $kelas->map(fn (Kelas $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama,
                'tingkat' => $item->tingkat === null ? null : (int) $item->tingkat,
            ])->values(),
            'jenis_pegawai' => [],
            'filter' => [
                'tahun_pelajaran_id' => $tahunPelajaranId ?: null,
                'kelas_id' => $kelasId ?: null,
                'status_foto' => $filter['status_foto'] ?? 'semua',
                'status_pegawai' => 'aktif',
                'jenis_pegawai' => '',
                'cari' => trim((string) ($filter['cari'] ?? '')),
            ],
            'paginasi' => $this->paginasi($paginator),
        ];
    }

    private function daftarPegawai(array $filter): array
    {
        $statusPegawai = $filter['status_pegawai'] ?? 'aktif';
        $jenisPegawai = trim((string) ($filter['jenis_pegawai'] ?? ''));
        $daftarJenisPegawai = Pegawai::query()
            ->whereNotNull('jenis_pegawai')
            ->where('jenis_pegawai', '<>', '')
            ->distinct()
            ->orderBy('jenis_pegawai')
            ->pluck('jenis_pegawai');
        if ($jenisPegawai !== '' && ! $daftarJenisPegawai->contains($jenisPegawai)) {
            $jenisPegawai = '';
        }
        $dasar = Pegawai::query()
            ->when($statusPegawai === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($statusPegawai === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($jenisPegawai !== '', fn (Builder $query) => $query->where('jenis_pegawai', $jenisPegawai));
        $ringkasan = $this->ringkasanOrang($dasar);
        $paginator = (clone $dasar)
            ->when(($filter['status_foto'] ?? 'semua') === 'sudah', fn (Builder $query) => $query
                ->whereNotNull('foto')->where('foto', '<>', ''))
            ->when(($filter['status_foto'] ?? 'semua') === 'belum', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereNull('foto')->orWhere('foto', '')))
            ->when(trim((string) ($filter['cari'] ?? '')) !== '', function (Builder $query) use ($filter) {
                $pola = '%'.mb_strtolower(trim((string) $filter['cari'])).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nip, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nuptk, '')) LIKE ?", [$pola]);
                });
            })
            ->orderBy('nama_lengkap')
            ->paginate(
                (int) ($filter['per_halaman'] ?? 20),
                ['*'],
                'halaman',
                (int) ($filter['halaman'] ?? 1),
            );

        return [
            'items' => collect($paginator->items())->map(fn (Pegawai $item) => $this->orang(
                id: $item->id,
                nama: $item->nama_lengkap,
                identitas: 'NIP '.($item->nip ?: '-'),
                detail: collect([$item->jenis_pegawai, $item->jabatan_utama])->filter()->unique()->join(' · '),
                foto: $item->foto,
                jenisKelamin: $item->jenis_kelamin,
                aktif: $item->aktif,
            ))->values(),
            'ringkasan' => $ringkasan,
            'tahun_pelajaran' => [],
            'kelas' => [],
            'jenis_pegawai' => $daftarJenisPegawai->values(),
            'filter' => [
                'tahun_pelajaran_id' => null,
                'kelas_id' => null,
                'status_foto' => $filter['status_foto'] ?? 'semua',
                'status_pegawai' => $statusPegawai,
                'jenis_pegawai' => $jenisPegawai,
                'cari' => trim((string) ($filter['cari'] ?? '')),
            ],
            'paginasi' => $this->paginasi($paginator),
        ];
    }

    private function ringkasanAnggota(Builder $query): array
    {
        $total = (clone $query)->count();
        $sudah = (clone $query)->whereHas(
            'siswa',
            fn (Builder $query) => $query->whereNotNull('foto')->where('foto', '<>', ''),
        )->count();

        return ['total' => $total, 'sudah' => $sudah, 'belum' => $total - $sudah];
    }

    private function ringkasanOrang(Builder $query): array
    {
        $total = (clone $query)->count();
        $sudah = (clone $query)->whereNotNull('foto')->where('foto', '<>', '')->count();

        return ['total' => $total, 'sudah' => $sudah, 'belum' => $total - $sudah];
    }

    private function orang(
        int $id,
        string $nama,
        string $identitas,
        string $detail,
        ?string $foto,
        ?string $jenisKelamin,
        bool $aktif,
    ): array {
        return [
            'id' => $id,
            'nama' => $nama,
            'identitas' => $identitas,
            'detail' => $detail,
            'foto_url' => $foto ? asset('storage/'.$foto) : null,
            'punya_foto' => filled($foto),
            'jenis_kelamin' => $jenisKelamin,
            'aktif' => $aktif,
        ];
    }

    private function paginasi($paginator): array
    {
        return [
            'halaman' => $paginator->currentPage(),
            'halaman_terakhir' => $paginator->lastPage(),
            'per_halaman' => $paginator->perPage(),
            'total' => $paginator->total(),
            'ada_halaman_berikutnya' => $paginator->hasMorePages(),
        ];
    }

    private function gantiFoto(Siswa|Pegawai $orang, UploadedFile $foto, string $direktori): string
    {
        $fotoLama = $orang->foto;
        $fotoBaru = $this->fotoProfilService->simpan($foto, $direktori);

        try {
            $orang->update(['foto' => $fotoBaru]);
        } catch (Throwable $exception) {
            $this->fotoProfilService->hapus($fotoBaru);
            throw $exception;
        }

        $this->fotoProfilService->hapus($fotoLama);

        return asset('storage/'.$fotoBaru);
    }
}
