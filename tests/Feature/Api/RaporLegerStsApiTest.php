<?php

namespace Tests\Feature\Api;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PesertaUjianCbt;
use App\Models\RaporStsKelas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use App\Services\Nilai\RaporStsService;
use App\Services\Nilai\StsManualService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaporLegerStsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(12, 0));
    }

    public function test_token_mobile_dan_izin_diwajibkan(): void
    {
        foreach (['rapor-sts.index', 'leger-sts.index', 'leger-sts.penghargaan'] as $route) {
            $this->getJson(route('api.v1.'.$route))->assertUnauthorized();
        }
        $d = $this->data();
        foreach (['pengaturan', 'kehadiran', 'pengecualian', 'mapel'] as $aksi) {
            $this->putJson($this->aksi($d, $aksi), [])->assertUnauthorized();
        }
        $this->getJson(route('api.v1.rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]))->assertUnauthorized();
        $this->getJson(route('api.v1.leger-sts.cetak'))->assertUnauthorized();
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->withToken($admin->createToken('Bukan mobile', ['web'])->plainTextToken);
        $this->getJson(route('api.v1.rapor-sts.index'))->assertForbidden();
        $this->getJson(route('api.v1.leger-sts.index'))->assertForbidden();
        $pegawai = $this->akun('guru_mapel', 'Tanpa akses STS');
        $this->login($pegawai);
        $this->getJson(route('api.v1.rapor-sts.index'))->assertForbidden();
        $this->getJson(route('api.v1.leger-sts.index'))->assertForbidden();
    }

    public function test_menu_rapor_dan_leger_native_tersedia_sesuai_hak_akses(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $this->getJson(route('api.v1.menu'))->assertOk()
            ->assertJsonFragment(['kode' => 'publikasi-rapor-sts', 'rute' => '/publikasi-rapor-sts', 'api' => '/api/v1/publikasi-rapor-sts', 'api_tersedia' => true, 'status' => 'tersedia'])
            ->assertJsonFragment(['kode' => 'rapor-sts', 'rute' => '/rapor-sts', 'api' => '/api/v1/rapor-sts', 'api_tersedia' => true, 'status' => 'tersedia'])
            ->assertJsonFragment(['kode' => 'leger-sts', 'rute' => '/leger-sts', 'api' => '/api/v1/leger-sts', 'api_tersedia' => true, 'status' => 'tersedia'])
            ->assertJsonFragment(['kode' => 'lampiran-perilaku-sts', 'api_tersedia' => true, 'status' => 'tersedia']);
        $this->login($d['wali']);
        $this->getJson(route('api.v1.menu'))->assertOk()->assertSee('rapor-sts')->assertSee('leger-sts')->assertDontSee('lampiran-perilaku-sts');
        $this->login($this->akun('bk', 'BK API STS'));
        $this->getJson(route('api.v1.menu'))->assertOk()->assertSee('lampiran-perilaku-sts')->assertDontSee('rapor-sts')->assertDontSee('leger-sts');
    }

    public function test_pusat_publikasi_mobile_memakai_pilihan_dan_kesiapan_tanpa_menerbitkan(): void
    {
        $d = $this->data();
        $url = route('api.v1.publikasi-rapor-sts.index');
        $this->getJson($url)->assertUnauthorized();
        $this->login($d['wali']);
        $response = $this->getJson($url)->assertOk()
            ->assertJsonPath('data.filter.kegiatan_id', $d['kegiatan']->id)
            ->assertJsonPath('data.filter.kelas_id', $d['kelas']->id)
            ->assertJsonCount(1, 'data.opsi.kelas')
            ->assertJsonCount(3, 'data.konteks.siswa')
            ->assertJsonPath('data.konteks.siswa.0.aktif', false)
            ->assertJsonPath('data.konteks.siswa.0.siap', false)
            ->assertJsonPath('data.konteks.siswa.0.versi_publikasi', 0);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $response->json('data.konteks.siswa.0.sidik_rapor'));
        $response->assertDontSee('NILAI-CATATAN-PRIVAT');
        $this->assertDatabaseCount('publikasi_rapor_sts', 0);
        $this->assertDatabaseCount('rapor_sts_kelas', 0);
        $lain = Kelas::create(['nama' => 'VII.B', 'tingkat' => 7, 'tahun_pelajaran_id' => $d['tahun']->id, 'aktif' => true]);
        $this->getJson($url.'?kelas_id='.$lain->id)->assertNotFound();
        $this->getJson($url.'?kegiatan_id=0')->assertUnprocessable();
        $this->login($this->akun('guru_mapel', 'Guru bukan pengelola'));
        $this->getJson($url)->assertForbidden();
        $this->getJson(route('api.v1.menu'))->assertJsonMissing(['kode' => 'publikasi-rapor-sts']);
    }

    public function test_pusat_publikasi_kosong_dan_identitas_penerima_tidak_membuka_pengelolaan(): void
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->login($admin);
        $this->getJson(route('api.v1.publikasi-rapor-sts.index'))->assertOk()
            ->assertJsonPath('data.konteks', null)->assertJsonCount(0, 'data.opsi.kegiatan');
        $d = $this->data();
        foreach (['siswa', 'orang_tua'] as $peran) {
            $user = Pengguna::create(['nama' => 'Penerima '.$peran, 'username' => 'publikasi-'.$peran,
                'peran' => $peran, 'aktif' => true, 'kata_sandi' => 'test-pass', 'wajib_ganti_kata_sandi' => false,
                'siswa_id' => $peran === 'siswa' ? $d['anggota'][0]->siswa_id : null]);
            if ($peran === 'orang_tua') {
                OrangTuaWali::create(['pengguna_id' => $user->id, 'nama_lengkap' => $user->nama,
                    'siswa_acuan_username_id' => $d['anggota'][0]->siswa_id]);
            }
            $user->daftarPeran()->sync([Peran::where('kode', 'wali_kelas')->value('id')]);
            $this->login($user);
            $this->getJson(route('api.v1.publikasi-rapor-sts.index'))->assertForbidden();
            $this->getJson(route('api.v1.menu'))->assertJsonMissing(['kode' => 'publikasi-rapor-sts']);
        }
    }

    public function test_identitas_siswa_dan_orang_tua_tetap_ditolak_meski_role_wali_tersisip(): void
    {
        $d = $this->data();
        foreach (['siswa', 'orang_tua'] as $peran) {
            $user = Pengguna::create(['nama' => 'Identitas '.$peran, 'username' => 'sts-'.$peran, 'peran' => $peran,
                'pegawai_id' => $peran === 'siswa' ? $d['wali']->pegawai_id : null,
                'siswa_id' => $peran === 'siswa' ? $d['anggota'][0]->siswa_id : null,
                'aktif' => true, 'kata_sandi' => 'test-pass', 'wajib_ganti_kata_sandi' => false]);
            if ($peran === 'orang_tua') {
                OrangTuaWali::create(['pengguna_id' => $user->id, 'nama_lengkap' => $user->nama,
                    'siswa_acuan_username_id' => $d['anggota'][0]->siswa_id]);
            }
            $user->daftarPeran()->sync([Peran::where('kode', 'wali_kelas')->value('id')]);
            $this->login($user);
            $this->getJson($this->url($d, 'rapor-sts.index'))->assertForbidden();
            $this->getJson($this->url($d, 'leger-sts.index'))->assertForbidden();
            $this->putJson($this->aksi($d, 'pengaturan'), $this->periode())->assertForbidden();
            $this->getJson(route('api.v1.menu'))->assertOk()->assertJsonMissing(['kode' => 'rapor-sts'])->assertJsonMissing(['kode' => 'leger-sts']);
        }
    }

    public function test_konteks_kosong_dan_filter_tidak_valid_aman(): void
    {
        $this->login(Pengguna::where('username', 'administrator')->firstOrFail());
        foreach (['rapor-sts.index', 'leger-sts.index'] as $route) {
            $this->getJson(route('api.v1.'.$route))->assertOk()->assertJsonPath('data.konteks', null)->assertJsonCount(0, 'data.opsi.kegiatan');
            $this->getJson(route('api.v1.'.$route, ['kegiatan_id' => 999999]))->assertNotFound();
            $this->getJson(route('api.v1.'.$route, ['kelas_id' => 0]))->assertUnprocessable();
        }
        $this->getJson(route('api.v1.leger-sts.index', ['mode' => 'semua']))->assertUnprocessable();
        $this->assertDatabaseCount('rapor_sts_kelas', 0);
    }

    public function test_rapor_memakai_nilai_final_manual_desimal_nol_dan_kosong(): void
    {
        $d = $this->data();
        $this->login($d['wali']);
        $r = $this->getJson($this->url($d, 'rapor-sts.index'))->assertOk()
            ->assertJsonCount(3, 'data.konteks.siswa')->assertJsonPath('data.konteks.siswa.0.nilai.0.nilai', 88.5)
            ->assertJsonPath('data.konteks.siswa.1.nilai.0.nilai', 0)->assertJsonPath('data.konteks.siswa.2.nilai.0.nilai', null)
            ->assertJsonPath('data.konteks.siswa.2.nilai.0.keterangan', 'Belum tersedia')
            ->assertJsonPath('data.konteks.pengaturan_mapel', null)->assertJsonPath('data.konteks.siswa.0.keterlambatan.jumlah', 1)
            ->assertJsonPath('data.konteks.siswa.0.keterlambatan.total_menit', 7);
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $web = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertEquals($web['baris'][0]['rata'], $r->json('data.konteks.siswa.0.rata'));
        $r->assertJsonMissingPath('data.konteks.siswa.0.koreksi')->assertDontSee('1234567890')->assertDontSee('NILAI-CATATAN-PRIVAT');
        $this->assertDatabaseCount('rapor_sts_kelas', 0);
        $this->getJson($this->url($d, 'rapor-sts.index', ['anggota_id' => $d['anggota'][1]->id]))->assertOk()->assertJsonCount(1, 'data.konteks.siswa');
        $this->getJson($this->url($d, 'rapor-sts.index', ['anggota_id' => 999999]))->assertNotFound();
    }

    public function test_cakupan_wali_kelas_membatasi_kelas_tingkat_siswa_dan_perubahan(): void
    {
        $d = $this->data();
        $lain = $this->kelasTambahan($d, 'VII.B', 7, 96);
        $kelasDelapan = $this->kelasTambahan($d, 'VIII.A', 8, 99);
        $this->login($d['wali']);
        $this->getJson($this->url($d, 'rapor-sts.index'))->assertOk()->assertJsonCount(1, 'data.opsi.kelas')->assertDontSee('VII.B');
        foreach (['rapor-sts.index', 'leger-sts.index', 'leger-sts.penghargaan'] as $route) {
            $this->getJson($this->url($d, $route, ['kelas_id' => $lain->id]))->assertNotFound();
        }
        $this->getJson($this->url($d, 'leger-sts.index', ['mode' => 'tingkat', 'tingkat' => 7]))->assertOk()
            ->assertJsonPath('data.konteks.cakupan_tingkat_lengkap', false)->assertJsonPath('data.konteks.ringkasan.jumlah_siswa', 3);
        $this->getJson($this->url($d, 'leger-sts.index', ['mode' => 'tingkat', 'tingkat' => 8]))->assertNotFound();
        $this->putJson(route('api.v1.rapor-sts.pengaturan', [$d['kegiatan'], $kelasDelapan]), $this->periode())->assertForbidden();
        $this->login($this->akun('wakil_pimpinan_kurikulum', 'Kurikulum API STS'));
        $this->getJson($this->url($d, 'rapor-sts.index'))->assertOk()->assertJsonCount(3, 'data.opsi.kelas');
    }

    public function test_leger_menghitung_ranking_sementara_dengan_pembagi_mapel_tetap(): void
    {
        $d = $this->data();
        $mapelKosong = MataPelajaran::create(['nama' => 'Tanpa Nilai STS', 'urutan' => 2, 'aktif' => true]);
        GuruMataPelajaran::create(['kelas_id' => $d['kelas']->id, 'tahun_pelajaran_id' => $d['tahun']->id,
            'mata_pelajaran_id' => $mapelKosong->id, 'pegawai_id' => $d['wali']->pegawai_id, 'aktif' => true]);
        $this->login($d['admin']);
        $this->getJson($this->url($d, 'leger-sts.index'))->assertOk()
            ->assertJsonPath('data.konteks.jumlah_mapel_pembagi', 2)->assertJsonPath('data.konteks.ranking_sementara', true)
            ->assertJsonPath('data.konteks.siswa.0.rata_leger', 44.25)->assertJsonPath('data.konteks.siswa.0.ranking', 1)
            ->assertJsonPath('data.konteks.siswa.1.rata_leger', 0)->assertJsonPath('data.konteks.siswa.1.ranking', 2)
            ->assertJsonPath('data.konteks.siswa.2.ranking', null)->assertJsonPath('data.konteks.ringkasan.masuk_ranking', 2);
        $this->getJson($this->url($d, 'leger-sts.penghargaan'))->assertOk()->assertJsonCount(0, 'data.konteks.kandidat');
        $this->getJson($this->url($d, 'leger-sts.penghargaan', ['kategori' => 'mapel', 'mapel_id' => $d['mapel']->id]))
            ->assertOk()->assertJsonCount(2, 'data.konteks.kandidat')->assertJsonPath('data.konteks.hanya_nilai_final', true);
    }

    public function test_draf_tidak_bocor_ke_rapor_atau_kandidat_penghargaan(): void
    {
        $d = $this->data(false);
        $this->login($d['admin']);
        $this->getJson($this->url($d, 'rapor-sts.index'))->assertOk()->assertJsonPath('data.konteks.siswa.0.nilai.0.nilai', null);
        $this->getJson($this->url($d, 'leger-sts.index'))->assertOk()
            ->assertJsonPath('data.konteks.siswa.0.nilai.0.nilai', 88.5)->assertJsonPath('data.konteks.siswa.0.nilai.0.draf', true)
            ->assertJsonPath('data.konteks.siswa.0.status_ranking', 'Ranking sementara')->assertJsonPath('data.konteks.statistik_mapel.0.jumlah_draf', 2);
        foreach (['keseluruhan', 'mapel'] as $kategori) {
            $this->getJson($this->url($d, 'leger-sts.penghargaan', ['kategori' => $kategori, 'mapel_id' => $d['mapel']->id]))
                ->assertOk()->assertJsonCount(0, 'data.konteks.kandidat');
        }
    }

    public function test_leger_paralel_statistik_dan_penghargaan_menggunakan_sumber_sama(): void
    {
        $d = $this->data();
        $lain = $this->kelasTambahan($d, 'VII.B', 7, 96);
        $this->login($d['admin']);
        $this->getJson($this->url($d, 'leger-sts.index', ['mode' => 'tingkat', 'tingkat' => 7]))->assertOk()
            ->assertJsonPath('data.konteks.cakupan_tingkat_lengkap', true)->assertJsonPath('data.konteks.ringkasan.jumlah_kelas', 2)
            ->assertJsonPath('data.konteks.siswa.0.kelas.id', $lain->id)->assertJsonPath('data.konteks.siswa.0.rata_leger', 96)
            ->assertJsonPath('data.konteks.statistik_kelas.0.kelas.id', $lain->id)->assertJsonCount(2, 'data.konteks.statistik_mapel.0.per_kelas')
            ->assertJsonPath('data.konteks.mapel_tertinggi.mapel.id', $d['mapel']->id);
        $this->getJson($this->url($d, 'leger-sts.penghargaan', ['mode' => 'tingkat', 'tingkat' => 7, 'batas' => 1]))->assertOk()
            ->assertJsonCount(1, 'data.konteks.kandidat')->assertJsonPath('data.konteks.kandidat.0.kelas.id', $lain->id);
        $this->getJson($this->url($d, 'leger-sts.penghargaan', ['mapel_id' => 99999]))->assertNotFound();
        $this->getJson($this->url($d, 'leger-sts.penghargaan', ['batas' => 99]))->assertUnprocessable();
    }

    public function test_periode_dan_pemeriksaan_presensi_memakai_versi_dan_tidak_mengubah_scan(): void
    {
        $d = $this->data();
        $this->login($d['wali']);
        $periode = $this->periode();
        $this->putJson($this->aksi($d, 'pengaturan'), [...$periode, 'tanggal_awal_presensi' => '2026-06-01'])->assertUnprocessable();
        $this->putJson($this->aksi($d, 'pengaturan'), $periode)->assertOk();
        $this->putJson($this->aksi($d, 'pengaturan'), $periode)->assertUnprocessable()->assertJsonValidationErrors('versi');
        $r = $this->getJson($this->url($d, 'rapor-sts.index'))->assertOk()->json('data.konteks');
        $payload = $this->presensi($r);
        $this->putJson($this->aksi($d, 'kehadiran'), $payload)->assertOk();
        $this->getJson($this->url($d, 'rapor-sts.index'))->assertOk()->assertJsonPath('data.konteks.siswa.2.siap_cetak', true);
        $this->putJson($this->aksi($d, 'kehadiran'), $payload)->assertUnprocessable()->assertJsonValidationErrors('versi');
        $this->assertDatabaseCount('absensi_siswa', 1);
        $this->assertSame('07:07:00', AbsensiSiswa::firstOrFail()->jam_masuk);
    }

    public function test_koreksi_tanpa_alasan_dan_snapshot_basi_membatalkan_penyimpanan_kolektif(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $this->putJson($this->aksi($d, 'pengaturan'), $this->periode())->assertOk();
        $payload = $this->presensi($this->getJson($this->url($d, 'rapor-sts.index'))->json('data.konteks'));
        $id = $d['anggota'][1]->id;
        $ganti = $payload;
        $ganti['siswa'][$id]['alfa']++;
        $this->putJson($this->aksi($d, 'kehadiran'), $ganti)->assertUnprocessable()->assertJsonValidationErrors("siswa.$id.catatan_koreksi");
        $ganti = $payload;
        $ganti['siswa'][$id]['sidik_sumber'] = str_repeat('0', 64);
        $this->putJson($this->aksi($d, 'kehadiran'), $ganti)->assertUnprocessable();
        $this->assertDatabaseCount('kehadiran_rapor_sts', 0);
        $this->assertSame(1, RaporStsKelas::firstOrFail()->versi);
        $ganti = $payload;
        $ganti['siswa'][$id]['alfa']++;
        $ganti['siswa'][$id]['catatan_koreksi'] = 'Koreksi rapor dengan alasan yang telah diperiksa.';
        $this->putJson($this->aksi($d, 'kehadiran'), $ganti)->assertOk();
        $this->assertDatabaseCount('absensi_siswa', 1);
    }

    public function test_api_cetak_rapor_tidak_menunggu_nilai_lengkap_dan_tidak_redirect(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $url = route('api.v1.rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]);
        $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors('cetak');
        $this->getJson($url.'?pratinjau=1')->assertOk()->assertSee('Belum tersedia');
        $this->putJson($this->aksi($d, 'pengaturan'), $this->periode())->assertOk();
        $payload = $this->presensi($this->getJson($this->url($d, 'rapor-sts.index'))->json('data.konteks'));
        $this->putJson($this->aksi($d, 'kehadiran'), $payload)->assertOk();
        $this->getJson($url)->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')->assertSee('Belum tersedia');
        $this->getJson($url.'?perilaku=1')->assertUnprocessable()->assertJsonValidationErrors('cetak');
        $this->getJson($url.'?anggota_id=999999')->assertNotFound();
        foreach (['kelas', 'tingkat'] as $mode) {
            $this->getJson($this->url($d, 'leger-sts.cetak', ['mode' => $mode, 'tingkat' => 7]))->assertOk()->assertSee('Alya API STS');
        }
        $this->getJson(route('api.v1.leger-sts.cetak'))->assertUnprocessable();
    }

    public function test_koreksi_input_cbt_dan_pengecualian_terbaca_di_api_rapor(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $ujian = UjianCbt::create(['jenis_ujian_cbt_id' => $d['kegiatan']->jenis_ujian_cbt_id, 'tahun_pelajaran_id' => $d['tahun']->id,
            'mata_pelajaran_id' => $d['mapel']->id, 'kode' => 'STS-API-CBT', 'nama' => 'CBT API', 'semester' => 'ganjil',
            'tingkat' => 7, 'alur' => 'terpusat', 'status' => 'selesai', 'hasil_difinalisasi_pada' => now()]);
        $kelasUjian = KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $d['kelas']->id, 'komponen_nilai_id' => $d['komponen']->id]);
        $jadwal = $d['kegiatan']->jadwalUjianCbt()->create(['ujian_cbt_id' => $ujian->id, 'mata_pelajaran_id' => $d['mapel']->id,
            'tanggal' => '2026-09-15', 'waktu_mulai' => '07:00', 'waktu_selesai' => '08:00', 'tingkat' => 7, 'status' => 'selesai']);
        $jadwal->kelas()->attach($d['kelas']);
        PesertaUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_ujian_cbt_id' => $kelasUjian->id,
            'anggota_kelas_id' => $d['anggota'][2]->id, 'nomor_peserta' => 'API-ABSEN', 'status' => 'aktif', 'status_kehadiran_ujian' => 'alfa']);
        NilaiSiswa::where('komponen_nilai_id', $d['komponen']->id)->where('siswa_id', $d['anggota'][0]->siswa_id)->update(['nilai' => 89.75]);
        $this->getJson($this->url($d, 'rapor-sts.index'))->assertOk()->assertJsonPath('data.konteks.siswa.0.nilai.0.nilai', 89.75);
        $this->putJson($this->aksi($d, 'pengaturan'), $this->periode())->assertOk();
        $n = $this->getJson($this->url($d, 'rapor-sts.index'))->json('data.konteks.siswa.2.nilai.0');
        $this->assertTrue($n['dapat_dikecualikan']);
        $body = ['versi' => 1, 'anggota_id' => $d['anggota'][2]->id, 'pengecualian' => [$d['mapel']->id => [
            'tidak_mengikuti' => true, 'alasan' => 'Tidak mengikuti ujian maupun susulan.', 'sidik_kondisi' => $n['sidik_kondisi']]]];
        $this->putJson($this->aksi($d, 'pengecualian'), $body)->assertOk();
        $this->getJson($this->url($d, 'rapor-sts.index'))->assertOk()->assertJsonPath('data.konteks.siswa.2.nilai.0.keterangan', 'Tidak mengikuti STS');
        $this->putJson($this->aksi($d, 'pengecualian'), $body)->assertUnprocessable();
    }

    public function test_pengaturan_mapel_hanya_admin_kurikulum_dan_memeriksa_sidik(): void
    {
        $d = $this->data();
        $lain = MataPelajaran::create(['nama' => 'Tidak Diujikan', 'aktif' => true]);
        GuruMataPelajaran::create(['kelas_id' => $d['kelas']->id, 'tahun_pelajaran_id' => $d['tahun']->id,
            'mata_pelajaran_id' => $lain->id, 'pegawai_id' => $d['wali']->pegawai_id, 'aktif' => true]);
        $this->login($d['admin']);
        $p = $this->getJson($this->url($d, 'rapor-sts.index'))->json('data.konteks.pengaturan_mapel');
        $body = ['versi_mapel' => $p['versi_mapel'], 'sidik_mapel' => $p['sidik_mapel'],
            'mapel_ids' => [$d['mapel']->id], 'alasan_mapel' => 'Mapel lain tidak mengikuti STS.'];
        $this->login($d['wali']);
        $this->putJson($this->aksi($d, 'mapel'), $body)->assertForbidden();
        $this->login($d['admin']);
        $this->putJson($this->aksi($d, 'mapel'), [...$body, 'sidik_mapel' => str_repeat('0', 64)])->assertUnprocessable();
        $this->putJson($this->aksi($d, 'mapel'), $body)->assertOk();
        $this->getJson($this->url($d, 'rapor-sts.index'))->assertOk()->assertJsonCount(1, 'data.konteks.mapel');
        $this->getJson($this->url($d, 'leger-sts.index'))->assertOk()->assertJsonPath('data.konteks.jumlah_mapel_pembagi', 1);
    }

    public function test_kegiatan_non_sts_dan_kelas_tahun_lain_tidak_dapat_digabungkan(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $tahunLain = TahunPelajaran::create(['nama' => '2027/2028', 'tanggal_mulai' => '2027-07-01', 'tanggal_selesai' => '2028-06-30', 'aktif' => true]);
        $kelasLain = Kelas::create(['nama' => 'VII.A Tahun Lain', 'tingkat' => 7, 'tahun_pelajaran_id' => $tahunLain->id, 'aktif' => true]);
        $jenisNonSts = JenisUjianCbt::where('kode', '!=', 'STS')->firstOrFail();
        $nonSts = KegiatanUjianCbt::create(['kode' => 'BUKAN-STS-API', 'nama' => 'Bukan STS', 'tahun_pelajaran_id' => $d['tahun']->id,
            'jenis_ujian_cbt_id' => $jenisNonSts->id, 'semester' => 'ganjil', 'tanggal_mulai' => '2026-09-15', 'tanggal_selesai' => '2026-09-20']);
        foreach (['rapor-sts.index', 'leger-sts.index'] as $route) {
            $this->getJson($this->url($d, $route, ['kelas_id' => $kelasLain->id]))->assertNotFound();
            $this->getJson($this->url($d, $route, ['kegiatan_id' => $nonSts->id]))->assertNotFound();
        }
        $this->putJson(route('api.v1.rapor-sts.pengaturan', [$d['kegiatan'], $kelasLain]), $this->periode())->assertNotFound();
        $this->putJson(route('api.v1.rapor-sts.pengaturan', [$nonSts, $d['kelas']]), $this->periode())->assertNotFound();
        $this->assertDatabaseCount('rapor_sts_kelas', 0);
    }

    public function test_mode_kelas_memakai_tingkat_kelas_asli_dan_penghargaan_menangani_nilai_seri(): void
    {
        $d = $this->data();
        $this->kelasTambahan($d, 'VII.B', 7, 88.5);
        $this->kelasTambahan($d, 'VIII.A', 8, 99);
        $this->login($d['admin']);
        $this->getJson($this->url($d, 'leger-sts.index', ['mode' => 'kelas', 'tingkat' => 8]))->assertOk()
            ->assertJsonPath('data.konteks.kelas.id', $d['kelas']->id)->assertJsonPath('data.konteks.tingkat', 7);
        $this->getJson($this->url($d, 'leger-sts.penghargaan', ['mode' => 'tingkat', 'tingkat' => 7, 'batas' => 1]))->assertOk()
            ->assertJsonCount(2, 'data.konteks.kandidat')->assertJsonPath('data.konteks.kandidat.0.ranking', 1)
            ->assertJsonPath('data.konteks.kandidat.1.ranking', 1);
    }

    private function periode(): array
    {
        return ['versi' => 0, 'tanggal_awal_presensi' => '2026-07-01', 'tanggal_akhir_presensi' => '2026-09-20', 'tanggal_rapor' => '2026-10-02'];
    }

    private function presensi(array $r): array
    {
        return ['versi' => $r['periode']['versi'], 'siswa' => collect($r['siswa'])->mapWithKeys(fn ($s) => [$s['id'] => [
            ...$s['kehadiran'], 'sidik_sumber' => $s['sidik_sumber'], 'diperiksa' => true, 'catatan_koreksi' => $s['catatan_koreksi']]])->all()];
    }

    private function url(array $d, string $route, array $filter = []): string
    {
        return route('api.v1.'.$route, ['kegiatan_id' => $d['kegiatan']->id, 'kelas_id' => $d['kelas']->id, ...$filter]);
    }

    private function aksi(array $d, string $method): string
    {
        return route('api.v1.rapor-sts.'.$method, [$d['kegiatan'], $d['kelas']]);
    }

    private function login(Pengguna $pengguna): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($pengguna->createToken('Uji STS', ['mobile'])->plainTextToken);
    }

    private function akun(string $peran, string $nama): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'jenis_pegawai' => 'Guru', 'aktif' => true]);
        $user = Pengguna::create(['nama' => $nama, 'username' => 'sts-api-'.$pegawai->id, 'pegawai_id' => $pegawai->id,
            'peran' => 'pegawai', 'kata_sandi' => 'test-pass', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $user->daftarPeran()->sync([Peran::where('kode', $peran)->value('id')]);

        return $user;
    }

    private function kelasTambahan(array $d, string $nama, int $tingkat, float $nilai): Kelas
    {
        $kelas = Kelas::create(['nama' => $nama, 'tingkat' => $tingkat, 'tahun_pelajaran_id' => $d['tahun']->id, 'aktif' => true]);
        $guru = GuruMataPelajaran::create(['kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $d['tahun']->id,
            'mata_pelajaran_id' => $d['mapel']->id, 'pegawai_id' => $d['wali']->pegawai_id, 'aktif' => true]);
        $komponen = KomponenNilai::create(['guru_mata_pelajaran_id' => $guru->id, 'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'nama' => 'Praktik STS '.$nama, 'aktif' => true]);
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa '.$nama, 'aktif' => true]);
        AnggotaKelas::create(['kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $d['tahun']->id, 'siswa_id' => $siswa->id, 'nomor_absen' => 1, 'status_keanggotaan' => 'aktif']);
        NilaiSiswa::create(['komponen_nilai_id' => $komponen->id, 'siswa_id' => $siswa->id, 'nilai' => $nilai]);
        $service = app(StsManualService::class);
        $service->tetapkan($d['admin'], $komponen, $service->konteks($komponen)['sidik'], true);

        return $kelas;
    }

    private function data(bool $final = true): array
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $wali = $this->akun('wali_kelas', 'Wali API STS');
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['nama' => 'VII.A', 'tingkat' => 7, 'tahun_pelajaran_id' => $tahun->id, 'wali_kelas_id' => $wali->pegawai_id, 'aktif' => true]);
        $mapel = MataPelajaran::create(['nama' => 'PJOK API STS', 'urutan' => 1, 'aktif' => true]);
        $guru = GuruMataPelajaran::create(['kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $wali->pegawai_id, 'aktif' => true]);
        $komponen = KomponenNilai::create(['guru_mata_pelajaran_id' => $guru->id, 'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'nama' => 'Praktik STS API', 'aktif' => true]);
        $kegiatan = KegiatanUjianCbt::create(['kode' => 'STS-API', 'nama' => 'STS Semester Ganjil', 'tahun_pelajaran_id' => $tahun->id,
            'jenis_ujian_cbt_id' => JenisUjianCbt::where('kode', 'STS')->value('id'), 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-09-15', 'tanggal_selesai' => '2026-09-20', 'status' => 'selesai']);
        $anggota = collect();
        foreach ([88.5, 0, null] as $i => $nilai) {
            $siswa = Siswa::create(['nama_lengkap' => ['Alya API STS', 'Bima API STS', 'Citra API STS'][$i], 'nisn' => '123456789'.$i, 'aktif' => true]);
            $anggota->push(AnggotaKelas::create(['kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $tahun->id,
                'siswa_id' => $siswa->id, 'nomor_absen' => $i + 1, 'status_keanggotaan' => 'aktif']));
            if ($nilai !== null) {
                NilaiSiswa::create(['komponen_nilai_id' => $komponen->id, 'siswa_id' => $siswa->id, 'nilai' => $nilai, 'catatan' => 'NILAI-CATATAN-PRIVAT']);
            }
        }
        if ($final) {
            $service = app(StsManualService::class);
            $service->tetapkan($admin, $komponen, $service->konteks($komponen)['sidik'], true);
        }
        AbsensiSiswa::create(['tanggal' => '2026-08-03', 'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota[0]->id, 'siswa_id' => $anggota[0]->siswa_id, 'status_kehadiran' => 'hadir',
            'sumber' => 'manual', 'jam_masuk' => '07:07:00', 'menit_terlambat' => 7]);

        return compact('admin', 'wali', 'tahun', 'kelas', 'mapel', 'komponen', 'kegiatan', 'anggota');
    }
}
