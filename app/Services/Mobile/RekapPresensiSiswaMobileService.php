<?php

namespace App\Services\Mobile;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\RiwayatPerubahanAbsensiSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\KoreksiPresensiSiswaService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class RekapPresensiSiswaMobileService
{
    public function __construct(private readonly KoreksiPresensiSiswaService $koreksi) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $tanggal = Carbon::parse($filter['tanggal'] ?? now())->toDateString();
        $this->koreksi->pastikanTanggalDiizinkan($pengguna, $tanggal);
        $cakupanWali = $pengguna->membatasiCakupanWaliKelas();
        $kelasWaliIds = $cakupanWali ? $pengguna->kelasWaliIds() : [];
        $tahun = $this->daftarTahun($cakupanWali, $kelasWaliIds);
        $tahunId = $this->ambilTahunId($filter['tahun_pelajaran_id'] ?? null, $tahun);
        $kelas = $this->daftarKelas($tahunId, $cakupanWali, $kelasWaliIds);
        $kelasId = $this->ambilKelasId($filter['kelas_id'] ?? null, $kelas, $cakupanWali);
        $status = $filter['status'] ?? 'semua';
        $cari = trim((string) ($filter['cari'] ?? ''));
        $halaman = (int) ($filter['halaman'] ?? 1);

        $query = $this->queryAnggota($tahunId, $kelasId, $cakupanWali ? $kelasWaliIds : null);
        $this->terapkanPencarian($query, $cari);
        $this->terapkanStatus($query, $status, $tanggal);
        $paginator = $query
            ->with(['kelas:id,nama,tingkat', 'siswa:id,nama_lengkap,nis,nisn,foto'])
            ->orderBy('kelas_id')
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->paginate(30, ['*'], 'halaman', $halaman);
        $anggota = collect($paginator->items());
        $absensi = $this->absensiPerSiswa($tanggal, $anggota->pluck('siswa_id'));

