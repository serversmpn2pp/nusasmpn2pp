<?php

namespace Tests\Feature;

use App\Http\Middleware\PastikanPerangkatScan;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PerangkatScanPresensi;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\AksesPerangkatScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\MenggunakanPerangkatScan;
use Tests\TestCase;

class PerangkatScanPresensiTest extends TestCase
{
    use MenggunakanPerangkatScan, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(6, 30));
    }

    public function test_browser_tanpa_aktivasi_tidak_dapat_mengirim_scan_meski_sudah_login_admin(): void
    {
        foreach (['scan-absensi', 'scan-absensi-pegawai'] as $route) {
            $this->get(route($route.'.index'))->assertUnauthorized()->assertSee('Browser scanner belum aktif')->assertDontSee('id="scanInput"', false)->assertCookieMissing(config('session.cookie'));
            $this->actingAs($this->admin());
            $this->postJson(route($route.'.store'), ['isi_scan' => '1234567890'])->assertUnauthorized()->assertJsonPath('status', 'perangkat_tidak_aktif');
        }
        $this->assertDatabaseCount('absensi_siswa', 0);
        $this->assertDatabaseCount('absensi_pegawai', 0);
    }

    public function test_aktivasi_hanya_admin_dan_cookie_persisten_aman_tidak_membocorkan_kredensial(): void
    {
        $this->get(route('perangkat-scan.index'))->assertRedirect(route('login'));
        $u = Pengguna::create(['nama' => 'Petugas', 'username' => 'petugas-test', 'kata_sandi' => 'Aman2026!', 'aktif' => true, 'wajib_ganti_kata_sandi' => false, 'peran' => 'pegawai']);
        $u->daftarPeran()->sync([Peran::where('kode', 'satpam')->value('id')]);
        $this->actingAs($u)->post(route('perangkat-scan.store'), $this->form())->assertForbidden();
        $this->actingAs($this->admin())->get(route('perangkat-scan.index'))->assertOk()->assertSee('Aktifkan browser ini');
        $r = $this->post(route('perangkat-scan.store'), $this->form())->assertRedirect(route('perangkat-scan.index'));
        $cookie = $r->getCookie(AksesPerangkatScan::COOKIE);
        $token = $cookie->getValue();
        $this->assertSame(80, strlen($token));
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertNull($cookie->getDomain());
        $this->assertGreaterThan(now()->addMonths(12)->timestamp, $cookie->getExpiresTime());
        $this->assertSame(hash('sha256', $token), PerangkatScanPresensi::first()->token_hash);
        $r->assertDontSee($token)->assertDontSee(hash('sha256', $token));
        $this->withCookie(AksesPerangkatScan::COOKIE, $token)->get(route('perangkat-scan.index'))->assertOk()->assertSee('Browser ini')->assertDontSee($token)->assertDontSee(hash('sha256', $token));
    }

    public function test_scan_berhasil_tanpa_session_dan_tetap_bekerja_setelah_sesi_login_habis(): void
    {
        [$siswa, $pegawai] = $this->dataPresensi();
        $token = $this->aktifkanBrowserScan();
        Auth::forgetGuards();
        $this->app['session']->flush();
        foreach (['scan-absensi' => $siswa->nisn, 'scan-absensi-pegawai' => $pegawai->nip] as $route => $card) {
            $page = $this->get(route($route.'.index'))->assertOk()->assertSee('scanner-csrf')->assertDontSee('meta name="csrf-token"', false)->assertCookieMissing(config('session.cookie'));
            $page->assertCookieMissing('XSRF-TOKEN')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
            $this->assertFalse(request()->hasSession());
            $this->postJson(route($route.'.store'), ['isi_scan' => $card])->assertOk()->assertJsonPath('berhasil', true)->assertCookieMissing(config('session.cookie'));
        }
        $this->assertDatabaseCount('absensi_siswa', 1);
        $this->assertDatabaseCount('absensi_pegawai', 1);
        $this->assertSame('06:30:00', AbsensiSiswa::first()->jam_masuk);
        $this->assertSame('06:30:00', AbsensiPegawai::first()->jam_masuk);
        $this->travelTo(now()->addDays(30));
        $r = $this->getJson(route('scan-absensi.index'))->assertOk()->assertJsonPath('scannerCsrf', app(AksesPerangkatScan::class)->csrf($token));
        $this->assertGreaterThan(now()->addMonths(12)->timestamp, $r->getCookie(AksesPerangkatScan::COOKIE)->getExpiresTime());
    }

    public function test_token_csrf_asal_request_cakupan_dan_cookie_palsu_ditolak(): void
    {
        $token = $this->aktifkanBrowserScan('siswa');
        $this->postJson(route('scan-absensi-pegawai.store'), ['isi_scan' => '123'])->assertForbidden();
        $this->withHeader('X-Scanner-CSRF', 'tidak-valid')->postJson(route('scan-absensi.store'), ['isi_scan' => '123'])->assertStatus(419);
        $this->withHeader('X-Scanner-CSRF', app(AksesPerangkatScan::class)->csrf($token))->withHeader('Origin', 'https://asing.example')->postJson(route('scan-absensi.store'), ['isi_scan' => '123'])->assertStatus(419);
        $this->withHeader('Origin', 'http://localhost')->withHeader('Sec-Fetch-Site', 'cross-site')->postJson(route('scan-absensi.store'), ['isi_scan' => '123'])->assertStatus(419);
        $this->withCookie(AksesPerangkatScan::COOKIE, Str::random(80))->getJson(route('scan-absensi.index'))->assertUnauthorized();
        $this->assertDatabaseCount('absensi_siswa', 0);
    }

    public function test_pencabutan_langsung_memblokir_tab_yang_sudah_terbuka_dan_aktivasi_ulang_merotasi_cookie(): void
    {
        $old = $this->aktifkanBrowserScan();
        $device = PerangkatScanPresensi::first();
        $this->getJson(route('scan-absensi.index'))->assertOk();
        $this->actingAs($this->admin())->post(route('perangkat-scan.store'), $this->form())->assertRedirect();
        $this->assertNotNull($device->fresh()->dicabut_pada);
        $this->withCookie(AksesPerangkatScan::COOKIE, $old)->getJson(route('scan-absensi.index'))->assertUnauthorized();
        $new = $this->aktifkanBrowserScan();
        $device = PerangkatScanPresensi::where('token_hash', hash('sha256', $new))->firstOrFail();
        $this->delete(route('perangkat-scan.cabut', $device), ['alasan' => 'Komputer dipindahkan dari gerbang'])->assertRedirect();
        $this->withCookie(AksesPerangkatScan::COOKIE, $new)->getJson(route('scan-absensi.index'))->assertUnauthorized();
        $this->postJson(route('scan-absensi.store'), ['isi_scan' => '1234567890'])->assertUnauthorized();
        $this->assertDatabaseCount('absensi_siswa', 0);
    }

    public function test_credential_scanner_tidak_membuka_admin_dan_siswa_ortu_tidak_boleh_aktivasi(): void
    {
        $this->aktifkanBrowserScan();
        $this->get(route('beranda'))->assertRedirect(route('login'));
        $this->post(route('perangkat-scan.store'), $this->form())->assertRedirect(route('login'));
        foreach (['siswa', 'ortu'] as $kind) {
            $u = Pengguna::create(['nama' => 'Akun '.$kind, 'username' => $kind, 'kata_sandi' => 'Aman2026!', 'aktif' => true, 'wajib_ganti_kata_sandi' => false, 'peran' => 'pegawai']);
            $u->daftarPeran()->sync([Peran::where('kode', 'administrator')->value('id')]);
            if ($kind === 'siswa') {
                $u->update(['siswa_id' => Siswa::create(['nama_lengkap' => 'Siswa', 'nisn' => '0000000001', 'aktif' => true])->id]);
            } else {
                OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => 'Wali']);
            }
            $this->actingAs($u)->post(route('perangkat-scan.store'), $this->form())->assertForbidden();
        }
    }

    public function test_rute_scan_benar_benar_tanpa_middleware_session_dan_jadwal_berganti_otomatis(): void
    {
        $this->aktifkanBrowserScan();
        foreach (['scan-absensi', 'scan-absensi-pegawai'] as $route) {
            $middleware = app('router')->gatherRouteMiddleware(Route::getRoutes()->getByName($route.'.store'));
            $this->assertContains(PastikanPerangkatScan::class, $middleware);
            $this->assertNotContains(StartSession::class, $middleware);
            $this->get(route($route.'.index'))->assertOk()->assertSee('scanner-device.js')->assertSee('tanggalJadwal');
            $this->getJson(route($route.'.index'))->assertOk()->assertJsonPath('tanggal_server', '2026-10-26');
        }
        $this->travelTo(now()->addDay());
        $this->getJson(route('scan-absensi.index'))->assertOk()->assertJsonPath('tanggal_server', '2026-10-27')->assertJsonPath('hariLabel', 'Selasa');
    }

    public function test_batas_laju_dan_header_aman_diterapkan(): void
    {
        $this->aktifkanBrowserScan();
        $device = PerangkatScanPresensi::first();
        RateLimiter::hit('scanner-device:'.$device->id.':siswa', 60);
        for ($i = 1; $i < 180; $i++) {
            RateLimiter::hit('scanner-device:'.$device->id.':siswa', 60);
        }
        $this->postJson(route('scan-absensi.store'), ['isi_scan' => '123'])->assertStatus(429)->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_capture_tampilan_sintetis_untuk_verifikasi_browser(): void
    {
        $this->dataPresensi();
        $this->aktifkanBrowserScan();
        $this->actingAs($this->admin());
        foreach (['scan-absensi.index' => 'siswa', 'scan-absensi-pegawai.index' => 'pegawai', 'perangkat-scan.index' => 'admin'] as $route => $name) {
            $html = $this->get(route($route))->assertOk()->getContent();
            if (getenv('NUSA_CAPTURE_SCANNER')) {
                File::ensureDirectoryExists(storage_path('logs/scanner-device'));
                File::put(storage_path('logs/scanner-device/'.$name.'.html'), $html);
            }
        }
    }

    public function test_cookie_scanner_host_only_di_https_meski_cookie_login_memakai_domain_bersama(): void
    {
        app('cookie')->setDefaultPathAndDomain('/', '.school.example', true);
        $response = $this->actingAs($this->admin())->post('https://localhost/perangkat-scan', $this->form())->assertRedirect();
        $cookie = $response->getCookie(AksesPerangkatScan::COOKIE);
        $this->assertNull($cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
    }

    public function test_keluar_akun_admin_tidak_mencabut_aktivasi_browser(): void
    {
        [$siswa] = $this->dataPresensi();
        $response = $this->actingAs($this->admin())->post(route('perangkat-scan.store'), $this->form())->assertRedirect();
        $token = $response->getCookie(AksesPerangkatScan::COOKIE)->getValue();
        $this->withCredentials()->withCookie(AksesPerangkatScan::COOKIE, $token)->withHeader('X-Scanner-CSRF', app(AksesPerangkatScan::class)->csrf($token));
        $this->post(route('logout'))->assertRedirect(route('login'))->assertCookieMissing(AksesPerangkatScan::COOKIE);
        Auth::forgetGuards();
        $this->getJson(route('scan-absensi.index'))->assertOk();
        $this->postJson(route('scan-absensi.store'), ['isi_scan' => $siswa->nisn])->assertOk()->assertJsonPath('berhasil', true);
        $this->get(route('perangkat-scan.index'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('absensi_siswa', 1);
    }

    private function admin(): Pengguna
    {
        return Pengguna::where('username', 'administrator')->firstOrFail();
    }

    private function form(): array
    {
        return ['nama' => 'Scanner Gerbang', 'cakupan' => 'keduanya', 'konfirmasi' => true];
    }

    private function dataPresensi(): array
    {
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A', 'tingkat' => 7, 'aktif' => true]);
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Scanner Uji', 'nisn' => '0011223344', 'aktif' => true]);
        AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'nomor_absen' => 1, 'status_keanggotaan' => 'aktif', 'tanggal_masuk' => '2026-07-01']);
        $pegawai = Pegawai::create(['nama_lengkap' => 'Pegawai Scanner Uji', 'nip' => '199001012015011001', 'jenis_pegawai' => 'Guru', 'aktif' => true]);
        $jadwal = ['hari' => 'senin', 'urutan_hari' => 1, 'jam_scan_masuk_mulai' => '05:30', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '10:00', 'jam_scan_pulang_mulai' => '14:00', 'jam_pulang' => '14:10', 'jam_scan_pulang_selesai' => '16:00', 'aktif' => true];
        PengaturanAbsensi::create($jadwal);
        PengaturanAbsensiPegawai::create($jadwal + ['nama_jadwal' => 'Jadwal Senin', 'cakupan' => 'semua']);

        return [$siswa, $pegawai];
    }
}
