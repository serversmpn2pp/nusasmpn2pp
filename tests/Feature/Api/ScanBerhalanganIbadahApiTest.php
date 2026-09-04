<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanPendampingIbadahSiswi;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanBerhalanganIbadahApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_api_privat_memerlukan_token_dan_penugasan_pendamping_aktif(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar();
        $bukanPendamping = $this->buatGuruPerempuan(
            'Guru Perempuan Bukan Pendamping',
            '198202022012022002',
        );

        $this->getJson(route('api.v1.scan-berhalangan-ibadah.index'))
            ->assertUnauthorized();

        $this->withToken($this->token($bukanPendamping))
            ->getJson(route('api.v1.scan-berhalangan-ibadah.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($data['pendamping']))
            ->getJson(route('api.v1.scan-berhalangan-ibadah.index'))
            ->assertOk();
    }

    public function test_dashboard_privat_tidak_mengirim_daftar_identitas_dan_hanya_muncul_di_menu_pendamping(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar();
        $bukanPendamping = $this->buatGuruPerempuan(
            'Guru Tanpa Menu Privat',
            '198303032013032003',
        );

        $this->withToken($this->token($data['pendamping']))
            ->getJson(route('api.v1.scan-berhalangan-ibadah.index'))
            ->assertOk()
            ->assertJsonPath('data.mode_privat', true)
            ->assertJsonPath('data.scan_dibuka', true)
            ->assertJsonPath('data.status_jadwal.kode', 'aktif')
            ->assertJsonPath('data.jumlah_hari_ini', 0)
            ->assertJsonPath('data.cakupan_kelas.0.id', $data['kelas']->id)
            ->assertJsonPath('data.batas_hari_konfirmasi', 7)
            ->assertJsonMissingPath('data.presensi_terbaru')
            ->assertJsonMissing(['nama_lengkap' => $data['siswi']->nama_lengkap]);

        $this->withToken($this->token($data['pendamping']))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'scan-berhalangan-ibadah',
                'rute' => '/scan-berhalangan-ibadah',
            ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($bukanPendamping))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonMissing(['kode' => 'scan-berhalangan-ibadah']);
    }

    public function test_pendamping_memindai_siswi_dan_hasil_hanya_dikembalikan_untuk_scan_saat_ini(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar();
        $token = $this->token($data['pendamping']);
        $payload = [
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'isi_scan' => $data['siswi']->nisn,
        ];

        $this->withToken($token)
            ->postJson(route('api.v1.scan-berhalangan-ibadah.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.berhasil', true)
            ->assertJsonPath('data.baru', true)
            ->assertJsonPath('data.status', 'berhasil')
            ->assertJsonPath('data.siswa.nama_lengkap', $data['siswi']->nama_lengkap)
            ->assertJsonPath('data.siswa.kelas', $data['kelas']->nama)
            ->assertJsonPath('data.siswa.hari_ke', 1)
            ->assertJsonPath('data.jumlah_hari_ini', 1);

        Carbon::setTestNow('2026-08-10 12:10:03');
        $this->withToken($token)
            ->postJson(route('api.v1.scan-berhalangan-ibadah.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.berhasil', true)
            ->assertJsonPath('data.baru', false)
            ->assertJsonPath('data.status', 'sudah_tercatat')
            ->assertJsonPath('data.jumlah_hari_ini', 1);

        $this->withToken($token)
            ->getJson(route('api.v1.scan-berhalangan-ibadah.index'))
            ->assertOk()
            ->assertJsonPath('data.jumlah_hari_ini', 1)
            ->assertJsonMissingPath('data.presensi_terbaru')
            ->assertJsonMissing(['nama_lengkap' => $data['siswi']->nama_lengkap]);

        $this->assertDatabaseCount('periode_berhalangan_ibadah', 1);
        $this->assertDatabaseCount('presensi_berhalangan_ibadah', 1);
        $this->assertDatabaseCount('log_scan_berhalangan_ibadah', 2);
    }

    public function test_siswa_laki_laki_ditolak_tanpa_membuat_presensi_privat(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar();
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Laki-laki Uji API',
            'nis' => '26002',
            'nisn' => '0131201151',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 2,
            'status_keanggotaan' => 'aktif',
        ]);

        $this->withToken($this->token($data['pendamping']))
            ->postJson(route('api.v1.scan-berhalangan-ibadah.store'), [
                'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
                'isi_scan' => $siswa->nisn,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('data.berhasil', false)
            ->assertJsonPath('data.status', 'bukan_siswi');

        $this->assertDatabaseCount('presensi_berhalangan_ibadah', 0);
    }

    public function test_sholat_jumat_tidak_tersedia_pada_scan_berhalangan_privat(): void
    {
        Carbon::setTestNow('2026-08-10 12:10:00');
        $data = $this->dataDasar();
        $kegiatanJumat = KegiatanIbadah::create([
            'kode' => KegiatanIbadah::KODE_SHOLAT_JUMAT,
            'nama' => 'Sholat Jumat',
            'aktif' => true,
        ]);
        $data['jadwal']->update(['kegiatan_ibadah_id' => $kegiatanJumat->id]);
        $token = $this->token($data['pendamping']);

        $this->withToken($token)
            ->getJson(route('api.v1.scan-berhalangan-ibadah.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data.jadwal')
            ->assertJsonPath('data.scan_dibuka', false);

        $this->withToken($token)
            ->postJson(route('api.v1.scan-berhalangan-ibadah.store'), [
                'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
                'isi_scan' => $data['siswi']->nisn,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('data.berhasil', false)
            ->assertJsonPath('data.status', 'kegiatan_tidak_memerlukan_berhalangan');

        $this->assertDatabaseCount('presensi_berhalangan_ibadah', 0);
    }

    private function dataDasar(): array
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
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswi = Siswa::create([
            'nama_lengkap' => 'Siswi Berhalangan Mobile',
            'nis' => '26001',
            'nisn' => '0131201150',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswi->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        $pendamping = $this->buatGuruPerempuan(
            'Guru Pendamping Privat Mobile',
            '198101012011012001',
        );
        $penugasan = PenugasanPendampingIbadahSiswi::create([
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $pendamping->pegawai_id,
            'semua_kelas' => false,
            'aktif' => true,
            'ditugaskan_oleh_pengguna_id' => Pengguna::where('username', 'administrator')->value('id'),
        ]);
        $penugasan->kelas()->sync([$kelas->id]);
        $kegiatan = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();
        $jadwal = JadwalKegiatanIbadah::create([
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => 'senin',
            'urutan_hari' => 1,
            'jam_scan_mulai' => '11:30',
            'jam_pelaksanaan' => '12:00',
            'jam_scan_selesai' => '13:00',
            'aktif' => true,
        ]);

        return compact('tahun', 'kelas', 'siswi', 'pendamping', 'kegiatan', 'jadwal');
    }

    private function buatGuruPerempuan(string $nama, string $nip): Pengguna
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_kelamin' => 'P',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'pegawai')->value('id'));

        return $pengguna;
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Pendamping Privat', ['mobile'])->plainTextToken;
    }
}