        return [
            'tanggal' => $tanggal,
            'tanggal_label' => Carbon::parse($tanggal)->locale('id')->translatedFormat('l, d F Y'),
            'waktu_server' => now()->toIso8601String(),
            'items' => $anggota->map(fn (AnggotaKelas $item) => $this->item(
                $pengguna,
                $item,
                $tanggal,
                $absensi->get($item->siswa_id),
            ))->values(),
            'ringkasan' => $this->ringkasan($tahunId, $kelasId, $cakupanWali ? $kelasWaliIds : null, $tanggal),
            'tahun_pelajaran' => $tahun->map(fn (TahunPelajaran $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama,
                'aktif' => (bool) $item->aktif,
            ])->values(),
            'kelas' => $kelas->map(fn (Kelas $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama,
                'tingkat' => (int) $item->tingkat,
            ])->values(),
            'filter' => [
                'tanggal' => $tanggal,
                'tahun_pelajaran_id' => $tahunId,
                'kelas_id' => $kelasId,
                'status' => $status,
                'cari' => $cari,
            ],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
            'hak_akses' => [
                'dapat_koreksi' => $pengguna->memilikiIzin(['absensi.koreksi', 'absensi.koreksi_hari_ini']),
                'koreksi_hari_ini_terbatas' => $this->koreksi->koreksiHariIniTerbatas($pengguna),
                'cakupan_wali_kelas' => $cakupanWali,
            ],
        ];
    }

    public function detail(Pengguna $pengguna, AnggotaKelas $anggotaKelas, string $tanggal): array
    {
        $tanggal = Carbon::parse($tanggal)->toDateString();
        $this->koreksi->pastikanTanggalDiizinkan($pengguna, $tanggal);
        abort_unless($pengguna->dapatMengaksesKelasSebagaiWali($anggotaKelas->kelas_id), 403);
        $anggotaKelas->load(['tahunPelajaran:id,nama,aktif', 'kelas:id,nama,tingkat', 'siswa:id,nama_lengkap,nis,nisn,foto']);
        $absensi = $this->koreksi->ambilAbsensi($tanggal, $anggotaKelas);
        $akses = $this->koreksi->evaluasiAkses($pengguna, $anggotaKelas, $tanggal, $absensi);
        $hari = array_keys(PengaturanAbsensi::DAFTAR_HARI)[Carbon::parse($tanggal)->isoWeekday() - 1];
        $pengaturan = PengaturanAbsensi::query()->where('hari', $hari)->where('aktif', true)->first();
        $riwayat = RiwayatPerubahanAbsensiSiswa::query()
            ->with('dibuatOleh:id,nama,username')
            ->where('siswa_id', $anggotaKelas->siswa_id)
            ->whereDate('tanggal', $tanggal)
            ->latest('id')
            ->get();

        return [
            'tanggal' => $tanggal,
            'tanggal_label' => Carbon::parse($tanggal)->locale('id')->translatedFormat('l, d F Y'),
            'item' => $this->item($pengguna, $anggotaKelas, $tanggal, $absensi),
            'jadwal_presensi' => $pengaturan ? [
                'tersedia' => true,
                'jam_masuk' => $pengaturan->formatJam($pengaturan->jam_masuk),
                'jam_pulang' => $pengaturan->formatJam($pengaturan->jam_pulang),
            ] : ['tersedia' => false, 'jam_masuk' => null, 'jam_pulang' => null],
            'riwayat' => $riwayat->map(fn (RiwayatPerubahanAbsensiSiswa $item) => [
                'id' => (int) $item->id,
                'status_sebelum' => $item->status_sebelum,
                'status_sesudah' => $item->status_sesudah,
                'sumber' => $item->sumber,
                'sumber_label' => $this->labelSumber($item->sumber),
                'catatan' => $item->catatan,
                'dibuat_oleh' => $item->dibuatOleh?->nama ?? $item->dibuatOleh?->username,
                'dibuat_pada' => $item->created_at?->toIso8601String(),
            ])->values(),
            'hak_akses' => $akses,
        ];
    }

    private function daftarTahun(bool $cakupanWali, array $kelasWaliIds): Collection
    {
        return TahunPelajaran::query()
            ->when($cakupanWali, fn (Builder $query) => $query->whereHas(
                'kelas',
                fn (Builder $query) => $query->whereIn('id', $kelasWaliIds),
            ))
            ->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->orderByDesc('id')->get();
    }

    private function daftarKelas(?int $tahunId, bool $cakupanWali, array $kelasWaliIds): Collection
    {
        if (! $tahunId) {
            return collect();
        }

        return Kelas::query()->where('tahun_pelajaran_id', $tahunId)->where('aktif', true)
            ->when($cakupanWali, fn (Builder $query) => $query->whereIn('id', $kelasWaliIds))
            ->orderBy('tingkat')->orderBy('nama')->get(['id', 'nama', 'tingkat']);
    }

    private function ambilTahunId(mixed $dipilih, Collection $tahun): ?int
    {
        $id = filled($dipilih) ? (int) $dipilih : null;
        if ($id && $tahun->contains('id', $id)) {
            return $id;
        }

        return $tahun->firstWhere('aktif', true)?->id ?? $tahun->first()?->id;
    }

    private function ambilKelasId(mixed $dipilih, Collection $kelas, bool $cakupanWali): ?int
    {
        $id = filled($dipilih) ? (int) $dipilih : null;
        if ($id && $kelas->contains('id', $id)) {
            return $id;
        }

        return $cakupanWali && $kelas->count() === 1 ? (int) $kelas->first()->id : null;
    }

    private function queryAnggota(?int $tahunId, ?int $kelasId, ?array $kelasIds): Builder
    {
        return AnggotaKelas::query()
            ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->when(! $tahunId, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn (Builder $query) => $query->where('aktif', true))
            ->when(is_array($kelasIds), fn (Builder $query) => $query->whereIn('kelas_id', $kelasIds))
            ->when($kelasId, fn (Builder $query) => $query->where('kelas_id', $kelasId));
    }

    private function terapkanPencarian(Builder $query, string $cari): void
    {
        if ($cari === '') {
            return;
        }
        $pola = '%'.mb_strtolower($cari).'%';
        $query->whereHas('siswa', fn (Builder $query) => $query
            ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
            ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
            ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]));
    }

    private function terapkanStatus(Builder $query, string $status, string $tanggal): void
    {
        if ($status === 'belum_scan') {
            $query->whereDoesntHave('siswa.absensiSiswa', fn (Builder $query) => $query->whereDate('tanggal', $tanggal));
        } elseif (in_array($status, ['hadir', 'izin', 'sakit', 'alfa'], true)) {
            $query->whereHas('siswa.absensiSiswa', fn (Builder $query) => $query
                ->whereDate('tanggal', $tanggal)->where('status_kehadiran', $status));
        } elseif ($status === 'terlambat') {
            $query->whereHas('siswa.absensiSiswa', fn (Builder $query) => $query
                ->whereDate('tanggal', $tanggal)->where('menit_terlambat', '>', 0));
        } elseif ($status === 'pulang_cepat') {
            $query->whereHas('siswa.absensiSiswa', fn (Builder $query) => $query
                ->whereDate('tanggal', $tanggal)->where('menit_pulang_cepat', '>', 0));
        } elseif ($status === 'belum_pulang') {
            $query->whereHas('siswa.absensiSiswa', fn (Builder $query) => $query
                ->whereDate('tanggal', $tanggal)->where('status_kehadiran', 'hadir')
                ->whereNotNull('jam_masuk')->whereNull('jam_pulang'));
        }
    }

    private function absensiPerSiswa(string $tanggal, Collection $siswaIds): Collection
    {
        return AbsensiSiswa::query()->whereDate('tanggal', $tanggal)
            ->whereIn('siswa_id', $siswaIds)->get()->keyBy('siswa_id');
    }

    private function ringkasan(?int $tahunId, ?int $kelasId, ?array $kelasIds, string $tanggal): array
    {
        $anggota = $this->queryAnggota($tahunId, $kelasId, $kelasIds);
        $total = (clone $anggota)->count();
        $siswaIds = (clone $anggota)->pluck('siswa_id');
        $absensi = AbsensiSiswa::query()->whereDate('tanggal', $tanggal)->whereIn('siswa_id', $siswaIds);
        $tercatat = (clone $absensi)->distinct('siswa_id')->count('siswa_id');

        return [
            'total' => $total,
            'hadir' => (clone $absensi)->where('status_kehadiran', 'hadir')->count(),
            'izin' => (clone $absensi)->where('status_kehadiran', 'izin')->count(),
            'sakit' => (clone $absensi)->where('status_kehadiran', 'sakit')->count(),
            'alfa' => (clone $absensi)->where('status_kehadiran', 'alfa')->count(),
            'belum_scan' => max($total - $tercatat, 0),
            'terlambat' => (clone $absensi)->where('menit_terlambat', '>', 0)->count(),
            'pulang_cepat' => (clone $absensi)->where('menit_pulang_cepat', '>', 0)->count(),
            'belum_pulang' => (clone $absensi)->where('status_kehadiran', 'hadir')
                ->whereNotNull('jam_masuk')->whereNull('jam_pulang')->count(),
        ];
    }

    private function item(Pengguna $pengguna, AnggotaKelas $anggota, string $tanggal, ?AbsensiSiswa $absensi): array
    {
        $siswa = $anggota->siswa;
        $foto = $siswa && filled($siswa->foto) && Storage::disk('public')->exists($siswa->foto);
        $akses = $this->koreksi->evaluasiAkses($pengguna, $anggota, $tanggal, $absensi);
        $status = $absensi?->status_kehadiran ?? 'belum_scan';

        return [
            'anggota_kelas_id' => (int) $anggota->id,
            'nomor_absen' => $anggota->nomor_absen,
            'siswa' => $siswa ? [
                'id' => (int) $siswa->id,
                'nama' => $siswa->nama_lengkap,
                'nis' => $siswa->nis,
                'nisn' => $siswa->nisn,
                'inisial' => $this->inisial($siswa),
                'foto_url' => $foto ? asset('storage/'.$siswa->foto) : null,
            ] : null,
            'kelas' => $anggota->kelas ? [
                'id' => (int) $anggota->kelas->id,
                'nama' => $anggota->kelas->nama,
            ] : null,
            'presensi' => [
                'id' => $absensi?->id ? (int) $absensi->id : null,
                'status' => $status,
                'status_label' => $this->labelStatus($status),
                'sumber' => $absensi?->sumber ?? 'inferensi',
                'sumber_label' => $absensi ? $this->labelSumber($absensi->sumber) : 'Belum ada catatan',
                'jam_masuk' => $this->formatJam($absensi?->jam_masuk),
                'status_masuk' => $absensi?->status_masuk,
                'menit_terlambat' => (int) ($absensi?->menit_terlambat ?? 0),
                'jam_pulang' => $this->formatJam($absensi?->jam_pulang),
                'status_pulang' => $absensi?->status_pulang,
                'menit_pulang_cepat' => (int) ($absensi?->menit_pulang_cepat ?? 0),
                'catatan' => $absensi?->catatan,
                'belum_pulang' => $status === 'hadir' && $absensi?->jam_masuk && ! $absensi?->jam_pulang,
            ],
            'koreksi' => $akses,
        ];
    }

    private function labelStatus(string $status): string
    {
        return match ($status) {
            'hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alfa' => 'Alfa', default => 'Belum scan',
        };
    }

    private function labelSumber(?string $sumber): string
    {
        return match ($sumber) {
            'scan' => 'Mesin scanner', 'manual' => 'Koreksi petugas', 'guru_piket' => 'Guru piket',
            'koreksi_manual' => 'Koreksi manual', default => str($sumber ?: 'Tidak diketahui')->headline()->toString(),
        };
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }

    private function inisial(Siswa $siswa): string
    {
        return mb_strtoupper(collect(preg_split('/\s+/', trim($siswa->nama_lengkap)) ?: [])->take(2)
            ->map(fn (string $kata) => mb_substr($kata, 0, 1))->join('') ?: 'S');
    }
}
