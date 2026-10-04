<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\OrangTuaWali;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\RiwayatBundelPertemuanHumas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UmpanBalikHumas;
use App\Services\Humas\BundelPertemuanHumasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

class BundelPertemuanHumasTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $humas;

    private AgendaHumas $agenda;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
        Storage::fake('local');
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->humas = $this->akun('wakil_pimpinan_humas');
        $this->actingAs($this->humas);
        $this->agenda = $this->rapat();
    }

    public function test_akses_peran_tamu_dan_izin_ekspor_terpisah(): void
    {
        foreach (['administrator', 'wakil_pimpinan_humas', 'pimpinan'] as $role) {
            $this->actingAs($this->akun($role))->get(route('agenda-humas.bundel', $this->agenda))->assertOk()->assertSee('Unduh bundel ZIP');
            $this->hapusUnduhan($this->export()->assertOk());
        }
        foreach (['pegawai', 'guru_mapel', 'orang_tua', 'siswa', 'satpam'] as $role) {
            $this->actingAs($this->akun($role))->get(route('agenda-humas.bundel', $this->agenda))->assertForbidden();
            $this->cetak()->assertForbidden();
            $this->export()->assertForbidden();
        }
        $this->actingAs($this->terbatas(['agenda_humas.lihat']));
        $this->get(route('agenda-humas.bundel', $this->agenda))->assertOk()->assertDontSee('Unduh bundel ZIP');
        $this->cetak()->assertOk();
        $this->export()->assertForbidden();
        $this->actingAs($this->terbatas(['agenda_humas.bundel']))->get(route('agenda-humas.bundel', $this->agenda))->assertForbidden();
        auth()->forgetGuards();
        $this->get(route('agenda-humas.bundel', $this->agenda))->assertRedirect(route('login'));
    }

    public function test_akun_nonaktif_dan_orang_tua_meski_punya_izin_ditolak(): void
    {
        $this->humas->update(['aktif' => false]);
        $this->actingAs($this->humas)->get(route('agenda-humas.bundel', $this->agenda))->assertForbidden();
        $u = $this->akun('wakil_pimpinan_humas');
        $w = OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => 'IDENTITAS PENGISI PRIVAT']);
        $s = Siswa::create(['nama_lengkap' => 'Anak privat', 'aktif' => true]);
        $w->siswa()->attach($s);
        $this->actingAs($u->fresh())->get(route('agenda-humas.bundel', $this->agenda))->assertForbidden();
        $this->export()->assertForbidden();
    }

    public function test_syarat_final_agenda_notulen_presensi_dan_draf_cetak(): void
    {
        foreach ([['status' => 'terjadwal'], ['status' => 'dibatalkan', 'alasan_pembatalan' => 'Dibatalkan'], ['pembahasan' => null], ['keputusan' => null]] as $ubah) {
            $a = $this->rapat($ubah);
            $this->export([], $a)->assertUnprocessable();
            $this->cetak([], $a)->assertOk()->assertSee('DRAF BUNDEL PERTEMUAN');
        }
        $this->agenda->peserta()->first()->update(['status_kehadiran' => 'belum_dicatat']);
        $this->export()->assertUnprocessable()->assertSee('belum dicatat');
        $this->cetak()->assertOk()->assertSee('DRAF BUNDEL PERTEMUAN');
        $this->agenda->peserta()->delete();
        $this->export()->assertUnprocessable()->assertSee('Daftar peserta');
        $this->assertSame(0, RiwayatBundelPertemuanHumas::count());
        $this->assertSame([], Storage::disk('local')->files('ekspor-pertemuan'));
    }

    public function test_izin_sumber_dokumen_dan_umpan_tidak_dapat_dilewati(): void
    {
        $d = $this->dokumen();
        $f = $this->evaluasi();
        $this->actingAs($this->terbatas(['agenda_humas.lihat', 'agenda_humas.bundel']));
        $r = $this->get(route('agenda-humas.bundel', $this->agenda))->assertOk()->assertDontSee($d->judul)->assertDontSee($f->judul);
        $this->export(['dokumen_ids' => [$d->riwayat()->first()->id]])->assertForbidden();
        $this->export(['formulir_ids' => [$f->id]])->assertForbidden();
        $this->cetak(['dokumen_ids' => [$d->riwayat()->first()->id]])->assertForbidden();
        $this->hapusUnduhan($this->export()->assertOk());
        $this->assertTrue($r->viewData('dokumen')->isEmpty());
    }

    public function test_pilihan_asing_duplikat_arsip_dan_versi_lama_ditolak(): void
    {
        $d = $this->dokumen();
        $v = $d->riwayat()->first();
        $asing = $this->dokumen([], $this->rapat())->riwayat()->first();
        $arsip = $this->dokumen(['status' => 'arsip'])->riwayat()->first();
        $f = $this->evaluasi();
        $fAsing = $this->evaluasi($this->rapat());
        $draf = $this->evaluasi(null, ['status' => 'draf', 'dibuka_pada' => null]);
        foreach ([['dokumen_ids' => [$asing->id]], ['dokumen_ids' => [$arsip->id]], ['dokumen_ids' => [$v->id, $v->id]], ['dokumen_ids' => ['bad']], ['dokumen_ids' => ['nested' => [$v->id]]], ['formulir_ids' => [$fAsing->id]], ['formulir_ids' => [$draf->id]], ['formulir_ids' => [$f->id, $f->id]]] as $ubah) {
            $this->export($ubah)->assertUnprocessable();
        }
        $this->versi($d, 2, 'BARU');
        $this->export(['dokumen_ids' => [$v->id]])->assertUnprocessable();
        $this->assertSame(0, RiwayatBundelPertemuanHumas::count());
    }

    public function test_perubahan_data_memerlukan_sidik_baru(): void
    {
        $data = $this->data();
        $this->agenda->peserta()->first()->update(['nama' => 'Nama sudah diperbaiki']);
        $this->postJson(route('agenda-humas.bundel.export', $this->agenda), $data)->assertUnprocessable()->assertSee('telah berubah');
        $data = $this->data();
        $this->agenda->update(['keputusan' => 'Keputusan baru']);
        $this->postJson(route('agenda-humas.bundel.cetak', $this->agenda), $data)->assertUnprocessable();
        $this->assertSame(0, RiwayatBundelPertemuanHumas::count());
        $d = $this->dokumen();
        $data = $this->data(['dokumen_ids' => [$d->riwayat()->first()->id]]);
        $this->versi($d, 2, 'VERSI TERBARU');
        $this->postJson(route('agenda-humas.bundel.export', $this->agenda), $data)->assertUnprocessable();
        $f = $this->evaluasi();
        $data = $this->data(['formulir_ids' => [$f->id]]);
        $f->forceFill(['versi' => 2, 'status' => 'ditutup'])->save();
        $this->postJson(route('agenda-humas.bundel.export', $this->agenda), $data)->assertUnprocessable();
    }

    public function test_zip_memuat_versi_dipilih_offline_dan_audit_tanpa_lokasi_privat(): void
    {
        $d = $this->dokumen(['judul' => '../../Undangan <script>window.injected=1</script>']);
        $v = $this->versi($d, 2, 'VERSI TERBARU');
        $f = $this->evaluasi();
        $r = $this->export(['dokumen_ids' => [$v->id], 'formulir_ids' => [$f->id]])->assertOk();
        $this->assertStringContainsString('application/zip', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $path = $r->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertSame(5, $zip->numFiles);
        $index = $zip->getFromName('index.html');
        $html = $zip->getFromName('01-bundel-pertemuan.html');
        $this->assertStringContainsString('assets/logo-kota.png', $html);
        $this->assertStringContainsString('assets/logo-sekolah.png', $html);
        $this->assertStringContainsString('TUGAS TINDAK LANJUT', $html);
        $this->assertStringContainsString('50,0%', $html);
        foreach ([$index, $html] as $konten) {
            foreach (['IDENTITAS PENGISI PRIVAT', 'MASUKAN PRIVAT', 'HASIL INTERNAL PRIVAT', storage_path(), $v->lokasi_file, 'token_presensi', 'orang_tua_wali_id', 'http://localhost'] as $rahasia) {
                $this->assertStringNotContainsString($rahasia, $konten);
            }
            $this->assertStringNotContainsString('<script>window.injected=1</script>', $konten);
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nama = $zip->getNameIndex($i);
            $this->assertStringNotContainsString('..', $nama);
            $this->assertStringNotContainsString('\\', $nama);
        }
        $nama = $zip->getNameIndex(4);
        $this->assertStringEndsWith('-v2.pdf', $nama);
        $this->assertSame('VERSI TERBARU', $zip->getFromName($nama));
        $log = RiwayatBundelPertemuanHumas::first();
        $this->assertSame($this->humas->id, $log->pengguna_id);
        $this->assertSame(hash_file('sha256', $path), $log->sha256);
        $this->assertSame(filesize($path), $log->ukuran_byte);
        $this->assertStringNotContainsString('Peserta Pertemuan', $log->toJson());
        $this->assertSame([$v->id], $log->ringkasan['lampiran']);
        $this->captureHtml('indeks', $index);
        $this->captureHtml('offline', $html);
        $zip->close();
        unlink($path);
    }

    public function test_gagal_berkas_hilang_di_luar_folder_dan_format_tidak_didukung(): void
    {
        foreach (['hilang', 'di-luar', 'format'] as $kasus) {
            $d = $this->dokumen();
            $v = $d->riwayat()->first();
            if ($kasus === 'hilang') {
                Storage::disk('local')->delete($v->lokasi_file);
            } elseif ($kasus === 'di-luar') {
                Storage::disk('local')->put('rahasia.txt', 'PRIVAT');
                $v->update(['lokasi_file' => 'rahasia.txt']);
            } else {
                $v->update(['tipe_file' => 'text/html']);
            }
            $this->export(['dokumen_ids' => [$v->id]])->assertUnprocessable();
        }
        $this->assertSame(0, RiwayatBundelPertemuanHumas::count());
        $this->assertSame([], Storage::disk('local')->files('ekspor-pertemuan'));
    }

    public function test_berkas_hilang_yang_tidak_dipilih_tidak_menghalangi(): void
    {
        $d = $this->dokumen();
        Storage::disk('local')->delete($d->riwayat()->first()->lokasi_file);
        $this->hapusUnduhan($this->export()->assertOk());
    }

    public function test_batas_berkas_dan_byte_diperiksa_dari_ukuran_asli(): void
    {
        $this->export(['dokumen_ids' => array_fill(0, 201, 1)])->assertUnprocessable();
        $d = $this->dokumen();
        $v = $d->riwayat()->first();
        $path = Storage::disk('local')->path($v->lokasi_file);
        $file = fopen($path, 'r+');
        ftruncate($file, BundelPertemuanHumasService::MAKS_BYTE + 1);
        fclose($file);
        clearstatcache();
        $this->export(['dokumen_ids' => [$v->id]])->assertUnprocessable()->assertSee('200 MB');
        $this->assertSame(0, RiwayatBundelPertemuanHumas::count());
    }

    public function test_tidak_hadir_izin_dan_tindak_lanjut_tertunda_tetap_bisa_dibundel(): void
    {
        $this->agenda->peserta()->create(['nama' => 'Peserta berhalangan', 'status_kehadiran' => 'izin']);
        $this->agenda->peserta()->create(['nama' => 'Peserta tidak hadir', 'status_kehadiran' => 'tidak_hadir']);
        $this->hapusUnduhan($this->export()->assertOk());
        $this->cetak()->assertOk()->assertSee('TUGAS TINDAK LANJUT')->assertSee('tidak hadir');
    }

    public function test_zip_sementara_dihapus_setelah_dikirim(): void
    {
        $r = $this->export()->assertOk();
        $path = $r->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);
        ob_start();
        try {
            $r->baseResponse->sendContent();
            $isi = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringStartsWith('PK', $isi);
        $this->assertFileDoesNotExist($path);
        $this->assertSame(1, RiwayatBundelPertemuanHumas::count());
    }

    public function test_rekap_terkirim_tidak_menilai_dan_jawaban_kosong(): void
    {
        $f = $this->evaluasi();
        $p = $f->pertanyaan()->where('jenis', 'skala')->first();
        foreach ([0, null] as $nilai) {
            $s = $f->sasaran()->create(['siswa_ids' => [0]]);
            $s->forceFill(['dikirim_pada' => now(), 'token_pengiriman' => (string) Str::uuid()])->save();
            if ($nilai !== null) {
                $s->jawaban()->create(['pertanyaan_umpan_balik_humas_id' => $p->id, 'nilai' => $nilai]);
            }
        }
        $r = $this->cetak(['formulir_ids' => [$f->id]])->assertOk()->assertSee('4 dari 4 akun merespons (100,0%).');
        $rekap = $r->viewData('rekapUmpan')[0]['rekap'];
        $this->assertSame(2, $rekap['pertanyaan'][$p->id]['menilai']);
        $this->assertSame(1, $rekap['pertanyaan'][$p->id]['skala'][0]);
        $this->assertEquals(3, $rekap['pertanyaan'][$p->id]['rata']);
        $this->assertEquals(50, $rekap['pertanyaan'][$p->id]['puas']);
        $this->assertSame(0, RiwayatBundelPertemuanHumas::count());
    }

    public function test_galeri_foto_maksimal_empat_per_lembar(): void
    {
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $d = $this->dokumen(['judul' => 'Dokumentasi '.$i.' '.str_repeat('Pertemuan orang tua dan koordinasi sekolah ', 3), 'tipe_file' => 'image/png']);
            $ids[] = $d->riwayat()->first()->id;
        }
        $r = $this->cetak(['dokumen_ids' => $ids])->assertOk();
        $this->assertSame(2, substr_count($r->getContent(), '<div class="bundle-gallery">'));
        $this->assertSame(5, $r->viewData('foto')->count());
        $this->capture('cetak-galeri', $r);
    }

    public function test_fixture_responsif_cetak_foto_umpan_dan_riwayat(): void
    {
        $d = $this->dokumen(['judul' => 'Surat undangan pertemuan orang tua dan evaluasi layanan pendidikan sekolah']);
        $v = $d->riwayat()->first();
        $foto = $this->dokumen(['judul' => 'Foto dokumentasi pertemuan', 'tipe_file' => 'image/png']);
        $fotoV = $foto->riwayat()->first();
        $f = $this->evaluasi();
        for ($i = 2; $i <= 51; $i++) {
            $this->agenda->peserta()->create(['nama' => sprintf('Peserta Pertemuan %02d', $i), 'instansi' => 'VII.A', 'peran' => 'Orang tua', 'status_kehadiran' => 'hadir']);
        }
        $this->capture('siap', $this->get(route('agenda-humas.bundel', $this->agenda))->assertOk());
        $r = $this->cetak(['dokumen_ids' => [$v->id, $fotoV->id], 'formulir_ids' => [$f->id]])->assertOk()->assertSee('DOKUMENTASI PERTEMUAN')->assertSee('Lembar 3 dari 3')->assertDontSee('MASUKAN PRIVAT');
        $this->assertSame(3, substr_count($r->getContent(), '<article class="sheet sheet--attendance">'));
        $this->capture('cetak', $r);
        $this->hapusUnduhan($this->export()->assertOk());
        $this->capture('riwayat', $this->get(route('agenda-humas.bundel', $this->agenda))->assertOk());
        $this->agenda->update(['status' => 'terjadwal', 'pembahasan' => null]);
        $this->capture('draf', $this->get(route('agenda-humas.bundel', $this->agenda))->assertOk());
        $this->capture('cetak-draf', $this->cetak()->assertOk()->assertSee('DRAF BUNDEL PERTEMUAN'));
        $this->actingAs($this->terbatas(['agenda_humas.lihat']));
        $this->capture('terbatas', $this->get(route('agenda-humas.bundel', $this->agenda))->assertOk()->assertDontSee($d->judul)->assertDontSee($f->judul));
    }

    private function akun(string $role): Pengguna
    {
        $u = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'bundel.'.Str::uuid(), 'kata_sandi' => 'UjiBundel123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $u->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $u;
    }

    private function terbatas(array $izin): Pengguna
    {
        $r = Peran::create(['nama' => 'Bundel terbatas', 'kode' => 'bundel_'.Str::random(10), 'aktif' => true]);
        $r->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $u = $this->akun('pegawai');
        $u->daftarPeran()->sync([$r->id]);

        return $u;
    }

    private function rapat(array $ubah = []): AgendaHumas
    {
        $a = AgendaHumas::create(array_replace(['judul' => 'Pertemuan orang tua dan koordinasi kegiatan sekolah', 'jenis' => 'orang_tua', 'waktu_mulai' => '2026-10-04 08:00', 'waktu_selesai' => '2026-10-04 10:00', 'tempat' => 'Aula sekolah', 'pemimpin' => 'Kepala Sekolah', 'notulis' => 'Waka Humas', 'topik' => 'Evaluasi layanan pendidikan', 'pembahasan' => 'Pembahasan layanan dan komunikasi.', 'keputusan' => 'Peningkatan komunikasi sekolah.', 'status' => 'selesai'], $ubah));
        $a->peserta()->create(['nama' => 'Peserta Pertemuan 01', 'instansi' => 'VII.A', 'peran' => 'Orang tua', 'status_kehadiran' => 'hadir']);
        $a->tindakLanjut()->create(['uraian' => 'TUGAS TINDAK LANJUT', 'penanggung_jawab' => 'Waka Humas', 'status' => 'diproses', 'batas_tanggal' => '2026-10-12']);

        return $a;
    }

    private function dokumen(array $ubah = [], ?AgendaHumas $agenda = null): DokumenHumas
    {
        $d = DokumenHumas::create(array_replace(['judul' => 'LAMPIRAN PRIVAT', 'kategori' => 'surat_keluar', 'status' => 'aktif', 'tipe_file' => 'application/pdf', 'lokasi_file' => 'dokumen-humas/placeholder.pdf', 'nama_file_asli' => 'NAMA PRIVAT.pdf', 'ukuran_file' => 4], $ubah));
        $this->versi($d, 1, 'LAMA');
        ($agenda ?? $this->agenda)->dokumen()->attach($d);

        return $d;
    }

    private function versi(DokumenHumas $d, int $versi, string $isi)
    {
        $foto = $d->tipe_file === 'image/png';
        $path = 'dokumen-humas/'.Str::uuid().($foto ? '.png' : '.pdf');
        if ($foto) {
            $isi = file_get_contents(public_path('images/kartu-pelajar/logo-smpn2pp.png'));
        }
        Storage::disk('local')->put($path, $isi);
        $d->update(['lokasi_file' => $path]);

        return $d->riwayat()->create(['versi' => $versi, 'lokasi_file' => $path, 'nama_file_asli' => 'NAMA PRIVAT'.($foto ? '.png' : '.pdf'), 'tipe_file' => $d->tipe_file, 'ukuran_file' => strlen($isi), 'diunggah_pada' => now()]);
    }

    private function evaluasi(?AgendaHumas $agenda = null, array $ubah = []): UmpanBalikHumas
    {
        $t = TahunPelajaran::firstOrCreate(['nama' => '2026/2027'], ['aktif' => true]);
        $f = new UmpanBalikHumas(array_replace(['tahun_pelajaran_id' => $t->id, 'agenda_humas_id' => ($agenda ?? $this->agenda)->id, 'token_pembuatan' => (string) Str::uuid(), 'judul' => 'Evaluasi pertemuan terpilih', 'cakupan' => 'agenda', 'pengantar' => 'Masukan untuk sekolah', 'penanggung_jawab' => 'Humas', 'mulai_pada' => '2026-10-04 08:00', 'selesai_pada' => '2026-10-12 12:00'], $ubah));
        $f->forceFill(array_replace(['status' => 'aktif', 'dibuka_pada' => '2026-10-04 08:00', 'versi' => 1], array_intersect_key($ubah, array_flip(['status', 'dibuka_pada']))))->save();
        $q = $f->pertanyaan()->create(['urutan' => 1, 'jenis' => 'skala', 'teks' => 'Kejelasan informasi sekolah', 'wajib' => true]);
        $txt = $f->pertanyaan()->create(['urutan' => 2, 'jenis' => 'teks', 'teks' => 'Saran perbaikan sekolah', 'wajib' => false]);
        foreach ([2, 4] as $nilai) {
            $s = $f->sasaran()->create(['siswa_ids' => [0]]);
            $s->forceFill(['dikirim_pada' => now(), 'token_pengiriman' => (string) Str::uuid()])->save();
            $s->jawaban()->create(['pertanyaan_umpan_balik_humas_id' => $q->id, 'nilai' => $nilai]);
            $s->jawaban()->create(['pertanyaan_umpan_balik_humas_id' => $txt->id, 'teks' => 'MASUKAN PRIVAT']);
        }
        $f->tindakLanjut()->create(['token_pembuatan' => (string) Str::uuid(), 'uraian' => 'HASIL INTERNAL PRIVAT', 'penanggung_jawab' => 'Humas', 'batas_tanggal' => '2026-10-12', 'status' => 'selesai', 'hasil' => 'HASIL INTERNAL PRIVAT']);

        return $f;
    }

    private function data(array $ubah = [], ?AgendaHumas $agenda = null): array
    {
        $a = ($agenda ?? $this->agenda)->fresh();
        $k = app(BundelPertemuanHumasService::class)->konteks($a, auth()->user());

        return array_replace(['sidik' => $k['sidik']], $ubah);
    }

    private function export(array $ubah = [], ?AgendaHumas $agenda = null): TestResponse
    {
        return $this->postJson(route('agenda-humas.bundel.export', $agenda ?? $this->agenda), $this->data($ubah, $agenda));
    }

    private function cetak(array $ubah = [], ?AgendaHumas $agenda = null): TestResponse
    {
        return $this->post(route('agenda-humas.bundel.cetak', $agenda ?? $this->agenda), $this->data($ubah, $agenda));
    }

    private function hapusUnduhan(TestResponse $r): void
    {
        unlink($r->baseResponse->getFile()->getPathname());
    }

    private function capture(string $nama, TestResponse $r): void
    {
        $this->captureHtml($nama, $r->getContent());
    }

    private function captureHtml(string $nama, string $html): void
    {
        if (getenv('NUSA_CAPTURE_BUNDEL_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/bundel-pertemuan-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }file_put_contents($folder.'/'.$nama.'.html', $html);
    }
}
