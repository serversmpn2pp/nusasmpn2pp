<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\KategoriPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KategoriPembinaanSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_dan_mengirim_ringkasan_filter_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->getJson(route('api.v1.kategori-pembinaan-siswa.index'))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.kategori-pembinaan-siswa.index', [
                'cari' => 'disiplin',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.cari', 'disiplin')
            ->assertJsonPath('data.filter.status', 'aktif')
            ->assertJsonPath('data.ringkasan.total', 9)
            ->assertJsonPath('data.ringkasan.aktif', 9)
            ->assertJsonPath('data.ringkasan.nonaktif', 0)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.kode', 'KEDISIPLINAN')
            ->assertJsonStructure([
                'data' => [
                    'items' => [[
                        'id',
                        'nama',
                        'kode',
                        'deskripsi',
                        'aktif',
                        'jumlah_laporan',
                        'jumlah_jenis_pelanggaran',
                    ]],
                    'paginasi',
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'kategori-pembinaan-non-poin',
                'status' => 'tersedia',
                'rute' => '/kategori-pembinaan-siswa',
            ]);
    }

    public function test_administrator_dapat_menambah_mengubah_dan_menonaktifkan_tanpa_menghapus_data(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.kategori-pembinaan-siswa.store'), [
                'nama' => '  Komunikasi Positif  ',
                'kode' => ' komunikasi positif ',
                'deskripsi' => '  Pembinaan komunikasi yang baik.  ',
                'aktif' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nama', 'Komunikasi Positif')
            ->assertJsonPath('data.kode', 'KOMUNIKASI_POSITIF')
            ->assertJsonPath('data.deskripsi', 'Pembinaan komunikasi yang baik.')
            ->assertJsonPath('data.aktif', true);
        $id = (int) $response->json('data.id');

        $this->withToken($token)
            ->patchJson(route('api.v1.kategori-pembinaan-siswa.update', $id), [
                'nama' => 'Komunikasi dan Etika',
                'kode' => 'komunikasi-etika',
                'deskripsi' => null,
                'aktif' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('kategori_pembinaan_siswa', [
            'id' => $id,
            'nama' => 'Komunikasi dan Etika',
            'kode' => 'KOMUNIKASI_ETIKA',
            'deskripsi' => null,
            'aktif' => true,
        ]);

        $this->withToken($token)
            ->deleteJson(route('api.v1.kategori-pembinaan-siswa.destroy', $id))
            ->assertOk();

        $this->assertDatabaseHas('kategori_pembinaan_siswa', [
            'id' => $id,
            'aktif' => false,
        ]);
        $this->assertSame(10, KategoriPembinaanSiswa::count());

        $this->withToken($token)
            ->getJson(route('api.v1.kategori-pembinaan-siswa.index', [
                'status' => 'nonaktif',
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.nonaktif', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.kode', 'KOMUNIKASI_ETIKA');
    }

    public function test_nama_dan_kode_harus_unik_setelah_kode_dirapikan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.kategori-pembinaan-siswa.store'), [
                'nama' => 'Kedisiplinan',
                'kode' => 'kedisiplinan',
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nama', 'kode']);
    }

    public function test_izin_lihat_hanya_dapat_membuka_daftar_bukan_mengubah(): void
    {
        $pengguna = $this->penggunaDenganIzin('bk.lihat');
        $token = $this->token($pengguna);

        $this->withToken($token)
            ->getJson(route('api.v1.kategori-pembinaan-siswa.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);

        $this->withToken($token)
            ->postJson(route('api.v1.kategori-pembinaan-siswa.store'), [
                'nama' => 'Tidak Boleh Dibuat',
                'kode' => 'TIDAK_BOLEH',
                'aktif' => true,
            ])
            ->assertForbidden();
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca BK Mobile',
            'kode' => 'pembaca_bk_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca BK Mobile',
            'username' => 'pembaca.bk.mobile',
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
        return $pengguna->createToken('Perangkat Kategori Pembinaan', ['mobile'])->plainTextToken;
    }
}
