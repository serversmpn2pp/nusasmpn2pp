<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KebijakanPrivasiTest extends TestCase
{
    public function test_halaman_html_publik_dapat_dibuka_tanpa_login_sesi_atau_query_database(): void
    {
        config(['session.driver' => 'database']);
        $query = [];
        DB::listen(function ($event) use (&$query): void {
            $query[] = $event->sql;
        });

        $response = $this->get(route('kebijakan-privasi'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('Kebijakan Privasi NUSA')
            ->assertSee('Berlaku mulai')
            ->assertSee('<time datetime="2026-10-08">8 Oktober 2026</time>', false)
            ->assertSee('official@smpn2padangpanjang.sch.id')
            ->assertSee('5 tahun')
            ->assertSee('Pengamanan akses foto belum diterapkan pada tahap ini.')
            ->assertSee('Google Firebase Cloud Messaging')
            ->assertSee('Google ML Kit')
            ->assertSee('Cloudflare')
            ->assertDontSee('fonts.googleapis.com', false)
            ->assertDontSee('<script', false);

        $this->assertSame([], $query);
        $this->assertSame([], $response->headers->getCookies());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_label_draf_dihapus_tanpa_menghilangkan_keterangan_kondisi_data(): void
    {
        $response = $this->get(route('kebijakan-privasi'))
            ->assertOk()
            ->assertSee('Isi kebijakan privasi')
            ->assertSee('Kebijakan ini berlaku mulai', false)
            ->assertSee('pihak yang mengetahui tautan foto dapat membukanya tanpa login')
            ->assertSee('Awal perhitungan per kategori')
            ->assertSee('masih perlu ditetapkan sekolah')
            ->assertSee('Penetapan masa simpan tersebut tidak berarti penghapusan otomatis sudah tersedia.')
            ->assertDontSee('sebelum kebijakan disahkan');

        $this->assertDoesNotMatchRegularExpression('/\b(draf|draft)\b/iu', $response->getContent());
    }

    public function test_halaman_tetap_publik_pada_akun_nonaktif_dan_wajib_ganti_sandi_tanpa_memuat_identitasnya(): void
    {
        $pengguna = new Pengguna;
        $pengguna->forceFill([
            'id' => 123,
            'nama' => 'Identitas privat tidak boleh tampil',
            'username' => 'akun-privat-123',
            'aktif' => false,
            'wajib_ganti_kata_sandi' => true,
        ]);

        $this->actingAs($pengguna)
            ->get(route('kebijakan-privasi'))
            ->assertOk()
            ->assertDontSee('Identitas privat tidak boleh tampil')
            ->assertDontSee('akun-privat-123');
    }

    public function test_input_url_tidak_disalin_ke_html_dan_semua_bagian_naskah_tersedia(): void
    {
        $response = $this->get(route('kebijakan-privasi', [
            'token' => 'token-rahasia-contoh',
            'nama' => '<script>alert(1)</script>',
        ]))
            ->assertOk()
            ->assertDontSee('token-rahasia-contoh')
            ->assertDontSee('<script', false);

        foreach (range(1, 12) as $nomor) {
            $response->assertSee('<h2>'.$nomor.'. ', false);
        }
    }

    public function test_login_web_menyediakan_tautan_privasi_tanpa_mengharuskan_login(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('href="'.route('kebijakan-privasi').'"', false)
            ->assertSee('Kebijakan Privasi NUSA');
    }
}
