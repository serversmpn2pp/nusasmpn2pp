<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\KehadiranRaporSts;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\LampiranPerilakuSts;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiRaporSts;
use App\Models\RaporStsKelas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Mobile\TujuanNotifikasiMobileService;
use App\Services\Nilai\LampiranPerilakuStsService;
use App\Services\Nilai\PublikasiRaporStsService;
use App\Services\Nilai\RaporStsService;
use App\Services\Nilai\StsManualService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublikasiRaporStsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(12, 0));
        config(['services.firebase.push_enabled' => false]);
    }

    public function test_api_memerlukan_token_mobile_dan_cakupan_wali_kelas(): void
    {
        $d = $this->data();
        $this->getJson($this->url($d))->assertUnauthorized();
        $this->getJson('/api/v1/rapor-sts-saya')->assertUnauthorized();
        $this->withToken($d['admin']->createToken('Web', ['web'])->plainTextToken);
        $this->getJson($this->url($d))->assertForbidden();
        $this->login($d['siswa']);
        $this->getJson($this->url($d))->assertForbidden();
        $this->postJson($this->url($d), [])->assertForbidden();
        $this->login($d['wali']);
        $this->getJson($this->url($d))->assertOk();
        $lain = Kelas::create(['nama' => 'VIII.B', 'tingkat' => 8, 'tahun_pelajaran_id' => $d['tahun']->id, 'aktif' => true]);
        $this->getJson(route('api.v1.rapor-sts.publikasi.index', [$d['kegiatan'], $lain]))->assertForbidden();
        $this->login($d['admin']);
        $this->getJson('/api/v1/rapor-sts-saya')->assertForbidden();
        $d['wali']->update(['wajib_ganti_kata_sandi' => true]);
        $this->login($d['wali']);
        $this->getJson($this->url($d))->assertStatus(428);
    }

    public function test_draf_tidak_terlihat_dan_publikasi_orang_tua_saja_tidak_membuka_siswa(): void
    {
        $d = $this->data();
        foreach ([$d['siswa'], $d['orang_tua']] as $u) {
            $this->login($u);
            $this->getJson('/api/v1/rapor-sts-saya')->assertOk()->assertJsonCount(0, 'data.items');
        }
        $this->login($d['admin']);
        $input = $this->payload($d);
        $this->postJson($this->url($d), $input)->assertOk();
        $p = PublikasiRaporSts::firstOrFail();
        $this->assertSame('orang_tua', $p->penerima);
        $this->assertNull($p->salinan['perilaku']);
        $this->assertSame(1, NotifikasiPengguna::where('kunci_unik', 'publikasi-rapor-sts:'.$p->id)->count());
        $this->assertSame('/rapor-sts-saya/'.$p->id, app(TujuanNotifikasiMobileService::class)->untuk(NotifikasiPengguna::latest('id')->first()));
        $this->login($d['orang_tua']);
        $r = $this->getJson('/api/v1/rapor-sts-saya')->assertOk()->assertJsonCount(1, 'data.items');
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id)->assertOk()->assertJsonPath('data.rapor.nilai.0.nilai', 88.5)
            ->assertDontSee('CATATAN-NILAI-PRIVAT')->assertDontSee('CATATAN-KOREKSI-PRIVAT')->assertJsonMissingPath('data.rapor.sidik_sumber');
        $this->login($d['siswa']);
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id)->assertNotFound();
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id.'/cetak')->assertNotFound();
        $this->getJson('/api/v1/rapor-sts-saya')->assertJsonCount(0, 'data.items');
    }

    public function test_penerima_siswa_saja_ditolak_dan_konfirmasi_wajib(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $input = $this->payload($d);
        $this->postJson($this->url($d), [...$input, 'penerima' => 'siswa'])->assertUnprocessable()->assertJsonValidationErrors('penerima');
        $this->postJson($this->url($d), [...$input, 'konfirmasi' => false])->assertUnprocessable();
        $this->assertDatabaseCount('publikasi_rapor_sts', 0);
    }

    public function test_publikasi_bersama_membuka_hanya_siswa_sendiri_dan_orang_tua_terhubung(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $this->postJson($this->url($d), [...$this->payload($d), 'penerima' => 'orang_tua_dan_siswa'])->assertOk();
        $p = PublikasiRaporSts::firstOrFail();
        $this->assertSame(2, NotifikasiPengguna::where('kunci_unik', 'publikasi-rapor-sts:'.$p->id)->count());
        $this->login($d['siswa']);
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id)->assertOk()->assertJsonPath('data.rapor.siswa.nama', 'Alya Publikasi STS');
        $other = $this->studentUser($d['anggota'][1]->siswa_id, 'Bima');
        $this->login($other);
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id)->assertNotFound();
        $parent = $this->parentUser([$d['anggota'][1]->siswa_id], 'Lain');
        $this->login($parent);
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id)->assertNotFound();
        $this->getJson('/api/v1/rapor-sts-saya')->assertJsonCount(0, 'data.items');
        $this->login($d['orang_tua']);
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id.'/cetak')->assertOk()->assertSee('88,50')->assertSee('Versi 1')->assertDontSee('DRAF PRATINJAU');
    }

    public function test_salinan_tetap_sampai_dipublikasikan_ulang_dan_versi_lama_tidak_bisa_diakses(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $input = $this->payload($d);
        $this->postJson($this->url($d), $input)->assertOk();
        $this->postJson($this->url($d), $input)->assertUnprocessable();
        $input = $this->payload($d);
        $this->postJson($this->url($d), $input)->assertOk();
        $this->assertDatabaseCount('publikasi_rapor_sts', 1);
        $this->assertDatabaseCount('notifikasi_pengguna', 1);
        $p = PublikasiRaporSts::firstOrFail();
        NilaiSiswa::where('komponen_nilai_id', $d['komponen']->id)->where('siswa_id', $d['anggota'][0]->siswa_id)->update(['nilai' => 92.75]);
        $manual = app(StsManualService::class);
        $manual->tetapkan($d['admin'], $d['komponen']->fresh(), $manual->konteks($d['komponen']->fresh())['sidik'], true);
        $this->getJson($this->url($d))->assertJsonPath('data.siswa.0.berubah', true);
        $this->login($d['orang_tua']);
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id)->assertJsonPath('data.rapor.nilai.0.nilai', 88.5);
        $this->login($d['admin']);
        $this->postJson($this->url($d), $this->payload($d))->assertOk();
        $new = PublikasiRaporSts::latest('id')->first();
        $this->assertSame(2, $new->versi);
        $this->assertFalse($p->fresh()->aktif);
        $this->assertEquals(88.5, $p->fresh()->salinan['nilai'][0]['nilai']);
        $this->login($d['orang_tua']);
        $this->getJson('/api/v1/rapor-sts-saya/'.$new->id)->assertJsonPath('data.rapor.nilai.0.nilai', 92.75);
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id)->assertNotFound();
    }

    public function test_nilai_kosong_tetap_dapat_diterbitkan_secara_kolektif(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $this->postJson($this->url($d), $this->payload($d, [0, 1, 2]))->assertOk();
        $this->assertDatabaseCount('publikasi_rapor_sts', 3);
        $this->login($d['orang_tua']);
        $this->getJson('/api/v1/rapor-sts-saya')->assertJsonCount(3, 'data.items');
        $p = PublikasiRaporSts::where('anggota_kelas_id', $d['anggota'][2]->id)->firstOrFail();
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id)->assertJsonPath('data.rapor.nilai.0.nilai', null)
            ->assertJsonPath('data.rapor.nilai.0.keterangan', 'Belum tersedia')->assertJsonPath('data.rapor.rata', null);
        $zero = PublikasiRaporSts::where('anggota_kelas_id', $d['anggota'][1]->id)->firstOrFail();
        $this->getJson('/api/v1/rapor-sts-saya/'.$zero->id)->assertJsonPath('data.rapor.nilai.0.nilai', 0);
    }

    public function test_kesiapan_sidik_dan_lampiran_diperiksa_atomik(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $input = $this->payload($d, [0, 1]);
        $input['siswa'][1]['sidik_sumber'] = str_repeat('f', 64);
        $this->postJson($this->url($d), $input)->assertUnprocessable();
        $this->assertDatabaseCount('publikasi_rapor_sts', 0);
        KehadiranRaporSts::where('anggota_kelas_id', $d['anggota'][1]->id)->update(['diperiksa_pada' => null]);
        $this->postJson($this->url($d), $this->payload($d, [0, 1]))->assertUnprocessable();
        $this->postJson($this->url($d), $this->payload($d, [0], true))->assertUnprocessable();
        $this->assertDatabaseCount('publikasi_rapor_sts', 0);
        $lampiran = app(LampiranPerilakuStsService::class)->konteks($d['kegiatan'], $d['kelas']);
        $b = $lampiran['baris'][0];
        LampiranPerilakuSts::create(['rapor_sts_kelas_id' => $d['pengaturan']->id, 'anggota_kelas_id' => $d['anggota'][0]->id,
            'guru_bk_id' => $d['wali']->pegawai_id, 'wakil_kesiswaan_id' => $d['wali']->pegawai_id,
            'baris' => [], 'ringkasan' => $b['ringkasan'], 'sidik_sumber' => $b['sidik_sumber'], 'versi' => 1,
            'catatan' => 'Ringkasan yang sudah diperiksa BK.', 'diperiksa_pada' => now(), 'diperiksa_oleh_pengguna_id' => $d['admin']->id]);
        $this->postJson($this->url($d), $this->payload($d, [0], true))->assertOk();
        $p = PublikasiRaporSts::firstOrFail();
        $this->login($d['orang_tua']);
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id)->assertJsonPath('data.rapor.perilaku.catatan', 'Ringkasan yang sudah diperiksa BK.');
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id.'/cetak')->assertOk()->assertSee('LAPORAN PERILAKU DAN PEMBINAAN SISWA')->assertDontSee('CATATAN-KOREKSI-PRIVAT');
    }

    public function test_pembatalan_beralasan_menutup_akses_dan_menjaga_riwayat(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $this->postJson($this->url($d), $this->payload($d))->assertOk();
        $input = $this->payload($d);
        $p = PublikasiRaporSts::firstOrFail();
        $cancel = ['konfirmasi' => true, 'alasan' => 'Dikoreksi sebelum pembagian ulang.', 'siswa' => [['anggota_id' => $p->anggota_kelas_id, 'versi_publikasi' => 1]]];
        $this->deleteJson($this->url($d), [...$cancel, 'alasan' => ''])->assertUnprocessable();
        $this->deleteJson($this->url($d), $cancel)->assertOk();
        $this->deleteJson($this->url($d), [...$cancel, 'alasan' => 'Tidak boleh menimpa alasan pertama.'])->assertOk();
        $this->assertSame($cancel['alasan'], $p->fresh()->alasan_pembatalan);
        $this->postJson($this->url($d), $input)->assertUnprocessable();
        $this->login($d['orang_tua']);
        $this->getJson('/api/v1/rapor-sts-saya')->assertJsonCount(0, 'data.items');
        $this->getJson('/api/v1/rapor-sts-saya/'.$p->id)->assertNotFound();
        $this->login($d['admin']);
        $this->postJson($this->url($d), $this->payload($d))->assertOk();
        $this->assertSame(2, PublikasiRaporSts::latest('id')->first()->versi);
    }

    public function test_web_memakai_salinan_dan_pilihan_menu_sesuai_identitas(): void
    {
        $d = $this->data();
        $this->actingAs($d['admin'])->get(route('rapor-sts.publikasi.index', [$d['kegiatan'], $d['kelas']]))->assertOk()->assertSee('Orang tua saja')->assertDontSee('value="siswa"', false);
        $this->post(route('rapor-sts.publikasi.store', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $p = PublikasiRaporSts::firstOrFail();
        $this->actingAs($d['orang_tua'])->get('/rapor-sts-saya')->assertOk()->assertSee('Rapor STS Anak Saya');
        $this->get('/rapor-sts-saya/'.$p->id)->assertOk()->assertSee('Kembali ke daftar rapor')->assertSee('88,50');
        $this->actingAs($d['siswa'])->get('/rapor-sts-saya/'.$p->id)->assertNotFound();
    }

    public function test_fixture_visual_publikasi_dan_cetak_salinan(): void
    {
        $d = $this->data();
        $this->actingAs($d['admin']);
        $url = route('rapor-sts.publikasi.index', [$d['kegiatan'], $d['kelas']]);
        $manager = $this->get($url)->assertOk();
        $this->post(route('rapor-sts.publikasi.store', [$d['kegiatan'], $d['kelas']]), $this->payload($d, [0, 1, 2]))->assertSessionHasNoErrors();
        $p = PublikasiRaporSts::first();
        $this->actingAs($d['orang_tua']);
        $index = $this->get('/rapor-sts-saya')->assertOk();
        $print = $this->get('/rapor-sts-saya/'.$p->id)->assertOk();
        $this->assertSame(1, substr_count($print->getContent(), '<article class="sheet'));
        if (getenv('NUSA_CAPTURE_PUBLICATION_STS') === '1') {
            $dir = storage_path('framework/testing/publikasi-rapor-sts');
            File::ensureDirectoryExists($dir);
            foreach (['manager' => $manager, 'index' => $index, 'print' => $print] as $name => $response) {
                File::put($dir.'/'.$name.'.html', $response->getContent());
            }
        }
    }

    private function url(array $d): string
    {
        return route('api.v1.rapor-sts.publikasi.index', [$d['kegiatan'], $d['kelas']]);
    }

    private function payload(array $d, array $indices = [0], bool $perilaku = false): array
    {
        $k = app(PublikasiRaporStsService::class)->konteks($d['admin'], $d['kegiatan'], $d['kelas']);

        return ['penerima' => 'orang_tua', 'sertakan_perilaku' => $perilaku, 'konfirmasi' => true,
            'siswa' => collect($indices)->map(fn ($i) => ['anggota_id' => $k['siswa'][$i]['anggota_id'],
                'versi_publikasi' => $k['siswa'][$i]['versi_publikasi'],
                'sidik_sumber' => $k['siswa'][$i][$perilaku ? 'sidik_gabungan' : 'sidik_rapor']])->all()];
    }

    private function login(Pengguna $u): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($u->createToken('Mobile STS', ['mobile'])->plainTextToken);
    }

    private function studentUser(int $id, string $name): Pengguna
    {
        return Pengguna::create(['nama' => $name, 'username' => 'student-sts-'.$id, 'siswa_id' => $id,
            'peran' => 'siswa', 'aktif' => true, 'kata_sandi' => 'test-pass', 'wajib_ganti_kata_sandi' => false]);
    }

    private function parentUser(array $ids, string $name): Pengguna
    {
        $u = Pengguna::create(['nama' => $name, 'username' => 'parent-sts-'.$name, 'peran' => 'orang_tua',
            'aktif' => true, 'kata_sandi' => 'test-pass', 'wajib_ganti_kata_sandi' => false]);
        $p = OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => $name, 'siswa_acuan_username_id' => $ids[0]]);
        $p->siswa()->sync($ids);

        return $u;
    }

    private function data(): array
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = Pegawai::create(['nama_lengkap' => 'Wali Publikasi', 'jenis_pegawai' => 'Guru', 'aktif' => true]);
        $wali = Pengguna::create(['nama' => $pegawai->nama_lengkap, 'username' => 'wali-publikasi', 'pegawai_id' => $pegawai->id,
            'peran' => 'pegawai', 'aktif' => true, 'kata_sandi' => 'test-pass', 'wajib_ganti_kata_sandi' => false]);
        $wali->daftarPeran()->sync([Peran::where('kode', 'wali_kelas')->value('id')]);
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['nama' => 'VII.A', 'tingkat' => 7, 'tahun_pelajaran_id' => $tahun->id, 'wali_kelas_id' => $pegawai->id, 'aktif' => true]);
        $mapel = MataPelajaran::create(['nama' => 'PJOK Publikasi', 'urutan' => 1, 'aktif' => true]);
        $guru = GuruMataPelajaran::create(['kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $pegawai->id, 'aktif' => true]);
        $komponen = KomponenNilai::create(['guru_mata_pelajaran_id' => $guru->id, 'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'nama' => 'Praktik STS', 'aktif' => true]);
        $kegiatan = KegiatanUjianCbt::create(['kode' => 'STS-PUB', 'nama' => 'STS Ganjil', 'tahun_pelajaran_id' => $tahun->id,
            'jenis_ujian_cbt_id' => JenisUjianCbt::where('kode', 'STS')->value('id'), 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-09-15', 'tanggal_selesai' => '2026-09-20', 'status' => 'selesai']);
        $anggota = collect();
        foreach ([88.5, 0, null] as $i => $score) {
            $s = Siswa::create(['nama_lengkap' => ['Alya', 'Bima', 'Citra'][$i].' Publikasi STS', 'aktif' => true]);
            $anggota->push(AnggotaKelas::create(['kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $tahun->id,
                'siswa_id' => $s->id, 'nomor_absen' => $i + 1, 'status_keanggotaan' => 'aktif']));
            if ($score !== null) {
                NilaiSiswa::create(['komponen_nilai_id' => $komponen->id, 'siswa_id' => $s->id, 'nilai' => $score, 'catatan' => 'CATATAN-NILAI-PRIVAT']);
            }
        }
        $manual = app(StsManualService::class);
        $manual->tetapkan($admin, $komponen, $manual->konteks($komponen)['sidik'], true);
        $pengaturan = RaporStsKelas::create(['kegiatan_ujian_cbt_id' => $kegiatan->id, 'kelas_id' => $kelas->id,
            'tanggal_awal_presensi' => '2026-08-01', 'tanggal_akhir_presensi' => '2026-09-20', 'tanggal_rapor' => '2026-10-02', 'versi' => 1]);
        $laporan = app(RaporStsService::class)->bangun($kegiatan, $kelas);
        foreach ($laporan['baris'] as $b) {
            KehadiranRaporSts::create(['rapor_sts_kelas_id' => $pengaturan->id, 'anggota_kelas_id' => $b['anggota']->id,
                ...$b['kehadiran'], 'rekap_sumber' => $b['sumber'], 'diperiksa_pada' => now(), 'diperiksa_oleh_pengguna_id' => $wali->id,
                'catatan_koreksi' => 'CATATAN-KOREKSI-PRIVAT']);
        }
        $siswa = $this->studentUser($anggota[0]->siswa_id, 'Alya');
        $orang_tua = $this->parentUser($anggota->pluck('siswa_id')->all(), 'Alya');

        return compact('admin', 'wali', 'tahun', 'kelas', 'kegiatan', 'komponen', 'anggota', 'pengaturan', 'siswa', 'orang_tua');
    }
}
