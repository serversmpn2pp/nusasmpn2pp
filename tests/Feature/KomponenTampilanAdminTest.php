<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class KomponenTampilanAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_standar_komponen_dimuat_sekali_pada_halaman_admin(): void
    {
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
        foreach (['beranda', 'tahun-pelajaran.index', 'tahun-pelajaran.create', 'persuratan.index', 'input-nilai.index', 'supervisi.index'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'css/admin-components.css'), $route);
            $this->assertSame(1, substr_count($html, 'js/admin-components.js'), $route);
        }
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertDontSee('admin-components.css')->assertDontSee('admin-components.js');
    }

    public function test_komponen_mendukung_status_pesan_dialog_dan_kondisi_kosong_aksesibel(): void
    {
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
        view()->addNamespace('ui-tests', base_path('tests/Fixtures/views'));
        $html = view('ui-tests::admin-components')->render();
        $dom = $this->dom($html);
        $this->assertSame(3, $dom->query('//*[contains(@class,"ui-alert")][@role="status"]')->length);
        $this->assertSame(1, $dom->query('//*[contains(@class,"ui-alert")][@role="alert"]')->length);
        $this->assertSame(1, $dom->query('//dialog[@id="contoh-dialog"][@aria-labelledby="contoh-dialog-title"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="contoh-dialog-title"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="invalid"][@aria-invalid="true"][@aria-describedby="invalid-error"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="invalid-error"]')->length);
        $this->assertSame(1, $dom->query('//*[@class="ui-empty-title"]')->length);
        $this->assertSame(1, $dom->query('//input[@id="readonly"][@readonly]')->length);
        $this->assertSame(1, $dom->query('//button[@id="disabled-button"][@disabled]')->length);
        $this->capture('components', $html);
    }

    public function test_validasi_dan_simpan_formulir_tetap_menggunakan_backend_yang_sama(): void
    {
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
        $this->from(route('tahun-pelajaran.create'))->post(route('tahun-pelajaran.store'), [
            'nama' => '', 'aktif' => '0', 'keterangan' => 'Catatan yang tetap tersimpan pada formulir',
        ])->assertRedirect(route('tahun-pelajaran.create'))->assertSessionHasErrors('nama');
        $this->assertTrue(session('errors')->has('nama'), 'Session keeps the validation error');
        $validationErrors = session('errors');
        $rendered = $this->withViewErrors($validationErrors->getMessages())->view('tahun-pelajaran.create');
        $rendered->assertSee('Catatan yang tetap tersimpan pada formulir');
        $html = (string) $rendered;
        $dom = $this->dom($html);
        $this->capture('validation', $html);
        $this->assertSame(1, $dom->query('//input[@id="nama"][@aria-invalid="true"][@aria-describedby="nama-error"]')->length, 'Input must reference the field error');
        $this->assertSame(1, $dom->query('//*[@id="nama-error"]')->length, 'Field error must have a unique ID');
        $this->assertSame(1, $dom->query('//*[@role="alert"][contains(@class,"alert-danger")]')->length, 'Validation summary must be an alert');
        $this->assertSame(0, TahunPelajaran::count());

        $this->post(route('tahun-pelajaran.store'), ['nama' => '2026/2027', 'aktif' => '1',
            'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'keterangan' => 'Tahun berjalan',
        ])->assertSessionHasNoErrors()->assertRedirect(route('tahun-pelajaran.index'));
        $this->assertDatabaseHas('tahun_pelajaran', ['nama' => '2026/2027', 'aktif' => true]);
        $this->capture('success', $this->get(route('tahun-pelajaran.index'))->assertOk()->getContent());
    }

    public function test_nilai_komponen_di_escape_dan_varian_tidak_dikenal_aman(): void
    {
        $html = Blade::render('<x-ui.alert :title="$title">{{ $text }}</x-ui.alert><x-ui.status type="unknown">{{ $text }}</x-ui.status>', [
            'title' => '<script>alert(1)</script>', 'text' => '<img src=x onerror=alert(1)>',
        ]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img ', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('badge-muted', $html);
    }

    private function capture(string $name, string $html): void
    {
        if (! getenv('NUSA_CAPTURE_ADMIN_COMPONENTS')) {
            return;
        }
        File::ensureDirectoryExists(storage_path('framework/testing/admin-components'));
        File::put(storage_path('framework/testing/admin-components/'.$name.'.html'), $html);
    }

    private function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }
}
