<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class KartuPelajarMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $tahunPelajaran = TahunPelajaran::query()
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
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get(['id', 'tahun_pelajaran_id', 'nama', 'tingkat', 'aktif']);
        $kelasId = (int) ($filter['kelas_id'] ?? 0);
        if (! $kelas->contains('id', $kelasId)) {
            $kelasId = (int) $kelas->first()?->id;
        }

        $siswaId = (int) ($filter['siswa_id'] ?? 0);
        $cari = trim((string) ($filter['cari'] ?? ''));
        $dasar = AnggotaKelas::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('kelas_id', $kelasId)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn (Builder $query) => $query->where('aktif', true))
            ->when($siswaId > 0, fn (Builder $query) => $query->where('siswa_id', $siswaId));
        $ringkasan = $this->ringkasan($dasar);
        $paginator = (clone $dasar)
            ->with([
                'siswa:id,nama_lengkap,nis,nisn,foto,tempat_lahir,tanggal_lahir,jenis_kelamin,aktif',
                'kelas:id,nama',
                'tahunPelajaran:id,nama',
            ])
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->whereHas('siswa', function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]);
                });
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
                ->filter(fn (AnggotaKelas $anggota) => $anggota->siswa !== null)
                ->map(fn (AnggotaKelas $anggota) => $this->kartu($anggota))
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
                'aktif' => (bool) $item->aktif,
            ])->values(),
            'filter' => [
                'tahun_pelajaran_id' => $tahunPelajaranId ?: null,
                'kelas_id' => $kelasId ?: null,
                'siswa_id' => $siswaId ?: null,
                'cari' => $cari,
            ],
            'paginasi' => $this->paginasi($paginator),
            'ukuran_kartu' => [
                'lebar_mm' => 53.98,
                'tinggi_mm' => 85.60,
                'orientasi' => 'portrait',
            ],
            'hak_akses' => [
                'dapat_cetak' => $pengguna->memilikiIzin('kartu_pelajar.cetak'),
                'dapat_kelola_foto' => $pengguna->memilikiIzin('siswa.kelola'),
            ],
        ];
    }

    private function kartu(AnggotaKelas $anggota): array
    {
        /** @var Siswa $siswa */
        $siswa = $anggota->siswa;
        $nisn = trim((string) ($siswa->nisn ?? ''));
        $fotoTersedia = filled($siswa->foto)
            && Storage::disk('public')->exists($siswa->foto);
        $tanggalLahir = $siswa->tanggal_lahir
            ? $siswa->tanggal_lahir->locale('id')->translatedFormat('d F Y')
            : null;

        return [
            'anggota_kelas_id' => (int) $anggota->id,
            'siswa_id' => (int) $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'nis' => $siswa->nis,
            'nisn' => $nisn !== '' ? $nisn : null,
            'jenis_kelamin' => $siswa->jenis_kelamin,
            'tempat_tanggal_lahir' => collect([$siswa->tempat_lahir, $tanggalLahir])
                ->filter()
                ->join(', ') ?: '-',
            'kelas' => $anggota->kelas?->nama ?? '-',
            'tahun_pelajaran' => $anggota->tahunPelajaran?->nama ?? '-',
            'nomor_absen' => $anggota->nomor_absen === null ? null : (int) $anggota->nomor_absen,
            'foto_url' => $fotoTersedia ? asset('storage/'.$siswa->foto) : null,
            'punya_foto' => $fotoTersedia,
            'qr_data' => preg_match('/^[0-9]{1,41}$/', $nisn) === 1 ? $nisn : null,
            'qr_bisa_dibuat' => preg_match('/^[0-9]{1,41}$/', $nisn) === 1,
        ];
    }

    private function ringkasan(Builder $query): array
    {
        $anggota = (clone $query)
            ->with('siswa:id,nisn,foto')
            ->get();
        $total = $anggota->count();
        $siapQr = $anggota->filter(fn (AnggotaKelas $item) => preg_match(
            '/^[0-9]{1,41}$/',
            trim((string) ($item->siswa?->nisn ?? '')),
        ) === 1)->count();
        $denganFoto = $anggota->filter(fn (AnggotaKelas $item) => filled($item->siswa?->foto)
            && Storage::disk('public')->exists($item->siswa->foto))->count();

        return [
            'total' => $total,
            'siap_qr' => $siapQr,
            'dengan_foto' => $denganFoto,
        ];
    }

    private function paginasi(LengthAwarePaginator $paginator): array
    {
        return [
            'halaman' => $paginator->currentPage(),
            'halaman_terakhir' => $paginator->lastPage(),
            'per_halaman' => $paginator->perPage(),
            'total' => $paginator->total(),
            'ada_halaman_berikutnya' => $paginator->hasMorePages(),
        ];
    }
}
