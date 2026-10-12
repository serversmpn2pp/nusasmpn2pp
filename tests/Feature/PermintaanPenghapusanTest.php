<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PermintaanPenghapusanTest extends TestCase
{
    public function test_halaman_publik_tanpa_sesi_database_cookie_atau_pengiriman_email(): void
    {
        config(['session.driver' => 'database']);
        Mail::fake();
        $query = [];
        DB::listen(function ($event) use (&$query): void {
            $query[] = $event->sql;
        });

        $response = $this->get(route('penghapusan-akun'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('Permintaan Penghapusan Akun/Data NUSA')
            ->assertSee('NUSA SMPN 2 Padang Panjang')
            ->assertSee('SMP Negeri 2 Padang Panjang')
            ->assertSee('id.sch.smpn2padangpanjang.nusa')
            ->assertSee('official@smpn2padangpanjang.sch.id')
            ->assertSee('penghapusan data tertentu tanpa menghapus akun')
            ->assertDontSee('<script', false)
            ->assertDontSee('<form', false)
            ->assertDontSee('fonts.googleapis.com', false);

        $this->assertSame([], $query);
        $this->assertSame([], $response->headers->getCookies());
        Mail::assertNothingOutgoing();
    }

    public function test_headers_dan_email_tidak_membutuhkan_skrip_cloudflare(): void
    {
        $response = $this->get(route('penghapusan-akun'))
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'")
            ->assertSee('href="mailto:official@smpn2padangpanjang.sch.id"', false)
            ->assertDontSee('data-cfemail', false)
            ->assertDontSee('/cdn-cgi/l/email-protection', false)
            ->assertDontSee('email-decode.min.js', false);

        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-transform'));
        $login = $this->get(route('login'))->assertOk();
        $this->assertFalse($login->headers->hasCacheControlDirective('no-transform'));
    }

    public function test_template_email_kosong_dan_input_url_tidak_disalin(): void
    {
        $response = $this->get(route('penghapusan-akun', [
            'token' => 'token-rahasia-jangan-salin',
            'nama' => '<script>identitas-rahasia</script>',
        ]))
            ->assertOk()
            ->assertDontSee('token-rahasia-jangan-salin')
            ->assertDontSee('identitas-rahasia')
            ->assertDontSee('<script', false);

        preg_match('/href="(mailto:[^"]+\?[^\"]+)"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);
        $mailto = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $this->assertStringStartsWith('mailto:official@smpn2padangpanjang.sch.id?', $mailto);
        parse_str(parse_url($mailto, PHP_URL_QUERY), $query);
        $this->assertSame('Permintaan Penghapusan Akun/Data NUSA', $query['subject']);
        $this->assertStringContainsString("Nama pemohon: \n", $query['body']);
        $this->assertStringContainsString('hapus data tertentu', $query['body']);
        $this->assertStringContainsString('Jangan lampirkan kata sandi', $query['body']);
    }

    public function test_halaman_terbuka_untuk_akun_nonaktif_tanpa_memuat_identitas(): void
    {
        $pengguna = new Pengguna;
        $pengguna->forceFill([
            'id' => 123,
            'nama' => 'Identitas privat jangan tampil',
            'username' => 'akun-privat-jangan-tampil',
            'aktif' => false,
            'wajib_ganti_kata_sandi' => true,
        ]);

        $this->actingAs($pengguna)
            ->get(route('penghapusan-akun'))
            ->assertOk()
            ->assertDontSee('Identitas privat jangan tampil')
            ->assertDontSee('akun-privat-jangan-tampil');
    }

    public function test_halaman_menjelaskan_arsip_retensi_dan_bukan_penghapusan_otomatis(): void
    {
        $response = $this->get(route('penghapusan-akun'))
            ->assertOk()
            ->assertSee('ditinjau manual')
            ->assertSee('Penghapusan berbeda dari penonaktifan akun.')
            ->assertSee('tidak otomatis menghapus nilai')
            ->assertSee('masa simpan 5 tahun')
            ->assertSee('tidak mengubah masa simpan tersebut menjadi penyimpanan tanpa batas')
            ->assertSee('perkiraan waktu pelaksanaan')
            ->assertSee('masa simpan atau status permanennya')
            ->assertSee('Jangan kirim kata sandi')
            ->assertSee('href="'.route('kebijakan-privasi').'"', false);

        foreach (range(1, 5) as $nomor) {
            $response->assertSee('<h2>'.$nomor.'. ', false);
        }
    }

    public function test_login_dan_kebijakan_privasi_memiliki_tautan_permohonan(): void
    {
        foreach (['login', 'kebijakan-privasi'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('href="'.route('penghapusan-akun').'"', false)
                ->assertSee('Permintaan Penghapusan Akun/Data');
        }
    }

    public function test_route_hanya_informasi_get_head_bukan_endpoint_penghapusan(): void
    {
        $this->head(route('penghapusan-akun'))->assertOk();
        $this->post(route('penghapusan-akun'), ['username' => 'akun-contoh'])->assertStatus(405);
        $this->delete(route('penghapusan-akun'))->assertStatus(405);
    }

    public function test_tautan_di_naskah_privasi_tidak_bergantung_pada_trailing_slash(): void
    {
        $this->get('/kebijakan-privasi/')
            ->assertOk()
            ->assertSee('href="'.route('penghapusan-akun').'">Permintaan Penghapusan Akun/Data NUSA</a>', false)
            ->assertDontSee('href="penghapusan-akun"', false);
    }
}
