<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeranApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_peran_memerlukan_token_dan_membedakan_izin_baca_dengan_kelola(): void
    {
        $this->getJson(route('api.v1.peran.index'))->assertUnauthorized();

        $pembaca = $this->penggunaDenganIzin('peran.lihat', 'pembaca_peran_api');
        $token = $this->token($pembaca);

        $this->withToken($token)
            ->getJson(route('api.v1.peran.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($token)
            ->getJson(route('api.v1.peran.show', Peran::where('kode', 'pegawai')->firstOrFail()))
            ->assertOk();
        $this->withToken($token)
            ->getJson(route('api.v1.peran.referensi'))
            ->assertForbidden();
        $this->withToken($token)
            ->postJson(route('api.v1.peran.store'), ['nama' => 'Role Terlarang'])
            ->assertForbidden();
    }

    public function test_daftar_peran_dapat_dicari_difilter_dan_memuat_ringkasan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $peran = Peran::create([
            'nama' => 'Koordinator Literasi Mobile',
            'kode' => 'koordinator_literasi_mobile',
            'deskripsi' => 'Role tambahan untuk program literasi.',
            'aktif' => false,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', 'pegawai.lihat')->firstOrFail());

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.peran.index', [
                'cari' => 'literasi',
                'status' => 'nonaktif',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.kode', 'koordinator_literasi_mobile')
            ->assertJsonPath('data.items.0.aktif', false)
            ->assertJsonPath('data.items.0.jumlah_izin', 1)
            ->assertJsonPath('data.filter.status', 'nonaktif')
            ->assertJsonPath('data.ringkasan.tambahan', 1)
            ->assertJsonPath('data.paginasi.total', 1);
    }

    public function test_referensi_dan_detail_memuat_kelompok_izin_dan_izin_terpilih(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = Peran::where('kode', 'pegawai')->firstOrFail();
        $roleAdministrator = Peran::where('kode', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $this->withToken($token)
            ->getJson(route('api.v1.peran.referensi'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonFragment(['nama' => 'Akun'])
            ->assertJsonFragment(['kode' => 'peran.kelola']);

        $this->withToken($token)
            ->getJson(route('api.v1.peran.show', $pegawai))
            ->assertOk()
            ->assertJsonPath('data.peran.kode', 'pegawai')
            ->assertJsonPath('data.peran.sistem', true)
            ->assertJsonStructure([
                'data' => [
                    'peran' => ['id', 'nama', 'kode', 'izin_ids'],
                    'kelompok_izin' => [['nama', 'izin']],
                ],
            ]);

        $jumlahIzinAktif = Izin::where('aktif', true)->count();
        $this->withToken($token)
            ->getJson(route('api.v1.peran.show', $roleAdministrator))
            ->assertOk()
            ->assertJsonPath('data.peran.jumlah_izin', $jumlahIzinAktif)
            ->assertJsonPath('data.peran.persentase_izin', 100)
            ->assertJsonCount($jumlahIzinAktif, 'data.peran.izin_ids');
    }

    public function test_administrator_dapat_membuat_peran_dengan_kode_otomatis_dan_validasi_izin(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $izin = Izin::where('kode', 'pegawai.lihat')->firstOrFail();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.peran.store'), [
                'nama' => 'Koordinator Literasi Sekolah',
                'deskripsi' => 'Mengelola program literasi sekolah.',
                'aktif' => true,
                'izin_ids' => [$izin->id],
            ])
            ->assertCreated()
            ->assertJsonPath('pesan', 'Peran baru berhasil ditambahkan.');

        $peran = Peran::findOrFail($response->json('data.id'));
        $this->assertSame('koordinator_literasi_sekolah', $peran->kode);
        $this->assertTrue($peran->aktif);
        $this->assertTrue($peran->izin()->whereKey($izin->id)->exists());

        $this->withToken($token)
            ->postJson(route('api.v1.peran.store'), [
                'nama' => 'Kode Salah',
                'kode' => 'Kode Tidak Valid',
                'izin_ids' => [999999],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kode', 'izin_ids.0']);
    }

    public function test_peran_dapat_diubah_dan_dinonaktifkan_dengan_perlindungan_role_sistem(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $izinLihat = Izin::where('kode', 'pegawai.lihat')->firstOrFail();
        $izinKelola = Izin::where('kode', 'pegawai.kelola')->firstOrFail();
        $tambahan = Peran::create([
            'nama' => 'Role Tambahan Mobile',
            'kode' => 'role_tambahan_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->patchJson(route('api.v1.peran.update', $tambahan), [
                'nama' => 'Role Tambahan Diperbarui',
                'kode' => 'role_tambahan_baru',
                'deskripsi' => 'Sudah diperbarui.',
                'aktif' => true,
                'izin_ids' => [$izinLihat->id, $izinKelola->id],
            ])
            ->assertOk();
        $this->assertDatabaseHas('peran', [
            'id' => $tambahan->id,
            'kode' => 'role_tambahan_baru',
        ]);
        $this->assertSame(2, $tambahan->izin()->count());

        $this->withToken($token)
            ->deleteJson(route('api.v1.peran.destroy', $tambahan))
            ->assertOk();
        $this->assertFalse($tambahan->fresh()->aktif);

        $roleAdministrator = Peran::where('kode', 'administrator')->firstOrFail();
        $jumlahIzinAktif = Izin::where('aktif', true)->count();
        $this->withToken($token)
            ->patchJson(route('api.v1.peran.update', $roleAdministrator), [
                'nama' => 'Administrator NUSA',
                'kode' => 'kode_diacuhkan',
                'aktif' => false,
                'izin_ids' => [$izinLihat->id],
            ])
            ->assertOk();
        $roleAdministrator->refresh();
        $this->assertSame('administrator', $roleAdministrator->kode);
        $this->assertTrue($roleAdministrator->aktif);
        $this->assertSame($jumlahIzinAktif, $roleAdministrator->izin()->count());

        $this->withToken($token)
            ->deleteJson(route('api.v1.peran.destroy', $roleAdministrator))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('peran');
    }

    private function penggunaDenganIzin(string $kodeIzin, string $kodePeran): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Role API',
            'kode' => $kodePeran,
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());

        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Role API',
            'username' => 'pembaca.role.api',
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
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
