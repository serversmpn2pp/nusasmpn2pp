<?php

namespace App\Services\Mobile;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LogScanAbsensi;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class StatusScanPresensiSiswaMobileService
{
    private const STATUS_SUDAH_TERCATAT = [
        'duplikat_cepat',
        'sudah_scan_masuk',
        'sudah_scan_pulang',
    ];

    public function statusHariIni(Pengguna $pengguna, array $filter): array
    {
        $tanggal = now()->toDateString();
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->first();
        $cakupanWaliKelas = $pengguna->membatasiCakupanWaliKelas();
        $kelasWaliIds = $cakupanWaliKelas ? $pengguna->kelasWaliIds() : [];
        $kelas = $tahunPelajaran
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->where('aktif', true)
                ->when($cakupanWaliKelas, fn (Builder $query) => $query->whereIn('id', $kelasWaliIds))
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(['id', 'nama', 'tingkat'])
            : collect();
        $kelasId = filled($filter['kelas_id'] ?? null) ? (int) $filter['kelas_id'] : null;
        if ($kelasId && ! $kelas->contains('id', $kelasId)) {
            $kelasId = null;
        }

        $anggota = $this->anggotaAktif($tahunPelajaran, $kelasId, $cakupanWaliKelas, $kelasWaliIds);
        $siswaIds = $anggota->pluck('siswa_id')->map(fn ($id) => (int) $id)->values();
        $kelasPerSiswa = $anggota->keyBy('siswa_id');
        $absensi = AbsensiSiswa::query()
            ->whereDate('tanggal', $tanggal)
            ->when($tahunPelajaran, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaran->id))
            ->when(! $tahunPelajaran, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(
                $tahunPelajaran || $cakupanWaliKelas || $kelasId,
                fn (Builder $query) => $query->whereIn('siswa_id', $siswaIds),
            );
        $logDasar = LogScanAbsensi::query()
            ->whereDate('tanggal', $tanggal)
            ->when(
                $cakupanWaliKelas || $kelasId,
                fn (Builder $query) => $query->whereIn('siswa_id', $siswaIds),
            );
        $status = $filter['status'] ?? 'semua';
        $cari = trim((string) ($filter['cari'] ?? ''));
        $log = (clone $logDasar)
            ->with([
                'siswa:id,nama_lengkap,nis,nisn,foto',
                'absensiSiswa:id,kelas_id,jam_masuk,status_masuk,menit_terlambat,jam_pulang,status_pulang,menit_pulang_cepat,status_kehadiran',
                'absensiSiswa.kelas:id,nama',
            ]);
        $this->terapkanFilterStatus($log, $status);
        $this->terapkanPencarian($log, $cari);
        $aktivitas = $log
            ->latest('waktu_scan')
            ->latest('id')
            ->limit((int) ($filter['batas'] ?? 40))
            ->get();

        return [
            'tanggal' => $tanggal,
            'tanggal_label' => now()->locale('id')->translatedFormat('l, d F Y'),
            'waktu_server' => now()->toIso8601String(),
            'pembaruan_berikutnya_detik' => 15,
            'tahun_pelajaran' => $tahunPelajaran ? [
                'id' => (int) $tahunPelajaran->id,
                'nama' => $tahunPelajaran->nama,
            ] : null,
            'jadwal' => $this->statusJadwal(),
            'ringkasan' => $this->ringkasan($anggota, $absensi, $logDasar),
            'aktivitas' => $aktivitas
                ->map(fn (LogScanAbsensi $item) => $this->aktivitas($item, $kelasPerSiswa))
                ->values(),
            'kelas' => $kelas->map(fn (Kelas $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama,
                'tingkat' => $item->tingkat === null ? null : (int) $item->tingkat,
            ])->values(),
            'filter' => [
                'kelas_id' => $kelasId,
                'status' => $status,
                'cari' => $cari,
            ],
        ];
    }

