<?php

namespace App\Services\Mobile;

use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Database\Eloquent\Builder;

class SiswaWaliSayaMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $pegawaiId = $this->pegawaiId($pengguna);
        $tahun = TahunPelajaran::query()
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->first();
        $tahunId = $tahun?->id;
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $tingkat = isset($filter['tingkat']) ? (int) $filter['tingkat'] : null;
        $kelasId = isset($filter['kelas_id']) ? (int) $filter['kelas_id'] : null;
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        $perHalaman = min(30, max(5, (int) ($filter['per_halaman'] ?? 15)));
        $cakupan = $this->cakupan($pegawaiId);
        $daftarKelas = $this->daftarKelas($pegawaiId, $tahunId);

        if ($kelasId && ! collect($daftarKelas)->contains('id', $kelasId)) {
            $kelasId = null;
        }

        $saldoPoin = TransaksiPoinSiswa::query()
            ->whereIn('siswa_id', (clone $cakupan)->select('siswa.id'))
            ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->selectRaw('siswa_id, SUM(poin) AS total_poin')
            ->groupBy('siswa_id')
            ->pluck('total_poin', 'siswa_id');
        $query = (clone $cakupan)
            ->select([
                'id', 'nama_lengkap', 'nis', 'nisn', 'foto', 'jenis_kelamin',
                'tempat_lahir', 'tanggal_lahir', 'aktif',
            ])
            ->with([
                'anggotaKelas' => fn ($query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
                    ->with('kelas:id,nama,tingkat,tahun_pelajaran_id')
                    ->latest('tahun_pelajaran_id'),
                'penugasanGuruWaliSiswa' => fn ($query) => $query
                    ->where('guru_wali_pegawai_id', $pegawaiId)
                    ->where('aktif', true)
                    ->latest('tanggal_mulai'),
            ])
            ->withSum([
                'transaksiPoinSiswa as total_poin' => fn ($query) => $query
                    ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId)),
            ], 'poin')
            ->withCount([
                'laporanPembinaanSiswa as jumlah_laporan' => fn ($query) => $query
                    ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId)),
            ])
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola): void {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]);
                });
            })
            ->when($kelasId, fn (Builder $query) => $query->whereHas(
                'anggotaKelas',
                fn (Builder $query) => $query
                    ->where('kelas_id', $kelasId)
                    ->where('status_keanggotaan', 'aktif')
                    ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId)),
            ))
            ->when($tingkat, fn (Builder $query) => $query->whereHas(
                'anggotaKelas',
                fn (Builder $query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))
                    ->whereHas('kelas', fn (Builder $query) => $query->where('tingkat', $tingkat)),
            ));
        $paginasi = $query->orderBy('nama_lengkap')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginasi->items())
                ->map(fn (Siswa $siswa) => $this->ringkasSiswa($siswa))
                ->values(),
            'ringkasan' => [
                'jumlah_siswa' => (clone $cakupan)->count(),
                'jumlah_kelas' => count($daftarKelas),
                'laki_laki' => (clone $cakupan)->where('jenis_kelamin', 'L')->count(),
                'perempuan' => (clone $cakupan)->where('jenis_kelamin', 'P')->count(),
                'memiliki_poin' => $saldoPoin->filter(fn ($poin) => (int) $poin > 0)->count(),
            ],
            'tahun_pelajaran' => $tahun ? [
                'id' => (int) $tahun->id,
                'nama' => $tahun->nama,
                'aktif' => (bool) $tahun->aktif,
            ] : null,
            'pilihan' => [
                'tingkat' => [
                    ['nilai' => 7, 'label' => 'VII'],
                    ['nilai' => 8, 'label' => 'VIII'],
                    ['nilai' => 9, 'label' => 'IX'],
                ],
                'kelas' => $daftarKelas,
            ],
            'filter' => [
                'kata_kunci' => $kataKunci,
                'tingkat' => $tingkat,
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

    public function rincian(Pengguna $pengguna, Siswa $siswa): array
    {
        $pegawaiId = $this->pegawaiId($pengguna);
        $penugasan = PenugasanGuruWaliSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->where('guru_wali_pegawai_id', $pegawaiId)
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->first();
        abort_unless($penugasan, 403);

        $tahun = TahunPelajaran::query()
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->first();
        $tahunId = $tahun?->id;
        $anggotaKelas = $siswa->anggotaKelas()
            ->where('status_keanggotaan', 'aktif')
            ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->with('kelas:id,nama,tingkat,tahun_pelajaran_id')
            ->latest('tahun_pelajaran_id')
            ->first();
        $totalPoin = max(0, (int) $siswa->transaksiPoinSiswa()
            ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->sum('poin'));
        $queryLaporan = $siswa->laporanPembinaanSiswa()
            ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId));
        $jumlahLaporan = (clone $queryLaporan)->count();
        $laporanTerbaru = $queryLaporan
            ->with(['kategoriPembinaanSiswa:id,nama', 'kelas:id,nama'])
            ->latest('tanggal_kejadian')
            ->latest('id')
            ->limit(5)
            ->get();

        return [
            'siswa' => $this->identitasSiswa($siswa),
            'kelas' => $anggotaKelas?->kelas ? [
                'id' => (int) $anggotaKelas->kelas->id,
                'nama' => $anggotaKelas->kelas->nama,
                'tingkat' => (int) $anggotaKelas->kelas->tingkat,
                'nomor_absen' => $anggotaKelas->nomor_absen ? (int) $anggotaKelas->nomor_absen : null,
            ] : null,
            'penugasan' => [
                'id' => (int) $penugasan->id,
                'tanggal_mulai' => $penugasan->tanggal_mulai?->toDateString(),
                'nomor_sk' => $penugasan->nomor_sk,
                'catatan' => $penugasan->catatan,
            ],
            'tahun_pelajaran' => $tahun ? [
                'id' => (int) $tahun->id,
                'nama' => $tahun->nama,
                'aktif' => (bool) $tahun->aktif,
            ] : null,
            'ringkasan' => [
                'total_poin' => $totalPoin,
                'jumlah_laporan' => $jumlahLaporan,
            ],
            'laporan_terbaru' => $laporanTerbaru
                ->map(fn (LaporanPembinaanSiswa $laporan) => [
                    'id' => (int) $laporan->id,
                    'nomor' => $laporan->nomor_laporan,
                    'tanggal' => $laporan->tanggal_kejadian?->toDateString(),
                    'jenis' => $laporan->jenis_laporan,
                    'label_jenis' => $laporan->labelJenisLaporan(),
                    'kategori' => $laporan->kategoriPembinaanSiswa?->nama,
                    'kelas' => $laporan->kelas?->nama,
                    'status' => $laporan->status_verifikasi,
                    'label_status' => $laporan->labelStatusVerifikasi(),
                    'poin' => max(0, (int) $laporan->total_poin),
                ])->values(),
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    private function cakupan(int $pegawaiId): Builder
    {
        return Siswa::query()
            ->where('aktif', true)
            ->whereHas('penugasanGuruWaliSiswa', fn (Builder $query) => $query
                ->where('guru_wali_pegawai_id', $pegawaiId)
                ->where('aktif', true));
    }

    private function daftarKelas(int $pegawaiId, ?int $tahunId): array
    {
        return Kelas::query()
            ->select(['id', 'nama', 'tingkat', 'tahun_pelajaran_id'])
            ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->where('status_keanggotaan', 'aktif')
                ->whereHas('siswa.penugasanGuruWaliSiswa', fn (Builder $query) => $query
                    ->where('guru_wali_pegawai_id', $pegawaiId)
                    ->where('aktif', true)))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get()
            ->map(fn (Kelas $kelas) => [
                'id' => (int) $kelas->id,
                'nama' => $kelas->nama,
                'tingkat' => (int) $kelas->tingkat,
            ])->values()->all();
    }

    private function ringkasSiswa(Siswa $siswa): array
    {
        $anggota = $siswa->anggotaKelas->first();
        $penugasan = $siswa->penugasanGuruWaliSiswa->first();

        return [
            'id' => (int) $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'nis' => $siswa->nis,
            'nisn' => $siswa->nisn,
            'foto_url' => $siswa->foto ? asset('storage/'.$siswa->foto) : null,
            'jenis_kelamin' => $siswa->jenis_kelamin,
            'label_jenis_kelamin' => $this->labelJenisKelamin($siswa->jenis_kelamin),
            'kelas' => $anggota?->kelas ? [
                'id' => (int) $anggota->kelas->id,
                'nama' => $anggota->kelas->nama,
                'tingkat' => (int) $anggota->kelas->tingkat,
                'nomor_absen' => $anggota->nomor_absen ? (int) $anggota->nomor_absen : null,
            ] : null,
            'total_poin' => max(0, (int) $siswa->total_poin),
            'jumlah_laporan' => (int) $siswa->jumlah_laporan,
            'tanggal_mulai_didampingi' => $penugasan?->tanggal_mulai?->toDateString(),
        ];
    }

    private function identitasSiswa(Siswa $siswa): array
    {
        return [
            'id' => (int) $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'nis' => $siswa->nis,
            'nisn' => $siswa->nisn,
            'nik' => $siswa->nik,
            'foto_url' => $siswa->foto ? asset('storage/'.$siswa->foto) : null,
            'jenis_kelamin' => $siswa->jenis_kelamin,
            'label_jenis_kelamin' => $this->labelJenisKelamin($siswa->jenis_kelamin),
            'tempat_lahir' => $siswa->tempat_lahir,
            'tanggal_lahir' => $siswa->tanggal_lahir?->toDateString(),
            'agama' => $siswa->agama,
            'sekolah_asal' => $siswa->sekolah_asal,
            'status_dalam_keluarga' => $siswa->status_dalam_keluarga,
            'anak_ke' => $siswa->anak_ke,
            'aktif' => (bool) $siswa->aktif,
            'orang_tua_wali' => [
                'nama_ayah' => $siswa->nama_ayah,
                'nomor_wa_ayah' => $siswa->nomor_wa_ayah,
                'pekerjaan_ayah' => $siswa->pekerjaan_ayah,
                'nama_ibu' => $siswa->nama_ibu,
                'nomor_wa_ibu' => $siswa->nomor_wa_ibu,
                'pekerjaan_ibu' => $siswa->pekerjaan_ibu,
                'nama_wali' => $siswa->nama_wali,
                'hubungan_wali' => $siswa->hubungan_wali,
                'nomor_wa_wali' => $siswa->nomor_wa_wali,
                'kontak_absensi_utama' => $siswa->kontak_absensi_utama,
                'label_kontak_absensi_utama' => filled($siswa->kontak_absensi_utama)
                    ? str($siswa->kontak_absensi_utama)->replace('_', ' ')->headline()->toString()
                    : null,
            ],
            'alamat' => $siswa->alamat,
            'keterangan' => $siswa->keterangan,
        ];
    }

    private function hakAkses(Pengguna $pengguna): array
    {
        return [
            'dapat_melihat_rekap_poin' => $pengguna->memilikiIzin('poin_siswa.lihat'),
        ];
    }

    private function pegawaiId(Pengguna $pengguna): int
    {
        $pegawaiId = (int) ($pengguna->pegawai_id ?? 0);
        abort_unless($pegawaiId > 0 && $pengguna->memilikiPeran('guru_wali'), 403);

        return $pegawaiId;
    }

    private function labelJenisKelamin(?string $jenisKelamin): string
    {
        return match ($jenisKelamin) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => '-',
        };
    }
}
