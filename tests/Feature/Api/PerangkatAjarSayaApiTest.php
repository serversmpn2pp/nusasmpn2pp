<?php

namespace Tests\Feature\Api;

use App\Models\GuruMataPelajaran;
use App\Models\JenisPerangkatAjar;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PerangkatAjar;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerangkatAjarSayaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_memerlukan_token_izin_dan_keterhubungan_pegawai(): void
    {
        $this->getJson(route('api.v1.perangkat-ajar-saya.index'))->assertUnauthorized();

        $pegawaiBiasa = Pengguna::create([
            'nama' => 'Pegawai Tanpa Izin Perangkat',
            'username' => 'tanpa-izin-perangkat-mobile',
            'kata_sandi' => Hash::make('rahasia'),
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $this->withToken($this->token($pegawaiBiasa))
            ->getJson(route('api.v1.perangkat-ajar-saya.index'))
            ->assertForbidden();
    }

    public function test_daftar_menyusun_kewajiban_per_mapel_dan_tingkat(): void
    {
        $data = $this->dataDasar();

        $this->withToken($this->token($data['pengguna']))
            ->getJson(route('api.v1.perangkat-ajar-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.pegawai.nama', 'Guru Informatika Mobile')
            ->assertJsonPath('data.ringkasan.wajib', 2)
            ->assertJsonPath('data.ringkasan.terunggah', 0)
            ->assertJsonPath('data.ringkasan.kelengkapan', 0)
            ->assertJsonCount(2, 'data.penugasan')
            ->assertJsonPath('data.penugasan.0.label_tingkat', 'VII')
            ->assertJsonPath('data.penugasan.1.label_tingkat', 'VIII')
            ->assertJsonCount(2, 'data.penugasan.0.dokumen')
            ->assertJsonPath('data.penugasan.0.dokumen.0.jenis.wajib', true)
            ->assertJsonPath('data.batas_unggah.byte', fn ($nilai) => $nilai > 0);
    }

    public function test_guru_dapat_mengunggah_pdf_dan_duplikat_ditolak(): void
    {
        Storage::fake('local');
        $data = $this->dataDasar();
        $token = $this->token($data['pengguna']);
        $payload = [
            'tahun_pelajaran_id' => $data['tahun']->id,
            'semester' => 1,
            'mata_pelajaran_id' => $data['mapel']->id,
            'tingkat' => 7,
            'jenis_perangkat_ajar_id' => $data['jenis_wajib']->id,
            'judul' => 'Modul Informatika Tingkat VII',
            'catatan_guru' => 'Unggahan awal dari Android.',
            'file_pdf' => UploadedFile::fake()->create('modul-vii.pdf', 100, 'application/pdf'),
        ];

        $response = $this->withToken($token)
            ->withHeader('Accept', 'application/json')
            ->post(route('api.v1.perangkat-ajar-saya.store'), $payload)
            ->assertCreated();

        $dokumen = PerangkatAjar::findOrFail($response->json('data.id'));
        Storage::disk('local')->assertExists($dokumen->lokasi_file);
        $this->assertSame('menunggu_pemeriksaan', $dokumen->status);
        $this->assertSame(1, $dokumen->riwayatFile()->count());

        $this->withToken($token)
            ->getJson(route('api.v1.perangkat-ajar-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.terunggah', 1)
            ->assertJsonPath('data.ringkasan.kelengkapan', 50)
            ->assertJsonPath('data.penugasan.0.dokumen.0.perangkat_ajar.id', $dokumen->id);

        $payload['file_pdf'] = UploadedFile::fake()->create('duplikat.pdf', 100, 'application/pdf');
        $this->withToken($token)
            ->withHeader('Accept', 'application/json')
            ->post(route('api.v1.perangkat-ajar-saya.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jenis_perangkat_ajar_id');
        $this->assertDatabaseCount('perangkat_ajar', 1);
    }

    public function test_revisi_pdf_membuat_riwayat_dan_kembali_menunggu_pemeriksaan(): void
    {
        Storage::fake('local');
        $data = $this->dataDasar();
        $token = $this->token($data['pengguna']);
        $dokumen = $this->unggahAwal($token, $data);
        $dokumen->update([
            'status' => 'perlu_perbaikan',
            'catatan_pemeriksa' => 'Lengkapi langkah pembelajaran.',
            'pemeriksa_pegawai_id' => $data['pegawai']->id,
            'diperiksa_pada' => now(),
        ]);

        $this->withToken($token)
            ->withHeader('Accept', 'application/json')
            ->post(route('api.v1.perangkat-ajar-saya.update', $dokumen), [
                'tingkat' => 7,
                'judul' => 'Modul Informatika Tingkat VII Revisi',
                'catatan_guru' => 'Sudah diperbaiki.',
                'file_pdf' => UploadedFile::fake()->create('modul-vii-revisi.pdf', 120, 'application/pdf'),
            ])
            ->assertOk();

        $dokumen->refresh();
        $this->assertSame('menunggu_pemeriksaan', $dokumen->status);
        $this->assertNull($dokumen->catatan_pemeriksa);
        $this->assertSame('modul-vii-revisi.pdf', $dokumen->nama_file_asli);
        $this->assertSame(2, $dokumen->riwayatFile()->count());

        $this->withToken($token)
            ->getJson(route('api.v1.perangkat-ajar-saya.show', $dokumen))
            ->assertOk()
            ->assertJsonPath('data.perangkat_ajar.judul', 'Modul Informatika Tingkat VII Revisi')
            ->assertJsonCount(2, 'data.riwayat')
            ->assertJsonPath('data.riwayat.0.nama_file', 'modul-vii-revisi.pdf');
    }

    public function test_tingkat_di_luar_penugasan_dan_dokumen_guru_lain_ditolak(): void
    {
        Storage::fake('local');
        $data = $this->dataDasar();
        $token = $this->token($data['pengguna']);

        $this->withToken($token)
            ->withHeader('Accept', 'application/json')
            ->post(route('api.v1.perangkat-ajar-saya.store'), [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 1,
                'mata_pelajaran_id' => $data['mapel']->id,
                'tingkat' => 9,
                'jenis_perangkat_ajar_id' => $data['jenis_wajib']->id,
                'judul' => 'Dokumen Tingkat Salah',
                'file_pdf' => UploadedFile::fake()->create('salah.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tingkat');

        $dokumen = $this->unggahAwal($token, $data);
        $penggunaLain = $this->buatGuru('Guru Lain Mobile', 'guru-lain-perangkat-mobile');
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($penggunaLain))
            ->getJson(route('api.v1.perangkat-ajar-saya.show', $dokumen))
            ->assertForbidden();
    }

    private function dataDasar(): array
    {
        $pengguna = $this->buatGuru('Guru Informatika Mobile', 'guru-perangkat-mobile');
        $pegawai = $pengguna->pegawai;
        $tahun = TahunPelajaran::create(['nama' => '2038/2039', 'aktif' => true]);
        $mapel = MataPelajaran::create([
            'kode' => 'INF-MOBILE-DOC',
            'nama' => 'Informatika Mobile',
            'aktif' => true,
        ]);
        foreach ([7, 8] as $tingkat) {
            $kelas = Kelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => $tingkat === 7 ? 'VII.A Mobile' : 'VIII.A Mobile',
                'tingkat' => $tingkat,
                'aktif' => true,
            ]);
            GuruMataPelajaran::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'mata_pelajaran_id' => $mapel->id,
                'pegawai_id' => $pegawai->id,
                'aktif' => true,
            ]);
        }
        $jenisWajib = JenisPerangkatAjar::create([
            'kode' => 'MODUL-MOBILE',
            'nama' => 'Modul Ajar',
            'wajib' => true,
            'urutan' => 1,
            'aktif' => true,
        ]);
        $jenisOpsional = JenisPerangkatAjar::create([
            'kode' => 'MEDIA-MOBILE',
            'nama' => 'Media Pembelajaran',
            'wajib' => false,
            'urutan' => 2,
            'aktif' => true,
        ]);

        return compact('pengguna', 'pegawai', 'tahun', 'mapel', 'jenisWajib', 'jenisOpsional') + [
            'jenis_wajib' => $jenisWajib,
            'jenis_opsional' => $jenisOpsional,
        ];
    }

    private function buatGuru(string $nama, string $username): Pengguna
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => fake()->unique()->numerify('19800101201001####'),
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $username,
            'kata_sandi' => Hash::make('rahasia'),
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());

        return $pengguna;
    }

    private function unggahAwal(string $token, array $data): PerangkatAjar
    {
        $response = $this->withToken($token)
            ->withHeader('Accept', 'application/json')
            ->post(route('api.v1.perangkat-ajar-saya.store'), [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 1,
                'mata_pelajaran_id' => $data['mapel']->id,
                'tingkat' => 7,
                'jenis_perangkat_ajar_id' => $data['jenis_wajib']->id,
                'judul' => 'Modul Informatika Tingkat VII',
                'file_pdf' => UploadedFile::fake()->create('modul-vii.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated();

        return PerangkatAjar::findOrFail($response->json('data.id'));
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
