<?php

namespace Tests\Feature\Api;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AutentikasiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengguna_aktif_dapat_login_dan_menerima_token_sanctum(): void
    {
        $pengguna = $this->buatPengguna([
            'wajib_ganti_kata_sandi' => true,
        ]);

        $response = $this->postJson(route('api.v1.auth.login'), [
            'username' => $pengguna->username,
            'password' => 'RahasiaNusa123',
            'device_name' => 'Pixel 7 Emulator',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Selamat datang di NUSA.')
            ->assertJsonPath('pengguna.id', $pengguna->id)
            ->assertJsonPath('pengguna.nama', $pengguna->nama)
            ->assertJsonPath('pengguna.wajib_ganti_kata_sandi', true)
            ->assertJsonStructure([
                'token',
                'pengguna' => ['id', 'nama', 'username', 'jenis_akun', 'peran', 'izin'],
            ]);

        $this->assertStringContainsString('|', $response->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $pengguna->id,
            'tokenable_type' => Pengguna::class,
            'name' => 'Pixel 7 Emulator',
        ]);
        $this->assertDatabaseHas('riwayat_login', [
            'pengguna_id' => $pengguna->id,
            'username' => $pengguna->username,
            'berhasil' => true,
        ]);
        $this->assertNotNull($pengguna->fresh()->terakhir_login_pada);
    }

    public function test_login_ditolak_dengan_pesan_umum_dan_tetap_dicatat(): void
    {
        $pengguna = $this->buatPengguna();

        $this->postJson(route('api.v1.auth.login'), [
            'username' => $pengguna->username,
            'password' => 'kata-sandi-salah',
            'device_name' => 'Pixel 7 Emulator',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'Username atau kata sandi tidak sesuai.');

        $this->assertDatabaseHas('riwayat_login', [
            'pengguna_id' => $pengguna->id,
            'username' => $pengguna->username,
            'berhasil' => false,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_profil_memerlukan_token_dan_token_akun_nonaktif_dicabut(): void
    {
        $pengguna = $this->buatPengguna();
        $token = $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;

        $this->getJson(route('api.v1.auth.saya'))
            ->assertUnauthorized();

        $this->withToken($token)
            ->getJson(route('api.v1.auth.saya'))
            ->assertOk()
            ->assertJsonPath('pengguna.username', $pengguna->username);

        $pengguna->forceFill(['aktif' => false])->save();

        $this->withToken($token)
            ->getJson(route('api.v1.auth.saya'))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Akun Anda sedang tidak aktif.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_pengguna_dapat_mengganti_kata_sandi_dan_status_wajib_dibersihkan(): void
    {
        $pengguna = $this->buatPengguna([
            'kata_sandi_awal' => 'RahasiaNusa123',
            'wajib_ganti_kata_sandi' => true,
        ]);
        $token = $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;

        $this->withToken($token)
            ->putJson(route('api.v1.auth.kata-sandi.update'), [
                'kata_sandi_lama' => 'RahasiaNusa123',
                'kata_sandi_baru' => 'KataSandiBaru456',
                'kata_sandi_baru_confirmation' => 'KataSandiBaru456',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Kata sandi berhasil diganti.')
            ->assertJsonPath('pengguna.wajib_ganti_kata_sandi', false);

        $pengguna->refresh();

        $this->assertTrue(Hash::check('KataSandiBaru456', $pengguna->kata_sandi));
        $this->assertNull($pengguna->kata_sandi_awal);
        $this->assertFalse($pengguna->wajib_ganti_kata_sandi);

        $this->withToken($token)
            ->getJson(route('api.v1.auth.saya'))
            ->assertOk();
    }

    public function test_logout_hanya_mencabut_token_yang_sedang_digunakan(): void
    {
        $pengguna = $this->buatPengguna();
        $tokenSaatIni = $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
        $pengguna->createToken('Samsung Guru', ['mobile']);

        $this->withToken($tokenSaatIni)
            ->postJson(route('api.v1.auth.logout'))
            ->assertOk()
            ->assertJsonPath('message', 'Anda telah keluar dari NUSA.');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $pengguna->id,
            'name' => 'Samsung Guru',
        ]);
    }

    public function test_login_ulang_di_perangkat_yang_sama_mengganti_token_lama(): void
    {
        $pengguna = $this->buatPengguna();
        $pengguna->createToken('Pixel 7 Emulator', ['mobile']);

        $this->postJson(route('api.v1.auth.login'), [
            'username' => $pengguna->username,
            'password' => 'RahasiaNusa123',
            'device_name' => 'Pixel 7 Emulator',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_dibatasi_per_username_dan_alamat_ip(): void
    {
        $pengguna = $this->buatPengguna();
        $payload = [
            'username' => $pengguna->username,
            'password' => 'kata-sandi-salah',
            'device_name' => 'Pixel 7 Emulator',
        ];

        foreach (range(1, 5) as $percobaan) {
            $this->postJson(route('api.v1.auth.login'), $payload)
                ->assertUnprocessable();
        }

        $this->postJson(route('api.v1.auth.login'), $payload)
            ->assertTooManyRequests();
    }

    public function test_token_tanpa_ability_mobile_tidak_dapat_mengakses_api_mobile(): void
    {
        $pengguna = $this->buatPengguna();
        $token = $pengguna->createToken('Integrasi lain', ['integrasi'])->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.auth.saya'))
            ->assertForbidden();
    }

    private function buatPengguna(array $atribut = []): Pengguna
    {
        return Pengguna::create(array_merge([
            'nama' => 'Pengguna Mobile Uji',
            'username' => 'mobile.uji',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ], $atribut));
    }
}
