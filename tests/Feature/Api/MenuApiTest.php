<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_mobile_memerlukan_token(): void
    {
        $this->getJson(route('api.v1.menu'))
            ->assertUnauthorized();
    }

    public function test_administrator_menerima_katalog_menu_lengkap(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonPath('data.jumlah_menu', 74)
            ->assertJsonCount(7, 'data.kelompok')
            ->assertJsonPath('data.kelompok.0.kode', 'data-sekolah')
            ->assertJsonPath('data.kelompok.0.items.0.kode', 'tahun-pelajaran')
            ->assertJsonPath('data.kelompok.6.kode', 'sistem')
            ->assertJsonPath('data.kelompok.6.items.5.kode', 'backup-restore')
            ->assertJsonFragment([
                'kode' => 'pusat-cbt',
                'status' => 'segera_hadir',
                'rute' => null,
            ])
            ->assertJsonFragment([
                'kode' => 'siswa',
                'status' => 'tersedia',
                'rute' => '/siswa',
            ])
            ->assertJsonFragment([
                'kode' => 'kelas',
                'status' => 'tersedia',
                'rute' => '/kelas',
            ])
            ->assertJsonFragment([
                'kode' => 'jadwal-pelajaran',
                'status' => 'tersedia',
                'rute' => '/kelas?mode=jadwal',
            ])
            ->assertJsonFragment([
                'kode' => 'jam-pelajaran',
                'status' => 'tersedia',
                'rute' => '/jam-pelajaran',
            ])
            ->assertJsonFragment([
                'kode' => 'guru-mata-pelajaran',
                'status' => 'tersedia',
                'rute' => '/guru-mata-pelajaran',
            ])
            ->assertJsonFragment([
                'kode' => 'role-hak-akses',
                'status' => 'tersedia',
                'rute' => '/role-hak-akses',
            ])
            ->assertJsonMissingPath('data.kelompok.0.items.0.izin')
            ->assertJsonStructure([
                'data' => [
                    'dihasilkan_pada',
                    'jumlah_menu',
                    'kelompok' => [
                        '*' => [
                            'kode',
                            'label',
                            'deskripsi',
                            'ikon',
                            'items' => [
                                '*' => [
                                    'kode',
                                    'label',
                                    'deskripsi',
                                    'inisial',
                                    'subkelompok',
                                    'ikon',
                                    'status',
                                    'rute',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_menu_disaring_menurut_izin_pengguna(): void
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Data Pegawai Mobile',
            'kode' => 'pembaca_pegawai_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', 'pegawai.lihat')->firstOrFail());

        $pengguna = Pengguna::create([
            'nama' => 'Pengguna Terbatas',
            'username' => 'menu.terbatas',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach($peran);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonPath('data.jumlah_menu', 3)
            ->assertJsonFragment(['kode' => 'pegawai'])
            ->assertJsonFragment(['kode' => 'kartu-pegawai'])
            ->assertJsonFragment(['kode' => 'katalog-barang'])
            ->assertJsonMissing(['kode' => 'siswa'])
            ->assertJsonMissing(['kode' => 'akun-pegawai']);
    }

    public function test_menu_dikunci_sampai_kata_sandi_awal_diganti(): void
    {
        $pengguna = Pengguna::create([
            'nama' => 'Pengguna Wajib Ganti',
            'username' => 'menu.wajib.ganti',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => true,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.menu'))
            ->assertStatus(428)
            ->assertJsonPath('wajib_ganti_kata_sandi', true);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
