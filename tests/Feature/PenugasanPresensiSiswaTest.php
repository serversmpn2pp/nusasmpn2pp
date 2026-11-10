<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\NotifikasiPengguna;
use App\Models\PengaturanAbsensi;
use App\Models\PengaturanPoinKeterlambatan;
use App\Models\PengecualianPresensiSiswa;
use App\Models\Pengguna;
use App\Models\RiwayatPerubahanAbsensiSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Absensi\AturanAlfaOtomatisSiswaService;
use App\Services\Absensi\KoreksiPresensiSiswaService;
use App\Services\Absensi\ProsesScanAbsensi;
use App\Services\AkunOrangTuaService;
use App\Services\Ibadah\EvaluasiIbadahMingguan;
use App\Services\Notifikasi\IsiPushNotifikasiService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PenugasanPresensiSiswaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 07:15:00');
        if (! is_dir(storage_path('framework/testing/penugasan-2026-11-10'))) {
            mkdir(storage_path('framework/testing/penugasan-2026-11-10'), 0777, true);
        }
        config(['services.firebase.enabled' => false]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_web_memilih_dua_siswa_lintas_kelas_dengan_pratinjau_dan_idempoten(): void
    {
        $d = $this->data();
        $index = $this->actingAs($d['admin'])->get(route('pengecualian-presensi.index'))->assertOk()
            ->assertSee('exception-student-search')->assertSee('anggota_kelas_ids[]', false);
        $preview = $this->preview($d)->assertOk()->assertViewHas('dampak', fn ($r) => $r['hadir_penugasan'] === 2);
        file_put_contents(storage_path('framework/testing/penugasan-2026-11-10/index.html'), $index->getContent());
        file_put_contents(storage_path('framework/testing/penugasan-2026-11-10/preview.html'), $preview->getContent());
        $this->get(route('pengecualian-presensi.index'))->assertOk()
            ->assertSee('value="'.$d['anggota'][0]->id.'" checked', false)
            ->assertSee('value="'.$d['anggota'][1]->id.'" checked', false);
        $this->assertDatabaseCount('absensi_siswa', 0);
        $this->simpan($preview->viewData('token'));
        $this->simpan($preview->viewData('token'));
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 1);
        $this->assertDatabaseCount('pengecualian_presensi_anggota', 2);
        $this->assertDatabaseCount('absensi_siswa', 2);
        foreach (AbsensiSiswa::get() as $a) {
            $this->assertSame('hadir', $a->status_kehadiran);
            $this->assertSame('tugas_sekolah', $a->status_masuk);
            $this->assertNull($a->jam_masuk);
            $this->assertSame(0, $a->sanksi_manual_menit);
            $this->assertSame(0, $a->poin_keterlambatan_terhitung);
            $this->assertStringContainsString('Lomba resmi sekolah', $a->catatanKehadiran());
        }
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
        Carbon::setTestNow('2026-11-11 09:00:00');
        $aturan = app(AturanAlfaOtomatisSiswaService::class);
        $this->assertFalse($aturan->menjadiAlfa('2026-11-10', ['selasa'], $d['anggota'][0]));
        $this->assertTrue($aturan->menjadiAlfa('2026-11-10', ['selasa'], $d['anggota'][2]));
        $q = AnggotaKelas::query();
        $aturan->batasiPengecualian($q, '2026-11-10', true);
        $this->assertEqualsCanonicalizing([$d['anggota'][0]->id, $d['anggota'][1]->id], $q->pluck('id')->all());
    }

    public function test_poin_manual_dibatalkan_hanya_untuk_peserta_dan_pulih_saat_penugasan_dibatalkan(): void
    {
        $d = $this->data();
        $layanan = app(KoreksiPresensiSiswaService::class);
        foreach ([0, 2] as $i) {
            $layanan->koreksi($d['admin'], $d['anggota'][$i], $this->koreksi());
        }
        $lama = AbsensiSiswa::where('anggota_kelas_id', $d['anggota'][0]->id)->firstOrFail();
        $this->assertSame(15, $lama->poin_keterlambatan_terhitung);
        $preview = $this->preview($d)->assertOk()->assertViewHas('dampak', fn ($r) => $r['poin_dibatalkan'] === 15);
        $this->simpan($preview->viewData('token'));
        $this->assertSame(0, $lama->fresh()->poin_keterlambatan_terhitung);
        $this->assertSame(0, $lama->fresh()->menit_terlambat);
        $this->assertSame(15, AbsensiSiswa::where('anggota_kelas_id', $d['anggota'][2]->id)->firstOrFail()->poin_keterlambatan_terhitung);
        $this->assertSame(15, (int) TransaksiPoinSiswa::sum('poin'));
        $this->batalkan();
        $this->assertSame(15, $lama->fresh()->poin_keterlambatan_terhitung);
        $this->assertSame(5, $lama->fresh()->sanksi_manual_menit);
        $this->assertSame('kartu_hilang', $lama->fresh()->alasan_hadir_manual);
        $this->assertSame(30, (int) TransaksiPoinSiswa::sum('poin'));
    }

    public function test_jam_scan_sebenarnya_dipertahankan_dan_tidak_dilaporkan_terlambat(): void
    {
        $d = $this->data();
        $this->simpan($this->preview($d)->assertOk()->viewData('token'));
        $hasil = app(ProsesScanAbsensi::class)->proses($d['anggota'][0]->siswa->nisn, now(), 'masuk');
        $this->assertTrue($hasil['berhasil']);
        $a = AbsensiSiswa::where('anggota_kelas_id', $d['anggota'][0]->id)->firstOrFail();
        $this->assertSame('07:15:00', $a->jam_masuk);
        $this->assertSame(0, $a->menit_terlambat);
        $this->assertStringNotContainsString('Siswa terlambat', $hasil['pesan']);
        $this->batalkan();
        $a->refresh();
        $this->assertSame('07:15:00', $a->jam_masuk);
        $this->assertSame(15, $a->menit_terlambat);
        $this->assertSame(0, $a->sanksi_manual_menit);
        $this->assertNull($a->penugasan_presensi_id);
        $this->assertDatabaseCount('log_scan_absensi', 1);
    }

    public function test_batal_tanpa_scan_mengembalikan_keadaan_tanpa_catatan_dengan_riwayat_penugasan(): void
    {
        $d = $this->data();
        $this->simpan($this->preview($d)->assertOk()->viewData('token'));
        $this->batalkan();
        $this->assertDatabaseCount('absensi_siswa', 0);
        $this->assertDatabaseCount('pengecualian_presensi_anggota', 2);
        $this->assertFalse(PengecualianPresensiSiswa::firstOrFail()->aktif);
        $this->batalkan();
        $this->assertDatabaseCount('absensi_siswa', 0);
    }

    public function test_koreksi_jam_scan_selama_tugas_tidak_mengembalikan_menit_lama_saat_batal(): void
    {
        $d = $this->data();
        $this->simpan($this->preview($d)->assertOk()->viewData('token'));
        app(ProsesScanAbsensi::class)->proses($d['anggota'][0]->siswa->nisn, now(), 'masuk');
        app(KoreksiPresensiSiswaService::class)->koreksi($d['admin'], $d['anggota'][0], $this->koreksi());
        $this->batalkan();
        $a = AbsensiSiswa::where('anggota_kelas_id', $d['anggota'][0]->id)->firstOrFail();
        $this->assertSame('06:55:00', $a->jam_masuk);
        $this->assertSame(0, $a->menit_terlambat);
        $this->assertSame(0, $a->sanksi_manual_menit);
    }

    public function test_koreksi_web_saat_tugas_tidak_memerlukan_jam_masuk_fiktif_atau_alasan_kartu(): void
    {
        $d = $this->data();
        $this->simpan($this->preview($d)->assertOk()->viewData('token'));
        $this->get(route('rekap-absensi-harian.koreksi.edit', ['anggotaKelas' => $d['anggota'][0], 'tanggal' => '2026-11-10']))
            ->assertOk()->assertDontSee('id="alasan_hadir_manual"', false);
        $this->put(route('rekap-absensi-harian.koreksi.update', $d['anggota'][0]), ['tanggal' => '2026-11-10',
            'status_kehadiran' => 'hadir', 'catatan' => 'Tambahan keterangan kegiatan resmi.'])->assertRedirect()->assertSessionHasNoErrors();
        $a = AbsensiSiswa::where('anggota_kelas_id', $d['anggota'][0]->id)->firstOrFail();
        $this->assertNull($a->jam_masuk);
        $this->assertSame(0, $a->sanksi_manual_menit);
        $this->assertSame(0, $a->poin_keterlambatan_terhitung);
        $this->batalkan();
        $this->assertDatabaseCount('absensi_siswa', 0);
        $pivot = PengecualianPresensiSiswa::firstOrFail()->anggotaKelas->first()->pivot;
        $this->assertStringContainsString('Tambahan keterangan kegiatan resmi.', $pivot->riwayat_pembatalan);
    }

    public function test_validasi_peserta_tahun_dan_hak_akses(): void
    {
        $d = $this->data();
        $this->preview($d, ['anggota_kelas_ids' => []])->assertSessionHasErrors('anggota_kelas_ids');
        $this->preview($d, ['anggota_kelas_ids' => [999999]])->assertSessionHasErrors('anggota_kelas_ids');
        $tahun = TahunPelajaran::create(['nama' => '2027/2028', 'tanggal_mulai' => '2027-07-01', 'tanggal_selesai' => '2028-06-30']);
        $this->preview($d, ['tahun_pelajaran_id' => $tahun->id, 'tanggal_mulai' => '2027-11-10', 'tanggal_selesai' => '2027-11-10'])->assertSessionHasErrors('anggota_kelas_ids');
        $akun = Pengguna::create(['nama' => 'Siswa', 'username' => 'penugasan.siswa', 'siswa_id' => $d['anggota'][0]->siswa_id, 'kata_sandi' => 'Tes123', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $this->actingAs($akun)->get(route('pengecualian-presensi.index'))->assertForbidden();
    }

    public function test_rentang_penugasan_mengikuti_hari_sekolah_dan_laporan_tidak_menghitung_hari_mendatang(): void
    {
        $d = $this->data();
        app(KoreksiPresensiSiswaService::class)->koreksi($d['admin'], $d['anggota'][0], ['tanggal' => '2026-11-15', 'status_kehadiran' => 'sakit', 'catatan' => 'Catatan di luar hari presensi aktif.']);
        $this->simpan($this->preview($d, ['tanggal_selesai' => '2026-11-17'])->assertOk()
            ->assertViewHas('dampak', fn ($r) => $r['sakit_dikoreksi'] === 0 && $r['izin_dikoreksi'] === 0)->viewData('token'));
        $this->assertDatabaseCount('absensi_siswa', 5);
        $this->assertSame(2, AbsensiSiswa::whereDate('tanggal', '2026-11-17')->count());
        $this->get(route('laporan-absensi.index', ['periode' => 'bulanan', 'bulan' => '2026-11', 'tahun_pelajaran_id' => $d['tahun']->id]))
            ->assertOk()->assertViewHas('ringkasan', fn ($r) => $r['hadir'] === 2);
        $this->batalkan();
        $this->assertDatabaseCount('absensi_siswa', 1);
        $this->assertSame('sakit', AbsensiSiswa::whereDate('tanggal', '2026-11-15')->firstOrFail()->status_kehadiran);
    }

    public function test_pratinjau_dan_penetapan_mengoreksi_sakit_izin_dengan_riwayat_dan_bisa_dipulihkan(): void
    {
        $d = $this->data();
        $koreksi = app(KoreksiPresensiSiswaService::class);
        foreach ([0 => 'sakit', 1 => 'izin', 2 => 'sakit'] as $i => $status) {
            $koreksi->koreksi($d['admin'], $d['anggota'][$i], ['tanggal' => '2026-11-10', 'status_kehadiran' => $status,
                'catatan' => 'Catatan lama privat '.$status.' peserta '.$i]);
        }
        $koreksi->koreksi($d['admin'], $d['anggota'][0], ['tanggal' => '2026-11-03', 'status_kehadiran' => 'izin', 'catatan' => 'Izin di luar tanggal penugasan.']);
        $lama = AbsensiSiswa::whereDate('tanggal', '2026-11-10')->orderBy('anggota_kelas_id')->get()->keyBy('anggota_kelas_id');
        $riwayatAwal = RiwayatPerubahanAbsensiSiswa::pluck('id')->all();
        $pencatatAwal = $d['admin']->id;
        $d['admin'] = Pengguna::create(['nama' => 'Admin Penetapan Tugas', 'username' => 'admin.koreksi.tugas', 'kata_sandi' => 'Tes123',
            'peran' => 'administrator', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $preview = $this->preview($d)->assertOk()->assertViewHas('dampak', fn ($r) => $r['sakit_dikoreksi'] === 1 && $r['izin_dikoreksi'] === 1)
            ->assertViewHas('perubahan_status', fn ($r) => $r->count() === 2)
            ->assertSeeText('Koreksi Sakit/Izin menjadi tugas sekolah');
        file_put_contents(storage_path('framework/testing/penugasan-2026-11-10/preview-correction.html'), $preview->getContent());
        $this->assertSame('sakit', $lama[$d['anggota'][0]->id]->fresh()->status_kehadiran);
        $this->simpan($preview->viewData('token'));
        $this->simpan($preview->viewData('token'));
        foreach ([0 => 'sakit', 1 => 'izin'] as $i => $status) {
            $a = $lama[$d['anggota'][$i]->id]->fresh();
            $this->assertSame('hadir', $a->status_kehadiran);
            $this->assertSame('tugas_sekolah', $a->status_masuk);
            $this->assertSame(0, $a->menit_terlambat);
            $this->assertSame(0, $a->sanksi_manual_menit);
            $this->assertSame(0, $a->poin_keterlambatan_terhitung);
            $this->assertNull($a->jam_masuk);
            $this->assertStringNotContainsString('Catatan lama privat', $a->catatanKehadiran());
            $arsip = $a->sebelum_penugasan['koreksi_penugasan'];
            $this->assertSame($status, $arsip['presensi_sebelum']['status_kehadiran']);
            $this->assertSame('Catatan lama privat '.$status.' peserta '.$i, $arsip['presensi_sebelum']['catatan']);
            $this->assertSame($pencatatAwal, $arsip['pencatat_sebelum_id']);
            $this->assertNotEmpty($arsip['waktu_sebelum']);
            $this->assertSame($d['admin']->id, $arsip['dikoreksi_oleh']);
            $audit = RiwayatPerubahanAbsensiSiswa::where('absensi_siswa_id', $a->id)->where('sumber', 'penugasan_sekolah')->firstOrFail();
            $this->assertSame($status, $audit->status_sebelum);
            $this->assertStringContainsString('Catatan lama privat', $audit->catatan);
        }
        $this->assertSame('sakit', $lama[$d['anggota'][2]->id]->fresh()->status_kehadiran);
        $this->assertSame('izin', AbsensiSiswa::whereDate('tanggal', '2026-11-03')->firstOrFail()->status_kehadiran);
        $this->assertSame(count($riwayatAwal), RiwayatPerubahanAbsensiSiswa::whereIn('id', $riwayatAwal)->count());
        $hasil = app(ProsesScanAbsensi::class)->proses($d['anggota'][0]->siswa->nisn, now(), 'masuk');
        $this->assertTrue($hasil['berhasil']);
        $this->batalkan();
        foreach ([0 => 'sakit', 1 => 'izin'] as $i => $status) {
            $a = $lama[$d['anggota'][$i]->id]->fresh();
            $this->assertSame($status, $a->status_kehadiran);
            $this->assertSame('Catatan lama privat '.$status.' peserta '.$i, $a->catatan);
            $this->assertNull($a->penugasan_presensi_id);
            $this->assertSame(0, $a->menit_terlambat);
        }
        $this->assertSame('07:15:00', $lama[$d['anggota'][0]->id]->fresh()->jam_masuk);
    }

    public function test_koreksi_sakit_izin_memberi_notifikasi_jelas_kepada_orang_tua(): void
    {
        $d = $this->data();
        $akun = app(AkunOrangTuaService::class)->buat($d['anggota'][0]->siswa);
        $akun->update(['wajib_ganti_kata_sandi' => false]);
        app(KoreksiPresensiSiswaService::class)->koreksi($d['admin'], $d['anggota'][0], ['tanggal' => '2026-11-10',
            'status_kehadiran' => 'sakit', 'catatan' => 'Catatan medis lama privat.']);
        $token = $this->preview($d)->assertOk()->viewData('token');
        $this->simpan($token);
        $this->simpan($token);
        $items = NotifikasiPengguna::where('pengguna_id', $akun->id)->where('kunci_unik', 'like', 'presensi-penugasan:%')->get();
        $this->assertCount(1, $items);
        $n = $items->first();
        $this->assertSame('Koreksi kehadiran: tugas sekolah', $n->judul);
        $this->assertStringContainsString('Sakit (10/11/2026)', $n->pesan);
        $this->assertStringContainsString('dikoreksi menjadi Hadir — Tugas sekolah', $n->pesan);
        $this->assertStringContainsString('Lomba resmi sekolah', $n->pesan);
        $this->assertStringNotContainsString('Catatan medis lama', $n->pesan);
        $push = app(IsiPushNotifikasiService::class)->untuk($n);
        $this->assertSame('Koreksi kehadiran: tugas sekolah', $push['title']);
        $this->assertStringContainsString('dikoreksi', $push['body']);
        $this->assertStringNotContainsString('Sakit', $push['body']);
        $this->assertStringNotContainsString('medis', $push['body']);
        $this->actingAs($akun)->get(route('presensi-anak.index', ['bulan' => '2026-11']))->assertOk()
            ->assertSeeText('Lomba resmi sekolah')->assertDontSeeText('Catatan medis lama');
    }

    public function test_perubahan_sakit_izin_setelah_pratinjau_memerlukan_pratinjau_baru(): void
    {
        $d = $this->data();
        $layanan = app(KoreksiPresensiSiswaService::class);
        $layanan->koreksi($d['admin'], $d['anggota'][0], ['tanggal' => '2026-11-10', 'status_kehadiran' => 'sakit', 'catatan' => 'Catatan sakit awal.']);
        $token = $this->preview($d)->assertOk()->viewData('token');
        $layanan->koreksi($d['admin'], $d['anggota'][0], ['tanggal' => '2026-11-10', 'status_kehadiran' => 'izin', 'catatan' => 'Catatan izin baru.']);
        $this->post(route('pengecualian-presensi.store'), ['token' => $token, 'konfirmasi' => 1])->assertSessionHasErrors('token');
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 0);
        $this->simpan($this->preview($d)->assertOk()->viewData('token'));
        $this->batalkan();
        $a = AbsensiSiswa::where('anggota_kelas_id', $d['anggota'][0]->id)->firstOrFail();
        $this->assertSame('izin', $a->status_kehadiran);
        $this->assertSame('Catatan izin baru.', $a->catatan);
    }

    public function test_pratinjau_basi_ditolak_dan_kelompok_berbeda_tidak_saling_mengecualikan(): void
    {
        $d = $this->data();
        $token = $this->preview($d)->assertOk()->viewData('token');
        app(KoreksiPresensiSiswaService::class)->koreksi($d['admin'], $d['anggota'][0], $this->koreksi());
        $this->post(route('pengecualian-presensi.store'), ['token' => $token, 'konfirmasi' => 1])->assertSessionHasErrors('token');
        $this->simpan($this->preview($d, ['anggota_kelas_ids' => [$d['anggota'][0]->id]])->assertOk()->viewData('token'));
        $this->preview($d, ['anggota_kelas_ids' => [$d['anggota'][0]->id]])->assertSessionHasErrors('tanggal_mulai');
        $this->simpan($this->preview($d, ['anggota_kelas_ids' => [$d['anggota'][2]->id]])->assertOk()->viewData('token'));
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 2);
    }

    public function test_keterangan_tugas_tampil_di_web_orang_tua_dan_notifikasi_tanpa_pesan_sanksi(): void
    {
        $d = $this->data();
        $akun = app(AkunOrangTuaService::class)->buat($d['anggota'][0]->siswa);
        $akun->update(['wajib_ganti_kata_sandi' => false]);
        $akunLain = app(AkunOrangTuaService::class)->buat($d['anggota'][2]->siswa);
        $akunLain->update(['wajib_ganti_kata_sandi' => false]);
        $this->simpan($this->preview($d)->assertOk()->viewData('token'));
        $this->actingAs($akun)->get(route('presensi-anak.index', ['bulan' => '2026-11']))->assertOk()->assertSeeText('Lomba resmi sekolah');
        $this->get(route('notifikasi.index'))->assertOk()->assertSeeText('Penugasan sekolah tercatat')->assertSeeText('Lomba resmi sekolah');
        $n = NotifikasiPengguna::where('pengguna_id', $akun->id)->where('kunci_unik', 'like', 'presensi-penugasan:%')->firstOrFail();
        $this->assertStringContainsString('Hadir — Tugas sekolah', $n->pesan);
        $this->assertStringContainsString('Peserta Lomba 1 (VII.A)', $n->pesan);
        $this->assertStringNotContainsString('15 poin', $n->pesan);
        $push = app(IsiPushNotifikasiService::class)->untuk($n);
        $this->assertSame('Penugasan sekolah anak Anda', $push['title']);
        $this->assertStringContainsString('10/11/2026', $push['body']);
        $this->assertDatabaseMissing('notifikasi_pengguna', ['pengguna_id' => $akunLain->id, 'kunci_unik' => $n->kunci_unik]);
        $this->app['auth']->forgetGuards();
        $this->withToken($akun->createToken('Tes notifikasi tugas', ['mobile'])->plainTextToken)
            ->getJson(route('api.v1.notifikasi.tujuan', $n))->assertOk()
            ->assertJsonPath('data.tautan_mobile', '/kehadiran-anak-saya?siswa_id='.$d['anggota'][0]->siswa_id.'&bulan=2026-11')
            ->assertJsonPath('data.pesan', fn ($pesan) => str_contains($pesan, 'Lomba resmi sekolah'));
        $this->app['auth']->forgetGuards();
        $this->withToken($akunLain->createToken('Tes notifikasi lain', ['mobile'])->plainTextToken)
            ->getJson(route('api.v1.notifikasi.tujuan', $n))->assertForbidden();
        app(ProsesScanAbsensi::class)->proses($d['anggota'][0]->siswa->nisn, now(), 'masuk');
        $scan = NotifikasiPengguna::where('pengguna_id', $akun->id)->where('kunci_unik', 'like', 'presensi-masuk-%')->firstOrFail();
        $push = app(IsiPushNotifikasiService::class)->untuk($scan);
        $this->assertStringContainsString('penugasan sekolah', $push['body']);
        $this->assertStringNotContainsString('tepat waktu', $push['body']);
        Http::assertNothingSent();
    }

    public function test_pengelolaan_native_belum_menawarkan_penugasan_dan_tidak_menganggapnya_seluruh_sekolah(): void
    {
        $d = $this->data();
        $this->simpan($this->preview($d)->assertOk()->viewData('token'));
        $token = $d['admin']->createToken('Tes penugasan', ['mobile'])->plainTextToken;
        $this->withToken($token)->getJson(route('api.v1.pengecualian-presensi.index'))->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson(route('api.v1.pengecualian-presensi.referensi'))->assertOk()->assertJsonMissingPath('data.jenis.tugas_sekolah');
    }

    public function test_tugas_tidak_mengecualikan_evaluasi_ibadah_siswa_lain(): void
    {
        $d = $this->data();
        $this->simpan($this->preview($d)->assertOk()->viewData('token'));
        app(KoreksiPresensiSiswaService::class)->koreksi($d['admin'], $d['anggota'][2], $this->koreksi());
        $kegiatan = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();
        JadwalKegiatanIbadah::create(['kegiatan_ibadah_id' => $kegiatan->id, 'tahun_pelajaran_id' => $d['tahun']->id,
            'hari' => 'selasa', 'urutan_hari' => 2, 'jam_scan_mulai' => '11:30', 'jam_pelaksanaan' => '12:00', 'jam_scan_selesai' => '13:00', 'aktif' => true]);
        Carbon::setTestNow('2026-11-10 13:15:00');
        $hasil = app(EvaluasiIbadahMingguan::class)->hitung($d['tahun'], $kegiatan,
            collect($d['kelas'])->keyBy('id'), CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-10'));
        $this->assertSame([$d['anggota'][2]->siswa_id], $hasil['baris']->pluck('siswa_id')->all());
    }

    private function preview(array $d, array $ganti = [])
    {
        return $this->actingAs($d['admin'])->post(route('pengecualian-presensi.pratinjau'), $ganti + [
            'tahun_pelajaran_id' => $d['tahun']->id, 'cakupan' => 'siswa', 'jenis' => 'tugas_sekolah',
            'tanggal_mulai' => '2026-11-10', 'tanggal_selesai' => '2026-11-10', 'alasan' => 'Lomba resmi sekolah di luar sekolah.',
            'anggota_kelas_ids' => [$d['anggota'][0]->id, $d['anggota'][1]->id],
        ]);
    }

    private function simpan(string $token): void
    {
        $this->post(route('pengecualian-presensi.store'), ['token' => $token, 'konfirmasi' => 1])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function batalkan(): void
    {
        $this->post(route('pengecualian-presensi.batalkan', PengecualianPresensiSiswa::firstOrFail()), ['konfirmasi' => 1, 'alasan_pembatalan' => 'Penugasan dibatalkan oleh sekolah.'])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function koreksi(): array
    {
        return ['tanggal' => '2026-11-10', 'status_kehadiran' => 'hadir', 'jam_masuk' => '06:55:00', 'catatan' => 'Dikonfirmasi petugas sekolah.', 'alasan_hadir_manual' => 'kartu_hilang'];
    }

    private function data(): array
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = [Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A', 'tingkat' => 7, 'aktif' => true]), Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.A', 'tingkat' => 8, 'aktif' => true])];
        $anggota = [];
        foreach ([0, 1, 0] as $i => $k) {
            $siswa = Siswa::create(['nama_lengkap' => 'Peserta Lomba '.($i + 1), 'nisn' => '881100000'.$i, 'aktif' => true]);
            $anggota[] = AnggotaKelas::create(['siswa_id' => $siswa->id, 'kelas_id' => $kelas[$k]->id, 'tahun_pelajaran_id' => $tahun->id, 'status_keanggotaan' => 'aktif', 'tanggal_masuk' => '2026-07-01']);
        }
        PengaturanAbsensi::create(['hari' => 'selasa', 'urutan_hari' => 2, 'aktif' => true, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '10:00', 'jam_scan_pulang_mulai' => '14:00', 'jam_pulang' => '14:10', 'jam_scan_pulang_selesai' => '16:00']);
        PengaturanPoinKeterlambatan::create(['tahun_pelajaran_id' => $tahun->id, 'aktif' => false, 'otomatis_langsung' => false, 'poin_terlambat' => 15, 'poin_alfa' => 25]);

        return compact('admin', 'tahun', 'kelas', 'anggota');
    }
}
