<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TemaKerangkaAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_kerangka_admin_konsisten_pada_setiap_kelompok_fitur_dan_halaman_crud(): void
    {
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $halaman = [
            ['beranda', [], 'utama'],
            ['tahun-pelajaran.index', [], 'data-sekolah'],
            ['tahun-pelajaran.create', [], 'data-sekolah'],
            ['tahun-pelajaran.show', [$tahun], 'data-sekolah'],
            ['tahun-pelajaran.edit', [$tahun], 'data-sekolah'],
            ['pegawai.create', [], 'data-sekolah'],
            ['siswa.index', [], 'data-sekolah'],
            ['input-nilai.index', [], 'akademik'],
            ['dashboard-humas.index', [], 'humas'],
            ['pusat-cbt.index', [], 'ujian-asesmen'],
            ['rekap-absensi-harian.index', [], 'kehadiran'],
            ['laporan-pembinaan-siswa.index', [], 'kesiswaan-bk'],
            ['barang.index', [], 'sarana-prasarana'],
            ['persuratan.index', [], 'persuratan'],
            ['peran.index', [], 'sistem'],
            ['supervisi.index', [], 'akademik'],
        ];
        $manifest = [];
        foreach ($halaman as [$route, $params, $section]) {
            $response = $this->get(route($route, $params))->assertOk()
                ->assertSee('data-admin-shell', false)->assertSee('css/admin-shell.css')
                ->assertSee('css/admin-components.css')->assertSee('js/admin-components.js')
                ->assertSee('js/admin-shell.js')->assertDontSee('admin-school-sketch')->assertDontSee('admin-sidebar-account');
            $html = $response->getContent();
            $dom = $this->dom($html);
            $this->assertSame(1, $dom->query('//aside[@id="admin-sidebar"]')->length, $route);
            $this->assertSame(0, $dom->query('//aside//*[contains(@class,"sidebar-foot")]')->length, $route);
            $this->assertSame(1, $dom->query('//header//details[contains(@class,"account-menu-dropdown")]')->length, $route);
            $this->assertSame(1, $dom->query('//*[@id="admin-tooltip"]')->length, $route);
            $this->assertSame(3, $dom->query('//input[@data-admin-sidebar-mode]')->length, $route);
            $this->assertSame(1, $dom->query('//input[@data-admin-sidebar-mode][@value="hidden"]')->length, $route);
            $this->assertSame(1, $dom->query('//button[@data-admin-sidebar-toggle][@aria-controls="admin-sidebar"]')->length, $route);
            $this->assertSame(1, substr_count($html, 'vendor/lucide/lucide.min.js'), $route);
            $this->assertSame(1, substr_count($html, 'js/admin-shell.js'), $route);
            $this->assertSame(1, substr_count($html, 'css/admin-components.css'), $route);
            $this->assertSame($section, $dom->query('//nav//*[@data-sidebar-section][contains(@class,"has-active")]')->item(0)?->getAttribute('data-sidebar-section-id'), $route);
            if ($route !== 'beranda') {
                $response->assertDontSee('dashboard-admin.css')->assertDontSee('js/dashboard-admin.js')
                    ->assertDontSee('vendor/chartjs/chart.umd.min.js')->assertDontSee('admin-attendance-data');
                $this->assertSame(0, $dom->query('//aside//a[contains(@class,"admin-home-link")][@aria-current="page"]')->length, $route);
            }
            $nama = str_replace('.', '-', $route);
            $manifest[parse_url(route($route, $params), PHP_URL_PATH)] = $nama;
            if (getenv('NUSA_CAPTURE_ADMIN_SHELL')) {
                File::ensureDirectoryExists(storage_path('framework/testing/admin-shell'));
                File::put(storage_path('framework/testing/admin-shell/'.$nama.'.html'), $html);
            }
        }
        if (getenv('NUSA_CAPTURE_ADMIN_SHELL')) {
            File::put(storage_path('framework/testing/admin-shell/manifest.json'), json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }

    public function test_judul_header_dan_menu_aktif_mengikuti_halaman_bukan_dashboard(): void
    {
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
        foreach ([['tahun-pelajaran.index', 'Tahun Pelajaran', 'Data Sekolah'], ['pegawai.create', 'Tambah Pegawai', 'Data Sekolah'], ['persuratan.index', 'Persuratan & Arsip', 'Persuratan & Arsip']] as [$route, $judul, $kelompok]) {
            $dom = $this->dom($this->get(route($route))->assertOk()->getContent());
            $this->assertSame($judul, trim($dom->query('//header//*[contains(@class,"admin-breadcrumb")]/strong')->item(0)->textContent));
            $this->assertSame($kelompok, trim($dom->query('//header//*[contains(@class,"admin-breadcrumb")]/span')->item(0)->textContent));
            $this->assertSame(1, $dom->query('//aside//a[@aria-current="page"]')->length);
            $this->assertSame(1, $dom->query('//aside//details[@data-sidebar-section][contains(@class,"has-active")][@open]')->length);
        }
    }

    public function test_formulir_tetap_memakai_aksi_field_csrf_dan_validasi_yang_sama(): void
    {
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
        $this->get(route('tahun-pelajaran.create'))->assertOk()
            ->assertSee('name="nama"', false)->assertSee('name="tanggal_mulai"', false)
            ->assertSee('name="_token"', false)->assertSee('action="'.route('tahun-pelajaran.store').'"', false);
        $this->post(route('tahun-pelajaran.store'), [])->assertSessionHasErrors(['nama']);
        $this->assertSame(0, TahunPelajaran::count());
    }

    public function test_akun_nonadmin_memakai_tema_bersama_tanpa_mengubah_pembatasan_izin(): void
    {
        $pegawai = Pegawai::create(['nama_lengkap' => 'Pegawai Kerangka', 'aktif' => true]);
        $akun = Pengguna::create(['pegawai_id' => $pegawai->id, 'nama' => 'Pegawai Kerangka', 'username' => 'pegawai.kerangka',
            'kata_sandi' => 'Rahasia123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->sync([Peran::where('kode', 'pegawai')->value('id')]);
        $this->actingAs($akun)->get(route('beranda'))->assertOk()->assertViewIs('beranda.index')
            ->assertSee('css/admin-shell.css')->assertSee('js/admin-shell.js')
            ->assertSee('admin-components.css')->assertSee('admin-components.js')
            ->assertSee('data-nusa-theme')->assertSee('vendor/lucide/lucide.min.js')->assertSee('data-admin-sidebar-mode');
        $this->get(route('peran.index'))->assertForbidden();
    }

    public function test_admin_dengan_profil_pegawai_tetap_mendapat_menu_tugasnya(): void
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = Pegawai::create(['nama_lengkap' => 'Admin Pegawai', 'aktif' => true]);
        $admin->update(['pegawai_id' => $pegawai->id]);
        $dom = $this->dom($this->actingAs($admin)->get(route('beranda'))->assertOk()
            ->assertSee('Laporkan Kejadian')->assertSee('Laporan Saya')->assertSee('data-admin-shell')->getContent());
        $this->assertSame(1, $dom->query('//aside//details[@data-sidebar-section-id="utama"]')->length);
        $this->assertSame(1, $dom->query('//aside//a[@aria-current="page"]')->length);
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
