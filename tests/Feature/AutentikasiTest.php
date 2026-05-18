<?php

namespace Tests\Feature;

use Tests\TestCase;

class AutentikasiTest extends TestCase
{
    public function test_halaman_login_dapat_dibuka(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Masuk ke aplikasi');
    }

    public function test_halaman_pegawai_dikunci_sebelum_login(): void
    {
        $response = $this->get('/pegawai');

        $response->assertRedirect(route('login'));
    }
}
