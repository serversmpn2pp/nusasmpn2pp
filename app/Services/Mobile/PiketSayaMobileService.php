<?php

namespace App\Services\Mobile;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\JadwalPiketGuru;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Services\Piket\GuruPiketHariIniService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class PiketSayaMobileService
{
    public function __construct(private readonly GuruPiketHariIniService $piket) {}

    public function halaman(Pengguna $pengguna, array $filter): array
    {
        abort_unless($pengguna->pegawai_id, 403);
        $tahun = $this->piket->tahunPelajaranAktif();
        $kodeHari = $this->piket->kodeHariIni();
        $jadwalSaya = JadwalPiketGuru::query()
            ->where('tahun_pelajaran_id', $tahun->id)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('aktif', true)
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->get();
        $guruMapelAktif = $this->piket->guruMapelAktif($pengguna, $tahun);
        $dapatMencatat = $guruMapelAktif && $jadwalSaya->contains('hari', $kodeHari);
        $kelas = Kelas::query()
            ->where('tahun_pelajaran_id', $tahun->id)
            ->where('aktif', true)
            ->orderBy('tingkat')->orderBy('nama')->get(['id', 'nama', 'tingkat']);
        $kelasId = filled($filter['kelas_id'] ?? null) && $kelas->contains('id', (int) $filter['kelas_id'])
            ? (int) $filter['kelas_id'] : null;
        $status = $filter['status'] ?? 'semua';
        $cari = trim((string) ($filter['cari'] ?? ''));
        $halaman = (int) ($filter['halaman'] ?? 1);

        $hasil = $dapatMencatat
            ? $this->daftarSiswa($tahun->id, $kelasId, $status, $cari, $halaman)
            : ['items' => collect(), 'ringkasan' => $this->ringkasanKosong(), 'paginasi' => $this->paginasiKosong()];

        return [
            'tanggal' => now()->toDateString(),
            'tanggal_label' => now()->locale('id')->translatedFormat('l, d F Y'),
            'waktu_server' => now()->toIso8601String(),
            'tahun_pelajaran' => ['id' => (int) $tahun->id, 'nama' => $tahun->nama],
            'hari_ini' => ['kode' => $kodeHari, 'label' => JadwalPiketGuru::DAFTAR_HARI[$kodeHari] ?? '-'],
            'jadwal_saya' => $jadwalSaya->map(fn (JadwalPiketGuru $item) => [
                'id' => (int) $item->id,
                'hari' => $item->hari,
                'hari_label' => $item->labelHari(),
                'keterangan' => $item->keterangan,
            ])->values(),
            'guru_mapel_aktif' => $guruMapelAktif,
            'dapat_mencatat_hari_ini' => $dapatMencatat,
            'items' => $hasil['items'],
            'ringkasan' => $hasil['ringkasan'],
            'paginasi' => $hasil['paginasi'],
            'kelas' => $kelas->map(fn (Kelas $item) => [
                'id' => (int) $item->id, 'nama' => $item->nama, 'tingkat' => (int) $item->tingkat,
            ])->values(),
            'filter' => ['kelas_id' => $kelasId, 'status' => $status, 'cari' => $cari],
        ];
    }

