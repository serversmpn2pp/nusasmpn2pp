<?php

namespace Tests\Feature;

use App\Http\Middleware\PastikanMonitorTv;
use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\OrangTuaWali;
use App\Models\PengaturanAbsensi;
use App\Models\PengecualianPresensiSiswa;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PerangkatMonitoring;
use App\Models\PerangkatScanPresensi;
use App\Models\PresensiKegiatanIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Absensi\AksesPerangkatScan;
use App\Services\Monitoring\AksesMonitorTv;
use App\Services\Monitoring\MonitoringSiswaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitoringTvSiswaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(9, 30));
    }

    public function test_monitoring_bukan_halaman_publik_dan_scanner_tidak_memiliki_akses(): void
    {
        $this->get(route('monitoring-siswa.index'))->assertUnauthorized()->assertSee('Layar monitoring belum aktif');
        $this->getJson(route('monitoring-siswa.data'))->assertUnauthorized();
        $this->actingAs($this->admin())->getJson(route('monitoring-siswa.data'))->assertUnauthorized();
        $token = Str::random(80);
        PerangkatScanPresensi::create(['nama' => 'Scanner', 'cakupan' => 'keduanya', 'token_hash' => hash('sha256', $token)]);
        $this->withCredentials()->withCookie(AksesPerangkatScan::COOKIE, $token)->getJson(route('monitoring-siswa.data'))->assertUnauthorized();
        $this->withCookie(AksesMonitorTv::COOKIE, 'palsu')->getJson(route('monitoring-siswa.data'))->assertUnauthorized();
    }

    public function test_aktivasi_admin_cookie_aman_dan_bertahan_setelah_logout(): void
    {
        $this->get(route('perangkat-monitoring.index'))->assertRedirect(route('login'));
        $this->actingAs($this->admin())->get(route('perangkat-monitoring.index'))->assertOk()->assertSee('Aktivasi browser TV');
        config(['session.domain' => '.nusa.test', 'session.secure' => true]);
        $response = $this->post(route('perangkat-monitoring.store'), ['nama' => 'TV ruang guru', 'konfirmasi' => 1])->assertRedirect(route('perangkat-monitoring.index'));
        $cookie = $response->getCookie(AksesMonitorTv::COOKIE);
        $token = $cookie->getValue();
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertNull($cookie->getDomain());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertGreaterThan(now()->addYear()->timestamp, $cookie->getExpiresTime());
        $this->assertSame(hash('sha256', $token), PerangkatMonitoring::first()->token_hash);
        $this->assertArrayNotHasKey('token_hash', PerangkatMonitoring::first()->toArray());
        $response->assertDontSee($token);
        $this->post(route('logout'))->assertRedirect(route('login'));
        Auth::forgetGuards();
        $this->app['session']->flush();
        $this->withCredentials()->withCookie(AksesMonitorTv::COOKIE, $token)->getJson(route('monitoring-siswa.data'))
            ->assertOk()->assertCookieMissing(config('session.cookie'))->assertCookieMissing('XSRF-TOKEN')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertFalse(request()->hasSession());
        $this->get(route('perangkat-monitoring.index'))->assertRedirect(route('login'));
        $this->getJson(route('scan-absensi.index'))->assertUnauthorized();
        $middleware = Route::getRoutes()->getByName('monitoring-siswa.data')->gatherMiddleware();
        $this->assertContains(PastikanMonitorTv::class, $middleware);
        $resolved = app('router')->gatherRouteMiddleware(Route::getRoutes()->getByName('monitoring-siswa.data'));
        $this->assertNotContains(StartSession::class, $resolved);
    }

    public function test_guru_siswa_dan_orang_tua_tidak_dapat_mengaktifkan_monitor_meski_salah_role_admin(): void
    {
        foreach (['pegawai', 'siswa', 'orang_tua'] as $role) {
            $u = Pengguna::create(['nama' => 'Akun '.$role, 'username' => 'tv-'.$role, 'kata_sandi' => 'Rahasia2026!', 'aktif' => true, 'wajib_ganti_kata_sandi' => false, 'peran' => $role]);
            $u->daftarPeran()->sync([Peran::where('kode', 'pegawai')->value('id')]);
            if ($role === 'siswa') {
                $u->update(['siswa_id' => Siswa::create(['nama_lengkap' => 'Siswa uji', 'aktif' => true])->id]);
            }
            if ($role === 'orang_tua') {
                OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => 'Wali uji']);
            }
            $this->actingAs($u)->post(route('perangkat-monitoring.store'), ['nama' => 'TV', 'konfirmasi' => 1])->assertForbidden();
            if ($role !== 'pegawai') {
                $u->daftarPeran()->sync([Peran::where('kode', 'administrator')->value('id')]);
                $u->unsetRelation('daftarPeran');
                $this->actingAs($u)->post(route('perangkat-monitoring.store'), ['nama' => 'TV', 'konfirmasi' => 1])->assertForbidden();
            }
        }
        $this->assertDatabaseCount('perangkat_monitoring', 0);
    }

    public function test_pencabutan_langsung_menolak_data_termasuk_ketika_snapshot_tersimpan(): void
    {
        $token = $this->perangkat();
        $this->getJson(route('monitoring-siswa.data'))->assertOk();
        $perangkat = PerangkatMonitoring::first();
        $this->actingAs($this->admin())->delete(route('perangkat-monitoring.cabut', $perangkat), ['alasan' => 'TV dipindahkan'])->assertRedirect();
        $this->withCookie(AksesMonitorTv::COOKIE, $token)->getJson(route('monitoring-siswa.data'))->assertUnauthorized();
        $this->assertSame($this->admin()->id, $perangkat->fresh()->dicabut_oleh);
    }

    public function test_ringkasan_mengikuti_tahun_siswa_aktif_dan_terlambat_detik_tanpa_menulis_presensi(): void
    {
        [$tahun, $a] = $this->fondasi();
        $this->presensi($a[0], 'hadir', ['jam_masuk' => '07:00:01', 'status_masuk' => 'terlambat']);
        $this->presensi($a[1], 'izin');
        $this->presensi($a[2], 'sakit');
        $this->presensi($a[3], 'alfa');
        $a[5]->siswa->update(['aktif' => false]);
        $this->presensi($a[5], 'hadir');
        $d = app(MonitoringSiswaService::class)->bangun();
        $this->assertSame(['dipantau' => 5, 'hadir' => 1, 'izin' => 1, 'sakit' => 1, 'alfa' => 1, 'belum' => 1, 'pengecualian' => 0, 'terlambat' => 1, 'laporan' => 0], $d['ringkasan']);
        $this->assertSame(0, $d['terlambat'][0]['menit']);
        $this->assertDatabaseCount('absensi_siswa', 5);
        $this->assertDatabaseCount('laporan_pembinaan_siswa', 0);
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
    }

    public function test_laporan_baru_langsung_muncul_berdasarkan_waktu_pengiriman_tanpa_kronologi_rahasia(): void
    {
        [$tahun, $a] = $this->fondasi();
        $report = $this->laporan($a[0], ['tanggal_kejadian' => '2026-10-20', 'total_poin' => 999, 'kronologi' => 'KRONOLOGI RAHASIA', 'catatan_rahasia' => 'SANGAT RAHASIA']);
        $this->laporan($a[1], ['status' => 'dibatalkan']);
        $this->laporan($a[2], ['status_verifikasi' => 'tidak_terbukti']);
        $this->laporan($a[3], ['jenis_laporan' => 'pembinaan']);
        $this->laporan($a[4], ['created_at' => now()->subDay()]);
        $this->perangkat();
        $response = $this->getJson(route('monitoring-siswa.data'))->assertOk()->assertJsonPath('ringkasan.laporan', 2);
        $response->assertDontSee('KRONOLOGI')->assertDontSee('SANGAT RAHASIA')->assertDontSee('999')->assertDontSee('nisn');
        $items = collect($response->json('laporan_terbaru'));
        $this->assertSame('Belum diverifikasi', $items->firstWhere('id', 'laporan-'.$report->id)['status']);
        $this->assertSame('Pembinaan siswa', $items->first()['nama']);
        $this->assertSame(0, $response->json('poin.saldo'));
        $this->assertSame(2, array_sum(array_column($response->json('grafik_laporan.week'), 'total')));
        $this->assertSame(3, array_sum(array_column($response->json('grafik_laporan.month'), 'total')));
        $this->laporan($a[0]);
        $this->travel(5)->seconds();
        $this->getJson(route('monitoring-siswa.data'))->assertOk()->assertJsonPath('ringkasan.laporan', 3);
    }

    public function test_poin_resmi_menggunakan_transaksi_dan_koreksi_bukan_total_usulan_laporan(): void
    {
        [$tahun, $a] = $this->fondasi();
        $this->laporan($a[0], ['total_poin' => 999]);
        foreach ([[0, 15], [0, -5], [1, 25], [2, -10]] as $i => [$index, $poin]) {
            TransaksiPoinSiswa::create(['siswa_id' => $a[$index]->siswa_id, 'tahun_pelajaran_id' => $tahun->id,
                'jenis' => $poin > 0 ? 'pelanggaran' : 'pembatalan', 'poin' => $poin, 'tercatat_pada' => now(), 'kunci_sumber' => 'tv-'.$i, 'keterangan' => 'Data uji']);
        }
        $d = app(MonitoringSiswaService::class)->bangun();
        $this->assertSame(['saldo' => 35, 'siswa' => 2, 'ditambahkan' => 40, 'dikurangi' => 15], $d['poin']);
    }

    public function test_rekap_ibadah_tidak_memuat_info_pribadi_dan_sesuai_jadwal(): void
    {
        [$tahun, $a] = $this->fondasi();
        $this->presensi($a[0], 'hadir');
        $this->presensi($a[1], 'hadir');
        $k = KegiatanIbadah::create(['kode' => 'zuhur-tv', 'nama' => 'Salat Zuhur', 'aktif' => true]);
        $j = JadwalKegiatanIbadah::create(['tahun_pelajaran_id' => $tahun->id, 'kegiatan_ibadah_id' => $k->id, 'hari' => 'senin', 'urutan_hari' => 1, 'jam_scan_mulai' => '11:00', 'jam_pelaksanaan' => '12:00', 'jam_scan_selesai' => '13:00', 'aktif' => true]);
        PresensiKegiatanIbadah::create(['jadwal_kegiatan_ibadah_id' => $j->id, 'kegiatan_ibadah_id' => $k->id, 'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $a[0]->kelas_id, 'siswa_id' => $a[0]->siswa_id, 'anggota_kelas_id' => $a[0]->id, 'tanggal' => now()->toDateString(), 'waktu_scan' => '12:01:00', 'sumber' => 'scan']);
        $d = app(MonitoringSiswaService::class)->bangun();
        $this->assertSame([['nama' => 'Salat Zuhur', 'jam' => '12:00', 'sudah' => 1, 'wajib' => 2, 'belum' => 1, 'persen' => 50]], $d['ibadah']);
        $this->assertStringNotContainsString('berhalangan', json_encode($d));
        $this->assertStringNotContainsString('catatan_koreksi', json_encode($d));
        $this->capture($d);
    }

    public function test_hari_libur_pengecualian_tanpa_tahun_dan_batas_tahun_tidak_membuat_alfa(): void
    {
        $d = app(MonitoringSiswaService::class)->bangun();
        $this->assertSame(0, $d['ringkasan']['dipantau']);
        [$tahun, $a] = $this->fondasi();
        $this->travel(6)->days();
        $d = app(MonitoringSiswaService::class)->bangun();
        $this->assertSame(0, $d['ringkasan']['alfa']);
        $this->assertSame(6, $d['ringkasan']['pengecualian']);
        $tahun->update(['tanggal_selesai' => now()->subDay()]);
        $d = app(MonitoringSiswaService::class)->bangun();
        $this->assertSame(0, $d['ringkasan']['dipantau']);
    }

    public function test_query_terbatas_dan_tampilan_memakai_aset_lokal_dengan_interval_lima_detik(): void
    {
        $this->fondasi();
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(MonitoringSiswaService::class)->bangun();
        $this->assertLessThanOrEqual(20, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->perangkat();
        $this->get(route('monitoring-siswa.index'))->assertOk()->assertSee('monitoring-tv.js')->assertSee('vendor/chartjs/chart.umd.min.js')->assertDontSee('app-sidebar')->assertDontSee('csrf-token');
        $this->assertStringContainsString('const REFRESH_MS = 5000', File::get(public_path('js/monitoring-tv.js')));
        $this->assertStringNotContainsString('location.reload', File::get(public_path('js/monitoring-tv.js')));
    }

    public function test_pengecualian_tidak_memalsukan_hadir_dan_catatan_alfa_manual_tetap_dihitung(): void
    {
        [$tahun, $a] = $this->fondasi();
        PengecualianPresensiSiswa::create(['tahun_pelajaran_id' => $tahun->id, 'tanggal_mulai' => '2026-10-26',
            'tanggal_selesai' => '2026-10-26', 'jenis' => 'pjj', 'alasan' => 'PJJ uji', 'aktif' => true,
            'kunci_pratinjau' => 'tv-pjj-uji', 'dampak_pratinjau' => []]);
        $this->presensi($a[0], 'alfa');
        $d = app(MonitoringSiswaService::class)->bangun();
        $this->assertSame(5, $d['ringkasan']['pengecualian']);
        $this->assertSame(1, $d['ringkasan']['alfa']);
        $this->assertSame(0, $d['ringkasan']['hadir']);
        $this->assertSame(0, $d['ringkasan']['belum']);
    }

    public function test_pergantian_hari_dan_minggu_lintas_bulan_memakai_waktu_masuk_laporan(): void
    {
        [$tahun, $a] = $this->fondasi();
        $this->laporan($a[0]);
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(23, 59, 59));
        $this->laporan($a[1]);
        $this->perangkat();
        $r = $this->getJson(route('monitoring-siswa.data'))->assertOk()->assertJsonPath('ringkasan.laporan', 1);
        $this->assertSame(2, array_sum(array_column($r->json('grafik_laporan.week'), 'total')));
        $this->assertSame(1, array_sum(array_column($r->json('grafik_laporan.month'), 'total')));
        $this->travel(2)->seconds();
        $this->getJson(route('monitoring-siswa.data'))->assertOk()->assertJsonPath('ringkasan.laporan', 0)
            ->assertJsonCount(0, 'laporan_terbaru');
    }

    private function admin(): Pengguna
    {
        return Pengguna::where('username', 'administrator')->firstOrFail();
    }

    private function perangkat(): string
    {
        $token = Str::random(80);
        PerangkatMonitoring::create(['nama' => 'TV Pengujian', 'token_hash' => hash('sha256', $token)]);
        $this->withCredentials()->withCookie(AksesMonitorTv::COOKIE, $token);

        return $token;
    }

    private function fondasi(): array
    {
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $a = [];
        foreach (['VIII.A', 'VIII.B'] as $nama) {
            $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => $nama, 'tingkat' => 8, 'aktif' => true]);
            foreach (range(1, 3) as $i) {
                $siswa = Siswa::create(['nama_lengkap' => 'Siswa Uji '.$nama.' '.$i, 'jenis_kelamin' => 'L', 'aktif' => true]);
                $a[] = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'status_keanggotaan' => 'aktif', 'nomor_absen' => $i]);
            }
        }
        PengaturanAbsensi::create(['hari' => 'senin', 'urutan_hari' => 1, 'aktif' => true, 'jam_scan_masuk_mulai' => '05:30', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '10:00', 'jam_scan_pulang_mulai' => '13:00', 'jam_pulang' => '14:00', 'jam_scan_pulang_selesai' => '16:00']);

        return [$tahun, $a];
    }

    private function presensi($a, string $status, array $extra = []): void
    {
        AbsensiSiswa::create(['tahun_pelajaran_id' => $a->tahun_pelajaran_id, 'kelas_id' => $a->kelas_id, 'anggota_kelas_id' => $a->id,
            'siswa_id' => $a->siswa_id, 'tanggal' => now()->toDateString(), 'status_kehadiran' => $status, 'sumber' => 'manual', ...$extra]);
    }

    private function laporan($a, array $extra = []): LaporanPembinaanSiswa
    {
        $laporan = LaporanPembinaanSiswa::create(['nomor_laporan' => 'TV-'.Str::random(10), 'jenis_laporan' => 'pelanggaran',
            'tahun_pelajaran_id' => $a->tahun_pelajaran_id, 'kelas_id' => $a->kelas_id, 'siswa_id' => $a->siswa_id,
            'tanggal_kejadian' => now()->toDateString(), 'status' => 'baru', 'status_verifikasi' => 'diajukan', 'kronologi' => 'Data uji', ...$extra]);
        if (isset($extra['created_at'])) {
            $laporan->forceFill(['created_at' => $extra['created_at']])->save();
        }

        return $laporan;
    }

    private function capture(array $d): void
    {
        if (! getenv('NUSA_CAPTURE_MONITOR')) {
            return;
        }
        $path = storage_path('framework/testing/monitor-tv');
        File::ensureDirectoryExists($path);
        // Synthetic data only; never capture real school records.
        foreach (range(1, 8) as $i) {
            $this->laporan(AnggotaKelas::first());
        }
        $this->perangkat();
        $response = $this->get(route('monitoring-siswa.index'))->assertOk();
        File::put($path.'/index.html', $response->getContent());
        File::put($path.'/data.json', json_encode(app(MonitoringSiswaService::class)->bangun()));
    }
}
