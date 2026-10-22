<?php

namespace Tests\Feature\Api;

use App\Http\Resources\Api\V1\PenggunaResource;
use App\Models\AgendaHumas;
use App\Models\Izin;
use App\Models\KontakHumas;
use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiHumas;
use App\Models\Siswa;
use App\Services\Mobile\TujuanNotifikasiMobileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HumasNativeTahapSatuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Queue::fake();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
    }

    public function test_menu_endpoint_dan_izin_kontak_publikasi_selaras_pada_semua_role(): void
    {
        foreach (Peran::where('aktif', true)->pluck('kode') as $role) {
            $u = $this->akun($role);
            $this->token($u);
            $codes = collect($this->getJson(route('api.v1.menu'))->assertOk()->json('data.kelompok'))->flatMap(fn ($g) => array_column($g['items'], 'kode'))->all();
            foreach (['kontak' => KontakHumas::IZIN, 'publikasi' => PublikasiHumas::IZIN_AKSES] as $path => $izin) {
                $allowed = $u->memilikiIzin($izin);
                $this->assertSame($allowed, in_array($path.'-humas', $codes, true), $role.'/'.$path);
                $this->getJson(route('api.v1.humas.'.$path.'.index'))->assertStatus($allowed ? 200 : 403);
                $this->getJson(route('api.v1.humas.'.$path.'.referensi'))->assertStatus($allowed ? 200 : 403);
            }
        }
        foreach (['siswa', 'orang_tua'] as $family) {
            $u = $this->akun('administrator');
            $u->update(['pegawai_id' => null]);
            if ($family === 'siswa') {
                $u->update(['siswa_id' => Siswa::create(['nama_lengkap' => 'Siswa Uji', 'aktif' => true])->id]);
            } else {
                OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => 'Wali Uji']);
            }
            $this->token($u->fresh());
            $this->getJson(route('api.v1.menu'))->assertOk()->assertJsonMissing(['kode' => 'kontak-humas'])->assertJsonMissing(['kode' => 'publikasi-humas']);
            $this->getJson(route('api.v1.humas.kontak.index'))->assertForbidden();
            $this->getJson(route('api.v1.humas.publikasi.index'))->assertForbidden();
        }
    }

    public function test_riwayat_kontak_dibatasi_dan_hanya_memuat_nama_kolom_bukan_data_privat(): void
    {
        $u = $this->akun('wakil_pimpinan_humas');
        $this->token($u);
        $input = $this->kontakInput();
        $id = $this->postJson(route('api.v1.humas.kontak.store'), $input)->assertCreated()->assertJsonPath('data.hak_akses.dapat_kelola', true)->json('data.id');
        for ($v = 0; $v < 3; $v++) {
            $this->patchJson(route('api.v1.humas.kontak.update', $id), array_diff_key($input, ['token_pembuatan' => true]) + ['versi' => $v, 'alasan' => 'Pemeriksaan data kontak'])->assertOk();
        }
        $this->getJson(route('api.v1.humas.kontak.riwayat', [$id, 'halaman' => 2, 'per_halaman' => 2]))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.paginasi.halaman', 2)->assertJsonCount(2, 'data.items')->assertDontSee('081234567890')->assertDontSee('privat@example.test');
        $this->getJson(route('api.v1.humas.kontak.riwayat', [$id, 'per_halaman' => 51]))->assertUnprocessable();
        $this->token($this->akun('pimpinan'));
        $this->getJson(route('api.v1.humas.kontak.show', $id))->assertOk()->assertJsonPath('data.hak_akses.dapat_lihat_kontak', false)->assertDontSee('081234567890');
        $this->getJson(route('api.v1.humas.kontak.riwayat', $id))->assertForbidden();
        $r = Peran::create(['kode' => 'uji_hanya_kontak', 'nama' => 'Baca kontak', 'aktif' => true]);
        $r->izin()->attach(Izin::where('kode', 'kontak_humas.kontak')->value('id'));
        $reader = $this->akun('pegawai');
        $reader->daftarPeran()->sync([$r->id]);
        $this->token($reader);
        $this->getJson(route('api.v1.humas.kontak.show', $id))->assertOk()->assertJsonPath('data.nomor_kontak', '081234567890')->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->getJson(route('api.v1.humas.kontak.riwayat', $id))->assertOk();
        $this->patchJson(route('api.v1.humas.kontak.update', $id), [])->assertForbidden();
    }

    public function test_referensi_publikasi_menjaga_agenda_terpilih_dan_kontributor_tidak_bocor_data_lain(): void
    {
        $owner = $this->akun('wakil_pimpinan_humas');
        $old = $this->agenda('Agenda lama', now()->subYears(2));
        for ($i = 0; $i < 102; $i++) {
            $this->agenda('Agenda baru '.$i, now()->addDays($i));
        }
        $this->token($owner);
        $id = $this->postJson(route('api.v1.humas.publikasi.store'), $this->publikasiInput() + ['agenda_humas_id' => $old->id])->assertCreated()->json('data.id');
        $ref = $this->getJson(route('api.v1.humas.publikasi.referensi', ['publikasi_id' => $id]))->assertOk()->assertJsonPath('data.dapat_pilih_agenda', true)->assertJsonPath('data.batas_foto.jumlah', 5);
        $this->assertContains($old->id, array_column($ref->json('data.agenda'), 'id'));
        $this->getJson(route('api.v1.humas.publikasi.referensi', ['publikasi_id' => $id, 'cari_agenda' => 'TIDAK COCOK']))->assertOk()->assertJsonCount(1, 'data.agenda')->assertJsonPath('data.agenda.0.id', $old->id);
        $this->token($this->akun('kontributor_publikasi'));
        $this->getJson(route('api.v1.humas.publikasi.referensi'))->assertOk()->assertJsonPath('data.dapat_buat', true)->assertJsonPath('data.dapat_pilih_agenda', false)->assertJsonPath('data.agenda', []);
        foreach (['show', 'riwayat', 'naskah'] as $action) {
            $this->getJson(route('api.v1.humas.publikasi.'.$action, $id))->assertNotFound();
        }
        $this->getJson(route('api.v1.humas.publikasi.referensi', ['publikasi_id' => $id]))->assertNotFound();
    }

    public function test_alur_multipart_foto_pemeriksaan_riwayat_dan_bukti_tayang_native(): void
    {
        $humas = $this->akun('wakil_pimpinan_humas');
        $pimpinan = $this->akun('pimpinan');
        $author = $this->akun('kontributor_publikasi');
        $this->token($author);
        $input = $this->publikasiInput();
        $p = $this->post(route('api.v1.humas.publikasi.store'), $input + ['foto' => [$this->foto()]], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.lampiran.0.tipe_file', 'image/jpeg')->json('data');
        $id = $p['id'];
        $photo = $p['lampiran'][0]['id'];
        $this->getJson(route('api.v1.humas.publikasi.berkas', [$id, $photo]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->post(route('api.v1.humas.publikasi.update', $id), array_replace($input, ['_method' => 'PATCH', 'versi' => 0, 'judul' => 'Naskah diperbarui', 'hapus_foto' => [$photo], 'foto' => [$this->foto()]]), ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.versi', 1)->assertJsonCount(1, 'data.lampiran');
        $this->postJson(route('api.v1.humas.publikasi.tindakan', [$id, 'ajukan']), ['versi' => 1])->assertOk()->assertJsonPath('data.hak_akses.dapat_edit', false);
        $n = NotifikasiPengguna::where('pengguna_id', $pimpinan->id)->firstOrFail();
        $this->assertSame('/humas/publikasi/'.$id, app(TujuanNotifikasiMobileService::class)->untuk($n));
        $this->token($pimpinan);
        $this->postJson(route('api.v1.humas.publikasi.tindakan', [$id, 'setujui']), ['versi' => 2])->assertOk()->assertJsonPath('data.status', 'disetujui');
        $this->token($humas);
        $this->getJson(route('api.v1.humas.publikasi.show', $id))->assertOk()->assertJsonPath('data.hak_akses.dapat_buka_revisi', true)->assertJsonPath('data.hak_akses.dapat_tayang', true);
        $this->post(route('api.v1.humas.publikasi.tindakan', [$id, 'tayang']), ['versi' => 3, 'url_tayang' => 'https://example.test/berita', 'waktu_tayang' => now()->format('Y-m-d\TH:i'), 'bukti' => UploadedFile::fake()->createWithContent('bukti.pdf', '%PDF-1.4 bukti uji')], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.status', 'tayang')->assertJsonPath('data.hak_akses.dapat_edit', false)->assertJsonCount(2, 'data.lampiran');
        $history = $this->getJson(route('api.v1.humas.publikasi.riwayat', [$id, 'per_halaman' => 2, 'halaman' => 2]))->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.paginasi.halaman', 2)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertArrayNotHasKey('snapshot', $history->json('data.items.0'));
        $history->assertDontSee('lokasi_file')->assertDontSee('token_pembuatan');
        $this->token($this->akun('kontributor_publikasi'));
        $this->getJson(route('api.v1.humas.publikasi.berkas', [$id, $photo]))->assertNotFound();
    }

    public function test_fixture_kontrak_native_berasal_dari_backend(): void
    {
        $fixtures = [];
        $u = $this->akun('wakil_pimpinan_humas');
        $this->token($u);
        $contact = $this->postJson(route('api.v1.humas.kontak.store'), $this->kontakInput())->assertCreated()->json('data.id');
        $publication = $this->postJson(route('api.v1.humas.publikasi.store'), $this->publikasiInput())->assertCreated()->json('data.id');
        foreach (['humas' => $u, 'pimpinan' => $this->akun('pimpinan'), 'kontributor' => $this->akun('kontributor_publikasi')] as $name => $user) {
            $this->token($user);
            if ($name === 'kontributor') {
                $publication = $this->postJson(route('api.v1.humas.publikasi.store'), $this->publikasiInput())->assertCreated()->json('data.id');
            }
            $data = ['user' => (new PenggunaResource($user->fresh()))->resolve(request()), 'menu' => $this->getJson(route('api.v1.menu'))->assertOk()->json('data')];
            foreach (['kontak' => $contact, 'publikasi' => $publication] as $module => $id) {
                if ($name === 'kontributor' && $module === 'kontak') {
                    continue;
                }
                $data[$module] = $this->getJson(route('api.v1.humas.'.$module.'.show', $id))->assertOk()->json('data');
                $data[$module.'_referensi'] = $this->getJson(route('api.v1.humas.'.$module.'.referensi'))->assertOk()->json('data');
            }
            $fixtures[$name] = $data;
        }
        if (getenv('NUSA_CAPTURE_HUMAS_MOBILE') === '1') {
            File::put(base_path('mobile/test/fixtures/humas_native_tahap_satu.json'), json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }

    private function akun(string $role): Pengguna
    {
        $u = Pengguna::create(['nama' => 'Uji '.$role, 'username' => 'native.'.Str::uuid(), 'kata_sandi' => 'Humas2026!', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false,
            'pegawai_id' => Pegawai::create(['nama_lengkap' => 'Uji '.$role, 'aktif' => true])->id]);
        $u->daftarPeran()->sync([Peran::where('kode', $role)->value('id')]);

        return $u->fresh();
    }

    private function token(Pengguna $u): void
    {
        auth()->forgetGuards();
        $this->withToken($u->createToken('Pengujian native', ['mobile'])->plainTextToken);
    }

    private function kontakInput(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'nama' => 'Narahubung Kemitraan Sekolah', 'instansi' => 'Instansi Mitra', 'jabatan' => 'Koordinator', 'urusan' => 'kedinasan', 'nomor_kontak' => '081234567890', 'email' => 'privat@example.test', 'dasar_penggunaan' => 'Koordinasi resmi program sekolah.', 'status' => 'aktif'];
    }

    private function publikasiInput(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'judul' => 'Kolaborasi Sekolah dan Masyarakat', 'jenis' => 'berita', 'kanal' => 'website', 'isi' => 'Kegiatan pembelajaran bersama masyarakat dan orang tua siswa.'];
    }

    private function agenda(string $title, $time): AgendaHumas
    {
        return AgendaHumas::create(['judul' => $title, 'jenis' => 'orang_tua', 'waktu_mulai' => $time, 'waktu_selesai' => $time->copy()->addHours(2), 'tempat' => 'Aula', 'topik' => 'Koordinasi', 'status' => 'terjadwal']);
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('foto.jpg', file_get_contents(public_path('images/login-sekolah.jpg')));
    }
}
