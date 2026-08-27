<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FotoIdentitasApiTest extends TestCase
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

    public function test_api_foto_identitas_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.foto-identitas.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Tanpa Izin Foto Identitas',
            'username' => 'tanpa.izin.foto.identitas',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.foto-identitas.index'))
            ->assertForbidden();
    }

    public function test_administrator_dapat_memfilter_foto_siswa_per_kelas(): void
    {
        $data = $this->dataFoto();

        $this->withToken($this->token($data['administrator']))
            ->getJson(route('api.v1.foto-identitas.index', [
                'tab' => 'siswa',
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas']->id,
                'status_foto' => 'belum',
            ]))
            ->assertOk()
            ->assertJsonPath('data.tab', 'siswa')
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.sudah', 1)
            ->assertJsonPath('data.ringkasan.belum', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.nama', 'Siswa Belum Foto Mobile')
            ->assertJsonPath('data.items.0.punya_foto', false)
            ->assertJsonPath('data.filter.kelas_id', $data['kelas']->id)
            ->assertJsonPath('data.hak_akses.dapat_kelola_siswa', true)
            ->assertJsonStructure([
                'data' => [
                    'tahun_pelajaran',
                    'kelas',
                    'paginasi' => ['halaman', 'total', 'ada_halaman_berikutnya'],
                ],
            ]);
    }

    public function test_administrator_dapat_memfilter_foto_pegawai(): void
    {
        $data = $this->dataFoto();

        $this->withToken($this->token($data['administrator']))
            ->getJson(route('api.v1.foto-identitas.index', [
                'tab' => 'pegawai',
                'status_pegawai' => 'aktif',
                'jenis_pegawai' => 'Guru',
                'status_foto' => 'belum',
                'cari' => 'pegawai belum',
            ]))
            ->assertOk()
            ->assertJsonPath('data.tab', 'pegawai')
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.sudah', 1)
            ->assertJsonPath('data.ringkasan.belum', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.nama', 'Pegawai Belum Foto Mobile')
            ->assertJsonFragment(['jenis_pegawai' => ['Guru']])
            ->assertJsonPath('data.hak_akses.dapat_kelola_pegawai', true);
    }

    public function test_administrator_dapat_mengganti_foto_siswa_dan_pegawai(): void
    {
        Storage::fake('public');
        $data = $this->dataFoto();
        Storage::disk('public')->put('siswa/foto/lama-mobile.jpg', 'lama siswa');
        Storage::disk('public')->put('pegawai/foto/lama-mobile.jpg', 'lama pegawai');
        $data['siswa_sudah']->update(['foto' => 'siswa/foto/lama-mobile.jpg']);
        $data['pegawai_sudah']->update(['foto' => 'pegawai/foto/lama-mobile.jpg']);
        $token = $this->token($data['administrator']);

        $responseSiswa = $this->withToken($token)
            ->postJson(route('api.v1.foto-identitas.siswa.update', $data['siswa_sudah']), [
                'foto' => $this->buatFotoPng('siswa-mobile-baru.png'),
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Foto siswa berhasil diperbarui.');
        $fotoSiswaBaru = $data['siswa_sudah']->fresh()->foto;
        Storage::disk('public')->assertExists($fotoSiswaBaru);
        Storage::disk('public')->assertMissing('siswa/foto/lama-mobile.jpg');
        $this->assertStringContainsString('/storage/'.$fotoSiswaBaru, $responseSiswa->json('data.foto_url'));

        $this->withToken($token)
            ->postJson(route('api.v1.foto-identitas.pegawai.update', $data['pegawai_sudah']), [
                'foto' => $this->buatFotoPng('pegawai-mobile-baru.png'),
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Foto pegawai berhasil diperbarui.');
        Storage::disk('public')->assertExists($data['pegawai_sudah']->fresh()->foto);
        Storage::disk('public')->assertMissing('pegawai/foto/lama-mobile.jpg');
    }

    public function test_api_menolak_foto_yang_melebihi_batas_server(): void
    {
        Storage::fake('public');
        $data = $this->dataFoto();

        $this->withToken($this->token($data['administrator']))
            ->postJson(route('api.v1.foto-identitas.siswa.update', $data['siswa_belum']), [
                'foto' => $this->buatFotoPng('foto-mobile-besar.png', 1600),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('foto')
            ->assertJsonPath(
                'errors.foto.0',
                'Foto setelah diproses masih terlalu besar. Ukuran maksimal yang dapat disimpan adalah 1,5 MB.',
            );
    }

    private function dataFoto(): array
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2029/2030 Foto Mobile',
            'tanggal_mulai' => '2029-07-16',
            'tanggal_selesai' => '2030-06-20',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.F Foto Mobile',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswaSudah = $this->siswa('Siswa Sudah Foto Mobile', '992900001', 'siswa/foto/sudah-mobile.jpg');
        $siswaBelum = $this->siswa('Siswa Belum Foto Mobile', '992900002');
        foreach ([[$siswaSudah, 1], [$siswaBelum, 2]] as [$siswa, $nomor]) {
            AnggotaKelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $nomor,
                'status_keanggotaan' => 'aktif',
            ]);
        }
        $pegawaiSudah = Pegawai::create([
            'nama_lengkap' => 'Pegawai Sudah Foto Mobile',
            'nip' => '197001012029010001',
            'foto' => 'pegawai/foto/sudah-mobile.jpg',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $pegawaiBelum = Pegawai::create([
            'nama_lengkap' => 'Pegawai Belum Foto Mobile',
            'nip' => '197001012029010002',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);

        return [
            'administrator' => $administrator,
            'tahun' => $tahun,
            'kelas' => $kelas,
            'siswa_sudah' => $siswaSudah,
            'siswa_belum' => $siswaBelum,
            'pegawai_sudah' => $pegawaiSudah,
            'pegawai_belum' => $pegawaiBelum,
        ];
    }

    private function siswa(string $nama, string $nisn, ?string $foto = null): Siswa
    {
        return Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => $nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'foto' => $foto,
            'aktif' => true,
        ]);
    }

    private function buatFotoPng(string $nama, int $tambahanKilobyte = 0): UploadedFile
    {
        $lokasi = tempnam(sys_get_temp_dir(), 'nusa-foto-api-');
        $isiPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        );
        file_put_contents($lokasi, $isiPng.str_repeat('0', $tambahanKilobyte * 1024));
        $this->berkasSementara[] = $lokasi;

        return new UploadedFile($lokasi, $nama, 'image/png', UPLOAD_ERR_OK, true);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
