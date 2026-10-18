<?php

namespace Tests\Feature;

use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\LogScanAbsensi;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\PengecualianPresensiSiswa;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\GrafikPresensiAdminService;
use App\Services\StatistikDashboardAdminService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DashboardAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-16 09:15:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_mendapat_desain_baru_dengan_data_asli_tanpa_akses_cepat(): void
    {
        [$tahun, $anggota] = $this->fondasi();
        $hadir = $this->catat($anggota[0], '2026-10-16', 'hadir', ['jam_masuk' => '06:40:00']);
        $this->catat($anggota[1], '2026-10-16', 'hadir', ['jam_masuk' => '07:01:00', 'menit_terlambat' => 1]);
        $this->catat($anggota[2], '2026-10-16', 'izin');
        $this->fondasiPegawai();
        foreach (['kejadian', 'pembinaan', 'pelanggaran'] as $i => $jenis) {
            $this->laporan($anggota[$i], '2026-10-16', $jenis, ['waktu_kejadian' => '08:15:00', 'status' => $i === 1 ? 'selesai' : 'baru']);
        }
        LogScanAbsensi::create(['absensi_siswa_id' => $hadir->id, 'siswa_id' => $hadir->siswa_id, 'tanggal' => '2026-10-16',
            'waktu_scan' => '2026-10-16 06:40:00', 'isi_scan' => 'DASH-0', 'jenis_scan' => 'masuk', 'berhasil' => true, 'status_scan' => 'berhasil', 'pesan' => 'Datang tercatat']);
        $response = $this->actingAs($this->admin())->get(route('beranda'))->assertOk()
            ->assertViewIs('beranda.admin')->assertSee('nusa-dashboard-admin')->assertSee('Statistik kehadiran')
            ->assertSee('Mingguan')->assertSee('Bulanan')->assertSee('vendor/chartjs/chart.umd.min.js')
            ->assertSee('pegawai-chart')->assertSee('laporan-chart')
            ->assertDontSee('Akses Cepat')->assertDontSee('Alya Putri')->assertDontSee('Pratinjau / data contoh')
            ->assertViewHas('grafikPresensi', fn ($grafik) => $grafik['day']['summary']['present'] === 2
                && $grafik['day']['summary']['late'] === 1 && $grafik['day']['summary']['pending'] === 1
                && $grafik['day']['summary']['absent'] === 0);
        $this->capture('populated', $response->getContent());
        $this->assertSame(3, AbsensiSiswa::count(), 'Dashboard hanya membaca presensi.');
    }

    public function test_akumulasi_jam_masuk_unik_dan_terlambat_adalah_subset_hadir(): void
    {
        [$tahun, $a] = $this->fondasi();
        $this->catat($a[0], '2026-10-16', 'hadir', ['jam_masuk' => '06:40:00']);
        $this->catat($a[1], '2026-10-16', 'hadir', ['jam_masuk' => '07:00:01', 'status_masuk' => 'terlambat']);
        $this->catat($a[2], '2026-10-16', 'hadir');
        $this->catat($a[3], '2026-10-16', 'izin', ['menit_terlambat' => 99]);
        $g = app(GrafikPresensiAdminService::class)->bangun(now(), $tahun);
        $this->assertSame(3, $g['day']['summary']['present']);
        $this->assertSame(1, $g['day']['summary']['late']);
        $this->assertSame(1, $g['day']['summary']['untimed']);
        $this->assertSame(2, end($g['day']['points'])['present']);
        $this->assertSame(1, end($g['day']['points'])['late']);
        foreach ($g['day']['points'] as $p) {
            $this->assertLessThanOrEqual($p['present'], $p['late']);
            $this->assertLessThanOrEqual('09.15', $p['date']);
        }
    }

    public function test_tanggal_mendatang_akhir_pekan_dan_tahun_lain_tidak_masuk_grafik(): void
    {
        [$tahun, $a] = $this->fondasi();
        $this->catat($a[0], '2026-10-17', 'hadir', ['jam_masuk' => '07:00']);
        $g = app(GrafikPresensiAdminService::class)->bangun(now(), $tahun);
        $this->assertCount(5, $g['week']['points']);
        $this->assertCount(12, $g['month']['points']);
        $this->assertSame(20, $g['week']['summary']['opportunities']);
        $this->assertSame(16, $g['week']['summary']['absent']);
        $this->assertSame(4, $g['week']['summary']['pending']);
        $this->assertSame(0, $g['week']['summary']['present']);
        $lain = TahunPelajaran::create(['nama' => 'Tahun lain', 'aktif' => false]);
        $this->assertSame(0, app(GrafikPresensiAdminService::class)->bangun(now(), $lain)['day']['summary']['opportunities']);
    }

    public function test_pengecualian_tidak_memalsukan_hadir_dan_alfa_manual_tetap_ada(): void
    {
        [$tahun, $a] = $this->fondasi();
        PengecualianPresensiSiswa::create(['tahun_pelajaran_id' => $tahun->id, 'tanggal_mulai' => '2026-10-15',
            'tanggal_selesai' => '2026-10-16', 'jenis' => 'pjj', 'alasan' => 'PJJ', 'aktif' => true, 'kunci_pratinjau' => 'qa-global', 'dampak_pratinjau' => []]);
        $this->catat($a[0], '2026-10-15', 'alfa');
        $g = app(GrafikPresensiAdminService::class)->bangun(now(), $tahun);
        $this->assertSame(4, $g['day']['summary']['excluded']);
        $this->assertSame(0, $g['day']['summary']['opportunities']);
        $this->assertSame(7, $g['week']['summary']['excluded']);
        $this->assertSame(13, $g['week']['summary']['opportunities']);
        $this->assertSame(13, $g['week']['summary']['absent']);
        $this->assertNull(end($g['week']['points'])['present']);
        $response = $this->actingAs($this->admin())->get(route('beranda'))->assertOk();
        $this->capture('exceptions', $response->getContent());
    }

    public function test_tanggal_masuk_keluar_dan_cakupan_kelas_membatasi_penyebut(): void
    {
        [$tahun, $a] = $this->fondasi();
        $a[0]->update(['tanggal_masuk' => '2026-10-16']);
        $a[1]->update(['tanggal_keluar' => '2026-10-14', 'status_keanggotaan' => 'nonaktif']);
        PengecualianPresensiSiswa::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $a[3]->kelas_id,
            'tanggal_mulai' => '2026-10-16', 'tanggal_selesai' => '2026-10-16', 'jenis' => 'pjj', 'alasan' => 'Kelas PJJ', 'aktif' => true, 'kunci_pratinjau' => 'qa-kelas', 'dampak_pratinjau' => []]);
        $g = app(GrafikPresensiAdminService::class)->bangun(now(), $tahun);
        $this->assertSame(2, $g['day']['summary']['opportunities']);
        $this->assertSame(1, $g['day']['summary']['excluded']);
        $this->assertSame(13, $g['week']['summary']['opportunities']);
        $this->assertSame(11, $g['week']['summary']['absent']);
    }

    public function test_data_kosong_dan_hari_tanpa_jadwal_tidak_membagi_dengan_nol(): void
    {
        $response = $this->actingAs($this->admin())->get(route('beranda'))->assertOk();
        $this->capture('empty', $response->getContent());
        $g = $response->viewData('grafikPresensi');
        $this->assertSame(0, $g['day']['summary']['opportunities']);
        $this->assertSame([], $g['day']['points']);
        [$tahun] = $this->fondasi();
        Carbon::setTestNow('2026-10-18 09:00:00');
        $g = app(GrafikPresensiAdminService::class)->bangun(now(), $tahun);
        $this->assertSame(0, $g['day']['summary']['pending']);
        $this->assertNotNull($g['day']['empty']);
        $response = $this->get(route('beranda'))->assertOk();
        $this->capture('holiday', $response->getContent());
    }

    public function test_dashboard_pegawai_tetap_dan_halaman_admin_lain_hanya_memuat_tema_kerangka(): void
    {
        $this->actingAs($this->admin())->get(route('siswa.index'))->assertOk()->assertDontSee('dashboard-admin.css')
            ->assertSee('css/admin-shell.css')->assertSee('js/admin-shell.js');
        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru Dashboard', 'aktif' => true]);
        $akun = Pengguna::create(['nama' => 'Guru Dashboard', 'username' => 'guru.dashboard', 'pegawai_id' => $pegawai->id,
            'kata_sandi' => 'Rahasia123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->sync([Peran::where('kode', 'pegawai')->value('id')]);
        $this->actingAs($akun)->get(route('beranda'))->assertOk()->assertViewIs('beranda.index')
            ->assertSee('Dashboard Pegawai')->assertDontSee('dashboard-admin.css')->assertDontSee('admin-attendance-data');
    }

    public function test_grafik_tidak_memicu_query_per_siswa_per_hari(): void
    {
        [$tahun] = $this->fondasi();
        DB::enableQueryLog();
        app(GrafikPresensiAdminService::class)->bangun(now(), $tahun);
        $this->assertLessThanOrEqual(9, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_grafik_pegawai_mengikuti_jadwal_dan_tidak_menulis_presensi(): void
    {
        [$tahun] = $this->fondasi();
        $pegawai = $this->fondasiPegawai();
        Pegawai::create(['nama_lengkap' => 'Belum bertugas', 'aktif' => true, 'tanggal_mulai_bertugas' => '2026-10-17']);
        Pegawai::create(['nama_lengkap' => 'Nonaktif', 'aktif' => false]);
        $g = app(StatistikDashboardAdminService::class)->bangun(now(), $tahun)['pegawai'];
        $this->assertSame(['present' => 2, 'late' => 1, 'permit' => 1, 'absent' => 0, 'pending' => 1, 'opportunities' => 4, 'untimed' => 0], $g['periods']['day']['summary']);
        $this->assertSame(2, end($g['periods']['day']['points'])['present']);
        $this->assertSame(1, end($g['periods']['day']['points'])['late']);
        $this->assertSame(16, $g['periods']['week']['summary']['absent']);
        $this->assertCount(16, $g['periods']['month']['points']);
        $this->assertNull($g['periods']['month']['points'][2]['present'], 'Sabtu tanpa jadwal bukan nol/alfa.');
        $this->assertSame(3, AbsensiPegawai::count());
        AbsensiPegawai::where('pegawai_id', $pegawai[0]->id)->update(['jam_masuk' => null]);
        $g = app(StatistikDashboardAdminService::class)->bangun(now(), $tahun)['pegawai'];
        $this->assertSame(1, $g['periods']['day']['summary']['untimed']);
        $this->assertSame(1, end($g['periods']['day']['points'])['present']);
    }

    public function test_grafik_pegawai_mendukung_jadwal_per_jenis_dan_per_pegawai(): void
    {
        $pegawai = $this->fondasiPegawai();
        PengaturanAbsensiPegawai::where('cakupan', 'semua')->update(['cakupan' => 'jenis_pegawai', 'jenis_pegawai' => 'Guru']);
        $pegawai[3]->update(['jenis_pegawai' => 'TU']);
        Carbon::setTestNow('2026-10-18 09:15:00');
        $jadwal = PengaturanAbsensiPegawai::first()->replicate();
        $jadwal->fill(['cakupan' => 'pegawai', 'pegawai_id' => $pegawai[3]->id, 'hari' => 'minggu'])->save();
        $g = app(StatistikDashboardAdminService::class)->bangun(now(), null)['pegawai']['periods'];
        $this->assertSame(1, $g['day']['summary']['opportunities']);
        $this->assertSame(1, $g['day']['summary']['pending']);
        $this->assertSame(0, $g['day']['summary']['absent']);
        $this->assertSame(12, $g['week']['summary']['absent']);
        $this->assertSame(16, $g['week']['summary']['opportunities']);
    }

    public function test_laporan_dihitung_berdasarkan_tanggal_kejadian_bukan_poin_atau_jumlah_siswa(): void
    {
        [$tahun, $a] = $this->fondasi();
        $this->laporan($a[0], '2026-10-16', 'kejadian', ['waktu_kejadian' => '07:30', 'total_poin' => 999]);
        $this->laporan($a[0], '2026-10-16', 'pembinaan', ['waktu_kejadian' => '08:30', 'status' => 'selesai']);
        $this->laporan($a[0], '2026-10-16', 'pelanggaran');
        $this->laporan($a[1], '2026-10-11', 'kejadian', ['created_at' => now()]);
        $this->laporan($a[0], '2026-10-16', 'pelanggaran', ['status' => 'dibatalkan']);
        $this->laporan($a[0], '2026-10-16', 'pelanggaran', ['status_verifikasi' => 'dibatalkan']);
        $this->laporan($a[0], '2026-10-17', 'kejadian');
        $lain = TahunPelajaran::create(['nama' => 'Tahun lain', 'aktif' => false]);
        $this->laporan($a[0], '2026-10-16', 'kejadian', ['tahun_pelajaran_id' => $lain->id]);
        $g = app(StatistikDashboardAdminService::class)->bangun(now(), $tahun)['laporan']['periods'];
        $this->assertSame(['total' => 3, 'finished' => 1, 'pending' => 2], $g['day']['summary']);
        $this->assertSame(1, array_sum(array_column($g['day']['points'], 'kejadian')));
        $this->assertSame(1, array_sum(array_column($g['day']['points'], 'pembinaan')));
        $this->assertSame(0, array_sum(array_column($g['day']['points'], 'pelanggaran')));
        $this->assertStringContainsString('1 laporan tanpa jam', $g['day']['untimed']);
        $this->assertSame(3, $g['week']['summary']['total']);
        $this->assertSame(4, $g['month']['summary']['total']);
        $this->assertSame(1, $g['month']['points'][10]['kejadian'], 'Laporan akhir pekan tetap masuk.');
        $this->assertStringNotContainsString('RAHASIA', json_encode($g));
        $this->assertStringNotContainsString('kronologi', json_encode($g));
        $this->assertSame(8, LaporanPembinaanSiswa::count());
    }

    public function test_grafik_tambahan_kosong_batas_tahun_dan_jumlah_query_tetap(): void
    {
        $service = app(StatistikDashboardAdminService::class);
        $kosong = $service->bangun(now(), null);
        $this->assertSame(0, $kosong['pegawai']['periods']['day']['summary']['opportunities']);
        $this->assertNotNull($kosong['pegawai']['periods']['day']['empty']);
        $this->assertSame(0, $kosong['laporan']['periods']['month']['summary']['total']);
        [$tahun, $a] = $this->fondasi();
        $tahun->update(['tanggal_mulai' => '2026-10-15']);
        $this->laporan($a[0], '2026-10-14', 'kejadian');
        $this->laporan($a[0], '2026-10-15', 'kejadian');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $g = $service->bangun(now(), $tahun);
        $this->assertLessThanOrEqual(4, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->assertSame(1, $g['laporan']['periods']['month']['summary']['total']);
        $this->assertCount(2, $g['laporan']['periods']['month']['points']);
    }

    private function fondasiPegawai(): array
    {
        $pegawai = [];
        foreach (['Satu', 'Dua', 'Tiga', 'Empat'] as $nama) {
            $pegawai[] = Pegawai::create(['nama_lengkap' => 'Pegawai '.$nama, 'aktif' => true, 'jenis_pegawai' => 'Guru']);
        }
        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat'] as $hari) {
            PengaturanAbsensiPegawai::create(['nama_jadwal' => 'Jadwal '.$hari, 'hari' => $hari, 'cakupan' => 'semua', 'aktif' => true,
                'jam_scan_masuk_mulai' => '05:30', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '10:00',
                'jam_scan_pulang_mulai' => '13:00', 'jam_pulang' => '14:00', 'jam_scan_pulang_selesai' => '16:00']);
        }
        foreach ([['hadir', '06:40', null], ['hadir', '07:00:01', 'terlambat'], ['dinas_luar', null, null]] as $i => [$status, $jam, $statusMasuk]) {
            AbsensiPegawai::create(['pegawai_id' => $pegawai[$i]->id, 'tanggal' => '2026-10-16', 'status_kehadiran' => $status,
                'jam_masuk' => $jam, 'status_masuk' => $statusMasuk, 'sumber' => 'manual']);
        }

        return $pegawai;
    }

    private function laporan(AnggotaKelas $a, string $tanggal, string $jenis, array $tambahan = []): LaporanPembinaanSiswa
    {
        return LaporanPembinaanSiswa::create(['nomor_laporan' => 'DASH-'.(LaporanPembinaanSiswa::count() + 1), 'siswa_id' => $a->siswa_id,
            'tahun_pelajaran_id' => $a->tahun_pelajaran_id, 'kelas_id' => $a->kelas_id, 'tanggal_kejadian' => $tanggal,
            'jenis_laporan' => $jenis, 'status' => 'baru', 'status_verifikasi' => 'tidak_perlu', 'kronologi' => 'RAHASIA', ...$tambahan]);
    }

    private function admin(): Pengguna
    {
        return Pengguna::where('username', 'administrator')->firstOrFail();
    }

    private function fondasi(): array
    {
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['nama' => 'VIII.A', 'tingkat' => 8, 'tahun_pelajaran_id' => $tahun->id, 'aktif' => true]);
        $kelasB = Kelas::create(['nama' => 'VIII.B', 'tingkat' => 8, 'tahun_pelajaran_id' => $tahun->id, 'aktif' => true]);
        $anggota = [];
        foreach (['Siswa Satu', 'Siswa Dua', 'Siswa Tiga', 'Siswa Empat'] as $i => $nama) {
            $siswa = Siswa::create(['nama_lengkap' => $nama, 'nis' => 'DASH-'.$i, 'aktif' => true]);
            $anggota[] = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $i === 3 ? $kelasB->id : $kelas->id,
                'siswa_id' => $siswa->id, 'nomor_absen' => $i + 1, 'status_keanggotaan' => 'aktif']);
        }
        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat'] as $i => $hari) {
            PengaturanAbsensi::create(['hari' => $hari, 'urutan_hari' => $i + 1, 'jam_scan_masuk_mulai' => '05:30', 'jam_masuk' => '07:00',
                'jam_scan_masuk_selesai' => '10:00', 'jam_scan_pulang_mulai' => '13:00', 'jam_pulang' => '14:00', 'jam_scan_pulang_selesai' => '16:00', 'aktif' => true]);
        }

        return [$tahun, $anggota];
    }

    private function catat(AnggotaKelas $a, string $tanggal, string $status, array $tambahan = []): AbsensiSiswa
    {
        return AbsensiSiswa::create(['tanggal' => $tanggal, 'tahun_pelajaran_id' => $a->tahun_pelajaran_id, 'kelas_id' => $a->kelas_id,
            'anggota_kelas_id' => $a->id, 'siswa_id' => $a->siswa_id, 'status_kehadiran' => $status, 'sumber' => 'manual', ...$tambahan]);
    }

    private function capture(string $nama, string $html): void
    {
        if (getenv('NUSA_CAPTURE_DASHBOARD')) {
            $path = storage_path('framework/testing/dashboard-admin');
            File::ensureDirectoryExists($path);
            File::put($path.'/'.$nama.'.html', $html);
        }
    }
}
