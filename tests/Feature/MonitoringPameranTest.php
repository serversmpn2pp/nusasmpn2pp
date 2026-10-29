<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AcaraBukuTamu;
use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\PerangkatMonitoring;
use App\Models\PresensiKegiatanIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Monitoring\AksesMonitorTv;
use App\Services\Monitoring\MonitoringPameranService;
use App\Services\Monitoring\MonitoringSiswaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitoringPameranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(10, 0));
    }

    public function test_sabtu_menampilkan_jumat_dengan_poin_laporan_dan_ibadah_tanggal_terpilih(): void
    {
        [$tahun, $a] = $this->fondasi();
        $this->isiAktivitas($tahun, $a);
        [$layar] = $this->layar();
        $r = $this->getJson(route('monitoring-pameran.data'))->assertOk()->assertJsonPath('tanggal_data', '2026-10-30')
            ->assertJsonPath('ringkasan.hadir', 2)->assertJsonPath('ringkasan.izin', 1)->assertJsonPath('ringkasan.alfa', 1)
            ->assertJsonPath('ringkasan.terlambat', 1)->assertJsonPath('ringkasan.laporan', 2)
            ->assertJsonPath('poin.saldo', 40)->assertJsonPath('poin.ditambahkan', 25)->assertJsonPath('poin.dikurangi', 0)
            ->assertJsonPath('ibadah.0.sudah', 1)->assertJsonPath('ibadah.0.belum', 1)->assertJsonPath('historis', true)
            ->assertJsonMissingPath('rincian');
        $this->assertStringStartsWith('2026-10-31', $r->json('diperbarui_pada'));
        $this->assertStringStartsWith('2026-10-30', $r->json('batas_data_pada'));
        $this->assertSame(2, array_sum(array_column($r->json('grafik_laporan.week'), 'total')));
        $this->assertStringContainsString('30 Okt', $r->json('grafik_presensi.day.date'));
        $this->getJson(route('monitoring-pameran.rincian', ['jenis' => 'hadir']))->assertOk()->assertJsonPath('tanggal_data', '2026-10-30')->assertJsonPath('pagination.total', 2);
        $this->getJson(route('monitoring-pameran.rincian', ['jenis' => 'poin_tambah']))->assertJsonPath('pagination.total', 1)->assertJsonPath('items.0.poin', 25);
        $this->getJson(route('monitoring-pameran.rincian', ['jenis' => 'ibadah', 'status_ibadah' => 'sudah']))->assertJsonPath('pagination.total', 1);
        $this->get(route('monitoring-pameran.index'))->assertOk()->assertSee('Aktivitas siswa')->assertSee('Pengunjung stand')->assertSee('2026-10-30')->assertSee('data-monitor-csrf', false);
        $internal = app(MonitoringSiswaService::class)->bangun();
        $this->assertSame(0, $internal['ringkasan']['alfa']);
        $this->assertSame(1, $internal['ringkasan']['laporan']);
        $this->assertSame(10, $internal['poin']['dikurangi']);
        $this->assertDatabaseCount('absensi_siswa', 4);
        $this->capture($layar);
    }

    public function test_pilihan_tanggal_tersimpan_per_perangkat_dan_wajib_csrf_tanpa_session(): void
    {
        $this->fondasi();
        [$layar, $token] = $this->layar();
        $url = route('monitoring-pameran.tanggal');
        $this->putJson($url, ['tanggal' => '2026-10-29'])->assertStatus(419);
        $headers = ['X-Monitor-CSRF' => app(AksesMonitorTv::class)->csrf($token)];
        $this->withHeaders($headers + ['Origin' => 'https://evil.example'])->putJson($url, ['tanggal' => '2026-10-29'])->assertStatus(419);
        $this->withHeaders($headers + ['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])->putJson($url, ['tanggal' => '2026-10-29', 'acara_buku_tamu_id' => 999])->assertOk()->assertJsonPath('tanggal_data', '2026-10-29');
        $this->assertSame('2026-10-29', $layar->fresh()->pameran_tanggal->toDateString());
        $this->getJson(route('monitoring-pameran.data'))->assertJsonPath('tanggal_data', '2026-10-29')->assertCookieMissing(config('session.cookie'))->assertCookieMissing('XSRF-TOKEN');
        foreach (['2026-11-01', '2026-06-30', '30-10-2026', '', ['2026-10-30']] as $tanggal) {
            $this->putJson($url, compact('tanggal'))->assertUnprocessable();
        }
        [$lain] = $this->layar(['pameran_tanggal' => '2026-10-28']);
        $this->getJson(route('monitoring-pameran.data'))->assertJsonPath('tanggal_data', '2026-10-28');
        $this->assertSame('2026-10-29', $layar->fresh()->pameran_tanggal->toDateString());
        $this->assertNotSame($lain->id, $layar->id);
    }

    public function test_cakupan_berdiri_sendiri_dan_akses_dicabut_sebelum_cache_dibaca(): void
    {
        $this->fondasi();
        $this->getJson(route('monitoring-pameran.data'))->assertUnauthorized();
        foreach (['siswa', 'keduanya', 'ujian'] as $cakupan) {
            $this->layar(compact('cakupan'));
            $this->getJson(route('monitoring-pameran.data'))->assertForbidden();
        }
        [$layar] = $this->layar();
        $this->getJson(route('monitoring-pameran.data'))->assertOk();
        $this->getJson(route('monitoring-siswa.data'))->assertForbidden();
        $this->getJson(route('monitoring-ujian-tv.data'))->assertForbidden();
        $this->getJson(route('scan-absensi.index'))->assertUnauthorized();
        $layar->update(['dicabut_pada' => now()]);
        $this->getJson(route('monitoring-pameran.data'))->assertUnauthorized();
        $this->getJson(route('monitoring-pameran.pengunjung'))->assertUnauthorized();
    }

    public function test_identitas_disamarkan_di_server_dan_pencarian_nama_asli_tidak_membocorkan_data(): void
    {
        [$tahun, $a] = $this->fondasi();
        $this->isiAktivitas($tahun, $a);
        $this->layar();
        foreach (['data', 'rincian'] as $route) {
            $this->getJson(route('monitoring-pameran.'.$route, $route === 'rincian' ? ['jenis' => 'laporan'] : []))
                ->assertOk()->assertDontSee('Nama Rahasia')->assertDontSee('KRONOLOGI RAHASIA')->assertDontSee('_siswa')->assertDontSee('nisn');
        }
        $this->getJson(route('monitoring-pameran.rincian', ['jenis' => 'hadir', 'q' => 'Nama Rahasia']))->assertJsonPath('pagination.total', 0);
        $this->getJson(route('monitoring-pameran.rincian', ['jenis' => 'hadir']))->assertJsonPath('items.0.foto_url', null);
        $this->getJson(route('monitoring-pameran.foto', $a[0]->siswa_id))->assertNotFound();
        $this->layar(['pameran_identitas' => true]);
        $this->getJson(route('monitoring-pameran.rincian', ['jenis' => 'hadir']))->assertSee('Nama Rahasia')->assertDontSee('_siswa');
        $this->getJson(route('monitoring-pameran.rincian', ['jenis' => 'laporan', 'q' => $a[1]->siswa->nama_lengkap]))->assertJsonPath('pagination.total', 0);
    }

    public function test_statistik_dan_pengunjung_terbaru_terikat_acara_nama_tampil_tanpa_saran(): void
    {
        $this->fondasi();
        [$layar] = $this->layar();
        $acara = $layar->acaraBukuTamu;
        foreach ([['2026-10-30 09:00', 'valid'], ['2026-10-31 09:00', 'valid'], ['2026-10-31 09:01', 'dibatalkan']] as [$waktu,$status]) {
            $acara->respon()->create(['kunci_kiriman' => Str::uuid(), 'sidik_kiriman' => str_repeat('a', 64), 'nama' => 'TAMU PAMERAN', 'kategori' => 'instansi', 'instansi' => 'INSTANSI INTERNAL', 'minat' => ['nusa'], 'kepuasan' => 4, 'nilai_nusa' => null, 'saran' => 'SARAN RAHASIA', 'status' => $status, 'created_at' => $waktu]);
        }
        $r = $this->getJson(route('monitoring-pameran.pengunjung'))->assertOk()->assertJsonPath('statistik.total', 2)->assertJsonPath('pengunjung_hari_ini', 1)
            ->assertJsonPath('statistik.persen_puas', 100)->assertJsonPath('statistik.penilai_nusa', 0)->assertJsonPath('statistik.persen_puas_nusa', null)
            ->assertJsonPath('terbaru.0.nama', 'TAMU PAMERAN')->assertJsonPath('terbaru.0.label_kategori', 'Instansi')
            ->assertJsonPath('statistik.kategori.instansi', 2)->assertJsonPath('statistik.label_kategori.instansi', 'Instansi')
            ->assertDontSee('SARAN RAHASIA')->assertDontSee('INSTANSI INTERNAL')->assertDontSee('sidik_kiriman')->assertDontSee('kunci_kiriman')->assertJsonMissingPath('items');
        $this->assertCount(2, $r->json('terbaru'));
        $this->assertSame(['id', 'nama', 'kategori', 'label_kategori', 'waktu'], array_keys($r->json('terbaru.0')));
        $this->assertSame('2026-10-29', $r->json('acara.mulai'));
        $this->assertSame('2026-10-30', $layar->fresh()->pameran_tanggal->toDateString());
        $acara->update(['status' => 'draf']);
        $this->getJson(route('monitoring-pameran.pengunjung'))->assertStatus(410);
        $this->getJson(route('monitoring-pameran.data'))->assertOk();
    }

    public function test_modal_pengunjung_memfilter_membatasi_data_dan_menangani_halaman_kosong(): void
    {
        $this->fondasi();
        [$layar] = $this->layar();
        foreach (range(1, 25) as $i) {
            $layar->acaraBukuTamu->respon()->create(['kunci_kiriman' => Str::uuid(), 'sidik_kiriman' => str_repeat('a', 64),
                'nama' => 'Tamu '.str_pad($i, 2, '0', STR_PAD_LEFT), 'kategori' => $i % 2 ? 'instansi' : 'umum',
                'minat' => [$i % 2 ? 'nusa' : 'literasi'], 'kepuasan' => $i % 2 ? 5 : 1,
                'nilai_nusa' => $i % 3 ? null : 4, 'saran' => 'SARAN INTERNAL', 'instansi' => 'INSTANSI INTERNAL',
                'status' => 'valid', 'created_at' => now()->startOfDay()->setTime(9, 0, $i)]);
        }
        $url = route('monitoring-pameran.pengunjung.rincian');
        $this->getJson($url.'?jenis=total')->assertOk()->assertJsonPath('pagination.total', 25)->assertJsonCount(20, 'items')
            ->assertJsonPath('items.0.nama', 'Tamu 25')->assertDontSee('SARAN INTERNAL')->assertDontSee('INSTANSI INTERNAL');
        $this->getJson($url.'?jenis=total&page=2')->assertJsonCount(5, 'items')->assertJsonPath('pagination.page', 2)->assertJsonPath('items.0.nama', 'Tamu 05');
        $this->getJson($url.'?jenis=total&page=999')->assertJsonPath('pagination.page', 2);
        foreach ([['jenis' => 'hari_ini', 'total' => 25], ['jenis' => 'puas', 'total' => 13], ['jenis' => 'nusa', 'total' => 8],
            ['jenis' => 'harian', 'tanggal' => '2026-10-30', 'total' => 0], ['jenis' => 'kepuasan', 'nilai' => 1, 'total' => 12],
            ['jenis' => 'minat', 'minat' => 'literasi', 'total' => 12], ['jenis' => 'total', 'kategori' => 'instansi', 'total' => 13],
            ['jenis' => 'terbaru', 'q' => 'Tamu 25', 'total' => 1]] as $filter) {
            $total = $filter['total']; unset($filter['total']);
            $this->getJson($url.'?'.http_build_query($filter))->assertOk()->assertJsonPath('pagination.total', $total);
        }
        $this->getJson(route('monitoring-pameran.pengunjung'))->assertJsonCount(10, 'terbaru')->assertJsonPath('terbaru.0.nama', 'Tamu 25');
        foreach ([['jenis' => 'salah'], ['jenis' => 'total', 'page' => 0], ['jenis' => 'kepuasan', 'nilai' => 6],
            ['jenis' => 'harian', 'tanggal' => '31/10/2026'], ['jenis' => 'minat', 'minat' => ['nusa']], ['jenis' => 'total', 'kategori' => 'admin']] as $filter) {
            $this->getJson($url.'?'.http_build_query($filter))->assertUnprocessable();
        }
        $layar->acaraBukuTamu->update(['status' => 'draf']);
        $this->getJson($url.'?jenis=total')->assertStatus(410);
        $layar->update(['dicabut_pada' => now()]);
        $this->getJson($url.'?jenis=total')->assertUnauthorized();
    }

    public function test_pengunjung_dibatalkan_dan_acara_lain_tidak_muncul_dan_cache_tiga_detik(): void
    {
        $this->fondasi();
        [$layar] = $this->layar();
        $data = ['kunci_kiriman' => Str::uuid(), 'sidik_kiriman' => str_repeat('a', 64), 'nama' => 'Tamu pertama',
            'kategori' => 'instansi', 'minat' => ['nusa'], 'kepuasan' => 5, 'status' => 'valid'];
        $pertama = $layar->acaraBukuTamu->respon()->create($data);
        $lain = $this->acara();
        $lain->respon()->create(array_replace($data, ['nama' => 'ACARA LAIN']));
        $this->getJson(route('monitoring-pameran.pengunjung'))->assertJsonPath('statistik.total', 1);
        $pertama->update(['status' => 'dibatalkan']);
        $layar->acaraBukuTamu->respon()->create(array_replace($data, ['kunci_kiriman' => Str::uuid(), 'nama' => 'Tamu baru']));
        $this->travel(3)->seconds();
        $this->getJson(route('monitoring-pameran.pengunjung'))->assertJsonPath('statistik.total', 1)
            ->assertJsonPath('terbaru.0.nama', 'Tamu baru')->assertDontSee('Tamu pertama')->assertDontSee('ACARA LAIN');
        $this->getJson(route('monitoring-pameran.pengunjung.rincian', ['jenis' => 'total', 'acara_buku_tamu_id' => $lain->id]))
            ->assertJsonPath('pagination.total', 1)->assertJsonPath('items.0.nama', 'Tamu baru')->assertDontSee('ACARA LAIN');
        $this->layar(['cakupan' => 'siswa']);
        $this->getJson(route('monitoring-pameran.pengunjung.rincian', ['jenis' => 'total']))->assertForbidden();
    }

    public function test_aktivasi_admin_pameran_validasi_acara_tanggal_dan_bisa_logout(): void
    {
        $this->fondasi();
        $acara = $this->acara();
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
        $this->get(route('perangkat-monitoring.index'))->assertOk()->assertSee('Mode pameran');
        $data = ['nama' => 'TV Stand', 'konfirmasi' => 1, 'cakupan' => 'pameran', 'acara_buku_tamu_id' => $acara->id, 'pameran_tanggal' => '2026-10-30'];
        $this->post(route('perangkat-monitoring.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse((bool) PerangkatMonitoring::latest('id')->first()->pameran_identitas);
        $r = $this->post(route('perangkat-monitoring.store'), $data + ['pameran_identitas' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $token = $r->getCookie(AksesMonitorTv::COOKIE)->getValue();
        $this->post(route('logout'));
        Auth::forgetGuards();
        $this->app['session']->flush();
        $this->withCredentials()->withCookie(AksesMonitorTv::COOKIE, $token)->get(route('monitoring-pameran.index'))->assertOk()->assertDontSee($token);
    }

    public function test_keanggotaan_historis_dan_foto_siswa_sudah_pindah_tetap_sesuai_tanggal(): void
    {
        Storage::fake('public');
        [$tahun,$a] = $this->fondasi();
        $siswa = $a[0]->siswa;
        $a[0]->update(['status_keanggotaan' => 'pindah', 'tanggal_keluar' => '2026-10-30']);
        $siswa->update(['aktif' => false, 'foto' => 'foto/siswa.png']);
        Storage::disk('public')->put('foto/siswa.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aPtUAAAAASUVORK5CYII='));
        $this->isiAktivitas($tahun, $a);
        $this->layar(['pameran_identitas' => true]);
        $this->getJson(route('monitoring-pameran.data'))->assertOk()->assertJsonPath('ringkasan.dipantau', 4)->assertJsonPath('ibadah.0.sudah', 1);
        $this->get(route('monitoring-pameran.foto', $siswa->id))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->getJson(route('monitoring-pameran.foto', 9999))->assertNotFound();
        $d = app(MonitoringSiswaService::class)->bangun();
        $this->assertSame(3, $d['ringkasan']['dipantau']);
    }

    public function test_aktivasi_menolak_acara_draf_dan_tanggal_di_luar_rentang(): void
    {
        $this->fondasi();
        $acara = $this->acara();
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
        $data = ['nama' => 'TV Stand', 'konfirmasi' => 1, 'cakupan' => 'pameran', 'acara_buku_tamu_id' => $acara->id, 'pameran_tanggal' => '2026-10-30'];
        foreach ([['pameran_tanggal' => '2026-11-01'], ['pameran_tanggal' => '2026-06-30'], ['acara_buku_tamu_id' => 9999]] as $ubah) {
            $this->postJson(route('perangkat-monitoring.store'), array_replace($data, $ubah))->assertUnprocessable();
        }
        $acara->update(['status' => 'draf']);
        $this->postJson(route('perangkat-monitoring.store'), $data)->assertUnprocessable();
        $this->assertDatabaseCount('perangkat_monitoring', 0);
    }

    public function test_ibadah_tidak_memakai_catatan_kelas_lama_setelah_pindah_pada_tanggal_data(): void
    {
        [$tahun, $a] = $this->fondasi();
        $this->isiAktivitas($tahun, $a);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.B', 'tingkat' => 8, 'aktif' => true]);
        $a[0]->update(['kelas_id' => $kelas->id]);
        $this->layar();
        $this->getJson(route('monitoring-pameran.data'))->assertOk()->assertJsonPath('ringkasan.hadir', 1)
            ->assertJsonPath('ibadah.0.wajib', 1)->assertJsonPath('ibadah.0.sudah', 0)->assertJsonPath('ibadah.0.belum', 1);
    }

    private function fondasi(): array
    {
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.A', 'tingkat' => 8, 'aktif' => true]);
        $a = [];
        foreach (range(0, 3) as $i) {
            $s = Siswa::create(['nama_lengkap' => 'Nama Rahasia '.$i, 'aktif' => true, 'jenis_kelamin' => 'L']);
            $a[] = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $s->id, 'status_keanggotaan' => 'aktif', 'tanggal_masuk' => '2026-07-01']);
        }
        PengaturanAbsensi::create(['hari' => 'jumat', 'urutan_hari' => 5, 'aktif' => true, 'jam_scan_masuk_mulai' => '05:30', 'jam_masuk' => '06:40', 'jam_scan_masuk_selesai' => '10:00', 'jam_scan_pulang_mulai' => '13:00', 'jam_pulang' => '14:00', 'jam_scan_pulang_selesai' => '16:00']);

        return [$tahun, $a];
    }

    private function isiAktivitas($tahun, array $a): void
    {
        foreach ([[0, 'hadir', '2026-10-30'], [1, 'izin', '2026-10-30'], [2, 'hadir', '2026-10-30'], [1, 'hadir', '2026-10-31']] as [$i,$status,$tanggal]) {
            AbsensiSiswa::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $a[$i]->kelas_id, 'anggota_kelas_id' => $a[$i]->id, 'siswa_id' => $a[$i]->siswa_id, 'tanggal' => $tanggal, 'status_kehadiran' => $status, 'jam_masuk' => '06:45', 'status_masuk' => $i === 0 ? 'terlambat' : 'tepat_waktu', 'menit_terlambat' => $i === 0 ? 5 : 0, 'sumber' => 'manual']);
        }
        foreach ([[0, 'pelanggaran', '2026-10-30 09:00'], [1, 'pembinaan', '2026-10-30 09:01'], [2, 'pelanggaran', '2026-10-31 09:00']] as [$i,$jenis,$waktu]) {
            $laporan = LaporanPembinaanSiswa::create(['nomor_laporan' => Str::uuid(), 'jenis_laporan' => $jenis, 'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $a[$i]->kelas_id, 'siswa_id' => $a[$i]->siswa_id, 'tanggal_kejadian' => '2026-10-29', 'status' => 'baru', 'status_verifikasi' => 'diajukan', 'kronologi' => 'KRONOLOGI RAHASIA', 'created_at' => $waktu]);
            $laporan->forceFill(['created_at' => $waktu])->save();
        }
        foreach ([[15, '2026-10-29 09:00'], [25, '2026-10-30 09:00'], [-10, '2026-10-31 09:00']] as [$p,$waktu]) {
            TransaksiPoinSiswa::create(['siswa_id' => $a[0]->siswa_id, 'tahun_pelajaran_id' => $tahun->id, 'jenis' => $p > 0 ? 'pelanggaran' : 'pembatalan', 'poin' => $p, 'tercatat_pada' => $waktu, 'kunci_sumber' => Str::uuid(), 'keterangan' => 'Data uji']);
        }
        $k = KegiatanIbadah::create(['kode' => 'dhuha-pameran', 'nama' => 'Salat Dhuha', 'aktif' => true]);
        $j = JadwalKegiatanIbadah::create(['tahun_pelajaran_id' => $tahun->id, 'kegiatan_ibadah_id' => $k->id, 'hari' => 'jumat', 'urutan_hari' => 5, 'jam_scan_mulai' => '07:00', 'jam_pelaksanaan' => '08:00', 'jam_scan_selesai' => '09:00', 'aktif' => true]);
        PresensiKegiatanIbadah::create(['jadwal_kegiatan_ibadah_id' => $j->id, 'kegiatan_ibadah_id' => $k->id, 'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $a[0]->kelas_id, 'siswa_id' => $a[0]->siswa_id, 'anggota_kelas_id' => $a[0]->id, 'tanggal' => '2026-10-30', 'waktu_scan' => '08:01:00', 'sumber' => 'scan']);
    }

    private function acara(): AcaraBukuTamu
    {
        return AcaraBukuTamu::create(['nama' => 'Festival Literasi IV Kota Padang Panjang 2026', 'lokasi' => 'Lapangan Banca Laweh', 'mulai_pada' => '2026-10-29 00:00', 'selesai_pada' => '2026-10-31 23:59:59', 'status' => 'dibuka', 'kode_publik' => Str::lower(Str::random(24)), 'kunci_pembuatan' => Str::uuid()]);
    }

    private function layar(array $ubah = []): array
    {
        $token = Str::random(80);
        $layar = PerangkatMonitoring::create(array_replace(['nama' => 'TV Pameran', 'cakupan' => 'pameran', 'token_hash' => hash('sha256', $token), 'acara_buku_tamu_id' => $this->acara()->id, 'pameran_tanggal' => '2026-10-30', 'pameran_identitas' => false], $ubah));
        $this->withCredentials()->withCookie(AksesMonitorTv::COOKIE, $token);

        return [$layar, $token];
    }

    private function capture(PerangkatMonitoring $layar): void
    {
        if (! getenv('NUSA_CAPTURE_PAMERAN')) {
            return;
        }
        $path = storage_path('framework/testing/monitor-pameran');
        File::ensureDirectoryExists($path);
        foreach (range(1, 12) as $i) {
            $layar->acaraBukuTamu->respon()->create(['kunci_kiriman' => Str::uuid(), 'sidik_kiriman' => str_repeat('a', 64), 'nama' => 'Pengunjung sintetis '.$i, 'kategori' => ['umum', 'pelajar', 'pendidik', 'orang_tua', 'instansi'][$i % 5], 'minat' => $i % 2 ? ['nusa'] : ['literasi', 'karya_siswa'], 'kepuasan' => ($i % 5) + 1, 'nilai_nusa' => $i % 3 ? 4 : null, 'status' => 'valid', 'created_at' => '2026-10-'.(29 + $i % 3).' 09:00:00']);
        }
        File::put($path.'/index.html', $this->get(route('monitoring-pameran.index'))->assertOk()->getContent());
        $service = app(MonitoringPameranService::class);
        File::put($path.'/data.json', json_encode($service->aktivitas($layar)));
        $details = [];
        foreach (['dipantau', 'hadir', 'izin', 'sakit', 'alfa', 'belum', 'terlambat', 'laporan', 'ibadah', 'poin', 'poin_tambah', 'poin_kurang'] as $jenis) {
            $details[$jenis] = app(MonitoringSiswaService::class)->rincian($service->aktivitas($layar, true), compact('jenis'));
        }
        File::put($path.'/rincian.json', json_encode($details));
        File::put($path.'/tamu.json', json_encode($service->pengunjung($layar)));
        $tamuDetails = [];
        foreach (['total', 'hari_ini', 'puas', 'nusa', 'harian', 'kepuasan', 'minat', 'terbaru'] as $jenis) {
            $tamuDetails[$jenis] = $service->rincianPengunjung($layar, compact('jenis'));
        }
        foreach ([['jenis' => 'harian', 'tanggal' => '2026-10-30'], ['jenis' => 'kepuasan', 'nilai' => 5], ['jenis' => 'minat', 'minat' => 'nusa']] as $filter) {
            $tamuDetails[implode('-', $filter)] = $service->rincianPengunjung($layar, $filter);
        }
        File::put($path.'/tamu-rincian.json', json_encode($tamuDetails));
        $lain = clone $layar;
        $lain->pameran_tanggal = '2026-10-29';
        File::put($path.'/data-29.json', json_encode($service->aktivitas($lain)));
        $details = [];
        foreach (['dipantau', 'hadir', 'izin', 'sakit', 'alfa', 'belum', 'terlambat', 'laporan', 'ibadah', 'poin', 'poin_tambah', 'poin_kurang'] as $jenis) {
            $details[$jenis] = app(MonitoringSiswaService::class)->rincian($service->aktivitas($lain, true), compact('jenis'));
        }
        File::put($path.'/rincian-29.json', json_encode($details));
    }
}
