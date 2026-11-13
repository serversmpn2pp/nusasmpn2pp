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

    public function dapatMemverifikasiScan(Pengguna $pengguna): bool
    {
        return $this->memilikiKewenanganKoreksi($pengguna) && $this->koreksiSeluruhKelas($pengguna)
            && $pengguna->memilikiIzin('absensi.koreksi');
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

        if ($terbatas || ! $this->koreksiSeluruhKelas($pengguna)) {
            if (! $absensiSudahDimuat) {
                $absensi ??= $this->ambilAbsensi($tanggal, $anggotaKelas);
            }
            if ($absensi?->menungguVerifikasiScan() || $absensi?->alasan_hadir_manual === AbsensiSiswa::GANGGUAN_SCAN_TERVERIFIKASI) {
                return ['dapat' => false, 'alasan' => 'Hanya Admin atau Wakil Kesiswaan yang dapat memverifikasi kasus ini.', 'terbatas_hari_ini' => $terbatas];
            }
            if ($terbatas && $absensi?->sumber === 'scan') {
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
            $hasilVerifikasi = $data['hasil_verifikasi_scan'] ?? null;
            $logMasuk = $absensi->exists ? $absensi->logScanAbsensi()
                ->where('siswa_id', $absensi->siswa_id)->whereDate('tanggal', $tanggal)
                ->where('jenis_scan', 'masuk')->where('berhasil', true)->orderBy('waktu_scan')->first() : null;
            if ($hasilVerifikasi !== null) {
                abort_unless($this->dapatMemverifikasiScan($pengguna), 403);
                if (! $absensi->menungguVerifikasiScan() || ! isset(AbsensiSiswa::HASIL_VERIFIKASI_SCAN[$hasilVerifikasi])) {
                    throw ValidationException::withMessages(['hasil_verifikasi_scan' => 'Kasus ini tidak sedang menunggu hasil verifikasi.']);
                }
                if ($hasilVerifikasi === 'scan_ditemukan' && ! $logMasuk) {
                    throw ValidationException::withMessages(['hasil_verifikasi_scan' => 'Tidak ada log scan masuk berhasil untuk siswa pada tanggal ini.']);
                }
                if ($logMasuk && in_array($hasilVerifikasi, ['tidak_membawa_kartu', 'kartu_hilang'], true)) {
                    throw ValidationException::withMessages(['hasil_verifikasi_scan' => 'Sanksi kartu tidak dapat diterapkan karena ada log scan masuk yang berhasil.']);
                }
                // Verification must not overwrite checkout or take a scanner timestamp from the form.
                $data['status_kehadiran'] = 'hadir';
                if ($hasilVerifikasi === 'scan_ditemukan') {
                    $data['jam_masuk'] = $logMasuk->waktu_scan->format('H:i:s');
                }
                $data['jam_pulang'] = $absensi->jam_pulang;
            } elseif ($absensi->menungguVerifikasiScan()) {
                throw ValidationException::withMessages(['hasil_verifikasi_scan' => 'Pilih hasil verifikasi. Kasus tetap menunggu jika bukti belum cukup.']);
            }

            $this->pastikanDataValid($data, $akses['terbatas_hari_ini'], (bool) $absensi->penugasan_presensi_id);
            $statusSebelum = $absensi->exists ? $absensi->status_kehadiran : null;
            $status = $data['status_kehadiran'];
            $jamMasuk = $status === 'hadir' ? ($data['jam_masuk'] ?? null) : null;
            $jamPulang = $status === 'hadir' ? ($data['jam_pulang'] ?? null) : null;
            $catatan = filled($data['catatan'] ?? null) ? trim($data['catatan']) : null;
            $menunggu = $status === 'hadir' && ($data['alasan_hadir_manual'] ?? null) === AbsensiSiswa::SCAN_BERMASALAH && $hasilVerifikasi === null;
            if (($menunggu || $hasilVerifikasi !== null) && mb_strlen($catatan ?? '') < 3) {
                throw ValidationException::withMessages(['catatan' => 'Catatan pemeriksaan scan wajib diisi, minimal 3 karakter.']);
            }
            if ($menunggu && $absensi->penugasan_presensi_id) {
                throw ValidationException::withMessages(['alasan_hadir_manual' => 'Siswa sedang dalam penugasan sekolah.']);
            }
            $gangguan = $status === 'hadir' && ($hasilVerifikasi === 'gangguan_terkonfirmasi'
                || (! $menunggu && $absensi->alasan_hadir_manual === AbsensiSiswa::GANGGUAN_SCAN_TERVERIFIKASI));
            if ($menunggu && ($logMasuk || $absensi->memilikiSanksiManual()
                || $absensi->alasan_hadir_manual === AbsensiSiswa::GANGGUAN_SCAN_TERVERIFIKASI)) {
                abort_unless($this->dapatMemverifikasiScan($pengguna), 403);
            }
            $adaScanMasuk = $logMasuk || ($absensi->sumber === 'scan' && $absensi->jam_masuk);
            $manualTanpaScan = ! $absensi->penugasan_presensi_id && $status === 'hadir' && ! $menunggu && ! $gangguan
                && (in_array($hasilVerifikasi, ['tidak_membawa_kartu', 'kartu_hilang'], true) || ! $adaScanMasuk || $absensi->memilikiSanksiManual());
            $jamPulang = $menunggu ? ($absensi->jam_pulang ?? $jamPulang) : $jamPulang;
            $this->pastikanWaktuPulang($jamMasuk, $jamPulang);
            if (($menunggu || $hasilVerifikasi !== null) && Carbon::parse($tanggal.' '.$jamMasuk)->isFuture()) {
                throw ValidationException::withMessages(['jam_masuk' => 'Jam datang tidak boleh melewati waktu sekarang.']);
            }
            [$statusMasuk, $menitTerlambat] = $menunggu ? ['manual', 0] : $this->hitungStatusMasuk($jamMasuk, $pengaturan, $ketat);
            [$statusPulang, $menitPulangCepat] = $this->hitungStatusPulang($jamPulang, $pengaturan, $anggotaKelas->siswa?->jenis_kelamin);
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
                'sumber' => $hasilVerifikasi === 'scan_ditemukan' ? 'scan' : 'manual',
                'catatan' => $catatan,
                'alasan_hadir_manual' => match (true) {
                    $menunggu => AbsensiSiswa::SCAN_BERMASALAH,
                    $gangguan => AbsensiSiswa::GANGGUAN_SCAN_TERVERIFIKASI,
                    $manualTanpaScan => AbsensiSiswa::normalisasiAlasanHadirManual($hasilVerifikasi ?? $data['alasan_hadir_manual'] ?? $absensi->alasan_hadir_manual),
                    default => null,
                },
                'sanksi_manual_menit' => $manualTanpaScan ? AbsensiSiswa::SANKSI_MANUAL_MENIT : 0,
            ]);
            if ($manualTanpaScan) {
                app(AturanPresensiManualService::class)->bekukan($absensi);
            } elseif ($menunggu) {
                $absensi->aturan_poin_hadir_manual = null;
            }
            $absensi->save();

            if (! $menunggu && ($ketat || $adaPoin || $absensi->memilikiSanksiManual())) {
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
                'sumber' => $hasilVerifikasi ? 'verifikasi_scan' : 'koreksi_manual',
                'catatan' => $hasilVerifikasi
                    ? AbsensiSiswa::HASIL_VERIFIKASI_SCAN[$hasilVerifikasi].".\n".$absensi->catatanKehadiran()
                    : $absensi->catatanKehadiran(),
                'dibuat_oleh_pengguna_id' => $pengguna->id,
            ]);

            if ($absensi->memilikiSanksiManual()) {
                app(NotifikasiAbsensiSiswaService::class)->jadwalkanHadirManual($absensi);
            }

            return $absensi;
        });

        return $absensi->refresh();
    }

    private function pastikanDataValid(array $data, bool $catatanWajib, bool $penugasan = false): void
    {
        if ($catatanWajib && blank($data['catatan'] ?? null)) {
            throw ValidationException::withMessages(['catatan' => 'Catatan koreksi wajib diisi oleh Guru PL.']);
        }

        if (! $penugasan && $data['status_kehadiran'] === 'hadir' && blank($data['jam_masuk'] ?? null)) {
            throw ValidationException::withMessages(['jam_masuk' => 'Jam masuk wajib diisi jika status kehadiran adalah hadir.']);
        }
    }

    private function pastikanWaktuPulang(?string $jamMasuk, ?string $jamPulang): void
    {
        if ($jamMasuk && $jamPulang && $this->menit($jamPulang) < $this->menit($jamMasuk)) {
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
