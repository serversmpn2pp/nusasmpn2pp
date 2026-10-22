<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\Izin;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Humas\NavigasiHumasService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NavigasiHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
    }

    public function test_tujuh_bidang_dan_seluruh_tautan_dashboard_selaras_dengan_sidebar(): void
    {
        foreach (['wakil_pimpinan_humas', 'pimpinan', 'administrator'] as $kode) {
            $akun = $this->akun($kode);
            $r = $this->actingAs($akun)->get(route('dashboard-humas.index'))->assertOk();
            $ruang = $r->viewData('ruangKerja');
            $this->assertSame(['kedinasan', 'komite', 'orang-tua', 'kemitraan', 'publikasi', 'alumni', 'pengaduan'], array_column($ruang, 'kode'));
            $dom = $this->dom($r->getContent());
            $this->assertSame(7, $dom->query('//*[@data-humas-workspace]')->length);
            foreach ($ruang as $bagian) {
                $this->assertSame(1, $dom->query('//aside//*[contains(@class,"sidebar-subgroup-title")][normalize-space()="'.$bagian['label'].'"]')->length);
                foreach ($bagian['items'] as $item) {
                    $href = htmlspecialchars(route($item['route'], $item['parameters']), ENT_QUOTES);
                    $r->assertSee('href="'.$href.'"', false);
                    $this->assertSame(1, $dom->query('//aside//a[@href="'.route($item['route'], $item['parameters']).'"]')->length, $item['kode']);
                }
            }
            $r->assertSee('Buku Tamu Digital')->assertSee('Agenda &amp; Pertemuan', false)->assertSee('Database Prestasi');
            foreach (app(NavigasiHumasService::class)->sidebar($akun) as $item) {
                $this->get(route($item['route'], $item['parameters']))->assertOk();
            }
        }
    }

    public function test_akun_terbatas_hanya_mendapat_bidang_dan_tautan_yang_diizinkan(): void
    {
        $akun = $this->terbatas(['dashboard_humas.lihat', 'prestasi_sekolah.lihat']);
        $r = $this->actingAs($akun)->get(route('dashboard-humas.index'))->assertOk();
        $this->assertSame(['publikasi'], array_column($r->viewData('ruangKerja'), 'kode'));
        $this->assertSame(['prestasi-sekolah'], array_column($r->viewData('ruangKerja')[0]['items'], 'kode'));
        $dom = $this->dom($r->getContent());
        $this->assertSame(1, $dom->query('//*[@data-humas-workspace]')->length);
        $r->assertDontSee('data-humas-menu="publikasi-humas"', false)->assertDontSee('data-humas-menu="buku-tamu"', false);
        foreach (['dokumen-humas.index', 'komite-humas.index', 'publikasi-humas.index', 'alumni-humas.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->withToken($akun->createToken('uji', ['mobile'])->plainTextToken)
            ->getJson(route('api.v1.humas.dashboard.index'))->assertOk()
            ->assertJsonCount(1, 'data.ruang_kerja')->assertJsonPath('data.ruang_kerja.0.kode', 'publikasi')
            ->assertJsonPath('data.ruang_kerja.0.items.0.kode', 'prestasi-sekolah');
    }

    public function test_sidebar_pegawai_tidak_menyembunyikan_modul_humas_yang_memang_diizinkan(): void
    {
        $akun = $this->terbatas(['dokumen_humas.lihat', 'agenda_humas.lihat']);
        $pegawai = Pegawai::create(['nama_lengkap' => 'Petugas Dokumen', 'aktif' => true]);
        $akun->update(['pegawai_id' => $pegawai->id]);
        $r = $this->actingAs($akun)->get(route('dokumen-humas.index'))->assertOk()->assertSee('data-sidebar-section-id="layanan-humas"', false);
        $dom = $this->dom($r->getContent());
        $this->assertSame(1, $dom->query('//aside//a[@href="'.route('dokumen-humas.index').'"]')->length);
        $this->assertSame(1, $dom->query('//aside//a[@aria-current="page"]')->length);
        $this->assertSame(0, $dom->query('//aside//a[@href="'.route('dashboard-humas.index').'"]')->length);
        $this->get(route('dashboard-humas.index'))->assertForbidden();
        $this->withToken($akun->createToken('uji', ['mobile'])->plainTextToken)
            ->getJson(route('api.v1.menu'))->assertOk()
            ->assertJsonFragment(['kode' => 'dokumen-humas', 'subkelompok' => 'Dokumen Kedinasan Humas'])
            ->assertJsonMissing(['kode' => 'dashboard-humas']);
    }

    public function test_agenda_terfilter_dan_detail_hanya_mengaktifkan_satu_menu_yang_tepat(): void
    {
        $this->actingAs($this->akun());
        foreach (['komite' => 'Rapat Komite', 'orang_tua' => 'Pertemuan Orang Tua', 'kemitraan' => 'Pertemuan Mitra', 'internal' => 'Agenda & Pertemuan'] as $jenis => $label) {
            $agenda = AgendaHumas::create(['judul' => 'Agenda '.$jenis, 'jenis' => $jenis, 'waktu_mulai' => now(), 'waktu_selesai' => now()->addHours(2), 'tempat' => 'Aula', 'topik' => 'Koordinasi', 'status' => 'terjadwal']);
            foreach ([['agenda-humas.index', ['jenis' => $jenis]], ['agenda-humas.show', [$agenda]]] as [$route, $params]) {
                $r = $this->get(route($route, $params))->assertOk();
                $dom = $this->dom($r->getContent());
                $aktif = $dom->query('//aside//a[@aria-current="page"]');
                $this->assertSame(1, $aktif->length, $route.' '.$jenis);
                $this->assertStringContainsString($label, $aktif->item(0)->textContent);
            }
        }
        $dom = $this->dom($this->get(route('agenda-humas.index'))->assertOk()->getContent());
        $this->assertSame(1, $dom->query('//aside//a[@aria-current="page"]')->length);
        $this->assertStringContainsString('Agenda & Pertemuan', $dom->query('//aside//a[@aria-current="page"]')->item(0)->textContent);
    }

    public function test_identitas_siswa_orang_tua_dan_akun_nonaktif_tidak_mendapat_navigasi_staf(): void
    {
        foreach (['siswa', 'orang_tua', 'nonaktif'] as $jenis) {
            $akun = $this->akun();
            if ($jenis === 'siswa') {
                $siswa = Siswa::create(['nama_lengkap' => 'Siswa Uji', 'nisn' => '1234567890', 'jenis_kelamin' => 'L', 'aktif' => true]);
                $akun->update(['siswa_id' => $siswa->id]);
            } elseif ($jenis === 'orang_tua') {
                OrangTuaWali::create(['pengguna_id' => $akun->id, 'nama_lengkap' => 'Wali Uji']);
            } else {
                $akun->update(['aktif' => false]);
            }
            $nav = app(NavigasiHumasService::class);
            $this->assertSame([], $nav->ruangKerja($akun));
            $this->assertSame([], $nav->sidebar($akun));
            $this->assertSame([], $nav->untukApi($akun));
            $this->actingAs($akun)->get(route('dashboard-humas.index'))->assertForbidden();
        }
    }

    public function test_api_memakai_tujuh_bidang_yang_sama_tanpa_mengiklankan_rute_native_yang_belum_ada(): void
    {
        $akun = $this->akun();
        $this->assertFalse($akun->memilikiIzin('publikasi_humas.periksa'));
        $response = $this->withToken($akun->createToken('uji', ['mobile'])->plainTextToken)
            ->getJson(route('api.v1.humas.dashboard.index'))->assertOk()->assertJsonCount(7, 'data.ruang_kerja')
            ->assertJsonPath('data.ruang_kerja.1.items.1.menu_mobile', 'agenda-humas')
            ->assertJsonPath('data.ruang_kerja.1.items.1.filter.jenis', 'komite')
            ->assertJsonPath('data.ruang_kerja.5.items.0.tersedia_mobile', true)
            ->assertJsonPath('data.ruang_kerja.5.items.0.menu_mobile', 'alumni-humas');
        $this->assertSame(array_column(app(NavigasiHumasService::class)->ruangKerja($akun), 'kode'), array_column($response->json('data.ruang_kerja'), 'kode'));
        $response->assertJsonMissingPath('data.ruang_kerja.0.items.0.izin')->assertJsonMissingPath('data.ruang_kerja.0.items.0.route');
        $this->assertFalse($akun->fresh()->memilikiIzin('publikasi_humas.periksa'));
    }

    public function test_izin_dashboard_saja_tidak_membuka_bidang_dan_pemeriksa_konten_tidak_mendapat_hak_kelola(): void
    {
        $akun = $this->terbatas(['dashboard_humas.lihat']);
        $this->actingAs($akun)->get(route('dashboard-humas.index'))->assertOk()->assertViewHas('ruangKerja', [])
            ->assertViewHas('layananPendukung', fn ($items) => array_column($items, 'kode') === ['faq-humas']);
        $this->withToken($akun->createToken('uji', ['mobile'])->plainTextToken)
            ->getJson(route('api.v1.humas.dashboard.index'))->assertOk()->assertJsonCount(0, 'data.ruang_kerja');

        $pemeriksa = $this->terbatas(['dashboard_humas.lihat', 'publikasi_humas.periksa']);
        $r = $this->actingAs($pemeriksa)->get(route('dashboard-humas.index'))->assertOk();
        $this->assertSame(['publikasi'], array_column($r->viewData('ruangKerja'), 'kode'));
        $this->assertSame(['publikasi-humas'], array_column($r->viewData('ruangKerja')[0]['items'], 'kode'));
        $this->get(route('publikasi-humas.index'))->assertOk();
        $this->get(route('publikasi-humas.create'))->assertForbidden();
    }

    private function akun(string $kode = 'wakil_pimpinan_humas'): Pengguna
    {
        $akun = Pengguna::create(['nama' => 'Petugas Humas Uji', 'username' => 'nav.'.Str::uuid(), 'kata_sandi' => 'UjiHumas2026!', 'peran' => 'pegawai', 'aktif' => true, 'akun_sistem' => false, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->sync([Peran::where('kode', $kode)->value('id')]);

        return $akun;
    }

    private function terbatas(array $izin): Pengguna
    {
        $role = Peran::create(['kode' => 'nav_'.Str::random(8), 'nama' => 'Humas terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $akun = $this->akun('pegawai');
        $akun->daftarPeran()->sync([$role->id]);

        return $akun;
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
