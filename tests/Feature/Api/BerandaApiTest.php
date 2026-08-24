<?php

namespace Tests\Feature\Api;

use App\Models\AbsensiPegawai;
use App\Models\JadwalPiketGuru;
use App\Models\Kelas;
use App\Models\NotifikasiPengguna;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BerandaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 08:15:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_beranda_mobile_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.beranda'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Akun Tanpa Izin',
            'username' => 'tanpa.izin',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.beranda'))
            ->assertForbidden();
    }

    public function test_beranda_pegawai_mengembalikan_ringkasan_pribadi_dan_notifikasi_miliknya(): void
    {
        [$pengguna, $pegawai] = $this->buatAkunPegawai();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);

        AbsensiPegawai::create([
            'tanggal' => '2026-08-22',
            'pegawai_id' => $pegawai->id,
            'status_kehadiran' => 'hadir',
            'jam_masuk' => '07:05:00',
            'jam_pulang' => '15:00:00',
            'menit_terlambat' => 5,
        ]);
        AbsensiPegawai::create([
            'tanggal' => '2026-08-23',
            'pegawai_id' => $pegawai->id,
            'status_kehadiran' => 'sakit',
        ]);
        AbsensiPegawai::create([
            'tanggal' => '2026-08-24',
            'pegawai_id' => $pegawai->id,
            'status_kehadiran' => 'hadir',
            'jam_masuk' => '06:55:00',
        ]);
        JadwalPiketGuru::create([
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $pegawai->id,
            'hari' => 'senin',
            'aktif' => true,
            'keterangan' => 'Piket gerbang utama',
        ]);
        Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_id' => $pegawai->id,
            'nama' => 'VII A',
            'tingkat' => 7,
            'aktif' => true,
        ]);

        foreach (range(1, 11) as $urutan) {
            NotifikasiPengguna::create([
                'pengguna_id' => $pengguna->id,
                'jenis' => $urutan === 11 ? 'penting' : 'informasi',
                'judul' => "Notifikasi mobile {$urutan}",
                'pesan' => "Isi notifikasi {$urutan}.",
                'dibaca_pada' => $urutan === 1 ? now() : null,
            ]);
        }

        $penggunaLain = Pengguna::where('username', 'administrator')->firstOrFail();
        NotifikasiPengguna::create([
            'pengguna_id' => $penggunaLain->id,
            'jenis' => 'penting',
            'judul' => 'Notifikasi pengguna lain',
            'pesan' => 'Tidak boleh tampil di perangkat pegawai.',
        ]);

        $response = $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.beranda'))
            ->assertOk()
            ->assertJsonPath('data.salam', 'Selamat pagi')
            ->assertJsonPath('data.tanggal.hari', 'Senin')
            ->assertJsonPath('data.tahun_pelajaran.nama', '2026/2027')
            ->assertJsonPath('data.pegawai.nip', $pegawai->nip)
            ->assertJsonPath('data.presensi.hari_ini.label_status', 'Hadir')
            ->assertJsonPath('data.presensi.hari_ini.jam_masuk', '06:55')
            ->assertJsonPath('data.presensi.bulan_ini.hadir', 2)
            ->assertJsonPath('data.presensi.bulan_ini.sakit', 1)
            ->assertJsonPath('data.presensi.bulan_ini.terlambat', 1)
            ->assertJsonPath('data.piket_hari_ini.label_hari', 'Senin')
            ->assertJsonPath('data.perwalian.jumlah_kelas', 1)
            ->assertJsonPath('data.notifikasi.jumlah_belum_dibaca', 10)
            ->assertJsonCount(10, 'data.notifikasi.terbaru')
            ->assertJsonPath('data.notifikasi.terbaru.0.judul', 'Notifikasi mobile 11')
            ->assertJsonMissing(['judul' => 'Notifikasi pengguna lain'])
            ->assertJsonStructure([
                'data' => [
                    'dihasilkan_pada',
                    'tanggal' => ['iso', 'hari', 'label', 'bulan'],
                    'pegawai',
                    'presensi' => ['hari_ini', 'bulan_ini'],
                    'piket_hari_ini',
                    'perwalian',
                    'notifikasi' => ['jumlah_belum_dibaca', 'terbaru'],
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_administrator_tanpa_data_pegawai_tetap_dapat_membuka_beranda_mobile(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.beranda'))
            ->assertOk()
            ->assertJsonPath('data.pegawai', null)
            ->assertJsonPath('data.presensi', null)
            ->assertJsonPath('data.perwalian', null);
    }

    public function test_fitur_beranda_dikunci_sampai_kata_sandi_awal_diganti(): void
    {
        [$pengguna] = $this->buatAkunPegawai([
            'wajib_ganti_kata_sandi' => true,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.beranda'))
            ->assertStatus(428)
            ->assertJsonPath('wajib_ganti_kata_sandi', true);
    }

    private function buatAkunPegawai(array $atributPengguna = []): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Mobile Uji',
            'nip' => '198808242026081001',
            'email' => 'guru.mobile@example.test',
            'jenis_pegawai' => 'Guru',
            'jabatan_utama' => 'Guru Mata Pelajaran',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create(array_merge([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ], $atributPengguna));
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'pegawai')->firstOrFail());

        return [$pengguna, $pegawai];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
