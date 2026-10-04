<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\KerjaSamaHumas;
use App\Models\MitraHumas;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Services\Humas\IngatkanKerjaSamaHumasService;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

class KemitraanHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));
        Storage::fake('local');
    }

    public function test_hak_akses_dan_menu_humas_pimpinan_serta_akun_lain(): void
    {
        $mitra = $this->mitra();
        $mou = $this->mou($mitra);
        $this->get(route('kemitraan-humas.index'))->assertRedirect(route('login'));
        foreach (['siswa', 'orang_tua', 'satpam', 'guru_mapel', 'pegawai'] as $role) {
            $this->actingAs($this->akun($role))->get(route('kemitraan-humas.index'))->assertForbidden();
            $this->get(route('kemitraan-humas.show', $mitra))->assertForbidden();
            $this->get(route('kemitraan-humas.mou.show', [$mitra, $mou]))->assertForbidden();
            $this->get(route('kemitraan-humas.export'))->assertForbidden();
            $this->post(route('kemitraan-humas.store'), $this->dataMitra())->assertForbidden();
        }
        $this->actingAs($this->akun('pimpinan'))->get(route('kemitraan-humas.index'))->assertOk()->assertDontSee('Tambah mitra');
        $this->get(route('kemitraan-humas.show', $mitra))->assertOk()->assertDontSee('Edit mitra');
        $this->get(route('kemitraan-humas.mou.show', [$mitra, $mou]))->assertOk()->assertDontSee('Edit MoU');
        $this->get(route('kemitraan-humas.create'))->assertForbidden();
        $this->get(route('kemitraan-humas.mou.create', $mitra))->assertForbidden();
        $this->put(route('kemitraan-humas.update', $mitra), $this->dataMitra() + ['versi' => 0])->assertForbidden();
        $this->post(route('kemitraan-humas.mou.store', $mitra), $this->dataMou())->assertForbidden();
        $this->get(route('kemitraan-humas.cetak'))->assertOk();
        $this->actingAs($this->akun('wakil_pimpinan_humas'))->get(route('kemitraan-humas.index'))
            ->assertOk()->assertSee(route('kemitraan-humas.index'))->assertSee('Tambah mitra');
    }

    public function test_mitra_dapat_dibuat_dikoreksi_dengan_versi_dan_jejak_perubahan(): void
    {
        $akun = $this->akun('wakil_pimpinan_humas');
        $this->actingAs($akun)->post(route('kemitraan-humas.store'), $this->dataMitra() + ['diubah_oleh_pengguna_id' => 999])->assertRedirect()->assertSessionHasNoErrors();
        $mitra = MitraHumas::firstOrFail();
        $this->assertSame($akun->id, $mitra->diubah_oleh_pengguna_id);
        $this->assertSame(1, $mitra->riwayat()->count());
        $data = array_replace($this->dataMitra(), ['nama' => 'Mitra baru', 'versi' => 0]);
        $this->put(route('kemitraan-humas.update', $mitra), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $mitra->fresh()->versi);
        $this->assertSame('Dinas Pendidikan Kota', $mitra->riwayat()->firstOrFail()->data_sebelum['nama']);
        $this->put(route('kemitraan-humas.update', $mitra), $data)->assertSessionHasErrors('versi');
        $this->put(route('kemitraan-humas.update', $mitra), array_replace($data, ['versi' => 1]))->assertRedirect();
        $this->assertSame(2, $mitra->riwayat()->count());
        $this->post(route('kemitraan-humas.store'), array_replace($this->dataMitra(), ['email' => 'bukanemail', 'website' => 'javascript:alert(1)', 'nomor_kontak' => 'abc']))->assertSessionHasErrors(['email', 'website', 'nomor_kontak']);
    }

    public function test_mou_aktif_memerlukan_berkas_tanggal_valid_dan_tidak_bisa_dipindah_ke_mitra_lain(): void
    {
        $mitra = $this->mitra();
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $url = route('kemitraan-humas.mou.store', $mitra);
        $aktif = array_replace($this->dataMou(), ['status' => 'aktif']);
        $this->post($url, $aktif)->assertSessionHasErrors('dokumen_humas_id');
        $dokumen = $this->dokumen();
        $this->post($url, array_replace($aktif, ['dokumen_humas_id' => $dokumen->id, 'tanggal_selesai' => '2026-06-30']))->assertSessionHasErrors('tanggal_selesai');
        $this->post($url, array_replace($aktif, ['dokumen_humas_id' => $dokumen->id, 'tanggal_mulai' => null, 'tanggal_selesai' => null]))->assertSessionHasErrors('tanggal_mulai');
        $this->post($url, $aktif + ['dokumen_humas_id' => $dokumen->id, 'mitra_humas_id' => 999])->assertRedirect()->assertSessionHasNoErrors();
        $mou = KerjaSamaHumas::firstOrFail();
        $this->assertSame($mitra->id, $mou->mitra_humas_id);
        $asing = $this->mitra(['nama' => 'Mitra asing']);
        $this->get(route('kemitraan-humas.mou.show', [$asing, $mou]))->assertNotFound();
        $this->get(route('kemitraan-humas.mou.edit', [$asing, $mou]))->assertNotFound();
        $this->put(route('kemitraan-humas.mou.update', [$asing, $mou]), $aktif + ['versi' => 0])->assertNotFound();
        $dokumen->update(['status' => 'arsip']);
        $this->post($url, $aktif + ['dokumen_humas_id' => $dokumen->id])->assertSessionHasErrors('dokumen_humas_id');
        $dokumen->update(['status' => 'aktif']);
        Storage::disk('local')->delete($dokumen->lokasi_file);
        $this->post($url, $aktif + ['dokumen_humas_id' => $dokumen->id])->assertSessionHasErrors('dokumen_humas_id');
    }

    public function test_status_otomatis_sesuai_tanggal_dan_ambang_pengingt_dengan_filter_sql(): void
    {
        $mitra = $this->mitra();
        $cases = [
            ['berlaku', ['tanggal_selesai' => '2027-06-30']],
            ['segera_berakhir', ['tanggal_selesai' => '2026-11-04']],
            ['berlaku', ['tanggal_selesai' => '2026-11-05']],
            ['segera_berakhir', ['tanggal_selesai' => '2026-10-05', 'ingatkan_hari_sebelum' => 0]],
            ['kedaluwarsa', ['tanggal_selesai' => '2026-10-04']],
            ['belum_mulai', ['tanggal_mulai' => '2026-10-06', 'tanggal_selesai' => '2026-10-10']],
            ['draf', ['status' => 'draf']],
            ['diakhiri', ['status' => 'diakhiri']],
            ['berlaku', ['tanggal_selesai' => '2026-10-13', 'ingatkan_hari_sebelum' => 7]],
            ['segera_berakhir', ['tanggal_selesai' => '2026-10-13', 'ingatkan_hari_sebelum' => 14]],
        ];
        foreach ($cases as [$status, $data]) {
            $mou = $this->mou($mitra, array_replace(['status' => 'aktif'], $data));
            $this->assertSame($status, $mou->statusBerlaku());
            foreach (array_keys(KerjaSamaHumas::STATUS_BERLAKU) as $filter) {
                $this->assertSame($status === $filter, KerjaSamaHumas::query()->whereKey($mou->id)->denganStatusBerlaku($filter)->exists());
            }
        }
    }

    public function test_pengakhiran_koreksi_lama_dan_arsip_mitra_menjaga_riwayat(): void
    {
        $mitra = $this->mitra();
        $mou = $this->mou($mitra, ['status' => 'aktif', 'dokumen_humas_id' => $this->dokumen()->id]);
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $arsip = array_replace($this->dataMitra(), ['status' => 'arsip', 'versi' => 0]);
        $this->put(route('kemitraan-humas.update', $mitra), $arsip)->assertSessionHasErrors('status');
        $data = array_replace($this->dataMou(), ['status' => 'diakhiri', 'versi' => 0]);
        $this->put(route('kemitraan-humas.mou.update', [$mitra, $mou]), $data)->assertSessionHasErrors('alasan_diakhiri');
        $data['alasan_diakhiri'] = 'Kegiatan telah diselesaikan berdasarkan kesepakatan.';
        $this->put(route('kemitraan-humas.mou.update', [$mitra, $mou]), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('diakhiri', $mou->fresh()->status);
        $this->assertSame('aktif', $mou->riwayat()->firstOrFail()->data_sebelum['status']);
        $this->put(route('kemitraan-humas.mou.update', [$mitra, $mou]), $data)->assertSessionHasErrors('versi');
        $this->put(route('kemitraan-humas.update', $mitra), $arsip)->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('kemitraan-humas.mou.create', $mitra))->assertSessionHasErrors('mitra');
        $this->post(route('kemitraan-humas.mou.store', $mitra), $this->dataMou())->assertSessionHasErrors('mitra');
        $this->get(route('kemitraan-humas.show', $mitra))->assertOk()->assertSee($mou->judul);
        $this->assertModelExists($mou);
        $this->put(route('kemitraan-humas.mou.update', [$mitra, $mou]), array_replace($this->dataMou(), ['status' => 'aktif', 'versi' => 1]))->assertSessionHasErrors('mitra');
    }

    public function test_izin_kemitraan_tidak_membocorkan_atau_mengubah_dokumen_dan_agenda(): void
    {
        $mitra = $this->mitra();
        $doc = $this->dokumen(['judul' => 'MoU rahasia', 'nama_file_asli' => 'rahasia.pdf']);
        $mou = $this->mou($mitra, ['dokumen_humas_id' => $doc->id]);
        $agenda = $this->agenda();
        $mitra->agenda()->attach($agenda);
        $terbatas = $this->akunIzin(['kemitraan_humas.lihat', 'kemitraan_humas.kelola']);
        $this->actingAs($terbatas)->get(route('kemitraan-humas.mou.show', [$mitra, $mou]))->assertOk()->assertDontSee('rahasia.pdf')->assertDontSee('MoU rahasia')->assertDontSee('Unduh MoU');
        $this->get(route('kemitraan-humas.show', [$mitra, 'tab' => 'kegiatan']))->assertOk()->assertDontSee($agenda->judul);
        $this->get(route('kemitraan-humas.mou.edit', [$mitra, $mou]))->assertOk()->assertDontSee('MoU rahasia');
        $this->get(route('dokumen-humas.unduh', $doc))->assertForbidden();
        $this->post(route('kemitraan-humas.mou.store', $mitra), $this->dataMou() + ['dokumen_humas_id' => $doc->id])->assertForbidden();
        $this->put(route('kemitraan-humas.mou.update', [$mitra, $mou]), $this->dataMou() + ['versi' => 0])->assertRedirect();
        $this->assertSame($doc->id, $mou->fresh()->dokumen_humas_id);
        $this->post(route('kemitraan-humas.agenda.store', $mitra), ['agenda_humas_id' => $agenda->id])->assertForbidden();
        $this->actingAs($this->akunIzin(['dokumen_humas.kelola']))->get(route('dokumen-humas.create', ['kerja_sama_humas_id' => $mou->id]))->assertForbidden();
        $this->post(route('dokumen-humas.store'), $this->upload($mou))->assertForbidden();
        $this->actingAs($this->akunIzin(['agenda_humas.kelola']))->get(route('agenda-humas.create', ['mitra_humas_id' => $mitra->id]))->assertForbidden();
    }

    public function test_unggah_mou_ke_arsip_privat_terhubung_otomatis_dan_idempoten(): void
    {
        $mitra = $this->mitra();
        $mou = $this->mou($mitra);
        $akun = $this->akun('wakil_pimpinan_humas');
        $this->actingAs($akun)->get(route('dokumen-humas.create', ['kerja_sama_humas_id' => $mou->id]))->assertOk()->assertSee('data-mou-upload', false)->assertSee($mou->judul);
        $data = $this->upload($mou);
        $this->postJson(route('dokumen-humas.store'), $data)->assertOk()->assertJsonPath('redirect', route('kemitraan-humas.mou.show', [$mitra, $mou]));
        $doc = $mou->fresh()->dokumen;
        Storage::disk('local')->assertExists($doc->lokasi_file);
        $this->assertSame('draf', $mou->fresh()->status);
        $this->assertSame(1, $mou->fresh()->versi);
        $this->postJson(route('dokumen-humas.store'), $data)->assertOk();
        $this->assertDatabaseCount('dokumen_humas', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('dokumen-humas'));
        $this->assertSame(1, $mou->riwayat()->count());
        $this->assertArrayNotHasKey('token_unggahan_mou', $mou->riwayat()->first()->data_sesudah);
        $this->get(route('dokumen-humas.show', $doc))->assertOk()->assertSee(route('kemitraan-humas.mou.show', [$mitra, $mou]));
        $this->get(route('kemitraan-humas.mou.show', [$mitra, $mou]))->assertOk()->assertSee('Unduh MoU')->assertSee(route('dokumen-humas.unduh', $doc));
        $this->get(route('dokumen-humas.unduh', $doc))->assertOk();
        $this->postJson(route('dokumen-humas.store'), array_replace($data, ['token_unggahan_mou' => (string) Str::uuid()]))->assertUnprocessable()->assertJsonValidationErrors('kerja_sama_humas_id');
        $this->assertCount(1, Storage::disk('local')->allFiles('dokumen-humas'));
    }

    public function test_unggahan_mou_batal_atau_arsip_dan_konteks_ganda_tidak_meninggalkan_file(): void
    {
        $mitra = $this->mitra(['status' => 'arsip']);
        $mou = $this->mou($mitra);
        $this->actingAs($this->akun('wakil_pimpinan_humas'))->postJson(route('dokumen-humas.store'), $this->upload($mou))->assertUnprocessable()->assertJsonValidationErrors('mitra');
        $mitra->update(['status' => 'aktif']);
        $mou->update(['status' => 'diakhiri']);
        $this->postJson(route('dokumen-humas.store'), $this->upload($mou))->assertUnprocessable()->assertJsonValidationErrors('kerja_sama_humas_id');
        $mou->update(['status' => 'draf']);
        $this->postJson(route('dokumen-humas.store'), $this->upload($mou) + ['agenda_humas_id' => $this->agenda()->id])->assertUnprocessable()->assertJsonValidationErrors('agenda_humas_id');
        $this->assertDatabaseCount('dokumen_humas', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('dokumen-humas'));
    }

    public function test_agenda_mitra_otomatis_terhubung_dan_lepas_hubungan_tidak_menghapus_agenda(): void
    {
        $mitra = $this->mitra();
        $this->actingAs($this->akun('wakil_pimpinan_humas'))->get(route('agenda-humas.create', ['mitra_humas_id' => $mitra->id]))->assertOk()->assertSee('mitra_humas_id');
        $this->post(route('agenda-humas.store'), $this->dataAgenda() + ['mitra_humas_id' => $mitra->id])->assertRedirect()->assertSessionHasNoErrors();
        $agenda = AgendaHumas::firstOrFail();
        $this->assertSame(1, $mitra->agenda()->count());
        $this->get(route('agenda-humas.show', $agenda))->assertOk()->assertSee(route('kemitraan-humas.show', $mitra));
        $this->post(route('kemitraan-humas.agenda.store', $mitra), ['agenda_humas_id' => $agenda->id])->assertRedirect();
        $this->assertSame(1, $mitra->agenda()->count());
        $this->assertSame(1, $mitra->riwayat()->count());
        $this->delete(route('kemitraan-humas.agenda.destroy', [$this->mitra(), $agenda]))->assertNotFound();
        $this->delete(route('kemitraan-humas.agenda.destroy', [$mitra, $agenda]))->assertRedirect();
        $this->assertModelExists($agenda);
        $this->assertSame(0, $mitra->agenda()->count());
        $mitra->update(['status' => 'arsip']);
        $this->post(route('agenda-humas.store'), $this->dataAgenda() + ['mitra_humas_id' => $mitra->id])->assertSessionHasErrors('mitra');
        $this->post(route('kemitraan-humas.agenda.store', $mitra), ['agenda_humas_id' => $agenda->id])->assertSessionHasErrors('mitra');
        $this->assertDatabaseCount('agenda_humas', 1);
    }

    public function test_pengingt_mou_tidak_berulang_dan_membedakan_mendekati_hari_akhir_dan_kedaluwarsa(): void
    {
        $mitra = $this->mitra();
        $mou = $this->mou($mitra, ['status' => 'aktif', 'tanggal_selesai' => '2026-10-10', 'ingatkan_hari_sebelum' => 7]);
        $this->mou($mitra, ['status' => 'draf', 'tanggal_selesai' => '2026-10-06']);
        $this->mou($mitra, ['status' => 'diakhiri', 'tanggal_selesai' => '2026-10-06']);
        $this->mou($this->mitra(['status' => 'arsip']), ['status' => 'aktif', 'tanggal_selesai' => '2026-10-06']);
        $akun = $this->akun('wakil_pimpinan_humas');
        $jumlah = app(NotifikasiPenggunaService::class)->penggunaDenganIzin('kemitraan_humas.kelola')->count();
        $service = app(IngatkanKerjaSamaHumasService::class);
        $this->assertSame($jumlah, $service->kirimPengingat());
        $this->assertSame(0, $service->kirimPengingat());
        $this->assertSame(1, $akun->notifikasiPengguna()->count());
        $this->travelTo(now()->setDate(2026, 10, 10));
        $this->assertSame($jumlah, $service->kirimPengingat());
        $this->assertSame(0, $service->kirimPengingat());
        $this->travel(1)->days();
        $this->assertSame($jumlah, $service->kirimPengingat());
        $this->assertSame(0, $service->kirimPengingat());
        $mou->update(['tanggal_selesai' => '2026-10-13']);
        $this->assertSame($jumlah, $service->kirimPengingat());
    }

    public function test_filter_rekap_cetak_excel_sama_dan_teks_tidak_menjadi_formula(): void
    {
        $mitra = $this->mitra(['nama' => '=SUM(1,2)', 'nomor_kontak' => '081234567890']);
        $mou = $this->mou($mitra, ['judul' => 'A & B < C', 'status' => 'aktif', 'tanggal_selesai' => '2026-10-12']);
        $this->mou($mitra, ['judul' => 'Di luar filter', 'bidang' => 'kesehatan']);
        $this->actingAs($this->akun('pimpinan'));
        $filter = ['tab' => 'mou', 'bidang' => 'pendidikan', 'masa_berlaku' => 'segera_berakhir', 'dari' => '2026-10-01', 'sampai' => '2026-10-31'];
        $this->get(route('kemitraan-humas.index', $filter))->assertOk()->assertSee(e($mou->judul), false)->assertDontSee('Di luar filter');
        $this->get(route('kemitraan-humas.cetak', $filter))->assertOk()->assertSee(e($mou->judul), false)->assertDontSee('Di luar filter')->assertDontSee('081234567890');
        $response = $this->get(route('kemitraan-humas.export', $filter))->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $this->assertNotFalse(simplexml_load_string($zip->getFromIndex($i)));
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('081234567890', $sheet);
            $this->assertStringContainsString('=SUM(1,2)', $sheet);
            $this->assertStringNotContainsString('<f>', $sheet);
            $this->assertStringContainsString('A &amp; B &lt; C', $sheet);
            $this->assertStringNotContainsString('Di luar filter', $sheet);
            $zip->close();
        } finally {
            @unlink($path);
        }
        $this->get(route('kemitraan-humas.index', ['dari' => '2026-10-31', 'sampai' => '2026-10-01']))->assertSessionHasErrors('sampai');
        $this->get(route('kemitraan-humas.index', ['masa_berlaku' => 'palsu']))->assertSessionHasErrors('masa_berlaku');
    }

    public function test_semua_tampilan_render_dan_menolak_html_berbahaya(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $mitra = $this->mitra(['nama' => 'Dinas Pendidikan dan Kebudayaan Kota Padang Panjang', 'alamat' => str_repeat('Alamat mitra yang cukup panjang. ', 10), 'catatan' => str_repeat('Catatan kerja sama sekolah. ', 10)]);
        $this->post(route('kemitraan-humas.mou.store', $mitra), $this->dataMou())->assertRedirect();
        $mou = $mitra->kerjaSama()->firstOrFail();
        $this->capture('upload', $this->get(route('dokumen-humas.create', ['kerja_sama_humas_id' => $mou->id]))->assertOk());
        $this->post(route('dokumen-humas.store'), $this->upload($mou))->assertRedirect();
        $this->capture('mou', $this->get(route('kemitraan-humas.mou.show', [$mitra, $mou]))->assertOk());
        $this->capture('mou-form', $this->get(route('kemitraan-humas.mou.edit', [$mitra, $mou]))->assertOk());
        $this->capture('mitra-form', $this->get(route('kemitraan-humas.create'))->assertOk());
        $this->get(route('kemitraan-humas.edit', $mitra))->assertOk();
        $mitra->agenda()->attach($this->agenda());
        foreach (['mou' => 'mitra', 'kegiatan' => 'kegiatan', 'riwayat' => 'riwayat'] as $tab => $nama) {
            $this->capture($nama, $this->get(route('kemitraan-humas.show', [$mitra, 'tab' => $tab]))->assertOk());
        }
        for ($i = 0; $i < 24; $i++) {
            $this->mou($mitra, ['judul' => 'Program kerja sama sekolah nomor '.$i, 'ruang_lingkup' => str_repeat('Program kerja sama dengan manfaat untuk sekolah. ', 10)]);
        }
        $this->capture('index', $this->get(route('kemitraan-humas.index'))->assertOk());
        $this->capture('rekap', $this->get(route('kemitraan-humas.index', ['tab' => 'mou']))->assertOk());
        $this->capture('cetak', $this->get(route('kemitraan-humas.cetak'))->assertOk());
        $this->assertSame(20, $this->get(route('kemitraan-humas.index', ['tab' => 'mou']))->viewData('daftarMou')->count());
        $this->get(route('kemitraan-humas.index', ['kata_kunci' => 'tidak ditemukan']))->assertOk()->assertDontSee($mitra->nama);
        $berbahaya = '</title><script>alert(1)</script>';
        $mitra->update(['nama' => $berbahaya]);
        $this->get(route('kemitraan-humas.show', $mitra))->assertOk()->assertDontSee($berbahaya, false)->assertSee(e($berbahaya), false);
    }

    private function akun(string $role): Pengguna
    {
        $akun = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'mitra.'.uniqid(), 'kata_sandi' => 'TesMitra123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $akun;
    }

    private function akunIzin(array $izin): Pengguna
    {
        $peran = Peran::create(['kode' => 'mitra_'.uniqid(), 'nama' => 'Mitra terbatas', 'aktif' => true]);
        $peran->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $akun = $this->akun('pegawai');
        $akun->daftarPeran()->attach($peran);

        return $akun;
    }

    private function dataMitra(): array
    {
        return ['nama' => 'Dinas Pendidikan Kota', 'jenis' => 'pemerintah', 'nama_kontak' => 'Kontak mitra', 'nomor_kontak' => '081234567890', 'status' => 'aktif'];
    }

    private function mitra(array $data = []): MitraHumas
    {
        return MitraHumas::create(array_replace($this->dataMitra(), $data));
    }

    private function dataMou(): array
    {
        return ['judul' => 'Kerja sama peningkatan mutu pendidikan', 'nomor' => '001/MoU/2026', 'bidang' => 'pendidikan', 'ruang_lingkup' => 'Program bersama untuk mendukung pembelajaran siswa.', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'ingatkan_hari_sebelum' => 30, 'status' => 'draf'];
    }

    private function mou(MitraHumas $mitra, array $data = []): KerjaSamaHumas
    {
        return $mitra->kerjaSama()->create(array_replace($this->dataMou(), $data));
    }

    private function dokumen(array $data = []): DokumenHumas
    {
        $path = 'dokumen-humas/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, 'berkas uji');

        return DokumenHumas::create(array_replace(['judul' => 'Berkas MoU sekolah', 'kategori' => 'kemitraan', 'lokasi_file' => $path, 'nama_file_asli' => 'mou.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => 20, 'status' => 'aktif'], $data));
    }

    private function upload(KerjaSamaHumas $mou): array
    {
        return ['kerja_sama_humas_id' => $mou->id, 'token_unggahan_mou' => (string) Str::uuid(), 'judul' => $mou->judul, 'kategori' => 'kemitraan', 'ingatkan_hari_sebelum' => 30, 'berkas' => UploadedFile::fake()->create('mou.pdf', 20, 'application/pdf')];
    }

    private function dataAgenda(): array
    {
        return ['judul' => 'Pertemuan koordinasi mitra', 'jenis' => 'kemitraan', 'waktu_mulai' => '2026-10-06T09:00', 'waktu_selesai' => '2026-10-06T10:00', 'tempat' => 'Aula sekolah', 'topik' => 'Tindak lanjut program bersama'];
    }

    private function agenda(): AgendaHumas
    {
        return AgendaHumas::create($this->dataAgenda());
    }

    private function capture(string $nama, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_KEMITRAAN_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/kemitraan-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$nama.'.html', $response->getContent());
    }
}
