<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SatuanBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SatuanBarangApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_dan_mengirim_ringkasan_filter_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        SatuanBarang::create([
            'nama' => 'Unit',
            'kode' => 'UNIT',
            'deskripsi' => 'Satuan per barang.',
            'aktif' => true,
        ]);
        SatuanBarang::create([
            'nama' => 'Satuan Lama',
            'kode' => 'SATUAN_LAMA',
            'aktif' => false,
        ]);

        $this->getJson(route('api.v1.satuan-barang.index'))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.satuan-barang.index', [
                'cari' => 'unit',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.cari', 'unit')
            ->assertJsonPath('data.filter.status', 'aktif')
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.nonaktif', 1)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.kode', 'UNIT')
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
                'kode' => 'satuan-barang',
                'status' => 'tersedia',
                'rute' => '/satuan-barang',
            ]);
    }

    public function test_administrator_dapat_menambah_mengubah_dan_menonaktifkan_tanpa_menghapus_data(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.satuan-barang.store'), [
                'nama' => '  Buah  ',
                'kode' => ' buah barang ',
                'deskripsi' => '  Satuan untuk barang satuan.  ',
                'aktif' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nama', 'Buah')
            ->assertJsonPath('data.kode', 'BUAH_BARANG')
            ->assertJsonPath('data.deskripsi', 'Satuan untuk barang satuan.')
            ->assertJsonPath('data.aktif', true);
        $id = (int) $response->json('data.id');

        $this->withToken($token)
            ->patchJson(route('api.v1.satuan-barang.update', $id), [
                'nama' => 'Unit Barang',
                'kode' => 'unit-barang',
                'deskripsi' => null,
                'aktif' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('satuan_barang', [
            'id' => $id,
            'nama' => 'Unit Barang',
            'kode' => 'UNIT_BARANG',
            'deskripsi' => null,
            'aktif' => true,
        ]);

        $this->withToken($token)
            ->deleteJson(route('api.v1.satuan-barang.destroy', $id))
            ->assertOk();

        $this->assertDatabaseHas('satuan_barang', [
            'id' => $id,
            'aktif' => false,
        ]);
        $this->assertSame(1, SatuanBarang::count());
    }

    public function test_nama_dan_kode_harus_unik_setelah_kode_dirapikan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        SatuanBarang::create([
            'nama' => 'Unit',
            'kode' => 'UNIT',
            'aktif' => true,
        ]);

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.satuan-barang.store'), [
                'nama' => 'Unit',
                'kode' => 'unit',
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
            ->getJson(route('api.v1.satuan-barang.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);

        $this->withToken($token)
            ->postJson(route('api.v1.satuan-barang.store'), [
                'nama' => 'Tidak Boleh Dibuat',
                'kode' => 'TIDAK_BOLEH',
                'aktif' => true,
            ])
            ->assertForbidden();
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Satuan Barang Mobile',
            'kode' => 'pembaca_satuan_barang_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Satuan Barang Mobile',
            'username' => 'pembaca.satuan.barang.mobile',
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
        return $pengguna->createToken('Perangkat Satuan Barang', ['mobile'])->plainTextToken;
    }
}
