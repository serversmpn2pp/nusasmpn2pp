<?php

namespace App\Services\Absensi;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\RiwayatPerubahanAbsensiSiswa;
use App\Services\Pembinaan\ProsesPoinKeterlambatanService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KoreksiPresensiSiswaService
{
    public function __construct(private readonly ProsesPoinKeterlambatanService $prosesPoinKeterlambatan) {}

    public function koreksiHariIniTerbatas(Pengguna $pengguna): bool
    {
        return $pengguna->memilikiIzin('absensi.koreksi_hari_ini')
            && ! $pengguna->memilikiIzin('absensi.koreksi');
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
    ): array {
        $tanggal = Carbon::parse($tanggal)->toDateString();
        $terbatas = $this->koreksiHariIniTerbatas($pengguna);

        if (! $pengguna->memilikiIzin(['absensi.koreksi', 'absensi.koreksi_hari_ini'])) {
            return ['dapat' => false, 'alasan' => 'Akun ini tidak memiliki kewenangan koreksi presensi siswa.', 'terbatas_hari_ini' => false];
        }

        if (! $pengguna->dapatMengaksesKelasSebagaiWali($anggotaKelas->kelas_id)) {
            return ['dapat' => false, 'alasan' => 'Data siswa berada di luar cakupan kelas yang dapat Anda akses.', 'terbatas_hari_ini' => $terbatas];
        }

        if ($terbatas && $tanggal !== now()->toDateString()) {
            return ['dapat' => false, 'alasan' => 'Guru PL hanya dapat mengoreksi presensi siswa pada hari berjalan.', 'terbatas_hari_ini' => true];
        }

        $absensi ??= $this->ambilAbsensi($tanggal, $anggotaKelas);
        if ($terbatas && $absensi?->sumber === 'scan') {
            return ['dapat' => false, 'alasan' => 'Catatan hasil scan hanya dapat dikoreksi oleh petugas dengan kewenangan penuh.', 'terbatas_hari_ini' => true];
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

        $absensi = DB::transaction(function () use ($pengguna, $anggotaKelas, $data, $tanggal) {
            $pengaturan = $this->ambilPengaturan($tanggal);
            $status = $data['status_kehadiran'];
            $jamMasuk = $status === 'hadir' ? ($data['jam_masuk'] ?? null) : null;
            $jamPulang = $status === 'hadir' ? ($data['jam_pulang'] ?? null) : null;
            $statusMasuk = null;
            $statusPulang = null;
            $menitTerlambat = 0;
            $menitPulangCepat = 0;

            if ($status === 'hadir') {
                [$statusMasuk, $menitTerlambat] = $this->hitungStatusMasuk($jamMasuk, $pengaturan);
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
            $statusSebelum = $absensi->exists ? $absensi->status_kehadiran : null;
            $catatan = filled($data['catatan'] ?? null) ? trim($data['catatan']) : null;

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
            ])->save();

            RiwayatPerubahanAbsensiSiswa::create([
                'absensi_siswa_id' => $absensi->id,
                'siswa_id' => $anggotaKelas->siswa_id,
                'tanggal' => $tanggal,
                'status_sebelum' => $statusSebelum,
                'status_sesudah' => $status,
                'sumber' => 'koreksi_manual',
                'catatan' => $catatan,
                'dibuat_oleh_pengguna_id' => $pengguna->id,
            ]);

            return $absensi->refresh();
        });

        $this->prosesPoinKeterlambatan->sinkronkanAbsensi($absensi, $pengguna->id);

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

    private function hitungStatusMasuk(?string $jam, ?PengaturanAbsensi $pengaturan): array
    {
        if (! $jam) {
            return [null, 0];
        }
        if (! $pengaturan) {
            return ['manual', 0];
        }
        $terlambat = max(0, $this->menit($jam) - $this->menit($pengaturan->formatJam($pengaturan->jam_masuk)));

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
