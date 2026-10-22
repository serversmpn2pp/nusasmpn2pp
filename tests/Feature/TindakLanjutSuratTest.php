<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\SuratTu;
use App\Models\TindakLanjutSuratTu;
use App\Services\Notifikasi\IsiPushNotifikasiService;
use App\Services\Persuratan\IngatkanTindakLanjutSuratService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TindakLanjutSuratTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $tu;

    private Pengguna $kepala;

    private Pengguna $guru;

    private Pengguna $lain;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.firebase.push_enabled' => false]);
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
        $this->tu = $this->akun('tata_usaha', 'Petugas TU');
        $this->kepala = $this->akun('pimpinan', 'Kepala Sekolah');
        $this->guru = $this->akun('guru_mapel', 'Guru Satu');
        $this->lain = $this->akun('guru_mapel', 'Guru Dua');
    }

    public function test_disposisi_hanya_pejabat_berwenang_penerima_aktif_dan_notifikasi_tidak_ganda(): void
    {
        $surat = $this->surat();
        $data = $this->isiDisposisi($surat);
        $this->actingAs($this->tu)->post(route('persuratan.disposisi', $surat), $data)->assertForbidden();
        $this->actingAs($this->guru)->post(route('persuratan.disposisi', $surat), $data)->assertForbidden();
        $this->actingAs($this->kepala)->post(route('persuratan.disposisi', $surat), $data)->assertSessionHasNoErrors();
        $t = $surat->tindakLanjut()->firstOrFail();
        $this->assertSame('belum_mulai', $t->status);
        $this->assertSame($this->kepala->id, $t->pemeriksa_pengguna_id);
        $this->assertSame(1, $this->guru->notifikasiPengguna()->count());
        $this->post(route('persuratan.disposisi', $surat), $data)->assertSessionHasErrors('tindak_lanjut');
        $data['versi'] = $surat->fresh()->versi;
        $this->post(route('persuratan.disposisi', $surat), $data)->assertSessionHasErrors('tindak_lanjut');
        $this->assertSame(1, $this->guru->notifikasiPengguna()->count());
        $data['pegawai_ids'] = [$this->lain->pegawai_id];
        $this->post(route('persuratan.disposisi', $surat), $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $surat->tindakLanjut()->count());
        $this->actingAs($this->tu)->get(route('persuratan.edit', $surat))->assertForbidden();
        $this->assertFalse($surat->fresh()->dapatDiedit());
    }

    public function test_disposisi_validasi_tenggat_instruksi_akun_dan_berkas_induk(): void
    {
        $surat = $this->surat();
        $data = $this->isiDisposisi($surat);
        $this->actingAs($this->kepala)->post(route('persuratan.disposisi', $surat), array_replace($data, ['batas_tanggal' => '2026-10-11']))->assertSessionHasErrors('batas_tanggal');
        $this->post(route('persuratan.disposisi', $surat), array_replace($data, ['instruksi' => '   ']))->assertSessionHasErrors();
        $this->post(route('persuratan.disposisi', $surat), array_replace($data, ['pegawai_ids' => [$this->kepala->pegawai_id]]))->assertSessionHasErrors('tindak_lanjut');
        $this->guru->pegawai->update(['aktif' => false]);
        $this->post(route('persuratan.disposisi', $surat), $data)->assertSessionHasErrors('tindak_lanjut');
        $this->guru->pegawai->update(['aktif' => true]);
        Storage::disk('local')->put($surat->berkas()->first()->lokasi_file, 'berubah');
        $this->post(route('persuratan.disposisi', $surat), $data)->assertSessionHasErrors('tindak_lanjut');
        $this->assertDatabaseCount('tindak_lanjut_surat_tu', 0);
    }

    public function test_akses_penerima_rahasia_dibatasi_ke_tugas_sendiri_dan_siswa_ortu_ditolak(): void
    {
        $surat = $this->surat('masuk', ['sifat' => 'rahasia']);
        $t = $this->disposisi($surat);
        $this->actingAs($this->guru)->get(route('tindak-lanjut-surat.index'))->assertOk()->assertSee($surat->nomor_surat);
        $this->get(route('tindak-lanjut-surat.show', $t))->assertOk()->assertDontSee('Guru Dua');
        $this->get(route('tindak-lanjut-surat.monitoring'))->assertForbidden();
        $unduhan = $this->get(route('tindak-lanjut-surat.berkas', [$t, $surat->berkas()->first()]))->assertOk();
        $this->assertStringContainsString('no-store', $unduhan->headers->get('Cache-Control'));
        $this->actingAs($this->lain)->get(route('tindak-lanjut-surat.show', $t))->assertNotFound();
        $this->get(route('tindak-lanjut-surat.index'))->assertDontSee($surat->nomor_surat);
        $izin = Izin::where('kode', 'surat.rahasia')->first();
        $this->tu->daftarPeran()->first()->izin()->detach($izin);
        $this->actingAs($this->tu->fresh())->get(route('tindak-lanjut-surat.show', $t))->assertNotFound();
        $ortu = $this->akun('tata_usaha', 'Orang Tua');
        $ortu->update(['pegawai_id' => null]);
        OrangTuaWali::create(['pengguna_id' => $ortu->id, 'nama_lengkap' => 'Orang Tua', 'aktif' => true]);
        $this->actingAs($ortu)->get(route('tindak-lanjut-surat.index'))->assertForbidden();
        $s = Siswa::create(['nisn' => '9001101234', 'nama_lengkap' => 'Siswa', 'jenis_kelamin' => 'L', 'aktif' => true]);
        $akun = $this->akun('pimpinan', 'Siswa');
        $akun->update(['siswa_id' => $s->id]);
        $this->actingAs($akun)->get(route('tindak-lanjut-surat.show', $t))->assertForbidden();
    }

    public function test_konfirmasi_baca_bukan_selesai_dan_mulai_tidak_menutup_tugas(): void
    {
        $t = $this->disposisi();
        $this->aksi($t, 'baca', $this->guru)->assertSessionHasNoErrors();
        $this->assertSame('belum_mulai', $t->fresh()->status);
        $this->assertNotNull($t->fresh()->dibaca_pada);
        $jumlah = $t->riwayat()->count();
        $this->aksi($t, 'baca', $this->guru)->assertSessionHasNoErrors();
        $this->assertSame($jumlah, $t->riwayat()->count());
        $this->aksi($t, 'mulai', $this->guru)->assertSessionHasNoErrors();
        $this->assertSame('diproses', $t->fresh()->status);
        $this->assertNull($t->fresh()->selesai_pada);
    }

    public function test_progres_pengajuan_pengembalian_perbaikan_dan_penerimaan_menyimpan_semua_versi(): void
    {
        $t = $this->disposisi();
        $this->lapor($t, 'draf', ['bukti' => [$this->pdf('foto.pdf')]])->assertSessionHasNoErrors();
        $lama = $t->laporan()->firstOrFail();
        $this->assertSame('diproses', $t->fresh()->status);
        $this->assertNull($lama->diajukan_pada);
        $this->aksi($t, 'terima', $this->kepala)->assertSessionHasErrors('tindak_lanjut');
        $this->lapor($t, 'ajukan', ['bukti_lama' => [$lama->bukti()->first()->id]])->assertSessionHasNoErrors();
        $this->assertSame('menunggu_pemeriksaan', $t->fresh()->status);
        $this->lapor($t, 'draf')->assertSessionHasErrors('tindak_lanjut');
        $this->aksi($t, 'terima', $this->tu)->assertForbidden();
        $pejabat = $this->akun('pimpinan', 'Pejabat lain');
        $this->aksi($t, 'terima', $pejabat)->assertForbidden();
        $this->aksi($t, 'kembalikan', $this->kepala)->assertSessionHasErrors('catatan');
        $this->aksi($t, 'kembalikan', $this->kepala, ['catatan' => 'Lengkapi hasil.'])->assertSessionHasNoErrors();
        $ditolak = $t->laporan()->first();
        $this->assertSame('dikembalikan', $ditolak->keputusan);
        $this->assertSame('perlu_perbaikan', $t->fresh()->status);
        $this->lapor($t, 'ajukan', ['hasil' => 'Hasil diperbaiki.', 'bukti_lama' => [$ditolak->bukti()->first()->id]])->assertSessionHasNoErrors();
        $this->aksi($t, 'terima', $this->kepala, ['catatan' => 'Sesuai.'])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $t->fresh()->status);
        $this->assertNotNull($t->fresh()->selesai_pada);
        $this->assertSame(3, $t->laporan()->count());
        $this->assertSame('Hasil tindak lanjut.', $lama->fresh()->hasil);
        $this->assertSame('diterima', $t->laporan()->first()->keputusan);
        $this->lapor($t, 'ajukan')->assertSessionHasErrors('tindak_lanjut');
        $this->aksi($t, 'terima', $this->kepala)->assertSessionHasErrors('tindak_lanjut');
        $this->actingAs($this->guru)->get(route('tindak-lanjut-surat.show', $t))->assertOk()->assertSee('Sesuai.')->assertSee('Lengkapi hasil.');
    }

    public function test_surat_tugas_otomatis_memiliki_pelaporan_tanpa_notifikasi_ganda(): void
    {
        $surat = $this->disahkan();
        $this->assertSame(2, $surat->tindakLanjut()->count());
        $this->assertSame(1, $this->guru->notifikasiPengguna()->count());
        $t = $surat->tindakLanjut()->where('pegawai_id', $this->guru->pegawai_id)->firstOrFail();
        $this->assertSame('2026-10-20', $t->batas_tanggal->toDateString());
        $this->aksi($t, 'baca', $this->guru)->assertSessionHasNoErrors();
        $this->assertNotNull($surat->penugasan()->where('pegawai_id', $this->guru->pegawai_id)->first()->dibaca_pada);
        $this->actingAs($this->guru)->get(route('surat-saya.show', $surat))->assertOk()->assertSee('Lihat tindak lanjut')->assertDontSee('Guru Dua');
        $this->lapor($t, 'ajukan')->assertSessionHasNoErrors();
        $this->aksi($t, 'terima', $this->kepala)->assertSessionHasNoErrors();
    }

    public function test_versi_lama_upload_gagal_tidak_menyisakan_berkas_atau_laporan(): void
    {
        $t = $this->disposisi();
        $versi = $t->versi;
        $this->aksi($t, 'mulai', $this->guru)->assertSessionHasNoErrors();
        $this->lapor($t, 'draf', ['versi' => $versi, 'bukti' => [$this->pdf()]])->assertSessionHasErrors('tindak_lanjut');
        $this->assertDatabaseCount('laporan_tindak_lanjut_surat_tu', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('persuratan-tindak-lanjut'));
        $this->lapor($t, 'ajukan', ['bukti' => [UploadedFile::fake()->create('bahaya.html', 20, 'text/html')]])->assertSessionHasErrors('bukti.0');
        $this->assertDatabaseCount('laporan_tindak_lanjut_surat_tu', 0);
    }

    public function test_bukti_lintas_tugas_unduhan_dan_integritas_ditolak(): void
    {
        $t = $this->disposisi();
        $this->lapor($t, 'draf', ['bukti' => [$this->pdf()]])->assertSessionHasNoErrors();
        $b = $t->laporan()->first()->bukti()->firstOrFail();
        $surat = $this->surat('masuk', ['nomor_surat' => 'D/2', 'nomor_agenda' => 'AG/2']);
        $lain = $this->disposisi($surat);
        $this->lapor($lain, 'draf', ['bukti_lama' => [$b->id], 'bukti' => [$this->pdf('baru.pdf')]])->assertSessionHasErrors('tindak_lanjut');
        $this->assertCount(1, Storage::disk('local')->allFiles('persuratan-tindak-lanjut'));
        $this->actingAs($this->guru)->get(route('tindak-lanjut-surat.bukti', [$lain, $b]))->assertNotFound();
        $this->get(route('tindak-lanjut-surat.berkas', [$t, $surat->berkas()->first()]))->assertNotFound();
        $this->get(route('tindak-lanjut-surat.bukti', [$t, $b]))->assertOk();
        $this->actingAs($this->lain)->get(route('tindak-lanjut-surat.bukti', [$t, $b]))->assertNotFound();
        Storage::disk('local')->put($b->lokasi_file, 'rusak');
        $this->actingAs($this->guru)->get(route('tindak-lanjut-surat.bukti', [$t, $b]))->assertNotFound();
        $this->lapor($t, 'ajukan', ['bukti_lama' => [$b->id]])->assertSessionHasErrors('tindak_lanjut');
        $lokasi = 'di-luar/bukti.pdf';
        Storage::disk('local')->put($lokasi, 'dokumen');
        $b->update(['lokasi_file' => $lokasi, 'sha256' => hash('sha256', 'dokumen')]);
        $this->get(route('tindak-lanjut-surat.bukti', [$t, $b]))->assertNotFound();
    }

    public function test_surat_dibatalkan_atau_digantikan_membatalkan_tugas_aktif(): void
    {
        $surat = $this->disahkan();
        $t = $surat->tindakLanjut()->firstOrFail();
        $this->lapor($t, 'ajukan')->assertSessionHasNoErrors();
        $this->actingAs($this->kepala)->post(route('persuratan.tindakan', [$surat, 'batal']), ['versi' => $surat->fresh()->versi, 'catatan' => 'Kegiatan batal.'])->assertSessionHasNoErrors();
        $this->assertSame('dibatalkan', $t->fresh()->status);
        $this->assertSame(1, $t->laporan()->count());
        $this->aksi($t, 'terima', $this->kepala)->assertSessionHasErrors('tindak_lanjut');
        $this->lapor($t, 'ajukan')->assertSessionHasErrors('tindak_lanjut');
        $surat = $this->disahkan(['nomor_surat' => 'ST/2']);
        $baru = $this->surat('tugas', ['nomor_surat' => 'ST/3', 'revisi_dari_id' => $surat->id, 'versi_asal' => $surat->versi]);
        $this->sahkan($baru);
        $this->assertSame(0, $surat->tindakLanjut()->where('status', '<>', 'dibatalkan')->count());
        $this->assertSame(2, $baru->tindakLanjut()->count());
    }

    public function test_ubah_instruksi_tenggat_pemeriksa_dan_batal_membutuhkan_alasan(): void
    {
        $t = $this->disposisi();
        $baru = $this->akun('pimpinan', 'Pemeriksa baru');
        $data = ['instruksi' => 'Instruksi baru', 'batas_tanggal' => '2026-10-22', 'pemeriksa_pengguna_id' => $baru->id, 'catatan' => 'Penyesuaian kegiatan'];
        $this->aksi($t, 'ubah', $this->tu, $data)->assertForbidden();
        $this->aksi($t, 'ubah', $this->kepala, array_replace($data, ['catatan' => '']))->assertSessionHasErrors('catatan');
        $this->aksi($t, 'ubah', $this->kepala, $data)->assertSessionHasNoErrors();
        $this->assertSame('Instruksi baru', $t->fresh()->instruksi);
        $this->assertSame($baru->id, $t->fresh()->pemeriksa_pengguna_id);
        $this->lapor($t, 'ajukan')->assertSessionHasNoErrors();
        $this->aksi($t, 'terima', $this->kepala)->assertForbidden();
        $this->aksi($t, 'ubah', $baru, $data)->assertSessionHasErrors('tindak_lanjut');
        $this->aksi($t, 'batal', $baru)->assertSessionHasErrors('catatan');
        $this->aksi($t, 'batal', $baru, ['catatan' => 'Tidak diperlukan lagi.'])->assertSessionHasNoErrors();
        $this->assertSame('dibatalkan', $t->fresh()->status);
        $this->assertSame('tercatat', $t->surat->status);
    }

    public function test_pemeriksa_tidak_aktif_atau_izin_dicabut_dan_periksa_diri_sendiri_ditolak(): void
    {
        $t = $this->disposisi();
        $this->kepala->update(['aktif' => false]);
        $this->lapor($t, 'ajukan')->assertSessionHasErrors('tindak_lanjut');
        $this->kepala->update(['aktif' => true]);
        $this->lapor($t, 'ajukan')->assertSessionHasNoErrors();
        Izin::where('kode', 'surat.disposisi')->update(['aktif' => false]);
        $this->aksi($t, 'terima', $this->kepala)->assertForbidden();
        Izin::where('kode', 'surat.disposisi')->update(['aktif' => true]);
        $s = $this->disahkan(['nomor_surat' => 'ST/10', 'pegawai_ids' => [$this->kepala->pegawai_id]]);
        $tugas = $s->tindakLanjut()->firstOrFail();
        $this->lapor($tugas, 'ajukan', [], $this->kepala)->assertSessionHasErrors('tindak_lanjut');
        $lain = $this->akun('pimpinan', 'Pemeriksa lain');
        $this->aksi($tugas, 'ubah', $this->kepala, ['instruksi' => $tugas->instruksi, 'batas_tanggal' => '2026-10-20', 'pemeriksa_pengguna_id' => $lain->id, 'catatan' => 'Hindari pemeriksaan sendiri.'])->assertSessionHasNoErrors();
        $this->lapor($tugas, 'ajukan', [], $this->kepala)->assertSessionHasNoErrors();
        $this->aksi($tugas, 'terima', $lain)->assertSessionHasNoErrors();
    }

    public function test_pengingat_deduplikasi_tenggat_bukan_penutupan_otomatis(): void
    {
        $t = $this->disposisi();
        $service = app(IngatkanTindakLanjutSuratService::class);
        $this->assertSame(0, $service->kirimPengingat());
        $this->travelTo(now()->setDate(2026, 10, 18));
        $this->assertSame(1, $service->kirimPengingat());
        $this->assertSame(0, $service->kirimPengingat());
        $this->travelTo(now()->setDate(2026, 10, 21));
        $this->assertSame(1, $service->kirimPengingat());
        $this->assertSame(0, $service->kirimPengingat());
        $this->assertSame('belum_mulai', $t->fresh()->status);
        $this->assertTrue($t->fresh()->terlambat());
        $this->lapor($t, 'ajukan')->assertSessionHasNoErrors();
        $this->assertSame(1, $service->kirimPengingat());
        $this->assertSame(0, $service->kirimPengingat());
        $this->aksi($t, 'terima', $this->kepala)->assertSessionHasNoErrors();
        $this->assertSame(0, $service->kirimPengingat());
        $this->artisan('surat:ingatkan-tindak-lanjut')->assertSuccessful();
    }

    public function test_tampilan_monitoring_filter_riwayat_dan_teks_aman(): void
    {
        $surat = $this->surat();
        $this->actingAs($this->kepala)->get(route('persuratan.show', $surat))->assertOk()->assertSee('Berikan disposisi');
        $t = $this->disposisi($surat);
        $this->lapor($t, 'draf', ['hasil' => '<script>alert(1)</script>'])->assertSessionHasNoErrors();
        $this->get(route('tindak-lanjut-surat.show', $t))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->actingAs($this->tu)->get(route('tindak-lanjut-surat.monitoring', ['jenis' => 'disposisi', 'kata_kunci' => 'Guru Satu']))->assertOk()->assertSee($surat->nomor_surat);
        $this->get(route('tindak-lanjut-surat.monitoring', ['status' => 'selesai']))->assertOk()->assertDontSee($surat->nomor_surat);
        $this->get(route('tindak-lanjut-surat.monitoring', ['status' => 'palsu']))->assertSessionHasErrors('status');
        $this->actingAs($this->guru)->get(route('tindak-lanjut-surat.show', $t))->assertOk()->assertDontSee('Pemeriksaan laporan');
        $this->lapor($t, 'ajukan', ['hasil' => 'Hasil dokumentasi kegiatan.', 'bukti' => [$this->pdf('bukti-kegiatan.pdf')]])->assertSessionHasNoErrors();
        $this->capture('tindak-penerima', $this->actingAs($this->guru)->get(route('tindak-lanjut-surat.show', $t))->assertOk());
        $this->capture('tindak-periksa', $this->actingAs($this->kepala)->get(route('tindak-lanjut-surat.show', $t))->assertOk()->assertSee('Pemeriksaan laporan'));
        $this->capture('tindak-monitor', $this->get(route('tindak-lanjut-surat.monitoring', ['pemeriksaan_saya' => 1]))->assertOk()->assertSee($surat->nomor_surat));
        $this->capture('tindak-disposisi', $this->get(route('persuratan.show', $surat))->assertOk());
        $this->aksi($t, 'kembalikan', $this->kepala, ['catatan' => 'Tambahkan rincian.'])->assertSessionHasNoErrors();
        $this->capture('tindak-laporan', $this->actingAs($this->guru)->get(route('tindak-lanjut-surat.show', $t))->assertOk());
        $notif = NotifikasiPengguna::where('tautan', '/tindak-lanjut-surat/'.$t->id)->firstOrFail();
        $push = app(IsiPushNotifikasiService::class)->untuk($notif);
        $this->assertStringNotContainsString('D/1', json_encode($push));
    }

    public function test_monitoring_dan_tindak_lanjut_pribadi_memakai_panel_nusa_yang_sama(): void
    {
        $this->actingAs($this->tu)->get(route('tindak-lanjut-surat.monitoring'))->assertOk()
            ->assertSee('class="stats-grid surat-stats surat-stats--tindak"', false)
            ->assertSee('class="feature-filter panel panel-pad surat-filter surat-filter--tindak"', false)
            ->assertSee('class="panel surat-shell"', false)
            ->assertSee('0 tindak lanjut')
            ->assertSee('Belum ada tindak lanjut yang sesuai.');
        $t = $this->disposisi();
        $pribadi = $this->actingAs($this->guru)->get(route('tindak-lanjut-surat.index'))->assertOk()
            ->assertSee('class="stats-grid surat-stats surat-stats--tindak"', false)
            ->assertSee('class="feature-filter panel panel-pad surat-filter surat-filter--tindak"', false)
            ->assertSee('1 tindak lanjut')
            ->assertSee('scope="col">Aksi', false)
            ->assertDontSee('name="pemeriksaan_saya"', false);
        $this->capture('tindak-saya-daftar', $pribadi);
        $this->get(route('tindak-lanjut-surat.show', $t))->assertOk()
            ->assertSee('<article class="panel surat-document">', false)
            ->assertSee('data-tindak-upload', false)
            ->assertDontSee('Pemeriksaan laporan');
    }

    public function test_migrasi_mengikutkan_surat_tugas_lama_tanpa_mengirim_notifikasi(): void
    {
        $surat = $this->surat('tugas');
        $surat->update(['status' => 'disahkan', 'disahkan_pada' => now(), 'disahkan_oleh_pengguna_id' => $this->kepala->id]);
        $migration = require database_path('migrations/2026_10_12_000002_buat_tindak_lanjut_surat_tu.php');
        $migration->down();
        $migration->up();
        $this->assertSame(2, $surat->tindakLanjut()->count());
        $this->assertSame(0, NotifikasiPengguna::count());
        $this->assertSame('belum_mulai', $surat->tindakLanjut()->first()->status);
    }

    public function test_pengajuan_dan_penerimaan_ulang_versi_lama_tidak_menggandakan_laporan_notifikasi(): void
    {
        $t = $this->disposisi();
        $versi = $t->versi;
        $this->lapor($t, 'ajukan')->assertRedirect()->assertSessionHasNoErrors();
        $jumlah = NotifikasiPengguna::count();
        $this->lapor($t, 'ajukan', ['versi' => $versi])->assertSessionHasErrors('tindak_lanjut');
        $this->assertSame(1, $t->laporan()->count());
        $this->assertSame($jumlah, NotifikasiPengguna::count());
        $versi = $t->fresh()->versi;
        $this->aksi($t, 'terima', $this->kepala)->assertRedirect()->assertSessionHasNoErrors();
        $jumlah = NotifikasiPengguna::count();
        $this->actingAs($this->kepala)->post(route('tindak-lanjut-surat.tindakan', [$t, 'terima']), ['versi' => $versi])->assertSessionHasErrors('tindak_lanjut');
        $this->assertSame($jumlah, NotifikasiPengguna::count());
    }

    public function test_integritas_bukti_diperiksa_lagi_saat_penerimaan(): void
    {
        $t = $this->disposisi();
        $this->lapor($t, 'ajukan', ['bukti' => [$this->pdf()]])->assertRedirect()->assertSessionHasNoErrors();
        $laporan = $t->laporan()->firstOrFail();
        Storage::disk('local')->delete($laporan->bukti()->first()->lokasi_file);
        $this->aksi($t, 'terima', $this->kepala)->assertSessionHasErrors('tindak_lanjut');
        $this->assertSame('menunggu_pemeriksaan', $t->fresh()->status);
        $this->assertNull($laporan->fresh()->keputusan);
        $this->aksi($t, 'kembalikan', $this->kepala, ['catatan' => 'Bukti hilang, unggah ulang.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->lapor($t, 'ajukan', ['bukti' => [$this->pdf('pengganti.pdf')]])->assertRedirect()->assertSessionHasNoErrors();
        $this->aksi($t, 'terima', $this->kepala)->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_laporan_selesai_dipertahankan_ketika_surat_induk_dibatalkan(): void
    {
        $surat = $this->disahkan();
        $t = $surat->tindakLanjut()->where('pegawai_id', $this->guru->pegawai_id)->firstOrFail();
        $this->lapor($t, 'ajukan')->assertRedirect()->assertSessionHasNoErrors();
        $this->aksi($t, 'terima', $this->kepala)->assertRedirect()->assertSessionHasNoErrors();
        $selesai = $t->fresh()->selesai_pada->toDateTimeString();
        $this->actingAs($this->kepala)->post(route('persuratan.tindakan', [$surat, 'batal']), ['versi' => $surat->fresh()->versi, 'catatan' => 'Arsip kegiatan digantikan.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('selesai', $t->fresh()->status);
        $this->assertSame($selesai, $t->fresh()->selesai_pada->toDateTimeString());
        $this->actingAs($this->guru)->get(route('tindak-lanjut-surat.show', $t))->assertOk()->assertSee('Surat telah dibatalkan atau digantikan.')->assertDontSee('Ajukan laporan');
    }

    public function test_pengingat_mengabaikan_penerima_nonaktif_dan_tugas_dibatalkan(): void
    {
        $t = $this->disposisi();
        $this->travelTo(now()->setDate(2026, 10, 21));
        $service = app(IngatkanTindakLanjutSuratService::class);
        $this->guru->pegawai->update(['aktif' => false]);
        $this->assertSame(0, $service->kirimPengingat());
        $this->guru->pegawai->update(['aktif' => true]);
        $this->aksi($t, 'batal', $this->kepala, ['catatan' => 'Tidak diperlukan.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $service->kirimPengingat());
    }

    public function test_hanya_lima_bukti_dan_pegawai_lain_tidak_boleh_menulis_laporan(): void
    {
        $t = $this->disposisi();
        $this->lapor($t, 'draf', [], $this->lain)->assertNotFound();
        $this->lapor($t, 'draf', [], $this->tu)->assertForbidden();
        $this->lapor($t, 'draf', ['bukti' => [$this->pdf('a.pdf'), $this->pdf('b.pdf'), $this->pdf('c.pdf'), $this->pdf('d.pdf'), $this->pdf('e.pdf')]])->assertRedirect()->assertSessionHasNoErrors();
        $ids = $t->laporan()->first()->bukti()->pluck('id')->all();
        $this->lapor($t, 'ajukan', ['bukti_lama' => $ids, 'bukti' => [$this->pdf('f.pdf')]])->assertSessionHasErrors('tindak_lanjut');
        $this->assertCount(5, Storage::disk('local')->allFiles('persuratan-tindak-lanjut'));
        $this->assertSame(1, $t->laporan()->count());
    }

    private function akun(string $role, string $nama): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'aktif' => true, 'jenis_kelamin' => 'L']);
        $akun = Pengguna::create(['pegawai_id' => $pegawai->id, 'nama' => $nama, 'username' => 'tindak.'.uniqid(),
            'kata_sandi' => 'SuratTes12345', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $akun;
    }

    private function pdf(string $nama = 'bukti.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 20, 'application/pdf');
    }

    private function surat(string $jenis = 'masuk', array $data = []): SuratTu
    {
        $this->actingAs($this->tu)->post(route('persuratan.store'), array_replace(['jenis' => $jenis, 'nomor_surat' => 'D/1', 'nomor_agenda' => 'AG/1',
            'tanggal_surat' => '2026-10-12', 'tanggal_diterima' => '2026-10-12', 'perihal' => 'Dokumentasi kegiatan sekolah', 'pihak' => 'Dinas Pendidikan', 'sifat' => 'biasa',
            'pengesah_pengguna_id' => $this->kepala->id, 'mulai_tugas' => '2026-10-19', 'selesai_tugas' => '2026-10-20', 'tempat_tugas' => 'Aula',
            'uraian_tugas' => 'Laksanakan kegiatan dan laporkan hasilnya.', 'pegawai_ids' => [$this->guru->pegawai_id, $this->lain->pegawai_id],
            'berkas_utama' => $this->pdf('surat.pdf')], $data))->assertRedirect()->assertSessionHasNoErrors();

        return SuratTu::latest('id')->firstOrFail();
    }

    private function isiDisposisi(SuratTu $surat): array
    {
        return ['versi' => $surat->fresh()->versi, 'pegawai_ids' => [$this->guru->pegawai_id], 'instruksi' => 'Dokumentasikan kegiatan dan laporkan hasilnya.', 'batas_tanggal' => '2026-10-20'];
    }

    private function disposisi(?SuratTu $surat = null): TindakLanjutSuratTu
    {
        $surat ??= $this->surat();
        $this->actingAs($this->kepala)->post(route('persuratan.disposisi', $surat), $this->isiDisposisi($surat))->assertRedirect()->assertSessionHasNoErrors();

        return $surat->tindakLanjut()->firstOrFail();
    }

    private function aksi(TindakLanjutSuratTu $t, string $aksi, Pengguna $akun, array $data = []): TestResponse
    {
        return $this->actingAs($akun)->post(route('tindak-lanjut-surat.tindakan', [$t, $aksi]), ['versi' => $t->fresh()->versi] + $data);
    }

    private function lapor(TindakLanjutSuratTu $t, string $aksi, array $data = [], ?Pengguna $akun = null): TestResponse
    {
        return $this->actingAs($akun ?? $this->guru)->post(route('tindak-lanjut-surat.laporan', $t), array_replace(['versi' => $t->fresh()->versi, 'aksi' => $aksi, 'hasil' => 'Hasil tindak lanjut.'], $data));
    }

    private function sahkan(SuratTu $surat): void
    {
        $this->actingAs($this->tu)->post(route('persuratan.tindakan', [$surat, 'ajukan']), ['versi' => $surat->fresh()->versi])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->kepala)->post(route('persuratan.tindakan', [$surat, 'sahkan']), ['versi' => $surat->fresh()->versi, 'konfirmasi_berkas' => 1])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function disahkan(array $data = []): SuratTu
    {
        $surat = $this->surat('tugas', $data);
        $this->sahkan($surat);

        return $surat->fresh();
    }

    private function capture(string $nama, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_PERSURATAN_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/persuratan');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$nama.'.html', $response->getContent());
    }
}
