<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\RiwayatLogin;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AktivitasLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_login_berhasil_memperbarui_waktu_dan_mencatat_riwayat(): void
    {
        Carbon::setTestNow('2026-08-22 07:15:30');

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.25'])
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/140.0')
            ->post(route('login.store'), [
                'username' => 'administrator',
                'password' => 'administrator',
            ]);

        $response->assertRedirect(route('beranda'));

        $administrator = Pengguna::query()->where('username', 'administrator')->firstOrFail();

        $this->assertSame('2026-08-22 07:15:30', $administrator->terakhir_login_pada?->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('riwayat_login', [
            'pengguna_id' => $administrator->id,
            'username' => 'administrator',
            'berhasil' => true,
            'alamat_ip' => '192.168.1.25',
        ]);

        $riwayat = RiwayatLogin::query()->firstOrFail();
        $this->assertSame('Windows - Chrome', $riwayat->labelPerangkat());
        $this->assertStringNotContainsString('administrator', $riwayat->getAttributes()['user_agent']);
        $this->assertArrayNotHasKey('password', $riwayat->getAttributes());
        $this->assertArrayNotHasKey('kata_sandi', $riwayat->getAttributes());
    }

    public function test_login_gagal_akun_dikenal_dan_tidak_dikenal_tetap_dicatat(): void
    {
        $administrator = Pengguna::query()->where('username', 'administrator')->firstOrFail();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => 'administrator',
                'password' => 'salah-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => 'akun-tidak-ada',
                'password' => 'salah-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertDatabaseHas('riwayat_login', [
            'pengguna_id' => $administrator->id,
            'username' => 'administrator',
            'berhasil' => false,
        ]);
        $this->assertDatabaseHas('riwayat_login', [
            'pengguna_id' => null,
            'username' => 'akun-tidak-ada',
            'berhasil' => false,
        ]);
        $this->assertGuest();
    }

    public function test_administrator_dapat_melihat_daftar_pengguna_dan_riwayat_login(): void
    {
        $administrator = Pengguna::query()->where('username', 'administrator')->firstOrFail();
        $pengguna = $this->buatPengguna('Guru Uji Aktivitas', '19870001');

        $pengguna->forceFill(['terakhir_login_pada' => now()->subHour()])->save();
        RiwayatLogin::create([
            'pengguna_id' => $pengguna->id,
            'username' => $pengguna->username,
            'berhasil' => true,
            'alamat_ip' => '10.10.10.20',
            'user_agent' => 'Mozilla/5.0 (Android 14) Chrome/140.0',
        ]);

        $this->actingAs($administrator)
            ->get(route('aktivitas-login.index'))
            ->assertOk()
            ->assertSee('Aktivitas Login')
            ->assertSee('Guru Uji Aktivitas')
            ->assertSee('19870001')
            ->assertSee('Lihat riwayat');

        $this->actingAs($administrator)
            ->get(route('aktivitas-login.index', [
                'tampilan' => 'riwayat',
                'kata_kunci' => '19870001',
            ]))
            ->assertOk()
            ->assertSee('Riwayat percobaan login')
            ->assertSee('Android - Chrome')
            ->assertSee('10.10.10.20');
    }

    public function test_pengguna_tanpa_permission_tidak_dapat_melihat_aktivitas_login(): void
    {
        $pengguna = $this->buatPengguna('Pegawai Biasa', 'pegawai-biasa');

        $this->actingAs($pengguna)
            ->get(route('aktivitas-login.index'))
            ->assertForbidden();
    }

    public function test_filter_dapat_menampilkan_akun_yang_belum_pernah_login(): void
    {
        $administrator = Pengguna::query()->where('username', 'administrator')->firstOrFail();
        $belumLogin = $this->buatPengguna('Belum Pernah Masuk', 'belum-login');
        $sudahLogin = $this->buatPengguna('Sudah Pernah Masuk', 'sudah-login');
        $sudahLogin->forceFill(['terakhir_login_pada' => now()])->save();

        $response = $this->actingAs($administrator)
            ->get(route('aktivitas-login.index', ['status_login' => 'belum']));

        $response->assertOk()
            ->assertSee($belumLogin->nama)
            ->assertDontSee($sudahLogin->nama);
    }

    private function buatPengguna(string $nama, string $username): Pengguna
    {
        return Pengguna::create([
            'nama' => $nama,
            'username' => $username,
            'kata_sandi' => Hash::make('password-uji'),
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
    }
}
