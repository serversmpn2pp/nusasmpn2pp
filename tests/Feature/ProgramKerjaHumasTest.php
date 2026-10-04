<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\BuktiLaporanHumas;
use App\Models\Izin;
use App\Models\LaporanPelaksanaanHumas;
use App\Models\OrangTuaWali;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\ProgramKerjaHumas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProgramKerjaHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
        Storage::fake('local');
        $this->actingAs($this->akun());
    }

    public function test_izin_humas_pimpinan_admin_dan_akun_lain(): void
    {
        $p = $this->program();
        $l = $this->laporan($p);
        foreach (['pimpinan', 'administrator'] as $role) {
            $this->actingAs($this->akun($role))->get(route('program-kerja-humas.index'))->assertOk();
            $this->get(route('program-kerja-humas.show', $p))->assertOk();
            $this->get(route('program-kerja-humas.laporan.show', [$p, $l]))->assertOk();
            $this->get(route('program-kerja-humas.cetak', $p))->assertOk();
            $this->get(route('program-kerja-humas.laporan.cetak', [$p, $l]))->assertOk();
        }
        $this->actingAs($this->akun('pimpinan'))->get(route('program-kerja-humas.create'))->assertForbidden();
        $this->get(route('program-kerja-humas.show', $p))->assertDontSee('Edit program')->assertDontSee('Tambah laporan');
        $this->post(route('program-kerja-humas.laporan.tindakan', [$p, $l, 'finalisasi']), ['versi' => 0])->assertForbidden();
        foreach (['pegawai', 'guru_mapel', 'orang_tua', 'siswa'] as $role) {
            $this->actingAs($this->akun($role))->get(route('program-kerja-humas.index'))->assertForbidden();
            $this->get(route('program-kerja-humas.laporan.show', [$p, $l]))->assertForbidden();
        }
        auth()->forgetGuards();
        $this->get(route('program-kerja-humas.index'))->assertRedirect(route('login'));
    }

    public function test_akun_nonaktif_orang_tua_dan_siswa_meski_diberi_role_humas(): void
    {
        $p = $this->program();
        $l = $this->laporan($p);
        $ortu = $this->akun();
        OrangTuaWali::create(['pengguna_id' => $ortu->id, 'nama_lengkap' => 'Wali Uji']);
        $siswa = $this->akun();
        $siswa->update(['siswa_id' => Siswa::create(['nisn' => '9911223344', 'nama_lengkap' => 'Siswa Uji', 'aktif' => true])->id]);
        $nonaktif = $this->akun();
        $nonaktif->update(['aktif' => false]);
        foreach ([$ortu, $siswa, $nonaktif] as $akun) {
            $this->actingAs($akun)->get(route('program-kerja-humas.index'))->assertForbidden();
            $this->get(route('program-kerja-humas.laporan.cetak', [$p, $l]))->assertForbidden();
            $this->postJson(route('program-kerja-humas.laporan.store', $p), $this->laporanData())->assertForbidden();
        }
    }

    public function test_program_idempoten_aktor_server_dan_validasi_tahun(): void
    {
        $data = $this->data() + ['dibuat_oleh_pengguna_id' => 999, 'versi' => 99];
        $this->post(route('program-kerja-humas.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('program-kerja-humas.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('program_kerja_humas', 1);
        $this->assertDatabaseCount('riwayat_program_kerja_humas', 1);
        $p = ProgramKerjaHumas::firstOrFail();
        $this->assertSame(0, $p->versi);
        $this->assertSame(auth()->id(), $p->dibuat_oleh_pengguna_id);
        $this->assertStringNotContainsString($data['token_pembuatan'], $p->toJson());
        $this->postJson(route('program-kerja-humas.store'), $this->data(['tanggal_mulai' => '2026-06-30']))->assertJsonValidationErrors('tanggal_mulai');
        $this->postJson(route('program-kerja-humas.store'), $this->data(['status' => 'selesai', 'evaluasi' => 'Sudah selesai.']))->assertJsonValidationErrors('status');
        $this->actingAs($this->akun())->post(route('program-kerja-humas.store'), $data)->assertForbidden();
    }

    public function test_validasi_form_program_dan_stale_edit(): void
    {
        $this->postJson(route('program-kerja-humas.store'), $this->data(['target_kegiatan' => 0, 'semester' => 'salah', 'bidang' => 'salah', 'token_pembuatan' => 'salah', 'nama' => str_repeat('x', 181)]))->assertJsonValidationErrors(['target_kegiatan', 'semester', 'bidang', 'token_pembuatan', 'nama']);
        $p = $this->program();
        $edit = $this->editProgram($p, ['nama' => 'Program baru']);
        $this->put(route('program-kerja-humas.update', $p), $edit)->assertSessionHasNoErrors();
        $this->putJson(route('program-kerja-humas.update', $p), $edit)->assertJsonValidationErrors('versi');
        $this->assertSame(1, $p->fresh()->versi);
    }

    public function test_laporan_selalu_draf_deduplikasi_parent_dan_aktor(): void
    {
        $p = $this->program();
        $data = $this->laporanData() + ['status' => 'final', 'dibuat_oleh_pengguna_id' => 999, 'snapshot_final' => ['palsu' => true]];
        $this->post(route('program-kerja-humas.laporan.store', $p), $data)->assertSessionHasNoErrors();
        $this->post(route('program-kerja-humas.laporan.store', $p), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('laporan_pelaksanaan_humas', 1);
        $l = $p->laporan()->firstOrFail();
        $this->assertSame('draf', $l->status);
        $this->assertNull($l->snapshot_final);
        $this->assertSame(auth()->id(), $l->dibuat_oleh_pengguna_id);
        $asing = $this->program(['nama' => 'Program lain']);
        $this->get(route('program-kerja-humas.laporan.show', [$asing, $l]))->assertNotFound();
        $this->get(route('program-kerja-humas.laporan.cetak', [$asing, $l]))->assertNotFound();
        $this->post(route('program-kerja-humas.laporan.store', $asing), $data)->assertNotFound();
    }

    public function test_tanggal_laporan_boleh_melewati_rencana_tetapi_bukan_tahun_atau_masa_depan(): void
    {
        $p = $this->program(['tanggal_selesai' => '2026-09-30']);
        $this->laporan($p);
        foreach ([['tanggal_mulai' => '2026-06-30', 'tanggal_selesai' => '2026-06-30'], ['tanggal_mulai' => '2026-10-06', 'tanggal_selesai' => '2026-10-06'], ['tanggal_selesai' => '2026-09-30']] as $data) {
            $this->postJson(route('program-kerja-humas.laporan.store', $p), $this->laporanData($data))->assertUnprocessable();
        }
        $this->postJson(route('program-kerja-humas.laporan.store', $p), $this->laporanData(['jumlah_peserta' => -1, 'hasil' => '', 'uraian' => []]))->assertJsonValidationErrors(['jumlah_peserta', 'hasil', 'uraian']);
    }

    public function test_finalisasi_menghitung_realisasi_tidak_menyelesaikan_program_dan_snapshot_beku(): void
    {
        $p = $this->program(['target_kegiatan' => 3]);
        $l = $this->laporan($p);
        $this->finalisasi($p, $l);
        $this->assertSame(1, $p->laporanFinal()->count());
        $this->assertSame('rencana', $p->fresh()->status);
        $l = $l->fresh();
        $this->assertSame('final', $l->snapshot_final['status']);
        $this->put(route('program-kerja-humas.update', $p), $this->editProgram($p, ['nama' => 'Nama program diganti']))->assertSessionHasNoErrors();
        $print = $this->get(route('program-kerja-humas.laporan.cetak', [$p, $l]))->assertOk();
        $this->assertSame('Pertemuan orang tua', $print->viewData('isi')['program']['nama']);
        $this->putJson(route('program-kerja-humas.laporan.update', [$p, $l]), $this->editLaporan($l))->assertJsonValidationErrors('status');
        $this->postJson(route('program-kerja-humas.laporan.tindakan', [$p, $l, 'batalkan']), ['versi' => $l->versi, 'catatan_perubahan' => 'Batalkan final.'])->assertJsonValidationErrors('status');
        $this->assertSame(1, $l->riwayat()->where('aksi', 'Laporan difinalisasi')->count());
    }

    public function test_revisi_mengeluarkan_realisasi_dan_final_lama_tetap_di_riwayat(): void
    {
        $p = $this->program();
        $l = $this->laporan($p);
        $this->finalisasi($p, $l);
        $this->tindakan($p, $l, 'revisi');
        $this->assertSame(0, $p->laporanFinal()->count());
        $this->assertNull($l->fresh()->snapshot_final);
        $this->assertSame('Hasil pertemuan terlaksana.', $l->riwayat()->where('aksi', 'Laporan difinalisasi')->first()->snapshot['hasil']);
        $this->put(route('program-kerja-humas.laporan.update', [$p, $l]), $this->editLaporan($l, ['hasil' => 'Hasil dikoreksi.']))->assertSessionHasNoErrors();
        $this->finalisasi($p, $l);
        $this->assertSame('Hasil dikoreksi.', $l->fresh()->snapshot_final['hasil']);
        $this->assertSame(2, $l->riwayat()->where('aksi', 'Laporan difinalisasi')->count());
    }

    public function test_tutup_program_boleh_realisasi_sebagian_tetapi_tidak_ada_draf(): void
    {
        $p = $this->program(['target_kegiatan' => 3]);
        $l = $this->laporan($p);
        $draf = $this->laporan($p, ['judul' => 'Draf yang keliru']);
        $this->finalisasi($p, $l);
        $close = $this->editProgram($p, ['status' => 'selesai', 'evaluasi' => 'Satu kegiatan tercapai, dua dialihkan.']);
        $this->putJson(route('program-kerja-humas.update', $p), $close)->assertJsonValidationErrors('status');
        $this->tindakan($p, $draf, 'batalkan');
        $this->put(route('program-kerja-humas.update', $p), $close)->assertSessionHasNoErrors();
        $this->assertSame('selesai', $p->fresh()->status);
        $this->postJson(route('program-kerja-humas.laporan.store', $p), $this->laporanData())->assertJsonValidationErrors('status');
        $this->postJson(route('program-kerja-humas.laporan.tindakan', [$p, $l, 'revisi']), ['versi' => $l->fresh()->versi, 'catatan_perubahan' => 'Koreksi laporan.'])->assertJsonValidationErrors('status');
        $this->put(route('program-kerja-humas.update', $p), $this->editProgram($p, ['status' => 'berjalan']))->assertSessionHasNoErrors();
        $this->tindakan($p, $l, 'revisi');
    }

    public function test_tahun_program_tidak_berubah_setelah_ada_laporan(): void
    {
        $p = $this->program();
        $this->laporan($p);
        $tahun = TahunPelajaran::create(['nama' => '2025/2026', 'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'aktif' => false]);
        $this->putJson(route('program-kerja-humas.update', $p), $this->editProgram($p, ['tahun_pelajaran_id' => $tahun->id]))->assertJsonValidationErrors('tahun_pelajaran_id');
    }

    public function test_agenda_terkait_opsional_dan_izin_terpisah(): void
    {
        $p = $this->program();
        $a = $this->agenda();
        $l = $this->laporan($p, ['agenda_humas_id' => $a->id]);
        $this->get(route('program-kerja-humas.laporan.show', [$p, $l]))->assertSee('Agenda rahasia uji');
        $a->update(['status' => 'dibatalkan']);
        $this->postJson(route('program-kerja-humas.laporan.store', $p), $this->laporanData(['agenda_humas_id' => $a->id]))->assertJsonValidationErrors('agenda_humas_id');
        $this->actingAs($this->akunIzin(['program_kerja_humas.lihat']))->get(route('program-kerja-humas.laporan.show', [$p, $l]))->assertOk()->assertDontSee('Agenda rahasia uji');
        $this->get(route('program-kerja-humas.laporan.cetak', [$p, $l]))->assertOk()->assertDontSee('Agenda rahasia uji');
        $this->actingAs($this->akunIzin(['program_kerja_humas.kelola']))->postJson(route('program-kerja-humas.laporan.store', $p), $this->laporanData(['agenda_humas_id' => $a->id]))->assertForbidden();
        $edit = $this->editLaporan($l, ['hasil' => 'Diperbaiki tanpa mengubah agenda.']);
        unset($edit['agenda_humas_id']);
        $this->put(route('program-kerja-humas.laporan.update', [$p, $l]), $edit)->assertSessionHasNoErrors();
        $this->assertSame($a->id, $l->fresh()->agenda_humas_id);
    }

    public function test_bukti_upload_privat_idempoten_pusat_dokumen_dan_parent(): void
    {
        $p = $this->program();
        $l = $this->laporan($p);
        $data = $this->buktiData($l);
        $this->postJson(route('program-kerja-humas.laporan.bukti.store', [$p, $l]), $data)->assertOk()->assertJsonStructure(['redirect', 'pesan']);
        $this->postJson(route('program-kerja-humas.laporan.bukti.store', [$p, $l]), $data)->assertOk();
        $this->assertDatabaseCount('bukti_laporan_humas', 1);
        $this->assertDatabaseCount('dokumen_humas', 1);
        $this->assertDatabaseCount('riwayat_dokumen_humas', 1);
        $b = $l->bukti()->firstOrFail();
        Storage::disk('local')->assertExists($b->berkas->lokasi_file);
        $this->assertSame('laporan_kegiatan', $b->berkas->dokumen->kategori);
        $this->assertSame(auth()->id(), $b->dibuat_oleh_pengguna_id);
        $this->assertSame(1, $l->fresh()->versi);
        $this->get(route('program-kerja-humas.laporan.berkas', [$p, $l, $b]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $asing = $this->laporan($p);
        $this->get(route('program-kerja-humas.laporan.berkas', [$p, $asing, $b]))->assertNotFound();
    }

    public function test_izin_bukti_terpisah_termasuk_riwayat_dan_cetak(): void
    {
        $p = $this->program();
        $l = $this->laporan($p);
        $b = $this->bukti($p, $l);
        $this->finalisasi($p, $l);
        $this->actingAs($this->akunIzin(['program_kerja_humas.lihat']))->get(route('program-kerja-humas.laporan.show', [$p, $l]))->assertOk()->assertDontSee('Bukti privat uji')->assertDontSee('foto-uji.jpg');
        $this->get(route('program-kerja-humas.laporan.cetak', [$p, $l]))->assertOk()->assertDontSee('foto-uji.jpg');
        $this->get(route('program-kerja-humas.laporan.berkas', [$p, $l, $b]))->assertForbidden();
        $this->actingAs($this->akunIzin(['program_kerja_humas.kelola', 'dokumen_humas.lihat']))->postJson(route('program-kerja-humas.laporan.bukti.store', [$p, $l]), $this->buktiData($l))->assertForbidden();
    }

    public function test_bukti_menahan_versi_dokumen_dan_lepas_tidak_hapus_arsip(): void
    {
        $p = $this->program();
        $l = $this->laporan($p);
        $b = $this->bukti($p, $l);
        $doc = $b->berkas->dokumen;
        $doc->riwayat()->create(['versi' => 2, 'lokasi_file' => 'baru.jpg', 'nama_file_asli' => 'baru.jpg', 'tipe_file' => 'image/jpeg', 'ukuran_file' => 100, 'diunggah_pada' => now()]);
        Storage::disk('local')->put('baru.jpg', 'baru');
        $doc->update(['lokasi_file' => 'baru.jpg', 'nama_file_asli' => 'baru.jpg']);
        $this->get(route('program-kerja-humas.laporan.show', [$p, $l]))->assertOk()->assertSee('foto-uji.jpg');
        $this->delete(route('program-kerja-humas.laporan.bukti.destroy', [$p, $l, $b]), ['versi' => $l->fresh()->versi, 'catatan_perubahan' => 'Bukti salah dipilih.'])->assertSessionHasNoErrors();
        $this->assertSame(0, $l->bukti()->count());
        $this->assertDatabaseCount('dokumen_humas', 1);
        Storage::disk('local')->assertExists($b->berkas->lokasi_file);
        $this->get(route('program-kerja-humas.laporan.berkas', [$p, $l, $b]))->assertOk();
        $this->assertSame('foto-uji.jpg', $l->riwayat()->where('aksi', 'Bukti ditambahkan')->first()->snapshot['bukti'][0]['nama_file_asli']);
    }

    public function test_hubungkan_dokumen_aktif_format_valid_dan_tidak_duplikat(): void
    {
        $p = $this->program();
        $l = $this->laporan($p);
        $b = $this->bukti($p, $l);
        $doc = $b->berkas->dokumen;
        $baru = $this->laporan($p);
        $data = ['token_pembuatan' => (string) Str::uuid(), 'judul' => 'Dokumen terhubung', 'dokumen_humas_id' => $doc->id, 'versi' => 0];
        $this->post(route('program-kerja-humas.laporan.bukti.store', [$p, $baru]), $data)->assertSessionHasNoErrors();
        $this->postJson(route('program-kerja-humas.laporan.bukti.store', [$p, $baru]), array_replace($data, ['token_pembuatan' => (string) Str::uuid(), 'versi' => 1]))->assertJsonValidationErrors('dokumen_humas_id');
        $doc->update(['status' => 'arsip']);
        $this->postJson(route('program-kerja-humas.laporan.bukti.store', [$p, $baru]), array_replace($data, ['token_pembuatan' => (string) Str::uuid(), 'versi' => 1]))->assertJsonValidationErrors('dokumen_humas_id');
        $this->assertDatabaseCount('dokumen_humas', 1);
    }

    public function test_file_invalid_stale_final_dan_hilang_tidak_merusak_data(): void
    {
        $p = $this->program();
        $l = $this->laporan($p);
        $this->postJson(route('program-kerja-humas.laporan.bukti.store', [$p, $l]), $this->buktiData($l, ['berkas' => UploadedFile::fake()->create('salah.txt', 1, 'text/plain')]))->assertJsonValidationErrors('berkas');
        $this->postJson(route('program-kerja-humas.laporan.bukti.store', [$p, $l]), $this->buktiData($l, ['versi' => 99]))->assertJsonValidationErrors('versi');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $b = $this->bukti($p, $l);
        Storage::disk('local')->delete($b->berkas->lokasi_file);
        $this->postJson(route('program-kerja-humas.laporan.tindakan', [$p, $l, 'finalisasi']), ['versi' => $l->fresh()->versi])->assertJsonValidationErrors('bukti');
        $this->assertSame('draf', $l->fresh()->status);
        $this->get(route('program-kerja-humas.laporan.berkas', [$p, $l, $b]))->assertNotFound();
        $this->delete(route('program-kerja-humas.laporan.bukti.destroy', [$p, $l, $b]), ['versi' => $l->fresh()->versi, 'catatan_perubahan' => 'Berkas tidak tersedia.'])->assertSessionHasNoErrors();
        $this->finalisasi($p, $l);
        $this->postJson(route('program-kerja-humas.laporan.bukti.store', [$p, $l]), $this->buktiData($l))->assertJsonValidationErrors('status');
    }

    public function test_stale_tindakan_ditolak_dan_alasan_revisi_batal_wajib(): void
    {
        $p = $this->program();
        $l = $this->laporan($p);
        $this->postJson(route('program-kerja-humas.laporan.tindakan', [$p, $l, 'batalkan']), ['versi' => 0])->assertJsonValidationErrors('catatan_perubahan');
        $this->put(route('program-kerja-humas.laporan.update', [$p, $l]), $this->editLaporan($l, ['judul' => 'Judul dikoreksi']))->assertSessionHasNoErrors();
        $this->postJson(route('program-kerja-humas.laporan.tindakan', [$p, $l, 'finalisasi']), ['versi' => 0])->assertJsonValidationErrors('versi');
        $this->finalisasi($p, $l);
        $this->postJson(route('program-kerja-humas.laporan.tindakan', [$p, $l, 'revisi']), ['versi' => $l->fresh()->versi])->assertJsonValidationErrors('catatan_perubahan');
    }

    public function test_filter_statistik_paginasi_tahun_bidang_dan_realisasi(): void
    {
        $p = $this->program(['tanggal_selesai' => '2026-10-04']);
        $lain = $this->program(['nama' => 'Program publikasi', 'bidang' => 'publikasi', 'status' => 'berjalan']);
        $this->finalisasi($p, $this->laporan($p));
        $r = $this->get(route('program-kerja-humas.index'))->assertOk();
        $this->assertSame(['rencana' => 1, 'berjalan' => 1, 'selesai' => 0, 'dibatalkan' => 0], $r->viewData('statistik'));
        $this->assertSame(1, $r->viewData('terlambat'));
        $this->assertSame(1, $r->viewData('daftar')->first()->laporan_final_count);
        $this->get(route('program-kerja-humas.index', ['terlambat' => 1]))->assertDontSee('Program publikasi');
        $this->get(route('program-kerja-humas.index', ['bidang' => 'publikasi']))->assertSee('Program publikasi')->assertDontSee('Pertemuan orang tua');
        $this->getJson(route('program-kerja-humas.index', ['status' => 'salah']))->assertJsonValidationErrors('status');
        for ($i = 0; $i < 20; $i++) {
            $this->program(['nama' => 'Program tambahan '.$i]);
        }
        $this->assertSame(20, $this->get(route('program-kerja-humas.index'))->viewData('daftar')->count());
        $this->assertSame(2, $this->get(route('program-kerja-humas.index', ['page' => 2]))->viewData('daftar')->count());
    }

    public function test_xss_cetak_draf_final_dan_capture_tampilan(): void
    {
        $p = $this->program(['nama' => '<script>alert(1)</script> Pertemuan orang tua']);
        $l = $this->laporan($p, ['agenda_humas_id' => $this->agenda()->id]);
        $this->bukti($p, $l);
        $this->capture('index', $this->get(route('program-kerja-humas.index'))->assertOk()->assertDontSee('<script>alert(1)</script>', false));
        $this->capture('form', $this->get(route('program-kerja-humas.create'))->assertOk());
        $this->capture('edit', $this->get(route('program-kerja-humas.edit', $p))->assertOk());
        $this->capture('show', $this->get(route('program-kerja-humas.show', $p))->assertOk());
        $this->capture('laporan-form', $this->get(route('program-kerja-humas.laporan.create', $p))->assertOk());
        $this->capture('laporan-draf', $this->get(route('program-kerja-humas.laporan.show', [$p, $l]))->assertOk());
        $this->get(route('program-kerja-humas.laporan.cetak', [$p, $l]))->assertSee('DRAF LAPORAN');
        $this->finalisasi($p, $l);
        $this->capture('laporan-final', $this->get(route('program-kerja-humas.laporan.show', [$p, $l]))->assertOk());
        $this->capture('cetak-program', $this->get(route('program-kerja-humas.cetak', $p))->assertOk()->assertSee('size:A4 portrait', false));
        $this->capture('cetak-laporan', $this->get(route('program-kerja-humas.laporan.cetak', [$p, $l]))->assertOk()->assertSee('LAPORAN FINAL')->assertDontSee('<script>alert(1)</script>', false));
        $this->actingAs($this->akun('pimpinan'));
        $this->capture('readonly', $this->get(route('program-kerja-humas.laporan.show', [$p, $l]))->assertOk()->assertDontSee('Buka revisi'));
    }

    private function akun(string $role = 'wakil_pimpinan_humas'): Pengguna
    {
        $p = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'program.humas.'.Str::uuid(), 'kata_sandi' => 'UjiHumas123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function akunIzin(array $izin): Pengguna
    {
        $role = Peran::create(['kode' => 'program_'.Str::random(10), 'nama' => 'Program terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $p = $this->akun('pegawai');
        $p->daftarPeran()->attach($role);

        return $p;
    }

    private function data(array $data = []): array
    {
        $tahun = TahunPelajaran::firstOrCreate(['nama' => '2026/2027'], ['tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);

        return array_replace(['token_pembuatan' => (string) Str::uuid(), 'tahun_pelajaran_id' => $tahun->id, 'semester' => 'ganjil', 'bidang' => 'orang_tua', 'nama' => 'Pertemuan orang tua',
            'tujuan' => 'Menguatkan hubungan sekolah dan orang tua.', 'sasaran' => 'Orang tua siswa', 'target_hasil' => 'Pertemuan terlaksana.', 'target_kegiatan' => 2, 'penanggung_jawab' => 'Waka Humas',
            'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-12-31', 'status' => 'rencana', 'evaluasi' => null], $data);
    }

    private function program(array $data = []): ProgramKerjaHumas
    {
        $this->post(route('program-kerja-humas.store'), $this->data($data))->assertRedirect()->assertSessionHasNoErrors();

        return ProgramKerjaHumas::latest('id')->firstOrFail();
    }

    private function laporanData(array $data = []): array
    {
        return array_replace(['token_pembuatan' => (string) Str::uuid(), 'agenda_humas_id' => null, 'judul' => 'Pertemuan wali siswa kelas VII', 'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-10-01',
            'tempat' => 'Aula sekolah', 'pelaksana' => 'Waka Humas dan wali kelas', 'jumlah_peserta' => 120, 'uraian' => 'Pertemuan membahas kegiatan sekolah.',
            'hasil' => 'Hasil pertemuan terlaksana.', 'kendala' => 'Sebagian orang tua berhalangan.', 'tindak_lanjut' => 'Sampaikan hasil kepada orang tua yang tidak hadir.'], $data);
    }

    private function laporan(ProgramKerjaHumas $p, array $data = []): LaporanPelaksanaanHumas
    {
        $this->post(route('program-kerja-humas.laporan.store', $p), $this->laporanData($data))->assertRedirect()->assertSessionHasNoErrors();

        return $p->laporan()->latest('id')->firstOrFail();
    }

    private function editProgram(ProgramKerjaHumas $p, array $data = []): array
    {
        $p = $p->fresh();

        return array_replace($p->only(ProgramKerjaHumas::KOLOM), ['versi' => $p->versi, 'tanggal_mulai' => $p->tanggal_mulai->format('Y-m-d'), 'tanggal_selesai' => $p->tanggal_selesai->format('Y-m-d'), 'catatan_perubahan' => 'Koreksi program kerja.'], $data);
    }

    private function editLaporan(LaporanPelaksanaanHumas $l, array $data = []): array
    {
        $l = $l->fresh();

        return array_replace($l->only(LaporanPelaksanaanHumas::KOLOM), ['versi' => $l->versi, 'tanggal_mulai' => $l->tanggal_mulai->format('Y-m-d'), 'tanggal_selesai' => $l->tanggal_selesai->format('Y-m-d'), 'catatan_perubahan' => 'Koreksi laporan kegiatan.'], $data);
    }

    private function buktiData(LaporanPelaksanaanHumas $l, array $data = []): array
    {
        return array_replace(['token_pembuatan' => (string) Str::uuid(), 'versi' => $l->fresh()->versi, 'judul' => 'Bukti privat uji',
            'berkas' => UploadedFile::fake()->createWithContent('foto-uji.jpg', file_get_contents(public_path('images/login-sekolah.jpg')))], $data);
    }

    private function bukti(ProgramKerjaHumas $p, LaporanPelaksanaanHumas $l): BuktiLaporanHumas
    {
        $this->postJson(route('program-kerja-humas.laporan.bukti.store', [$p, $l]), $this->buktiData($l))->assertOk();

        return $l->bukti()->latest('id')->firstOrFail();
    }

    private function finalisasi(ProgramKerjaHumas $p, LaporanPelaksanaanHumas $l): void
    {
        $this->post(route('program-kerja-humas.laporan.tindakan', [$p, $l, 'finalisasi']), ['versi' => $l->fresh()->versi])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function tindakan(ProgramKerjaHumas $p, LaporanPelaksanaanHumas $l, string $aksi): void
    {
        $this->post(route('program-kerja-humas.laporan.tindakan', [$p, $l, $aksi]), ['versi' => $l->fresh()->versi, 'catatan_perubahan' => 'Koreksi data pelaksanaan.'])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function agenda(): AgendaHumas
    {
        return AgendaHumas::create(['judul' => 'Agenda rahasia uji', 'jenis' => 'orang_tua', 'waktu_mulai' => '2026-10-01 09:00', 'waktu_selesai' => '2026-10-01 11:00', 'tempat' => 'Aula', 'topik' => 'Kegiatan orang tua', 'status' => 'selesai']);
    }

    private function capture(string $name, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_PROGRAM_HUMAS_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/program-kerja-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$name.'.html', $response->getContent());
    }
}
