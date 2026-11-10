<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalKegiatanIbadah;
use App\Models\JadwalPiketGuru;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
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

    public function test_menu_dan_endpoint_scan_tersedia_untuk_admin_tanpa_profil_pegawai_dan_guru_pl(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar('senin');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->assertNull($administrator->pegawai_id);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment(['kode' => 'scan-ibadah-siswa']);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.scan-kegiatan-ibadah.index'))
            ->assertOk();
        $this->postJson(route('api.v1.scan-kegiatan-ibadah.store'), [
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'isi_scan' => $data['siswa']->nisn,
        ])->assertOk()->assertJsonPath('data.berhasil', true);
        $this->assertDatabaseHas('presensi_kegiatan_ibadah', ['siswa_id' => $data['siswa']->id, 'dipindai_oleh_pengguna_id' => $administrator->id]);

        $this->app['auth']->forgetGuards();
        $guruPl = $this->guruPl();

        $this->withToken($this->token($guruPl))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'scan-ibadah-siswa',
                'rute' => '/scan-kegiatan-ibadah',
            ]);

        $this->withToken($this->token($guruPl))
            ->getJson(route('api.v1.scan-kegiatan-ibadah.index'))
            ->assertOk();
    }

    public function test_guru_pai_dapat_menu_dashboard_dan_scan_mobile_di_luar_hari_piket(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar('senin');
        $guruPai = $this->guruPiket($data['tahun'], 'selasa');
        $pai = MataPelajaran::create(['kode' => 'PAI7', 'nama' => 'Pendidikan Agama Islam dan Budi Pekerti', 'kelompok' => 'Agama', 'aktif' => true]);
        $penugasan = GuruMataPelajaran::create(['tahun_pelajaran_id' => $data['tahun']->id, 'kelas_id' => $data['kelas']->id,
            'mata_pelajaran_id' => $pai->id, 'pegawai_id' => $guruPai->pegawai_id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
        $token = $this->token($guruPai);
        $payload = ['jadwal_kegiatan_ibadah_id' => $data['jadwal']->id, 'isi_scan' => $data['siswa']->nisn];
        $this->withToken($token)->getJson(route('api.v1.menu'))->assertOk()->assertJsonFragment(['kode' => 'scan-ibadah-siswa'])
            ->assertJsonMissing(['kode' => 'scan-berhalangan-ibadah']);
        $this->getJson(route('api.v1.scan-kegiatan-ibadah.index'))->assertOk();
        $this->postJson(route('api.v1.scan-kegiatan-ibadah.store'), $payload)->assertOk()->assertJsonPath('data.berhasil', true);
        $this->assertDatabaseHas('presensi_kegiatan_ibadah', ['siswa_id' => $data['siswa']->id, 'dipindai_oleh_pengguna_id' => $guruPai->id]);
        $penugasan->update(['aktif' => false]);
        $this->getJson(route('api.v1.menu'))->assertOk()->assertJsonMissing(['kode' => 'scan-ibadah-siswa']);
        $this->getJson(route('api.v1.scan-kegiatan-ibadah.index'))->assertForbidden();
        $this->postJson(route('api.v1.scan-kegiatan-ibadah.store'), $payload)->assertForbidden();
        $this->assertDatabaseCount('log_scan_kegiatan_ibadah', 1);
    }

    public function test_menu_scan_guru_piket_hanya_muncul_pada_hari_tugasnya(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar('senin');
        $guruPiket = $this->guruPiket($data['tahun'], 'senin');
        $token = $this->token($guruPiket);

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment(['kode' => 'scan-ibadah-siswa']);

        Carbon::setTestNow('2026-08-11 12:10:00');

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonMissing(['kode' => 'scan-ibadah-siswa']);

        $this->withToken($token)
            ->getJson(route('api.v1.scan-kegiatan-ibadah.index'))
            ->assertForbidden();
    }

    public function test_admin_tetap_mengikuti_jendela_jam_scan_ibadah(): void
    {
        Carbon::setTestNow('2026-08-10 13:10:00');
        $data = $this->dataDasar('senin');
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->withToken($this->token($admin))->getJson(route('api.v1.scan-kegiatan-ibadah.index'))
            ->assertOk()->assertJsonPath('data.scan_dibuka', false);
        $this->postJson(route('api.v1.scan-kegiatan-ibadah.store'), [
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id, 'isi_scan' => $data['siswa']->nisn,
        ])->assertUnprocessable()->assertJsonPath('data.status', 'di_luar_jadwal');
        $this->assertDatabaseCount('presensi_kegiatan_ibadah', 0);
    }

    public function test_dashboard_memilih_jadwal_aktif_dan_menyediakan_presensi_terbaru(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar('senin');
        $guruPl = $this->guruPl();
        PresensiKegiatanIbadah::create([
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'siswa_id' => $data['siswa']->id,
            'tanggal' => '2026-08-10',
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota']->id,
            'dipindai_oleh_pengguna_id' => $guruPl->id,
            'waktu_scan' => '12:05:00',
            'sumber' => 'kamera',
        ]);

        $this->withToken($this->token($guruPl))
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
        $guruPl = $this->guruPl();
        $token = $this->token($guruPl);
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

    public function test_scan_sholat_jumat_menolak_kartu_siswi(): void
    {
        Carbon::setTestNow('2026-08-14 12:10:00');
        $data = $this->dataDasar('jumat');
        $guruPl = $this->guruPl();
        $kegiatanJumat = KegiatanIbadah::create([
            'kode' => KegiatanIbadah::KODE_SHOLAT_JUMAT,
            'nama' => 'Sholat Jumat',
            'aktif' => true,
        ]);
        $data['siswa']->update(['jenis_kelamin' => 'P']);
        $data['jadwal']->update(['kegiatan_ibadah_id' => $kegiatanJumat->id]);

        $this->withToken($this->token($guruPl))
            ->postJson(route('api.v1.scan-kegiatan-ibadah.store'), [
                'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
                'isi_scan' => $data['siswa']->nisn,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('data.berhasil', false)
            ->assertJsonPath('data.status', 'peserta_tidak_wajib')
            ->assertJsonPath('data.siswa.nama_lengkap', $data['siswa']->nama_lengkap)
            ->assertJsonPath('data.jumlah_hari_ini', 0);

        $this->assertDatabaseCount('presensi_kegiatan_ibadah', 0);
        $this->assertDatabaseCount('log_scan_kegiatan_ibadah', 1);
    }

    public function test_qr_tidak_valid_dikembalikan_sebagai_hasil_scan_yang_dapat_ditampilkan_mobile(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar('senin');
        $guruPl = $this->guruPl();

        $this->withToken($this->token($guruPl))
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

    private function guruPl(): Pengguna
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru PL Scan Ibadah',
            'nip' => 'PL-SCAN-IBADAH',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach(
            Peran::whereIn('kode', ['pegawai', 'guru_pl'])->pluck('id'),
        );

        return $pengguna;
    }

    private function guruPiket(TahunPelajaran $tahunPelajaran, string $hari): Pengguna
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Piket Scan Ibadah',
            'nip' => 'PIKET-SCAN-IBADAH',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach(
            Peran::whereIn('kode', ['pegawai', 'guru_mapel'])->pluck('id'),
        );
        JadwalPiketGuru::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'pegawai_id' => $pegawai->id,
            'hari' => $hari,
            'aktif' => true,
        ]);

        return $pengguna;
    }
}
