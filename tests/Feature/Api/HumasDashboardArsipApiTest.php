<?php

namespace Tests\Feature\Api;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\KerjaSamaHumas;
use App\Models\MitraHumas;
use App\Models\OrangTuaWali;
use App\Models\PengaduanHumas;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\RiwayatBundelPertemuanHumas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UmpanBalikHumas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class HumasDashboardArsipApiTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $humas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        Storage::fake('public');
        $this->humas = $this->akun('wakil_pimpinan_humas');
    }

    public function test_dashboard_default_semester_referensi_dan_kustom(): void
    {
        $this->token($this->humas);
        $this->getJson($this->url('dashboard'))->assertOk()->assertJsonPath('data.filter.tanggal_mulai', '2026-09-06');
        $tahun = $this->tahun();
        $r = $this->getJson($this->url('dashboard'))->assertOk()->assertJsonPath('data.filter.tahun_pelajaran_id', $tahun->id)
            ->assertJsonPath('data.filter.tanggal_mulai', '2026-07-01')->assertJsonPath('data.filter.tanggal_selesai', '2027-06-30');
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->getJson($this->url('dashboard').'?periode=ganjil')->assertJsonPath('data.filter.tanggal_selesai', '2026-12-31');
        $this->getJson($this->url('dashboard').'?periode=genap')->assertJsonPath('data.filter.tanggal_mulai', '2027-01-01');
        $this->getJson($this->url('dashboard').'?'.http_build_query($this->periode()))->assertJsonPath('data.filter.tahun_pelajaran_id', null);
        $this->getJson($this->url('dashboard/referensi'))->assertOk()->assertJsonPath('data.tahun.0.id', $tahun->id)->assertJsonStructure(['data' => ['periode']]);
    }

    public function test_dashboard_filter_invalid_dan_batas_periode(): void
    {
        $this->token($this->humas);
        foreach ([['periode' => 'ganjil'], ['periode' => 'bad'], ['periode' => 'kustom'], ['periode' => ['genap']], ['tahun_pelajaran_id' => 999],
            $this->periode(['tanggal_selesai' => '2026-08-31']), $this->periode(['tanggal_mulai' => '2020-01-01', 'tanggal_selesai' => '2022-01-01']),
            $this->periode(['tanggal_mulai' => ['2026-09-01']])] as $filter) {
            $this->getJson($this->url('dashboard').'?'.http_build_query($filter))->assertUnprocessable();
        }
    }

    public function test_dashboard_metrik_bulanan_tanpa_data_privat_dan_setara_web(): void
    {
        $this->tahun();
        foreach (['2026-09-01 00:00:00', '2026-09-30 23:00:00', '2026-08-31 12:00:00', '2026-10-01 12:00:00'] as $tgl) {
            $this->agenda(['waktu_mulai' => $tgl, 'waktu_selesai' => now()->parse($tgl)->addHour()->toDateTimeString()]);
        }
        $t = new PengaduanHumas;
        $t->forceFill(['token_pembuatan' => Str::uuid(), 'judul' => 'JUDUL RAHASIA', 'jenis' => 'pengaduan', 'kategori' => 'layanan', 'kanal' => 'telepon', 'tanggal_diterima' => '2026-09-20',
            'isi' => 'ISI RAHASIA', 'nama_pelapor' => 'IDENTITAS RAHASIA', 'kontak_pelapor' => '081299999999', 'prioritas' => 'normal', 'status' => 'baru'])->save();
        $this->token($this->humas);
        $r = $this->getJson($this->url('dashboard').'?'.http_build_query($this->periode()))->assertOk()->assertJsonPath('data.metrik.agenda.jumlah', 2)
            ->assertJsonPath('data.bulan.2026-09.jumlah.agenda', 2)->assertJsonPath('data.metrik.pengaduan_masuk.jumlah', 1)->assertJsonMissingPath('data.metrik.agenda.url');
        foreach (['IDENTITAS RAHASIA', 'JUDUL RAHASIA', 'ISI RAHASIA', '081299999999', 'lokasi_file'] as $privat) {
            $this->assertStringNotContainsString($privat, $r->getContent());
        }
        $web = $this->actingAs($this->humas)->get(route('dashboard-humas.index', $this->periode()))->assertOk();
        $this->assertSame($web->viewData('filter'), $r->json('data.filter'));
        $this->assertSame($web->viewData('metrik')['agenda']['jumlah'], $r->json('data.metrik.agenda.jumlah'));
        $this->assertSame('baru', $t->fresh()->status);
    }

    public function test_dashboard_tanpa_izin_sumber_tidak_membaca_data_tersebut(): void
    {
        $this->agenda();
        $this->token($this->terbatas(['dashboard_humas.lihat']));
        DB::enableQueryLog();
        $r = $this->getJson($this->url('dashboard'))->assertOk()->assertJsonPath('data.metrik', [])->assertJsonPath('data.agenda', []);
        $body = json_decode($r->getContent())->data;
        foreach (['metrik', 'distribusi', 'kolom_bulanan', 'bulan'] as $map) {
            $this->assertInstanceOf(\stdClass::class, $body->{$map});
        }
        $this->assertInstanceOf(\stdClass::class, $body->bulan->{'2026-09'}->jumlah);
        $queries = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        foreach (['from "agenda_humas"', 'from "pengaduan_humas"', 'from "dokumen_humas"', 'from "alumni_humas"', 'from "kunjungan_tamu"'] as $table) {
            $this->assertStringNotContainsString($table, $queries);
        }
    }

    public function test_login_peran_readonly_dan_identitas_orang_tua_siswa(): void
    {
        $d = $this->dokumen();
        $a = $this->agenda();
        foreach (['dashboard', 'dashboard/referensi', 'dokumen', "dokumen/{$d->id}/unduh", "agenda/{$a->id}/bundel"] as $path) {
            $this->getJson($this->url($path))->assertUnauthorized();
        }
        $this->token($this->akun('pimpinan'));
        $this->getJson($this->url('dashboard'))->assertOk();
        $this->getJson($this->url("dokumen/{$d->id}"))->assertOk()->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->postJson($this->url('dokumen'), $this->inputDokumen() + ['berkas' => $this->pdf()])->assertForbidden();
        $ortu = $this->akun('wakil_pimpinan_humas');
        OrangTuaWali::create(['pengguna_id' => $ortu->id, 'nama_lengkap' => 'Orang tua']);
        $siswa = $this->akun('wakil_pimpinan_humas');
        $siswa->update(['siswa_id' => Siswa::create(['nama_lengkap' => 'Siswa', 'nisn' => '9911223344', 'aktif' => true])->id]);
        foreach ([$ortu, $siswa, $this->akun('guru_mapel')] as $u) {
            $this->token($u);
            foreach (['dashboard', 'dokumen', "agenda/{$a->id}/bundel"] as $path) {
                $this->getJson($this->url($path))->assertForbidden();
            }
        }
        $this->humas->update(['aktif' => false]);
        $this->token($this->humas);
        $this->getJson($this->url('dokumen'))->assertUnauthorized();
    }

    public function test_unggah_dokumen_otoritas_server_metadata_dan_berkas_privat(): void
    {
        $this->token($this->humas);
        $r = $this->postJson($this->url('dokumen'), $this->inputDokumen() + ['berkas' => $this->pdf(), 'status' => 'arsip', 'dibuat_oleh_pengguna_id' => 999, 'lokasi_file' => 'outside.txt'])->assertCreated();
        $d = DokumenHumas::findOrFail($r->json('data.id'));
        $this->assertSame($this->humas->id, $d->dibuat_oleh_pengguna_id);
        $this->assertSame('aktif', $d->status);
        $this->assertStringStartsWith('dokumen-humas/', $d->lokasi_file);
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('riwayat_dokumen_humas', 1);
        $this->getJson($this->url("dokumen/{$d->id}"))->assertOk()->assertJsonPath('data.versi_berkas', 1)->assertJsonMissingPath('data.lokasi_file')->assertJsonMissingPath('data.dibuat_oleh_pengguna_id');
        $this->get($r->json('data.berkas.url'))->assertDownload('bukti.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson($this->url('dokumen/referensi'))->assertOk()->assertJsonPath('data.batas_berkas_mb', 20);
    }

    public function test_revisi_metadata_riwayat_unduh_dan_arsip(): void
    {
        $this->token($this->humas);
        $d = $this->dokumen();
        $v = $d->riwayat()->firstOrFail();
        $sidik = $this->getJson($this->url("dokumen/{$d->id}"))->json('data.sidik');
        $r = $this->patchJson($this->url("dokumen/{$d->id}"), $this->inputDokumen() + ['sidik' => $sidik])->assertOk();
        $this->assertDatabaseCount('riwayat_dokumen_humas', 1);
        $r = $this->postJson($this->url("dokumen/{$d->id}/revisi"), $this->inputDokumen() + ['sidik' => $r->json('data.sidik'), 'berkas' => $this->pdf('baru.pdf'), 'catatan_revisi' => 'Memperbaiki isi dokumen'])->assertOk()->assertJsonPath('data.versi_berkas', 2);
        $this->get($r->json('data.berkas.url'))->assertDownload('baru.pdf');
        $this->get($this->url("dokumen/{$d->id}/riwayat/{$v->id}/unduh"))->assertDownload($v->nama_file_asli);
        $this->getJson($this->url("dokumen/{$d->id}/riwayat"))->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.items.0.versi', 2)->assertJsonMissingPath('data.items.0.lokasi_file');
        $r = $this->patchJson($this->url("dokumen/{$d->id}/status"), ['sidik' => $r->json('data.sidik'), 'status' => 'arsip'])->assertOk()->assertJsonPath('data.status', 'arsip');
        $this->get($r->json('data.berkas.url'))->assertDownload('baru.pdf');
        $this->patchJson($this->url("dokumen/{$d->id}/status"), ['sidik' => $r->json('data.sidik'), 'status' => 'aktif'])->assertOk()->assertJsonPath('data.status', 'aktif');
    }

    public function test_sidik_lama_tidak_menimpa_metadata_revisi_atau_status_dan_file_dibersihkan(): void
    {
        $this->token($this->humas);
        $d = $this->dokumen();
        $sidik = $this->getJson($this->url("dokumen/{$d->id}"))->json('data.sidik');
        $d->update(['judul' => 'Perubahan petugas lain']);
        $this->patchJson($this->url("dokumen/{$d->id}"), $this->inputDokumen() + ['sidik' => $sidik])->assertJsonValidationErrors('sidik');
        $this->postJson($this->url("dokumen/{$d->id}/revisi"), $this->inputDokumen() + ['sidik' => $sidik, 'berkas' => $this->pdf(), 'catatan_revisi' => 'Revisi dari data lama'])->assertJsonValidationErrors('sidik');
        $this->patchJson($this->url("dokumen/{$d->id}/status"), ['sidik' => $sidik, 'status' => 'arsip'])->assertJsonValidationErrors('sidik');
        $this->patchJson($this->url("dokumen/{$d->id}/status"), ['status' => 'arsip'])->assertJsonValidationErrors('sidik');
        $this->assertSame('Perubahan petugas lain', $d->fresh()->judul);
        $this->assertSame('aktif', $d->fresh()->status);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('riwayat_dokumen_humas', 1);
    }

    public function test_validasi_berkas_tanggal_dan_revisi_wajib_catatan(): void
    {
        $this->token($this->humas);
        foreach ([UploadedFile::fake()->create('x.html', 1, 'text/html'), $this->pdf()->size(20481)] as $f) {
            $this->postJson($this->url('dokumen'), $this->inputDokumen() + ['berkas' => $f])->assertJsonValidationErrors('berkas');
        }
        $this->postJson($this->url('dokumen'), $this->inputDokumen() + ['berkas' => $this->pdf(), 'berlaku_mulai' => '2026-10-05', 'berlaku_sampai' => '2026-10-01'])->assertJsonValidationErrors('berlaku_sampai');
        $d = $this->dokumen();
        $sidik = $this->getJson($this->url("dokumen/{$d->id}"))->json('data.sidik');
        $this->postJson($this->url("dokumen/{$d->id}/revisi"), $this->inputDokumen() + ['sidik' => $sidik, 'berkas' => $this->pdf()])->assertJsonValidationErrors('catatan_revisi');
        $this->postJson($this->url("dokumen/{$d->id}/revisi"), $this->inputDokumen() + ['sidik' => $sidik])->assertJsonValidationErrors('berkas');
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_filter_arsip_kategori_cari_masa_berlaku_dan_paginasi_sidik_konsisten(): void
    {
        $this->token($this->humas);
        $a = $this->dokumen(['judul' => 'SOP komunikasi', 'berlaku_sampai' => '2026-09-01']);
        $this->dokumen(['judul' => 'SOP arsip', 'status' => 'arsip']);
        $this->dokumen(['judul' => 'Surat orang tua', 'kategori' => 'surat_masuk', 'berlaku_sampai' => '2026-10-20']);
        $this->dokumen(['judul' => 'SOP baru', 'berlaku_sampai' => '2027-01-01']);
        $r = $this->getJson($this->url('dokumen').'?status=aktif&kata_kunci=SOP&per_halaman=1')->assertJsonPath('data.paginasi.total', 2)->assertJsonPath('data.paginasi.ada_halaman_berikutnya', true);
        $this->assertSame($r->json('data.items.0.sidik'), $this->getJson($this->url("dokumen/{$a->id}"))->json('data.sidik'));
        foreach (['kedaluwarsa' => 1, 'segera_berakhir' => 1, 'masih_berlaku' => 1, 'tanpa_batas' => 1] as $filter => $jumlah) {
            $this->getJson($this->url('dokumen').'?masa_berlaku='.$filter)->assertJsonPath('data.paginasi.total', $jumlah);
        }
        $this->getJson($this->url('dokumen').'?status=arsip')->assertJsonPath('data.paginasi.total', 1);
        $this->getJson($this->url('dokumen').'?kategori=surat_masuk')->assertJsonPath('data.paginasi.total', 1);
        $this->getJson($this->url('dokumen').'?per_halaman=51')->assertUnprocessable();
        $this->getJson($this->url('dokumen').'?kata_kunci[]=bad')->assertUnprocessable();
    }

    public function test_berkas_hilang_diluar_root_dan_versi_milik_dokumen_lain_ditolak(): void
    {
        $this->token($this->humas);
        $d = $this->dokumen();
        $v = $this->dokumen()->riwayat()->firstOrFail();
        $this->getJson($this->url("dokumen/{$d->id}/riwayat/{$v->id}/unduh"))->assertNotFound();
        Storage::disk('local')->put('outside.txt', 'PRIVATE');
        $d->update(['lokasi_file' => 'outside.txt']);
        $this->getJson($this->url("dokumen/{$d->id}/unduh"))->assertNotFound();
        $v->update(['lokasi_file' => 'outside.txt']);
        $this->getJson($this->url("dokumen/{$v->dokumen_humas_id}/riwayat/{$v->id}/unduh"))->assertNotFound();
        $d->update(['lokasi_file' => 'dokumen-humas/hilang.pdf']);
        $this->getJson($this->url("dokumen/{$d->id}/unduh"))->assertNotFound();
    }

    public function test_unggah_ke_agenda_memerlukan_izin_sumber_dan_agenda_batal_dibersihkan(): void
    {
        $a = $this->agenda();
        $this->token($this->terbatas(['dokumen_humas.kelola']));
        $this->postJson($this->url('dokumen'), $this->inputDokumen() + ['berkas' => $this->pdf(), 'agenda_humas_id' => $a->id])->assertForbidden();
        $this->token($this->humas);
        $a->update(['status' => 'dibatalkan']);
        $this->postJson($this->url('dokumen'), $this->inputDokumen() + ['berkas' => $this->pdf(), 'agenda_humas_id' => $a->id])->assertJsonValidationErrors('agenda_humas_id');
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('dokumen_humas', 0);
    }

    public function test_unggah_mou_idempoten_dan_akun_lain_ditolak(): void
    {
        $mitra = MitraHumas::create(['nama' => 'Mitra pendidikan', 'jenis' => 'pendidikan', 'status' => 'aktif']);
        $mou = new KerjaSamaHumas;
        $mou->forceFill(['mitra_humas_id' => $mitra->id, 'judul' => 'Kerja sama', 'bidang' => 'pendidikan', 'ruang_lingkup' => 'Koordinasi', 'penanggung_jawab' => 'Humas', 'status' => 'aktif',
            'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2027-06-30', 'ingatkan_hari_sebelum' => 30])->save();
        $this->token($this->humas);
        $data = $this->inputDokumen() + ['berkas' => $this->pdf(), 'kerja_sama_humas_id' => $mou->id, 'token_unggahan_mou' => (string) Str::uuid()];
        $id = $this->postJson($this->url('dokumen'), $data)->assertCreated()->json('data.id');
        $this->postJson($this->url('dokumen'), $data)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame($id, $mou->fresh()->dokumen_humas_id);
        $this->token($this->akun('wakil_pimpinan_humas'));
        $this->postJson($this->url('dokumen'), $data)->assertForbidden();
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('dokumen_humas', 1);
    }

    public function test_tautan_agenda_izin_ganda_dan_lepas_tidak_menghapus_arsip(): void
    {
        $a = $this->agenda(['status' => 'terjadwal']);
        $d = $this->dokumen();
        $path = $this->url("agenda/{$a->id}/dokumen");
        $this->token($this->terbatas(['agenda_humas.kelola']));
        $this->postJson($path, ['dokumen_humas_id' => $d->id])->assertForbidden();
        $this->getJson($path)->assertForbidden();
        $this->token($this->humas);
        $this->postJson($path, ['dokumen_humas_id' => $d->id])->assertOk();
        $this->postJson($path, ['dokumen_humas_id' => $d->id])->assertOk();
        $this->getJson($path)->assertOk()->assertJsonPath('data.paginasi.total', 1)->assertJsonMissingPath('data.items.0.pivot');
        $this->deleteJson($path.'/'.$d->id)->assertOk();
        $this->deleteJson($path.'/'.$d->id)->assertNotFound();
        $this->assertDatabaseCount('dokumen_humas', 1);
        Storage::disk('local')->assertExists($d->lokasi_file);
    }

    public function test_bundel_zip_pilihan_versi_audit_dan_tidak_mengirim_path_privat(): void
    {
        $a = $this->agenda();
        $d = $this->dokumen();
        $a->dokumen()->attach($d);
        $this->token($this->humas);
        $path = $this->url("agenda/{$a->id}/bundel");
        $s = $this->getJson($path)->assertOk()->assertJsonPath('data.siap_unduh', true)->json('data.sidik');
        $v = $this->getJson($path.'/dokumen')->assertOk()->assertJsonPath('data.paginasi.total', 1)->assertJsonMissingPath('data.items.0.lokasi_file')->json('data.items.0.id_versi');
        $r = $this->postJson($path.'/unduh', ['sidik' => $s, 'dokumen_ids' => [$v]])->assertOk()->assertHeader('Content-Type', 'application/zip')->assertHeader('X-Content-Type-Options', 'nosniff');
        $zip = new ZipArchive;
        $file = $r->baseResponse->getFile()->getPathname();
        $this->assertTrue($zip->open($file));
        $this->assertSame(5, $zip->numFiles);
        $html = $zip->getFromName('01-bundel-pertemuan.html');
        foreach (['lokasi_file', $d->lokasi_file, storage_path(), 'token_presensi', 'orang_tua_wali_id'] as $privat) {
            $this->assertStringNotContainsString($privat, $html);
        }
        $log = RiwayatBundelPertemuanHumas::firstOrFail();
        $this->assertSame(hash_file('sha256', $file), $log->sha256);
        $this->getJson($path.'/riwayat')->assertOk()->assertJsonPath('data.items.0.jumlah_lampiran', 1)->assertJsonMissingPath('data.items.0.ringkasan')->assertJsonMissingPath('data.items.0.pengguna_id');
        $zip->close();
        unlink($file);
    }

    public function test_bundel_izin_sumber_dan_izin_unduh_terpisah(): void
    {
        $a = $this->agenda();
        $d = $this->dokumen();
        $a->dokumen()->attach($d);
        $path = $this->url("agenda/{$a->id}/bundel");
        $this->token($this->terbatas(['agenda_humas.lihat']));
        $s = $this->getJson($path)->assertOk()->assertJsonPath('data.jumlah_dokumen_aktif', 0)->assertJsonPath('data.siap_unduh', false)->json('data.sidik');
        $this->postJson($path.'/unduh', ['sidik' => $s])->assertForbidden();
        $this->getJson($path.'/dokumen')->assertForbidden();
        $this->getJson($path.'/formulir')->assertForbidden();
        $this->token($this->terbatas(['agenda_humas.lihat', 'agenda_humas.bundel']));
        $s = $this->getJson($path)->json('data.sidik');
        $this->postJson($path.'/unduh', ['sidik' => $s, 'dokumen_ids' => [$d->riwayat()->first()->id]])->assertForbidden();
        $this->token($this->terbatas(['agenda_humas.bundel']));
        $this->getJson($path)->assertForbidden();
        $this->postJson($path.'/unduh', ['sidik' => $s])->assertForbidden();
    }

    public function test_bundel_draf_sidik_lama_versi_asing_dan_duplikat_tidak_membuat_audit(): void
    {
        $a = $this->agenda();
        $d = $this->dokumen();
        $a->dokumen()->attach($d);
        $foreign = $this->dokumen()->riwayat()->first();
        $v = $d->riwayat()->first();
        $this->token($this->humas);
        $path = $this->url("agenda/{$a->id}/bundel");
        $s = $this->getJson($path)->json('data.sidik');
        foreach ([[$foreign->id], [$v->id, $v->id], ['bad'], [[$v->id]]] as $ids) {
            $this->postJson($path.'/unduh', ['sidik' => $s, 'dokumen_ids' => $ids])->assertUnprocessable();
        }
        $a->peserta()->first()->update(['nama' => 'Nama dikoreksi']);
        $this->postJson($path.'/unduh', ['sidik' => $s])->assertJsonValidationErrors('bundel');
        $a->update(['status' => 'terjadwal']);
        $s = $this->getJson($path)->assertJsonPath('data.siap_unduh', false)->json('data.sidik');
        $this->postJson($path.'/unduh', ['sidik' => $s])->assertUnprocessable();
        $this->assertDatabaseCount('riwayat_bundel_pertemuan_humas', 0);
        $this->assertSame([], Storage::disk('local')->files('ekspor-pertemuan'));
    }

    public function test_bundel_formulir_hanya_rekap_sesuai_izin_dan_bukan_jawaban_privat(): void
    {
        $a = $this->agenda();
        $f = new UmpanBalikHumas;
        $f->forceFill(['token_pembuatan' => Str::uuid(), 'tahun_pelajaran_id' => $this->tahun()->id, 'judul' => 'Evaluasi pertemuan', 'pengantar' => 'Saran layanan',
            'penanggung_jawab' => 'Humas', 'cakupan' => 'agenda', 'agenda_humas_id' => $a->id, 'mulai_pada' => '2026-09-01 09:00:00', 'selesai_pada' => '2026-10-10 09:00:00', 'status' => 'aktif', 'dibuka_pada' => now()])->save();
        $this->token($this->humas);
        $path = $this->url("agenda/{$a->id}/bundel");
        $this->getJson($path.'/formulir')->assertOk()->assertJsonPath('data.items.0.id', $f->id)->assertJsonMissingPath('data.items.0.sasaran')->assertJsonMissingPath('data.items.0.jawaban');
        $f->forceFill(['status' => 'draf'])->save();
        $this->getJson($path.'/formulir')->assertJsonPath('data.paginasi.total', 0);
        $this->getJson($path.'/dokumen?per_halaman=51')->assertUnprocessable();
        $this->getJson($this->url('dokumen/bukan-id'))->assertNotFound();
    }

    public function test_middleware_token_mobile_dan_sandi_berlaku_pada_dashboard_dokumen_dan_bundel(): void
    {
        $d = $this->dokumen();
        $a = $this->agenda();
        foreach (['dashboard', "dokumen/{$d->id}/unduh", "agenda/{$a->id}/bundel"] as $path) {
            auth()->forgetGuards();
            $this->withToken($this->humas->createToken('Bukan mobile', ['other'])->plainTextToken);
            $this->getJson($this->url($path))->assertForbidden();
            $this->humas->update(['wajib_ganti_kata_sandi' => true]);
            $this->token($this->humas);
            $this->getJson($this->url($path))->assertStatus(428);
            $this->humas->update(['wajib_ganti_kata_sandi' => false]);
        }
    }

    public function test_upload_multipart_revisi_dan_format_tanggal_tidak_ambigu(): void
    {
        $this->token($this->humas);
        $input = $this->inputDokumen() + ['berkas' => $this->pdf(), 'berlaku_mulai' => '2026-09-01', 'berlaku_sampai' => '2027-06-30'];
        $r = $this->post($this->url('dokumen'), $input, ['Accept' => 'application/json'])->assertCreated();
        $id = $r->json('data.id');
        $this->post($this->url("dokumen/{$id}/revisi"), $this->inputDokumen() + [
            'sidik' => $r->json('data.sidik'), 'berkas' => $this->pdf('multipart.pdf'), 'catatan_revisi' => 'Revisi multipart',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.versi_berkas', 2);
        $s = $this->getJson($this->url("dokumen/{$id}"))->json('data.sidik');
        $this->patchJson($this->url("dokumen/{$id}"), $this->inputDokumen() + [
            'sidik' => $s, 'berkas' => $this->pdf(), 'catatan_revisi' => 'Tidak lewat rute revisi',
        ])->assertJsonValidationErrors('berkas');
        foreach (['01/09/2026', 'September 1, 2026', '2026-09-01T09:00:00'] as $tanggal) {
            $this->post($this->url('dokumen'), array_replace($input, ['berkas' => $this->pdf(), 'berlaku_mulai' => $tanggal]),
                ['Accept' => 'application/json'])->assertJsonValidationErrors('berlaku_mulai');
        }
        $this->assertDatabaseCount('dokumen_humas', 1);
        $this->assertCount(2, Storage::disk('local')->allFiles('dokumen-humas'));
    }

    public function test_revisi_web_dan_api_berbagi_riwayat_dan_menolak_sidik_sebelum_perubahan_web(): void
    {
        $d = $this->dokumen();
        $this->token($this->humas);
        $s = $this->getJson($this->url("dokumen/{$d->id}"))->json('data.sidik');
        $this->actingAs($this->humas)->put(route('dokumen-humas.update', $d), $this->inputDokumen() + [
            'berkas' => $this->pdf('dari-web.pdf'), 'catatan_revisi' => 'Revisi melalui web',
        ])->assertRedirect(route('dokumen-humas.show', $d))->assertSessionHas('berhasil', 'Informasi dan revisi dokumen berhasil disimpan.');
        $this->token($this->humas);
        $this->patchJson($this->url("dokumen/{$d->id}/status"), ['sidik' => $s, 'status' => 'arsip'])->assertJsonValidationErrors('sidik');
        $r = $this->getJson($this->url("dokumen/{$d->id}"))->assertJsonPath('data.versi_berkas', 2)->assertJsonPath('data.berkas.nama', 'dari-web.pdf');
        $this->postJson($this->url("dokumen/{$d->id}/revisi"), $this->inputDokumen() + [
            'sidik' => $r->json('data.sidik'), 'berkas' => $this->pdf('dari-api.pdf'), 'catatan_revisi' => 'Revisi melalui API',
        ])->assertOk()->assertJsonPath('data.versi_berkas', 3);
        $this->actingAs($this->humas)->get(route('dokumen-humas.show', $d))->assertOk()->assertSee('dari-api.pdf');
        $this->patch(route('dokumen-humas.status', $d), ['status' => 'arsip'])->assertRedirect()->assertSessionHas('berhasil', 'Dokumen dipindahkan ke arsip.');
        $this->token($this->humas);
        $this->getJson($this->url("dokumen/{$d->id}"))->assertJsonPath('data.status', 'arsip');
        $this->get($this->url("dokumen/{$d->id}/unduh"))->assertDownload('dari-api.pdf');
        $this->assertDatabaseCount('riwayat_dokumen_humas', 3);
    }

    public function test_bundel_harus_muat_ulang_setelah_revisi_atau_arsip_dokumen(): void
    {
        $a = $this->agenda();
        $d = $this->dokumen();
        $a->dokumen()->attach($d);
        $this->token($this->humas);
        $path = $this->url("agenda/{$a->id}/bundel");
        $s = $this->getJson($path)->json('data.sidik');
        $lama = $d->riwayat()->firstOrFail()->id;
        $ds = $this->getJson($this->url("dokumen/{$d->id}"))->json('data.sidik');
        $r = $this->postJson($this->url("dokumen/{$d->id}/revisi"), $this->inputDokumen() + [
            'sidik' => $ds, 'berkas' => $this->pdf(), 'catatan_revisi' => 'Lampiran yang diperbarui',
        ])->assertOk();
        $this->postJson($path.'/unduh', ['sidik' => $s])->assertJsonValidationErrors('bundel');
        $s = $this->getJson($path)->json('data.sidik');
        $this->postJson($path.'/unduh', ['sidik' => $s, 'dokumen_ids' => [$lama]])->assertJsonValidationErrors('dokumen_ids.0');
        $this->patchJson($this->url("dokumen/{$d->id}/status"), ['sidik' => $r->json('data.sidik'), 'status' => 'arsip'])->assertOk();
        $this->postJson($path.'/unduh', ['sidik' => $s])->assertJsonValidationErrors('bundel');
        $this->getJson($path.'/dokumen')->assertJsonPath('data.paginasi.total', 0);
        $this->assertDatabaseCount('riwayat_bundel_pertemuan_humas', 0);
        $this->assertSame([], Storage::disk('local')->files('ekspor-pertemuan'));
    }

    public function test_rekap_bundel_dengan_jawaban_asli_tidak_membocorkan_identitas_atau_teks(): void
    {
        $a = $this->agenda();
        $f = new UmpanBalikHumas;
        $f->forceFill(['token_pembuatan' => Str::uuid(), 'tahun_pelajaran_id' => $this->tahun()->id, 'judul' => 'Evaluasi rapat',
            'pengantar' => 'Evaluasi', 'penanggung_jawab' => 'Humas', 'cakupan' => 'agenda', 'agenda_humas_id' => $a->id,
            'mulai_pada' => '2026-09-01', 'selesai_pada' => '2026-10-10', 'status' => 'aktif', 'dibuka_pada' => now()])->save();
        $ortu = OrangTuaWali::create(['pengguna_id' => $this->akun('orang_tua')->id, 'nama_lengkap' => 'IDENTITAS PENGISI RAHASIA', 'nomor_wa' => '081234567899']);
        $skala = $f->pertanyaan()->create(['urutan' => 1, 'jenis' => 'skala', 'teks' => 'Kejelasan informasi', 'wajib' => true]);
        $teks = $f->pertanyaan()->create(['urutan' => 2, 'jenis' => 'teks', 'teks' => 'Saran layanan', 'wajib' => false]);
        $target = $f->sasaran()->create(['orang_tua_wali_id' => $ortu->id, 'siswa_ids' => [123]]);
        $target->forceFill(['dikirim_pada' => now(), 'token_pengiriman' => Str::uuid()])->save();
        $target->jawaban()->create(['pertanyaan_umpan_balik_humas_id' => $skala->id, 'nilai' => 4]);
        $target->jawaban()->create(['pertanyaan_umpan_balik_humas_id' => $teks->id, 'teks' => 'MASUKAN TEKS RAHASIA']);
        $this->token($this->humas);
        $path = $this->url("agenda/{$a->id}/bundel");
        $r = $this->getJson($path.'/formulir')->assertJsonPath('data.items.0.jumlah_respons', 1);
        foreach (['IDENTITAS PENGISI RAHASIA', 'MASUKAN TEKS RAHASIA', '081234567899', 'token_pengiriman'] as $privat) {
            $this->assertStringNotContainsString($privat, $r->getContent());
        }
        $s = $this->getJson($path)->json('data.sidik');
        $this->postJson($path.'/unduh', ['sidik' => $s, 'formulir_ids' => [999]])->assertJsonValidationErrors('formulir_ids.0');
        $r = $this->postJson($path.'/unduh', ['sidik' => $s, 'formulir_ids' => [$f->id]])->assertOk();
        $file = $r->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($file));
        $html = $zip->getFromName('01-bundel-pertemuan.html');
        foreach (['IDENTITAS PENGISI RAHASIA', 'MASUKAN TEKS RAHASIA', '081234567899', 'token_pengiriman'] as $privat) {
            $this->assertStringNotContainsString($privat, $html);
        }
        $this->assertStringContainsString('Kejelasan informasi', $html);
        $zip->close();
        unlink($file);
        $this->token($this->terbatas(['agenda_humas.lihat']));
        $this->getJson($path.'/riwayat')->assertOk()->assertJsonMissingPath('data.items.0.jumlah_formulir')
            ->assertJsonMissingPath('data.items.0.jumlah_lampiran')->assertJsonMissingPath('data.items.0.ringkasan');
    }

    public function test_batas_bundel_dan_upload_terpisah_tidak_menghalangi_baca(): void
    {
        $a = $this->agenda();
        $this->token($this->humas);
        $this->withMiddleware(ThrottleRequests::class);
        $path = $this->url("agenda/{$a->id}/bundel");
        $s = $this->getJson($path)->json('data.sidik');
        for ($i = 0; $i < 6; $i++) {
            $r = $this->postJson($path.'/unduh', ['sidik' => $s])->assertOk();
            unlink($r->baseResponse->getFile()->getPathname());
        }
        $this->postJson($path.'/unduh', ['sidik' => $s])->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('riwayat_bundel_pertemuan_humas', 6);
        for ($i = 0; $i < 15; $i++) {
            $this->post($this->url('dokumen'), $this->inputDokumen() + ['berkas' => $this->pdf()], ['Accept' => 'application/json'])->assertCreated();
        }
        $this->post($this->url('dokumen'), $this->inputDokumen() + ['berkas' => $this->pdf()], ['Accept' => 'application/json'])->assertStatus(429);
        $this->getJson($path)->assertOk();
        $this->getJson($this->url('dokumen'))->assertOk()->assertJsonPath('data.paginasi.total', 15);
        $this->assertDatabaseCount('dokumen_humas', 15);
        $this->assertCount(15, Storage::disk('local')->allFiles('dokumen-humas'));
        $this->assertSame([], Storage::disk('local')->files('ekspor-pertemuan'));
    }

    private function url(string $path): string
    {
        return '/api/v1/humas/'.$path;
    }

    private function token(Pengguna $u): void
    {
        auth()->forgetGuards();
        $this->withToken($u->createToken('Android arsip', ['mobile'])->plainTextToken);
    }

    private function akun(string $role): Pengguna
    {
        $u = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'arsip.api.'.Str::uuid(), 'kata_sandi' => 'TesArsip123!', 'aktif' => true, 'peran' => 'pegawai', 'wajib_ganti_kata_sandi' => false]);
        $u->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $u;
    }

    private function terbatas(array $izin): Pengguna
    {
        $role = Peran::create(['kode' => 'arsip_'.Str::random(10), 'nama' => 'Arsip terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $u = $this->akun('pegawai');
        $u->daftarPeran()->sync([$role->id]);

        return $u;
    }

    private function tahun(): TahunPelajaran
    {
        return TahunPelajaran::firstOrCreate(['nama' => '2026/2027'], ['tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
    }

    private function periode(array $ubah = []): array
    {
        return array_replace(['periode' => 'kustom', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-30'], $ubah);
    }

    private function inputDokumen(): array
    {
        return ['judul' => 'SOP komunikasi sekolah', 'kategori' => 'sop', 'deskripsi' => 'Tata layanan sekolah', 'ingatkan_hari_sebelum' => 30];
    }

    private function pdf(string $nama = 'bukti.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, "%PDF-1.4\nDATA BERKAS\n%%EOF");
    }

    private function dokumen(array $ubah = []): DokumenHumas
    {
        $path = 'dokumen-humas/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, 'DATA BERKAS');
        $d = DokumenHumas::create(array_replace($this->inputDokumen() + ['status' => 'aktif', 'lokasi_file' => $path, 'nama_file_asli' => 'bukti.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => 11], $ubah));
        $d->riwayat()->create(['versi' => 1, 'lokasi_file' => $path, 'nama_file_asli' => 'bukti.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => 11, 'catatan' => 'Versi awal', 'diunggah_pada' => now()]);

        return $d;
    }

    private function agenda(array $ubah = []): AgendaHumas
    {
        $a = AgendaHumas::create(array_replace(['judul' => 'Pertemuan sekolah', 'jenis' => 'orang_tua', 'waktu_mulai' => '2026-09-10 09:00:00', 'waktu_selesai' => '2026-09-10 11:00:00', 'tempat' => 'Aula',
            'topik' => 'Pertemuan', 'pembahasan' => 'Pembahasan rapat', 'keputusan' => 'Keputusan rapat', 'status' => 'selesai'], $ubah));
        $a->peserta()->create(['nama' => 'Peserta rapat', 'status_kehadiran' => 'hadir']);

        return $a;
    }
}
