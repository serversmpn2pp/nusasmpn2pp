<?php

namespace App\Services\Mobile;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Pengguna;
use App\Models\PresensiBerhalanganIbadah;
use App\Models\PresensiKegiatanIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\RekapHarianKegiatanIbadah;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class IbadahSayaMobileService
{
    public function ringkasanBeranda(Pengguna $pengguna, Carbon $tanggal): ?array
    {
        $identitas = $this->siswaBerandaUntukPengguna($pengguna);
        if (! $identitas) {
            return null;
        }

        [$siswa, $mode] = $identitas;
        $tahunPelajaran = $this->tahunPelajaran($siswa);
        $tahunDipilih = $this->pilihTahunPelajaran($tahunPelajaran, null);
        $bulan = $tanggal->copy()->startOfMonth();
        $anggotaKelas = $this->anggotaKelas($siswa, $tahunDipilih);
        [$tanggalMulai, $tanggalSelesai] = $this->rentangBerlaku(
            $bulan,
            $tahunDipilih,
            $anggotaKelas,
        );
        [$riwayat, $ringkasan] = $this->dataIbadah(
            $siswa,
            $tahunDipilih,
            $anggotaKelas,
            $tanggalMulai,
            $tanggalSelesai,
        );
        $hariIni = $riwayat
            ->where('tanggal', $tanggal->toDateString())
            ->values();
        $statusHariIni = $this->statusRingkasanHariIni($hariIni);

        return [
            'mode' => $mode,
            'judul' => $mode === 'orang_tua' ? 'Ibadah Anak Saya' : 'Ibadah Saya',
            'siswa' => $this->ringkasSiswa($siswa),
            'bulan_label' => $bulan->copy()->locale('id')->translatedFormat('F Y'),
            'rute' => $mode === 'orang_tua' ? '/ibadah-anak-saya' : '/ibadah-saya',
            'hari_ini' => [
                'tanggal' => $tanggal->toDateString(),
                'ada_jadwal' => $hariIni->isNotEmpty(),
                'status' => $statusHariIni['status'],
                'label_status' => $statusHariIni['label'],
                'items' => $hariIni
                    ->map(fn (array $item) => [
                        'jadwal_id' => $item['jadwal_id'],
                        'kegiatan' => $item['kegiatan']['nama'],
                        'jam_pelaksanaan' => $item['jam_pelaksanaan'],
                        'status' => $item['status'],
                        'status_label' => $item['status_label'],
                        'waktu_tercatat' => $item['waktu_tercatat'],
                    ])
                    ->all(),
            ],
            'bulan_ini' => $ringkasan,
        ];
    }

    public function tampilkan(Pengguna $pengguna, array $filter): array
    {
        [$siswa, $pilihanSiswa, $mode] = $this->siswaUntukPengguna(
            $pengguna,
            isset($filter['siswa_id']) ? (int) $filter['siswa_id'] : null,
        );
        $tahunPelajaran = $this->tahunPelajaran($siswa);
        $tahunDipilih = $this->pilihTahunPelajaran(
            $tahunPelajaran,
            isset($filter['tahun_pelajaran_id']) ? (int) $filter['tahun_pelajaran_id'] : null,
        );
        $bulan = filled($filter['bulan'] ?? null)
            ? Carbon::createFromFormat('Y-m-d', $filter['bulan'].'-01')->startOfMonth()
            : now()->startOfMonth();

        abort_if(
            $bulan->gt(now()->startOfMonth()),
            422,
            'Bulan ibadah tidak boleh melewati bulan berjalan.',
        );

        $anggotaKelas = $this->anggotaKelas($siswa, $tahunDipilih);
        [$tanggalMulai, $tanggalSelesai] = $this->rentangBerlaku(
            $bulan,
            $tahunDipilih,
            $anggotaKelas,
        );
        [$riwayat, $ringkasan] = $this->dataIbadah(
            $siswa,
            $tahunDipilih,
            $anggotaKelas,
            $tanggalMulai,
            $tanggalSelesai,
        );

        return [
            'mode' => $mode,
            'siswa' => $this->ringkasSiswa($siswa),
            'pilihan_siswa' => $pilihanSiswa
                ->map(fn (Siswa $item) => $this->ringkasSiswa($item))
                ->values()
                ->all(),
            'tahun_pelajaran' => $tahunPelajaran
                ->map(fn (TahunPelajaran $item) => $this->ringkasTahun($item))
                ->values()
                ->all(),
            'tahun_pelajaran_dipilih' => $tahunDipilih
                ? $this->ringkasTahun($tahunDipilih)
                : null,
            'kelas' => $anggotaKelas ? [
                'id' => (int) $anggotaKelas->kelas_id,
                'nama' => $anggotaKelas->kelas?->nama ?? '-',
                'tingkat' => (int) ($anggotaKelas->kelas?->tingkat ?? 0),
                'nomor_absen' => $anggotaKelas->nomor_absen,
                'status_keanggotaan' => $anggotaKelas->status_keanggotaan,
            ] : null,
            'filter' => [
                'siswa_id' => (int) $siswa->id,
                'tahun_pelajaran_id' => $tahunDipilih ? (int) $tahunDipilih->id : null,
                'bulan' => $bulan->format('Y-m'),
            ],
            'bulan_label' => $bulan->copy()->locale('id')->translatedFormat('F Y'),
            'ringkasan' => $ringkasan,
            'riwayat' => $riwayat->values()->all(),
            'pesan_kosong' => $tahunDipilih && $anggotaKelas
                ? 'Belum ada kegiatan ibadah yang dijadwalkan pada bulan ini.'
                : 'Siswa belum memiliki riwayat penempatan kelas.',
            'pesan_privasi' => 'Status berhalangan ditampilkan tanpa catatan privat atau rincian konfirmasi.',
        ];
    }

    private function siswaUntukPengguna(Pengguna $pengguna, ?int $siswaId): array
    {
        if ($pengguna->akunSiswa()) {
            $siswa = $pengguna->siswa;
            abort_unless($siswa, 403);
            abort_if($siswaId !== null && $siswaId !== (int) $siswa->id, 403);

            return [$siswa, collect([$siswa]), 'siswa'];
        }

        $orangTua = $pengguna->orangTuaWali()
            ->with(['siswa' => fn ($query) => $query->orderBy('nama_lengkap')])
            ->first();
        abort_unless($orangTua, 403);
        $pilihan = $orangTua->siswa;
        abort_if($pilihan->isEmpty(), 403, 'Belum ada siswa yang terhubung dengan akun orang tua.');
        $siswa = $siswaId !== null
            ? $pilihan->firstWhere('id', $siswaId)
            : ($pilihan->firstWhere('id', $orangTua->siswa_acuan_username_id) ?: $pilihan->first());
        abort_unless($siswa, 403);

        return [$siswa, $pilihan, 'orang_tua'];
    }

    private function siswaBerandaUntukPengguna(Pengguna $pengguna): ?array
    {
        if ($pengguna->akunSiswa()) {
            return $pengguna->siswa ? [$pengguna->siswa, 'siswa'] : null;
        }

        if (! $pengguna->akunOrangTua()) {
            return null;
        }

        $orangTua = $pengguna->orangTuaWali()
            ->with(['siswa' => fn ($query) => $query->orderBy('nama_lengkap')])
            ->first();
        $pilihan = $orangTua?->siswa ?? collect();
        if ($pilihan->isEmpty()) {
            return null;
        }

        $siswa = $pilihan->firstWhere('id', $orangTua->siswa_acuan_username_id)
            ?: $pilihan->first();

        return [$siswa, 'orang_tua'];
    }

    private function tahunPelajaran(Siswa $siswa): Collection
    {
        $ids = AnggotaKelas::query()
            ->where('siswa_id', $siswa->id)
            ->pluck('tahun_pelajaran_id')
            ->merge(PresensiKegiatanIbadah::query()
                ->where('siswa_id', $siswa->id)
                ->pluck('tahun_pelajaran_id'))
            ->merge(PresensiBerhalanganIbadah::query()
                ->where('siswa_id', $siswa->id)
                ->pluck('tahun_pelajaran_id'))
            ->filter()
            ->unique();

        return TahunPelajaran::query()
            ->whereIn('id', $ids)
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'nama', 'aktif', 'tanggal_mulai', 'tanggal_selesai']);
    }

    private function pilihTahunPelajaran(Collection $pilihan, ?int $tahunId): ?TahunPelajaran
    {
        if ($tahunId !== null) {
            $tahun = $pilihan->firstWhere('id', $tahunId);
            abort_unless($tahun, 403);

            return $tahun;
        }

        return $pilihan->firstWhere('aktif', true) ?: $pilihan->first();
    }

    private function anggotaKelas(Siswa $siswa, ?TahunPelajaran $tahun): ?AnggotaKelas
    {
        if (! $tahun) {
            return null;
        }

        return AnggotaKelas::query()
            ->with('kelas:id,nama,tingkat')
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahun->id)
            ->orderByRaw("CASE WHEN status_keanggotaan = 'aktif' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();
    }

    private function rentangBerlaku(
        Carbon $bulan,
        ?TahunPelajaran $tahun,
        ?AnggotaKelas $anggota,
    ): array {
        if (! $tahun || ! $anggota) {
            return [null, null];
        }

        $mulai = $bulan->copy()->startOfMonth()
            ->max($tahun->tanggal_mulai->copy()->startOfDay());
        $selesai = $bulan->copy()->endOfMonth()
            ->min($tahun->tanggal_selesai->copy()->endOfDay())
            ->min(now()->endOfDay());

        if ($anggota->tanggal_masuk) {
            $mulai = $mulai->max($anggota->tanggal_masuk->copy()->startOfDay());
        }
        if ($anggota->tanggal_keluar) {
            $selesai = $selesai->min($anggota->tanggal_keluar->copy()->endOfDay());
        }

        return $mulai->gt($selesai) ? [null, null] : [$mulai, $selesai];
    }

    private function dataIbadah(
        Siswa $siswa,
        ?TahunPelajaran $tahun,
        ?AnggotaKelas $anggota,
        ?Carbon $tanggalMulai,
        ?Carbon $tanggalSelesai,
    ): array {
        if (! $tahun || ! $anggota || ! $tanggalMulai || ! $tanggalSelesai) {
            return [collect(), $this->ringkasan(collect())];
        }

        $jadwalPerHari = JadwalKegiatanIbadah::query()
            ->with('kegiatanIbadah:id,kode,nama,aktif')
            ->where('tahun_pelajaran_id', $tahun->id)
            ->where('aktif', true)
            ->whereHas('kegiatanIbadah', fn ($query) => $query->where('aktif', true))
            ->orderBy('jam_pelaksanaan')
            ->orderBy('id')
            ->get()
            ->groupBy('hari');
        $absensi = AbsensiSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahun->id)
            ->whereDate('tanggal', '>=', $tanggalMulai->toDateString())
            ->whereDate('tanggal', '<=', $tanggalSelesai->toDateString())
            ->latest('id')
            ->get(['id', 'tanggal', 'status_kehadiran'])
            ->unique(fn (AbsensiSiswa $item) => substr((string) $item->getRawOriginal('tanggal'), 0, 10))
            ->keyBy(fn (AbsensiSiswa $item) => substr((string) $item->getRawOriginal('tanggal'), 0, 10));
        $presensi = PresensiKegiatanIbadah::query()
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahun->id)
            ->whereDate('tanggal', '>=', $tanggalMulai->toDateString())
            ->whereDate('tanggal', '<=', $tanggalSelesai->toDateString())
            ->get(['id', 'kegiatan_ibadah_id', 'tanggal', 'waktu_scan'])
            ->keyBy(fn (PresensiKegiatanIbadah $item) => $this->kunci($item->getRawOriginal('tanggal'), $item->kegiatan_ibadah_id));
        $berhalangan = PresensiBerhalanganIbadah::query()
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahun->id)
            ->whereDate('tanggal', '>=', $tanggalMulai->toDateString())
            ->whereDate('tanggal', '<=', $tanggalSelesai->toDateString())
            ->get(['id', 'kegiatan_ibadah_id', 'tanggal', 'waktu_scan'])
            ->keyBy(fn (PresensiBerhalanganIbadah $item) => $this->kunci($item->getRawOriginal('tanggal'), $item->kegiatan_ibadah_id));
        $riwayat = collect();

        foreach (CarbonPeriod::create($tanggalMulai, $tanggalSelesai) as $tanggal) {
            $tanggalString = $tanggal->toDateString();
            $kehadiran = $absensi->get($tanggalString);
            $statusKehadiran = $kehadiran?->status_kehadiran ?: 'alfa';

            foreach ($jadwalPerHari->get($this->kodeHari($tanggal->isoWeekday()), collect()) as $jadwal) {
                $kegiatan = $jadwal->kegiatanIbadah;
                $kunci = $this->kunci($tanggal, $jadwal->kegiatan_ibadah_id);
                $catatan = $presensi->get($kunci);
                $catatanBerhalangan = $berhalangan->get($kunci);
                $status = $this->status(
                    $statusKehadiran,
                    $siswa,
                    $kegiatan,
                    (bool) $catatan,
                    (bool) $catatanBerhalangan,
                );
                $waktu = match ($status) {
                    RekapHarianKegiatanIbadah::STATUS_SUDAH => $catatan?->waktu_scan,
                    RekapHarianKegiatanIbadah::STATUS_BERHALANGAN => $catatanBerhalangan?->waktu_scan,
                    default => null,
                };

                $riwayat->push([
                    'jadwal_id' => (int) $jadwal->id,
                    'tanggal' => $tanggalString,
                    'tanggal_label' => $tanggal->copy()->locale('id')->translatedFormat('l, d F Y'),
                    'hari' => $jadwal->labelHari(),
                    'jam_pelaksanaan' => $jadwal->formatJam($jadwal->jam_pelaksanaan),
                    'kegiatan' => [
                        'id' => (int) $kegiatan->id,
                        'kode' => $kegiatan->kode,
                        'nama' => $kegiatan->nama,
                        'khusus_laki_laki' => $kegiatan->khususLakiLaki(),
                    ],
                    'status' => $status,
                    'status_label' => $this->labelStatus($status),
                    'status_kehadiran' => $statusKehadiran,
                    'status_kehadiran_label' => $this->labelKehadiran($statusKehadiran, (bool) $kehadiran),
                    'waktu_tercatat' => filled($waktu) ? substr((string) $waktu, 0, 5) : null,
                ]);
            }
        }

        $riwayat = $riwayat
            ->sortByDesc(fn (array $item) => $item['tanggal'].' '.$item['jam_pelaksanaan'])
            ->values();

        return [$riwayat, $this->ringkasan($riwayat)];
    }

    private function status(
        string $statusKehadiran,
        Siswa $siswa,
        KegiatanIbadah $kegiatan,
        bool $sudah,
        bool $berhalangan,
    ): string {
        if ($statusKehadiran !== 'hadir') {
            return RekapHarianKegiatanIbadah::STATUS_TIDAK_HADIR;
        }
        if ($kegiatan->khususLakiLaki() && $siswa->jenis_kelamin === 'P') {
            return RekapHarianKegiatanIbadah::STATUS_TIDAK_WAJIB;
        }
        if ($sudah) {
            return RekapHarianKegiatanIbadah::STATUS_SUDAH;
        }
        if ($berhalangan) {
            return RekapHarianKegiatanIbadah::STATUS_BERHALANGAN;
        }

        return RekapHarianKegiatanIbadah::STATUS_BELUM;
    }

    private function ringkasan(Collection $riwayat): array
    {
        $jumlah = $riwayat->countBy('status');
        $sudah = (int) ($jumlah[RekapHarianKegiatanIbadah::STATUS_SUDAH] ?? 0);
        $belum = (int) ($jumlah[RekapHarianKegiatanIbadah::STATUS_BELUM] ?? 0);
        $wajib = $sudah + $belum;

        return [
            'total' => $riwayat->count(),
            'sudah' => $sudah,
            'belum' => $belum,
            'berhalangan' => (int) ($jumlah[RekapHarianKegiatanIbadah::STATUS_BERHALANGAN] ?? 0),
            'tidak_hadir' => (int) ($jumlah[RekapHarianKegiatanIbadah::STATUS_TIDAK_HADIR] ?? 0),
            'tidak_wajib' => (int) ($jumlah[RekapHarianKegiatanIbadah::STATUS_TIDAK_WAJIB] ?? 0),
            'wajib' => $wajib,
            'persentase' => $wajib > 0 ? (int) round(($sudah / $wajib) * 100) : 0,
        ];
    }

    private function statusRingkasanHariIni(Collection $riwayat): array
    {
        if ($riwayat->isEmpty()) {
            return ['status' => 'tanpa_jadwal', 'label' => 'Tidak ada jadwal'];
        }

        $status = $riwayat->pluck('status');
        if ($status->contains(RekapHarianKegiatanIbadah::STATUS_BELUM)) {
            return [
                'status' => RekapHarianKegiatanIbadah::STATUS_BELUM,
                'label' => $riwayat->count() > 1 ? 'Belum lengkap' : 'Belum salat',
            ];
        }
        if ($status->contains(RekapHarianKegiatanIbadah::STATUS_SUDAH)) {
            return ['status' => RekapHarianKegiatanIbadah::STATUS_SUDAH, 'label' => 'Sudah tercatat'];
        }
        if ($status->contains(RekapHarianKegiatanIbadah::STATUS_BERHALANGAN)) {
            return ['status' => RekapHarianKegiatanIbadah::STATUS_BERHALANGAN, 'label' => 'Berhalangan'];
        }
        if ($status->contains(RekapHarianKegiatanIbadah::STATUS_TIDAK_HADIR)) {
            return ['status' => RekapHarianKegiatanIbadah::STATUS_TIDAK_HADIR, 'label' => 'Tidak hadir sekolah'];
        }

        return ['status' => RekapHarianKegiatanIbadah::STATUS_TIDAK_WAJIB, 'label' => 'Tidak wajib'];
    }

    private function labelStatus(string $status): string
    {
        return match ($status) {
            RekapHarianKegiatanIbadah::STATUS_SUDAH => 'Sudah salat',
            RekapHarianKegiatanIbadah::STATUS_BELUM => 'Belum salat',
            RekapHarianKegiatanIbadah::STATUS_BERHALANGAN => 'Berhalangan',
            RekapHarianKegiatanIbadah::STATUS_TIDAK_HADIR => 'Tidak hadir sekolah',
            RekapHarianKegiatanIbadah::STATUS_TIDAK_WAJIB => 'Tidak wajib (pulang)',
            default => 'Belum diketahui',
        };
    }

    private function labelKehadiran(string $status, bool $tercatat): string
    {
        if (! $tercatat) {
            return 'Belum tercatat di presensi sekolah';
        }

        return match ($status) {
            'hadir' => 'Hadir di sekolah',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'alfa' => 'Alfa',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function ringkasSiswa(Siswa $siswa): array
    {
        return [
            'id' => (int) $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'nis' => $siswa->nis,
            'nisn' => $siswa->nisn,
            'jenis_kelamin' => $siswa->jenis_kelamin,
        ];
    }

    private function ringkasTahun(TahunPelajaran $tahun): array
    {
        return [
            'id' => (int) $tahun->id,
            'nama' => $tahun->nama,
            'aktif' => (bool) $tahun->aktif,
        ];
    }

    private function kodeHari(int $isoWeekday): string
    {
        return [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][$isoWeekday];
    }

    private function kunci($tanggal, int $kegiatanId): string
    {
        return Carbon::parse($tanggal)->toDateString().'|'.$kegiatanId;
    }
}
