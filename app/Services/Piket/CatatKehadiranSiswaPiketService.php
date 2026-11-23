<?php

namespace App\Services\Piket;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\RiwayatPerubahanAbsensiSiswa;
use App\Services\Absensi\HadirManualTanpaKartuService;
use App\Services\Notifikasi\NotifikasiAbsensiSiswaService;
use App\Services\Pembinaan\AturanPresensiManualService;
use App\Services\Pembinaan\ProsesPoinKeterlambatanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatatKehadiranSiswaPiketService
{
    public function __construct(
        private readonly GuruPiketHariIniService $piket,
        private readonly AksesCatatanPresensiPiketService $akses,
    ) {}

    public function catat(Pengguna $pengguna, AnggotaKelas $anggotaKelas, string $status, string $catatan, ?string $alasan = null, ?string $jamDatang = null, ?string $statusMasukManual = null): AbsensiSiswa
    {
        $tahunPelajaran = $this->piket->tahunPelajaranAktif();
        $this->piket->pastikanSedangPiket($pengguna, $tahunPelajaran);

        abort_unless(
            (int) $anggotaKelas->tahun_pelajaran_id === (int) $tahunPelajaran->id
                && $anggotaKelas->status_keanggotaan === 'aktif'
                && $anggotaKelas->siswa?->aktif && $anggotaKelas->kelas?->aktif,
            404,
        );

        return DB::transaction(function () use ($pengguna, $anggotaKelas, $tahunPelajaran, $status, $catatan, $alasan, $jamDatang, $statusMasukManual) {
            $anggotaKelas = AnggotaKelas::whereKey($anggotaKelas->id)->lockForUpdate()->firstOrFail();
            abort_unless($anggotaKelas->status_keanggotaan === 'aktif' && $anggotaKelas->siswa?->aktif && $anggotaKelas->kelas?->aktif, 404);
            $this->piket->pastikanSedangPiket($pengguna, $tahunPelajaran);
            $tanggal = now()->toDateString();
            $absensi = AbsensiSiswa::query()
                ->whereDate('tanggal', $tanggal)
                ->where('siswa_id', $anggotaKelas->siswa_id)
                ->lockForUpdate()
                ->first();

            $akses = $this->akses->rincian($absensi, $anggotaKelas);
            abort_unless($akses['dapat_dicatat'], 403, $akses['alasan_terkunci']);
            if ($akses['hanya_scan_pulang'] && ($status !== 'hadir' || $alasan !== AbsensiSiswa::SCAN_BERMASALAH)) {
                throw ValidationException::withMessages(['alasan_hadir_manual' => 'Scan pulang sudah tercatat. Catat gangguan scan masuk untuk diverifikasi petugas.']);
            }
            if ($status !== 'hadir' && $absensi?->jam_pulang) {
                throw ValidationException::withMessages(['status_kehadiran' => 'Jam pulang sudah tercatat. Perubahan menjadi Sakit atau Izin harus melalui Admin atau Wakil Kesiswaan.']);
            }
            $menunggu = $status === 'hadir' && $alasan === AbsensiSiswa::SCAN_BERMASALAH;
            if ($menunggu && ($absensi?->memilikiSanksiManual() || $absensi?->penugasan_presensi_id)) {
                throw ValidationException::withMessages(['alasan_hadir_manual' => 'Laporkan koreksi catatan ini kepada Admin atau Wakil Kesiswaan.']);
            }
            if ($menunggu && (! $jamDatang || mb_strlen(trim($catatan)) < 3)) {
                throw ValidationException::withMessages(['jam_masuk' => 'Jam datang dan keterangan gangguan scan wajib diisi.']);
            }

            $statusSebelum = $absensi?->status_kehadiran;
            $jamMasuk = $status === 'hadir' && ($menunggu || $statusMasukManual === null) ? $jamDatang : null;
            if ($jamMasuk && ((strlen($jamMasuk) === 5 ? $jamMasuk.':00' : $jamMasuk) > now()->format('H:i:s')
                || ($absensi?->jam_pulang && substr($jamMasuk, 0, 5) > substr($absensi->jam_pulang, 0, 5)))) {
                throw ValidationException::withMessages(['jam_masuk' => 'Jam datang tidak boleh melewati jam sekarang atau jam pulang.']);
            }
            $jadwal = PengaturanAbsensi::where('hari', $this->piket->kodeHariIni())->where('aktif', true)->first();
            $masuk = $status === 'hadir' && ! $menunggu
                ? app(HadirManualTanpaKartuService::class)->dataMasuk($statusMasukManual, $alasan ?? $absensi?->alasan_hadir_manual, $jamMasuk, $jadwal)
                : [
                    'jam_masuk' => $jamMasuk,
                    'status_masuk' => $menunggu ? 'manual' : null,
                    'menit_terlambat' => 0,
                    'alasan_hadir_manual' => $menunggu ? AbsensiSiswa::SCAN_BERMASALAH : null,
                    'sanksi_manual_menit' => 0,
                ];
            $absensi ??= new AbsensiSiswa([
                'tanggal' => $tanggal,
                'siswa_id' => $anggotaKelas->siswa_id,
            ]);
            $absensi->fill([
                'tahun_pelajaran_id' => $tahunPelajaran->id,
                'kelas_id' => $anggotaKelas->kelas_id,
                'anggota_kelas_id' => $anggotaKelas->id,
                ...$masuk,
                'jam_pulang' => $absensi->jam_pulang,
                'status_pulang' => $absensi->status_pulang,
                'menit_pulang_cepat' => (int) $absensi->menit_pulang_cepat,
                'status_kehadiran' => $status,
                'sumber' => 'guru_piket',
                'catatan' => trim($catatan),
            ]);
            if ($absensi->memilikiSanksiManual()) {
                app(AturanPresensiManualService::class)->bekukan($absensi);
            }
            $absensi->save();

            app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($absensi->refresh(), $pengguna->id);
            $absensi->refresh();

            RiwayatPerubahanAbsensiSiswa::create([
                'absensi_siswa_id' => $absensi->id,
                'siswa_id' => $anggotaKelas->siswa_id,
                'tanggal' => $tanggal,
                'status_sebelum' => $statusSebelum,
                'status_sesudah' => $status,
                'sumber' => 'guru_piket',
                'catatan' => $absensi->catatanKehadiran(),
                'dibuat_oleh_pengguna_id' => $pengguna->id,
            ]);

            if ($absensi->memilikiSanksiManual()) {
                app(NotifikasiAbsensiSiswaService::class)->jadwalkanHadirManual($absensi);
            }

            return $absensi;
        });
    }
}
