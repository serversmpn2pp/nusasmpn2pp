<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginTampilanTest extends TestCase
{
    public function test_login_memakai_desain_sketsa_dan_form_asli_dengan_csrf(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('<html lang="id" translate="no" class="notranslate">', false)
            ->assertSee('Masuk ke NUSA')
            ->assertSee('css/login.css?v=', false)
            ->assertSee('js/login.js?v=', false)
            ->assertSee('placeholder="NIP/NISN/ORT-NISN"', false)
            ->assertSee('Satu pintu. Terhubung untuk')
            ->assertSee('Hubungi operator sekolah.')
            ->assertDontSee('Siswa menggunakan NISN')
            ->assertDontSee('preview.js')
            ->assertDontSee('lucide.min.js')
            ->assertDontSee('login-sekolah.jpg');

        $xpath = $this->xpath($response);
        $form = $xpath->query('//form[@data-login-form]')->item(0);
        $this->assertSame('POST', $form?->getAttribute('method'));
        $this->assertSame(route('login.store'), $form?->getAttribute('action'));
        $this->assertNotEmpty($xpath->query('//input[@name="_token"]')->item(0)?->getAttribute('value'));
        $this->assertSame('username', $xpath->query('//input[@id="username"]')->item(0)?->getAttribute('autocomplete'));
        $this->assertSame('current-password', $xpath->query('//input[@id="password"]')->item(0)?->getAttribute('autocomplete'));
        $this->assertSame('ingat', $xpath->query('//input[@id="ingat"]')->item(0)?->getAttribute('name'));
        $this->assertSame(route('kebijakan-privasi'), $xpath->query('//nav[@aria-label="Informasi akun"]/a')->item(0)?->getAttribute('href'));
        $this->assertSame(route('penghapusan-akun'), $xpath->query('//nav[@aria-label="Informasi akun"]/a')->item(1)?->getAttribute('href'));

        foreach (['css/login.css', 'js/login.js', 'images/nusa/login/sekolah-sketsa.webp', 'images/nusa/login/user-round.svg', 'images/nusa/login/lock-keyhole.svg', 'images/nusa/login/arrow-right.svg', 'images/nusa/login/circle-help.svg', 'images/nusa/login/download.svg'] as $asset) {
            $this->assertFileExists(public_path($asset));
        }
        $this->assertLessThan(800_000, filesize(public_path('images/nusa/login/sekolah-sketsa.webp')));
        $this->capture('normal', $response);
    }

    public function test_halaman_login_ditandai_agar_tidak_diterjemahkan(): void
    {
        $response = $this->get(route('login'))->assertOk();
        $xpath = $this->xpath($response);

        $this->assertSame('no', $xpath->query('/html')->item(0)?->getAttribute('translate'));
        $this->assertSame('notranslate', $xpath->query('/html')->item(0)?->getAttribute('class'));
        $this->assertSame('notranslate', $xpath->query('//meta[@name="google"]')->item(0)?->getAttribute('content'));
        $this->assertSame('no', $xpath->query('//body')->item(0)?->getAttribute('translate'));
        $this->assertStringContainsString('notranslate', $xpath->query('//body')->item(0)?->getAttribute('class'));
        $this->assertSame(0, $xpath->query('//*[@translate="yes"]')->length);
    }

    public function test_tombol_pasang_berada_di_bawah_tautan_privasi_dan_di_luar_form_login(): void
    {
        $response = $this->get(route('login'))->assertOk();
        $xpath = $this->xpath($response);

        $this->assertSame(1, $xpath->query('//nav[@aria-label="Informasi akun"]/following-sibling::div[@data-pwa-install-container]')->length);
        $this->assertSame(0, $xpath->query('//form//button[@data-pwa-install]')->length);
        $this->assertSame(0, $xpath->query('//header//button[@data-pwa-install]')->length);
        $this->assertTrue($xpath->query('//div[@data-pwa-install-container]')->item(0)?->hasAttribute('hidden'));
        $this->assertSame('button', $xpath->query('//button[@data-pwa-install]')->item(0)?->getAttribute('type'));
        $this->assertSame('Pasang NUSA', $xpath->query('//button[@data-pwa-install]/span[@class="install-label"]')->item(0)?->textContent);
    }

    public function test_username_lama_dan_ingat_saya_dikembalikan_tetapi_password_tidak(): void
    {
        $response = $this->withSession(['_old_input' => [
            'username' => 'ORT-0012345678',
            'ingat' => '1',
            'password' => 'RAHASIA-TIDAK-BOLEH-DITAMPILKAN',
        ]])->get(route('login'));

        $response->assertOk()->assertDontSee('RAHASIA-TIDAK-BOLEH-DITAMPILKAN');
        $xpath = $this->xpath($response);
        $this->assertSame('ORT-0012345678', $xpath->query('//input[@id="username"]')->item(0)?->getAttribute('value'));
        $this->assertTrue($xpath->query('//input[@id="ingat"]')->item(0)?->hasAttribute('checked'));
        $this->assertFalse($xpath->query('//input[@id="password"]')->item(0)?->hasAttribute('value'));
    }

    public function test_pesan_gagal_login_terhubung_dengan_username(): void
    {
        $response = $this->withSession([
            'errors' => $this->errors(['username' => 'Username atau kata sandi tidak sesuai.']),
            '_old_input' => ['username' => '0012345678'],
        ])->get(route('login'));

        $response->assertOk()->assertSee('Username atau kata sandi tidak sesuai.');
        $xpath = $this->xpath($response);
        $username = $xpath->query('//input[@id="username"]')->item(0);
        $this->assertSame('true', $username?->getAttribute('aria-invalid'));
        $this->assertSame('username_error', $username?->getAttribute('aria-describedby'));
        $this->assertSame(1, $xpath->query('//*[@id="username_error"]')->length);
        $this->assertFalse($xpath->query('//button[@data-login-submit]')->item(0)?->hasAttribute('disabled'));
        $this->capture('username-error', $response);
    }

    public function test_validasi_password_tetap_menggunakan_komponen_yang_sama(): void
    {
        $response = $this->withSession([
            'errors' => $this->errors(['password' => 'Kata sandi wajib diisi.']),
        ])->get(route('login'));

        $response->assertOk()->assertSee('Kata sandi wajib diisi.')->assertSee('Caps Lock aktif.');
        $xpath = $this->xpath($response);
        $password = $xpath->query('//input[@id="password"]')->item(0);
        $this->assertSame('true', $password?->getAttribute('aria-invalid'));
        $this->assertStringContainsString('password_error', $password?->getAttribute('aria-describedby'));
        $this->assertSame(1, $xpath->query('//*[@id="password_error"]')->length);
        $this->assertSame(1, $xpath->query('//button[@data-password-toggle][@aria-controls="password"]')->length);
        $this->capture('password-error', $response);
    }

    public function test_pesan_sesi_dan_username_di_escape(): void
    {
        $response = $this->withSession([
            'gagal' => 'Identitas sesi berubah. Silakan masuk kembali untuk melindungi akun Anda.',
            '_old_input' => ['username' => '\"><script>alert(1)</script>'],
        ])->get(route('login'));

        $response->assertOk()
            ->assertSee('Identitas sesi berubah.')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('role="alert"', false);
        $this->capture('session-error', $response);
    }

    public function test_pesan_validasi_umum_tetap_ditampilkan(): void
    {
        $this->withSession([
            'errors' => $this->errors(['login' => 'Permintaan masuk belum dapat diproses.']),
        ])->get(route('login'))->assertOk()
            ->assertSee('Permintaan masuk belum dapat diproses.')
            ->assertSee('class="login-alert" role="alert"', false);
    }

    private function errors(array $messages): array
    {
        // JSON sessions restore this serialized bag when the request starts.
        return ['default' => ['format' => ':message', 'messages' => collect($messages)->map(fn ($message) => [$message])->all()]];
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function capture(string $name, TestResponse $response): void
    {
        if (! getenv('NUSA_CAPTURE_LOGIN')) {
            return;
        }
        $directory = storage_path('framework/testing/login');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/'.$name.'.html', $response->getContent());
    }
}
