<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PegawaiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_pegawai_memerlukan_token_dan_membedakan_izin_baca_dengan_kelola(): void
    {
        $this->getJson(route('api.v1.pegawai.index'))->assertUnauthorized();

        $pembaca = $this->penggunaDenganIzin('pegawai.lihat');
        $pegawai = $this->pegawai('Guru Pembaca Mobile', '198801012026011001');
        $token = $this->token($pembaca);

        $this->withToken($token)
            ->getJson(route('api.v1.pegawai.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($token)
            ->getJson(route('api.v1.pegawai.show', $pegawai))
            ->assertOk()
            ->assertJsonPath('data.nama', 'Guru Pembaca Mobile');
        $this->withToken($token)
            ->postJson(route('api.v1.pegawai.store'), [])
            ->assertForbidden();
    }

    public function test_daftar_pegawai_dapat_dicari_difilter_dan_memuat_ringkasan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->pegawai('Antonius Guru Mobile', '198801012026011002', true, 'Guru');
        $this->pegawai('Petugas Tata Usaha Mobile', '198801012026011003', false, 'Tenaga Kependidikan');

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pegawai.index', [
                'cari' => 'Antonius',
                'status' => 'aktif',
                'jenis_pegawai' => 'Guru',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.nama', 'Antonius Guru Mobile')
            ->assertJsonPath('data.items.0.nip', '198801012026011002')
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.nonaktif', 1)
            ->assertJsonPath('data.filter.jenis_pegawai', 'Guru')
            ->assertJsonFragment(['Tenaga Kependidikan']);
    }

    public function test_detail_pegawai_memuat_identitas_akun_dan_ringkasan_penugasan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Detail Mobile',
            'nip' => '198801012026011004',
            'nuptk' => '1234567890123456',
            'nik' => '1374010101880001',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Padang Panjang',
            'tanggal_lahir' => '1988-01-01',
            'email' => 'guru.detail@example.test',
            'no_hp' => '081234567890',
            'jenis_pegawai' => 'Guru',
            'jabatan_utama' => 'Guru Mata Pelajaran',
            'pendidikan_terakhir' => 'S1',
            'aktif' => true,
        ]);
        Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true]);
        Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.PEGAWAI.API',
            'tingkat' => 8,
            'wali_kelas_id' => $pegawai->id,
            'aktif' => true,
        ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pegawai.show', $pegawai))
            ->assertOk()
            ->assertJsonPath('data.nik', '1374010101880001')
            ->assertJsonPath('data.tanggal_lahir', '1988-01-01')
            ->assertJsonPath('data.akun.tersedia', true)
            ->assertJsonPath('data.akun.username', '198801012026011004')
            ->assertJsonPath('data.ringkasan_penugasan.kelas_wali_aktif', 1)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);
    }

    public function test_administrator_dapat_menambah_pegawai_dengan_validasi_identitas_unik(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.pegawai.store'), [
                'nama_lengkap' => 'Pegawai Baru Android',
                'nip' => '198801012026011005',
                'jenis_kelamin' => 'P',
                'email' => 'pegawai.android@example.test',
                'jenis_pegawai' => 'Guru',
                'status_kepegawaian' => 'PNS',
                'tahun_lulus' => 2012,
                'aktif' => true,
            ])
            ->assertCreated();

        $pegawai = Pegawai::where('nip', '198801012026011005')->firstOrFail();
        $response->assertJsonPath('data.id', $pegawai->id);
        $this->assertSame('Pegawai Baru Android', $pegawai->nama_lengkap);
        $this->assertTrue($pegawai->aktif);

        $this->withToken($token)
            ->postJson(route('api.v1.pegawai.store'), [
                'nama_lengkap' => 'Duplikat Android',
                'nip' => '198801012026011005',
                'email' => 'bukan-email',
                'tahun_lulus' => 1800,
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nip', 'email', 'tahun_lulus']);
    }

    public function test_perubahan_nip_pegawai_menyinkronkan_username_akun(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = $this->pegawai('Guru Sinkron Mobile', '198801012026011006');
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($administrator))
            ->patchJson(route('api.v1.pegawai.update', $pegawai), [
                'nama_lengkap' => 'Guru Sinkron Mobile Revisi',
                'nip' => '198801012026011099',
                'jenis_kelamin' => 'L',
                'jenis_pegawai' => 'Guru',
                'jabatan_utama' => 'Wali Kelas',
                'aktif' => true,
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Data pegawai berhasil diperbarui. Username login ikut disesuaikan dengan NIP baru.');

        $this->assertSame('198801012026011099', $akun->fresh()->username);
        $this->assertDatabaseHas('pegawai', [
            'id' => $pegawai->id,
            'nama_lengkap' => 'Guru Sinkron Mobile Revisi',
            'jabatan_utama' => 'Wali Kelas',
        ]);
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Pegawai API',
            'kode' => 'pembaca_pegawai_api',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());

        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Pegawai API',
            'username' => 'pembaca.pegawai.api',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach($peran);

        return $pengguna;
    }

    private function pegawai(
        string $nama,
        string $nip,
        bool $aktif = true,
        string $jenis = 'Guru',
    ): Pegawai {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => $jenis,
            'jabatan_utama' => $jenis,
            'aktif' => $aktif,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
