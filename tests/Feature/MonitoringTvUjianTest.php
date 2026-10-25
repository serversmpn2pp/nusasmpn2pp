<?php

namespace Tests\Feature;

use App\Models\AktivitasKeamananUjianCbt;
use App\Models\AnggotaKelas;
use App\Models\JadwalUjianCbt;
use App\Models\JawabanPesertaUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PerangkatMonitoring;
use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Models\SesiUjianCbt;
use App\Models\Siswa;
use App\Models\SoalCbt;
use App\Models\SoalUjianCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use App\Services\Monitoring\AksesMonitorTv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitoringTvUjianTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(8, 30));
    }

    public function test_hanya_browser_dengan_aktivasi_ujian_dapat_membaca_bukan_login_admin_saja(): void
    {
        $this->get(route('monitoring-ujian-tv.index'))->assertUnauthorized();
        $this->actingAs($this->admin())->getJson(route('monitoring-ujian-tv.data'))->assertUnauthorized();
        $token = Str::random(80);
        PerangkatMonitoring::create(['nama' => 'TV lama', 'token_hash' => hash('sha256', $token)]);
        $this->withCredentials()->withCookie(AksesMonitorTv::COOKIE, $token)->getJson(route('monitoring-ujian-tv.data'))->assertForbidden();
        $this->getJson(route('monitoring-siswa.data'))->assertOk();
    }

    public function test_aktivasi_memerlukan_kegiatan_yang_sah_dan_tetap_berjalan_setelah_logout(): void
    {
        $d = $this->fondasi();
        $this->actingAs($this->admin())->post(route('perangkat-monitoring.store'), ['nama' => 'TV ujian', 'cakupan' => 'ujian', 'konfirmasi' => 1])->assertSessionHasErrors('kegiatan_ujian_cbt_id');
        $r = $this->post(route('perangkat-monitoring.store'), ['nama' => 'TV ujian', 'cakupan' => 'ujian', 'kegiatan_ujian_cbt_id' => $d['kegiatan']->id, 'konfirmasi' => 1])->assertRedirect();
        $token = $r->getCookie(AksesMonitorTv::COOKIE)->getValue();
        $this->assertTrue($r->getCookie(AksesMonitorTv::COOKIE)->isHttpOnly());
        $this->assertSame('ujian', PerangkatMonitoring::first()->cakupan);
        $this->withCredentials()->withCookie(AksesMonitorTv::COOKIE, $token);
        $this->post(route('logout'))->assertRedirect(route('login'));
        Auth::forgetGuards();
        $this->getJson(route('monitoring-ujian-tv.data'))->assertOk()->assertJsonPath('ringkasan.total', 8)->assertCookieMissing(config('session.cookie'))->assertCookieMissing('XSRF-TOKEN');
        $this->assertFalse(request()->hasSession());
        $this->getJson(route('monitoring-siswa.data'))->assertForbidden();
        $this->get(route('perangkat-monitoring.index'))->assertRedirect(route('login'));
    }

    public function test_jadwal_sesi_dan_kegiatan_lain_tidak_bisa_dibaca_termasuk_snapshot_cache(): void
    {
        $d = $this->fondasi();
        $lain = $this->fondasi();
        $this->perangkat($d);
        $this->getJson(route('monitoring-ujian-tv.data'))->assertOk()->assertJsonCount(1, 'pilihan_jadwal')->assertJsonPath('ringkasan.total', 8);
        $this->getJson(route('monitoring-ujian-tv.data', ['jadwal_id' => $lain['jadwal']->id]))->assertStatus(422);
        $this->getJson(route('monitoring-ujian-tv.data', ['sesi_id' => $lain['sesi']->id]))->assertStatus(422);
        $d['jadwal']->update(['status' => 'dibatalkan']);
        $this->getJson(route('monitoring-ujian-tv.data', ['jadwal_id' => $d['jadwal']->id]))->assertStatus(422);
    }

    public function test_ringkasan_ruang_dan_perhatian_memakai_status_ujian_bukan_presensi_sekolah(): void
    {
        $d = $this->fondasi();
        $this->perangkat($d);
        $r = $this->getJson(route('monitoring-ujian-tv.data'))->assertOk();
        $summary = $r->json('ringkasan');
        $this->assertSame(8, $summary['total']);
        $this->assertSame(2, $summary['mengerjakan']);
        $this->assertSame(1, $summary['selesai']);
        $this->assertSame(1, $summary['belum_hadir']);
        $this->assertSame(1, $summary['hadir_belum_mulai']);
        $this->assertSame(1, $summary['tidak_hadir']);
        $this->assertSame(1, $summary['terblokir']);
        $this->assertSame(1, $summary['nonaktif']);
        $this->assertSame(3, $summary['perlu_perhatian']);
        $this->assertSame(8, array_sum(array_column(array_column($r->json('ruang'), 'ringkasan'), 'total')));
        $this->assertStringContainsString('Pengawas Uji', $r->json('ruang.0.pengawas'));
    }

    public function test_jawaban_hanya_jumlah_tersimpan_tanpa_soal_kunci_nilai_token_atau_metadata_rahasia(): void
    {
        $d = $this->fondasi();
        foreach ($d['soal'] as $i => $soal) {
            JawabanPesertaUjianCbt::create(['peserta_ujian_cbt_id' => $d['peserta'][2]->id,
                'soal_ujian_cbt_id' => $soal->id, 'soal_cbt_id' => $soal->soal_cbt_id, 'jawaban' => $i === 2 ? null : ['jawaban' => 'JAWABAN-RAHASIA'], 'skor' => 99]);
        }
        AktivitasKeamananUjianCbt::create(['peserta_ujian_cbt_id' => $d['peserta'][2]->id, 'jenis' => 'keluar_aplikasi', 'mulai_pada' => now(), 'metadata' => ['rahasia' => 'METADATA-RAHASIA'], 'catatan' => 'CATATAN-RAHASIA', 'ip' => '10.99.88.77']);
        $this->perangkat($d);
        $r = $this->getJson(route('monitoring-ujian-tv.data'))->assertOk();
        $row = collect($r->json('peserta'))->firstWhere('id', $d['peserta'][2]->id);
        $this->assertSame(2, $row['jawaban']);
        $this->assertSame(3, $row['jumlah_soal']);
        $this->assertSame(67, $row['persen']);
        foreach (['JAWABAN-RAHASIA', 'METADATA-RAHASIA', 'CATATAN-RAHASIA', 'PERTANYAAN-RAHASIA', 'KUNCI-RAHASIA', 'TOKEN-RAHASIA', '10.99.88.77', 'kata_sandi', '"skor"', '"benar"', 'nisn'] as $secret) {
            $r->assertDontSee($secret);
        }
        $this->assertSame('Aktivitas keluar aplikasi', $r->json('aktivitas.0.label'));
    }

    public function test_waktu_tambahan_dihormati_dan_monitor_tidak_menutup_ujian_kedaluwarsa(): void
    {
        $d = $this->fondasi();
        $d['peserta'][2]->update(['waktu_mulai' => now()->subHours(2)]);
        $d['peserta'][7]->update(['waktu_mulai' => now()->subHours(2), 'waktu_tambahan_sampai' => now()->addMinutes(20)]);
        $sebelum = DB::table('peserta_ujian_cbt')->orderBy('id')->get()->toJson();
        $this->perangkat($d);
        $r = $this->getJson(route('monitoring-ujian-tv.data'))->assertOk();
        $rows = collect($r->json('peserta'))->keyBy('id');
        $this->assertContains('Waktu habis, menunggu penutupan sistem', $rows[$d['peserta'][2]->id]['peringatan']);
        $this->assertNotContains('Waktu habis, menunggu penutupan sistem', $rows[$d['peserta'][7]->id]['peringatan']);
        $this->assertSame(now()->addMinutes(20)->toIso8601String(), $rows[$d['peserta'][7]->id]['batas_waktu']);
        $this->assertSame($sebelum, DB::table('peserta_ujian_cbt')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
        $this->assertNull($d['ujian']->fresh()->hasil_difinalisasi_pada);
    }

    public function test_sesi_filter_dan_peserta_tanpa_ruang_tetap_terhitung(): void
    {
        $d = $this->fondasi();
        $d['peserta'][0]->update(['ruang_ujian_cbt_id' => null]);
        $this->perangkat($d);
        $this->getJson(route('monitoring-ujian-tv.data', ['sesi_id' => $d['sesi']->id]))->assertOk()->assertJsonPath('ringkasan.total', 8)
            ->assertJsonFragment(['nama' => 'Belum ditempatkan']);
    }

    public function test_pencabutan_dan_kegiatan_nonaktif_menolak_sebelum_membaca_cache(): void
    {
        $d = $this->fondasi();
        $this->perangkat($d);
        $this->getJson(route('monitoring-ujian-tv.data'))->assertOk();
        $d['kegiatan']->update(['status' => 'nonaktif']);
        $this->getJson(route('monitoring-ujian-tv.data'))->assertNotFound();
        PerangkatMonitoring::query()->update(['dicabut_pada' => now()]);
        $this->getJson(route('monitoring-ujian-tv.data'))->assertUnauthorized();
    }

    public function test_hari_tanpa_jadwal_kosong_dan_pemilihan_eksplisit_tidak_mencampur_jadwal(): void
    {
        $d = $this->fondasi();
        $this->perangkat($d);
        $this->travel(1)->days();
        $this->getJson(route('monitoring-ujian-tv.data'))->assertOk()->assertJsonPath('jadwal', null)->assertJsonPath('ringkasan.total', 0);
        $this->getJson(route('monitoring-ujian-tv.data', ['jadwal_id' => $d['jadwal']->id]))->assertOk()->assertJsonPath('ringkasan.total', 8);
    }

    public function test_query_tetap_dan_tampilan_baca_saja_dengan_fixture_tv_sintetis(): void
    {
        $d = $this->fondasi(getenv('NUSA_CAPTURE_EXAM_TV') ? 48 : 8);
        $this->perangkat($d);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $r = $this->getJson(route('monitoring-ujian-tv.data'))->assertOk();
        $this->assertLessThanOrEqual(22, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $html = $this->get(route('monitoring-ujian-tv.index'))->assertOk()->assertSee('monitoring-ujian-tv.js')->assertDontSee('app-sidebar')->assertDontSee('csrf-token');
        $this->assertStringContainsString('const REFRESH_MS = 5000', File::get(public_path('js/monitoring-ujian-tv.js')));
        if (getenv('NUSA_CAPTURE_EXAM_TV')) {
            $folder = storage_path('framework/testing/exam-tv');
            File::ensureDirectoryExists($folder);
            File::put($folder.'/index.html', $html->getContent());
            File::put($folder.'/data.json', $r->getContent());
        }
    }

    public function test_hadir_sebelum_jadwal_dan_heartbeat_tepat_120_detik_tidak_diperingatkan(): void
    {
        $d = $this->fondasi();
        $d['peserta'][2]->update(['heartbeat_terakhir_pada' => now()->subSeconds(120)]);
        $this->perangkat($d);
        $r = $this->getJson(route('monitoring-ujian-tv.data'))->assertOk();
        $p = collect($r->json('peserta'))->firstWhere('id', $d['peserta'][2]->id);
        $this->assertNotContains('Komunikasi belum diperbarui > 2 menit', $p['peringatan']);
        $this->travel(5)->seconds();
        $r = $this->getJson(route('monitoring-ujian-tv.data'))->assertOk();
        $p = collect($r->json('peserta'))->firstWhere('id', $d['peserta'][2]->id);
        $this->assertContains('Komunikasi belum diperbarui > 2 menit', $p['peringatan']);
        $this->travelTo(now()->setTime(7, 30));
        $r = $this->getJson(route('monitoring-ujian-tv.data'))->assertOk();
        $p = collect($r->json('peserta'))->firstWhere('id', $d['peserta'][1]->id);
        $this->assertNotContains('Hadir tetapi belum mulai', $p['peringatan']);
    }

    public function test_cakupan_keduanya_dapat_memakai_dua_layar_tanpa_memperluas_perangkat_lain(): void
    {
        $d = $this->fondasi();
        $this->perangkat($d);
        PerangkatMonitoring::query()->update(['cakupan' => 'keduanya']);
        $this->getJson(route('monitoring-siswa.data'))->assertOk();
        $this->getJson(route('monitoring-ujian-tv.data'))->assertOk();
        $this->actingAs($this->admin())->get(route('pusat-cbt.index'))->assertOk()->assertSee('Monitoring TV ujian');
    }

    private function admin(): Pengguna
    {
        return Pengguna::where('username', 'administrator')->firstOrFail();
    }

    private function perangkat(array $d): void
    {
        $token = Str::random(80);
        PerangkatMonitoring::create(['nama' => 'TV ujian uji', 'token_hash' => hash('sha256', $token), 'cakupan' => 'ujian', 'kegiatan_ujian_cbt_id' => $d['kegiatan']->id]);
        $this->withCredentials()->withCookie(AksesMonitorTv::COOKIE, $token);
    }

    private function fondasi(int $jumlah = 8): array
    {
        $suffix = Str::random(8);
        $tahun = TahunPelajaran::create(['nama' => '2026/2027 '.$suffix, 'aktif' => true]);
        $jenis = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $kegiatan = KegiatanUjianCbt::create(['nama' => 'Sumatif Tengah Semester 2026/2027', 'kode' => 'TV-'.$suffix, 'tahun_pelajaran_id' => $tahun->id, 'jenis_ujian_cbt_id' => $jenis->id, 'semester' => 'ganjil', 'status' => 'aktif']);
        $mapel = MataPelajaran::firstOrCreate(['nama' => 'Matematika'], ['kode' => 'MTK-'.$suffix, 'aktif' => true]);
        $ujian = UjianCbt::create(['kode' => 'PAKET-'.$suffix, 'nama' => 'STS Matematika', 'alur' => 'terpusat', 'tahun_pelajaran_id' => $tahun->id, 'jenis_ujian_cbt_id' => $jenis->id, 'mata_pelajaran_id' => $mapel->id,
            'semester' => 'ganjil', 'tingkat' => 8, 'status' => 'berlangsung', 'tanggal_mulai' => now()->setTime(8, 0), 'tanggal_selesai' => now()->setTime(9, 0), 'durasi_menit' => 60, 'jumlah_soal' => 3, 'token' => 'TOKEN-RAHASIA']);
        $jadwal = JadwalUjianCbt::create(['kegiatan_ujian_cbt_id' => $kegiatan->id, 'ujian_cbt_id' => $ujian->id, 'mata_pelajaran_id' => $mapel->id, 'tanggal' => now()->toDateString(), 'waktu_mulai' => '08:00:00', 'waktu_selesai' => '09:00:00', 'tingkat' => 8, 'status' => 'siap']);
        $sesi = SesiUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kode' => 'S01', 'nama' => 'Sesi pagi', 'waktu_mulai' => now()->setTime(8, 0), 'waktu_selesai' => now()->setTime(9, 0), 'status' => 'aktif']);
        $kelas = Kelas::create(['nama' => 'VIII.A Bilingual', 'tahun_pelajaran_id' => $tahun->id, 'tingkat' => 8, 'aktif' => true]);
        $kelasUjian = KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $kelas->id]);
        $guru = Pegawai::create(['nama_lengkap' => 'Pengawas Uji Akademik, S.Pd.', 'aktif' => true]);
        $rooms = collect(range(1, 6))->map(fn ($i) => RuangUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'sesi_ujian_cbt_id' => $sesi->id, 'jadwal_ujian_cbt_id' => $jadwal->id, 'kode' => 'R0'.$i, 'nama' => 'Ruang '.$i, 'pengawas_utama_pegawai_id' => $guru->id, 'status' => 'berlangsung']));
        $peserta = [];
        foreach (range(0, $jumlah - 1) as $i) {
            $siswa = Siswa::create(['nama_lengkap' => 'Siswa Uji Monitoring '.($i + 1), 'aktif' => true]);
            $a = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'status_keanggotaan' => 'aktif', 'nomor_absen' => $i + 1]);
            $status = ['aktif', 'aktif', 'sedang_mengerjakan', 'selesai', 'aktif', 'terblokir', 'nonaktif', 'sedang_mengerjakan'][$i % 8];
            $presensi = ['belum_absen', 'hadir', 'hadir', 'hadir', 'izin', 'hadir', 'belum_absen', 'hadir'][$i % 8];
            $peserta[] = PesertaUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'sesi_ujian_cbt_id' => $sesi->id, 'kelas_ujian_cbt_id' => $kelasUjian->id, 'anggota_kelas_id' => $a->id,
                'ruang_ujian_cbt_id' => $rooms[$i % 6]->id, 'nomor_meja' => intdiv($i, 6) + 1, 'nomor_peserta' => 'TV-'.$suffix.'-'.$i, 'status' => $status, 'status_kehadiran_ujian' => $presensi,
                'waktu_mulai' => in_array($status, ['sedang_mengerjakan', 'selesai', 'terblokir']) ? now()->setTime(8, 0) : null,
                'waktu_selesai' => $status === 'selesai' ? now()->setTime(8, 20) : null,
                'heartbeat_terakhir_pada' => $i % 8 === 7 ? now()->subMinutes(3) : ($status === 'sedang_mengerjakan' ? now() : null)]);
        }
        $soal = [];
        foreach (range(1, 3) as $i) {
            $s = SoalCbt::create(['kode' => 'SOAL-'.$suffix.'-'.$i, 'mata_pelajaran_id' => $mapel->id, 'tingkat' => 8, 'jenis_soal' => 'pilihan_ganda', 'pertanyaan' => 'PERTANYAAN-RAHASIA', 'kunci_jawaban' => ['jawaban' => 'KUNCI-RAHASIA'], 'aktif' => true]);
            $soal[] = SoalUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'soal_cbt_id' => $s->id, 'nomor_urut' => $i, 'bobot' => 1]);
        }

        return compact('kegiatan', 'jadwal', 'ujian', 'sesi', 'peserta', 'soal');
    }
}
