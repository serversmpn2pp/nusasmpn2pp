<?php

namespace App\Services\Ibadah;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\KegiatanIbadah;
use App\Models\PresensiBerhalanganIbadah;
use App\Models\PresensiKegiatanIbadah;
use App\Models\TahunPelajaran;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RekapHarianKegiatanIbadah
{
    public const STATUS_SUDAH = 'sudah';

    public const STATUS_BELUM = 'belum';

    public const STATUS_BERHALANGAN = 'berhalangan';

    public const STATUS_TIDAK_HADIR = 'tidak_hadir';

    public const STATUS_TIDAK_WAJIB = 'tidak_wajib';

    public function hitung(
        TahunPelajaran $tahunPelajaran,
        Collection $daftarKelas,
        int $kegiatanIbadahId,
        CarbonInterface $tanggal,
    ): array {
        $kelasIds = $daftarKelas->pluck('id')->map(fn ($id) => (int) $id)->values();

        if ($kelasIds->isEmpty()) {
            return [
                'status_per_siswa' => collect(),
                'ringkasan_per_kelas' => collect(),
            ];
        }

        $khususLakiLaki = KegiatanIbadah::query()
            ->whereKey($kegiatanIbadahId)
            ->value('kode') === KegiatanIbadah::KODE_SHOLAT_JUMAT;
        $anggotaKelas = AnggotaKelas::query()
            ->select(['id', 'tahun_pelajaran_id', 'kelas_id', 'siswa_id'])
            ->with('siswa:id,jenis_kelamin')
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->whereIn('kelas_id', $kelasIds)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
            ->get();
        $siswaIds = $anggotaKelas->pluck('siswa_id')->unique()->values();
        $tanggalString = $tanggal->toDateString();

        $absensiPerSiswa = AbsensiSiswa::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->whereDate('tanggal', $tanggalString)
            ->whereIn('kelas_id', $kelasIds)
            ->whereIn('siswa_id', $siswaIds)
            ->latest('id')
            ->get(['id', 'siswa_id', 'status_kehadiran'])
            ->unique('siswa_id')
            ->keyBy('siswa_id');
        $presensiPerSiswa = PresensiKegiatanIbadah::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('kegiatan_ibadah_id', $kegiatanIbadahId)
            ->whereDate('tanggal', $tanggalString)
            ->whereIn('kelas_id', $kelasIds)
            ->whereIn('siswa_id', $siswaIds)
            ->pluck('id', 'siswa_id');
        $berhalanganPerSiswa = PresensiBerhalanganIbadah::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('kegiatan_ibadah_id', $kegiatanIbadahId)
            ->whereDate('tanggal', $tanggalString)
            ->whereIn('kelas_id', $kelasIds)
            ->whereIn('siswa_id', $siswaIds)
            ->pluck('id', 'siswa_id');

        $statusPerSiswa = $anggotaKelas->mapWithKeys(function (AnggotaKelas $anggota) use ($absensiPerSiswa, $presensiPerSiswa, $berhalanganPerSiswa, $khususLakiLaki) {
            $absensi = $absensiPerSiswa->get($anggota->siswa_id);
            $statusKehadiran = $absensi?->status_kehadiran ?: 'alfa';

            if ($statusKehadiran !== 'hadir') {
                $status = self::STATUS_TIDAK_HADIR;
            } elseif ($khususLakiLaki && $anggota->siswa?->jenis_kelamin === 'P') {
                $status = self::STATUS_TIDAK_WAJIB;
            } elseif ($presensiPerSiswa->has($anggota->siswa_id)) {
                $status = self::STATUS_SUDAH;
            } elseif ($berhalanganPerSiswa->has($anggota->siswa_id)) {
                $status = self::STATUS_BERHALANGAN;
            } else {
                $status = self::STATUS_BELUM;
            }

            return [
                (int) $anggota->siswa_id => [
                    'anggota_kelas_id' => (int) $anggota->id,
                    'kelas_id' => (int) $anggota->kelas_id,
                    'siswa_id' => (int) $anggota->siswa_id,
                    'status' => $status,
                    'status_label' => $this->labelStatus($status),
                    'status_kehadiran' => $statusKehadiran,
                    'status_kehadiran_label' => $this->labelKehadiran($statusKehadiran, (bool) $absensi),
                    'kehadiran_tercatat' => (bool) $absensi,
                ],
            ];
        });

        $ringkasanPerKelas = $daftarKelas->mapWithKeys(function ($kelas) use ($statusPerSiswa) {
            $statusKelas = $statusPerSiswa->where('kelas_id', (int) $kelas->id);
            $total = $statusKelas->count();
            $sudah = $statusKelas->where('status', self::STATUS_SUDAH)->count();
            $belum = $statusKelas->where('status', self::STATUS_BELUM)->count();
            $berhalangan = $statusKelas->where('status', self::STATUS_BERHALANGAN)->count();
            $tidakHadir = $statusKelas->where('status', self::STATUS_TIDAK_HADIR)->count();
            $tidakWajib = $statusKelas->where('status', self::STATUS_TIDAK_WAJIB)->count();
            $wajib = $sudah + $belum;

            return [
                (int) $kelas->id => [
                    'total' => $total,
                    'hadir' => $total - $tidakHadir,
                    'tidak_hadir' => $tidakHadir,
                    'berhalangan' => $berhalangan,
                    'tidak_wajib' => $tidakWajib,
                    'wajib' => $wajib,
                    'sudah' => $sudah,
                    'belum' => $belum,
                    'persentase' => $wajib > 0 ? (int) round(($sudah / $wajib) * 100) : 0,
                ],
            ];
        });

        return [
            'status_per_siswa' => $statusPerSiswa,
            'ringkasan_per_kelas' => $ringkasanPerKelas,
        ];
    }

    public static function ringkasanKosong(): array
    {
        return [
            'total' => 0,
            'hadir' => 0,
            'tidak_hadir' => 0,
            'berhalangan' => 0,
            'tidak_wajib' => 0,
            'wajib' => 0,
            'sudah' => 0,
            'belum' => 0,
            'persentase' => 0,
        ];
    }

    private function labelStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_SUDAH => 'Sudah salat',
            self::STATUS_BELUM => 'Belum salat',
            self::STATUS_BERHALANGAN => 'Berhalangan',
            self::STATUS_TIDAK_HADIR => 'Tidak hadir sekolah',
            self::STATUS_TIDAK_WAJIB => 'Tidak wajib (pulang)',
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
}
