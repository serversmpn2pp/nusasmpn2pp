<?php

namespace Tests\Feature;

use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PeriodeKomiteHumas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class KomiteHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_akses_role_dan_kontak_pengurus_privat(): void
    {
        $this->get(route('komite-humas.index'))->assertRedirect(route('login'));
        $humas = $this->akun();
        $this->actingAs($humas);
        $p = $this->buatAktif();
        foreach (['siswa', 'orang_tua', 'pegawai', 'guru_mapel', 'satpam'] as $role) {
            $this->actingAs($this->akun($role))->get(route('komite-humas.index'))->assertForbidden();
            $this->get(route('komite-humas.show', $p))->assertForbidden();
            $this->postJson(route('komite-humas.store'), $this->data())->assertForbidden();
            $this->get(route('komite-humas.berkas', [$p, $p->berkas]))->assertForbidden();
        }
        $this->actingAs($this->akun('pimpinan'))->get(route('komite-humas.index'))->assertOk()->assertDontSee('Tambah kepengurusan')->assertDontSee('081234567890');
        $r = $this->get(route('komite-humas.show', $p))->assertOk()->assertSee('Ketua Pertama')->assertSee('Buka SK')->assertDontSee('Edit kepengurusan')->assertDontSee('081234567890')->assertDontSee('Kontak privat');
        $this->assertArrayNotHasKey('nomor_telepon', $r->viewData('pengurus')->first()->getAttributes());
        $this->get(route('komite-humas.edit', $p))->assertForbidden();
        $this->putJson(route('komite-humas.update', $p), $this->editData($p))->assertForbidden();
        $this->getJson(route('komite-humas.dokumen'))->assertForbidden();
        $this->actingAs($humas)->get(route('komite-humas.show', $p))->assertOk()->assertSee('081234567890');
        $this->get(route('komite-humas.index'))->assertOk()->assertDontSee('081234567890');
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail())->get(route('komite-humas.index'))->assertOk();
        $this->delete(route('komite-humas.show', $p))->assertStatus(405);
    }

    public function test_pembuatan_idempoten_aktor_server_dan_kontak_terenkripsi(): void
    {
        $humas = $this->akun();
        $this->actingAs($humas);
        $data = $this->data() + ['dibuat_oleh_pengguna_id' => 999, 'diubah_oleh_pengguna_id' => 999, 'versi' => 99, 'riwayat_dokumen_humas_id' => 999];
        $data['pengurus'][0]['periode_komite_humas_id'] = 999;
        $this->postJson(route('komite-humas.store'), $data)->assertOk();
        $this->postJson(route('komite-humas.store'), $data)->assertOk();
        $p = PeriodeKomiteHumas::firstOrFail();
        $this->assertSame(0, $p->versi);
        $this->assertSame($humas->id, $p->dibuat_oleh_pengguna_id);
        $this->assertNull($p->riwayat_dokumen_humas_id);
        $this->assertSame($p->id, $p->pengurus()->first()->periode_komite_humas_id);
        $this->assertStringNotContainsString('081234567890', DB::table('pengurus_komite_humas')->first()->nomor_telepon);
        $this->assertSame('081234567890', $p->pengurus()->first()->nomor_telepon);
        $this->assertStringNotContainsString('081234567890', $p->pengurus->toJson());
        $this->assertStringNotContainsString('081234567890', $p->riwayat->toJson());
        $this->assertDatabaseCount('periode_komite_humas', 1);
        $this->assertDatabaseCount('pengurus_komite_humas', 3);
        $this->assertDatabaseCount('riwayat_komite_humas', 1);
        $this->actingAs($this->akun())->postJson(route('komite-humas.store'), $data)->assertForbidden();
    }

    public function test_draf_boleh_kosong_tetapi_aktif_memerlukan_sk_dan_ketua(): void
    {
        $this->actingAs($this->akun());
        $draf = $this->buat(['pengurus' => [['nama' => '', 'nomor_telepon' => '', 'jabatan' => 'ketua', 'aktif' => true]]]);
        $this->assertSame(0, $draf->pengurus()->count());
        $this->postJson(route('komite-humas.store'), array_replace($this->data(), ['status' => 'aktif']))->assertJsonValidationErrors('status');
        $this->postJson(route('komite-humas.store'), array_replace($this->aktifData(), ['pengurus' => []]))->assertJsonValidationErrors('pengurus');
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('dokumen_humas', 0);
        $this->assertDatabaseCount('periode_komite_humas', 1);
        $p = $this->buatAktif();
        $this->assertSame('aktif', $p->status);
        $this->assertNotNull($p->riwayat_dokumen_humas_id);
        $this->assertSame('komite', $p->berkas->dokumen->kategori);
        $this->assertSame('2026-07-01', $p->berkas->dokumen->berlaku_mulai->format('Y-m-d'));
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_periode_aktif_tidak_boleh_tumpang_tindih_dan_tanggal_batas_inklusif(): void
    {
        $this->actingAs($this->akun());
        $p = $this->buatAktif(['tanggal_selesai' => '2026-12-31']);
        $this->postJson(route('komite-humas.store'), array_replace($this->aktifData(), ['tanggal_mulai' => '2026-12-31', 'tanggal_selesai' => '2027-12-31']))->assertJsonValidationErrors('tanggal_mulai');
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('dokumen_humas', 1);
        $future = $this->buatAktif(['tanggal_mulai' => '2027-01-01', 'tanggal_selesai' => '2027-12-31']);
        $this->assertSame('belum_mulai', $future->masaBakti());
        $this->putJson(route('komite-humas.update', $future), $this->editData($future, ['tanggal_mulai' => '2026-10-05']))->assertJsonValidationErrors('tanggal_mulai');
        $this->assertSame('2027-01-01', $future->fresh()->tanggal_mulai->format('Y-m-d'));
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['status' => 'arsip']))->assertOk();
        $this->putJson(route('komite-humas.update', $future), $this->editData($future, ['tanggal_mulai' => '2026-10-05']))->assertOk();
        $this->assertSame(3, $this->get(route('komite-humas.index'))->viewData('statistik')['pengurus']);
    }

    public function test_jabatan_inti_tunggal_dan_pergantian_pengurus_menjaga_id_dan_riwayat(): void
    {
        $this->actingAs($this->akun());
        $data = $this->data();
        $data['pengurus'][] = ['nama' => 'Ketua Lain', 'jabatan' => 'ketua', 'aktif' => true];
        $this->postJson(route('komite-humas.store'), $data)->assertJsonValidationErrors('pengurus');
        $p = $this->buatAktif();
        $rows = $this->editData($p)['pengurus'];
        $oldId = $rows[0]['id'];
        $rows[0]['aktif'] = false;
        $rows[] = ['nama' => 'Ketua Pengganti', 'jabatan' => 'ketua', 'aktif' => true];
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['pengurus' => $rows]))->assertOk();
        $this->assertFalse($p->pengurus()->findOrFail($oldId)->aktif);
        $this->assertSame('Ketua Pertama', $p->riwayat()->reorder()->oldest('id')->first()->snapshot['pengurus'][0]['nama']);
        $this->assertTrue($p->riwayat()->reorder()->oldest('id')->first()->snapshot['pengurus'][0]['aktif']);
        $this->assertCount(3, $p->riwayat()->reorder()->oldest('id')->first()->snapshot['pengurus']);
        $this->assertSame(4, $p->pengurus()->count());
        $this->assertSame(1, $p->fresh()->versi);
        $this->get(route('komite-humas.show', $p))->assertOk()->assertSee('Ketua Pengganti')->assertSee('Tidak aktif');
        $this->get(route('komite-humas.index'))->assertOk()->assertSee('Ketua Pengganti')->assertDontSee('Ketua Pertama');
        $this->assertStringNotContainsString('081234567890', $p->riwayat->toJson());
    }

    public function test_id_pengurus_asing_penghapusan_dan_stale_version_ditolak(): void
    {
        $this->actingAs($this->akun());
        $p = $this->buat();
        $asing = $this->buat();
        $rows = $this->editData($p)['pengurus'];
        $rows[0]['id'] = $asing->pengurus()->first()->id;
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['pengurus' => $rows, 'metode' => 'unggah', 'berkas' => $this->foto()]))->assertJsonValidationErrors('pengurus');
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['pengurus' => []]))->assertJsonValidationErrors('pengurus');
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['catatan' => 'Catatan berubah']))->assertOk();
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['versi' => 0, 'metode' => 'unggah', 'berkas' => $this->foto()]))->assertJsonValidationErrors('versi');
        $this->assertSame(1, $p->fresh()->versi);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $this->assertSame(3, $p->pengurus()->count());
    }

    public function test_tanpa_perubahan_tidak_menambah_versi_dan_koreksi_kontak_tetap_tercatat(): void
    {
        $this->actingAs($this->akun());
        $p = $this->buat();
        $this->putJson(route('komite-humas.update', $p), $this->editData($p))->assertOk();
        $this->assertSame(0, $p->fresh()->versi);
        $this->assertSame(1, $p->riwayat()->count());
        $rows = $this->editData($p)['pengurus'];
        $rows[0]['nomor_telepon'] = '082345678901';
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['pengurus' => $rows]))->assertOk();
        $this->assertSame(1, $p->fresh()->versi);
        $this->assertSame(2, $p->riwayat()->count());
        $this->assertStringNotContainsString('082345678901', $p->riwayat->toJson());
    }

    public function test_sk_pengganti_idempoten_dan_sk_lama_tetap_dapat_dibuka(): void
    {
        $this->actingAs($this->akun());
        $data = $this->aktifData();
        $this->postJson(route('komite-humas.store'), $data)->assertOk();
        $this->postJson(route('komite-humas.store'), $data)->assertOk();
        $p = PeriodeKomiteHumas::firstOrFail();
        $old = $p->berkas;
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['metode' => 'unggah', 'berkas' => $this->foto('SK-revisi.jpg')]))->assertOk();
        $p = $p->fresh();
        $this->assertNotSame($old->id, $p->riwayat_dokumen_humas_id);
        $this->get(route('komite-humas.berkas', [$p, $old]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('komite-humas.berkas', [$p, $p->berkas, 'unduh' => 1]))->assertDownload('SK-revisi.jpg');
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['metode' => 'lepas']))->assertJsonValidationErrors('status');
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['metode' => 'lepas', 'status' => 'arsip']))->assertOk();
        $this->assertNull($p->fresh()->riwayat_dokumen_humas_id);
        $this->get(route('komite-humas.berkas', [$p, $old]))->assertOk();
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_dokumen_kategori_komite_saja_dan_versi_sk_dikunci(): void
    {
        $this->actingAs($this->akun());
        $doc = $this->dokumen();
        $other = $this->dokumen('lainnya');
        $other->update(['nomor_dokumen' => 'Cari SK']);
        $this->getJson(route('komite-humas.dokumen'))->assertOk()->assertJsonCount(1, 'dokumen');
        $this->getJson(route('komite-humas.dokumen', ['cari' => 'Cari SK']))->assertOk()->assertJsonCount(0, 'dokumen');
        $this->postJson(route('komite-humas.store'), array_replace($this->data(), ['metode' => 'dokumen', 'dokumen_humas_id' => $other->id]))->assertJsonValidationErrors('dokumen_humas_id');
        $p = $this->buat(['metode' => 'dokumen', 'dokumen_humas_id' => $doc->id]);
        $old = $p->riwayat_dokumen_humas_id;
        $this->versiDokumen($doc, 2);
        $this->assertSame($old, $p->fresh()->riwayat_dokumen_humas_id);
        $this->get(route('komite-humas.berkas', [$p, $doc->riwayat()->first()]))->assertNotFound();
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['metode' => 'dokumen', 'dokumen_humas_id' => $doc->id]))->assertOk();
        $this->assertSame($doc->riwayat()->first()->id, $p->fresh()->riwayat_dokumen_humas_id);
        $doc->update(['status' => 'arsip']);
        $this->postJson(route('komite-humas.store'), array_replace($this->data(), ['metode' => 'dokumen', 'dokumen_humas_id' => $doc->id]))->assertJsonValidationErrors('dokumen_humas_id');
    }

    public function test_izin_dokumen_tidak_dapat_dilewati_dan_berkas_tidak_tersedia_ditolak(): void
    {
        $this->actingAs($this->akun());
        $p = $this->buatAktif();
        $asing = $this->dokumen();
        $this->get(route('komite-humas.berkas', [$p, $asing->riwayat()->first()]))->assertNotFound();
        $this->actingAs($this->akunIzin(['komite_humas.lihat']))->get(route('komite-humas.show', $p))->assertOk()->assertDontSee('SK.jpg')->assertDontSee('081234567890');
        $this->get(route('komite-humas.berkas', [$p, $p->berkas]))->assertForbidden();
        $this->actingAs($this->akunIzin(['komite_humas.kelola']));
        $this->postJson(route('komite-humas.store'), $this->aktifData())->assertForbidden();
        $this->getJson(route('komite-humas.dokumen'))->assertForbidden();
        $this->actingAs($this->akun());
        Storage::disk('local')->delete($p->berkas->lokasi_file);
        $this->get(route('komite-humas.berkas', [$p, $p->berkas]))->assertNotFound();
        $this->putJson(route('komite-humas.update', $p), $this->editData($p))->assertJsonValidationErrors('berkas');
    }

    public function test_validasi_input_berkas_tanggal_dan_batas_pengurus(): void
    {
        $this->actingAs($this->akun());
        foreach ([['nama' => ''], ['tanggal_mulai' => '2026-12-31', 'tanggal_selesai' => '2026-07-01'], ['tanggal_sk' => '2026-10-06'], ['status' => 'tidak_valid'], ['token_pembuatan' => 'abc']] as $data) {
            $this->postJson(route('komite-humas.store'), array_replace($this->data(), $data))->assertUnprocessable();
        }
        $rows = $this->data()['pengurus'];
        $rows[0]['nomor_telepon'] = 'nomor salah';
        $this->postJson(route('komite-humas.store'), array_replace($this->data(), ['pengurus' => $rows]))->assertJsonValidationErrors('pengurus.0.nomor_telepon');
        $this->postJson(route('komite-humas.store'), array_replace($this->data(), ['pengurus' => array_fill(0, 51, ['nama' => 'Anggota', 'jabatan' => 'anggota', 'aktif' => true])]))->assertJsonValidationErrors('pengurus');
        foreach ([UploadedFile::fake()->create('bahaya.html', 1, 'text/html'), $this->foto()->size(20481)] as $file) {
            $this->postJson(route('komite-humas.store'), array_replace($this->data(), ['metode' => 'unggah', 'berkas' => $file]))->assertJsonValidationErrors('berkas');
        }
        $this->assertDatabaseCount('periode_komite_humas', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $this->withSession(['_old_input' => ['pengurus' => 'invalid']])->get(route('komite-humas.create'))->assertOk();
    }

    public function test_filter_statistik_masa_bakti_dan_paginasi(): void
    {
        $this->actingAs($this->akun());
        $this->buatAktif(['nama' => 'Periode Saat Ini', 'tanggal_selesai' => '2026-10-15']);
        $this->buatAktif(['nama' => 'Periode Lama', 'tanggal_mulai' => '2023-01-01', 'tanggal_selesai' => '2025-12-31']);
        $this->buat(['nama' => 'Draf Masa Depan', 'tanggal_mulai' => '2027-01-01', 'tanggal_selesai' => '2029-12-31']);
        $r = $this->get(route('komite-humas.index'))->assertOk();
        $this->assertSame(['berjalan' => 1, 'pengurus' => 3, 'draf' => 1, 'berakhir' => 1], $r->viewData('statistik'));
        $this->get(route('komite-humas.index', ['masa' => 'berakhir']))->assertSee('Periode Lama')->assertDontSee('Periode Saat Ini');
        $this->get(route('komite-humas.index', ['masa' => 'segera_berakhir']))->assertSee('Periode Saat Ini')->assertDontSee('Periode Lama');
        $this->get(route('komite-humas.index', ['kata_kunci' => 'Ketua Pertama', 'status' => 'draf']))->assertSee('Draf Masa Depan')->assertDontSee('Periode Saat Ini');
        $this->travelTo(today()->setDate(2026, 10, 15));
        $this->assertSame('segera_berakhir', PeriodeKomiteHumas::where('nama', 'Periode Saat Ini')->first()->masaBakti());
        $this->travel(1)->days();
        $this->assertSame('berakhir', PeriodeKomiteHumas::where('nama', 'Periode Saat Ini')->first()->masaBakti());
        for ($i = 0; $i < 20; $i++) {
            $this->buat(['nama' => 'Draf '.$i]);
        }
        $this->assertCount(20, $this->get(route('komite-humas.index'))->viewData('daftar'));
        $this->assertCount(3, $this->get(route('komite-humas.index', ['page' => 2]))->viewData('daftar'));
    }

    public function test_nonaktif_dan_pencabutan_izin_tidak_bisa_mengakses(): void
    {
        $humas = $this->akun();
        $this->actingAs($humas);
        $p = $this->buat();
        $humas->update(['aktif' => false]);
        $this->actingAs($humas)->get(route('komite-humas.index'))->assertForbidden();
        $this->get(route('komite-humas.show', $p))->assertForbidden();
        $this->postJson(route('komite-humas.store'), $this->data())->assertForbidden();
        $humas->update(['aktif' => true]);
        $humas->daftarPeran()->detach();
        $this->actingAs($humas)->get(route('komite-humas.index'))->assertForbidden();
    }

    public function test_halaman_render_dan_teks_di_escape(): void
    {
        $humas = $this->akun();
        $this->actingAs($humas);
        $this->capture('empty', $this->get(route('komite-humas.index'))->assertOk());
        $this->capture('form', $this->get(route('komite-humas.create'))->assertOk());
        $p = $this->buatAktif(['nama' => 'Kepengurusan Komite SMP Negeri 2 Padang Panjang Periode Tahun 2026 sampai Tahun 2029']);
        $this->capture('show', $this->get(route('komite-humas.show', $p))->assertOk());
        $this->capture('edit', $this->get(route('komite-humas.edit', $p))->assertOk());
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['catatan' => 'Koordinasi sekolah dan orang tua melalui kepengurusan komite.']))->assertOk();
        $this->capture('history', $this->get(route('komite-humas.show', $p))->assertOk());
        $this->buat(['nama' => 'Draf Komite 2029-2032', 'tanggal_mulai' => '2029-07-01', 'tanggal_selesai' => '2032-06-30']);
        $this->buat(['nama' => 'Kepengurusan Lama 2023-2026', 'status' => 'arsip', 'tanggal_mulai' => '2023-07-01', 'tanggal_selesai' => '2026-06-30']);
        $this->capture('index', $this->get(route('komite-humas.index'))->assertOk());
        $this->putJson(route('komite-humas.update', $p), $this->editData($p, ['status' => 'arsip']))->assertOk();
        $this->capture('archive', $this->get(route('komite-humas.show', $p))->assertOk());
        $this->actingAs($this->akun('pimpinan'));
        $this->capture('readonly', $this->get(route('komite-humas.show', $p))->assertOk()->assertDontSee('081234567890'));
        $this->actingAs($this->akunIzin(['komite_humas.lihat']));
        $this->capture('nodoc', $this->get(route('komite-humas.show', $p))->assertOk());
        $xss = '</textarea><script>alert(1)</script>';
        $p->update(['nama' => $xss, 'catatan' => $xss]);
        $this->actingAs($humas)->get(route('komite-humas.show', $p))->assertOk()->assertDontSee($xss, false)->assertSee(e($xss), false);
        $this->get(route('komite-humas.edit', $p))->assertOk()->assertDontSee($xss, false);
    }

    private function akun(string $role = 'wakil_pimpinan_humas'): Pengguna
    {
        $p = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'komite.'.Str::uuid(), 'kata_sandi' => 'UjiKomite123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function akunIzin(array $izin): Pengguna
    {
        $role = Peran::create(['kode' => 'komite_'.Str::random(10), 'nama' => 'Komite terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $p = $this->akun('pegawai');
        $p->daftarPeran()->attach($role);

        return $p;
    }

    private function data(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'nama' => 'Komite Periode 2026-2029', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2029-06-30',
            'status' => 'draf', 'metode' => 'tanpa', 'pengurus' => [['nama' => 'Ketua Pertama', 'jabatan' => 'ketua', 'nomor_telepon' => '081234567890', 'aktif' => true],
                ['nama' => 'Sekretaris Komite', 'jabatan' => 'sekretaris', 'aktif' => true], ['nama' => 'Bendahara Komite', 'jabatan' => 'bendahara', 'aktif' => true]]];
    }

    private function aktifData(): array
    {
        return array_replace($this->data(), ['status' => 'aktif', 'metode' => 'unggah', 'berkas' => $this->foto(), 'nomor_sk' => '800/001/Komite/2026', 'tanggal_sk' => '2026-07-01']);
    }

    private function buat(array $data = []): PeriodeKomiteHumas
    {
        $this->postJson(route('komite-humas.store'), array_replace($this->data(), $data))->assertOk();

        return PeriodeKomiteHumas::latest('id')->firstOrFail();
    }

    private function buatAktif(array $data = []): PeriodeKomiteHumas
    {
        return $this->buat(array_replace($this->aktifData(), $data));
    }

    private function editData(PeriodeKomiteHumas $p, array $data = []): array
    {
        $p = $p->fresh();

        return array_replace($p->only(PeriodeKomiteHumas::KOLOM), ['tanggal_mulai' => $p->tanggal_mulai->format('Y-m-d'), 'tanggal_selesai' => $p->tanggal_selesai->format('Y-m-d'),
            'tanggal_sk' => $p->tanggal_sk?->format('Y-m-d'), 'versi' => $p->versi, 'metode' => 'tetap', 'catatan_perubahan' => 'Koreksi susunan kepengurusan komite.',
            'pengurus' => $p->pengurus->map(fn ($p) => $p->only(['id', 'nama', 'jabatan', 'nomor_telepon', 'aktif']))->all()], $data);
    }

    private function foto(string $name = 'SK.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(public_path('images/login-sekolah.jpg')));
    }

    private function dokumen(string $kategori = 'komite'): DokumenHumas
    {
        $file = $this->foto();
        $metadata = ['lokasi_file' => $file->storeAs('dokumen-humas', Str::uuid().'.jpg', 'local'), 'nama_file_asli' => $file->getClientOriginalName(), 'tipe_file' => 'image/jpeg', 'ukuran_file' => $file->getSize()];
        $doc = DokumenHumas::create($metadata + ['judul' => 'SK Komite sekolah', 'kategori' => $kategori, 'status' => 'aktif']);
        $doc->riwayat()->create($metadata + ['versi' => 1, 'diunggah_pada' => now()]);

        return $doc;
    }

    private function versiDokumen(DokumenHumas $doc, int $versi): void
    {
        $file = $this->foto('SK-versi-baru.jpg');
        $metadata = ['lokasi_file' => $file->storeAs('dokumen-humas', Str::uuid().'.jpg', 'local'), 'nama_file_asli' => $file->getClientOriginalName(), 'tipe_file' => 'image/jpeg', 'ukuran_file' => $file->getSize()];
        $doc->riwayat()->create($metadata + ['versi' => $versi, 'diunggah_pada' => now()]);
        $doc->update($metadata);
    }

    private function capture(string $name, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_KOMITE_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/komite-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$name.'.html', $response->getContent());
    }
}
