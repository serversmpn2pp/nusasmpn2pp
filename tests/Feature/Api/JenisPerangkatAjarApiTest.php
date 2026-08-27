<?php

namespace Tests\Feature\Api;

use App\Models\JenisPerangkatAjar;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PerangkatAjar;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JenisPerangkatAjarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_jenis_perangkat_ajar_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.jenis-perangkat-ajar.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Guru Tanpa Izin Jenis',
            'username' => 'guru.tanpa.izin.jenis',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.jenis-perangkat-ajar.index'))
            ->assertForbidden();
    }

    public function test_administrator_dapat_mencari_memfilter_dan_melihat_ringkasan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        JenisPerangkatAjar::create([
            'kode' => 'MODUL_MOBILE_FILTER',
            'nama' => 'Modul Mobile Filter',
            'deskripsi' => 'Perangkat pembelajaran semester',
            'wajib' => true,
            'urutan' => 12,
            'aktif' => true,
        ]);
        JenisPerangkatAjar::create([
            'kode' => 'LAMPIRAN_MOBILE_FILTER',
            'nama' => 'Lampiran Mobile Filter',
            'wajib' => false,
            'urutan' => 13,
            'aktif' => false,
        ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.jenis-perangkat-ajar.index', [
                'cari' => 'modul mobile',
                'status' => 'aktif',
                'kewajiban' => 'wajib',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.kode', 'MODUL_MOBILE_FILTER')
            ->assertJsonPath('data.items.0.jumlah_dokumen', 0)
            ->assertJsonPath('data.filter.status', 'aktif')
            ->assertJsonPath('data.filter.kewajiban', 'wajib')
            ->assertJsonStructure([
                'data' => [
                    'ringkasan' => ['total', 'aktif', 'wajib'],
                    'paginasi' => ['halaman', 'total', 'ada_halaman_berikutnya'],
                    'urutan_berikutnya',
                ],
            ]);
    }

    public function test_administrator_dapat_menambah_dan_mengubah_jenis_dengan_kode_rapi(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.jenis-perangkat-ajar.store'), [
                'nama' => '  Modul Projek Mobile  ',
                'kode' => ' modul projek-mobile ',
                'deskripsi' => '  Dokumen projek sekolah  ',
                'wajib' => true,
                'urutan' => 20,
                'aktif' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.kode', 'MODUL_PROJEK_MOBILE')
            ->assertJsonPath('data.nama', 'Modul Projek Mobile');

        $jenis = JenisPerangkatAjar::findOrFail($response->json('data.id'));
        $this->assertSame('Dokumen projek sekolah', $jenis->deskripsi);

        $this->withToken($token)
            ->patchJson(route('api.v1.jenis-perangkat-ajar.update', $jenis), [
                'nama' => 'Modul Projek Mobile Baru',
                'kode' => 'modul projek mobile baru',
                'deskripsi' => null,
                'wajib' => false,
                'urutan' => 21,
                'aktif' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('jenis_perangkat_ajar', [
            'id' => $jenis->id,
            'nama' => 'Modul Projek Mobile Baru',
            'kode' => 'MODUL_PROJEK_MOBILE_BARU',
            'wajib' => false,
            'urutan' => 21,
        ]);

        $this->withToken($token)
            ->postJson(route('api.v1.jenis-perangkat-ajar.store'), [
                'nama' => 'Nama Jenis Lain',
                'kode' => 'modul-projek-mobile-baru',
                'wajib' => true,
                'urutan' => 22,
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kode');
    }

    public function test_menonaktifkan_jenis_tidak_menghapus_dokumen_lama(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $jenis = JenisPerangkatAjar::create([
            'kode' => 'ARSIP_MOBILE',
            'nama' => 'Arsip Mobile',
            'wajib' => false,
            'urutan' => 90,
            'aktif' => true,
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Arsip Mobile',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $tahun = TahunPelajaran::create(['nama' => '2030/2031', 'aktif' => true]);
        $mataPelajaran = MataPelajaran::create(['nama' => 'Mapel Arsip Mobile', 'aktif' => true]);
        $dokumen = PerangkatAjar::create([
            'pegawai_id' => $pegawai->id,
            'tahun_pelajaran_id' => $tahun->id,
            'semester' => 1,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 7,
            'jenis_perangkat_ajar_id' => $jenis->id,
            'judul' => 'Dokumen Lama Mobile',
            'lokasi_file' => 'perangkat-ajar/arsip-mobile.pdf',
            'nama_file_asli' => 'arsip-mobile.pdf',
            'tipe_file' => 'application/pdf',
            'ukuran_file' => 1024,
            'status' => 'menunggu_pemeriksaan',
            'diunggah_pada' => now(),
        ]);

        $this->withToken($this->token($administrator))
            ->deleteJson(route('api.v1.jenis-perangkat-ajar.destroy', $jenis))
            ->assertOk()
            ->assertJsonPath(
                'pesan',
                'Jenis perangkat ajar berhasil dinonaktifkan. Dokumen lama tetap tersimpan.',
            );

        $this->assertDatabaseHas('jenis_perangkat_ajar', ['id' => $jenis->id, 'aktif' => false]);
        $this->assertDatabaseHas('perangkat_ajar', ['id' => $dokumen->id]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
