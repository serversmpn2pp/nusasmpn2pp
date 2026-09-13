<?php

namespace Tests\Feature\Api;

use App\Models\Pengguna;
use App\Models\PerangkatNotifikasiPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PerangkatNotifikasiPushApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengguna_dapat_mendaftarkan_dan_menonaktifkan_perangkatnya(): void
    {
        $pengguna = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($pengguna);
        $tokenPerangkat = 'fcm-token-perangkat-admin-123';

        $this->withToken($token)
            ->postJson(route('api.v1.notifikasi.perangkat.store'), [
                'token' => $tokenPerangkat,
                'platform' => 'android',
                'nama_perangkat' => 'Pixel 7 Emulator',
            ])
            ->assertOk()
            ->assertJsonPath('data.aktif', true);

        $perangkat = PerangkatNotifikasiPush::query()->firstOrFail();
        $this->assertSame($pengguna->id, $perangkat->pengguna_id);
        $this->assertSame('android', $perangkat->platform);
        $this->assertSame('Pixel 7 Emulator', $perangkat->nama_perangkat);
        $this->assertTrue($perangkat->aktif);
        $this->assertNotNull($perangkat->terakhir_terlihat_pada);

        $this->withToken($token)
            ->deleteJson(route('api.v1.notifikasi.perangkat.destroy'), [
                'token' => $tokenPerangkat,
            ])
            ->assertOk()
            ->assertJsonPath('data.jumlah_dinonaktifkan', 1);

        $this->assertFalse($perangkat->fresh()->aktif);
    }

    public function test_token_perangkat_berpindah_ke_pengguna_yang_terakhir_login(): void
    {
        $penggunaPertama = Pengguna::where('username', 'administrator')->firstOrFail();
        $penggunaKedua = Pengguna::create([
            'nama' => 'Pegawai Kedua',
            'username' => 'pegawai.kedua',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $tokenPerangkat = 'fcm-token-perangkat-bersama-456';

        foreach ([$penggunaPertama, $penggunaKedua] as $pengguna) {
            Auth::forgetGuards();
            $this->withToken($this->token($pengguna))
                ->postJson(route('api.v1.notifikasi.perangkat.store'), [
                    'token' => $tokenPerangkat,
                    'platform' => 'android',
                ])
                ->assertOk();
        }

        $this->assertDatabaseCount('perangkat_notifikasi_push', 1);
        $this->assertDatabaseHas('perangkat_notifikasi_push', [
            'token' => $tokenPerangkat,
            'pengguna_id' => $penggunaKedua->id,
            'aktif' => true,
        ]);
    }

    public function test_perangkat_push_memerlukan_token_mobile_dan_data_valid(): void
    {
        $this->postJson(route('api.v1.notifikasi.perangkat.store'), [])
            ->assertUnauthorized();

        $pengguna = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($pengguna))
            ->postJson(route('api.v1.notifikasi.perangkat.store'), [
                'token' => '',
                'platform' => 'windows',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'platform']);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pengujian Push', ['mobile'])->plainTextToken;
    }
}
