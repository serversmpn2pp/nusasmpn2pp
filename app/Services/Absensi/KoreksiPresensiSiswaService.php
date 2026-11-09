<?php

namespace App\Services\Absensi;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\RiwayatPerubahanAbsensiSiswa;
use App\Services\Notifikasi\NotifikasiAbsensiSiswaService;
use App\Services\Pembinaan\AturanPresensiManualService;
use App\Services\Pembinaan\PengaturanPoinKeterlambatanService;
use App\Services\Pembinaan\PoinPresensiOtomatisService;
use App\Services\Pembinaan\ProsesPoinKeterlambatanService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KoreksiPresensiSiswaService
{
    public function __construct(private readonly ProsesPoinKeterlambatanService $prosesPoinKeterlambatan) {}

    public function memilikiKewenanganKoreksi(Pengguna $pengguna): bool
    {
        $pengguna->loadMissing('daftarPeran.izin');
        if (! $pengguna->aktif || $pengguna->akunSiswa() || $pengguna->akunOrangTua()
            || (! $pengguna->administrator() && ! $pengguna->akunPegawai())) {
            return false;
        }

        return $this->koreksiPenuhDiizinkan($pengguna)
            || ($pengguna->memilikiPeran('guru_pl') && $pengguna->memilikiIzin('absensi.koreksi_hari_ini'));
    }

    private function koreksiPenuhDiizinkan(Pengguna $pengguna): bool
    {
        return $pengguna->memilikiIzin('absensi.koreksi') && (
            $this->koreksiSeluruhKelas($pengguna)
            || ($pengguna->memilikiPeran('wali_kelas') && $this->kelasWaliIds($pengguna) !== [])
        );
    }

    private function koreksiSeluruhKelas(Pengguna $pengguna): bool
    {
        return $pengguna->administrator() || $pengguna->memilikiPeran('wakil_pimpinan_kesiswaan');
    }

    private function kelasWaliIds(Pengguna $pengguna): array
    {
        $pengguna->loadMissing('pegawai.kelasSebagaiWali');

        return $pengguna->pegawai?->kelasSebagaiWali->modelKeys() ?? [];
    }

    public function koreksiHariIniTerbatas(Pengguna $pengguna): bool
    {
        return $this->memilikiKewenanganKoreksi($pengguna) && ! $this->koreksiPenuhDiizinkan($pengguna);
    }

    public function ambilAbsensi(string $tanggal, AnggotaKelas $anggotaKelas): ?AbsensiSiswa
    {
        return AbsensiSiswa::query()
            ->whereDate('tanggal', Carbon::parse($tanggal)->toDateString())
            ->where('siswa_id', $anggotaKelas->siswa_id)
            ->first();
    }

    /** @return array{dapat: bool, alasan: string|null, terbatas_hari_ini: bool} */
    public function evaluasiAkses(
        Pengguna $pengguna,
        AnggotaKelas $anggotaKelas,
        string $tanggal,
        ?AbsensiSiswa $absensi = null,
        bool $absensiSudahDimuat = false,
    ): array {
        $tanggal = Carbon::parse($tanggal)->toDateString();
        $terbatas = $this->koreksiHariIniTerbatas($pengguna);

        if (! $this->memilikiKewenanganKoreksi($pengguna)) {
            return ['dapat' => false, 'alasan' => 'Akun ini tidak memiliki kewenangan koreksi presensi siswa.', 'terbatas_hari_ini' => false];
        }

        if (! $terbatas && ! $this->koreksiSeluruhKelas($pengguna)
            && ! in_array((int) $anggotaKelas->kelas_id, $this->kelasWaliIds($pengguna), true)) {
            return ['dapat' => false, 'alasan' => 'Koreksi presensi hanya diizinkan untuk kelas yang Anda wali.', 'terbatas_hari_ini' => false];
        }

        if ($terbatas && $tanggal !== now()->toDateString()) {
            return ['dapat' => false, 'alasan' => 'Guru PL hanya dapat mengoreksi presensi siswa pada hari berjalan.', 'terbatas_hari_ini' => true];
        }

        if ($terbatas) {
            if (! $absensiSudahDimuat) {
                $absensi ??= $this->ambilAbsensi($tanggal, $anggotaKelas);
            }
            if ($absensi?->sumber === 'scan') {
                return ['dapat' => false, 'alasan' => 'Catatan hasil scan hanya dapat dikoreksi oleh petugas dengan kewenangan penuh.', 'terbatas_hari_ini' => true];
            }
        }

        return ['dapat' => true, 'alasan' => null, 'terbatas_hari_ini' => $terbatas];
    }

    public function pastikanTanggalDiizinkan(Pengguna $pengguna, string $tanggal): void
    {
        abort_if(
            $this->koreksiHariIniTerbatas($pengguna)
                && Carbon::parse($tanggal)->toDateString() !== now()->toDateString(),
            403,
            'Guru PL hanya dapat mengoreksi presensi siswa pada hari berjalan.',
        );
    }

    public function koreksi(Pengguna $pengguna, AnggotaKelas $anggotaKelas, array $data): AbsensiSiswa
    {
        $tanggal = Carbon::parse($data['tanggal'])->toDateString();
        $anggotaKelas->loadMissing(['tahunPelajaran', 'kelas', 'siswa']);
        $absensiSaatIni = $this->ambilAbsensi($tanggal, $anggotaKelas);
        $akses = $this->evaluasiAkses($pengguna, $anggotaKelas, $tanggal, $absensiSaatIni);
        abort_unless($akses['dapat'], 403, $akses['alasan']);

        $this->pastikanDataValid($data, $akses['terbatas_hari_ini']);
        $ketat = app(PengaturanPoinKeterlambatanService::class)->langsungPada($anggotaKelas->tahun_pelajaran_id, $tanggal);
        $adaPoin = LaporanPembinaanSiswa::where('siswa_id', $anggotaKelas->siswa_id)
            ->where('tahun_pelajaran_id', $anggotaKelas->tahun_pelajaran_id)->whereDate('tanggal_kejadian', $tanggal)
            ->whereNotNull('kunci_presensi_otomatis')->exists();
        if (($ketat || $adaPoin) && blank($data['catatan'] ?? null)) {
            throw ValidationException::withMessages(['catatan' => 'Alasan koreksi presensi berpoin wajib diisi.']);
        }

        $absensi = DB::transaction(function () use ($pengguna, $anggotaKelas, $data, $tanggal, $ketat, $adaPoin) {
            AnggotaKelas::whereKey($anggotaKelas->id)->lockForUpdate()->firstOrFail();
            $pengaturan = $this->ambilPengaturan($tanggal);
            $status = $data['status_kehadiran'];
            $jamMasuk = $status === 'hadir' ? ($data['jam_masuk'] ?? null) : null;
            $jamPulang = $status === 'hadir' ? ($data['jam_pulang'] ?? null) : null;
            $statusMasuk = null;
            $statusPulang = null;
            $menitTerlambat = 0;
            $menitPulangCepat = 0;

            if ($status === 'hadir') {
                [$statusMasuk, $menitTerlambat] = $this->hitungStatusMasuk($jamMasuk, $pengaturan, $ketat);
                [$statusPulang, $menitPulangCepat] = $this->hitungStatusPulang(
                    $jamPulang,
                    $pengaturan,
                    $anggotaKelas->siswa?->jenis_kelamin,
                );
            }

            $absensi = AbsensiSiswa::query()
                ->whereDate('tanggal', $tanggal)
                ->where('siswa_id', $anggotaKelas->siswa_id)
                ->lockForUpdate()
                ->first() ?? new AbsensiSiswa([
                    'tanggal' => $tanggal,
                    'siswa_id' => $anggotaKelas->siswa_id,
                ]);
            $akses = $this->evaluasiAkses($pengguna, $anggotaKelas, $tanggal, $absensi, true);
            abort_unless($akses['dapat'], 403, $akses['alasan']);
            $statusSebelum = $absensi->exists ? $absensi->status_kehadiran : null;
            $catatan = filled($data['catatan'] ?? null) ? trim($data['catatan']) : null;
            $adaScanMasuk = $absensi->sumber === 'scan' || ($absensi->exists && $absensi->logScanAbsensi()
                ->where('jenis_scan', 'masuk')->where('berhasil', true)->exists());
            $manualTanpaScan = $status === 'hadir' && (! $adaScanMasuk || $absensi->memilikiSanksiManual());
            if ($manualTanpaScan) {
                $menitTerlambat = max(AbsensiSiswa::SANKSI_MANUAL_MENIT, $pengaturan
                    ? app(HitungKeterlambatanSiswaService::class)->menit($jamMasuk, $pengaturan->jam_masuk, true) : 0);
                $statusMasuk = 'terlambat';
            }

            $absensi->fill([
                'tahun_pelajaran_id' => $anggotaKelas->tahun_pelajaran_id,
                'kelas_id' => $anggotaKelas->kelas_id,
                'anggota_kelas_id' => $anggotaKelas->id,
                'jam_masuk' => $jamMasuk,
                'status_masuk' => $statusMasuk,
                'menit_terlambat' => $menitTerlambat,
                'jam_pulang' => $jamPulang,
                'status_pulang' => $statusPulang,
                'menit_pulang_cepat' => $menitPulangCepat,
                'status_kehadiran' => $status,
                'sumber' => 'manual',
                'catatan' => $catatan,
                'alasan_hadir_manual' => $manualTanpaScan
                    ? AbsensiSiswa::normalisasiAlasanHadirManual($data['alasan_hadir_manual'] ?? $absensi->alasan_hadir_manual) : null,
                'sanksi_manual_menit' => $manualTanpaScan ? AbsensiSiswa::SANKSI_MANUAL_MENIT : 0,
            ]);
            if ($manualTanpaScan) {
                app(AturanPresensiManualService::class)->bekukan($absensi);
            }
            $absensi->save();

            if ($ketat || $adaPoin || $absensi->memilikiSanksiManual()) {
                app(PoinPresensiOtomatisService::class)->sinkronkanAbsensi($absensi->refresh(), $pengguna->id);
            } else {
                $this->prosesPoinKeterlambatan->sinkronkanAbsensi($absensi->refresh(), $pengguna->id);
            }
            $absensi->refresh();

            RiwayatPerubahanAbsensiSiswa::create([
                'absensi_siswa_id' => $absensi->id,
                'siswa_id' => $anggotaKelas->siswa_id,
                'tanggal' => $tanggal,
                'status_sebelum' => $statusSebelum,
                'status_sesudah' => $status,
                'sumber' => 'koreksi_manual',
                'catatan' => $absensi->catatanKehadiran(),
                'dibuat_oleh_pengguna_id' => $pengguna->id,
            ]);

            if ($absensi->memilikiSanksiManual()) {
                app(NotifikasiAbsensiSiswaService::class)->jadwalkanHadirManual($absensi);
            }

            return $absensi;
        });

        return $absensi->refresh();
    }

    private function pastikanDataValid(array $data, bool $catatanWajib): void
    {
        if ($catatanWajib && blank($data['catatan'] ?? null)) {
            throw ValidationException::withMessages(['catatan' => 'Catatan koreksi wajib diisi oleh Guru PL.']);
        }

        if ($data['status_kehadiran'] === 'hadir' && blank($data['jam_masuk'] ?? null)) {
            throw ValidationException::withMessages(['jam_masuk' => 'Jam masuk wajib diisi jika status kehadiran adalah hadir.']);
        }

        if (filled($data['jam_masuk'] ?? null) && filled($data['jam_pulang'] ?? null)
            && $this->menit($data['jam_pulang']) < $this->menit($data['jam_masuk'])) {
            throw ValidationException::withMessages(['jam_pulang' => 'Jam pulang tidak boleh lebih awal dari jam masuk.']);
        }
    }

    private function ambilPengaturan(string $tanggal): ?PengaturanAbsensi
    {
        $hari = array_keys(PengaturanAbsensi::DAFTAR_HARI)[Carbon::parse($tanggal)->isoWeekday() - 1];

        return PengaturanAbsensi::query()->where('hari', $hari)->where('aktif', true)->first();
    }

    private function hitungStatusMasuk(?string $jam, ?PengaturanAbsensi $pengaturan, bool $ketat = false): array
    {
        if (! $jam) {
            return [null, 0];
        }
        if (! $pengaturan) {
            return ['manual', 0];
        }
        $terlambat = app(HitungKeterlambatanSiswaService::class)->menit($jam, $pengaturan->jam_masuk, $ketat);

        return [$terlambat > 0 ? 'terlambat' : 'tepat_waktu', $terlambat];
    }

    private function hitungStatusPulang(
        ?string $jam,
        ?PengaturanAbsensi $pengaturan,
        ?string $jenisKelamin,
    ): array {
        if (! $jam) {
            return [null, 0];
        }
        if (! $pengaturan) {
            return ['manual', 0];
        }
        $jadwalPulang = $pengaturan->jadwalPulangUntuk($jenisKelamin);
        $cepat = max(0, $this->menit($pengaturan->formatJam($jadwalPulang['jam_pulang'])) - $this->menit($jam));

        return [$cepat > 0 ? 'pulang_cepat' : 'normal', $cepat];
    }

    private function menit(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($hour * 60) + $minute;
    }
}
