<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SumberPerolehanBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SumberPerolehanBarangApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_dan_mengirim_ringkasan_filter_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        SumberPerolehanBarang::where('kode', 'DAK')->firstOrFail()->update([
            'deskripsi' => 'Dana Alokasi Khusus.',
        ]);
        SumberPerolehanBarang::create([
            'nama' => 'Hibah Lama',
            'kode' => 'HIBAH_LAMA',
            'aktif' => false,
        ]);

        $this->getJson(route('api.v1.sumber-perolehan.index'))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.sumber-perolehan.index', [
                'cari' => 'dak',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.cari', 'dak')
            ->assertJsonPath('data.filter.status', 'aktif')
            ->assertJsonPath('data.ringkasan.total', 3)
            ->assertJsonPath('data.ringkasan.aktif', 2)
            ->assertJsonPath('data.ringkasan.nonaktif', 1)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.kode', 'DAK')
            ->assertJsonPath('data.items.0.jumlah_unit_aset', 0)
            ->assertJsonStructure([
                'data' => [
                    'items' => [[
                        'id',
                        'nama',
                        'kode',
                        'deskripsi',
                        'aktif',
                        'jumlah_unit_aset',
                    ]],
                    'paginasi',
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'sumber-perolehan',
                'status' => 'tersedia',
                'rute' => '/sumber-perolehan',
            ]);
    }

    public function test_administrator_dapat_menambah_mengubah_dan_menonaktifkan_tanpa_menghapus_data(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.sumber-perolehan.store'), [
                'nama' => '  BOS Daerah  ',
                'kode' => ' bos daerah ',
                'deskripsi' => '  Bantuan operasional sekolah daerah.  ',
                'aktif' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nama', 'BOS Daerah')
            ->assertJsonPath('data.kode', 'BOS_DAERAH')
            ->assertJsonPath('data.deskripsi', 'Bantuan operasional sekolah daerah.')
            ->assertJsonPath('data.aktif', true);
        $id = (int) $response->json('data.id');

        $this->withToken($token)
            ->patchJson(route('api.v1.sumber-perolehan.update', $id), [
                'nama' => 'BOS Kota',
                'kode' => 'bos-kota',
                'deskripsi' => null,
                'aktif' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('sumber_perolehan_barang', [
            'id' => $id,
            'nama' => 'BOS Kota',
            'kode' => 'BOS_KOTA',
            'deskripsi' => null,
            'aktif' => true,
        ]);

        $this->withToken($token)
            ->deleteJson(route('api.v1.sumber-perolehan.destroy', $id))
            ->assertOk();

        $this->assertDatabaseHas('sumber_perolehan_barang', [
            'id' => $id,
            'aktif' => false,
        ]);
        $this->assertSame(3, SumberPerolehanBarang::count());
    }

    public function test_nama_dan_kode_harus_unik_setelah_kode_dirapikan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.sumber-perolehan.store'), [
                'nama' => 'DAK',
                'kode' => 'dak',
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
            ->getJson(route('api.v1.sumber-perolehan.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);

        $this->withToken($token)
            ->postJson(route('api.v1.sumber-perolehan.store'), [
                'nama' => 'Tidak Boleh Dibuat',
                'kode' => 'TIDAK_BOLEH',
                'aktif' => true,
            ])
            ->assertForbidden();
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Sumber Perolehan Mobile',
            'kode' => 'pembaca_sumber_perolehan_barang_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Sumber Perolehan Mobile',
            'username' => 'pembaca.sumber.perolehan.mobile',
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
        return $pengguna->createToken('Perangkat Sumber Perolehan', ['mobile'])->plainTextToken;
    }
}