    private function daftarSiswa(int $tahunId, ?int $kelasId, string $status, string $cari, int $halaman): array
    {
        $tanggal = now()->toDateString();
        $query = AnggotaKelas::query()
            ->with(['kelas:id,nama', 'siswa:id,nama_lengkap,nis,nisn,foto'])
            ->where('tahun_pelajaran_id', $tahunId)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn (Builder $query) => $query->where('aktif', true))
            ->when($kelasId, fn (Builder $query) => $query->where('kelas_id', $kelasId))
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->whereHas('siswa', fn (Builder $query) => $query
                    ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                    ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                    ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]));
            })
            ->when($status === 'belum_scan', fn (Builder $query) => $query->whereDoesntHave(
                'siswa.absensiSiswa', fn (Builder $query) => $query->whereDate('tanggal', $tanggal),
            ))
            ->when(in_array($status, ['hadir', 'sakit', 'izin', 'alfa'], true), fn (Builder $query) => $query->whereHas(
                'siswa.absensiSiswa', fn (Builder $query) => $query->whereDate('tanggal', $tanggal)->where('status_kehadiran', $status),
            ))
            ->orderBy('kelas_id')->orderByRaw('nomor_absen IS NULL')->orderBy('nomor_absen')->orderBy('id');
        $paginator = $query->paginate(30, ['*'], 'halaman', $halaman);
        $anggota = collect($paginator->items());
        $absensi = AbsensiSiswa::query()->whereDate('tanggal', $tanggal)
            ->whereIn('siswa_id', $anggota->pluck('siswa_id'))->get()->keyBy('siswa_id');

        return [
            'items' => $anggota->map(fn (AnggotaKelas $item) => $this->siswa($item, $absensi->get($item->siswa_id)))->values(),
            'ringkasan' => $this->ringkasan($tahunId, $kelasId, $tanggal),
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    private function ringkasan(int $tahunId, ?int $kelasId, string $tanggal): array
    {
        $cakupan = AnggotaKelas::query()->where('tahun_pelajaran_id', $tahunId)
            ->where('status_keanggotaan', 'aktif')->whereHas('siswa', fn (Builder $q) => $q->where('aktif', true))
            ->when($kelasId, fn (Builder $q) => $q->where('kelas_id', $kelasId));
        $total = (clone $cakupan)->count();
        $siswaIds = (clone $cakupan)->pluck('siswa_id');
        $absensi = AbsensiSiswa::query()->whereDate('tanggal', $tanggal)->whereIn('siswa_id', $siswaIds);
        $tercatat = (clone $absensi)->distinct('siswa_id')->count('siswa_id');

        return [
            'total' => $total,
            'hadir' => (clone $absensi)->where('status_kehadiran', 'hadir')->count(),
            'sakit' => (clone $absensi)->where('status_kehadiran', 'sakit')->count(),
            'izin' => (clone $absensi)->where('status_kehadiran', 'izin')->count(),
            'belum_scan' => max($total - $tercatat, 0),
        ];
    }

    private function siswa(AnggotaKelas $anggota, ?AbsensiSiswa $absensi): array
    {
        $siswa = $anggota->siswa;
        $foto = $siswa && filled($siswa->foto) && Storage::disk('public')->exists($siswa->foto);

        return [
            'anggota_kelas_id' => (int) $anggota->id,
            'nomor_absen' => $anggota->nomor_absen,
            'siswa' => $siswa ? [
                'id' => (int) $siswa->id, 'nama' => $siswa->nama_lengkap, 'nis' => $siswa->nis,
                'nisn' => $siswa->nisn, 'foto_url' => $foto ? asset('storage/'.$siswa->foto) : null,
                'inisial' => $this->inisial($siswa),
            ] : null,
            'kelas' => $anggota->kelas ? ['id' => (int) $anggota->kelas->id, 'nama' => $anggota->kelas->nama] : null,
            'presensi' => [
                'status' => $absensi?->status_kehadiran ?? 'belum_scan',
                'status_label' => match ($absensi?->status_kehadiran) {
                    'hadir' => 'Hadir', 'sakit' => 'Sakit', 'izin' => 'Izin', 'alfa' => 'Alfa', default => 'Belum scan',
                },
                'sumber' => $absensi?->sumber,
                'catatan' => $absensi?->catatan,
                'jam_masuk' => $absensi?->jam_masuk ? substr($absensi->jam_masuk, 0, 5) : null,
                'dapat_dicatat' => ! $absensi || ($absensi->sumber === 'guru_piket' && ! $absensi->jam_masuk),
            ],
        ];
    }

    private function inisial(Siswa $siswa): string
    {
        return mb_strtoupper(collect(preg_split('/\s+/', trim($siswa->nama_lengkap)) ?: [])->take(2)
            ->map(fn (string $kata) => mb_substr($kata, 0, 1))->join('') ?: 'S');
    }

    private function ringkasanKosong(): array
    {
        return ['total' => 0, 'hadir' => 0, 'sakit' => 0, 'izin' => 0, 'belum_scan' => 0];
    }

    private function paginasiKosong(): array
    {
        return ['halaman' => 1, 'halaman_terakhir' => 1, 'total' => 0, 'ada_halaman_berikutnya' => false];
    }
}
