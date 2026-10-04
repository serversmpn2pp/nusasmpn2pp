<?php

namespace Tests\Feature;

use App\Models\BuktiAkreditasiHumas;
use App\Models\ButirAkreditasiHumas;
use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\OrangTuaWali;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PortofolioAkreditasiHumas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Humas\PortofolioAkreditasiHumasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

class PortofolioAkreditasiHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
        $this->actingAs($this->akun());
    }

    public function test_izin_default_sidebar_dan_akses_role(): void
    {
        foreach (['administrator', 'wakil_pimpinan_humas', 'pimpinan'] as $role) {
            $akun = $this->akun($role);
            $this->assertTrue($akun->memilikiIzin('akreditasi_humas.lihat'));
            $this->actingAs($akun)->get(route('akreditasi-humas.index'))->assertOk()->assertSee('Portofolio Akreditasi');
            $this->get(route('akreditasi-humas.create'))->assertStatus($role === 'pimpinan' ? 403 : 200);
        }
        foreach (['pegawai', 'guru_mapel', 'siswa', 'orang_tua', 'satpam'] as $role) {
            $this->actingAs($this->akun($role))->get(route('akreditasi-humas.index'))->assertForbidden();
            $this->get(route('akreditasi-humas.create'))->assertForbidden();
        }
        auth()->forgetGuards();
        $this->get(route('akreditasi-humas.index'))->assertRedirect(route('login'));
    }

    public function test_nonaktif_orang_tua_dan_siswa_meski_memiliki_role_humas(): void
    {
        $p = $this->portofolio();
        $ortu = $this->akun();
        OrangTuaWali::create(['pengguna_id' => $ortu->id, 'nama_lengkap' => 'Orang tua']);
        $siswa = $this->akun();
        $siswa->update(['siswa_id' => Siswa::create(['nama_lengkap' => 'Siswa', 'nisn' => '9988776655', 'aktif' => true])->id]);
        $mati = $this->akun();
        $mati->update(['aktif' => false]);
        foreach ([$ortu, $siswa, $mati] as $akun) {
            $this->actingAs($akun)->get(route('akreditasi-humas.index'))->assertForbidden();
            $this->get(route('akreditasi-humas.show', $p))->assertForbidden();
            $this->post(route('akreditasi-humas.export', $p), ['versi' => 0])->assertForbidden();
        }
    }

    public function test_pembuatan_idempoten_audit_dan_validasi(): void
    {
        $data = $this->dataPortofolio();
        $this->post(route('akreditasi-humas.store'), $data)->assertRedirect();
        $this->post(route('akreditasi-humas.store'), $data)->assertRedirect();
        $p = PortofolioAkreditasiHumas::firstOrFail();
        $this->assertSame(1, PortofolioAkreditasiHumas::count());
        $this->assertSame(1, $p->riwayat()->count());
        $this->assertSame('draf', $p->status);
        $this->actingAs($this->akun())->post(route('akreditasi-humas.store'), $data)->assertForbidden();
        foreach ([['nama' => ''], ['instrumen' => ''], ['tahun_pelajaran_id' => 99999], ['token_pembuatan' => 'not-uuid'], ['nama' => str_repeat('x', 181)]] as $salah) {
            $this->postJson(route('akreditasi-humas.store'), array_replace($data, $salah))->assertUnprocessable();
        }
    }

    public function test_filter_statistik_paginasi_dan_empty_state(): void
    {
        $this->capture('empty', $this->get(route('akreditasi-humas.index'))->assertOk()->assertSee('Belum ada portofolio'));
        $p = $this->portofolio(['nama' => 'Portofolio kemitraan', 'instrumen' => 'Instrumen sekolah']);
        $this->portofolio(['nama' => 'Arsip lama', 'status' => 'arsip']);
        $this->get(route('akreditasi-humas.index', ['kata_kunci' => 'kemitraan']))->assertOk()->assertSee('Portofolio kemitraan')->assertDontSee('Arsip lama');
        $r = $this->get(route('akreditasi-humas.index', ['status' => 'arsip', 'tahun_pelajaran_id' => $p->tahun_pelajaran_id]));
        $r->assertOk()->assertViewHas('jumlah', fn ($j) => $j['draf'] === 1 && $j['arsip'] === 1)->assertViewHas('daftar', fn ($d) => $d->total() === 1);
        foreach ([['status' => 'bad'], ['tahun_pelajaran_id' => 999], ['kata_kunci' => ['bad']]] as $filter) {
            $this->getJson(route('akreditasi-humas.index', $filter))->assertUnprocessable();
        }
        for ($i = 0; $i < 21; $i++) {
            $this->portofolio(['nama' => 'Portofolio '.$i]);
        }
        $this->get(route('akreditasi-humas.index'))->assertViewHas('daftar', fn ($d) => count($d) === 20 && $d->total() === 23);
    }

    public function test_butir_edit_duplikat_batas_dan_reset_pemeriksaan(): void
    {
        $p = $this->portofolio();
        $this->get(route('akreditasi-humas.butir.create', $p))->assertOk()->assertSee('Tambah butir');
        $b = $this->butir($p);
        $this->postJson(route('akreditasi-humas.butir.store', $p), $this->dataButir($p))->assertUnprocessable();
        foreach ([['target_bukti' => 0], ['target_bukti' => 201], ['urutan' => 1000], ['judul' => '']] as $salah) {
            $this->putJson(route('akreditasi-humas.butir.update', [$p, $b]), array_replace($this->dataButir($p), $salah))->assertUnprocessable();
        }
        $b->forceFill(['status' => 'terpenuhi', 'catatan_pemeriksaan' => 'Diperiksa', 'diperiksa_pada' => now()])->save();
        $this->put(route('akreditasi-humas.butir.update', [$p, $b]), array_replace($this->dataButir($p), ['judul' => 'Butir diperbarui']))->assertRedirect();
        $this->assertSame('belum_diperiksa', $b->fresh()->status);
        $this->assertNull($b->fresh()->diperiksa_pada);
        $this->assertSame('Butir diperbarui', $b->fresh()->judul);
    }

    public function test_perubahan_versi_ditolak_tanpa_mutasi(): void
    {
        $p = $this->portofolio();
        $b = $this->butir($p);
        $data = array_replace($this->dataPortofolio(), ['versi' => 0, 'alasan' => 'Perbaikan identitas']);
        $this->putJson(route('akreditasi-humas.update', $p), $data)->assertUnprocessable()->assertSee('Muat ulang');
        $this->postJson(route('akreditasi-humas.butir.periksa', [$p, $b]), ['versi' => 0, 'status' => 'tidak_berlaku', 'catatan_pemeriksaan' => 'Bukan ruang lingkup'])->assertUnprocessable();
        $this->assertSame('belum_diperiksa', $b->fresh()->status);
        $this->assertSame(1, $p->fresh()->versi);
    }

    public function test_tautan_idempoten_dan_pin_versi_lama(): void
    {
        $p = $this->portofolio();
        $b = $this->butir($p);
        $doc = $this->dokumen();
        $r = $doc->riwayat->first();
        $data = ['versi' => $p->fresh()->versi, 'token_pembuatan' => (string) Str::uuid(), 'riwayat_dokumen_humas_id' => $r->id, 'catatan' => 'Bukti kemitraan'];
        $url = route('akreditasi-humas.bukti.store', [$p, $b]);
        $this->post($url, $data)->assertRedirect();
        $this->post($url, $data)->assertRedirect();
        $this->assertSame(1, $b->bukti()->count());
        $this->assertSame(2, $p->fresh()->versi);
        $this->postJson($url, array_replace($data, ['versi' => 2, 'token_pembuatan' => (string) Str::uuid()]))->assertUnprocessable();
        $this->versiDokumen($doc, 2, 'BARU');
        $bukti = $b->bukti()->first();
        $this->assertSame($r->id, $bukti->riwayat_dokumen_humas_id);
        $download = $this->get(route('akreditasi-humas.bukti.unduh', [$p, $b, $bukti]))->assertOk();
        $this->assertSame('LAMA', file_get_contents($download->baseResponse->getFile()->getPathname()));
        $this->actingAs($this->akun())->postJson($url, $data)->assertForbidden();
    }

    public function test_bukti_arsip_hilang_dan_path_di_luar_dokumen_ditolak(): void
    {
        $p = $this->portofolio();
        $b = $this->butir($p);
        $d = $this->dokumen();
        $r = $d->riwayat->first();
        $d->update(['status' => 'arsip']);
        $this->tautkan($p, $b, $r->id)->assertUnprocessable();
        $d->update(['status' => 'aktif']);
        Storage::disk('local')->delete($r->lokasi_file);
        $this->tautkan($p, $b, $r->id)->assertUnprocessable();
        Storage::disk('local')->put('secret.txt', 'RAHASIA');
        $r->update(['lokasi_file' => 'secret.txt']);
        $this->tautkan($p, $b, $r->id)->assertUnprocessable();
        $r->update(['lokasi_file' => 'dokumen-humas/../secret.txt']);
        $this->tautkan($p, $b, $r->id)->assertUnprocessable();
        $this->assertSame(0, $b->bukti()->count());
        $this->assertSame(1, $p->fresh()->versi);
    }

    public function test_butir_dan_bukti_portofolio_lain_tidak_bisa_diakses(): void
    {
        $p = $this->portofolio();
        $lain = $this->portofolio();
        $b = $this->butir($lain);
        $bukti = $this->bukti($lain, $b);
        $this->get(route('akreditasi-humas.butir', [$p, $b]))->assertNotFound();
        $this->get(route('akreditasi-humas.bukti.unduh', [$p, $b, $bukti]))->assertNotFound();
        $this->deleteJson(route('akreditasi-humas.butir.destroy', [$p, $b]), ['versi' => $p->versi, 'alasan' => 'Alasan lengkap'])->assertNotFound();
        $this->putJson(route('akreditasi-humas.butir.update', [$p, $b]), $this->dataButir($p))->assertNotFound();
        $bSendiri = $this->butir($p);
        $this->deleteJson(route('akreditasi-humas.bukti.destroy', [$p, $bSendiri, $bukti]), ['versi' => $p->fresh()->versi, 'alasan' => 'Alasan lengkap'])->assertNotFound();
    }

    public function test_pemeriksaan_target_alasan_tidak_berlaku_dan_siap(): void
    {
        $p = $this->portofolio();
        $b = $this->butir($p, ['target_bukti' => 2]);
        $this->periksa($p, $b)->assertUnprocessable();
        $this->bukti($p, $b);
        $this->periksa($p, $b)->assertUnprocessable();
        $this->bukti($p, $b);
        $this->periksa($p, $b)->assertRedirect();
        $this->assertSame('terpenuhi', $b->fresh()->status);
        $na = $this->butir($p, ['kode' => 'NA']);
        $this->postJson(route('akreditasi-humas.butir.periksa', [$p, $na]), ['versi' => $p->fresh()->versi, 'status' => 'tidak_berlaku'])->assertUnprocessable();
        $this->ubahStatus($p, 'siap')->assertUnprocessable();
        $this->periksa($p, $na, 'tidak_berlaku')->assertRedirect();
        $this->ubahStatus($p, 'siap')->assertRedirect();
        $this->assertSame('siap', $p->fresh()->status);
    }

    public function test_semua_tidak_berlaku_dan_kosong_tidak_bisa_siap(): void
    {
        $p = $this->portofolio();
        $this->ubahStatus($p, 'siap')->assertUnprocessable();
        $b = $this->butir($p);
        $this->periksa($p, $b, 'tidak_berlaku')->assertRedirect();
        $this->ubahStatus($p, 'siap')->assertUnprocessable();
        $this->assertSame('draf', $p->fresh()->status);
    }

    public function test_penguncian_revisi_arsip_dan_audit(): void
    {
        [$p, $b] = $this->siap();
        $this->get(route('akreditasi-humas.edit', $p))->assertForbidden();
        $this->get(route('akreditasi-humas.butir.create', $p))->assertForbidden();
        $this->putJson(route('akreditasi-humas.butir.update', [$p, $b]), $this->dataButir($p))->assertUnprocessable();
        $this->periksa($p, $b)->assertUnprocessable();
        $this->ubahStatus($p, 'siap')->assertUnprocessable();
        $this->postJson(route('akreditasi-humas.status', $p), ['versi' => $p->fresh()->versi, 'status' => 'draf'])->assertUnprocessable();
        $this->ubahStatus($p, 'arsip')->assertRedirect();
        $this->ubahStatus($p, 'siap')->assertUnprocessable();
        $this->ubahStatus($p, 'draf')->assertRedirect();
        $this->assertSame('draf', $p->fresh()->status);
        $this->assertTrue($p->riwayat()->where('aksi', 'Status: Draf')->where('catatan', 'Pemeriksaan lengkap')->exists());
    }

    public function test_identitas_instrumen_berubah_reset_semua_butir(): void
    {
        $p = $this->portofolio();
        $b = $this->butir($p);
        $this->bukti($p, $b);
        $this->periksa($p, $b)->assertRedirect();
        $this->put(route('akreditasi-humas.update', $p), array_replace($this->dataPortofolio(), ['versi' => $p->fresh()->versi, 'instrumen' => 'Instrumen baru', 'alasan' => 'Revisi instrumen']))->assertRedirect();
        $this->assertSame('belum_diperiksa', $b->fresh()->status);
        $this->assertSame(1, $b->bukti()->count());
        $this->ubahStatus($p, 'siap')->assertUnprocessable();
    }

    public function test_lepas_bukti_dan_keluarkan_butir_tanpa_menghapus_dokumen(): void
    {
        $p = $this->portofolio();
        $b = $this->butir($p);
        $bukti = $this->bukti($p, $b);
        $this->periksa($p, $b)->assertRedirect();
        $this->delete(route('akreditasi-humas.bukti.destroy', [$p, $b, $bukti]), ['versi' => $p->fresh()->versi, 'alasan' => 'Bukti tidak relevan'])->assertRedirect();
        $this->assertSame(0, $b->bukti()->count());
        $this->assertNotNull($bukti->fresh()->dilepas_pada);
        $this->assertSame('belum_diperiksa', $b->fresh()->status);
        Storage::disk('local')->assertExists($bukti->berkas->lokasi_file);
        $this->get(route('akreditasi-humas.bukti.unduh', [$p, $b, $bukti]))->assertNotFound();
        $this->delete(route('akreditasi-humas.butir.destroy', [$p, $b]), ['versi' => $p->fresh()->versi, 'alasan' => 'Di luar ruang lingkup'])->assertRedirect();
        $this->assertNotNull($b->fresh()->dihapus_pada);
        $this->get(route('akreditasi-humas.butir', [$p, $b]))->assertNotFound();
        $this->assertSame(0, $p->butir()->count());
        $this->assertSame(1, DokumenHumas::count());
    }

    public function test_izin_dokumen_tidak_bisa_dilewati_dengan_bundel(): void
    {
        [$p, $b] = $this->siap();
        $bukti = $b->bukti()->first();
        $this->actingAs($this->terbatas(['akreditasi_humas.lihat', 'akreditasi_humas.kelola', 'akreditasi_humas.ekspor']));
        $this->get(route('akreditasi-humas.show', $p))->assertOk()->assertDontSee('Unduh bundel ZIP');
        $this->get(route('akreditasi-humas.butir', [$p, $b]))->assertOk()->assertDontSee($bukti->judul)->assertDontSee('BERKAS-PRIVAT.pdf');
        $this->get(route('akreditasi-humas.cetak', $p))->assertOk()->assertDontSee($bukti->judul);
        $this->get(route('akreditasi-humas.bukti.unduh', [$p, $b, $bukti]))->assertForbidden();
        $this->post(route('akreditasi-humas.export', $p), ['versi' => $p->fresh()->versi])->assertForbidden();
        $this->periksa($p, $b)->assertForbidden();
        $this->tautkan($p, $b, $bukti->riwayat_dokumen_humas_id)->assertForbidden();
        $this->actingAs($this->terbatas(['akreditasi_humas.ekspor', 'dokumen_humas.lihat']))->post(route('akreditasi-humas.export', $p), ['versi' => $p->fresh()->versi])->assertForbidden();
        $this->actingAs($this->terbatas(['akreditasi_humas.lihat', 'dokumen_humas.lihat']))->post(route('akreditasi-humas.export', $p), ['versi' => $p->fresh()->versi])->assertForbidden();
    }

    public function test_readonly_tidak_bisa_mutasi(): void
    {
        $p = $this->portofolio();
        $b = $this->butir($p);
        $this->actingAs($this->akun('pimpinan'));
        $this->get(route('akreditasi-humas.show', $p))->assertOk()->assertDontSee('Tambah butir')->assertDontSee('Simpan status');
        $this->put(route('akreditasi-humas.update', $p), $this->dataPortofolio())->assertForbidden();
        $this->post(route('akreditasi-humas.butir.store', $p), $this->dataButir($p))->assertForbidden();
        $this->periksa($p, $b)->assertForbidden();
        $this->ubahStatus($p, 'siap')->assertForbidden();
        $this->delete(route('akreditasi-humas.butir.destroy', [$p, $b]))->assertForbidden();
    }

    public function test_bundel_indeks_path_aman_versi_lama_dan_butir_na(): void
    {
        $p = $this->portofolio(['nama' => '<script>window.injected=1</script>']);
        $b = $this->butir($p, ['kode' => '../A', 'judul' => 'Hubungan / orang tua']);
        $d = $this->dokumen(['judul' => '../ DOKUMEN <script>window.injected=1</script>']);
        $bukti = $this->bukti($p, $b, $d);
        $this->versiDokumen($d, 2, 'BARU');
        $this->periksa($p, $b)->assertRedirect();
        $na = $this->butir($p, ['kode' => 'NA']);
        $this->bukti($p, $na);
        $this->periksa($p, $na, 'tidak_berlaku')->assertRedirect();
        $this->ubahStatus($p, 'siap')->assertRedirect();
        $versi = $p->fresh()->versi;
        $r = $this->post(route('akreditasi-humas.export', $p), ['versi' => $versi])->assertOk()->assertHeader('Content-Type', 'application/zip');
        $path = $r->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertSame(2, $zip->numFiles);
        $html = $zip->getFromName('index.html');
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString(storage_path(), $html);
        $this->assertStringNotContainsString($bukti->berkas->lokasi_file, $html);
        $this->assertStringContainsString('Tidak berlaku', $html);
        $name = $zip->getNameIndex(1);
        $this->assertStringNotContainsString('..', $name);
        $this->assertStringNotContainsString('\\', $name);
        $this->assertStringEndsWith('-v1.pdf', $name);
        $this->assertSame('LAMA', $zip->getFromName($name));
        $this->captureHtml('manifest', $html);
        $zip->close();
        unlink($path);
        $this->assertSame($versi, $p->fresh()->versi);
        $this->assertTrue($p->riwayat()->where('aksi', 'Bundel ZIP diunduh')->exists());
        $this->postJson(route('akreditasi-humas.export', $p), ['versi' => $versi - 1])->assertUnprocessable();
    }

    public function test_berkas_hilang_menghalangi_siap_dan_ekspor(): void
    {
        [$p, $b] = $this->siap();
        Storage::disk('local')->delete($b->bukti()->first()->berkas->lokasi_file);
        $this->get(route('akreditasi-humas.show', $p))->assertOk()->assertSee('1 berkas bukti tidak tersedia');
        $this->postJson(route('akreditasi-humas.export', $p), ['versi' => $p->fresh()->versi])->assertUnprocessable();
        $this->assertFalse($p->riwayat()->where('aksi', 'Bundel ZIP diunduh')->exists());
        $this->assertSame([], Storage::disk('local')->files('ekspor-akreditasi'));
        $this->ubahStatus($p, 'draf')->assertRedirect();
        $this->ubahStatus($p, 'siap')->assertUnprocessable();
    }

    public function test_draf_dan_arsip_tidak_bisa_ekspor(): void
    {
        $p = $this->portofolio();
        $this->postJson(route('akreditasi-humas.export', $p), ['versi' => 0])->assertUnprocessable();
        $this->ubahStatus($p, 'arsip')->assertRedirect();
        $this->postJson(route('akreditasi-humas.export', $p), ['versi' => $p->fresh()->versi])->assertUnprocessable();
    }

    public function test_batas_ukuran_bundel_dan_format_tidak_didukung(): void
    {
        [$p, $b] = $this->siap();
        $berkas = $b->bukti()->first()->berkas;
        $berkas->update(['tipe_file' => 'text/html']);
        $this->postJson(route('akreditasi-humas.export', $p), ['versi' => $p->fresh()->versi])->assertUnprocessable();
        $berkas->update(['tipe_file' => 'application/pdf']);
        $f = fopen(Storage::disk('local')->path($berkas->lokasi_file), 'r+');
        ftruncate($f, PortofolioAkreditasiHumasService::MAKS_BYTE + 1);
        fclose($f);
        clearstatcache();
        $this->postJson(route('akreditasi-humas.export', $p), ['versi' => $p->fresh()->versi])->assertUnprocessable()->assertSee('200 MB');
        $this->assertSame([], Storage::disk('local')->files('ekspor-akreditasi'));
    }

    public function test_pemilihan_dokumen_filter_paginasi_pin_versi_dan_arsip(): void
    {
        $p = $this->portofolio();
        $b = $this->butir($p);
        $d = $this->dokumen(['judul' => 'NOTULEN PILIHAN', 'kategori' => 'notulen']);
        $baru = $this->versiDokumen($d, 2, 'BARU');
        $this->dokumen(['judul' => 'DOKUMEN ARSIP', 'status' => 'arsip']);
        $this->dokumen(['judul' => 'DOKUMEN LAIN', 'kategori' => 'sop']);
        $r = $this->get(route('akreditasi-humas.butir', [$p, $b, 'kata_kunci' => 'PILIHAN', 'kategori' => 'notulen']))->assertOk()->assertSee('NOTULEN PILIHAN')->assertDontSee('DOKUMEN ARSIP')->assertDontSee('DOKUMEN LAIN');
        $this->assertSame($baru->id, $r->viewData('dokumen')->first()->riwayat->first()->id);
        for ($i = 0; $i < 11; $i++) {
            $this->dokumen(['judul' => 'Dokumen tambahan '.$i]);
        }
        $this->get(route('akreditasi-humas.butir', [$p, $b]))->assertViewHas('dokumen', fn ($d) => count($d) === 10 && $d->total() === 13);
        $this->getJson(route('akreditasi-humas.butir', [$p, $b, 'kategori' => 'invalid']))->assertUnprocessable();
    }

    public function test_tampilan_escape_cetak_dan_fixture_ui(): void
    {
        $p = $this->portofolio(['nama' => str_repeat('Portofolio hubungan orang tua dan kemitraan ', 3)]);
        $b = $this->butir($p, ['judul' => 'Pelibatan orang tua dalam peningkatan layanan pendidikan dan kemitraan sekolah']);
        $d = $this->dokumen(['judul' => 'Notulen rapat bersama komite dan orang tua murid tahun pelajaran 2026/2027']);
        $this->bukti($p, $b, $d);
        $this->dokumen(['judul' => '<script>window.injected=1</script>']);
        $this->capture('index', $this->get(route('akreditasi-humas.index'))->assertOk());
        $this->capture('form', $this->get(route('akreditasi-humas.create'))->assertOk());
        $this->capture('show', $this->get(route('akreditasi-humas.show', $p))->assertOk());
        $this->capture('butir', $this->get(route('akreditasi-humas.butir', [$p, $b]))->assertOk()->assertDontSee('<script>window.injected=1</script>', false));
        $this->capture('new-butir', $this->get(route('akreditasi-humas.butir.create', $p))->assertOk());
        $cetak = $this->get(route('akreditasi-humas.cetak', $p))->assertOk()->assertSee('PORTOFOLIO AKREDITASI HUMAS');
        $this->assertStringContainsString('no-store', $cetak->headers->get('Cache-Control'));
        $this->capture('cetak', $cetak);
        $this->periksa($p, $b)->assertRedirect();
        $this->ubahStatus($p, 'siap')->assertRedirect();
        $this->capture('ready', $this->get(route('akreditasi-humas.show', $p))->assertOk()->assertSee('Unduh bundel ZIP'));
        $this->actingAs($this->terbatas(['akreditasi_humas.lihat']));
        $this->capture('limited', $this->get(route('akreditasi-humas.butir', [$p, $b]))->assertOk()->assertDontSee($d->judul));
    }

    private function akun(string $role = 'wakil_pimpinan_humas'): Pengguna
    {
        $p = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'akreditasi.'.Str::uuid(), 'kata_sandi' => 'UjiAkreditasi123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function terbatas(array $izin): Pengguna
    {
        $r = Peran::create(['kode' => 'akreditasi_'.Str::random(10), 'nama' => 'Akses terbatas', 'aktif' => true]);
        $r->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $p = $this->akun('pegawai');
        $p->daftarPeran()->sync([$r->id]);

        return $p;
    }

    private function dataPortofolio(): array
    {
        $t = TahunPelajaran::firstOrCreate(['nama' => '2026/2027'], ['aktif' => true, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);

        return ['token_pembuatan' => (string) Str::uuid(), 'tahun_pelajaran_id' => $t->id, 'nama' => 'Portofolio akreditasi sekolah', 'instrumen' => 'Instrumen ditentukan sekolah', 'penanggung_jawab' => 'Waka Humas'];
    }

    private function portofolio(array $data = []): PortofolioAkreditasiHumas
    {
        $p = new PortofolioAkreditasiHumas;
        $p->forceFill(array_replace($this->dataPortofolio(), ['status' => 'draf', 'versi' => 0, 'dibuat_oleh_pengguna_id' => auth()->id()], $data))->save();

        return $p;
    }

    private function dataButir(PortofolioAkreditasiHumas $p): array
    {
        return ['versi' => $p->fresh()->versi, 'kode' => 'H-01', 'judul' => 'Pelibatan orang tua', 'deskripsi' => 'Bukti pertemuan dan tindak lanjut', 'urutan' => 1, 'target_bukti' => 1];
    }

    private function butir(PortofolioAkreditasiHumas $p, array $data = []): ButirAkreditasiHumas
    {
        $this->post(route('akreditasi-humas.butir.store', $p), array_replace($this->dataButir($p), $data))->assertRedirect();

        return $p->butir()->orderByDesc('id')->get()->sortByDesc('id')->first();
    }

    private function dokumen(array $data = []): DokumenHumas
    {
        $d = DokumenHumas::create(array_replace(['judul' => 'BUKTI PRIVAT '.Str::random(8), 'kategori' => 'iasp', 'status' => 'aktif', 'lokasi_file' => 'dokumen-humas/placeholder.pdf', 'nama_file_asli' => 'BERKAS-PRIVAT.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => 4], $data));
        $this->versiDokumen($d, 1, 'LAMA');

        return $d->fresh('riwayat');
    }

    private function versiDokumen(DokumenHumas $d, int $versi, string $isi)
    {
        $path = 'dokumen-humas/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $isi);
        $d->update(['lokasi_file' => $path]);

        return $d->riwayat()->create(['versi' => $versi, 'lokasi_file' => $path, 'nama_file_asli' => 'BERKAS-PRIVAT.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => strlen($isi), 'diunggah_pada' => now()]);
    }

    private function tautkan(PortofolioAkreditasiHumas $p, ButirAkreditasiHumas $b, int $id): TestResponse
    {
        return $this->postJson(route('akreditasi-humas.bukti.store', [$p, $b]), ['versi' => $p->fresh()->versi, 'token_pembuatan' => (string) Str::uuid(), 'riwayat_dokumen_humas_id' => $id]);
    }

    private function bukti(PortofolioAkreditasiHumas $p, ButirAkreditasiHumas $b, ?DokumenHumas $d = null): BuktiAkreditasiHumas
    {
        $d ??= $this->dokumen();
        $this->tautkan($p, $b, $d->riwayat->first()->id)->assertRedirect();

        return $b->bukti()->latest('id')->first();
    }

    private function periksa(PortofolioAkreditasiHumas $p, ButirAkreditasiHumas $b, string $status = 'terpenuhi'): TestResponse
    {
        return $this->postJson(route('akreditasi-humas.butir.periksa', [$p, $b]), ['versi' => $p->fresh()->versi, 'status' => $status, 'catatan_pemeriksaan' => 'Bukti sesuai ruang lingkup sekolah']);
    }

    private function ubahStatus(PortofolioAkreditasiHumas $p, string $status): TestResponse
    {
        return $this->postJson(route('akreditasi-humas.status', $p), ['versi' => $p->fresh()->versi, 'status' => $status, 'alasan' => 'Pemeriksaan lengkap']);
    }

    private function siap(): array
    {
        $p = $this->portofolio();
        $b = $this->butir($p);
        $this->bukti($p, $b);
        $this->periksa($p, $b)->assertRedirect();
        $this->ubahStatus($p, 'siap')->assertRedirect();

        return [$p->fresh(), $b->fresh()];
    }

    private function capture(string $nama, TestResponse $r): void
    {
        $this->captureHtml($nama, $r->getContent());
    }

    private function captureHtml(string $nama, string $html): void
    {
        if (getenv('NUSA_CAPTURE_AKREDITASI_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/akreditasi-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$nama.'.html', $html);
    }
}
