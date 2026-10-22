<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PeriodeKomiteHumas;
use App\Models\ProgramKerjaHumas;
use App\Models\ProgramKomiteHumas;
use App\Models\SuratTu;
use App\Models\TahunPelajaran;
use App\Services\Humas\KelolaDokumenHumasService;
use App\Services\Humas\KlasifikasiDokumenHumasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class KlasifikasiDokumenHumasTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $humas;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->humas = $this->akun();
        $this->actingAs($this->humas);
    }

    public function test_unggahan_baru_memisahkan_urusan_jenis_dan_menyimpan_pemeriksa(): void
    {
        $this->post(route('dokumen-humas.store'), $this->input() + ['urusan' => 'komite', 'jenis_dokumen' => 'undangan'])->assertSessionHasNoErrors()->assertRedirect();
        $d = DokumenHumas::firstOrFail();
        $this->assertSame('komite', $d->urusan);
        $this->assertSame('undangan', $d->jenis_dokumen);
        $this->assertFalse($d->perluDitinjau());
        $this->assertSame($this->humas->id, $d->klasifikasi_pemeriksa_id);
        $this->assertCount(1, $d->riwayat);
        $this->assertCount(1, $d->riwayatKlasifikasi);
        Storage::disk('local')->assertExists($d->lokasi_file);
        $this->get(route('dokumen-humas.index', ['urusan' => 'komite', 'jenis_dokumen' => 'undangan', 'klasifikasi' => 'diperiksa']))->assertOk()->assertViewHas('dokumen', fn ($p) => $p->total() === 1);
        $this->get(route('dokumen-humas.index', ['jenis_dokumen' => 'notulen']))->assertOk()->assertViewHas('dokumen', fn ($p) => $p->total() === 0);
    }

    public function test_api_lama_tetap_berfungsi_dan_tidak_menghapus_klasifikasi_manual(): void
    {
        $this->token();
        $url = route('api.v1.humas.dokumen.store');
        $r = $this->post($url, $this->input() + ['kategori' => 'notulen'], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.kategori', 'notulen')->assertJsonPath('data.jenis_dokumen', 'notulen')->assertJsonPath('data.urusan', null)
            ->assertJsonPath('data.klasifikasi.perlu_tinjau', true);
        $d = DokumenHumas::findOrFail($r->json('data.id'));
        $this->actingAs($this->humas);
        $this->kolektif([$d], 'komite', 'notulen')->assertRedirect();
        $this->token();
        $r = $this->patchJson(route('api.v1.humas.dokumen.update', $d), ['kategori' => 'notulen', 'judul' => 'Judul dikoreksi', 'ingatkan_hari_sebelum' => 30, 'sidik' => $this->sidik($d->fresh())])
            ->assertOk()->assertJsonPath('data.urusan', 'komite')->assertJsonPath('data.klasifikasi.perlu_tinjau', false);
        $this->assertSame('manual', $d->fresh()->sumber_klasifikasi);
        $this->assertSame(1, $d->riwayat()->count());
    }

    public function test_penataan_pratinjau_tidak_mengubah_data_dan_terapkan_tidak_menebak_urusan_notulen(): void
    {
        $notulen = $this->dokumen('notulen');
        $sop = $this->dokumen('sop');
        $sebelum = $notulen->getAttributes();
        $this->artisan('humas:tata-arsip')->expectsOutputToContain('Pratinjau saja')->assertSuccessful();
        $this->assertSame($sebelum, $notulen->fresh()->getAttributes());
        $this->assertSame(0, $notulen->riwayatKlasifikasi()->count());
        $this->artisan('humas:tata-arsip', ['--terapkan' => true])->assertSuccessful();
        $this->assertNull($notulen->fresh()->urusan);
        $this->assertSame('notulen', $notulen->fresh()->jenis_dokumen);
        $this->assertSame('kedinasan', $sop->fresh()->urusan);
        $this->assertTrue($sop->fresh()->perluDitinjau());
        $jumlahLog = $sop->riwayatKlasifikasi()->count();
        $this->artisan('humas:tata-arsip', ['--terapkan' => true])->assertSuccessful();
        $this->assertSame($jumlahLog, $sop->riwayatKlasifikasi()->count());
        $this->assertSame($sebelum['lokasi_file'], $notulen->fresh()->lokasi_file);
        $this->assertSame(1, $notulen->riwayat()->count());
    }

    public function test_penataan_memakai_bukti_relasi_dan_menandai_konflik_tanpa_memaksakan_urusan(): void
    {
        $d = $this->dokumen('notulen');
        $komite = $this->agenda('komite');
        $d->agenda()->attach($komite);
        $service = app(KlasifikasiDokumenHumasService::class);
        $this->assertSame('komite', $service->usulan($d)['urusan']);
        $d->agenda()->attach($this->agenda('orang_tua'));
        $this->assertTrue($service->usulan($d)['konflik']);
        $service->tataArsipLama(true);
        $this->assertNull($d->fresh()->urusan);
        $this->assertTrue($d->fresh()->perluDitinjau());
        $this->kolektif([$d->fresh()], 'komite', 'notulen')->assertRedirect();
        $service->tataArsipLama(true);
        $this->assertSame('komite', $d->fresh()->urusan);
        $this->assertFalse($d->fresh()->perluDitinjau());
    }

    public function test_konfirmasi_kolektif_atomik_menolak_sidik_lama_dan_tidak_mengubah_berkas(): void
    {
        $a = $this->dokumen('komite');
        $b = $this->dokumen('notulen');
        $old = $this->sidik($b);
        $b->forceFill(['versi_metadata' => 2])->save();
        $this->patch(route('dokumen-humas.klasifikasi-kolektif'), ['ids' => [$a->id, $b->id], 'sidik' => [$a->id => $this->sidik($a), $b->id => $old],
            'urusan' => 'komite', 'jenis_dokumen' => 'notulen', 'alasan' => 'Pemeriksaan arsip komite'])->assertSessionHasErrors('ids');
        $this->assertNull($a->fresh()->klasifikasi_diperiksa_pada);
        $this->assertSame(0, $a->riwayatKlasifikasi()->count());
        $file = Storage::disk('local')->get($a->lokasi_file);
        $this->kolektif([$a, $b->fresh()], 'komite', 'notulen')->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($a->fresh()->perluDitinjau());
        $this->assertFalse($b->fresh()->perluDitinjau());
        $this->assertSame('komite', $a->fresh()->kategori);
        $this->assertSame($file, Storage::disk('local')->get($a->lokasi_file));
        $this->assertSame(1, $a->riwayat()->count());
    }

    public function test_kaitan_agenda_program_dan_arsip_balik_terlihat_tanpa_salin_berkas(): void
    {
        $d = $this->dokumen('lainnya');
        $agenda = $this->agenda('orang_tua');
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $program = ProgramKerjaHumas::create(['token_pembuatan' => Str::uuid(), 'tahun_pelajaran_id' => $tahun->id, 'semester' => 'ganjil', 'bidang' => 'orang_tua', 'nama' => 'Program hubungan orang tua',
            'tujuan' => 'Koordinasi', 'sasaran' => 'Orang tua', 'target_hasil' => 'Pertemuan', 'target_kegiatan' => 1, 'penanggung_jawab' => 'Humas', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-11-01', 'status' => 'berjalan']);
        $this->kaitan($d, 'agenda', $agenda->id)->assertSessionHasNoErrors()->assertRedirect();
        $this->kaitan($d->fresh(), 'program', $program->id)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertCount(1, $d->agenda);
        $this->assertCount(1, $d->program);
        $this->assertSame(1, $d->riwayat()->count());
        $this->get(route('dokumen-humas.show', $d))->assertOk()->assertSee($agenda->judul)->assertSee($program->nama);
        $r = $this->get(route('program-kerja-humas.show', $program))->assertOk()->assertSee('Arsip Humas terkait')->assertSee($d->judul);
        $this->simpanFixture('program', $r->getContent());
        $this->kaitan($d->fresh(), 'agenda', $agenda->id, 'lepas')->assertRedirect();
        $this->assertSame(0, $d->agenda()->count());
        Storage::disk('local')->assertExists($d->lokasi_file);
        $this->assertSame(1, $d->program()->count());
    }

    public function test_kaitan_menolak_izin_sumber_yang_tidak_dimiliki_dan_sidik_yang_usang(): void
    {
        $d = $this->dokumen('notulen');
        $agenda = $this->agenda('komite');
        $old = $this->sidik($d);
        $this->kaitan($d, 'agenda', $agenda->id)->assertRedirect();
        $this->patchJson(route('dokumen-humas.kaitan', $d), ['jenis_kaitan' => 'agenda', 'target_id' => $agenda->id, 'aksi' => 'lepas', 'alasan' => 'Diperiksa ulang', 'sidik' => $old])->assertUnprocessable();
        $terbatas = $this->akun(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        $this->actingAs($terbatas);
        $this->kaitan($d->fresh(), 'agenda', $agenda->id, 'lepas')->assertForbidden();
        $this->get(route('dokumen-humas.show', $d))->assertOk()->assertDontSee($agenda->judul);
        $this->assertSame(1, $d->agenda()->count());
    }

    public function test_surat_rahasia_hanya_terlihat_untuk_penerima_yang_memiliki_akses(): void
    {
        $d = $this->dokumen('surat_tugas');
        $surat = SuratTu::create(['jenis' => 'tugas', 'nomor_surat' => 'RAHASIA-001', 'nomor_unik' => hash('sha256', Str::uuid()),
            'tanggal_surat' => '2026-10-01', 'perihal' => 'JUDUL SURAT TERBATAS', 'pihak' => 'Sekolah', 'sifat' => 'rahasia', 'status' => 'disahkan',
            'disahkan_pada' => now(), 'dibuat_oleh_pengguna_id' => $this->humas->id, 'diubah_oleh_pengguna_id' => $this->humas->id]);
        $surat->penugasan()->create(['pegawai_id' => $this->humas->pegawai_id, 'nama_pegawai' => 'Penerima Humas']);
        $this->kaitan($d, 'surat', $surat->id)->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('dokumen-humas.show', $d))->assertOk()->assertSee('JUDUL SURAT TERBATAS');
        $r = $this->get(route('surat-saya.show', $surat))->assertOk()->assertSee('Arsip Humas terkait')->assertSee($d->judul);
        $this->simpanFixture('surat', $r->getContent());
        $lain = $this->akun();
        $this->actingAs($lain)->get(route('dokumen-humas.show', $d))->assertOk()->assertDontSee('JUDUL SURAT TERBATAS')->assertDontSee('RAHASIA-001');
        $this->token($lain);
        $this->getJson(route('api.v1.humas.dokumen.show', $d))->assertOk()->assertJsonPath('data.hubungan', []);
        $this->patchJson(route('api.v1.humas.dokumen.kaitan', $d), ['jenis_kaitan' => 'surat', 'target_id' => $surat->id, 'aksi' => 'lepas', 'sidik' => $this->sidik($d->fresh()), 'alasan' => 'Mencoba lepas'])->assertNotFound();
        $this->assertSame(1, $d->surat()->count());
    }

    public function test_izin_baca_tidak_dapat_mengonfirmasi_dan_filter_api_selaras(): void
    {
        $d = $this->dokumen('notulen');
        $this->kolektif([$d], 'komite', 'notulen')->assertRedirect();
        $pembaca = $this->akun(['dokumen_humas.lihat']);
        $this->actingAs($pembaca);
        $this->kolektif([$d->fresh()], 'orang_tua', 'notulen')->assertForbidden();
        $this->token($pembaca);
        $this->getJson(route('api.v1.humas.dokumen.index', ['urusan' => 'komite', 'jenis_dokumen' => 'notulen', 'klasifikasi' => 'diperiksa']))
            ->assertOk()->assertJsonPath('data.paginasi.total', 1)->assertJsonPath('data.items.0.urusan', 'komite');
        $this->getJson(route('api.v1.humas.dokumen.referensi'))->assertOk()->assertJsonPath('data.jenis_dokumen.notulen', 'Notulen');
        $this->patchJson(route('api.v1.humas.dokumen.klasifikasi-kolektif'), [])->assertForbidden();
    }

    public function test_pilihan_kaitan_bisa_dicari_dengan_nomor_surat_tetap_dibatasi_izin_dan_status(): void
    {
        $d = $this->dokumen('surat_keluar');
        $surat = SuratTu::create(['jenis' => 'tugas', 'nomor_surat' => '421/STS/2026-77', 'nomor_unik' => hash('sha256', Str::uuid()),
            'tanggal_surat' => '2026-10-01', 'perihal' => 'Penugasan kegiatan sekolah', 'pihak' => 'Sekolah', 'sifat' => 'rahasia', 'status' => 'disahkan',
            'disahkan_pada' => now(), 'dibuat_oleh_pengguna_id' => $this->humas->id, 'diubah_oleh_pengguna_id' => $this->humas->id]);
        $surat->penugasan()->create(['pegawai_id' => $this->humas->pegawai_id, 'nama_pegawai' => 'Penerima Humas']);
        $this->token();
        $url = route('api.v1.humas.dokumen.pilihan-kaitan', [$d, 'jenis_kaitan' => 'surat', 'kata_kunci' => 'STS/2026-77']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.items.0.id', $surat->id);
        $lain = $this->akun();
        $this->actingAs($lain);
        $this->token($lain);
        $this->getJson($url)->assertOk()->assertJsonPath('data.items', []);
        $this->actingAs($this->humas);
        $this->token();
        $surat->update(['status' => 'dibatalkan']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.items', []);
        $this->actingAs($this->humas);
        $this->kaitan($d, 'surat', $surat->id)->assertSessionHasErrors('target_id');
        $agenda = $this->agenda('komite');
        $agenda->update(['status' => 'dibatalkan']);
        $this->token();
        $this->getJson(route('api.v1.humas.dokumen.pilihan-kaitan', [$d, 'jenis_kaitan' => 'agenda']))->assertOk()->assertJsonPath('data.items', []);
        $this->assertSame(0, $d->surat()->count());
    }

    public function test_api_kolektif_mengonfirmasi_metadata_dan_mengirim_riwayat_tanpa_id_target(): void
    {
        $d = $this->dokumen('notulen');
        $this->token();
        $this->patchJson(route('api.v1.humas.dokumen.klasifikasi-kolektif'), ['ids' => [$d->id], 'sidik' => [$d->id => $this->sidik($d)],
            'urusan' => 'komite', 'jenis_dokumen' => 'notulen', 'alasan' => 'Pemeriksaan arsip komite dari aplikasi mobile'])
            ->assertOk()->assertJsonPath('data.jumlah', 1);
        $this->assertFalse($d->fresh()->perluDitinjau());
        $this->actingAs($this->humas);
        $this->kaitan($d->fresh(), 'agenda', $this->agenda('komite')->id)->assertRedirect();
        $this->token();
        $r = $this->getJson(route('api.v1.humas.dokumen.riwayat-klasifikasi', $d))->assertOk()
            ->assertJsonPath('data.paginasi.total', 2)->assertJsonPath('data.items.1.sesudah.urusan', 'komite');
        $this->assertArrayNotHasKey('target_id', $r->json('data.items.0.sesudah'));
        $this->assertArrayNotHasKey('klasifikasi_pemeriksa_id', $r->json('data.items.1.sesudah'));
    }

    public function test_program_komite_terhubung_dan_api_menggunakan_sumber_yang_sama(): void
    {
        $periode = PeriodeKomiteHumas::create(['token_pembuatan' => Str::uuid(), 'nama' => 'Komite 2026', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status' => 'aktif']);
        $program = new ProgramKomiteHumas;
        $program->forceFill(['periode_komite_humas_id' => $periode->id, 'token_pembuatan' => Str::uuid(), 'nama' => 'Program komite bersama orang tua', 'tujuan' => 'Koordinasi', 'target_hasil' => 'Pertemuan', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-11-01', 'status' => 'berjalan'])->save();
        $d = $this->dokumen('komite');
        $this->token();
        $this->getJson(route('api.v1.humas.dokumen.pilihan-kaitan', [$d, 'jenis_kaitan' => 'program_komite']))->assertOk()->assertJsonPath('data.items.0.id', $program->id);
        $this->patchJson(route('api.v1.humas.dokumen.kaitan', $d), ['jenis_kaitan' => 'program_komite', 'target_id' => $program->id, 'aksi' => 'tambah', 'alasan' => 'Dokumen program komite', 'sidik' => $this->sidik($d)])->assertOk()->assertJsonPath('data.hubungan.0.jenis', 'program_komite');
        $this->actingAs($this->humas);
        $r = $this->get(route('komite-humas.program.show', [$periode, $program]))->assertOk()->assertSee('Arsip Humas terkait')->assertSee($d->judul);
        $this->simpanFixture('program-komite', $r->getContent());
    }

    public function test_identitas_orang_tua_dan_data_tidak_valid_tidak_dapat_mengubah_arsip(): void
    {
        $d = $this->dokumen('notulen');
        $this->patchJson(route('dokumen-humas.klasifikasi-kolektif'), ['ids' => [$d->id, $d->id], 'sidik' => [$d->id => $this->sidik($d)], 'urusan' => 'komite', 'jenis_dokumen' => 'notulen', 'alasan' => 'Pemeriksaan ulang'])->assertUnprocessable();
        $this->patchJson(route('dokumen-humas.klasifikasi-kolektif'), ['ids' => [$d->id], 'sidik' => [$d->id => $this->sidik($d)], 'urusan' => 'tidak-valid', 'jenis_dokumen' => 'notulen', 'alasan' => 'Pemeriksaan ulang'])->assertUnprocessable();
        $parent = $this->akun();
        $parent->update(['pegawai_id' => null]);
        OrangTuaWali::create(['pengguna_id' => $parent->id, 'nama_lengkap' => 'Wali uji']);
        $this->actingAs($parent)->get(route('dokumen-humas.show', $d))->assertForbidden();
        $this->kolektif([$d], 'komite', 'notulen')->assertForbidden();
        $this->token($parent);
        $this->getJson(route('api.v1.humas.dokumen.show', $d))->assertForbidden();
        $this->assertNull($d->fresh()->klasifikasi_diperiksa_pada);
    }

    public function test_fixture_tampilan_klasifikasi_dan_hubungan(): void
    {
        $a = $this->dokumen('notulen');
        $a->update(['judul' => 'Notulen rapat koordinasi komite dan orang tua dengan judul dokumen panjang untuk uji tampilan']);
        $b = $this->dokumen('lainnya');
        $this->kolektif([$b], 'orang_tua', 'undangan')->assertRedirect();
        $this->kaitan($b->fresh(), 'agenda', $this->agenda('orang_tua')->id)->assertRedirect();
        if (getenv('NUSA_CAPTURE_KLASIFIKASI_HUMAS') === '1') {
            foreach (['index' => route('dokumen-humas.index'), 'create' => route('dokumen-humas.create'), 'edit' => route('dokumen-humas.edit', $a), 'show' => route('dokumen-humas.show', $b)] as $nama => $url) {
                $this->simpanFixture($nama, $this->get($url)->assertOk()->getContent());
            }
        }
    }

    private function simpanFixture(string $nama, string $html): void
    {
        if (getenv('NUSA_CAPTURE_KLASIFIKASI_HUMAS') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/klasifikasi-humas');
        File::ensureDirectoryExists($folder);
        File::put($folder.'/'.$nama.'.html', $html);
    }

    private function akun(?array $izin = null): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => 'Humas Uji', 'aktif' => true]);
        $akun = Pengguna::create(['nama' => 'Humas Uji', 'username' => 'klasifikasi.'.Str::uuid(), 'kata_sandi' => 'UjiHumas2026!', 'peran' => 'pegawai', 'aktif' => true, 'pegawai_id' => $pegawai->id, 'wajib_ganti_kata_sandi' => false]);
        $role = Peran::where('kode', 'wakil_pimpinan_humas')->firstOrFail();
        if ($izin !== null) {
            $role = Peran::create(['kode' => 'klasifikasi_'.Str::random(8), 'nama' => 'Klasifikasi terbatas', 'aktif' => true]);
            $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        }
        $akun->daftarPeran()->sync([$role->id]);

        return $akun;
    }

    private function input(): array
    {
        return ['judul' => 'Dokumen uji Humas', 'ingatkan_hari_sebelum' => 30, 'berkas' => UploadedFile::fake()->create('dokumen.pdf', 5, 'application/pdf')];
    }

    private function dokumen(string $kategori): DokumenHumas
    {
        $d = new DokumenHumas;
        $d->forceFill(['kategori' => isset(DokumenHumas::KATEGORI[$kategori]) ? $kategori : 'lainnya', 'judul' => 'Dokumen '.Str::uuid(), 'status' => 'aktif',
            'lokasi_file' => 'dokumen-humas/'.Str::uuid().'.pdf', 'nama_file_asli' => 'arsip.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => 20,
            'dibuat_oleh_pengguna_id' => $this->humas->id, 'diubah_oleh_pengguna_id' => $this->humas->id])->save();
        Storage::disk('local')->put($d->lokasi_file, '%PDF-1.4 berkas asli');
        $d->riwayat()->create($d->only(['lokasi_file', 'nama_file_asli', 'tipe_file', 'ukuran_file']) + ['versi' => 1, 'catatan' => 'Arsip lama', 'diunggah_pada' => now()]);
        $d->forceFill(['urusan' => null, 'jenis_dokumen' => null, 'sumber_klasifikasi' => null])->save();

        return $d->fresh();
    }

    private function agenda(string $jenis): AgendaHumas
    {
        return AgendaHumas::create(['judul' => 'Rapat '.$jenis, 'jenis' => $jenis, 'waktu_mulai' => now(), 'waktu_selesai' => now()->addHours(2), 'tempat' => 'Aula', 'topik' => 'Koordinasi', 'status' => 'terjadwal']);
    }

    private function sidik(DokumenHumas $d): string
    {
        return app(KelolaDokumenHumasService::class)->sidik($d);
    }

    private function kolektif(array $dokumen, string $urusan, string $jenis)
    {
        return $this->patch(route('dokumen-humas.klasifikasi-kolektif'), ['ids' => array_map(fn ($d) => $d->id, $dokumen),
            'sidik' => collect($dokumen)->mapWithKeys(fn ($d) => [$d->id => $this->sidik($d)])->all(), 'urusan' => $urusan, 'jenis_dokumen' => $jenis, 'alasan' => 'Pemeriksaan klasifikasi arsip lama']);
    }

    private function kaitan(DokumenHumas $d, string $jenis, int $id, string $aksi = 'tambah')
    {
        return $this->patch(route('dokumen-humas.kaitan', $d), ['jenis_kaitan' => $jenis, 'target_id' => $id, 'aksi' => $aksi,
            'sidik' => $this->sidik($d), 'alasan' => 'Dokumen pendukung kegiatan']);
    }

    private function token(?Pengguna $akun = null): void
    {
        auth()->forgetGuards();
        $this->withToken(($akun ?? $this->humas)->createToken('uji', ['mobile'])->plainTextToken);
    }
}
