<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoginAlurTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_dengan_ingat_saya_membuat_cookie_remember(): void
    {
        $user = $this->user();
        $cookie = Auth::guard('web')->getRecallerName();

        $this->post(route('login.store'), [
            'username' => $user->username, 'password' => 'KataSandiUji123', 'ingat' => '1',
        ])->assertRedirect(route('beranda'))->assertCookie($cookie);

        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty($user->fresh()->remember_token);
    }

    public function test_login_tanpa_ingat_saya_tidak_membuat_cookie_remember(): void
    {
        $user = $this->user();
        $cookie = Auth::guard('web')->getRecallerName();

        $this->post(route('login.store'), [
            'username' => $user->username, 'password' => 'KataSandiUji123',
        ])->assertRedirect(route('beranda'))->assertCookieMissing($cookie);

        $this->assertAuthenticatedAs($user);
    }

    public function test_akun_nonaktif_tetap_ditolak_dengan_pesan_umum_dan_tanpa_password_lama(): void
    {
        $user = $this->user(false);

        $this->from(route('login'))->post(route('login.store'), [
            'username' => $user->username, 'password' => 'KataSandiUji123',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($this->app['session']->getOldInput('password'));
        $this->get(route('login'))->assertOk()
            ->assertSee('Username atau kata sandi tidak sesuai.')
            ->assertDontSee('KataSandiUji123');
    }

    public function test_kolom_wajib_tetap_divalidasi_di_server(): void
    {
        $this->from(route('login'))->post(route('login.store'), [])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username', 'password']);

        $this->assertGuest();
        $this->assertDatabaseCount('riwayat_login', 0);
    }

    public function test_form_login_tetap_dilindungi_csrf(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $token = str_repeat('a', 40);

        $this->withSession(['_token' => $token])->post(route('login.store'), ['_token' => 'salah'])
            ->assertStatus(419);
        $this->withSession(['_token' => $token])->post(route('login.store'), ['_token' => $token])
            ->assertRedirect()->assertSessionHasErrors(['username', 'password']);

        $this->assertDatabaseCount('riwayat_login', 0);
    }

    private function user(bool $aktif = true): Pengguna
    {
        return Pengguna::create([
            'nama' => 'Pegawai Uji Login', 'username' => '198001012010011001',
            'kata_sandi' => 'KataSandiUji123', 'peran' => 'pegawai', 'aktif' => $aktif, 'akun_sistem' => false,
        ]);
    }
}
