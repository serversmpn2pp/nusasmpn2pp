<?php

namespace Tests\Feature;

use App\Models\FaqHumas;
use App\Models\Izin;
use App\Models\KinerjaSemesterHumas;
use App\Models\LaporanPelaksanaanHumas;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\PengaduanHumas;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\ProgramKerjaHumas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UmpanBalikHumas;
use App\Services\Humas\NavigasiHumasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class FaqKinerjaHumasTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $humas;

    private TahunPelajaran $tahun;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->humas = $this->akun('wakil_pimpinan_humas');
        $this->tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $this->actingAs($this->humas);
    }

    public function test_faq_mulai_draf_penerbitan_edit_dan_arsip_terpisah_dengan_versi_audit(): void
    {
        $input = $this->faqInput();
        $this->token($this->humas);
        $this->postJson(route('api.v1.humas.faq.store'), $input)->assertCreated()->assertJsonPath('data.status', 'draf');
        $this->postJson(route('api.v1.humas.faq.store'), $input)->assertOk();
        $f = FaqHumas::firstOrFail();
        $this->assertSame(1, FaqHumas::count());
        $this->patchJson(route('api.v1.humas.faq.status', $f), ['versi' => 0, 'status' => 'terbit', 'alasan' => 'Informasi sudah diperiksa'])->assertOk()->assertJsonPath('data.versi', 1);
        $edit = collect($input)->except('token_pembuatan')->all() + ['versi' => 1, 'alasan' => 'Koreksi informasi layanan'];
        $this->patchJson(route('api.v1.humas.faq.update', $f), $edit)->assertOk()->assertJsonPath('data.status', 'draf')->assertJsonPath('data.diterbitkan_pada', null);
        $this->patchJson(route('api.v1.humas.faq.update', $f), $edit)->assertUnprocessable()->assertJsonValidationErrors('versi');
        $this->patchJson(route('api.v1.humas.faq.status', $f), ['versi' => 2, 'status' => 'arsip', 'alasan' => 'Informasi tidak berlaku'])->assertOk();
        $this->patchJson(route('api.v1.humas.faq.status', $f), ['versi' => 3, 'status' => 'terbit', 'alasan' => 'Coba menerbitkan langsung'])->assertUnprocessable();
        $this->getJson(route('api.v1.humas.faq.riwayat', $f))->assertOk()->assertJsonPath('data.paginasi.total', 4);
        $this->assertSame(4, DB::table('riwayat_faq_humas')->count());
        $this->token($this->akun('wakil_pimpinan_humas'));
        $this->postJson(route('api.v1.humas.faq.store'), $input)->assertForbidden();
    }

    public function test_pembaca_faq_dibatasi_identitas_akun_bukan_role_dan_pencarian_tidak_bocor(): void
    {
        foreach (['semua', 'pegawai', 'orang_tua', 'siswa'] as $s) {
            $this->faq(['pertanyaan' => 'Informasi '.$s, 'sasaran' => $s]);
        }
        $rahasia = $this->faq(['pertanyaan' => 'RAHASIA DRAF', 'status' => 'draf']);
        $staff = $this->akun('pegawai');
        $ortu = $this->keluarga('ortu');
        $siswa = $this->keluarga('siswa');
        foreach ([[$staff, 'pegawai'], [$ortu, 'orang_tua'], [$siswa, 'siswa']] as [$u,$sasaran]) {
            $u->daftarPeran()->attach(Peran::where('kode', 'wakil_pimpinan_humas')->value('id'));
            if ($sasaran === 'pegawai') {
                $u->daftarPeran()->sync([Peran::where('kode', 'pegawai')->value('id')]);
            }
            $this->token($u);
            $this->getJson(route('api.v1.humas.faq.index'))->assertOk()->assertJsonPath('data.paginasi.total', 2)->assertJsonPath('data.dapat_kelola', false);
            $this->getJson(route('api.v1.humas.faq.show', $rahasia))->assertNotFound();
            $this->getJson(route('api.v1.humas.faq.index', ['kata_kunci' => 'RAHASIA']))->assertJsonPath('data.paginasi.total', 0);
            $this->postJson(route('api.v1.humas.faq.store'), $this->faqInput())->assertForbidden();
            $this->getJson(route('api.v1.humas.faq.riwayat', $rahasia))->assertForbidden();
            $this->actingAs($u)->get(route('faq-humas.index'))->assertOk()->assertSee('Informasi '.$sasaran)->assertDontSee('RAHASIA DRAF')->assertDontSee('Tambah FAQ');
        }
    }

    public function test_faq_validasi_filter_urusan_xss_dan_menu_keluarga(): void
    {
        $f = $this->faq(['pertanyaan' => '<script>window.injected=true</script>', 'jawaban' => '<img src=x onerror=alert(1)> Teks jawaban']);
        $this->get(route('faq-humas.show', $f))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>window.injected=true</script>', false);
        $this->token($this->humas);
        $this->getJson(route('api.v1.humas.faq.index', ['urusan' => 'komite']))->assertJsonPath('data.paginasi.total', 0);
        $this->getJson(route('api.v1.humas.faq.index', ['urusan' => 'tidak_valid']))->assertUnprocessable();
        $this->getJson(route('api.v1.humas.faq.referensi'))->assertOk()->assertJsonPath('data.dapat_kelola', true);
        $u = $this->keluarga('ortu');
        $this->actingAs($u)->get(route('faq-humas.index'))->assertOk()->assertSee('FAQ &amp; Bantuan', false);
        $this->assertSame([], app(NavigasiHumasService::class)->sidebar($u));
        $this->assertContains('kinerja-humas', array_column(app(NavigasiHumasService::class)->sidebar($this->humas), 'kode'));
    }

    public function test_kinerja_memilih_tahun_semester_tahunan_tanggal_dan_status_dengan_tepat(): void
    {
        $ganjil = $this->program(['status' => 'selesai']);
        $this->laporan($ganjil);
        $tahunan = $this->program(['semester' => 'tahunan', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $this->laporan($tahunan);
        $this->laporan($ganjil, ['status' => 'draf']);
        $this->laporan($ganjil, ['tanggal_selesai' => '2026-10-23']);
        $this->laporan($ganjil, ['tanggal_selesai' => '2026-06-30']);
        $this->laporan($this->program(['semester' => 'genap', 'nama' => 'PROGRAM GENAP SALAH']));
        $this->laporan($this->program(['status' => 'dibatalkan', 'nama' => 'PROGRAM BATAL']));
        $lain = TahunPelajaran::create(['nama' => '2025/2026', 'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'aktif' => false]);
        $this->laporan($this->program(['tahun_pelajaran_id' => $lain->id, 'nama' => 'PROGRAM TAHUN LAIN']));
        $this->token($this->humas);
        $r = $this->getJson(route('api.v1.humas.kinerja.pratinjau', $this->periode()))->assertOk()->assertJsonPath('data.jumlah_program', 2)
            ->assertJsonPath('data.metrik.program.jumlah', 1)->assertJsonPath('data.metrik.laporan.jumlah', 2)->assertJsonPath('data.bulan.2026-09.jumlah.laporan', 2)
            ->assertJsonPath('data.data_sampai', '2026-10-22')->assertJsonPath('data.sementara', true);
        $this->assertStringNotContainsString('PROGRAM GENAP SALAH', $r->getContent());
        $this->getJson(route('api.v1.humas.kinerja.pratinjau', $this->periode('genap')))->assertUnprocessable()->assertJsonValidationErrors('semester');
    }

    public function test_laporan_memuat_semua_program_bukan_hanya_delapan_dan_menolak_lebih_dari_500(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->program(['nama' => 'Program '.$i]);
        }
        $this->token($this->humas);
        $this->getJson(route('api.v1.humas.kinerja.pratinjau', $this->periode()))->assertOk()->assertJsonCount(12, 'data.program');
        $template = ProgramKerjaHumas::first()->getAttributes();
        unset($template['id']);
        $rows = [];
        for ($i = 12; $i < 501; $i++) {
            $rows[] = array_replace($template, ['token_pembuatan' => (string) Str::uuid(), 'nama' => 'Tambahan '.$i]);
        }
        DB::table('program_kerja_humas')->insert($rows);
        $this->postJson(route('api.v1.humas.kinerja.store'), $this->laporanInput())->assertUnprocessable()->assertJsonValidationErrors('semester');
        $this->assertSame(0, KinerjaSemesterHumas::count());
    }

    public function test_kinerja_snapshot_beku_final_koreksi_versi_audit_dan_idempotensi(): void
    {
        $p = $this->program();
        $this->laporan($p);
        $this->token($this->humas);
        $input = $this->laporanInput();
        $r = $this->postJson(route('api.v1.humas.kinerja.store'), $input)->assertCreated()->assertJsonPath('data.snapshot.metrik.laporan.jumlah', 1);
        $l = KinerjaSemesterHumas::findOrFail($r->json('data.id'));
        $this->postJson(route('api.v1.humas.kinerja.store'), $input)->assertOk();
        $this->postJson(route('api.v1.humas.kinerja.store'), $this->laporanInput())->assertUnprocessable()->assertJsonValidationErrors('semester');
        $this->aksi($l, 'finalisasi', 0)->assertUnprocessable()->assertJsonValidationErrors('konfirmasi_sementara');
        $this->aksi($l, 'finalisasi', 0, ['konfirmasi_sementara' => true])->assertOk()->assertJsonPath('data.status', 'final');
        $beku = $l->fresh()->snapshot;
        $this->laporan($p);
        $this->getJson(route('api.v1.humas.kinerja.show', $l))->assertJsonPath('data.snapshot.metrik.laporan.jumlah', 1);
        $this->aksi($l, 'perbarui-data', 1)->assertUnprocessable();
        $edit = collect($input)->except(['token_pembuatan', 'tahun_pelajaran_id', 'semester'])->all() + ['versi' => 1, 'alasan' => 'Perbaikan evaluasi laporan'];
        $this->patchJson(route('api.v1.humas.kinerja.update', $l), $edit)->assertUnprocessable();
        $this->aksi($l, 'buka-revisi', 1)->assertOk()->assertJsonPath('data.versi', 2);
        $edit['versi'] = 2;
        $this->patchJson(route('api.v1.humas.kinerja.update', $l), $edit)->assertOk();
        $this->assertSame($beku, $l->fresh()->snapshot);
        $this->aksi($l, 'perbarui-data', 2)->assertUnprocessable()->assertJsonValidationErrors('versi');
        $this->aksi($l, 'perbarui-data', 3)->assertOk()->assertJsonPath('data.snapshot.metrik.laporan.jumlah', 2);
        $this->getJson(route('api.v1.humas.kinerja.riwayat', $l))->assertJsonPath('data.paginasi.total', 5);
        $this->assertSame(1, KinerjaSemesterHumas::count());
        $audit = json_decode(DB::table('riwayat_kinerja_semester_humas')->where('aksi', 'finalisasi')->value('data'), true);
        $this->assertSame($beku, $audit['snapshot']);
    }

    public function test_finalisasi_memerlukan_narasi_dan_tanggal_laporan_tidak_mendahului_data(): void
    {
        $this->token($this->humas);
        $this->postJson(route('api.v1.humas.kinerja.store'), array_replace($this->laporanInput(), ['tanggal_laporan' => '2026-09-01']))->assertUnprocessable()->assertJsonValidationErrors('tanggal_laporan');
        $r = $this->postJson(route('api.v1.humas.kinerja.store'), array_replace($this->laporanInput(), ['kendala' => null]))->assertCreated();
        $l = KinerjaSemesterHumas::findOrFail($r->json('data.id'));
        $this->aksi($l, 'finalisasi', 0, ['konfirmasi_sementara' => true])->assertUnprocessable()->assertJsonValidationErrors('kendala');
        $this->assertSame('draf', $l->fresh()->status);
    }

    public function test_genap_batas_januari_dan_laporan_semester_selesai_tidak_perlu_konfirmasi_sementara(): void
    {
        $this->travelTo(now()->setDate(2027, 7, 5));
        $g = $this->program(['semester' => 'genap', 'tanggal_mulai' => '2027-01-01', 'tanggal_selesai' => '2027-06-30']);
        $this->laporan($g, ['tanggal_selesai' => '2026-12-31']);
        $this->laporan($g, ['tanggal_selesai' => '2027-01-01']);
        $this->laporan($g, ['tanggal_selesai' => '2027-06-30']);
        $this->laporan($g, ['tanggal_selesai' => '2027-07-01']);
        $this->token($this->humas);
        $r = $this->postJson(route('api.v1.humas.kinerja.store'), array_replace($this->laporanInput(), ['semester' => 'genap', 'tanggal_laporan' => '2027-07-05']))->assertCreated()
            ->assertJsonPath('data.snapshot.sementara', false)->assertJsonPath('data.snapshot.metrik.laporan.jumlah', 2)->assertJsonPath('data.snapshot.data_sampai', '2027-06-30');
        $this->aksi(KinerjaSemesterHumas::findOrFail($r->json('data.id')), 'finalisasi', 0)->assertOk();
    }

    public function test_pembaca_terbatas_tidak_menerima_sumber_terlarang_dalam_web_api_atau_cetak(): void
    {
        $this->program(['nama' => 'PROGRAM TERBATAS']);
        PengaduanHumas::create(['token_pembuatan' => Str::uuid(), 'judul' => 'JUDUL RAHASIA', 'jenis' => 'pengaduan', 'kategori' => 'layanan', 'kanal' => 'telepon', 'tanggal_diterima' => '2026-09-20', 'isi' => 'ISI RAHASIA', 'nama_pelapor' => 'IDENTITAS RAHASIA', 'prioritas' => 'normal', 'status' => 'baru']);
        $this->post(route('kinerja-humas.store'), $this->laporanInput())->assertRedirect();
        $l = KinerjaSemesterHumas::firstOrFail();
        $this->assertSame(1, $l->snapshot['metrik']['pengaduan_masuk']['jumlah']);
        $this->assertStringNotContainsString('RAHASIA', json_encode($l->snapshot));
        $u = $this->akun(null, ['kinerja_humas.lihat', 'kinerja_humas.kelola', 'prestasi_sekolah.lihat', 'pengaduan_humas.tangani']);
        $this->token($u);
        $r = $this->getJson(route('api.v1.humas.kinerja.show', $l))->assertOk()->assertJsonPath('data.snapshot.akses_dibatasi', true)->assertJsonPath('data.dapat_kelola', false)->assertJsonMissingPath('data.snapshot.metrik.pengaduan_masuk');
        $this->assertStringNotContainsString('PROGRAM TERBATAS', $r->getContent());
        $this->aksi($l, 'finalisasi', 0, ['konfirmasi_sementara' => true])->assertForbidden();
        $this->actingAs($u)->get(route('kinerja-humas.show', $l))->assertOk()->assertDontSee('PROGRAM TERBATAS')->assertDontSee('Aspirasi / aduan diterima')->assertDontSee('Finalisasi laporan');
        $this->get(route('kinerja-humas.cetak', $l))->assertOk()->assertSee('SALINAN TERBATAS')->assertDontSee('PROGRAM TERBATAS');
        $this->get(route('kinerja-humas.edit', $l))->assertForbidden();
        $this->token($u);
        $this->getJson(route('api.v1.humas.kinerja.pratinjau', $this->periode()))->assertForbidden();
    }

    public function test_izin_sumber_dicabut_setelah_final_tetap_diperiksa_saat_dibaca(): void
    {
        $this->program(['nama' => 'PROGRAM SEBELUM PENCABUTAN']);
        $this->post(route('kinerja-humas.store'), $this->laporanInput())->assertRedirect();
        $l = KinerjaSemesterHumas::firstOrFail();
        $this->token($this->humas);
        $this->aksi($l, 'finalisasi', 0, ['konfirmasi_sementara' => true])->assertOk();
        $pimpinan = $this->akun('pimpinan');
        $this->token($pimpinan);
        $this->getJson(route('api.v1.humas.kinerja.show', $l))->assertOk()->assertJsonPath('data.dapat_kelola', false);
        $r = Peran::create(['kode' => 'lihat_saja', 'nama' => 'Lihat saja', 'aktif' => true]);
        $r->izin()->attach(Izin::where('kode', 'kinerja_humas.lihat')->value('id'));
        $this->humas->daftarPeran()->sync([$r->id]);
        $this->token($this->humas->fresh());
        $this->getJson(route('api.v1.humas.kinerja.show', $l))->assertOk()->assertJsonPath('data.snapshot.program', [])->assertJsonPath('data.snapshot.akses_dibatasi', true);
        $this->getJson(route('api.v1.humas.kinerja.cetak', $l))->assertOk()->assertDontSee('PROGRAM SEBELUM PENCABUTAN');
    }

    public function test_keluarga_dengan_role_humas_tetap_tidak_boleh_membaca_laporan(): void
    {
        $this->post(route('kinerja-humas.store'), $this->laporanInput())->assertRedirect();
        $l = KinerjaSemesterHumas::firstOrFail();
        foreach (['siswa', 'ortu'] as $jenis) {
            $u = $this->keluarga($jenis);
            $u->daftarPeran()->attach(Peran::where('kode', 'wakil_pimpinan_humas')->value('id'));
            $this->actingAs($u)->get(route('kinerja-humas.index'))->assertForbidden();
            $this->get(route('kinerja-humas.cetak', $l))->assertForbidden();
            $this->token($u);
            $this->getJson(route('api.v1.humas.kinerja.index'))->assertForbidden();
            $this->getJson(route('api.v1.humas.kinerja.show', $l))->assertForbidden();
        }
    }

    public function test_api_membutuhkan_token_mobile_dan_akun_aktif(): void
    {
        auth()->forgetGuards();
        $this->getJson(route('api.v1.humas.faq.index'))->assertUnauthorized();
        $this->withToken($this->humas->createToken('Salah', ['lainnya'])->plainTextToken)->getJson(route('api.v1.humas.kinerja.index'))->assertForbidden();
        $this->humas->update(['aktif' => false]);
        $this->token($this->humas);
        $this->getJson(route('api.v1.humas.faq.index'))->assertUnauthorized();
    }

    public function test_evaluasi_dan_respons_dihitung_tanpa_identitas_atau_teks_jawaban(): void
    {
        $f = new UmpanBalikHumas;
        $f->forceFill(['token_pembuatan' => Str::uuid(), 'tahun_pelajaran_id' => $this->tahun->id, 'judul' => 'FORMULIR PRIVAT',
            'pengantar' => 'PENGANTAR PRIVAT', 'penanggung_jawab' => 'PJ Uji', 'cakupan' => 'semua', 'mulai_pada' => '2026-09-01 08:00:00',
            'selesai_pada' => '2026-10-01 08:00:00', 'status' => 'ditutup', 'dibuka_pada' => '2026-09-01 08:00:00'])->save();
        foreach (['2026-09-20', '2026-10-23', null] as $tanggal) {
            $sasaran = $f->sasaran()->create(['siswa_ids' => [999]]);
            $sasaran->forceFill(['dikirim_pada' => $tanggal])->save();
        }
        $this->token($this->humas);
        $r = $this->getJson(route('api.v1.humas.kinerja.pratinjau', $this->periode()))->assertOk()
            ->assertJsonPath('data.metrik.evaluasi.jumlah', 1)->assertJsonPath('data.metrik.respons_evaluasi.jumlah', 1);
        $this->assertStringNotContainsString('PRIVAT', $r->getContent());
        $this->assertStringNotContainsString('siswa_ids', $r->getContent());
    }

    public function test_rute_rusak_dan_parameter_tindakan_tidak_menjadi_mutasi(): void
    {
        $this->token($this->humas);
        $this->getJson('/api/v1/humas/faq/bukan-angka')->assertNotFound();
        $this->getJson('/api/v1/humas/kinerja/999999999999999999999')->assertNotFound();
        $r = $this->postJson(route('api.v1.humas.kinerja.store'), $this->laporanInput())->assertCreated();
        $id = $r->json('data.id');
        $this->postJson('/api/v1/humas/kinerja/'.$id.'/tindakan/hapus', ['versi' => 0])->assertNotFound();
        $this->getJson(route('api.v1.humas.kinerja.index'))->assertOk()->assertJsonPath('data.paginasi.total', 1);
        $this->getJson(route('api.v1.humas.kinerja.referensi'))->assertOk()->assertJsonPath('data.dapat_kelola', true);
        $this->assertSame(0, KinerjaSemesterHumas::findOrFail($id)->versi);
    }

    public function test_fixture_web_form_cetak_dan_status_final(): void
    {
        $this->capture('faq-empty', $this->get(route('faq-humas.index'))->assertOk()->getContent());
        $this->capture('report-empty', $this->get(route('kinerja-humas.index'))->assertOk()->getContent());
        for ($i = 0; $i < 6; $i++) {
            $this->faq(['pertanyaan' => 'Bagaimana pelaksanaan koordinasi sekolah bersama orang tua dan mitra pada semester ini? '.$i]);
        }
        $f = FaqHumas::firstOrFail();
        $this->capture('faq', $this->get(route('faq-humas.index'))->assertOk()->getContent());
        $this->capture('faq-form', $this->get(route('faq-humas.edit', $f))->assertOk()->getContent());
        $this->capture('faq-detail', $this->get(route('faq-humas.show', $f))->assertOk()->getContent());
        for ($i = 0; $i < 10; $i++) {
            $this->laporan($this->program(['nama' => 'Program koordinasi pengembangan sekolah bersama komite, orang tua dan masyarakat '.$i]));
        }
        $this->post(route('kinerja-humas.store'), $this->laporanInput())->assertSessionHasNoErrors()->assertRedirect();
        $l = KinerjaSemesterHumas::firstOrFail();
        session()->forget(['berhasil', 'errors']);
        $this->capture('report', $this->get(route('kinerja-humas.show', $l))->assertOk()->getContent());
        $this->capture('report-list', $this->get(route('kinerja-humas.index'))->assertOk()->getContent());
        $this->capture('report-form', $this->get(route('kinerja-humas.edit', $l))->assertOk()->getContent());
        $this->capture('report-create', $this->get(route('kinerja-humas.create'))->assertOk()->getContent());
        $this->capture('print', $this->get(route('kinerja-humas.cetak', $l))->assertOk()->getContent());
        $this->post(route('kinerja-humas.tindakan', [$l, 'finalisasi']), ['versi' => 0, 'alasan' => 'Pemeriksaan laporan selesai', 'konfirmasi_sementara' => true])->assertSessionHasNoErrors()->assertRedirect();
        $this->capture('report-final', $this->get(route('kinerja-humas.show', $l))->assertOk()->getContent());
        $this->actingAs($this->keluarga('ortu'));
        $this->capture('faq-parent', $this->get(route('faq-humas.index'))->assertOk()->getContent());
    }

    private function akun(?string $kode, array $izin = []): Pengguna
    {
        $p = Pegawai::create(['nama_lengkap' => 'Petugas Humas Uji', 'aktif' => true]);
        $u = Pengguna::create(['nama' => 'Petugas Humas Uji', 'username' => 'faq.'.Str::uuid(), 'kata_sandi' => 'UjiHumas2026!', 'peran' => 'pegawai', 'aktif' => true, 'pegawai_id' => $p->id, 'wajib_ganti_kata_sandi' => false]);
        $role = $kode ? Peran::where('kode', $kode)->firstOrFail() : Peran::create(['kode' => 'faq_'.Str::random(10), 'nama' => 'Izin terbatas', 'aktif' => true]);
        if ($izin) {
            $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        }
        $u->daftarPeran()->sync([$role->id]);

        return $u;
    }

    private function keluarga(string $jenis): Pengguna
    {
        $u = $this->akun('pegawai');
        $u->update(['pegawai_id' => null]);
        if ($jenis === 'siswa') {
            $u->update(['siswa_id' => Siswa::create(['nama_lengkap' => 'Siswa Uji', 'aktif' => true])->id]);
        } else {
            OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => 'Orang Tua Uji']);
        }

        return $u->fresh();
    }

    private function faqInput(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'pertanyaan' => 'Bagaimana menghubungi sekolah?', 'jawaban' => 'Hubungi operator sekolah pada jam layanan yang ditetapkan.', 'urusan' => 'kedinasan', 'sasaran' => 'semua', 'urutan' => 0];
    }

    private function faq(array $data = []): FaqHumas
    {
        $f = new FaqHumas;
        $f->forceFill(array_replace($this->faqInput(), ['status' => 'terbit', 'diterbitkan_pada' => now()], $data))->save();

        return $f;
    }

    private function periode(string $s = 'ganjil'): array
    {
        return ['tahun_pelajaran_id' => $this->tahun->id, 'semester' => $s];
    }

    private function laporanInput(): array
    {
        return $this->periode() + ['token_pembuatan' => (string) Str::uuid(), 'judul' => 'Laporan Kinerja Humas Semester Ganjil 2026/2027', 'tanggal_laporan' => '2026-10-22', 'nama_penyusun' => 'Wakil Humas Uji', 'nama_pimpinan' => 'Kepala Sekolah Uji', 'ringkasan' => 'Ringkasan kegiatan semester berjalan.', 'capaian' => 'Capaian kegiatan dan koordinasi bersama.', 'kendala' => 'Kendala jadwal pertemuan bersama mitra.', 'tindak_lanjut' => 'Koordinasi lanjutan pada bulan berikutnya.', 'rekomendasi' => 'Pertahankan koordinasi bersama orang tua.'];
    }

    private function program(array $d = []): ProgramKerjaHumas
    {
        $p = new ProgramKerjaHumas;
        $p->forceFill(array_replace(['token_pembuatan' => Str::uuid(), 'tahun_pelajaran_id' => $this->tahun->id, 'semester' => 'ganjil', 'nama' => 'Program Humas', 'bidang' => 'orang_tua', 'tujuan' => 'Koordinasi sekolah', 'sasaran' => 'Orang tua', 'target_hasil' => 'Pertemuan bersama', 'target_kegiatan' => 3, 'penanggung_jawab' => 'Waka Humas', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2026-12-31', 'status' => 'berjalan'], $d))->save();

        return $p;
    }

    private function laporan(ProgramKerjaHumas $p, array $d = []): void
    {
        $l = new LaporanPelaksanaanHumas;
        $l->forceFill(array_replace(['token_pembuatan' => Str::uuid(), 'program_kerja_humas_id' => $p->id, 'judul' => 'Laporan kegiatan', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-20', 'tempat' => 'Sekolah', 'pelaksana' => 'Humas', 'jumlah_peserta' => 10, 'uraian' => 'Pertemuan bersama', 'hasil' => 'Kesepakatan program', 'status' => 'final'], $d))->save();
    }

    private function token(Pengguna $u): void
    {
        auth()->forgetGuards();
        $this->withToken($u->createToken('Uji FAQ Kinerja', ['mobile'])->plainTextToken);
    }

    private function aksi(KinerjaSemesterHumas $l, string $a, int $v, array $data = [])
    {
        return $this->postJson(route('api.v1.humas.kinerja.tindakan', [$l, $a]), ['versi' => $v, 'alasan' => 'Pemeriksaan laporan semester'] + $data);
    }

    private function capture(string $name, string $html): void
    {
        if (getenv('NUSA_CAPTURE_FAQ_KINERJA') !== '1') {
            return;
        }$p = storage_path('framework/testing/faq-kinerja-humas');
        File::ensureDirectoryExists($p);
        File::put($p.'/'.$name.'.html', $html);
    }
}
