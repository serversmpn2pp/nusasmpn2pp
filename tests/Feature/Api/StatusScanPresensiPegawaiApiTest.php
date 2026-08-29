<?php

namespace Tests\Feature\Api;

use App\Models\AbsensiPegawai;
use App\Models\LogScanAbsensiPegawai;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusScanPresensiPegawaiApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_api_status_scan_memerlukan_token_dan_izin_kehadiran(): void
    {
        $this->getJson(route('api.v1.status-scan-presensi-pegawai.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Pegawai Tanpa Izin Status Scan',
            'username' => 'pegawai.tanpa.izin.status.scan',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.status-scan-presensi-pegawai.index'))
            ->assertForbidden();
    }

    public function test_administrator_melihat_status_hari_ini_dan_aktivitas_terbaru(): void
    {
        Carbon::setTestNow('2026-08-27 06:35:00');
        $jadwal = $this->jadwal('Jadwal Semua Pegawai', 'semua');
        $guru = $this->pegawai('Guru Scan Mobile', 'Guru', '198601012026081001');
        $this->pegawai('Pegawai Belum Scan', 'Tenaga Kependidikan', '198601012026081002');
        $absensi = $this->absensi($guru, $jadwal, '06:31:10');
        $this->log($guru, $absensi, true, 'berhasil_masuk', '06:31:10');
        $this->log($guru, $absensi, false, 'duplikat_cepat', '06:31:15');
        LogScanAbsensiPegawai::create([
            'isi_scan' => 'KARTU-TIDAK-VALID',
            'waktu_scan' => '2026-08-27 06:32:00',
            'tanggal' => '2026-08-27',
            'berhasil' => false,
            'status_scan' => 'format_tidak_valid',
            'pesan' => 'Scan pegawai tidak terbaca. Silakan scan ulang.',
            'scanner_id' => 'P1',
        ]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'scan-presensi-pegawai',
                'label' => 'Status Scan Presensi Pegawai',
                'status' => 'tersedia',
                'rute' => '/status-scan-presensi-pegawai',
            ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.status-scan-presensi-pegawai.index'))
            ->assertOk()
            ->assertJsonPath('data.tanggal', '2026-08-27')
            ->assertJsonPath('data.jadwal.fase', 'scan_masuk')
            ->assertJsonPath('data.jadwal.jumlah', 1)
            ->assertJsonPath('data.jadwal.items.0.nama', 'Jadwal Semua Pegawai')
            ->assertJsonPath('data.ringkasan.jumlah_pegawai', 2)
            ->assertJsonPath('data.ringkasan.sudah_masuk', 1)
            ->assertJsonPath('data.ringkasan.belum_scan_masuk', 1)
            ->assertJsonPath('data.ringkasan.scan_berhasil', 1)
            ->assertJsonPath('data.ringkasan.sudah_tercatat', 1)
            ->assertJsonPath('data.ringkasan.perlu_perhatian', 1)
            ->assertJsonCount(3, 'data.aktivitas')
            ->assertJsonPath('data.aktivitas.0.status', 'format_tidak_valid')
            ->assertJsonPath('data.aktivitas.1.pegawai.nama', 'Guru Scan Mobile')
            ->assertJsonPath('data.aktivitas.1.presensi.nama_jadwal', 'Jadwal Semua Pegawai')
            ->assertJsonMissingPath('data.aktivitas.0.isi_scan');
    }

    public function test_filter_jenis_status_dan_pencarian_diterapkan_pada_log(): void
    {
        Carbon::setTestNow('2026-08-27 07:05:00');
        $jadwal = $this->jadwal('Jadwal Guru', 'jenis_pegawai', jenisPegawai: 'Guru');
        $guru = $this->pegawai('Antonius Guru Mobile', 'Guru', '198601012026081003');
        $tendik = $this->pegawai('Tendik Mobile', 'Tenaga Kependidikan', '198601012026081004');
        $absensiGuru = $this->absensi($guru, $jadwal, '07:05:00', terlambat: 5);
        $this->log($guru, $absensiGuru, true, 'berhasil_masuk', '07:05:00');
        $this->log($guru, $absensiGuru, false, 'duplikat_cepat', '07:05:05');
        $this->log($tendik, null, false, 'jadwal_absensi_tidak_ada', '07:06:00');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.status-scan-presensi-pegawai.index', [
                'jenis_pegawai' => 'Guru',
                'status' => 'sudah_tercatat',
                'cari' => 'Antonius',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.aktivitas')
            ->assertJsonPath('data.aktivitas.0.status', 'duplikat_cepat')
            ->assertJsonPath('data.aktivitas.0.pegawai.jenis_pegawai', 'Guru')
            ->assertJsonPath('data.filter.jenis_pegawai', 'Guru')
            ->assertJsonPath('data.filter.status', 'sudah_tercatat')
            ->assertJsonPath('data.ringkasan.jumlah_pegawai', 1)
            ->assertJsonPath('data.ringkasan.terlambat', 1)
            ->assertJsonPath('data.jadwal.jumlah', 1);
    }

    private function pegawai(string $nama, string $jenis, string $nip): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => $jenis,
            'jabatan_utama' => $jenis === 'Guru' ? 'Guru Mata Pelajaran' : 'Staf Tata Usaha',
            'aktif' => true,
        ]);
    }

    private function jadwal(
        string $nama,
        string $cakupan,
        ?string $jenisPegawai = null,
    ): PengaturanAbsensiPegawai {
        return PengaturanAbsensiPegawai::create([
            'nama_jadwal' => $nama,
            'cakupan' => $cakupan,
            'jenis_pegawai' => $jenisPegawai,
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
    }

    private function absensi(
        Pegawai $pegawai,
        PengaturanAbsensiPegawai $jadwal,
        string $jam,
        int $terlambat = 0,
    ): AbsensiPegawai {
        return AbsensiPegawai::create([
            'tanggal' => '2026-08-27',
            'pegawai_id' => $pegawai->id,
            'pengaturan_absensi_pegawai_id' => $jadwal->id,
            'jam_masuk' => $jam,
            'status_masuk' => $terlambat > 0 ? 'terlambat' : 'tepat_waktu',
            'menit_terlambat' => $terlambat,
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);
    }

    private function log(
        Pegawai $pegawai,
        ?AbsensiPegawai $absensi,
        bool $berhasil,
        string $status,
        string $jam,
    ): LogScanAbsensiPegawai {
        return LogScanAbsensiPegawai::create([
            'absensi_pegawai_id' => $absensi?->id,
            'pegawai_id' => $pegawai->id,
            'isi_scan' => $pegawai->nip,
            'nip' => $pegawai->nip,
            'scanner_id' => 'P1',
            'jenis_scan' => 'masuk',
            'waktu_scan' => "2026-08-27 {$jam}",
            'tanggal' => '2026-08-27',
            'berhasil' => $berhasil,
            'status_scan' => $status,
            'pesan' => $berhasil
                ? 'Presensi masuk berhasil dicatat.'
                : 'Presensi masuk belum dapat dicatat.',
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
