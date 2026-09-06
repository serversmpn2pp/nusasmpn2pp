<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\KategoriBarang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KategoriBarangApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_dan_mengirim_ringkasan_filter_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        KategoriBarang::create([
            'nama' => 'Elektronik',
            'kode' => 'ELEKTRONIK',
            'deskripsi' => 'Peralatan elektronik sekolah.',
            'aktif' => true,
        ]);
        KategoriBarang::create([
            'nama' => 'Kategori Lama',
            'kode' => 'KATEGORI_LAMA',
            'aktif' => false,
        ]);

        $this->getJson(route('api.v1.kategori-barang.index'))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.kategori-barang.index', [
                'cari' => 'elektronik',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.cari', 'elektronik')
            ->assertJsonPath('data.filter.status', 'aktif')
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.nonaktif', 1)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.kode', 'ELEKTRONIK')
            ->assertJsonPath('data.items.0.jumlah_barang', 0)
            ->assertJsonStructure([
                'data' => [
                    'items' => [[
                        'id',
                        'nama',
                        'kode',
                        'deskripsi',
                        'aktif',
                        'jumlah_barang',
                    ]],
                    'paginasi',
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'kategori-barang',
                'status' => 'tersedia',
                'rute' => '/kategori-barang',
            ]);
    }

    public function test_administrator_dapat_menambah_mengubah_dan_menonaktifkan_tanpa_menghapus_data(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.kategori-barang.store'), [
                'nama' => '  Alat Tulis Kantor  ',
                'kode' => ' alat tulis-kantor ',
                'deskripsi' => '  Perlengkapan administrasi sekolah.  ',
                'aktif' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nama', 'Alat Tulis Kantor')
            ->assertJsonPath('data.kode', 'ALAT_TULIS_KANTOR')
            ->assertJsonPath('data.deskripsi', 'Perlengkapan administrasi sekolah.')
            ->assertJsonPath('data.aktif', true);
        $id = (int) $response->json('data.id');

        $this->withToken($token)
            ->patchJson(route('api.v1.kategori-barang.update', $id), [
                'nama' => 'Alat Tulis dan Kantor',
                'kode' => 'atk-sekolah',
                'deskripsi' => null,
                'aktif' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('kategori_barang', [
            'id' => $id,
            'nama' => 'Alat Tulis dan Kantor',
            'kode' => 'ATK_SEKOLAH',
            'deskripsi' => null,
            'aktif' => true,
        ]);

        $this->withToken($token)
            ->deleteJson(route('api.v1.kategori-barang.destroy', $id))
            ->assertOk();

        $this->assertDatabaseHas('kategori_barang', [
            'id' => $id,
            'aktif' => false,
        ]);
        $this->assertSame(1, KategoriBarang::count());
    }

    public function test_nama_dan_kode_harus_unik_setelah_kode_dirapikan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        KategoriBarang::create([
            'nama' => 'Elektronik',
            'kode' => 'ELEKTRONIK',
            'aktif' => true,
        ]);

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.kategori-barang.store'), [
                'nama' => 'Elektronik',
                'kode' => 'elektronik',
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nama', 'kode']);
    }

    public function test_izin_lihat_hanya_dapat_membuka_daftar_bukan_mengubah(): void
    {
        $pengguna = $this->penggunaDenganIzin('barang.lihat');
        $token = $this->token($pengguna);

        $this->withToken($token)
            ->getJson(route('api.v1.kategori-barang.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);

        $this->withToken($token)
            ->postJson(route('api.v1.kategori-barang.store'), [
                'nama' => 'Tidak Boleh Dibuat',
                'kode' => 'TIDAK_BOLEH',
                'aktif' => true,
            ])
            ->assertForbidden();
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Barang Mobile',
            'kode' => 'pembaca_barang_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Barang Mobile',
            'username' => 'pembaca.barang.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach($peran);

        return $pengguna;
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Kategori Barang', ['mobile'])->plainTextToken;
    }
}
