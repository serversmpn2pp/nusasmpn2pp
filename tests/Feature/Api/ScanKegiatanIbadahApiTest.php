<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\PresensiKegiatanIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanKegiatanIbadahApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_api_scan_ibadah_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.scan-kegiatan-ibadah.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Pegawai Tanpa Izin Scan Ibadah',
            'username' => 'tanpa.izin.scan.ibadah',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.scan-kegiatan-ibadah.index'))
            ->assertForbidden();
    }

    public function test_dashboard_memilih_jadwal_aktif_dan_menyediakan_presensi_terbaru(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar('senin');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        PresensiKegiatanIbadah::create([
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'siswa_id' => $data['siswa']->id,
            'tanggal' => '2026-08-10',
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota']->id,
            'dipindai_oleh_pengguna_id' => $administrator->id,
            'waktu_scan' => '12:05:00',
            'sumber' => 'kamera',
        ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.scan-kegiatan-ibadah.index'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertJsonPath('data.scan_dibuka', true)
            ->assertJsonPath('data.status_jadwal.kode', 'aktif')
            ->assertJsonPath('data.jadwal_dipilih_id', $data['jadwal']->id)
            ->assertJsonPath('data.jadwal.0.kegiatan', $data['kegiatan']->nama)
            ->assertJsonPath('data.jadwal.0.rentang_scan', '11:30 - 13:00')
            ->assertJsonPath('data.jumlah_hari_ini', 1)
            ->assertJsonPath('data.presensi_terbaru.0.nama_lengkap', $data['siswa']->nama_lengkap)
            ->assertJsonPath('data.presensi_terbaru.0.kelas', $data['kelas']->nama);
    }

    public function test_scan_mobile_mencatat_presensi_dan_scan_ganda_tidak_menduplikasi(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar('senin');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);
        $payload = [
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'isi_scan' => $data['siswa']->nisn,
        ];

        $this->withToken($token)
            ->postJson(route('api.v1.scan-kegiatan-ibadah.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.berhasil', true)
            ->assertJsonPath('data.baru', true)
            ->assertJsonPath('data.status', 'berhasil')
            ->assertJsonPath('data.siswa.nama_lengkap', $data['siswa']->nama_lengkap)
            ->assertJsonPath('data.siswa.kelas', $data['kelas']->nama)
            ->assertJsonPath('data.jumlah_hari_ini', 1);

        Carbon::setTestNow('2026-08-10 12:10:02');
        $this->withToken($token)
            ->postJson(route('api.v1.scan-kegiatan-ibadah.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.berhasil', true)
            ->assertJsonPath('data.baru', false)
            ->assertJsonPath('data.status', 'sudah_tercatat')
            ->assertJsonPath('data.jumlah_hari_ini', 1);

        $this->assertDatabaseCount('presensi_kegiatan_ibadah', 1);
        $this->assertDatabaseCount('log_scan_kegiatan_ibadah', 2);
    }

    public function test_qr_tidak_valid_dikembalikan_sebagai_hasil_scan_yang_dapat_ditampilkan_mobile(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar('senin');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.scan-kegiatan-ibadah.store'), [
                'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
                'isi_scan' => 'BUKAN-NISN',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'QR tidak terbaca sebagai NISN. Arahkan kamera kembali ke QR kartu pelajar.')
            ->assertJsonPath('data.berhasil', false)
            ->assertJsonPath('data.status', 'format_tidak_valid')
            ->assertJsonPath('data.jumlah_hari_ini', 0);

        $this->assertDatabaseCount('presensi_kegiatan_ibadah', 0);
        $this->assertDatabaseCount('log_scan_kegiatan_ibadah', 1);
    }

    private function dataDasar(string $hari): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Scan Mobile',
            'nis' => '26001',
            'nisn' => '0131201150',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        $kegiatan = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();
        $jadwal = JadwalKegiatanIbadah::create([
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => $hari,
            'urutan_hari' => JadwalKegiatanIbadah::DAFTAR_HARI[$hari]['urutan'],
            'jam_scan_mulai' => '11:30',
            'jam_pelaksanaan' => '12:00',
            'jam_scan_selesai' => '13:00',
            'aktif' => true,
            'keterangan' => 'Mushalla sekolah',
        ]);

        return compact('tahun', 'kelas', 'siswa', 'anggota', 'kegiatan', 'jadwal');
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Scan Ibadah', ['mobile'])->plainTextToken;
    }
}
