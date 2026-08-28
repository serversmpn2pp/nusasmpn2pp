<?php

namespace Tests\Feature\Api;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LogScanAbsensi;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusScanPresensiSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_api_status_scan_memerlukan_token_dan_izin_kehadiran(): void
    {
        $this->getJson(route('api.v1.status-scan-presensi-siswa.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Pegawai Tanpa Izin Scan',
            'username' => 'pegawai.tanpa.izin.scan',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.status-scan-presensi-siswa.index'))
            ->assertForbidden();
    }

    public function test_administrator_melihat_status_hari_ini_dan_aktivitas_terbaru(): void
    {
        Carbon::setTestNow('2026-08-27 06:35:00');
        [$tahun, $kelas, $siswa, $anggota] = $this->siapkanSekolah();
        $siswaBelumScan = Siswa::create([
            'nama_lengkap' => 'Siswa Belum Scan',
            'nis' => '20002',
            'nisn' => '0022334466',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswaBelumScan->id,
            'nomor_absen' => 2,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);
        $absensi = AbsensiSiswa::create([
            'tanggal' => '2026-08-27',
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $siswa->id,
            'jam_masuk' => '06:31:10',
            'status_masuk' => 'tepat_waktu',
            'menit_terlambat' => 0,
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);
        $this->log($siswa, $absensi, true, 'berhasil_masuk', '06:31:10');
        $this->log($siswa, $absensi, false, 'duplikat_cepat', '06:31:15');
        LogScanAbsensi::create([
            'isi_scan' => 'KARTU-TIDAK-VALID',
            'waktu_scan' => '2026-08-27 06:32:00',
            'tanggal' => '2026-08-27',
            'berhasil' => false,
            'status_scan' => 'format_tidak_valid',
            'pesan' => 'Scan tidak terbaca. Silakan scan ulang.',
            'scanner_id' => 'S1',
        ]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'scan-presensi-siswa',
                'label' => 'Status Scan Presensi Siswa',
                'status' => 'tersedia',
                'rute' => '/status-scan-presensi-siswa',
            ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.status-scan-presensi-siswa.index'))
            ->assertOk()
            ->assertJsonPath('data.tanggal', '2026-08-27')
            ->assertJsonPath('data.jadwal.fase', 'scan_masuk')
            ->assertJsonPath('data.ringkasan.jumlah_siswa', 2)
            ->assertJsonPath('data.ringkasan.sudah_masuk', 1)
            ->assertJsonPath('data.ringkasan.belum_scan_masuk', 1)
            ->assertJsonPath('data.ringkasan.scan_berhasil', 1)
            ->assertJsonPath('data.ringkasan.sudah_tercatat', 1)
            ->assertJsonPath('data.ringkasan.perlu_perhatian', 1)
            ->assertJsonCount(3, 'data.aktivitas')
            ->assertJsonPath('data.aktivitas.0.status', 'format_tidak_valid')
            ->assertJsonPath('data.aktivitas.1.siswa.nama', 'Siswa Scan Mobile')
            ->assertJsonMissingPath('data.aktivitas.0.isi_scan');
    }

    public function test_filter_kelas_status_dan_pencarian_diterapkan_pada_log(): void
    {
        Carbon::setTestNow('2026-08-27 06:35:00');
        [$tahun, $kelas, $siswa, $anggota] = $this->siapkanSekolah();
        $absensi = AbsensiSiswa::create([
            'tanggal' => '2026-08-27',
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $siswa->id,
            'jam_masuk' => '07:05:00',
            'status_masuk' => 'terlambat',
            'menit_terlambat' => 5,
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);
        $this->log($siswa, $absensi, true, 'berhasil_masuk', '07:05:00');
        $this->log($siswa, $absensi, false, 'duplikat_cepat', '07:05:05');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.status-scan-presensi-siswa.index', [
                'kelas_id' => $kelas->id,
                'status' => 'sudah_tercatat',
                'cari' => 'Scan Mobile',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.aktivitas')
            ->assertJsonPath('data.aktivitas.0.status', 'duplikat_cepat')
            ->assertJsonPath('data.aktivitas.0.siswa.kelas', 'VII.A Mobile')
            ->assertJsonPath('data.filter.kelas_id', $kelas->id)
            ->assertJsonPath('data.filter.status', 'sudah_tercatat');
    }

    private function siapkanSekolah(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A Mobile',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Scan Mobile',
            'nis' => '20001',
            'nisn' => '0011223344',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);
        PengaturanAbsensi::create([
            'hari' => 'kamis',
            'urutan_hari' => 4,
            'jam_scan_masuk_mulai' => '06:00',
            'jam_masuk' => '07:00',
            'jam_scan_masuk_selesai' => '07:30',
            'jam_scan_pulang_mulai' => '14:00',
            'jam_pulang' => '14:10',
            'jam_scan_pulang_selesai' => '15:00',
            'aktif' => true,
        ]);

        return [$tahun, $kelas, $siswa, $anggota];
    }

    private function log(
        Siswa $siswa,
        AbsensiSiswa $absensi,
        bool $berhasil,
        string $status,
        string $jam,
    ): LogScanAbsensi {
        return LogScanAbsensi::create([
            'absensi_siswa_id' => $absensi->id,
            'siswa_id' => $siswa->id,
            'isi_scan' => $siswa->nisn,
            'nisn' => $siswa->nisn,
            'scanner_id' => 'S1',
            'jenis_scan' => 'masuk',
            'waktu_scan' => "2026-08-27 {$jam}",
            'tanggal' => '2026-08-27',
            'berhasil' => $berhasil,
            'status_scan' => $status,
            'pesan' => $berhasil
                ? 'Presensi masuk berhasil dicatat.'
                : 'Presensi masuk sudah tercatat.',
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