    private function anggotaAktif(
        ?TahunPelajaran $tahunPelajaran,
        ?int $kelasId,
        bool $cakupanWaliKelas,
        array $kelasWaliIds,
    ): Collection {
        if (! $tahunPelajaran) {
            return collect();
        }

        return AnggotaKelas::query()
            ->with('kelas:id,nama')
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn (Builder $query) => $query->where('aktif', true))
            ->when($cakupanWaliKelas, fn (Builder $query) => $query->whereIn('kelas_id', $kelasWaliIds))
            ->when($kelasId, fn (Builder $query) => $query->where('kelas_id', $kelasId))
            ->get(['id', 'kelas_id', 'siswa_id']);
    }

    private function ringkasan(Collection $anggota, Builder $absensi, Builder $logDasar): array
    {
        $masuk = (clone $absensi)->whereNotNull('jam_masuk')->count();
        $pulang = (clone $absensi)->whereNotNull('jam_pulang')->count();

        return [
            'jumlah_siswa' => $anggota->count(),
            'sudah_masuk' => $masuk,
            'terlambat' => (clone $absensi)->where('status_masuk', 'terlambat')->count(),
            'sudah_pulang' => $pulang,
            'belum_scan_masuk' => max($anggota->count() - $masuk, 0),
            'belum_scan_pulang' => max($masuk - $pulang, 0),
            'scan_berhasil' => (clone $logDasar)->where('berhasil', true)->count(),
            'sudah_tercatat' => (clone $logDasar)->whereIn('status_scan', self::STATUS_SUDAH_TERCATAT)->count(),
            'perlu_perhatian' => (clone $logDasar)
                ->where('berhasil', false)
                ->whereNotIn('status_scan', self::STATUS_SUDAH_TERCATAT)
                ->count(),
        ];
    }

    private function terapkanFilterStatus(Builder $query, string $status): void
    {
        match ($status) {
            'berhasil' => $query->where('berhasil', true),
            'sudah_tercatat' => $query->whereIn('status_scan', self::STATUS_SUDAH_TERCATAT),
            'perlu_perhatian' => $query
                ->where('berhasil', false)
                ->whereNotIn('status_scan', self::STATUS_SUDAH_TERCATAT),
            'masuk' => $query->where('jenis_scan', 'masuk'),
            'pulang' => $query->where('jenis_scan', 'pulang'),
            'terlambat' => $query
                ->where('berhasil', true)
                ->whereHas('absensiSiswa', fn (Builder $query) => $query->where('status_masuk', 'terlambat')),
            default => null,
        };
    }

    private function terapkanPencarian(Builder $query, string $cari): void
    {
        if ($cari === '') {
            return;
        }

        $pola = '%'.mb_strtolower($cari).'%';
        $query->where(function (Builder $query) use ($pola) {
            $query->whereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola])
                ->orWhereRaw("LOWER(COALESCE(scanner_id, '')) LIKE ?", [$pola])
                ->orWhereRaw("LOWER(COALESCE(status_scan, '')) LIKE ?", [$pola])
                ->orWhereRaw("LOWER(COALESCE(pesan, '')) LIKE ?", [$pola])
                ->orWhereHas('siswa', function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]);
                });
        });
    }

    private function aktivitas(LogScanAbsensi $item, Collection $kelasPerSiswa): array
    {
        $siswa = $item->siswa;
        $anggota = $item->siswa_id ? $kelasPerSiswa->get($item->siswa_id) : null;
        $kelas = $item->absensiSiswa?->kelas?->nama ?? $anggota?->kelas?->nama;
        $fotoTersedia = $siswa && filled($siswa->foto)
            && Storage::disk('public')->exists($siswa->foto);

        return [
            'id' => (int) $item->id,
            'berhasil' => (bool) $item->berhasil,
            'status' => $item->status_scan,
            'status_label' => $this->labelStatus($item->status_scan),
            'pesan' => $item->pesan,
            'jenis_scan' => $item->jenis_scan,
            'jenis_scan_label' => match ($item->jenis_scan) {
                'masuk' => 'Masuk',
                'pulang' => 'Pulang',
                default => 'Tidak ditentukan',
            },
            'scanner_id' => $item->scanner_id,
            'waktu_scan' => $item->waktu_scan?->toIso8601String(),
            'jam_scan' => $item->waktu_scan?->format('H:i:s'),
            'siswa' => $siswa ? [
                'id' => (int) $siswa->id,
                'nama' => $siswa->nama_lengkap,
                'nis' => $siswa->nis,
                'nisn' => $siswa->nisn,
                'kelas' => $kelas,
                'foto_url' => $fotoTersedia ? asset('storage/'.$siswa->foto) : null,
                'inisial' => $this->inisial($siswa),
            ] : null,
            'presensi' => $item->absensiSiswa ? [
                'jam_masuk' => $this->formatJam($item->absensiSiswa->jam_masuk),
                'status_masuk' => $item->absensiSiswa->status_masuk,
                'menit_terlambat' => (int) $item->absensiSiswa->menit_terlambat,
                'jam_pulang' => $this->formatJam($item->absensiSiswa->jam_pulang),
                'status_pulang' => $item->absensiSiswa->status_pulang,
                'menit_pulang_cepat' => (int) $item->absensiSiswa->menit_pulang_cepat,
                'status_kehadiran' => $item->absensiSiswa->status_kehadiran,
            ] : null,
        ];
    }

    private function statusJadwal(): array
    {
        $hari = array_keys(PengaturanAbsensi::DAFTAR_HARI)[now()->isoWeekday() - 1];
        $pengaturan = PengaturanAbsensi::query()
            ->where('hari', $hari)
            ->where('aktif', true)
            ->first();
        if (! $pengaturan) {
            return [
                'tersedia' => false,
                'hari' => $hari,
                'hari_label' => PengaturanAbsensi::DAFTAR_HARI[$hari]['label'],
                'fase' => 'tidak_tersedia',
                'fase_label' => 'Jadwal belum tersedia',
            ];
        }

        $sekarang = now()->hour * 60 + now()->minute;
        $mulaiMasuk = $this->menit($pengaturan->jam_scan_masuk_mulai);
        $selesaiMasuk = $this->menit($pengaturan->jam_scan_masuk_selesai);
        $jadwalLakiLaki = $pengaturan->jadwalPulangUntuk('L');
        $jadwalPerempuan = $pengaturan->jadwalPulangUntuk('P');
        $mulaiPulangLakiLaki = $this->menit($jadwalLakiLaki['jam_scan_pulang_mulai']);
        $selesaiPulangLakiLaki = $this->menit($jadwalLakiLaki['jam_scan_pulang_selesai']);
        $mulaiPulangPerempuan = $this->menit($jadwalPerempuan['jam_scan_pulang_mulai']);
        $selesaiPulangPerempuan = $this->menit($jadwalPerempuan['jam_scan_pulang_selesai']);
        $mulaiPulang = min($mulaiPulangLakiLaki, $mulaiPulangPerempuan);
        $selesaiPulang = max($selesaiPulangLakiLaki, $selesaiPulangPerempuan);
        $perempuanAktif = $sekarang >= $mulaiPulangPerempuan && $sekarang <= $selesaiPulangPerempuan;
        $lakiLakiAktif = $sekarang >= $mulaiPulangLakiLaki && $sekarang <= $selesaiPulangLakiLaki;
        $labelPulang = match (true) {
            $pengaturan->pulangJumatDibedakan() && $perempuanAktif && $lakiLakiAktif => 'Scan pulang semua siswa berlangsung',
            $pengaturan->pulangJumatDibedakan() && $perempuanAktif => 'Scan pulang siswi berlangsung',
            $pengaturan->pulangJumatDibedakan() && $lakiLakiAktif => 'Scan pulang siswa laki-laki berlangsung',
            default => 'Scan pulang berlangsung',
        };
        [$fase, $label] = match (true) {
            $sekarang < $mulaiMasuk => ['belum_dibuka', 'Menunggu scan masuk'],
            $sekarang <= $selesaiMasuk => ['scan_masuk', 'Scan masuk berlangsung'],
            $sekarang < $mulaiPulang => ['menunggu_pulang', 'Menunggu scan pulang'],
            $perempuanAktif || $lakiLakiAktif => ['scan_pulang', $labelPulang],
            $pengaturan->pulangJumatDibedakan() && $sekarang < $mulaiPulangLakiLaki => ['menunggu_pulang', 'Menunggu scan pulang siswa laki-laki'],
            $sekarang <= $selesaiPulang => ['menunggu_pulang', 'Menunggu jadwal scan berikutnya'],
            default => ['selesai', 'Jadwal scan selesai'],
        };

        return [
            'tersedia' => true,
            'hari' => $hari,
            'hari_label' => $pengaturan->labelHari(),
            'fase' => $fase,
            'fase_label' => $label,
            'jam_scan_masuk_mulai' => $pengaturan->formatJam($pengaturan->jam_scan_masuk_mulai),
            'jam_masuk' => $pengaturan->formatJam($pengaturan->jam_masuk),
            'jam_scan_masuk_selesai' => $pengaturan->formatJam($pengaturan->jam_scan_masuk_selesai),
            'jam_scan_pulang_mulai' => $pengaturan->formatJam($pengaturan->jam_scan_pulang_mulai),
            'jam_pulang' => $pengaturan->formatJam($pengaturan->jam_pulang),
            'jam_scan_pulang_selesai' => $pengaturan->formatJam($pengaturan->jam_scan_pulang_selesai),
            'pulang_jumat_dibedakan' => $pengaturan->pulangJumatDibedakan(),
            'jam_scan_pulang_perempuan_mulai' => $pengaturan->pulangJumatDibedakan()
                ? $pengaturan->formatJam($pengaturan->jam_scan_pulang_perempuan_mulai) : null,
            'jam_pulang_perempuan' => $pengaturan->pulangJumatDibedakan()
                ? $pengaturan->formatJam($pengaturan->jam_pulang_perempuan) : null,
            'jam_scan_pulang_perempuan_selesai' => $pengaturan->pulangJumatDibedakan()
                ? $pengaturan->formatJam($pengaturan->jam_scan_pulang_perempuan_selesai) : null,
        ];
    }

    private function labelStatus(string $status): string
    {
        return match ($status) {
            'berhasil_masuk' => 'Presensi masuk tersimpan',
            'berhasil_pulang' => 'Presensi pulang tersimpan',
            'duplikat_cepat', 'sudah_scan_masuk', 'sudah_scan_pulang' => 'Presensi sudah tercatat',
            'format_tidak_valid' => 'Kartu tidak terbaca',
            'siswa_tidak_ditemukan' => 'Siswa tidak ditemukan',
            'siswa_nonaktif' => 'Siswa nonaktif',
            'anggota_kelas_tidak_ada' => 'Siswa belum ditempatkan',
            'jadwal_absensi_tidak_ada' => 'Jadwal belum tersedia',
            'pulang_jumat_belum_dibuka' => 'Jadwal pulang laki-laki belum dibuka',
            'di_luar_jadwal', 'di_luar_jadwal_masuk', 'di_luar_jadwal_pulang' => 'Di luar jadwal',
            'kehadiran_manual_aktif' => 'Kehadiran manual sudah tercatat',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 8) : null;
    }

    private function menit(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($hour * 60) + $minute;
    }

    private function inisial(Siswa $siswa): string
    {
        $inisial = collect(preg_split('/\s+/', trim($siswa->nama_lengkap)) ?: [])
            ->take(2)
            ->map(fn (string $kata) => mb_substr($kata, 0, 1))
            ->join('');

        return mb_strtoupper($inisial ?: 'S');
    }
}
