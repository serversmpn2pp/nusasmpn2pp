<?php

namespace App\Services\Piket;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Services\Absensi\RincianPresensiHarianSiswaService;
use Illuminate\Database\Eloquent\Collection;

class AksesCatatanPresensiPiketService
{
    public function __construct(private readonly RincianPresensiHarianSiswaService $rincian) {}

    public function muatBukti(Collection $absensi, string $tanggal): void
    {
        if ($absensi->isNotEmpty()) {
            $this->rincian->muatBukti($absensi, $tanggal, denganRiwayat: true);
        }
    }

    public function rincian(?AbsensiSiswa $absensi, ?AnggotaKelas $anggota = null): array
    {
        if ($absensi?->exists
            && (! $absensi->relationLoaded('logScanAbsensi') || ! $absensi->relationLoaded('riwayatPerubahan'))) {
            $this->muatBukti(new Collection([$absensi]), $absensi->tanggal->toDateString());
        }
        $sumber = $this->rincian->sumberJam($absensi);
        $riwayat = $absensi?->riwayatPerubahan?->where('siswa_id', $absensi->siswa_id)->sortByDesc('id') ?? collect();
        $terakhir = $riwayat->first();
        $hanyaPulang = $absensi && ! $absensi->jam_masuk && (bool) $absensi->jam_pulang;
        $alasan = null;

        if ($absensi) {
            $scanMasuk = $absensi->logScanAbsensi->contains(fn ($log) => (int) $log->siswa_id === (int) $absensi->siswa_id
                && $log->jenis_scan === 'masuk');
            $manual = in_array($absensi->sumber, ['manual', 'guru_piket'], true)
                || $absensi->memilikiSanksiManual()
                || $riwayat->contains(fn ($row) => in_array($row->sumber, ['koreksi_manual', 'guru_piket'], true));

            $alasan = match (true) {
                ! $absensi->tanggal?->isToday() => 'Guru piket hanya dapat mengubah catatan pada hari bertugas.',
                $anggota && ((int) $absensi->tahun_pelajaran_id !== (int) $anggota->tahun_pelajaran_id
                    || (int) $absensi->kelas_id !== (int) $anggota->kelas_id
                    || (int) $absensi->siswa_id !== (int) $anggota->siswa_id) => 'Catatan berasal dari penempatan kelas berbeda. Hubungi Admin.',
                (bool) $absensi->penugasan_presensi_id => 'Siswa memiliki penugasan sekolah. Hubungi Admin atau Wakil Kesiswaan.',
                $absensi->menungguVerifikasiScan() => 'Scan masuk sedang menunggu verifikasi Admin atau Wakil Kesiswaan.',
                $absensi->alasan_hadir_manual === AbsensiSiswa::GANGGUAN_SCAN_TERVERIFIKASI
                    || $riwayat->contains('sumber', 'verifikasi_scan') => 'Catatan memiliki keputusan verifikasi scan. Hubungi Admin atau Wakil Kesiswaan.',
                $scanMasuk => 'Ada bukti scan masuk berhasil. Koreksi hasil scanner harus melalui petugas koreksi yang berwenang.',
                ! $manual && ! $hanyaPulang => $absensi->sumber === 'scan'
                    ? 'Catatan masuk berasal dari scanner atau belum memiliki bukti input manual. Hubungi Admin.'
                    : 'Sumber pencatatan belum dapat dipastikan. Hubungi Admin.',
                default => null,
            };
        }

        return [
            ...$sumber,
            'dapat_dicatat' => $alasan === null,
            'alasan_terkunci' => $alasan,
            'hanya_scan_pulang' => (bool) $hanyaPulang,
            'pencatat_terakhir' => $terakhir ? [
                'nama' => $terakhir->dibuatOleh?->nama ?? 'Akun tidak tercatat',
                'waktu' => $terakhir->created_at?->toIso8601String(),
                'waktu_label' => $terakhir->created_at?->copy()->setTimezone(config('app.timezone'))->format('d/m/Y H:i:s'),
                'jenis' => match ($terakhir->sumber) {
                    'guru_piket' => 'Guru piket',
                    'koreksi_manual' => 'Koreksi manual',
                    'verifikasi_scan' => 'Verifikasi scan',
                    default => str($terakhir->sumber)->headline()->toString(),
                },
            ] : null,
        ];
    }
}
