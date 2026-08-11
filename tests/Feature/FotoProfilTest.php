<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
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

    public function test_header_menampilkan_foto_dari_data_pegawai_pengguna(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Antonius Pitra',
            'nip' => '198001012010015555',
            'foto' => 'pegawai/foto/antonius-pitra.jpg',
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
            ->get(route('beranda'))
            ->assertOk()
            ->assertSee('class="account-avatar"', false)
            ->assertSee('AP')
            ->assertSee('/storage/pegawai/foto/antonius-pitra.jpg', false);
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

    public function test_url_unggah_foto_aman_saat_diakses_melalui_cloudflare_https(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Foto Cloudflare',
            'nip' => '198001012010014444',
            'foto' => 'pegawai/foto/cloudflare.jpg',
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->withHeaders([
                'X-Forwarded-Host' => 'nusa.smpn2padangpanjang.sch.id',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/pegawai/'.$pegawai->id.'/edit')
            ->assertOk()
            ->assertSee('data-upload-url="/pegawai/'.$pegawai->id.'/foto"', false)
            ->assertSee('https://nusa.smpn2padangpanjang.sch.id/storage/pegawai/foto/cloudflare.jpg', false);
    }

    public function test_administrator_dapat_mengelola_foto_seluruh_siswa_dalam_satu_kelas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelasA = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $kelasB = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.B',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswaNomorDua = $this->buatSiswaFoto('Budi Nomor Dua', '2601002', '0000001002');
        $siswaNomorSatu = $this->buatSiswaFoto('Andi Nomor Satu', '2601001', '0000001001');
        $siswaKelasLain = $this->buatSiswaFoto('Citra Kelas Lain', '2602001', '0000002001');

        foreach ([
            [$kelasA, $siswaNomorDua, 2],
            [$kelasA, $siswaNomorSatu, 1],
            [$kelasB, $siswaKelasLain, 1],
        ] as [$kelas, $siswa, $nomorAbsen]) {
            AnggotaKelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $nomorAbsen,
                'status_keanggotaan' => 'aktif',
            ]);
        }

        $this->actingAs($administrator)
            ->get(route('foto-identitas.index', [
                'tab' => 'siswa',
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelasA->id,
            ]))
            ->assertOk()
            ->assertSee('Foto Identitas')
            ->assertSee('Penyimpanan otomatis aktif')
            ->assertSeeInOrder(['Andi Nomor Satu', 'Budi Nomor Dua'])
            ->assertDontSee('Citra Kelas Lain')
            ->assertSee('data-upload-url="/siswa/'.$siswaNomorSatu->id.'/foto"', false)
            ->assertSee('data-upload-url="/siswa/'.$siswaNomorDua->id.'/foto"', false);
    }

    public function test_halaman_foto_identitas_dapat_menyaring_status_foto_dan_mengelola_pegawai(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2027/2028',
            'tanggal_mulai' => '2027-07-01',
            'tanggal_selesai' => '2028-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.A',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswaSudahFoto = $this->buatSiswaFoto('Siswa Sudah Foto', '2701001', '0000010001', 'siswa/foto/sudah.jpg');
        $siswaBelumFoto = $this->buatSiswaFoto('Siswa Belum Foto', '2701002', '0000010002');

        foreach ([[$siswaSudahFoto, 1], [$siswaBelumFoto, 2]] as [$siswa, $nomorAbsen]) {
            AnggotaKelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $nomorAbsen,
                'status_keanggotaan' => 'aktif',
            ]);
        }

        $this->actingAs($administrator)
            ->get(route('foto-identitas.index', [
                'tab' => 'siswa',
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'status_foto' => 'belum',
            ]))
            ->assertOk()
            ->assertSee('Siswa Belum Foto')
            ->assertDontSee('Siswa Sudah Foto');

        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Untuk Foto Identitas',
            'nip' => '198001012010019999',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->get(route('foto-identitas.index', ['tab' => 'pegawai']))
            ->assertOk()
            ->assertSee('Pegawai Untuk Foto Identitas')
            ->assertSee('data-upload-url="/pegawai/'.$pegawai->id.'/foto"', false);
    }

    private function buatSiswaFoto(
        string $nama,
        string $nis,
        string $nisn,
        ?string $foto = null,
    ): Siswa {
        return Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => $nis,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'foto' => $foto,
            'aktif' => true,
        ]);
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
