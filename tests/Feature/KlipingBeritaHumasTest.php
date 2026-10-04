<?php

namespace Tests\Feature;

use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\KlipingBeritaHumas;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Support\TautanPublikHumas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class KlipingBeritaHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
        Storage::fake('local');
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    public function test_izin_role_menu_dan_berkas(): void
    {
        $k = $this->fixture();
        $this->get(route('kliping-berita-humas.index'))->assertRedirect(route('login'));
        foreach (['siswa', 'orang_tua', 'satpam', 'pegawai', 'guru_mapel'] as $role) {
            $this->actingAs($this->akun($role))->get(route('kliping-berita-humas.index'))->assertForbidden();
            $this->get(route('kliping-berita-humas.show', $k))->assertForbidden();
            $this->get(route('kliping-berita-humas.dokumen'))->assertForbidden();
            $this->post(route('kliping-berita-humas.store'), $this->data())->assertForbidden();
            $this->put(route('kliping-berita-humas.update', $k), $this->editData($k))->assertForbidden();
        }
        $this->actingAs($this->akun('pimpinan'))->get(route('kliping-berita-humas.index'))->assertOk()->assertSee('Kliping Berita')->assertDontSee('Tambah kliping');
        $this->get(route('kliping-berita-humas.show', $k))->assertOk()->assertDontSee('Edit kliping');
        $this->get(route('kliping-berita-humas.edit', $k))->assertForbidden();
        $this->get(route('kliping-berita-humas.create'))->assertForbidden();
        $this->actingAs($this->akun())->get(route('kliping-berita-humas.index'))->assertOk()->assertSee('Tambah kliping');
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail())->get(route('kliping-berita-humas.index'))->assertOk();
        $this->delete(route('kliping-berita-humas.show', $k))->assertStatus(405);
    }

    public function test_arsip_tautan_tanpa_mengambil_konten_situs_luar(): void
    {
        $this->actingAs($this->akun());
        $k = $this->buat();
        $this->assertSame('Media Padang', $k->nama_media);
        $this->assertNull($k->riwayat_dokumen_humas_id);
        $this->get(route('kliping-berita-humas.show', $k))->assertOk()->assertSee('Buka berita asli');
        $this->assertDatabaseCount('dokumen_humas', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        Http::assertNothingSent();
    }

    public function test_upload_privat_idempoten_dan_aktor_hash_versi_tidak_bisa_dipalsukan(): void
    {
        $humas = $this->akun();
        $this->actingAs($humas);
        $data = array_replace($this->data(), ['metode' => 'unggah', 'tautan' => null, 'berkas' => $this->foto(),
            'dibuat_oleh_pengguna_id' => 999, 'diubah_oleh_pengguna_id' => 998, 'versi' => 19, 'riwayat_dokumen_humas_id' => 777, 'tautan_hash' => str_repeat('a', 64)]);
        $this->postJson(route('kliping-berita-humas.store'), $data)->assertOk()->assertJsonStructure(['redirect', 'pesan']);
        $k = KlipingBeritaHumas::firstOrFail();
        $this->assertSame($humas->id, $k->dibuat_oleh_pengguna_id);
        $this->assertSame(0, $k->versi);
        $this->assertNull($k->tautan_hash);
        $this->assertNotSame(777, $k->riwayat_dokumen_humas_id);
        $this->assertSame('kliping_media', $k->berkas->dokumen->kategori);
        $this->assertArrayNotHasKey('token_pembuatan', $k->toArray());
        $this->postJson(route('kliping-berita-humas.store'), $data)->assertOk();
        $this->assertDatabaseCount('kliping_berita_humas', 1);
        $this->assertDatabaseCount('riwayat_kliping_berita_humas', 1);
        $this->assertDatabaseCount('dokumen_humas', 1);
        $this->assertDatabaseCount('riwayat_dokumen_humas', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('dokumen-humas'));
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->actingAs($this->akun())->postJson(route('kliping-berita-humas.store'), $data)->assertForbidden();
        $this->assertCount(1, Storage::disk('local')->allFiles('dokumen-humas'));
    }

    public function test_tautan_ganda_tidak_meninggalkan_berkas_atau_dokumen_baru(): void
    {
        $this->actingAs($this->akun());
        $k = $this->buat();
        foreach ([$k->tautan, 'https://MEDIA.test/berita-sekolah/', 'https://media.test/berita-sekolah#foto'] as $url) {
            $this->postJson(route('kliping-berita-humas.store'), array_replace($this->data(), ['tautan' => $url, 'metode' => 'unggah', 'berkas' => $this->foto()]))->assertUnprocessable()->assertJsonValidationErrors('tautan');
        }
        $this->assertDatabaseCount('kliping_berita_humas', 1);
        $this->assertDatabaseCount('dokumen_humas', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $lain = $this->buat(['tautan' => 'https://media.test/berita-lain']);
        $this->putJson(route('kliping-berita-humas.update', $lain), $this->editData($lain, ['tautan' => $k->tautan, 'metode' => 'unggah', 'berkas' => $this->foto()]))->assertJsonValidationErrors('tautan');
        $this->assertSame('https://media.test/berita-lain', $lain->fresh()->tautan);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_wajib_ada_tautan_atau_bukti_dan_bukti_lama_tidak_dihapus_fisik(): void
    {
        $this->actingAs($this->akun());
        $this->postJson(route('kliping-berita-humas.store'), array_replace($this->data(), ['tautan' => null]))->assertJsonValidationErrors('tautan');
        $k = $this->buat(['tautan' => null, 'metode' => 'unggah', 'berkas' => $this->foto()]);
        $awal = $k->berkas;
        $this->putJson(route('kliping-berita-humas.update', $k), $this->editData($k, ['metode' => 'hapus']))->assertJsonValidationErrors('tautan');
        $this->assertSame($awal->id, $k->fresh()->riwayat_dokumen_humas_id);
        $this->putJson(route('kliping-berita-humas.update', $k), $this->editData($k, ['metode' => 'hapus', 'tautan' => 'https://media.test/berita-sekolah']))->assertOk();
        $this->assertNull($k->fresh()->riwayat_dokumen_humas_id);
        $this->get(route('kliping-berita-humas.berkas', [$k, $awal]))->assertOk();
        Storage::disk('local')->assertExists($awal->lokasi_file);
        $this->assertSame($awal->id, $k->riwayat()->where('versi', 0)->first()->riwayat_dokumen_humas_id);
    }

    public function test_revisi_bukti_tidak_menimpa_dokumen_terhubung_dan_versi_usang_dibersihkan(): void
    {
        $this->actingAs($this->akun());
        $doc = $this->dokumen();
        $k = $this->buat(['metode' => 'dokumen', 'dokumen_humas_id' => $doc->id]);
        $awal = $k->berkas;
        $token = $k->token_pembuatan;
        $data = $this->editData($k, ['judul' => 'Berita prestasi versi koreksi', 'metode' => 'unggah', 'berkas' => $this->foto(), 'token_pembuatan' => (string) Str::uuid()]);
        $this->putJson(route('kliping-berita-humas.update', $k), $data)->assertOk();
        $this->assertSame($token, $k->fresh()->token_pembuatan);
        $this->assertSame(1, $k->fresh()->versi);
        $this->assertNotSame($doc->id, $k->fresh()->berkas->dokumen_humas_id);
        $this->assertSame($awal->lokasi_file, $doc->fresh()->lokasi_file);
        $this->putJson(route('kliping-berita-humas.update', $k), $data)->assertJsonValidationErrors('versi');
        $this->assertCount(2, Storage::disk('local')->allFiles('dokumen-humas'));
        $this->get(route('kliping-berita-humas.berkas', [$k, $awal]))->assertOk();
        $this->assertSame('Siswa sekolah meraih prestasi', $k->riwayat()->where('versi', 0)->first()->snapshot['judul']);
    }

    public function test_pilihan_dokumen_memakai_versi_tertentu_tanpa_duplikasi(): void
    {
        $this->actingAs($this->akun());
        $doc = $this->dokumen();
        $k = $this->buat(['tautan' => null, 'metode' => 'dokumen', 'dokumen_humas_id' => $doc->id]);
        $awal = $k->riwayat_dokumen_humas_id;
        $this->versiDokumen($doc, 2);
        $this->assertSame($awal, $k->fresh()->riwayat_dokumen_humas_id);
        $this->get(route('kliping-berita-humas.berkas', [$k, $doc->riwayat()->first()]))->assertNotFound();
        $this->putJson(route('kliping-berita-humas.update', $k), $this->editData($k, ['metode' => 'dokumen', 'dokumen_humas_id' => $doc->id]))->assertOk();
        $this->assertNotSame($awal, $k->fresh()->riwayat_dokumen_humas_id);
        $this->assertDatabaseCount('dokumen_humas', 1);
        $this->get(route('kliping-berita-humas.berkas', [$k, $awal, 'unduh' => 1]))->assertDownload('bukti-berita.jpg');
    }

    public function test_dokumen_arsip_tipe_tidak_sesuai_dan_berkas_hilang_ditolak(): void
    {
        $this->actingAs($this->akun());
        $doc = $this->dokumen();
        $data = array_replace($this->data(), ['metode' => 'dokumen', 'dokumen_humas_id' => $doc->id]);
        $doc->update(['status' => 'arsip']);
        $this->postJson(route('kliping-berita-humas.store'), $data)->assertJsonValidationErrors('dokumen_humas_id');
        $doc->update(['status' => 'aktif']);
        $versi = $doc->riwayat()->first();
        $versi->update(['tipe_file' => 'text/html']);
        $this->postJson(route('kliping-berita-humas.store'), $data)->assertJsonValidationErrors('berkas');
        $versi->update(['tipe_file' => 'image/jpeg']);
        Storage::disk('local')->delete($versi->lokasi_file);
        $this->postJson(route('kliping-berita-humas.store'), $data)->assertJsonValidationErrors('berkas');
        $doc->riwayat()->delete();
        $this->postJson(route('kliping-berita-humas.store'), $data)->assertJsonValidationErrors('berkas');
        $this->assertDatabaseCount('kliping_berita_humas', 0);
    }

    public function test_izin_kliping_tidak_membuka_berkas_privat_dan_versi_asing(): void
    {
        $this->actingAs($this->akun());
        $k = $this->buat(['metode' => 'unggah', 'berkas' => $this->foto()]);
        $asing = $this->dokumen()->riwayat()->first();
        $this->get(route('kliping-berita-humas.berkas', [$k, $asing]))->assertNotFound();
        $response = $this->get(route('kliping-berita-humas.berkas', [$k, $k->berkas]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->actingAs($this->akunIzin(['kliping_berita_humas.lihat', 'kliping_berita_humas.kelola']));
        $this->get(route('kliping-berita-humas.show', $k))->assertOk()->assertDontSee('bukti-berita.jpg')->assertSee('Bukti privat');
        $this->get(route('kliping-berita-humas.index'))->assertOk()->assertDontSee('/berkas/', false);
        $this->get(route('kliping-berita-humas.berkas', [$k, $k->berkas]))->assertForbidden();
        $this->get(route('kliping-berita-humas.dokumen'))->assertForbidden();
        $this->postJson(route('kliping-berita-humas.store'), array_replace($this->data(), ['tautan' => 'https://media.test/baru', 'metode' => 'unggah', 'berkas' => $this->foto()]))->assertForbidden();
        $this->postJson(route('kliping-berita-humas.store'), array_replace($this->data(), ['tautan' => 'https://media.test/baru', 'metode' => 'dokumen', 'dokumen_humas_id' => $k->berkas->dokumen_humas_id]))->assertForbidden();
        $this->buat(['tautan' => 'https://media.test/baru']);
        $this->assertDatabaseCount('kliping_berita_humas', 2);
    }

    public function test_validasi_tipe_ukuran_sumber_tunggal_dan_pdf(): void
    {
        $this->actingAs($this->akun());
        foreach ([UploadedFile::fake()->create('bahaya.html', 1, 'text/html'), $this->foto()->size(20481), UploadedFile::fake()->createWithContent('bahaya.html', file_get_contents(public_path('images/login-sekolah.jpg')))] as $file) {
            $this->postJson(route('kliping-berita-humas.store'), array_replace($this->data(), ['metode' => 'unggah', 'berkas' => $file]))->assertJsonValidationErrors('berkas');
        }
        $this->postJson(route('kliping-berita-humas.store'), $this->data() + ['berkas' => $this->foto()])->assertJsonValidationErrors('berkas');
        $this->postJson(route('kliping-berita-humas.store'), array_replace($this->data(), ['metode' => 'unggah']))->assertJsonValidationErrors('berkas');
        $this->postJson(route('kliping-berita-humas.store'), array_replace($this->data(), ['metode' => 'dokumen']))->assertJsonValidationErrors('dokumen_humas_id');
        $k = $this->buat(['tautan' => null, 'jenis' => 'cetak', 'metode' => 'unggah', 'berkas' => UploadedFile::fake()->create('koran.pdf', 20, 'application/pdf')]);
        $this->get(route('kliping-berita-humas.berkas', [$k, $k->berkas]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_tautan_aman_kredensial_tidak_diflash_dan_tanggal_valid(): void
    {
        $this->actingAs($this->akun());
        foreach (['javascript:alert(1)', 'data:text/html,hello', 'file:///C:/Windows', ['https://media.test']] as $url) {
            $this->postJson(route('kliping-berita-humas.store'), array_replace($this->data(), ['tautan' => $url]))->assertJsonValidationErrors('tautan');
        }
        foreach (['https://user:Rahasia@media.test', 'https://media.test/?access_token=Rahasia', 'https://media.test/#password=Rahasia'] as $url) {
            $this->post(route('kliping-berita-humas.store'), array_replace($this->data(), ['tautan' => $url]))->assertSessionHasErrors('tautan');
            $this->assertStringNotContainsString('Rahasia', json_encode(session('_old_input')));
        }
        $this->post(route('kliping-berita-humas.store'), $this->data() + ['kata_sandi' => 'Rahasia'])->assertSessionHasErrors('tautan')->assertSessionMissing('_old_input.kata_sandi');
        foreach (['judul' => '', 'nama_media' => '', 'jenis' => 'palsu', 'topik' => 'palsu', 'tanggal_terbit' => '2026-10-06', 'ringkasan' => str_repeat('x', 3001)] as $key => $value) {
            $this->postJson(route('kliping-berita-humas.store'), array_replace($this->data(), [$key => $value]))->assertJsonValidationErrors($key);
        }
        $this->assertDatabaseCount('kliping_berita_humas', 0);
    }

    public function test_metadata_arsip_riwayat_noop_dan_alasan_wajib(): void
    {
        $this->actingAs($this->akun());
        $k = $this->buat();
        $this->putJson(route('kliping-berita-humas.update', $k), $this->editData($k, ['catatan_perubahan' => '']))->assertJsonValidationErrors('catatan_perubahan');
        $this->putJson(route('kliping-berita-humas.update', $k), $this->editData($k))->assertOk();
        $this->assertSame(0, $k->fresh()->versi);
        $this->assertDatabaseCount('riwayat_kliping_berita_humas', 1);
        $this->putJson(route('kliping-berita-humas.update', $k), $this->editData($k, ['status' => 'arsip', 'judul' => 'Judul berita dikoreksi']))->assertOk();
        $this->get(route('kliping-berita-humas.index'))->assertOk()->assertDontSee('Judul berita dikoreksi');
        $this->get(route('kliping-berita-humas.index', ['status' => 'arsip']))->assertOk()->assertSee('Judul berita dikoreksi');
        $this->assertSame('Siswa sekolah meraih prestasi', $k->riwayat()->where('versi', 0)->first()->snapshot['judul']);
        $this->assertSame('Perbaiki arsip pemberitaan sekolah.', $k->riwayat()->where('versi', 1)->first()->catatan_perubahan);
    }

    public function test_filter_tanggal_media_jenis_topik_statistik_dan_paginasi(): void
    {
        $this->actingAs($this->akun());
        $k = $this->fixture(['judul' => 'Prestasi lomba pelajar', 'topik' => 'prestasi', 'nama_media' => 'Media Prestasi', 'tanggal_terbit' => '2026-10-03']);
        $this->fixture(['judul' => 'Arsip lama', 'status' => 'arsip', 'tautan' => 'https://media.test/arsip']);
        for ($i = 0; $i < 22; $i++) {
            $this->fixture(['judul' => 'Berita kegiatan '.$i, 'tautan' => 'https://media.test/kegiatan-'.$i, 'tanggal_terbit' => '2026-09-01', 'topik' => 'kegiatan']);
        }
        $r = $this->get(route('kliping-berita-humas.index'))->assertOk()->assertDontSee('Arsip lama');
        $this->assertCount(20, $r->viewData('daftar'));
        $this->assertSame($k->id, $r->viewData('daftar')->first()->id);
        $this->assertSame(23, $r->viewData('statistik')['aktif']);
        $this->assertSame(1, $r->viewData('statistik')['bulan_ini']);
        $this->assertSame(2, $r->viewData('statistik')['media']);
        $this->get(route('kliping-berita-humas.index', ['kata_kunci' => 'lomba', 'nama_media' => 'Media Prestasi', 'jenis' => 'online', 'topik' => 'prestasi', 'mulai' => '2026-10-01', 'sampai' => '2026-10-05']))->assertOk()->assertSee('Prestasi lomba pelajar')->assertDontSee('Berita kegiatan');
        $this->get(route('kliping-berita-humas.index', ['sampai' => '2026-09-30']))->assertOk()->assertDontSee('Prestasi lomba pelajar');
        $this->get(route('kliping-berita-humas.index', ['mulai' => '2026-10-01']))->assertOk()->assertSee('Prestasi lomba pelajar')->assertDontSee('Berita kegiatan');
        $this->get(route('kliping-berita-humas.index', ['mulai' => '2026-10-04', 'sampai' => '2026-10-01']))->assertSessionHasErrors('sampai');
        $this->assertSame(24, $this->get(route('kliping-berita-humas.index', ['status' => 'semua']))->viewData('daftar')->total());
    }

    public function test_tampilan_pencarian_dokumen_dan_escape_html(): void
    {
        $humas = $this->akun();
        $this->actingAs($humas);
        $this->capture('form', $this->get(route('kliping-berita-humas.create'))->assertOk());
        $k = $this->buat(['judul' => 'Siswa SMP Negeri 2 Padang Panjang meraih prestasi dalam kegiatan pendidikan bersama masyarakat dan mitra sekolah', 'metode' => 'unggah', 'berkas' => $this->foto()]);
        $this->capture('show', $this->get(route('kliping-berita-humas.show', $k))->assertOk());
        $this->putJson(route('kliping-berita-humas.update', $k), $this->editData($k, ['penulis' => 'Wartawan Pendidikan', 'ringkasan' => 'Pemberitaan tentang prestasi siswa dan kerja sama sekolah dengan masyarakat.']))->assertOk();
        $this->capture('history', $this->get(route('kliping-berita-humas.show', $k))->assertOk());
        $this->capture('edit', $this->get(route('kliping-berita-humas.edit', $k->fresh()))->assertOk());
        $this->buat(['judul' => 'Kliping koran tentang kegiatan sekolah', 'tautan' => null, 'jenis' => 'cetak', 'topik' => 'kegiatan', 'metode' => 'unggah', 'berkas' => UploadedFile::fake()->create('koran.pdf', 20, 'application/pdf')]);
        $this->buat(['judul' => 'Liputan kerja sama sekolah', 'topik' => 'kemitraan', 'nama_media' => 'Televisi Pendidikan', 'jenis' => 'televisi', 'tautan' => 'https://media.test/liputan']);
        $this->buat(['judul' => 'Kliping diarsipkan', 'status' => 'arsip', 'tautan' => 'https://media.test/arsip']);
        $this->capture('index', $this->get(route('kliping-berita-humas.index'))->assertOk());
        $this->capture('all', $this->get(route('kliping-berita-humas.index', ['status' => 'semua']))->assertOk());
        $this->capture('empty', $this->get(route('kliping-berita-humas.index', ['kata_kunci' => 'tidak ditemukan']))->assertOk());
        $this->getJson(route('kliping-berita-humas.dokumen', ['cari' => 'Siswa']))->assertOk()->assertJsonCount(1, 'dokumen');
        $this->actingAs($this->akun('pimpinan'));
        $this->capture('readonly', $this->get(route('kliping-berita-humas.show', $k))->assertOk()->assertDontSee('Edit kliping'));
        $html = '</textarea><script>alert(1)</script>';
        $k->update(['judul' => $html, 'ringkasan' => $html, 'nama_media' => $html]);
        $this->get(route('kliping-berita-humas.show', $k))->assertOk()->assertDontSee($html, false)->assertSee(e($html), false);
        $this->actingAs($humas)->get(route('kliping-berita-humas.edit', $k))->assertOk()->assertDontSee($html, false);
    }

    private function akun(string $role = 'wakil_pimpinan_humas'): Pengguna
    {
        $p = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'kliping.'.Str::uuid(), 'kata_sandi' => 'UjiKliping123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function akunIzin(array $izin): Pengguna
    {
        $role = Peran::create(['kode' => 'kliping_'.Str::random(12), 'nama' => 'Kliping terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $p = $this->akun('pegawai');
        $p->daftarPeran()->attach($role);

        return $p;
    }

    private function data(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'judul' => 'Siswa sekolah meraih prestasi', 'nama_media' => 'Media Padang', 'jenis' => 'online', 'topik' => 'prestasi',
            'tanggal_terbit' => '2026-10-04', 'tautan' => 'https://media.test/berita-sekolah', 'metode' => 'tanpa', 'status' => 'aktif'];
    }

    private function buat(array $data = []): KlipingBeritaHumas
    {
        $this->postJson(route('kliping-berita-humas.store'), array_replace($this->data(), $data))->assertOk();

        return KlipingBeritaHumas::latest('id')->firstOrFail();
    }

    private function fixture(array $data = []): KlipingBeritaHumas
    {
        $values = array_replace($this->data(), $data);
        $k = new KlipingBeritaHumas(collect($values)->except('metode')->all());
        $k->forceFill(['tautan_hash' => TautanPublikHumas::hash($k->tautan)])->save();

        return $k;
    }

    private function editData(KlipingBeritaHumas $k, array $data = []): array
    {
        return array_replace($k->only(KlipingBeritaHumas::KOLOM), ['tanggal_terbit' => $k->tanggal_terbit->format('Y-m-d'), 'metode' => 'tetap', 'versi' => $k->versi,
            'catatan_perubahan' => 'Perbaiki arsip pemberitaan sekolah.'], $data);
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bukti-berita.jpg', file_get_contents(public_path('images/login-sekolah.jpg')));
    }

    private function dokumen(): DokumenHumas
    {
        $file = $this->foto();
        $metadata = ['lokasi_file' => $file->storeAs('dokumen-humas', Str::uuid().'.jpg', 'local'), 'nama_file_asli' => $file->getClientOriginalName(), 'tipe_file' => 'image/jpeg', 'ukuran_file' => $file->getSize()];
        $doc = DokumenHumas::create($metadata + ['judul' => 'Bukti pemberitaan eksternal', 'kategori' => 'kliping_media', 'status' => 'aktif']);
        $doc->riwayat()->create($metadata + ['versi' => 1, 'catatan' => 'Bukti pertama.', 'diunggah_pada' => now()]);

        return $doc;
    }

    private function versiDokumen(DokumenHumas $doc, int $versi): void
    {
        $file = $this->foto();
        $metadata = ['lokasi_file' => $file->storeAs('dokumen-humas', Str::uuid().'.jpg', 'local'), 'nama_file_asli' => $file->getClientOriginalName(), 'tipe_file' => 'image/jpeg', 'ukuran_file' => $file->getSize()];
        $doc->riwayat()->create($metadata + ['versi' => $versi, 'catatan' => 'Revisi bukti.', 'diunggah_pada' => now()]);
        $doc->update($metadata);
    }

    private function capture(string $name, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_KLIPING_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/kliping-berita-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$name.'.html', $response->getContent());
    }
}
