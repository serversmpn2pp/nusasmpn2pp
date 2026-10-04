<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\Izin;
use App\Models\NotifikasiPengguna;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiHumas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublikasiHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
    }

    public function test_role_humas_pimpinan_dan_menu_dengan_akses_berbeda(): void
    {
        $p = $this->draf();
        $this->get(route('publikasi-humas.index'))->assertRedirect(route('login'));
        foreach (['siswa', 'orang_tua', 'satpam', 'pegawai', 'guru_mapel'] as $role) {
            $this->actingAs($this->akun($role))->get(route('publikasi-humas.index'))->assertForbidden();
            $this->get(route('publikasi-humas.show', $p))->assertForbidden();
            $this->get(route('publikasi-humas.naskah', $p))->assertForbidden();
            $this->post(route('publikasi-humas.store'), $this->data())->assertForbidden();
            $this->aksi($p, 'setujui')->assertForbidden();
        }
        $this->actingAs($this->akun('pimpinan'))->get(route('publikasi-humas.index'))->assertOk()->assertDontSee('Buat draf')->assertSee('Publikasi &amp; Persetujuan', false);
        $this->get(route('publikasi-humas.create'))->assertForbidden();
        $this->get(route('publikasi-humas.edit', $p))->assertForbidden();
        $this->actingAs($this->akun())->get(route('publikasi-humas.index'))->assertOk()->assertSee('Buat draf');
        $this->aksi($p, 'setujui')->assertForbidden();
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail())->get(route('publikasi-humas.index'))->assertOk();
    }

    public function test_draf_idempoten_tidak_menerima_status_dan_aktor_palsu(): void
    {
        $humas = $this->akun();
        $data = $this->data() + ['foto' => [$this->foto()], 'status' => 'tayang', 'versi' => 99, 'url_tayang' => 'https://evil.test', 'dibuat_oleh_pengguna_id' => 999];
        $this->actingAs($humas)->postJson(route('publikasi-humas.store'), $data)->assertOk()->assertJsonStructure(['redirect', 'pesan']);
        $p = PublikasiHumas::firstOrFail();
        $this->assertSame('draf', $p->status);
        $this->assertSame(0, $p->versi);
        $this->assertNull($p->url_tayang);
        $this->assertSame($humas->id, $p->dibuat_oleh_pengguna_id);
        $this->assertArrayNotHasKey('token_pembuatan', $p->toArray());
        $this->postJson(route('publikasi-humas.store'), $data)->assertOk();
        $this->assertDatabaseCount('publikasi_humas', 1);
        $this->assertDatabaseCount('lampiran_publikasi_humas', 1);
        $this->assertSame(1, $p->riwayat()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('publikasi-humas'));
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->actingAs($this->akun())->postJson(route('publikasi-humas.store'), $data)->assertForbidden();
        $this->assertCount(1, Storage::disk('local')->allFiles('publikasi-humas'));
    }

    public function test_update_versi_usang_ditolak_dan_konten_pengajuan_dikunci(): void
    {
        $humas = $this->akun();
        $this->actingAs($humas);
        $p = $this->buat();
        $data = array_replace($this->data(), ['versi' => 0, 'judul' => 'Judul baru']);
        $this->put(route('publikasi-humas.update', $p), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $p->fresh()->versi);
        $this->put(route('publikasi-humas.update', $p), $data + ['foto' => [$this->foto()]])->assertSessionHasErrors('versi');
        $this->assertCount(0, Storage::disk('local')->allFiles('publikasi-humas'));
        $this->aksi($p, 'ajukan')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $p->fresh()->status);
        $this->assertSame($humas->id, $p->fresh()->diajukan_oleh_pengguna_id);
        $this->get(route('publikasi-humas.edit', $p))->assertSessionHasErrors('status');
        $this->put(route('publikasi-humas.update', $p), array_replace($data, ['versi' => 2, 'foto' => [$this->foto()]]))->assertSessionHasErrors('status');
        $this->assertSame('Judul baru', $p->fresh()->judul);
        $this->assertCount(0, Storage::disk('local')->allFiles('publikasi-humas'));
        $this->aksi($p, 'tarik')->assertRedirect();
        $this->assertSame('draf', $p->fresh()->status);
        $this->assertSame(4, $p->riwayat()->count());
    }

    public function test_pemeriksaan_revisi_pengajuan_ulang_dan_snapshot_naskah(): void
    {
        $humas = $this->akun();
        $pimpinan = $this->akun('pimpinan');
        $this->actingAs($humas);
        $p = $this->buat();
        $this->aksi($p, 'ajukan')->assertRedirect();
        $this->actingAs($pimpinan);
        $this->aksi($p, 'minta-revisi')->assertSessionHasErrors('catatan');
        $this->aksi($p, 'minta-revisi', ['catatan' => 'Lengkapi nama narasumber.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('revisi', $p->fresh()->status);
        $this->get(route('publikasi-humas.show', $p))->assertOk()->assertSee('Lengkapi nama narasumber.');
        $this->aksi($p, 'setujui')->assertSessionHasErrors('status');
        $this->actingAs($humas)->put(route('publikasi-humas.update', $p), array_replace($this->data(), ['versi' => $p->fresh()->versi, 'isi' => 'Naskah yang telah dilengkapi dengan nama narasumber.']))->assertRedirect();
        $this->aksi($p, 'ajukan')->assertRedirect();
        $this->assertNull($p->fresh()->catatan_pemeriksaan);
        $this->actingAs($pimpinan);
        $this->aksi($p, 'setujui', ['catatan' => 'Naskah sudah sesuai.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('disetujui', $p->fresh()->status);
        $this->assertSame($pimpinan->id, $p->fresh()->diperiksa_oleh_pengguna_id);
        $this->assertSame($this->data()['isi'], $p->riwayat()->where('aksi', 'Draf dibuat')->firstOrFail()->snapshot['isi']);
        $this->assertSame('Naskah yang telah dilengkapi dengan nama narasumber.', $p->riwayat()->where('aksi', 'Konten disetujui')->firstOrFail()->snapshot['isi']);
        $this->assertSame(6, $p->riwayat()->count());
    }

    public function test_pembuat_dan_pengaju_tidak_dapat_memeriksa_konten_sendiri(): void
    {
        $akun = $this->akunIzin(['publikasi_humas.kelola', 'publikasi_humas.periksa']);
        $this->actingAs($akun);
        $p = $this->buat();
        $this->aksi($p, 'ajukan')->assertRedirect();
        $this->aksi($p, 'setujui')->assertSessionHasErrors('pemeriksaan');
        $this->aksi($p, 'minta-revisi', ['catatan' => 'Revisi sendiri'])->assertSessionHasErrors('pemeriksaan');
        $this->get(route('publikasi-humas.show', $p))->assertOk()->assertDontSee('Setujui konten');
        $lain = $this->akunIzin(['publikasi_humas.kelola', 'publikasi_humas.periksa']);
        $this->aksi($p, 'tarik')->assertRedirect();
        $this->actingAs($lain);
        $this->aksi($p, 'ajukan')->assertRedirect();
        $this->aksi($p, 'setujui')->assertSessionHasErrors('pemeriksaan');
        $this->actingAs($akun);
        $this->aksi($p, 'setujui')->assertSessionHasErrors('pemeriksaan');
        $this->actingAs($this->akun('pimpinan'));
        $this->aksi($p, 'setujui')->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_tayang_hanya_setelah_disetujui_dan_berkas_gagal_tidak_tertinggal(): void
    {
        $humas = $this->akun();
        $this->actingAs($humas);
        $p = $this->buat();
        $tayang = ['url_tayang' => 'https://sekolah.test/berita', 'waktu_tayang' => '2026-10-05T09:00'];
        $this->aksi($p, 'tayang', $tayang + ['bukti' => UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf')])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('lampiran_publikasi_humas', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('publikasi-humas'));
        $this->aksi($p, 'ajukan')->assertRedirect();
        $this->actingAs($this->akun('pimpinan'));
        $this->aksi($p, 'setujui')->assertRedirect();
        $this->actingAs($humas);
        $this->aksi($p, 'tayang', ['url_tayang' => 'javascript:alert(1)', 'waktu_tayang' => '2026-10-06T09:00'])->assertSessionHasErrors(['url_tayang', 'waktu_tayang']);
        $this->aksi($p, 'tayang', $tayang + ['bukti' => $this->foto()])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('tayang', $p->fresh()->status);
        $this->assertSame('2026-10-05 09:00:00', $p->fresh()->waktu_tayang->toDateTimeString());
        $this->assertSame($tayang['url_tayang'], $p->fresh()->url_tayang);
        $this->assertSame(1, $p->lampiran()->where('jenis', 'bukti')->count());
        $this->get(route('publikasi-humas.show', $p))->assertOk()->assertSee('Bukti tayang')->assertDontSee('Edit draf')->assertDontSee('Simpan bukti tayang');
        $this->aksi($p, 'tayang', $tayang)->assertSessionHasErrors('status');
        $this->aksi($p, 'buka-revisi', ['catatan' => 'Mengubah setelah tayang'])->assertSessionHasErrors('status');
    }

    public function test_revisi_setelah_persetujuan_membatalkan_persetujuan_lama(): void
    {
        $humas = $this->akun();
        $this->actingAs($humas);
        $p = $this->buat();
        $this->aksi($p, 'ajukan')->assertRedirect();
        $this->actingAs($this->akun('pimpinan'));
        $this->aksi($p, 'setujui')->assertRedirect();
        $this->actingAs($humas);
        $this->put(route('publikasi-humas.update', $p), array_replace($this->data(), ['versi' => 2]))->assertSessionHasErrors('status');
        $this->aksi($p, 'buka-revisi')->assertSessionHasErrors('catatan');
        $this->aksi($p, 'buka-revisi', ['catatan' => 'Ada perubahan tanggal kegiatan.'])->assertRedirect();
        $this->assertSame('revisi', $p->fresh()->status);
        $this->assertNull($p->fresh()->diperiksa_oleh_pengguna_id);
        $this->assertNull($p->fresh()->diperiksa_pada);
        $this->aksi($p, 'tayang', ['url_tayang' => 'https://sekolah.test', 'waktu_tayang' => '2026-10-05T09:00'])->assertSessionHasErrors('status');
        $this->assertSame(1, $p->riwayat()->where('aksi', 'Konten disetujui')->count());
    }

    public function test_foto_privat_lintas_konten_ditolak_dan_riwayat_foto_lama_tetap_tersedia(): void
    {
        $humas = $this->akun();
        $this->actingAs($humas);
        $p = $this->buat(['foto' => [$this->foto()]]);
        $file = $p->lampiran()->firstOrFail();
        $this->get(route('publikasi-humas.berkas', [$p, $file]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('publikasi-humas.berkas', [$p, $file, 'unduh' => 1]))->assertDownload($file->nama_file_asli);
        $this->get(route('publikasi-humas.berkas', [$this->draf(), $file]))->assertNotFound();
        $this->put(route('publikasi-humas.update', $p), array_replace($this->data(), ['versi' => 0, 'hapus_foto' => [$file->id]]))->assertRedirect();
        $this->assertNotNull($file->fresh()->dihapus_pada);
        $this->assertTrue(Storage::disk('local')->exists($file->lokasi_file));
        $this->assertCount(0, $p->riwayat()->firstOrFail()->snapshot['lampiran']);
        $this->assertCount(1, $p->riwayat()->where('aksi', 'Draf dibuat')->firstOrFail()->snapshot['lampiran']);
        $this->get(route('publikasi-humas.berkas', [$p, $file]))->assertOk();
        $this->put(route('publikasi-humas.update', $p), array_replace($this->data(), ['versi' => 1, 'hapus_foto' => [$file->id]]))->assertSessionHasErrors('hapus_foto');
        $this->actingAs($this->akun('siswa'))->get(route('publikasi-humas.berkas', [$p, $file]))->assertForbidden();
        $this->actingAs($this->akun('pimpinan'))->get(route('publikasi-humas.berkas', [$p, $file]))->assertOk();
        Storage::disk('local')->delete($file->lokasi_file);
        $this->get(route('publikasi-humas.berkas', [$p, $file]))->assertNotFound();
    }

    public function test_validasi_foto_jumlah_gabungan_dan_berkas_hilang(): void
    {
        $this->actingAs($this->akun());
        $this->post(route('publikasi-humas.store'), $this->data() + ['foto' => [UploadedFile::fake()->create('berbahaya.svg', 1, 'image/svg+xml')]])->assertSessionHasErrors('foto.0');
        $this->post(route('publikasi-humas.store'), $this->data() + ['foto' => [$this->foto()->size(2049)]])->assertSessionHasErrors('foto.0');
        $this->post(route('publikasi-humas.store'), $this->data() + ['foto' => array_fill(0, 6, $this->foto())])->assertSessionHasErrors('foto');
        $p = $this->buat(['foto' => array_fill(0, 5, $this->foto())]);
        $this->put(route('publikasi-humas.update', $p), array_replace($this->data(), ['versi' => 0, 'foto' => [$this->foto()]]))->assertSessionHasErrors('foto');
        $this->assertCount(5, Storage::disk('local')->allFiles('publikasi-humas'));
        Storage::disk('local')->delete($p->lampiran()->firstOrFail()->lokasi_file);
        $this->aksi($p, 'ajukan')->assertSessionHasErrors('foto');
        $this->assertSame('draf', $p->fresh()->status);
    }

    public function test_agenda_terkait_dibatasi_izin_dan_dipertahankan_pada_koreksi_terbatas(): void
    {
        $agenda = AgendaHumas::create(['judul' => 'Agenda sekolah', 'jenis' => 'internal', 'waktu_mulai' => '2026-10-04 08:00', 'waktu_selesai' => '2026-10-04 09:00', 'tempat' => 'Aula', 'topik' => 'Agenda kerja sama']);
        $this->actingAs($this->akun());
        $p = $this->buat(['agenda_humas_id' => $agenda->id]);
        $agenda->update(['status' => 'dibatalkan']);
        $this->post(route('publikasi-humas.store'), $this->data() + ['agenda_humas_id' => $agenda->id])->assertSessionHasErrors('agenda_humas_id');
        $limited = $this->akunIzin(['publikasi_humas.kelola']);
        $this->actingAs($limited)->get(route('publikasi-humas.show', $p))->assertOk()->assertDontSee('Agenda sekolah');
        $this->get(route('publikasi-humas.edit', $p))->assertOk()->assertDontSee('Agenda terkait');
        $this->post(route('publikasi-humas.store'), $this->data() + ['agenda_humas_id' => $agenda->id])->assertForbidden();
        $this->put(route('publikasi-humas.update', $p), array_replace($this->data(), ['versi' => 0, 'judul' => 'Koreksi terbatas']))->assertRedirect();
        $this->assertSame($agenda->id, $p->fresh()->agenda_humas_id);
        $this->put(route('publikasi-humas.update', $p), array_replace($this->data(), ['versi' => 1, 'agenda_humas_id' => null]))->assertForbidden();
    }

    public function test_notifikasi_pengajuan_ke_pemeriksa_dan_keputusan_ke_humas_tidak_ganda(): void
    {
        $humas = $this->akun();
        $pimpinan = $this->akun('pimpinan');
        $this->actingAs($humas);
        $p = $this->buat();
        $this->aksi($p, 'ajukan')->assertRedirect();
        $this->assertSame(1, NotifikasiPengguna::where('pengguna_id', $pimpinan->id)->count());
        $this->assertSame(0, NotifikasiPengguna::where('pengguna_id', $humas->id)->count());
        $this->aksi($p, 'ajukan', ['versi' => 0])->assertSessionHasErrors('versi');
        $this->assertSame(1, NotifikasiPengguna::where('pengguna_id', $pimpinan->id)->count());
        $this->actingAs($pimpinan);
        $this->aksi($p, 'setujui')->assertRedirect();
        $this->assertSame(1, NotifikasiPengguna::where('pengguna_id', $humas->id)->count());
        $this->aksi($p, 'setujui', ['versi' => 1])->assertSessionHasErrors('versi');
        $this->assertSame(1, NotifikasiPengguna::where('pengguna_id', $humas->id)->count());
    }

    public function test_filter_paginasi_xss_dan_semua_status_render(): void
    {
        $humas = $this->akun();
        $pimpinan = $this->akun('pimpinan');
        $this->actingAs($humas);
        $this->capture('form', $this->get(route('publikasi-humas.create'))->assertOk());
        $p = $this->buat(['foto' => [$this->foto()], 'judul' => 'SMP Negeri 2 Padang Panjang melaksanakan kegiatan bersama masyarakat dan mitra pendidikan']);
        $this->capture('draft', $this->get(route('publikasi-humas.show', $p))->assertOk());
        $this->capture('edit', $this->get(route('publikasi-humas.edit', $p))->assertOk());
        $this->aksi($p, 'ajukan')->assertRedirect();
        $this->capture('pending', $this->get(route('publikasi-humas.show', $p))->assertOk());
        $this->actingAs($pimpinan);
        $this->capture('review', $this->get(route('publikasi-humas.show', $p))->assertOk());
        $this->aksi($p, 'minta-revisi', ['catatan' => 'Lengkapi informasi kegiatan dan nama narasumber sebelum publikasi.'])->assertRedirect();
        $this->actingAs($humas);
        $this->capture('revision', $this->get(route('publikasi-humas.show', $p))->assertOk());
        $this->aksi($p, 'ajukan')->assertRedirect();
        $this->actingAs($pimpinan);
        $this->aksi($p, 'setujui')->assertRedirect();
        $this->actingAs($humas);
        $this->capture('approved', $this->get(route('publikasi-humas.show', $p))->assertOk());
        $this->aksi($p, 'tayang', ['url_tayang' => 'https://sekolah.test/berita-kegiatan', 'waktu_tayang' => '2026-10-05T09:00', 'bukti' => UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf')])->assertRedirect();
        $this->capture('published', $this->get(route('publikasi-humas.show', $p))->assertOk());
        for ($i = 0; $i < 23; $i++) {
            $this->draf(['judul' => 'Program publikasi sekolah nomor '.$i]);
        }
        $this->capture('index', $this->get(route('publikasi-humas.index'))->assertOk());
        $this->capture('filtered', $this->get(route('publikasi-humas.index', ['status' => 'tayang']))->assertOk());
        $this->assertCount(20, $this->get(route('publikasi-humas.index'))->viewData('daftar'));
        $this->get(route('publikasi-humas.index', ['status' => 'tayang', 'jenis' => 'berita', 'kanal' => 'website']))->assertOk()->assertSee($p->judul)->assertDontSee('Program publikasi sekolah nomor');
        $this->get(route('publikasi-humas.index', ['status' => 'palsu']))->assertSessionHasErrors('status');
        $html = '</textarea><script>alert(1)</script>';
        $p->update(['judul' => $html, 'isi' => $html]);
        $this->get(route('publikasi-humas.show', $p))->assertOk()->assertDontSee($html, false)->assertSee(e($html), false);
        $this->get(route('publikasi-humas.naskah', $p))->assertOk()->assertDownload('naskah-publikasi-'.$p->id.'.txt');
    }

    private function akun(string $role = 'wakil_pimpinan_humas'): Pengguna
    {
        $p = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'publikasi.'.Str::uuid(), 'kata_sandi' => 'UjiPublikasi123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function akunIzin(array $izin): Pengguna
    {
        $role = Peran::create(['kode' => 'pub_'.Str::random(12), 'nama' => 'Publikasi terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $p = $this->akun('pegawai');
        $p->daftarPeran()->attach($role);

        return $p;
    }

    private function data(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'judul' => 'Kegiatan sekolah bersama masyarakat', 'jenis' => 'berita', 'isi' => trim(str_repeat('Kegiatan sekolah dilaksanakan bersama masyarakat untuk mendukung pembelajaran siswa. ', 8)), 'kanal' => 'website', 'rencana_tayang' => '2026-10-06'];
    }

    private function buat(array $data = []): PublikasiHumas
    {
        $this->post(route('publikasi-humas.store'), array_replace($this->data(), $data))->assertRedirect()->assertSessionHasNoErrors();

        return PublikasiHumas::latest('id')->firstOrFail();
    }

    private function draf(array $data = []): PublikasiHumas
    {
        return PublikasiHumas::create(array_replace($this->data(), $data));
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('foto-kegiatan.jpg', file_get_contents(public_path('images/login-sekolah.jpg')));
    }

    private function aksi(PublikasiHumas $p, string $aksi, array $data = []): TestResponse
    {
        $route = in_array($aksi, ['setujui', 'minta-revisi']) ? 'publikasi-humas.pemeriksaan' : 'publikasi-humas.tindakan';

        return $this->post(route($route, [$p, $aksi]), array_replace(['versi' => $p->fresh()->versi], $data));
    }

    private function capture(string $nama, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_PUBLIKASI_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/publikasi-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$nama.'.html', $response->getContent());
    }
}
