<?php

namespace Tests\Feature;

use App\Console\Commands\SiapkanFestivalLiterasi2026;
use App\Models\AcaraBukuTamu;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\ResponAcaraBukuTamu;
use App\Models\Siswa;
use App\Services\Humas\AcaraBukuTamuService;
use App\Services\Humas\NavigasiHumasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class AcaraBukuTamuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 28)->setTime(10, 0));
        config(['app.url' => 'http://localhost']);
    }

    public function test_formulir_publik_tanpa_login_dan_data_pengunjung_tidak_dibocorkan(): void
    {
        $a = $this->acara();
        $r = $this->get($a->urlPublik())->assertOk()->assertSee('Kirim kunjungan')->assertSee('SMP Negeri 2 Padang Panjang')
            ->assertDontSee('app-sidebar')->assertDontSee('nomor_wa')->assertDontSee('nisn')->assertHeader('Cache-Control', 'no-store, private');
        $data = $this->kiriman($a);
        $this->post($a->urlPublik(), $data)->assertRedirect(route('kunjungi-acara.selesai', $a->kode_publik));
        $this->get(route('kunjungi-acara.selesai', $a->kode_publik))->assertOk()->assertSee('Terima kasih sudah hadir')->assertDontSee($data['nama']);
        $this->get($a->urlPublik())->assertDontSee($data['nama'])->assertDontSee($data['saran']);
        $this->get(route('kunjungi-acara.selesai', $a->kode_publik))->assertRedirect($a->urlPublik());
        $this->assertDatabaseCount('kunjungan_tamu', 0);
        $this->assertDatabaseCount('respon_acara_buku_tamu', 1);
    }

    public function test_kirim_idempoten_dan_tablet_dapat_melayani_pengunjung_berikutnya(): void
    {
        $a = $this->acara();
        $data = $this->kiriman($a) + ['status' => 'dibatalkan', 'created_at' => '2020-01-01', 'acara_buku_tamu_id' => 999];
        $this->postJson($a->urlPublik(), $data)->assertCreated();
        $this->postJson($a->urlPublik(), $data)->assertOk();
        $this->assertDatabaseCount('respon_acara_buku_tamu', 1);
        $row = ResponAcaraBukuTamu::firstOrFail();
        $this->assertSame('valid', $row->status);
        $this->assertSame($a->id, $row->acara_buku_tamu_id);
        $this->assertSame(now()->toDateTimeString(), $row->created_at->toDateTimeString());
        $this->assertNull($row->nilai_nusa);
        $this->postJson($a->urlPublik(), array_replace($data, ['nama' => 'Nama berbeda']))->assertUnprocessable();
        $this->postJson($a->urlPublik(), $this->kiriman($a))->assertCreated();
        $this->assertDatabaseCount('respon_acara_buku_tamu', 2);
    }

    public function test_tanda_tangan_tidak_dapat_diganti_dipakai_lintas_acara_atau_kedaluwarsa(): void
    {
        $a = $this->acara();
        $b = $this->acara();
        $d = $this->kiriman($a);
        $this->postJson($a->urlPublik(), array_replace($d, ['tanda_tangan' => 'palsu']))->assertUnprocessable();
        $this->postJson($b->urlPublik(), $d)->assertUnprocessable();
        $this->travel(5)->hours();
        $this->postJson($a->urlPublik(), $d)->assertUnprocessable();
        $this->assertDatabaseCount('respon_acara_buku_tamu', 0);
    }

    public function test_status_dan_periode_dicek_kembali_saat_pengiriman(): void
    {
        $a = $this->acara();
        $d = $this->kiriman($a);
        $a->update(['status' => 'ditutup']);
        $this->postJson($a->urlPublik(), $d)->assertConflict();
        $this->get($a->urlPublik())->assertOk()->assertSee('sudah ditutup')->assertDontSee('id="visitor-form"', false);
        $a->update(['status' => 'dibuka', 'mulai_pada' => now()->addDay(), 'selesai_pada' => now()->addDays(2)]);
        $this->get($a->urlPublik())->assertSee('mulai dibuka')->assertDontSee('id="visitor-form"', false);
        $this->postJson($a->urlPublik(), $d)->assertConflict();
        $a->update(['mulai_pada' => now()->subHours(2), 'selesai_pada' => now()]);
        $this->postJson($a->urlPublik(), $d)->assertCreated();
        $a->update(['status' => 'ditutup']);
        $this->postJson($a->urlPublik(), $d)->assertOk();
        $this->postJson($a->urlPublik(), $this->kiriman($a))->assertConflict();
        $a->update(['status' => 'draf']);
        $this->get($a->urlPublik())->assertNotFound();
        $this->assertDatabaseCount('respon_acara_buku_tamu', 1);
    }

    public function test_validasi_pilihan_tunggal_persetujuan_minat_dan_honeypot(): void
    {
        $a = $this->acara();
        $d = $this->kiriman($a);
        foreach ([['kepuasan' => [4, 5]], ['kepuasan' => 0], ['kategori' => 'administrator'], ['minat' => []],
            ['minat' => ['nusa', 'nusa']], ['minat' => ['rahasia']], ['persetujuan' => 0], ['website' => 'spam.example'],
            ['nilai_nusa' => 6], ['nama' => ''], ['saran' => str_repeat('x', 1001)]] as $ubah) {
            $this->postJson($a->urlPublik(), array_replace($d, $ubah))->assertUnprocessable();
        }
        $this->assertDatabaseCount('respon_acara_buku_tamu', 0);
    }

    public function test_hak_akses_pegawai_humas_pimpinan_dan_siswa_salah_role(): void
    {
        $a = $this->acara();
        $this->get(route('acara-buku-tamu.index'))->assertRedirect(route('login'));
        $this->getJson('/api/v1/humas/acara-buku-tamu')->assertUnauthorized();
        foreach (['satpam', 'guru_mapel'] as $role) {
            $this->actingAs($this->akun($role))->get(route('acara-buku-tamu.show', $a))->assertForbidden();
            $this->get(route('acara-buku-tamu.export', $a))->assertForbidden();
        }
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa uji', 'aktif' => true]);
        $u = $this->akun('administrator');
        $u->update(['siswa_id' => $siswa->id]);
        $this->actingAs($u)->get(route('acara-buku-tamu.show', $a))->assertForbidden();
        $p = $this->akun('pimpinan');
        $this->actingAs($p)->get(route('acara-buku-tamu.show', $a))->assertOk()->assertDontSee('Pengaturan</a>', false);
        $this->get(route('acara-buku-tamu.create'))->assertForbidden();
        $this->get(route('acara-buku-tamu.poster', $a))->assertOk();
        $this->actingAs($this->akun('wakil_pimpinan_humas'))->get(route('acara-buku-tamu.create'))->assertOk();
    }

    public function test_kelola_acara_menggunakan_versi_dan_riwayat_tanpa_mengubah_qr(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $d = $this->dataAcara();
        $this->post(route('acara-buku-tamu.store'), $d)->assertRedirect()->assertSessionHasNoErrors();
        $a = AcaraBukuTamu::firstOrFail();
        $url = $a->urlPublik();
        $this->post(route('acara-buku-tamu.store'), $d)->assertRedirect();
        $this->assertDatabaseCount('acara_buku_tamu', 1);
        $this->put(route('acara-buku-tamu.update', $a), array_replace($d, ['nama' => 'Acara diperbarui', 'versi' => 0, 'kode_publik' => 'palsu']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($url, $a->fresh()->urlPublik());
        $this->assertSame(1, $a->fresh()->versi);
        $this->putJson(route('acara-buku-tamu.update', $a), array_replace($d, ['versi' => 0]))->assertUnprocessable();
        $this->assertDatabaseCount('riwayat_acara_buku_tamu', 2);
    }

    public function test_statistik_puas_dan_penilai_nusa_memakai_pembagi_yang_benar(): void
    {
        $a = $this->acara();
        foreach ([[5, 0], [4, 5], [3, 0], [2, 2], [1, 0]] as [$nilai, $nusa]) {
            $this->postJson($a->urlPublik(), array_replace($this->kiriman($a), ['kepuasan' => $nilai, 'nilai_nusa' => $nusa]))->assertCreated();
        }
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $r = $this->getJson(route('acara-buku-tamu.statistik', $a))->assertOk()->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.persen_puas', 40)->assertJsonPath('data.rata_rata', 3)->assertJsonPath('data.penilai_nusa', 2)->assertJsonPath('data.persen_puas_nusa', 50);
        $this->assertSame(5, array_sum($r->json('data.harian')));
        $this->assertSame(5, array_sum($r->json('data.jam')));
        $this->assertSame(5, $r->json('data.minat.nusa'));
        $b = $this->acara();
        $this->getJson(route('acara-buku-tamu.statistik', $b))->assertJsonPath('data.persen_puas', null)->assertJsonPath('data.persen_puas_nusa', null);
    }

    public function test_pembatalan_dan_pemulihan_respons_memiliki_jejak_tanpa_hapus_data(): void
    {
        $a = $this->acara();
        $b = $this->acara();
        $this->postJson($a->urlPublik(), $this->kiriman($a))->assertCreated();
        $row = ResponAcaraBukuTamu::firstOrFail();
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $this->patchJson(route('acara-buku-tamu.respon.status', [$b, $row]), ['status' => 'dibatalkan', 'alasan' => 'Duplikat manual', 'versi' => 0])->assertNotFound();
        $this->patch(route('acara-buku-tamu.respon.status', [$a, $row]), ['status' => 'dibatalkan', 'alasan' => 'Duplikat manual', 'versi' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->getJson(route('acara-buku-tamu.statistik', $a))->assertJsonPath('data.total', 0);
        $this->patchJson(route('acara-buku-tamu.respon.status', [$a, $row]), ['status' => 'valid', 'alasan' => 'Sudah diperiksa', 'versi' => 0])->assertUnprocessable();
        $this->patch(route('acara-buku-tamu.respon.status', [$a, $row]), ['status' => 'valid', 'alasan' => 'Sudah diperiksa', 'versi' => 1])->assertRedirect();
        $this->getJson(route('acara-buku-tamu.statistik', $a))->assertJsonPath('data.total', 1);
        $this->assertDatabaseCount('respon_acara_buku_tamu', 1);
        $this->assertDatabaseCount('riwayat_acara_buku_tamu', 2);
    }

    public function test_filter_tanggal_kategori_kepuasan_dan_paginasi_terisolasi_per_acara(): void
    {
        $a = $this->acara();
        $b = $this->acara();
        foreach (range(1, 28) as $i) {
            $this->postJson($a->urlPublik(), array_replace($this->kiriman($a), ['nama' => 'Pengunjung '.$i, 'kategori' => $i < 5 ? 'pelajar' : 'umum']))->assertCreated();
        }
        $this->postJson($b->urlPublik(), array_replace($this->kiriman($b), ['nama' => 'Rahasia acara lain']))->assertCreated();
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $this->get(route('acara-buku-tamu.show', $a))->assertOk()->assertDontSee('Rahasia acara lain');
        $this->assertSame(3, $this->get(route('acara-buku-tamu.show', [$a, 'page' => 2]))->viewData('daftar')->count());
        $this->assertSame(4, $this->get(route('acara-buku-tamu.show', [$a, 'kategori' => 'pelajar']))->viewData('daftar')->total());
        $this->getJson(route('acara-buku-tamu.statistik', [$a, 'dari' => '2020-01-01']))->assertUnprocessable();
        $this->get(route('acara-buku-tamu.show', [$a, 'q' => 'Pengunjung 28']))->assertOk()->assertSee('Pengunjung 28')->assertDontSee('Pengunjung 27');
    }

    public function test_api_sanctum_dan_menu_mengikuti_izin_yang_sama(): void
    {
        $u = $this->akun('wakil_pimpinan_humas');
        Sanctum::actingAs($u, ['mobile']);
        $this->getJson('/api/v1/humas/acara-buku-tamu/referensi')->assertOk()->assertJsonPath('data.hak_akses.dapat_kelola', true);
        $r = $this->postJson('/api/v1/humas/acara-buku-tamu', $this->dataAcara())->assertCreated();
        $id = $r->json('data.acara.id');
        $this->getJson('/api/v1/humas/acara-buku-tamu')->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->getJson('/api/v1/humas/acara-buku-tamu/'.$id)->assertOk()->assertJsonPath('data.statistik.total', 0)->assertJsonMissingPath('data.acara.kunci_pembuatan');
        $this->get('/api/v1/humas/acara-buku-tamu/'.$id.'/poster')->assertOk();
        $this->assertContains('acara-buku-tamu', array_column(app(NavigasiHumasService::class)->pendukung($u), 'kode'));
        $this->assertNotContains('acara-buku-tamu', array_column(app(NavigasiHumasService::class)->pendukung($this->akun('satpam')), 'kode'));
    }

    public function test_excel_adalah_xlsx_dan_input_pengunjung_tidak_menjadi_formula(): void
    {
        $a = $this->acara();
        $this->postJson($a->urlPublik(), array_replace($this->kiriman($a), ['nama' => '=HYPERLINK("https://example.invalid")', 'saran' => '<script>uji</script>']))->assertCreated();
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $r = $this->get(route('acara-buku-tamu.export', $a))->assertOk()->assertDownload('pengunjung-acara-'.$a->id.'.xlsx');
        $zip = new ZipArchive;
        $zip->open($r->getFile()->getPathname());
        $xml = $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertStringContainsString('inlineStr', $xml);
        $this->assertStringContainsString('=HYPERLINK', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        $this->get(route('acara-buku-tamu.show', $a))->assertDontSee('<script>uji</script>', false)->assertSee('&lt;script&gt;uji&lt;/script&gt;', false);
    }

    public function test_banner_hanya_gambar_dan_draf_tidak_membuka_akses_publik(): void
    {
        Storage::fake('local');
        $a = $this->acara(['status' => 'draf']);
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $foto = UploadedFile::fake()->createWithContent('acara.jpg', file_get_contents(public_path('images/login-sekolah.jpg')));
        $this->put(route('acara-buku-tamu.update', $a), $this->dataAcara() + ['banner' => $foto, 'versi' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($a->fresh()->banner);
        $this->get(route('kunjungi-acara.banner', $a->kode_publik))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->putJson(route('acara-buku-tamu.update', $a), $this->dataAcara() + ['banner' => UploadedFile::fake()->createWithContent('acara.svg', '<svg/>'), 'versi' => 1])->assertUnprocessable();
    }

    public function test_capture_desain_sintetis_dan_poster_qr_kanonik(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 29)->setTime(10, 0));
        $a = $this->acara(['nama' => 'Festival Literasi IV Kota Padang Panjang 2026', 'lokasi' => 'Lapangan Banca Laweh',
            'mulai_pada' => '2026-10-29 00:00:00', 'selesai_pada' => '2026-10-31 23:59:59']);
        $publik = $this->get($a->urlPublik())->assertOk();
        $d = $this->kiriman($a);
        $this->post($a->urlPublik(), $d)->assertRedirect();
        $sukses = $this->get(route('kunjungi-acara.selesai', $a->kode_publik))->assertOk();
        foreach (range(1, 6) as $i) {
            $this->postJson($a->urlPublik(), array_replace($this->kiriman($a), ['nama' => 'Pengunjung Contoh '.$i, 'kepuasan' => ($i % 5) + 1, 'nilai_nusa' => $i % 3 === 0 ? 0 : 4]))->assertCreated();
        }
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $index = $this->get(route('acara-buku-tamu.index'))->assertOk();
        $form = $this->get(route('acara-buku-tamu.create'))->assertOk();
        $show = $this->get(route('acara-buku-tamu.show', $a))->assertOk();
        $poster = $this->get(route('acara-buku-tamu.poster', $a))->assertOk()->assertSee($a->urlPublik())->assertSee('<svg', false);
        $a5 = $this->get(route('acara-buku-tamu.poster', [$a, 'ukuran' => 'A5']))->assertOk();
        $cetak = $this->get(route('acara-buku-tamu.cetak', $a))->assertOk();
        $this->get(route('acara-buku-tamu.qr', $a))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        if (getenv('NUSA_CAPTURE_ACARA')) {
            $path = storage_path('framework/testing/acara-buku-tamu');
            File::ensureDirectoryExists($path);
            foreach (compact('publik', 'sukses', 'index', 'form', 'show', 'poster', 'a5', 'cetak') as $name => $response) {
                File::put($path.'/'.$name.'.html', $response->getContent());
            }
            File::put($path.'/meta.json', json_encode(['url' => $a->urlPublik()]));
            $pilihan = $a->pilihanMinat();
            $pilihan[1]['aktif'] = false;
            array_unshift($pilihan, ['kode' => 'robotika', 'nama' => 'Pameran Robotika dan Inovasi Teknologi Pembelajaran Siswa', 'ikon' => 'flask-conical', 'aktif' => true]);
            $a->update(['pilihan_minat' => $pilihan]);
            File::put($path.'/publik-dinamis.html', $this->get($a->urlPublik())->assertOk()->getContent());
            File::put($path.'/form-edit.html', $this->get(route('acara-buku-tamu.edit', $a))->assertOk()->getContent());
            $a->update(['banner_tampilan' => 'utuh', 'banner_fokus_desktop' => 31, 'banner_fokus_mobile' => 88]);
            File::put($path.'/publik-utuh.html', $this->get($a->urlPublik())->assertOk()->getContent());
            File::put($path.'/poster-utuh.html', $this->get(route('acara-buku-tamu.poster', [$a, 'ukuran' => 'A5']))->assertOk()->getContent());
            $a->update(['nama' => str_repeat('Festival Literasi ', 10), 'lokasi' => str_repeat('Lapangan Banca Laweh ', 9)]);
            File::put($path.'/poster-panjang.html', $this->get(route('acara-buku-tamu.poster', [$a, 'ukuran' => 'A5']))->assertOk()->getContent());
        }
    }

    public function test_perintah_festival_idempoten_dan_mencakup_tiga_hari_penuh(): void
    {
        $this->artisan('humas:siapkan-festival-literasi-2026')->assertSuccessful();
        $a = AcaraBukuTamu::where('kunci_pembuatan', SiapkanFestivalLiterasi2026::KUNCI)->firstOrFail();
        $this->assertSame('Festival Literasi IV Kota Padang Panjang 2026', $a->nama);
        $this->assertSame('Lapangan Banca Laweh', $a->lokasi);
        $this->assertSame('2026-10-29 00:00:00', $a->mulai_pada->toDateTimeString());
        $this->assertSame('2026-10-31 23:59:59', $a->selesai_pada->toDateTimeString());
        $this->assertFalse($a->menerima());
        $this->travelTo(now()->setDate(2026, 10, 29)->startOfDay());
        $this->assertTrue($a->menerima());
        $this->travelTo(now()->setDate(2026, 10, 31)->endOfDay()->startOfSecond());
        $this->assertTrue($a->menerima());
        $this->travelTo(now()->setMicrosecond(999999));
        $this->assertTrue($a->menerima());
        $this->travel(1)->seconds();
        $this->assertFalse($a->menerima());
        $a->update(['status' => 'ditutup']);
        $this->artisan('humas:siapkan-festival-literasi-2026')->assertSuccessful();
        $this->assertDatabaseCount('acara_buku_tamu', 1);
        $this->assertSame('ditutup', $a->fresh()->status);
        Storage::fake('local');
        Storage::disk('local')->put('acara-buku-tamu/foto-lama.png', $this->gambarPng(1000, 600));
        $a->update(['banner' => 'acara-buku-tamu/foto-lama.png', 'banner_tampilan' => 'utuh', 'banner_fokus_desktop' => 18]);
        $this->artisan('humas:siapkan-festival-literasi-2026')->assertSuccessful();
        $this->assertSame('acara-buku-tamu/foto-lama.png', $a->fresh()->banner);
        $this->artisan('humas:siapkan-festival-literasi-2026', ['--perbarui-foto' => true])->assertSuccessful();
        $this->assertNull($a->fresh()->banner);
        $this->assertSame(66, $a->fresh()->banner_fokus_desktop);
        $this->assertSame('ditutup', $a->fresh()->status);
        $this->assertSame('2026-10-29 00:00:00', $a->fresh()->mulai_pada->toDateTimeString());
        Storage::disk('local')->assertExists('acara-buku-tamu/foto-lama.png');
        $this->artisan('humas:siapkan-festival-literasi-2026', ['--perbarui-foto' => true])->assertSuccessful();
        $this->assertSame(1, $a->fresh()->versi);
        $this->assertDatabaseCount('riwayat_acara_buku_tamu', 2);
    }

    public function test_alamat_qr_mengikuti_konfigurasi_server_tanpa_berubah_kode(): void
    {
        $a = $this->acara();
        config(['app.url' => 'https://server-uji.example']);
        $this->assertSame('https://server-uji.example/kunjungi/'.$a->kode_publik, $a->urlPublik());
        config(['app.url' => 'https://server-final.example']);
        $this->assertSame('https://server-final.example/kunjungi/'.$a->kode_publik, $a->urlPublik());
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $this->get(route('acara-buku-tamu.poster', $a))->assertSee($a->urlPublik());
        $this->get(route('acara-buku-tamu.pratinjau', $a))->assertOk()->assertSee('pengiriman dinonaktifkan')->assertDontSee('name="tanda_tangan"', false);
        $this->assertDatabaseCount('respon_acara_buku_tamu', 0);
    }

    public function test_pilihan_minat_dinamis_per_acara_dan_api_tidak_mengubah_acara_lain(): void
    {
        Sanctum::actingAs($this->akun('wakil_pimpinan_humas'), ['mobile']);
        $pilihan = [
            ['kode' => 'robotika', 'nama' => 'Robotika siswa', 'ikon' => 'flask-conical', 'aktif' => true],
            ['kode' => 'seni', 'nama' => 'Pentas seni', 'ikon' => 'music', 'aktif' => false],
            ['kode' => 'buku', 'nama' => 'Buku karya guru', 'ikon' => 'book-open', 'aktif' => true],
        ];
        $r = $this->postJson('/api/v1/humas/acara-buku-tamu', array_replace($this->dataAcara(), ['status' => 'dibuka', 'pilihan_minat' => $pilihan]))
            ->assertCreated()->assertJsonPath('data.acara.pilihan_minat.0.nama', 'Robotika siswa');
        $a = AcaraBukuTamu::findOrFail($r->json('data.acara.id'));
        $b = $this->acara();
        $this->get($a->urlPublik())->assertSeeInOrder(['Robotika siswa', 'Buku karya guru'])->assertDontSee('value="seni"', false)->assertDontSee('value="nusa"', false);
        $this->postJson($a->urlPublik(), array_replace($this->kiriman($a), ['minat' => ['robotika', 'buku']]))->assertCreated();
        $this->postJson($a->urlPublik(), array_replace($this->kiriman($a), ['minat' => ['seni']]))->assertUnprocessable();
        $this->postJson($b->urlPublik(), array_replace($this->kiriman($b), ['minat' => ['robotika']]))->assertUnprocessable();
        $this->getJson('/api/v1/humas/acara-buku-tamu/'.$a->id)->assertOk()
            ->assertJsonPath('data.statistik.minat.robotika', 1)->assertJsonPath('data.statistik.label_minat.robotika', 'Robotika siswa')
            ->assertJsonPath('data.minat_terpakai', ['buku', 'robotika']);
        $this->getJson('/api/v1/humas/acara-buku-tamu/referensi')->assertJsonPath('data.ikon_minat.music', 'Musik')->assertJsonCount(5, 'data.pilihan_minat_default');
        $this->patchJson('/api/v1/humas/acara-buku-tamu/'.$a->id, $this->dataAcara() + ['versi' => 0])->assertOk()
            ->assertJsonPath('data.acara.pilihan_minat', $pilihan);
        $this->assertCount(5, $b->fresh()->pilihanMinat());
    }

    public function test_minat_terpakai_tetap_dihitung_setelah_nonaktif_dan_replay_tidak_duplikat(): void
    {
        $a = $this->acara();
        $d = $this->kiriman($a);
        $belumDikirim = $this->kiriman($a);
        $this->postJson($a->urlPublik(), $d)->assertCreated();
        $u = $this->akun('wakil_pimpinan_humas');
        $this->actingAs($u);
        $pilihan = $a->pilihanMinat();
        foreach ($pilihan as &$p) {
            if ($p['kode'] === 'nusa') {
                $p['aktif'] = false;
                $p['nama'] = 'Teknologi NUSA';
            }
        }
        unset($p);
        $pilihan = array_reverse($pilihan);
        $this->putJson(route('acara-buku-tamu.update', $a), array_replace($this->dataAcara(), ['status' => 'dibuka', 'versi' => 0, 'pilihan_minat' => $pilihan]))->assertRedirect();
        $a->refresh();
        $this->get($a->urlPublik())->assertDontSee('value="nusa"', false);
        $this->postJson($a->urlPublik(), $d)->assertOk();
        $this->postJson($a->urlPublik(), $belumDikirim)->assertUnprocessable()->assertJsonValidationErrors('minat');
        $this->assertDatabaseCount('respon_acara_buku_tamu', 1);
        $this->get(route('acara-buku-tamu.show', $a))->assertOk()->assertSee('Teknologi NUSA');
        $this->get(route('acara-buku-tamu.cetak', $a))->assertOk()->assertSee('Teknologi NUSA');
        $this->getJson(route('acara-buku-tamu.statistik', $a))->assertJsonPath('data.minat.nusa', 1)->assertJsonPath('data.label_minat.nusa', 'Teknologi NUSA');
        $r = $this->get(route('acara-buku-tamu.export', $a))->assertOk();
        $zip = new ZipArchive;
        $zip->open($r->getFile()->getPathname());
        foreach (['sheet1', 'sheet2'] as $sheet) {
            $this->assertStringContainsString('Teknologi NUSA', $zip->getFromName('xl/worksheets/'.$sheet.'.xml'));
        }
        $zip->close();
        $hapus = array_values(array_filter($pilihan, fn ($p) => $p['kode'] !== 'nusa'));
        $this->putJson(route('acara-buku-tamu.update', $a), $this->dataAcara() + ['versi' => 1, 'pilihan_minat' => $hapus])->assertUnprocessable()->assertJsonValidationErrors('pilihan_minat');
        $a->respon()->update(['status' => 'dibatalkan']);
        $this->putJson(route('acara-buku-tamu.update', $a), $this->dataAcara() + ['versi' => 1, 'pilihan_minat' => $hapus])->assertUnprocessable();
        $this->assertSame(1, $a->fresh()->versi);
        $this->assertCount(5, $a->fresh()->pilihanMinat());
    }

    public function test_hapus_minat_belum_terpakai_validasi_konfigurasi_dan_izin(): void
    {
        $a = $this->acara();
        $pilihan = $a->pilihanMinat();
        $this->actingAs($this->akun('pimpinan'))->putJson(route('acara-buku-tamu.update', $a), $this->dataAcara() + ['versi' => 0, 'pilihan_minat' => $pilihan])->assertForbidden();
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $kasus = [[], null, array_fill(0, 31, $pilihan[0]),
            [array_replace($pilihan[0], ['nama' => '   '])],
            [$pilihan[0], array_replace($pilihan[1], ['nama' => ' KARYA SISWA '])],
            [array_replace($pilihan[0], ['aktif' => false])],
            [array_replace($pilihan[0], ['ikon' => '<svg>'])],
            [array_replace($pilihan[0], ['kode' => 'kode tidak valid'])],
            [array_replace($pilihan[0], ['aktif' => 'false'])],
            [$pilihan[0], $pilihan[0]], ['salah' => $pilihan[0]],
        ];
        foreach ($kasus as $p) {
            $this->putJson(route('acara-buku-tamu.update', $a), $this->dataAcara() + ['versi' => 0, 'pilihan_minat' => $p])->assertUnprocessable();
        }
        $this->assertSame(0, $a->fresh()->versi);
        $this->putJson(route('acara-buku-tamu.update', $a), $this->dataAcara() + ['versi' => 0, 'pilihan_minat' => [$pilihan[0]]])->assertRedirect();
        $this->assertSame([$pilihan[0]], $a->fresh()->pilihanMinat());
        $this->get(route('acara-buku-tamu.edit', $a))->assertOk()->assertSee('Pilihan minat pengunjung');
    }

    public function test_syarat_gambar_dan_pengaturan_banner_api_dengan_kompatibilitas_klien_lama(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->akun('wakil_pimpinan_humas'), ['mobile']);
        $this->getJson('/api/v1/humas/acara-buku-tamu/referensi')->assertOk()
            ->assertJsonPath('data.syarat_banner.minimal_lebar', 1000)->assertJsonPath('data.syarat_banner.minimal_tinggi', 600)
            ->assertJsonPath('data.syarat_banner.maksimal_kb', 4096)->assertJsonPath('data.syarat_banner.tampilan.utuh', 'Gambar utuh');
        $r = $this->postJson('/api/v1/humas/acara-buku-tamu', $this->dataAcara())->assertCreated()
            ->assertJsonPath('data.acara.banner_tampilan', 'potong')->assertJsonPath('data.acara.banner_fokus_desktop', 66)
            ->assertJsonPath('data.acara.banner_fokus_mobile', 74)->assertJsonPath('data.url_banner', asset(AcaraBukuTamu::BANNER_DEFAULT));
        $id = $r->json('data.acara.id');
        $foto = UploadedFile::fake()->createWithContent('gambar.png', $this->gambarPng(1000, 600));
        $this->post('/api/v1/humas/acara-buku-tamu/'.$id, $this->dataAcara() + ['versi' => 0, 'banner' => $foto,
            'banner_tampilan' => 'utuh', 'banner_fokus_desktop' => 0, 'banner_fokus_mobile' => 100])->assertOk()
            ->assertJsonPath('data.acara.banner_tampilan', 'utuh')->assertJsonPath('data.acara.banner_fokus_desktop', 0)
            ->assertJsonPath('data.acara.banner_fokus_mobile', 100);
        $a = AcaraBukuTamu::findOrFail($id);
        $path = $a->banner;
        $this->get('/api/v1/humas/acara-buku-tamu/'.$id.'/banner')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->patchJson('/api/v1/humas/acara-buku-tamu/'.$id, $this->dataAcara() + ['versi' => 1])->assertOk()
            ->assertJsonPath('data.acara.banner_tampilan', 'utuh')->assertJsonPath('data.acara.banner_fokus_desktop', 0);
        $this->assertSame($path, $a->fresh()->banner);
        $this->get(route('acara-buku-tamu.pratinjau', $a))->assertOk()->assertSee('data-banner-tampilan="utuh"', false)
            ->assertSee('--banner-fokus-mobile:100%', false);
        $this->actingAs($this->akun('pimpinan'))->putJson(route('acara-buku-tamu.update', $a), $this->dataAcara() + ['versi' => 2, 'banner_tampilan' => 'potong'])->assertForbidden();
    }

    public function test_validasi_dimensi_ukuran_dan_mode_banner_tidak_mengubah_data_lama(): void
    {
        Storage::fake('local');
        $a = $this->acara();
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        foreach ([[999, 600], [1000, 599], [6001, 1000], [1000, 6001], [5000, 5000]] as [$w, $h]) {
            $foto = UploadedFile::fake()->createWithContent('foto.png', $this->gambarPng($w, $h));
            $this->putJson(route('acara-buku-tamu.update', $a), $this->dataAcara() + ['versi' => 0, 'banner' => $foto])->assertUnprocessable()->assertJsonValidationErrors('banner');
        }
        $besar = UploadedFile::fake()->createWithContent('foto.png', $this->gambarPng(1000, 600))->size(4097);
        foreach ([$besar, 'bukan-berkas', UploadedFile::fake()->createWithContent('foto.jpg', 'bukan gambar')] as $foto) {
            $this->putJson(route('acara-buku-tamu.update', $a), $this->dataAcara() + ['versi' => 0, 'banner' => $foto])->assertUnprocessable()->assertJsonValidationErrors('banner');
        }
        foreach ([['banner_tampilan' => 'acak'], ['banner_tampilan' => null], ['banner_fokus_desktop' => -1],
            ['banner_fokus_mobile' => 101], ['banner_fokus_mobile' => 12.5], ['banner_fokus_desktop' => ['90']], ['banner_fokus_desktop' => '1%;color:red']] as $ubah) {
            $this->putJson(route('acara-buku-tamu.update', $a), $this->dataAcara() + ['versi' => 0] + $ubah)->assertUnprocessable();
        }
        $this->assertNull($a->fresh()->banner);
        $this->assertSame(0, $a->fresh()->versi);
        $this->assertCount(0, Storage::disk('local')->allFiles('acara-buku-tamu'));
        $foto = UploadedFile::fake()->createWithContent('foto.webp', file_get_contents(public_path(AcaraBukuTamu::BANNER_DEFAULT)));
        $this->put(route('acara-buku-tamu.update', $a), $this->dataAcara() + ['versi' => 0, 'banner' => $foto])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $a->fresh()->versi);
        $this->assertCount(1, Storage::disk('local')->allFiles('acara-buku-tamu'));
        $this->get(route('acara-buku-tamu.edit', $a))->assertOk()->assertSee('1920')->assertSee('Foto responsif')->assertSee('Gambar utuh');
    }

    private function gambarPng(int $w, int $h): string
    {
        $chunk = fn ($jenis, $data) => pack('N', strlen($data)).$jenis.$data.pack('N', crc32($jenis.$data));
        $scanline = "\0".str_repeat("\0", (int) ceil($w / 8));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $w, $h, 1, 0, 0, 0, 0))
            .$chunk('IDAT', gzcompress(str_repeat($scanline, $h))).$chunk('IEND', '');
    }

    private function akun(string $role): Pengguna
    {
        $u = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'acara-'.Str::random(10), 'peran' => 'pegawai', 'kata_sandi' => 'AcaraNusa2026!', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $u->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $u;
    }

    private function acara(array $ubah = []): AcaraBukuTamu
    {
        return AcaraBukuTamu::create(array_replace(['nama' => 'Pameran Literasi Kota Padang Panjang', 'lokasi' => 'Stand SMP Negeri 2 Padang Panjang',
            'mulai_pada' => now()->startOfDay()->addHours(8), 'selesai_pada' => now()->startOfDay()->addHours(17), 'status' => 'dibuka',
            'kode_publik' => Str::lower(Str::random(24)), 'kunci_pembuatan' => (string) Str::uuid()], $ubah));
    }

    private function kiriman(AcaraBukuTamu $a): array
    {
        return app(AcaraBukuTamuService::class)->tiket($a) + ['nama' => 'Pengunjung Uji Rahasia', 'instansi' => 'Sekolah Contoh', 'kategori' => 'umum',
            'minat' => ['nusa', 'literasi'], 'kepuasan' => 5, 'nilai_nusa' => 0, 'saran' => 'Kesan sintetis untuk pengujian.', 'persetujuan' => 1];
    }

    private function dataAcara(): array
    {
        return ['nama' => 'Pameran Literasi Contoh', 'lokasi' => 'Stand sekolah', 'mulai_pada' => '2026-10-28T08:00',
            'selesai_pada' => '2026-10-28T17:00', 'status' => 'draf', 'kunci_pembuatan' => (string) Str::uuid()];
    }
}
