<?php

namespace App\Services\Piket;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Pengguna;
use App\Models\RiwayatPerubahanAbsensiSiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatatKehadiranSiswaPiketService
{
    public function __construct(private readonly GuruPiketHariIniService $piket) {}

    public function catat(Pengguna $pengguna, AnggotaKelas $anggotaKelas, string $status, string $catatan): AbsensiSiswa
    {
        $tahunPelajaran = $this->piket->tahunPelajaranAktif();
        $this->piket->pastikanSedangPiket($pengguna, $tahunPelajaran);

        abort_unless(
            (int) $anggotaKelas->tahun_pelajaran_id === (int) $tahunPelajaran->id
                && $anggotaKelas->status_keanggotaan === 'aktif',
            404,
        );

        return DB::transaction(function () use ($pengguna, $anggotaKelas, $tahunPelajaran, $status, $catatan) {
            $tanggal = now()->toDateString();
            $absensi = AbsensiSiswa::query()
                ->whereDate('tanggal', $tanggal)
                ->where('siswa_id', $anggotaKelas->siswa_id)
                ->lockForUpdate()
                ->first();

            if ($absensi?->jam_masuk || $absensi?->status_kehadiran === 'hadir') {
                throw ValidationException::withMessages([
                    'status_kehadiran' => 'Siswa sudah melakukan scan masuk sehingga tidak dapat dicatat sakit atau izin.',
                ]);
            }

            if ($absensi && $absensi->sumber !== 'guru_piket') {
                throw ValidationException::withMessages([
                    'status_kehadiran' => 'Kehadiran siswa sudah dicatat oleh petugas lain. Gunakan fitur koreksi presensi yang berwenang untuk mengubahnya.',
                ]);
            }

            $statusSebelum = $absensi?->status_kehadiran;
            $absensi ??= new AbsensiSiswa([
                'tanggal' => $tanggal,
                'siswa_id' => $anggotaKelas->siswa_id,
            ]);
            $absensi->fill([
                'tahun_pelajaran_id' => $tahunPelajaran->id,
                'kelas_id' => $anggotaKelas->kelas_id,
                'anggota_kelas_id' => $anggotaKelas->id,
                'jam_masuk' => null,
                'status_masuk' => null,
                'menit_terlambat' => 0,
                'jam_pulang' => null,
                'status_pulang' => null,
                'menit_pulang_cepat' => 0,
                'status_kehadiran' => $status,
                'sumber' => 'guru_piket',
                'catatan' => trim($catatan),
            ])->save();

            RiwayatPerubahanAbsensiSiswa::create([
                'absensi_siswa_id' => $absensi->id,
                'siswa_id' => $anggotaKelas->siswa_id,
                'tanggal' => $tanggal,
                'status_sebelum' => $statusSebelum,
                'status_sesudah' => $status,
                'sumber' => 'guru_piket',
                'catatan' => trim($catatan),
                'dibuat_oleh_pengguna_id' => $pengguna->id,
            ]);

            return $absensi->refresh();
        });
    }
}
