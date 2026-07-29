<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FotoProfilTest extends TestCase
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

    public function test_administrator_dapat_memperbarui_foto_pegawai_secara_otomatis(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pegawai/foto/lama.jpg', 'foto lama');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Foto',
            'nip' => '198001012010011111',
            'foto' => 'pegawai/foto/lama.jpg',
            'aktif' => true,
        ]);

        $respons = $this->actingAs($administrator)
            ->postJson(route('pegawai.foto.update', $pegawai), [
                'foto' => $this->buatFotoPng('pegawai-baru.png'),
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Foto pegawai berhasil diperbarui.');

        $lokasiBaru = $pegawai->fresh()->foto;

        $this->assertNotSame('pegawai/foto/lama.jpg', $lokasiBaru);
        Storage::disk('public')->assertExists($lokasiBaru);
        Storage::disk('public')->assertMissing('pegawai/foto/lama.jpg');
        $this->assertStringContainsString('/storage/'.$lokasiBaru, $respons->json('url'));
    }

    public function test_administrator_dapat_memperbarui_foto_siswa_secara_otomatis(): void
    {
        Storage::fake('public');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Foto',
            'nis' => '2600001',
            'nisn' => '0011111111',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->postJson(route('siswa.foto.update', $siswa), [
                'foto' => $this->buatFotoPng('siswa-baru.png'),
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Foto siswa berhasil diperbarui.');

        Storage::disk('public')->assertExists($siswa->fresh()->foto);
    }

    public function test_foto_yang_masih_melebihi_batas_mendapat_pesan_yang_jelas(): void
    {
        Storage::fake('public');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Foto Besar',
            'nis' => '2600002',
            'nisn' => '0022222222',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->postJson(route('siswa.foto.update', $siswa), [
                'foto' => $this->buatFotoPng('foto-besar.png', 1600),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('foto')
            ->assertJsonPath(
                'errors.foto.0',
                'Foto setelah diproses masih terlalu besar. Ukuran maksimal yang dapat disimpan adalah 1,5 MB.',
            );

        $this->assertNull($siswa->fresh()->foto);
    }

    public function test_pegawai_dapat_memperbarui_foto_profil_miliknya_sendiri(): void
    {
        Storage::fake('public');
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Profil Foto',
            'nip' => '198001012010012222',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => Hash::make('rahasia'),
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akun->daftarPeran()->sync([
            Peran::where('kode', 'pegawai')->value('id'),
        ]);

        $this->actingAs($akun)
            ->postJson(route('profil-pegawai.foto.update'), [
                'foto' => $this->buatFotoPng('profil-baru.png'),
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Foto profil berhasil diperbarui.');

        Storage::disk('public')->assertExists($pegawai->fresh()->foto);
    }

    public function test_form_foto_menjelaskan_pemrosesan_dan_penyimpanan_otomatis(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Form Foto',
            'nip' => '198001012010013333',
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Form Foto',
            'nis' => '2600003',
            'nisn' => '0033333333',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->get(route('pegawai.edit', $pegawai))
            ->assertOk()
            ->assertSee('hingga 20 MB akan diperkecil otomatis')
            ->assertSee('Perubahan foto langsung tersimpan.');

        $this->actingAs($administrator)
            ->get(route('siswa.edit', $siswa))
            ->assertOk()
            ->assertSee('hingga 20 MB akan diperkecil otomatis')
            ->assertSee('Perubahan foto langsung tersimpan.');

        $this->actingAs($administrator)
            ->get(route('siswa.create'))
            ->assertOk()
            ->assertSee('Foto disimpan bersama data utama.');
    }

    private function buatFotoPng(string $nama, int $tambahanKilobyte = 0): UploadedFile
    {
        $lokasi = tempnam(sys_get_temp_dir(), 'nusa-foto-');
        $isiPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        );
        file_put_contents($lokasi, $isiPng.str_repeat('0', $tambahanKilobyte * 1024));
        $this->berkasSementara[] = $lokasi;

        return new UploadedFile($lokasi, $nama, 'image/png', UPLOAD_ERR_OK, true);
    }
}
