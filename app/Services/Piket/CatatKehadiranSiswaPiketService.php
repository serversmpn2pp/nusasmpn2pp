<?php

namespace App\Services\Piket;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\RiwayatPerubahanAbsensiSiswa;
use App\Services\Absensi\HitungKeterlambatanSiswaService;
use App\Services\Notifikasi\NotifikasiAbsensiSiswaService;
use App\Services\Pembinaan\ProsesPoinKeterlambatanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatatKehadiranSiswaPiketService
{
    public function __construct(private readonly GuruPiketHariIniService $piket) {}

    public function catat(Pengguna $pengguna, AnggotaKelas $anggotaKelas, string $status, string $catatan, ?string $alasan = null): AbsensiSiswa
    {
        $tahunPelajaran = $this->piket->tahunPelajaranAktif();
        $this->piket->pastikanSedangPiket($pengguna, $tahunPelajaran);

        abort_unless(
            (int) $anggotaKelas->tahun_pelajaran_id === (int) $tahunPelajaran->id
                && $anggotaKelas->status_keanggotaan === 'aktif',
            404,
        );

        return DB::transaction(function () use ($pengguna, $anggotaKelas, $tahunPelajaran, $status, $catatan, $alasan) {
            AnggotaKelas::whereKey($anggotaKelas->id)->lockForUpdate()->firstOrFail();
            $tanggal = now()->toDateString();
            $absensi = AbsensiSiswa::query()
                ->whereDate('tanggal', $tanggal)
                ->where('siswa_id', $anggotaKelas->siswa_id)
                ->lockForUpdate()
                ->first();

            if ($absensi?->jam_masuk && $absensi->sumber !== 'guru_piket') {
                throw ValidationException::withMessages([
                    'status_kehadiran' => 'Siswa sudah melakukan scan masuk sehingga tidak dapat dikoreksi dari menu guru piket.',
                ]);
            }

            if ($absensi && $absensi->sumber !== 'guru_piket') {
                throw ValidationException::withMessages([
                    'status_kehadiran' => 'Kehadiran siswa sudah dicatat oleh petugas lain. Gunakan fitur koreksi presensi yang berwenang untuk mengubahnya.',
                ]);
            }

            $statusSebelum = $absensi?->status_kehadiran;
            $jamMasuk = $status === 'hadir' ? ($absensi?->jam_masuk ?: now()->format('H:i:s')) : null;
            $jadwal = PengaturanAbsensi::where('hari', $this->piket->kodeHariIni())->where('aktif', true)->first();
            $menit = $status === 'hadir'
                ? max(AbsensiSiswa::SANKSI_MANUAL_MENIT, $jadwal
                    ? app(HitungKeterlambatanSiswaService::class)->menit($jamMasuk, $jadwal->jam_masuk, true) : 0)
                : 0;
            $absensi ??= new AbsensiSiswa([
                'tanggal' => $tanggal,
                'siswa_id' => $anggotaKelas->siswa_id,
            ]);
            $absensi->fill([
                'tahun_pelajaran_id' => $tahunPelajaran->id,
                'kelas_id' => $anggotaKelas->kelas_id,
                'anggota_kelas_id' => $anggotaKelas->id,
                'jam_masuk' => $jamMasuk,
                'status_masuk' => $status === 'hadir' ? 'terlambat' : null,
                'menit_terlambat' => $menit,
                'alasan_hadir_manual' => $status === 'hadir'
                    ? AbsensiSiswa::normalisasiAlasanHadirManual($alasan ?? $absensi->alasan_hadir_manual) : null,
                'sanksi_manual_menit' => $status === 'hadir' ? AbsensiSiswa::SANKSI_MANUAL_MENIT : 0,
                'jam_pulang' => null,
                'status_pulang' => null,
                'menit_pulang_cepat' => 0,
                'status_kehadiran' => $status,
                'sumber' => 'guru_piket',
                'catatan' => trim($catatan),
            ])->save();

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
