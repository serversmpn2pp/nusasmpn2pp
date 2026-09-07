<?php

namespace Tests\Feature\Api;

use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilSayaApiTest extends TestCase
{
    use RefreshDatabase;

    private array $berkasSementara = [];

    protected function tearDown(): void
    {
        foreach ($this->berkasSementara as $lokasi) {
            if (is_file($lokasi)) {
                unlink($lokasi);
            }
        }

        parent::tearDown();
    }

    public function test_profil_sendiri_tidak_mengirimkan_peran_dan_hak_akses(): void
    {
        $pengguna = $this->buatPengguna([
            'nama' => 'Administrator NUSA',
            'username' => 'admin.profil',
            'akun_sistem' => true,
        ]);

        $response = $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.profil-saya.show'))
            ->assertOk()
            ->assertJsonPath('data.nama', 'Administrator NUSA')
            ->assertJsonPath('data.username', 'admin.profil')
            ->assertJsonPath('data.jenis', 'akun');

        $this->assertArrayNotHasKey('peran', $response->json('data'));
        $this->assertArrayNotHasKey('izin', $response->json('data'));
    }

    public function test_pegawai_dapat_memperbarui_profil_sendiri_tanpa_mengubah_username(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Lama',
            'nip' => '198501012010011001',
            'email' => 'lama@example.test',
            'aktif' => true,
        ]);
        $pengguna = $this->buatPengguna([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'peran' => 'pegawai',
        ]);

        $this->withToken($this->token($pengguna))
            ->putJson(route('api.v1.profil-saya.update'), [
                'nama_lengkap' => 'Guru Diperbarui',
                'nuptk' => '1234567890123456',
                'nik' => '1374010101850001',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Padang Panjang',
                'tanggal_lahir' => '1985-01-01',
                'alamat' => 'Jalan Pendidikan Nomor 2',
                'email' => 'guru.baru@example.test',
                'no_hp' => '081234567890',
                'pendidikan_terakhir' => 'S1',
                'jurusan_pendidikan' => 'Pendidikan Matematika',
                'tahun_lulus' => 2008,
                'keterangan' => 'Data profil mandiri.',
                'username' => 'tidak-boleh-berubah',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Profil Anda berhasil diperbarui.')
            ->assertJsonPath('data.nama', 'Guru Diperbarui')
            ->assertJsonPath('data.data.email', 'guru.baru@example.test')
            ->assertJsonPath('pengguna.nama', 'Guru Diperbarui');

        $this->assertDatabaseHas('pegawai', [
            'id' => $pegawai->id,
            'nama_lengkap' => 'Guru Diperbarui',
            'no_hp' => '081234567890',
        ]);
        $this->assertSame('198501012010011001', $pengguna->fresh()->username);
    }

    public function test_orang_tua_dapat_memperbarui_nama_dan_nomor_whatsapp(): void
    {
        $pengguna = $this->buatPengguna([
            'nama' => 'Wali Lama',
            'username' => 'ORT-0011223344',
            'peran' => 'orang_tua',
        ]);
        $orangTua = OrangTuaWali::create([
            'pengguna_id' => $pengguna->id,
            'nama_lengkap' => 'Wali Lama',
            'nomor_wa' => '081111111111',
        ]);

        $this->withToken($this->token($pengguna))
            ->putJson(route('api.v1.profil-saya.update'), [
                'nama_lengkap' => 'Wali Baru',
                'nomor_wa' => '+62 812-3456-7890',
            ])
            ->assertOk()
            ->assertJsonPath('data.data.nama_lengkap', 'Wali Baru')
            ->assertJsonPath('data.data.nomor_wa', '+62 812-3456-7890');

        $this->assertSame('Wali Baru', $orangTua->fresh()->nama_lengkap);
        $this->assertSame('Wali Baru', $pengguna->fresh()->nama);
    }

    public function test_siswa_hanya_dapat_memperbarui_alamatnya(): void
    {
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa NUSA',
            'nis' => '260001',
            'nisn' => '0011223344',
            'alamat' => 'Alamat lama',
            'aktif' => true,
        ]);
        $pengguna = $this->buatPengguna([
            'siswa_id' => $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'username' => $siswa->nisn,
            'peran' => 'siswa',
        ]);

        $this->withToken($this->token($pengguna))
            ->putJson(route('api.v1.profil-saya.update'), [
                'alamat' => 'Alamat baru siswa',
                'nama_lengkap' => 'Nama Tidak Boleh Berubah',
                'nisn' => '9999999999',
            ])
            ->assertOk()
            ->assertJsonPath('data.data.alamat', 'Alamat baru siswa')
            ->assertJsonPath('data.data.nama_lengkap', 'Siswa NUSA')
            ->assertJsonPath('data.data.nisn', '0011223344');

        $this->assertSame('Siswa NUSA', $siswa->fresh()->nama_lengkap);
        $this->assertSame('Alamat baru siswa', $siswa->fresh()->alamat);
    }

    public function test_validasi_profil_pegawai_menolak_email_yang_tidak_valid(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Validasi',
            'nip' => '198501012010011002',
            'aktif' => true,
        ]);
        $pengguna = $this->buatPengguna([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'peran' => 'pegawai',
        ]);

        $this->withToken($this->token($pengguna))
            ->putJson(route('api.v1.profil-saya.update'), [
                'nama_lengkap' => 'Guru Validasi',
                'email' => 'bukan-email',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_pegawai_dapat_memperbarui_foto_profil_sendiri(): void
    {
        Storage::fake('public');
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Foto',
            'nip' => '198501012010011003',
            'foto' => 'pegawai/foto/lama.png',
            'aktif' => true,
        ]);
        Storage::disk('public')->put($pegawai->foto, 'foto lama');
        $pengguna = $this->buatPengguna([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'peran' => 'pegawai',
        ]);

        $this->withToken($this->token($pengguna))
            ->postJson(route('api.v1.profil-saya.foto.update'), [
                'foto' => $this->buatFotoPng('foto-profil-baru.png'),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Foto profil berhasil diperbarui.');

        $fotoBaru = $pegawai->fresh()->foto;
        $this->assertNotSame('pegawai/foto/lama.png', $fotoBaru);
        Storage::disk('public')->assertExists($fotoBaru);
        Storage::disk('public')->assertMissing('pegawai/foto/lama.png');
    }

    private function buatPengguna(array $atribut = []): Pengguna
    {
        return Pengguna::create(array_merge([
            'nama' => 'Pengguna Profil',
            'username' => 'pengguna.profil.'.fake()->unique()->numerify('####'),
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'lainnya',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ], $atribut));
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }

    private function buatFotoPng(string $nama): UploadedFile
    {
        $lokasi = tempnam(sys_get_temp_dir(), 'nusa-profil-');
        $isiPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        );
        file_put_contents($lokasi, $isiPng);
        $this->berkasSementara[] = $lokasi;

        return new UploadedFile($lokasi, $nama, 'image/png', UPLOAD_ERR_OK, true);
    }
}
