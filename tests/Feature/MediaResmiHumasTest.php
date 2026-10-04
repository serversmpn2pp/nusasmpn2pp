<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\MediaResmiHumas;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MediaResmiHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
    }

    public function test_hak_akses_menu_dan_role(): void
    {
        $media = $this->fixture();
        $this->get(route('media-resmi-humas.index'))->assertRedirect(route('login'));
        foreach (['siswa', 'orang_tua', 'guru_mapel', 'pegawai', 'satpam'] as $role) {
            $this->actingAs($this->akun($role))->get(route('media-resmi-humas.index'))->assertForbidden();
            $this->get(route('media-resmi-humas.show', $media))->assertForbidden();
            $this->post(route('media-resmi-humas.store'), $this->data())->assertForbidden();
            $this->put(route('media-resmi-humas.update', $media), $this->editData($media))->assertForbidden();
        }
        $this->actingAs($this->akun('pimpinan'))->get(route('media-resmi-humas.index'))->assertOk()->assertSee('Daftar Media Resmi')->assertDontSee('Tambah media');
        $this->get(route('media-resmi-humas.show', $media))->assertOk()->assertDontSee('Edit media');
        $this->get(route('media-resmi-humas.create'))->assertForbidden();
        $this->get(route('media-resmi-humas.edit', $media))->assertForbidden();
        $this->actingAs($this->akun())->get(route('media-resmi-humas.index'))->assertOk()->assertSee('Tambah media');
        $this->get(route('media-resmi-humas.edit', $media))->assertOk();
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail())->get(route('media-resmi-humas.index'))->assertOk();
        $this->actingAs($this->akunIzin(['media_resmi_humas.lihat']))->get(route('media-resmi-humas.show', $media))->assertOk();
        $this->get(route('media-resmi-humas.edit', $media))->assertForbidden();
        $this->delete(route('media-resmi-humas.show', $media))->assertStatus(405);
    }

    public function test_pembuatan_idempoten_aktor_versi_dan_hash_dikendalikan_server(): void
    {
        $humas = $this->akun();
        $this->actingAs($humas);
        $data = $this->data() + ['dibuat_oleh_pengguna_id' => 999, 'diubah_oleh_pengguna_id' => 998, 'versi' => 22, 'tautan_hash' => str_repeat('f', 64)];
        $this->postJson(route('media-resmi-humas.store'), $data)->assertOk()->assertJsonStructure(['redirect', 'pesan']);
        $media = MediaResmiHumas::firstOrFail();
        $this->assertSame($humas->id, $media->dibuat_oleh_pengguna_id);
        $this->assertSame($humas->id, $media->diubah_oleh_pengguna_id);
        $this->assertSame(0, $media->versi);
        $this->assertNotSame($data['tautan_hash'], $media->tautan_hash);
        $this->assertArrayNotHasKey('token_pembuatan', $media->toArray());
        $this->assertArrayNotHasKey('tautan_hash', $media->toArray());
        $this->postJson(route('media-resmi-humas.store'), $data)->assertOk();
        $this->assertDatabaseCount('media_resmi_humas', 1);
        $this->assertDatabaseCount('riwayat_media_resmi_humas', 1);
        $this->actingAs($this->akun())->postJson(route('media-resmi-humas.store'), $data)->assertForbidden();
        $this->assertDatabaseCount('media_resmi_humas', 1);
    }

    public function test_alamat_ganda_ditolak_termasuk_huruf_besar_host_dan_garis_akhir(): void
    {
        $this->actingAs($this->akun());
        $awal = $this->buat(['tautan' => 'https://sekolah.test']);
        foreach (['https://sekolah.test', 'https://SEKOLAH.test/', 'https://sekolah.test/#halaman'] as $tautan) {
            $this->postJson(route('media-resmi-humas.store'), array_replace($this->data(), ['tautan' => $tautan]))->assertUnprocessable()->assertJsonValidationErrors('tautan');
        }
        $lain = $this->buat(['tautan' => 'https://instagram.com/sekolah', 'jenis' => 'instagram']);
        $this->putJson(route('media-resmi-humas.update', $lain), $this->editData($lain, ['tautan' => $awal->tautan]))->assertJsonValidationErrors('tautan');
        $this->assertSame('https://instagram.com/sekolah', $lain->fresh()->tautan);
        $this->assertDatabaseCount('media_resmi_humas', 2);
        $this->assertDatabaseCount('riwayat_media_resmi_humas', 2);
    }

    public function test_kredensial_ditolak_dan_tidak_diflash_atau_disimpan(): void
    {
        $this->actingAs($this->akun());
        foreach (['password', 'kata_sandi', 'token_akses', 'access_token', 'refresh_token', 'api_key', 'secret', 'client_secret'] as $kolom) {
            $this->post(route('media-resmi-humas.store'), $this->data() + [$kolom => 'RahasiaTidakBolehTersimpan'])->assertSessionHasErrors('kredensial')->assertSessionMissing('_old_input.'.$kolom);
            $this->assertDatabaseCount('media_resmi_humas', 0);
            $this->assertFalse(Schema::hasColumn('media_resmi_humas', $kolom));
        }
        $this->post(route('media-resmi-humas.store', ['api_key' => 'RahasiaTidakBolehTersimpan']), $this->data())->assertSessionHasErrors('kredensial')->assertSessionMissing('_old_input.api_key');
        $this->assertDatabaseCount('riwayat_media_resmi_humas', 0);
        $this->post(route('media-resmi-humas.store'), $this->data() + ['credentials' => ['password' => 'RahasiaTidakBolehTersimpan']])->assertRedirect()->assertSessionHasNoErrors();
        $snapshot = MediaResmiHumas::firstOrFail()->riwayat()->firstOrFail()->snapshot;
        $this->assertStringNotContainsString('RahasiaTidakBolehTersimpan', json_encode($snapshot));
        $this->assertArrayNotHasKey('credentials', $snapshot);
    }

    public function test_tautan_berkredensial_dan_skema_berbahaya_ditolak(): void
    {
        $this->actingAs($this->akun());
        foreach (['https://user:RahasiaTidakBolehTersimpan@sekolah.test', 'https://sekolah.test/?access_token=RahasiaTidakBolehTersimpan',
            'https://sekolah.test/#access_token=RahasiaTidakBolehTersimpan', 'https://sekolah.test/?akun[password]=RahasiaTidakBolehTersimpan'] as $url) {
            $this->post(route('media-resmi-humas.store'), array_replace($this->data(), ['tautan' => $url]))->assertSessionHasErrors('tautan');
            $this->assertStringNotContainsString('RahasiaTidakBolehTersimpan', json_encode(session('_old_input')));
        }
        foreach (['javascript:alert(1)', 'file:///C:/Windows', 'data:text/html,test', 'ftp://sekolah.test', ['https://sekolah.test']] as $url) {
            $this->postJson(route('media-resmi-humas.store'), array_replace($this->data(), ['tautan' => $url]))->assertUnprocessable()->assertJsonValidationErrors('tautan');
        }
        $this->assertDatabaseCount('media_resmi_humas', 0);
    }

    public function test_pergantian_penanggung_jawab_dan_tautan_menyimpan_riwayat(): void
    {
        $humas = $this->akun();
        $this->actingAs($humas);
        $media = $this->buat();
        $token = $media->token_pembuatan;
        $data = $this->editData($media, ['penanggung_jawab' => 'Petugas baru', 'jabatan_penanggung_jawab' => 'Tim publikasi', 'tautan' => 'https://sekolah.test/baru',
            'token_pembuatan' => (string) Str::uuid(), 'dibuat_oleh_pengguna_id' => 999, 'tautan_hash' => str_repeat('a', 64)]);
        $this->putJson(route('media-resmi-humas.update', $media), $data)->assertOk();
        $baru = $media->fresh();
        $this->assertSame(1, $baru->versi);
        $this->assertSame($token, $baru->token_pembuatan);
        $this->assertSame($humas->id, $baru->dibuat_oleh_pengguna_id);
        $this->assertSame('Petugas baru', $baru->penanggung_jawab);
        $this->assertSame('Petugas Humas', $media->riwayat()->where('versi', 0)->first()->snapshot['penanggung_jawab']);
        $this->assertSame('Petugas baru', $media->riwayat()->where('versi', 1)->first()->snapshot['penanggung_jawab']);
        $this->assertSame('Pergantian pengelola media sekolah.', $media->riwayat()->where('versi', 1)->first()->catatan_perubahan);
        $this->get(route('media-resmi-humas.show', $media))->assertOk()->assertSee('Petugas baru')->assertSee('Petugas Humas');
        $this->putJson(route('media-resmi-humas.update', $media), $this->editData($baru))->assertOk();
        $this->assertDatabaseCount('riwayat_media_resmi_humas', 2);
        $this->assertSame(1, $media->fresh()->versi);
    }

    public function test_versi_usang_dan_alasan_perubahan_kosong_ditolak(): void
    {
        $this->actingAs($this->akun());
        $media = $this->buat();
        $data = $this->editData($media, ['status' => 'nonaktif']);
        $this->putJson(route('media-resmi-humas.update', $media), array_replace($data, ['catatan_perubahan' => '']))->assertJsonValidationErrors('catatan_perubahan');
        $this->putJson(route('media-resmi-humas.update', $media), $data)->assertOk();
        $this->putJson(route('media-resmi-humas.update', $media), array_replace($data, ['status' => 'arsip']))->assertJsonValidationErrors('versi');
        $this->assertSame('nonaktif', $media->fresh()->status);
        $this->assertDatabaseCount('riwayat_media_resmi_humas', 2);
    }

    public function test_validasi_penanggung_jawab_status_jenis_dan_tanggal(): void
    {
        $this->actingAs($this->akun());
        foreach (['penanggung_jawab' => '', 'jenis' => 'palsu', 'status' => 'palsu', 'tanggal_diperiksa' => '2026-10-06', 'nama' => str_repeat('a', 181), 'catatan' => str_repeat('a', 2001)] as $field => $value) {
            $this->postJson(route('media-resmi-humas.store'), array_replace($this->data(), [$field => $value]))->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('media_resmi_humas', 0);
        $media = $this->buat(['tanggal_diperiksa' => '2026-10-05']);
        $this->assertSame('05-10-2026', $media->snapshot()['tanggal_diperiksa']);
    }

    public function test_filter_status_jenis_petugas_pencarian_statistik_dan_paginasi(): void
    {
        $this->actingAs($this->akun());
        $this->fixture(['nama' => 'Instagram sekolah', 'jenis' => 'instagram', 'tautan' => 'https://instagram.com/sekolah', 'identitas_akun' => '@smpn2', 'penanggung_jawab' => 'Guru Media']);
        $this->fixture(['nama' => 'YouTube tidak aktif', 'jenis' => 'youtube', 'tautan' => 'https://youtube.com/@sekolah', 'status' => 'nonaktif']);
        $this->fixture(['nama' => 'Website lama diarsipkan', 'tautan' => 'https://lama.test', 'status' => 'arsip']);
        for ($i = 0; $i < 22; $i++) {
            $this->fixture(['nama' => 'Website program '.$i, 'tautan' => 'https://program-'.$i.'.test']);
        }
        $r = $this->get(route('media-resmi-humas.index'))->assertOk()->assertDontSee('YouTube tidak aktif')->assertDontSee('Website lama diarsipkan');
        $this->assertCount(20, $r->viewData('daftar'));
        $this->assertSame(23, (int) $r->viewData('statistik')['aktif']);
        $this->assertSame(2, (int) $r->viewData('statistik')['penanggung_jawab']);
        $this->get(route('media-resmi-humas.index', ['jenis' => 'instagram', 'penanggung_jawab' => 'Guru Media', 'kata_kunci' => 'smpn2']))->assertOk()->assertSee('Instagram sekolah')->assertDontSee('Website program');
        $this->get(route('media-resmi-humas.index', ['status' => 'nonaktif']))->assertOk()->assertSee('YouTube tidak aktif')->assertDontSee('Instagram sekolah');
        $this->get(route('media-resmi-humas.index', ['status' => 'arsip']))->assertOk()->assertSee('Website lama diarsipkan');
        $this->assertSame(25, $this->get(route('media-resmi-humas.index', ['status' => 'semua']))->viewData('daftar')->total());
        $this->get(route('media-resmi-humas.index', ['status' => 'palsu']))->assertSessionHasErrors('status');
    }

    public function test_escape_html_dan_tampilan_di_semua_status(): void
    {
        $this->actingAs($this->akun());
        $this->capture('form', $this->get(route('media-resmi-humas.create'))->assertOk()->assertDontSee('type="password"', false));
        $media = $this->buat(['nama' => 'Website resmi SMP Negeri 2 Padang Panjang untuk informasi kegiatan pendidikan dan layanan sekolah', 'catatan' => 'Publikasi kegiatan dan pengumuman sekolah.']);
        $this->capture('show', $this->get(route('media-resmi-humas.show', $media))->assertOk());
        $this->putJson(route('media-resmi-humas.update', $media), $this->editData($media, ['penanggung_jawab' => 'Tim Humas dan Dokumentasi Sekolah', 'jabatan_penanggung_jawab' => 'Penanggung jawab publikasi dan dokumentasi kegiatan']))->assertOk();
        $this->capture('history', $this->get(route('media-resmi-humas.show', $media))->assertOk());
        $this->capture('edit', $this->get(route('media-resmi-humas.edit', $media))->assertOk());
        $this->fixture(['nama' => 'Instagram sekolah', 'jenis' => 'instagram', 'tautan' => 'https://instagram.com/smpn2padangpanjang', 'identitas_akun' => '@smpn2padangpanjang']);
        $this->fixture(['nama' => 'YouTube dokumentasi kegiatan sekolah', 'jenis' => 'youtube', 'tautan' => 'https://youtube.com/@smpn2padangpanjang', 'penanggung_jawab' => 'Tim Dokumentasi']);
        $this->fixture(['nama' => 'Facebook sekolah (tidak aktif)', 'jenis' => 'facebook', 'tautan' => 'https://facebook.com/sekolah', 'status' => 'nonaktif']);
        $this->fixture(['nama' => 'Website lama', 'tautan' => 'https://lama.test', 'status' => 'arsip']);
        $this->capture('index', $this->get(route('media-resmi-humas.index'))->assertOk());
        $this->capture('all', $this->get(route('media-resmi-humas.index', ['status' => 'semua']))->assertOk());
        $this->capture('empty', $this->get(route('media-resmi-humas.index', ['kata_kunci' => 'tidak ditemukan']))->assertOk());
        $this->actingAs($this->akun('pimpinan'));
        $this->capture('readonly', $this->get(route('media-resmi-humas.show', $media))->assertOk()->assertDontSee('Edit media'));
        $html = '</textarea><script>alert(1)</script>';
        $media->update(['nama' => $html, 'penanggung_jawab' => $html, 'catatan' => $html]);
        $this->get(route('media-resmi-humas.show', $media))->assertOk()->assertDontSee($html, false)->assertSee(e($html), false);
        $this->actingAs($this->akun())->get(route('media-resmi-humas.edit', $media))->assertOk()->assertDontSee($html, false);
    }

    private function akun(string $role = 'wakil_pimpinan_humas'): Pengguna
    {
        $p = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'media.'.Str::uuid(), 'kata_sandi' => 'UjiMedia123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function akunIzin(array $izin): Pengguna
    {
        $role = Peran::create(['kode' => 'media_'.Str::random(12), 'nama' => 'Media terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $p = $this->akun('pegawai');
        $p->daftarPeran()->attach($role);

        return $p;
    }

    private function data(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'nama' => 'Website resmi sekolah', 'jenis' => 'website', 'tautan' => 'https://sekolah.test',
            'penanggung_jawab' => 'Petugas Humas', 'jabatan_penanggung_jawab' => 'Wakil bidang Humas', 'status' => 'aktif', 'tanggal_diperiksa' => '2026-10-04'];
    }

    private function buat(array $data = []): MediaResmiHumas
    {
        $this->postJson(route('media-resmi-humas.store'), array_replace($this->data(), $data))->assertOk();

        return MediaResmiHumas::latest('id')->firstOrFail();
    }

    private function fixture(array $data = []): MediaResmiHumas
    {
        $media = new MediaResmiHumas(array_replace($this->data(), $data));
        $media->forceFill(['tautan_hash' => hash('sha256', $media->tautan)])->save();

        return $media;
    }

    private function editData(MediaResmiHumas $media, array $data = []): array
    {
        return array_replace($media->only(MediaResmiHumas::KOLOM), ['tanggal_diperiksa' => $media->tanggal_diperiksa?->format('Y-m-d'),
            'versi' => $media->versi, 'catatan_perubahan' => 'Pergantian pengelola media sekolah.'], $data);
    }

    private function capture(string $name, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_MEDIA_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/media-resmi-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$name.'.html', $response->getContent());
    }
}
