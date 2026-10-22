<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\DokumenPendukungKemitraan;
use App\Models\Izin;
use App\Models\KerjaSamaHumas;
use App\Models\KontakHumas;
use App\Models\MitraHumas;
use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiHumas;
use App\Services\Humas\NavigasiHumasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperasionalHumasTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $humas;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->humas = $this->akun('wakil_pimpinan_humas');
        $this->actingAs($this->humas);
    }

    public function test_direktori_memisahkan_hak_identitas_dan_kontak_pada_web_api_dan_pencarian(): void
    {
        $input = $this->kontakInput();
        $this->post(route('kontak-humas.store'), $input)->assertSessionHasNoErrors()->assertRedirect();
        $k = KontakHumas::firstOrFail();
        $pembaca = $this->akun('pimpinan');
        $this->actingAs($pembaca)->get(route('kontak-humas.index'))->assertOk()->assertSee($k->nama)->assertDontSee('081234567890')->assertDontSee('privat@example.test');
        $this->get(route('kontak-humas.show', $k))->assertOk()->assertDontSee('081234567890')->assertDontSee('SUMBER KONTAK PRIVAT');
        $this->get(route('kontak-humas.edit', $k))->assertForbidden();
        $this->token($pembaca);
        $this->getJson(route('api.v1.humas.kontak.show', $k))->assertOk()->assertJsonPath('data.nomor_kontak', null)->assertJsonPath('data.email', null)
            ->assertJsonPath('data.kontak_terbatas', true);
        $this->getJson(route('api.v1.humas.kontak.index', ['kata_kunci' => '081234567890']))->assertOk()->assertJsonPath('data.paginasi.total', 0);
        $this->patchJson(route('api.v1.humas.kontak.update', $k), [])->assertForbidden();
        $this->assertArrayNotHasKey('nomor_kontak', $k->toArray());
        $this->token($this->humas);
        $this->getJson(route('api.v1.humas.kontak.show', $k))->assertOk()->assertJsonPath('data.nomor_kontak', '081234567890');
    }

    public function test_kontak_memiliki_versi_retry_idempoten_dan_audit_tanpa_salin_nomor(): void
    {
        $this->token($this->humas);
        $input = $this->kontakInput();
        $this->postJson(route('api.v1.humas.kontak.store'), $input)->assertCreated();
        $this->postJson(route('api.v1.humas.kontak.store'), $input)->assertOk();
        $k = KontakHumas::firstOrFail();
        $ubah = collect($input)->except('token_pembuatan')->all() + ['versi' => 0, 'alasan' => 'Koreksi jabatan narahubung'];
        $ubah['jabatan'] = 'Ketua koordinator';
        $this->patchJson(route('api.v1.humas.kontak.update', $k), $ubah)->assertOk()->assertJsonPath('data.versi', 1);
        $ubah['nama'] = 'JANGAN TERSIMPAN';
        $this->patchJson(route('api.v1.humas.kontak.update', $k), $ubah)->assertUnprocessable()->assertJsonValidationErrors('versi');
        $this->assertNotSame('JANGAN TERSIMPAN', $k->fresh()->nama);
        $this->assertSame(1, KontakHumas::count());
        $this->assertSame(2, DB::table('riwayat_kontak_humas')->count());
        $this->assertStringNotContainsString('081234567890', json_encode(DB::table('riwayat_kontak_humas')->get()));
        $this->token($this->akun('wakil_pimpinan_humas'));
        $this->postJson(route('api.v1.humas.kontak.store'), $input)->assertForbidden();
    }

    public function test_kontributor_hanya_melihat_kirimannya_termasuk_statistik_foto_naskah_dan_api(): void
    {
        $a = $this->akun('kontributor_publikasi');
        $b = $this->akun('kontributor_publikasi');
        $foto = UploadedFile::fake()->createWithContent('foto.jpg', file_get_contents(public_path('images/login-sekolah.jpg')));
        $this->actingAs($a)->post(route('publikasi-humas.store'), $this->publikasiInput() + ['foto' => [$foto]])->assertSessionHasNoErrors()->assertRedirect();
        $p = PublikasiHumas::firstOrFail();
        $this->assertSame($a->id, $p->dibuat_oleh_pengguna_id);
        $this->actingAs($b)->get(route('publikasi-humas.index'))->assertOk()->assertViewHas('daftar', fn ($d) => $d->total() === 0)->assertDontSee($p->judul);
        $this->get(route('publikasi-humas.show', $p))->assertNotFound();
        $this->get(route('publikasi-humas.edit', $p))->assertNotFound();
        $this->get(route('publikasi-humas.naskah', $p))->assertNotFound();
        $this->get(route('publikasi-humas.berkas', [$p, $p->lampiran->first()]))->assertNotFound();
        $this->put(route('publikasi-humas.update', $p), [])->assertNotFound();
        $this->token($b);
        $this->getJson(route('api.v1.humas.publikasi.index'))->assertOk()->assertJsonPath('data.paginasi.total', 0)->assertJsonPath('data.cakupan', 'milik_sendiri');
        $this->getJson(route('api.v1.humas.publikasi.show', $p))->assertNotFound();
        $this->getJson(route('api.v1.humas.publikasi.berkas', [$p, $p->lampiran->first()]))->assertNotFound();
        $this->token($a);
        $this->getJson(route('api.v1.humas.publikasi.show', $p))->assertOk()->assertJsonPath('data.hak_akses.dapat_edit', true)->assertJsonPath('data.hak_akses.dapat_tayang', false);
        $menus = app(NavigasiHumasService::class)->sidebar($a);
        $this->assertSame(['publikasi-humas'], array_column($menus, 'kode'));
        $this->assertFalse($a->memilikiIzin('publikasi_humas.periksa'));
        $this->assertFalse($a->memilikiIzin('publikasi_humas.kelola'));
    }

    public function test_alur_api_kontributor_revisi_notifikasi_dan_larangan_menyetujui_menerbitkan(): void
    {
        $a = $this->akun('kontributor_publikasi');
        $pimpinan = $this->akun('pimpinan');
        $this->token($a);
        $input = $this->publikasiInput();
        $r = $this->postJson(route('api.v1.humas.publikasi.store'), $input)->assertCreated()->assertJsonPath('data.status', 'draf');
        $p = PublikasiHumas::findOrFail($r->json('data.id'));
        $edit = collect($input)->except('token_pembuatan')->all() + ['versi' => 0];
        $edit['judul'] = 'Naskah kontribusi dikoreksi';
        $this->patchJson(route('api.v1.humas.publikasi.update', $p), $edit)->assertOk()->assertJsonPath('data.judul', $edit['judul'])->assertJsonPath('data.versi', 1);
        $this->postJson(route('api.v1.humas.publikasi.tindakan', [$p, 'ajukan']), ['versi' => 1])->assertOk()->assertJsonPath('data.status', 'diajukan')->assertJsonPath('data.versi', 2);
        $this->assertSame(1, NotifikasiPengguna::where('pengguna_id', $pimpinan->id)->count());
        $this->assertSame(1, NotifikasiPengguna::where('pengguna_id', $this->humas->id)->count());
        $this->postJson(route('api.v1.humas.publikasi.tindakan', [$p, 'setujui']), ['versi' => 2])->assertForbidden();
        $this->token($pimpinan);
        $this->postJson(route('api.v1.humas.publikasi.tindakan', [$p, 'minta-revisi']), ['versi' => 2, 'catatan' => 'Lengkapi informasi kegiatan'])->assertOk()->assertJsonPath('data.status', 'revisi');
        $this->assertSame(1, NotifikasiPengguna::where('pengguna_id', $a->id)->count());
        $this->token($a);
        $this->postJson(route('api.v1.humas.publikasi.tindakan', [$p, 'ajukan']), ['versi' => 3])->assertOk();
        $this->token($pimpinan);
        $this->postJson(route('api.v1.humas.publikasi.tindakan', [$p, 'setujui']), ['versi' => 4])->assertOk()->assertJsonPath('data.status', 'disetujui');
        $this->token($a);
        $this->postJson(route('api.v1.humas.publikasi.tindakan', [$p, 'tayang']), ['versi' => 5, 'url_tayang' => 'https://example.test/berita', 'waktu_tayang' => now()->format('Y-m-d\TH:i')])->assertForbidden();
        $this->postJson(route('api.v1.humas.publikasi.tindakan', [$p, 'buka-revisi']), ['versi' => 5, 'catatan' => 'Ingin mengubah konten'])->assertForbidden();
        $this->assertSame('disetujui', $p->fresh()->status);
    }

    public function test_kontributor_pembaca_dan_pemeriksa_tetap_tidak_boleh_memeriksa_konten_sendiri(): void
    {
        $u = $this->akun('kontributor_publikasi');
        $u->daftarPeran()->attach(Peran::where('kode', 'pimpinan')->value('id'));
        $this->actingAs($u)->post(route('publikasi-humas.store'), $this->publikasiInput())->assertRedirect();
        $p = PublikasiHumas::firstOrFail();
        $this->post(route('publikasi-humas.tindakan', [$p, 'ajukan']), ['versi' => 0])->assertRedirect();
        $this->post(route('publikasi-humas.pemeriksaan', [$p, 'setujui']), ['versi' => 1])->assertSessionHasErrors('pemeriksaan');
        $this->assertSame('diajukan', $p->fresh()->status);
    }

    public function test_paket_mengikuti_data_nyata_dan_tidak_membocorkan_lampiran_tanpa_izin(): void
    {
        $a = $this->agenda();
        $d = $this->dokumen();
        $d->update(['judul' => 'DOKUMEN PRIVAT PERTEMUAN', 'jenis_dokumen' => 'undangan']);
        $a->dokumen()->attach($d);
        $this->token($this->humas);
        $this->getJson(route('api.v1.humas.agenda.paket', $a))->assertOk()->assertJsonPath('data.siap_bundel', false)->assertJsonPath('data.komponen.0.status', 'siap');
        $peserta = $a->peserta()->create(['nama' => 'Peserta satu', 'status_kehadiran' => 'belum_dicatat']);
        $a->update(['status' => 'selesai', 'pembahasan' => 'Pembahasan rapat', 'keputusan' => 'Keputusan rapat']);
        $this->getJson(route('api.v1.humas.agenda.paket', $a))->assertOk()->assertJsonPath('data.siap_bundel', false);
        $peserta->update(['status_kehadiran' => 'hadir']);
        $this->getJson(route('api.v1.humas.agenda.paket', $a))->assertOk()->assertJsonPath('data.siap_bundel', true);
        $read = $this->akun(null, ['agenda_humas.lihat']);
        $this->token($read);
        $this->getJson(route('api.v1.humas.agenda.paket', $a))->assertOk()->assertJsonPath('data.lampiran', [])->assertJsonPath('data.komponen.0.status', 'terbatas')->assertDontSee('DOKUMEN PRIVAT PERTEMUAN');
        $this->actingAs($read)->get(route('agenda-humas.paket', $a))->assertOk()->assertDontSee('DOKUMEN PRIVAT PERTEMUAN')->assertDontSee('Unggah undangan');
    }

    public function test_pendukung_mempertahankan_versi_berkas_dan_mou_utama_serta_mencegah_sidik_usang(): void
    {
        $m = $this->mou();
        $d = $this->dokumen();
        $v = $d->riwayat()->firstOrFail();
        $this->token($this->humas);
        $data = ['versi' => 0, 'id_versi' => $v->id, 'jenis' => 'proposal', 'catatan' => 'Proposal pelaksanaan kerja sama'];
        $this->postJson(route('api.v1.humas.kemitraan.pendukung.store', $m), $data)->assertOk()->assertJsonPath('data.versi', 1);
        $this->postJson(route('api.v1.humas.kemitraan.pendukung.store', $m), $data)->assertUnprocessable();
        $l = DokumenPendukungKemitraan::firstOrFail();
        $v2 = $d->riwayat()->create($v->only(['lokasi_file', 'nama_file_asli', 'tipe_file', 'ukuran_file']) + ['versi' => 2, 'diunggah_pada' => now()]);
        $this->getJson(route('api.v1.humas.kemitraan.pendukung.index', $m))->assertOk()->assertJsonPath('data.items.0.versi_berkas', 1);
        $this->postJson(route('api.v1.humas.kemitraan.pendukung.store', $m), array_replace($data, ['versi' => 1]))->assertUnprocessable()->assertJsonValidationErrors('id_versi');
        $lain = $this->mou();
        $this->deleteJson(route('api.v1.humas.kemitraan.pendukung.destroy', [$lain, $l]), ['versi' => 0, 'catatan' => 'Lepas bukti tidak tepat'])->assertNotFound();
        $this->deleteJson(route('api.v1.humas.kemitraan.pendukung.destroy', [$m, $l]), ['versi' => 1, 'catatan' => 'Lepas hubungan proposal'])->assertOk()->assertJsonPath('data.versi', 2);
        $this->assertNotNull($l->fresh()->dilepas_pada);
        $this->assertSame($v->id, $l->fresh()->riwayat_dokumen_humas_id);
        $this->assertNull($m->fresh()->dokumen_humas_id);
        $this->assertSame(2, $d->riwayat()->count());
        Storage::disk('local')->assertExists($v->lokasi_file);
    }

    public function test_pendukung_menolak_kekurangan_izin_dokumen_dan_perjanjian_diakhiri(): void
    {
        $m = $this->mou();
        $u = $this->akun(null, ['kemitraan_humas.kelola']);
        $this->actingAs($u)->get(route('kemitraan-humas.pendukung', $m))->assertForbidden();
        $this->token($u);
        $this->getJson(route('api.v1.humas.kemitraan.pendukung.index', $m))->assertForbidden();
        $d = $this->dokumen();
        $m->update(['status' => 'diakhiri', 'alasan_diakhiri' => 'Kegiatan selesai']);
        $this->token($this->humas);
        $this->postJson(route('api.v1.humas.kemitraan.pendukung.store', $m), ['versi' => 0, 'id_versi' => $d->riwayat()->first()->id, 'jenis' => 'laporan', 'catatan' => 'Laporan akhir kegiatan'])->assertUnprocessable();
        $this->assertSame(0, DokumenPendukungKemitraan::count());
    }

    public function test_berkas_hilang_tidak_ditawarkan_atau_diterima_sebagai_pendukung(): void
    {
        $m = $this->mou();
        $d = $this->dokumen();
        $v = $d->riwayat()->first();
        Storage::disk('local')->delete($v->lokasi_file);
        $this->token($this->humas);
        $this->getJson(route('api.v1.humas.kemitraan.pendukung.pilihan', $m))->assertOk()->assertJsonPath('data.items', []);
        $this->postJson(route('api.v1.humas.kemitraan.pendukung.store', $m), ['versi' => 0, 'id_versi' => $v->id, 'jenis' => 'proposal', 'catatan' => 'Proposal berkas sudah hilang'])->assertUnprocessable();
        $this->assertSame(0, DokumenPendukungKemitraan::count());
        $this->assertSame(0, $m->fresh()->versi);
    }

    public function test_role_humas_yang_salah_dipasang_ke_orang_tua_tetap_ditolak(): void
    {
        $u = $this->akun('wakil_pimpinan_humas');
        $u->update(['pegawai_id' => null]);
        OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => 'Wali uji']);
        $a = $this->agenda();
        $m = $this->mou();
        $this->actingAs($u)->get(route('kontak-humas.index'))->assertForbidden();
        $this->get(route('publikasi-humas.index'))->assertForbidden();
        $this->get(route('agenda-humas.paket', $a))->assertForbidden();
        $this->get(route('kemitraan-humas.pendukung', $m))->assertForbidden();
        $this->token($u);
        $this->getJson(route('api.v1.humas.kontak.index'))->assertForbidden();
        $this->getJson(route('api.v1.humas.publikasi.index'))->assertForbidden();
        $this->assertSame([], app(NavigasiHumasService::class)->sidebar($u));
    }

    public function test_fixture_ui_operasional_humas(): void
    {
        $this->post(route('kontak-humas.store'), $this->kontakInput())->assertRedirect();
        $k = KontakHumas::firstOrFail();
        $a = $this->agenda();
        $m = $this->mou();
        $d = $this->dokumen();
        $this->post(route('kemitraan-humas.pendukung.store', $m), ['versi' => 0, 'id_versi' => $d->riwayat()->first()->id, 'jenis' => 'proposal', 'catatan' => 'Proposal program kegiatan bersama'])->assertSessionHasNoErrors()->assertRedirect();
        session()->forget(['berhasil', 'errors']);
        foreach (['kontak' => route('kontak-humas.index'), 'form' => route('kontak-humas.create'), 'kontak-detail' => route('kontak-humas.show', $k),
            'paket' => route('agenda-humas.paket', $a), 'pendukung' => route('kemitraan-humas.pendukung', $m)] as $nama => $url) {
            $this->capture($nama, $this->get($url)->assertOk()->getContent());
        }
        $this->actingAs($this->akun('pimpinan'));
        $this->capture('kontak-terbatas', $this->get(route('kontak-humas.show', $k))->assertOk()->getContent());
        $this->actingAs($this->akun('kontributor_publikasi'));
        $this->post(route('publikasi-humas.store'), $this->publikasiInput())->assertRedirect();
        $p = PublikasiHumas::firstOrFail();
        $this->capture('kontribusi', $this->get(route('publikasi-humas.index'))->assertOk()->getContent());
        $this->capture('kontribusi-detail', $this->get(route('publikasi-humas.show', $p))->assertOk()->assertSee('Ajukan ke pimpinan')->getContent());
        $this->capture('kontribusi-form', $this->get(route('publikasi-humas.edit', $p))->assertOk()->getContent());
    }

    private function akun(?string $role, ?array $izin = null): Pengguna
    {
        $p = Pegawai::create(['nama_lengkap' => 'Pegawai Humas Uji', 'aktif' => true]);
        $u = Pengguna::create(['nama' => 'Pegawai Humas Uji', 'username' => 'operasional.'.Str::uuid(), 'kata_sandi' => 'UjiHumas2026!', 'peran' => 'pegawai', 'aktif' => true, 'pegawai_id' => $p->id, 'wajib_ganti_kata_sandi' => false]);
        $r = $role ? Peran::where('kode', $role)->firstOrFail() : Peran::create(['kode' => 'uji_'.Str::random(10), 'nama' => 'Akses terbatas', 'aktif' => true]);
        if ($izin) {
            $r->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        }
        $u->daftarPeran()->sync([$r->id]);

        return $u;
    }

    private function kontakInput(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'nama' => 'Narahubung kegiatan sekolah dan koordinasi bersama organisasi orang tua', 'instansi' => 'Instansi mitra sekolah',
            'jabatan' => 'Koordinator kegiatan', 'urusan' => 'kedinasan', 'nomor_kontak' => '081234567890', 'email' => 'privat@example.test',
            'dasar_penggunaan' => 'SUMBER KONTAK PRIVAT dari narahubung untuk koordinasi kegiatan.', 'status' => 'aktif'];
    }

    private function publikasiInput(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'judul' => 'Kolaborasi sekolah bersama orang tua dan masyarakat untuk mendukung pembelajaran', 'jenis' => 'berita',
            'kanal' => 'website', 'isi' => 'Kegiatan kolaborasi dilaksanakan bersama orang tua, guru, dan masyarakat sekolah.'];
    }

    private function agenda(): AgendaHumas
    {
        return AgendaHumas::create(['judul' => 'Pertemuan koordinasi program sekolah bersama orang tua dan komite', 'jenis' => 'orang_tua', 'waktu_mulai' => now(),
            'waktu_selesai' => now()->addHours(2), 'tempat' => 'Aula sekolah', 'topik' => 'Koordinasi program', 'status' => 'terjadwal']);
    }

    private function mou(): KerjaSamaHumas
    {
        $mitra = MitraHumas::create(['nama' => 'Mitra kegiatan sekolah', 'jenis' => 'komunitas', 'status' => 'aktif']);

        return $mitra->kerjaSama()->create(['judul' => 'Kerja sama pengembangan pembelajaran dan kegiatan bersama masyarakat', 'bidang' => 'pendidikan', 'ruang_lingkup' => 'Kegiatan bersama', 'status' => 'draf']);
    }

    private function dokumen(): DokumenHumas
    {
        $d = DokumenHumas::create(['judul' => 'Proposal program kemitraan dan dokumentasi kegiatan sekolah', 'kategori' => 'kemitraan', 'status' => 'aktif', 'lokasi_file' => 'dokumen-humas/'.Str::uuid().'.pdf',
            'nama_file_asli' => 'proposal.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => 20]);
        Storage::disk('local')->put($d->lokasi_file, '%PDF-1.4 berkas pengujian');
        $d->riwayat()->create($d->only(['lokasi_file', 'nama_file_asli', 'tipe_file', 'ukuran_file']) + ['versi' => 1, 'diunggah_pada' => now()]);

        return $d;
    }

    private function token(Pengguna $u): void
    {
        auth()->forgetGuards();
        $this->withToken($u->createToken('Pengujian operasional', ['mobile'])->plainTextToken);
    }

    private function capture(string $nama, string $html): void
    {
        if (getenv('NUSA_CAPTURE_OPERASIONAL_HUMAS') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/operasional-humas');
        File::ensureDirectoryExists($folder);
        File::put($folder.'/'.$nama.'.html', $html);
    }
}
