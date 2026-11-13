<?php

namespace App\Services\Absensi;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\LogScanAbsensi;
use App\Models\Pengguna;
use App\Models\RiwayatPerubahanAbsensiSiswa;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class RincianPresensiHarianSiswaService
{
    public function __construct(private readonly KoreksiPresensiSiswaService $koreksi) {}

    public function detail(Pengguna $pengguna, AnggotaKelas $anggota, string $tanggal): array
    {
        abort_unless($pengguna->aktif && ! $pengguna->akunSiswa() && ! $pengguna->akunOrangTua(), 403);
        $this->koreksi->pastikanTanggalDiizinkan($pengguna, $tanggal);
        abort_unless($pengguna->dapatMengaksesKelasSebagaiWali($anggota->kelas_id), 403);

        $absensi = AbsensiSiswa::query()
            ->where('siswa_id', $anggota->siswa_id)
            ->where('tahun_pelajaran_id', $anggota->tahun_pelajaran_id)
            ->where('kelas_id', $anggota->kelas_id)
            ->whereDate('tanggal', $tanggal)
            ->first();
        $this->muatBukti(new Collection($absensi ? [$absensi] : []), $tanggal, denganRiwayat: true);
        $riwayat = $absensi ? $this->riwayatSiswa($absensi) : collect();

        return [
            'absensi' => $absensi,
            'akses' => $this->koreksi->evaluasiAkses($pengguna, $anggota, $tanggal, $absensi, true),
            'riwayat' => $riwayat->map(fn (RiwayatPerubahanAbsensiSiswa $item) => [
                'id' => (int) $item->id,
                'status_sebelum' => $item->status_sebelum,
                'status_sesudah' => $item->status_sesudah,
                'sumber' => $item->sumber,
                'sumber_label' => $this->labelSumber($item->sumber),
                'catatan' => $item->catatan,
                'dibuat_oleh' => $item->dibuatOleh?->nama ?? $item->dibuatOleh?->username,
                'dibuat_pada' => $item->created_at?->toIso8601String(),
                'dibuat_pada_label' => $this->labelWaktu($item),
            ])->values(),
        ];
    }

    public function muatBukti(Collection $absensi, string $tanggal, bool $denganRiwayat = false): void
    {
        $absensi->load([
            'logScanAbsensi' => fn ($query) => $query
                ->whereDate('tanggal', $tanggal)->where('berhasil', true)
                ->whereIn('jenis_scan', ['masuk', 'pulang'])
                ->select('id', 'absensi_siswa_id', 'siswa_id', 'jenis_scan', 'waktu_scan'),
            'riwayatPerubahan' => fn ($query) => $query
                ->whereDate('tanggal', $tanggal)
                ->whereIntegerInRaw('siswa_id', $absensi->pluck('siswa_id')->unique()->all())
                ->when($denganRiwayat,
                    fn ($query) => $query->with('dibuatOleh:id,nama,username')->latest('id'),
                    fn ($query) => $query->where('status_sesudah', 'hadir')->whereIn('sumber', ['koreksi_manual', 'guru_piket'])
                        ->select('absensi_siswa_id', 'siswa_id', 'sumber', 'status_sesudah')),
        ]);
    }

    public function sumberJam(?AbsensiSiswa $absensi): array
    {
        $hasil = [];
        foreach (['masuk', 'pulang'] as $jenis) {
            $sumber = $absensi ? $this->sumberPada($absensi, $jenis) : null;
            $hasil['sumber_'.$jenis] = $sumber;
            $hasil['sumber_'.$jenis.'_label'] = match ($sumber) {
                'scan' => 'Scanner',
                'manual' => 'Input manual',
                'tidak_diketahui' => 'Sumber belum diketahui',
                default => null,
            };
        }

        return $hasil;
    }

    private function sumberPada(AbsensiSiswa $absensi, string $jenis): ?string
    {
        $jam = $absensi->{'jam_'.$jenis};
        if (! $jam) {
            return null;
        }
        if ($jenis === 'masuk' && ($absensi->menungguVerifikasiScan() || $absensi->alasan_hadir_manual === AbsensiSiswa::GANGGUAN_SCAN_TERVERIFIKASI)) {
            return 'manual';
        }

        // Tanda scan harus cocok dengan jenis dan jam yang masih tercatat, bukan sumber terakhir satu hari.
        $jam = strlen($jam) === 5 ? $jam.':00' : substr($jam, 0, 8);
        if ($absensi->logScanAbsensi->contains(fn (LogScanAbsensi $log) => (int) $log->siswa_id === (int) $absensi->siswa_id
            && $log->jenis_scan === $jenis && $log->waktu_scan->format('H:i:s') === $jam)) {
            return 'scan';
        }

        $pernahDicatat = $this->riwayatSiswa($absensi)->contains(fn (RiwayatPerubahanAbsensiSiswa $item) => $item->status_sesudah === 'hadir'
            && ($item->sumber === 'koreksi_manual' || ($jenis === 'masuk' && $item->sumber === 'guru_piket')));

        return $absensi->sumber === 'manual' || $pernahDicatat
            || ($jenis === 'masuk' && ($absensi->sumber === 'guru_piket' || $absensi->memilikiSanksiManual()))
            ? 'manual' : 'tidak_diketahui';
    }

    private function riwayatSiswa(AbsensiSiswa $absensi): SupportCollection
    {
        return $absensi->riwayatPerubahan->where('siswa_id', $absensi->siswa_id);
    }

    private function labelWaktu(RiwayatPerubahanAbsensiSiswa $item): ?string
    {
        if (! $item->created_at) {
            return null;
        }

        $waktu = $item->created_at->copy()->setTimezone(config('app.timezone'))->locale('id');
        $zona = match ($waktu->getTimezone()->getName()) {
            'Asia/Jakarta', 'Asia/Pontianak' => 'WIB',
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => $waktu->format('T'),
        };

        return $waktu->translatedFormat('d M Y, H:i:s').' '.$zona;
    }

    private function labelSumber(string $sumber): string
    {
        return match ($sumber) {
            'koreksi_manual' => 'Koreksi manual',
            'verifikasi_scan' => 'Verifikasi scan',
            'guru_piket' => 'Guru piket',
            'penugasan_sekolah' => 'Penugasan sekolah',
            'pembatalan_penugasan' => 'Pembatalan penugasan',
            default => str($sumber)->headline()->toString(),
        };
    }
}
