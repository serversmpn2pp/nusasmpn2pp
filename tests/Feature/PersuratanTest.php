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
use App\Services\Notifikasi\IsiPushNotifikasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PersuratanTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $tu;

    private Pengguna $kepala;

    private Pengguna $guru;

    private Pengguna $guruLain;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.firebase.push_enabled' => false]);
        Storage::fake('local');
        $this->tu = $this->akun('tata_usaha', 'Petugas TU');
        $this->kepala = $this->akun('pimpinan', 'Kepala Sekolah');
        $this->guru = $this->akun('guru_mapel', 'Guru Satu');
        $this->guruLain = $this->akun('guru_mapel', 'Guru Dua');
    }

    public function test_akses_tu_pimpinan_guru_siswa_dan_orang_tua(): void
    {
        $this->get(route('persuratan.index'))->assertRedirect(route('login'));
        $this->actingAs($this->tu)->get(route('persuratan.index'))->assertOk()->assertSee('Tambah surat');
        $this->actingAs($this->tu)->get(route('persuratan.pemeriksaan'))->assertForbidden();
        $this->actingAs($this->kepala)->get(route('persuratan.index'))->assertOk()->assertDontSee('Tambah surat');
        $this->get(route('persuratan.pemeriksaan'))->assertOk()->assertSee('Pengesahan surat');
        $this->get(route('persuratan.create'))->assertForbidden();
        $this->actingAs($this->guru)->get(route('persuratan.index'))->assertForbidden();
        $this->get(route('surat-saya.index'))->assertOk()->assertSee('Surat &amp; Penugasan Saya', false);
        $siswa = Siswa::create(['nisn' => '991212001', 'nama_lengkap' => 'Siswa Tes', 'jenis_kelamin' => 'L', 'aktif' => true]);
        $akun = $this->akun('tata_usaha');
        $akun->update(['siswa_id' => $siswa->id]);
        $this->actingAs($akun)->get(route('persuratan.index'))->assertForbidden();
        $this->get(route('surat-saya.index'))->assertForbidden();
        $ortu = $this->akun('tata_usaha');
        $ortu->update(['pegawai_id' => null]);
        OrangTuaWali::create(['pengguna_id' => $ortu->id, 'nama_lengkap' => 'Orang Tua Tes', 'aktif' => true]);
        $this->actingAs($ortu)->get(route('persuratan.index'))->assertForbidden();
        $this->get(route('surat-saya.index'))->assertForbidden();
    }

    public function test_arsip_surat_masuk_nomor_agenda_dan_unduhan_privat(): void
    {
        $surat = $this->buat('masuk');
        $this->assertSame('tercatat', $surat->status);
        $this->assertSame('AG-001', $surat->nomor_agenda);
        $this->assertNull($surat->pengesah_pengguna_id);
        $this->assertSame(0, $surat->penugasan()->count());
        $this->assertSame(0, NotifikasiPengguna::count());
        $file = $surat->berkas()->firstOrFail();
        Storage::disk('local')->assertExists($file->lokasi_file);
        $this->get(route('persuratan.berkas', [$surat, $file]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->guru)->get(route('persuratan.berkas', [$surat, $file]))->assertForbidden();
        $this->get(route('surat-saya.show', $surat))->assertNotFound();
        $this->aksi($surat, 'ajukan', $this->tu)->assertSessionHasErrors('surat');
        $this->assertSame('tercatat', $surat->fresh()->status);
    }

    public function test_nomor_keluar_dan_tugas_satu_register_nomor_agenda_tidak_ganda(): void
    {
        $this->buat('keluar');
        $data = $this->payload('tugas');
        $this->actingAs($this->tu)->post(route('persuratan.store'), $data)->assertSessionHasErrors('surat');
        $this->assertDatabaseCount('surat_tu', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('persuratan'));
        $masuk = $this->buat('masuk', ['nomor_surat' => 'DINAS/1']);
        $this->post(route('persuratan.store'), $this->payload('masuk', ['nomor_surat' => 'DINAS/2']))->assertSessionHasErrors('surat');
        $this->post(route('persuratan.store'), $this->payload('masuk', ['nomor_agenda' => 'AG-002', 'nomor_surat' => 'DINAS/1', 'pihak' => 'Instansi lain']))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('persuratan.store'), $this->payload('keluar', ['tanggal_surat' => '2027-10-12']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('AG-001', $masuk->fresh()->nomor_agenda);
    }

    public function test_validasi_berkas_tanggal_pengesah_dan_penerima(): void
    {
        $data = $this->payload('tugas');
        $data['berkas_utama'] = UploadedFile::fake()->create('bahaya.html', 20, 'text/html');
        $data['selesai_tugas'] = '2026-10-01';
        $this->actingAs($this->tu)->post(route('persuratan.store'), $data)->assertSessionHasErrors(['berkas_utama', 'selesai_tugas']);
        $this->post(route('persuratan.store'), $this->payload('tugas', ['pegawai_ids' => [$this->guru->pegawai_id, $this->guru->pegawai_id]]))->assertSessionHasErrors('pegawai_ids.0');
        $this->post(route('persuratan.store'), $this->payload('tugas', ['pengesah_pengguna_id' => $this->guru->id]))->assertSessionHasErrors('surat');
        $pegawai = Pegawai::create(['nama_lengkap' => 'Tanpa Akun', 'aktif' => true]);
        $this->post(route('persuratan.store'), $this->payload('tugas', ['pegawai_ids' => [$pegawai->id]]))->assertSessionHasErrors('surat');
        $this->assertDatabaseCount('surat_tu', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('persuratan'));
    }

    public function test_pengajuan_baru_memberitahu_pengesah_bukan_guru(): void
    {
        $surat = $this->buat();
        $this->assertSame(0, $this->guru->notifikasiPengguna()->count());
        $this->aksi($surat, 'ajukan', $this->tu)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $surat->fresh()->status);
        $this->assertSame(1, $this->kepala->notifikasiPengguna()->count());
        $this->assertSame(0, $this->guru->notifikasiPengguna()->count());
        $this->actingAs($this->guru)->get(route('surat-saya.show', $surat))->assertNotFound();
        $this->get(route('surat-saya.index'))->assertDontSee($surat->nomor_surat);
        $this->actingAs($this->kepala)->get(route('persuratan.pemeriksaan'))->assertSee($surat->nomor_surat);
    }

    public function test_hanya_pengesah_terpilih_boleh_sahkan_dan_notifikasi_tidak_ganda(): void
    {
        $surat = $this->buat();
        $this->aksi($surat, 'ajukan', $this->tu)->assertSessionHasNoErrors();
        $versi = $surat->fresh()->versi;
        $this->aksi($surat, 'sahkan', $this->tu, ['konfirmasi_berkas' => 1])->assertForbidden();
        $lain = $this->akun('pimpinan', 'Pejabat lain');
        $this->aksi($surat, 'sahkan', $lain, ['konfirmasi_berkas' => 1])->assertForbidden();
        $this->aksi($surat, 'sahkan', $this->kepala)->assertSessionHasErrors('konfirmasi_berkas');
        $this->aksi($surat, 'sahkan', $this->kepala, ['konfirmasi_berkas' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('disahkan', $surat->fresh()->status);
        $this->assertSame($this->kepala->id, $surat->fresh()->disahkan_oleh_pengguna_id);
        $this->assertSame(1, $this->guru->notifikasiPengguna()->count());
        $this->assertSame(1, $this->guruLain->notifikasiPengguna()->count());
        $this->post(route('persuratan.tindakan', [$surat, 'sahkan']), ['versi' => $versi, 'konfirmasi_berkas' => 1])->assertSessionHasErrors('surat');
        $this->aksi($surat, 'sahkan', $this->kepala, ['konfirmasi_berkas' => 1])->assertSessionHasErrors('surat');
        $this->assertSame(1, $this->guru->notifikasiPengguna()->count());
        $this->assertSame('/surat-saya/'.$surat->id, $this->guru->notifikasiPengguna()->first()->tautan);
    }

    public function test_surat_terkunci_sejak_pengajuan_dan_versi_lama_ditolak_atomik(): void
    {
        $surat = $this->buat();
        $lama = $surat->versi;
        $this->aksi($surat, 'ajukan', $this->tu)->assertSessionHasNoErrors();
        $data = $this->payload('tugas', ['versi' => $surat->fresh()->versi, 'catatan_revisi' => 'Koreksi', 'perihal' => 'Tidak boleh berubah']);
        $this->actingAs($this->tu)->put(route('persuratan.update', $surat), $data)->assertSessionHasErrors('surat');
        $this->assertSame('Penugasan Pelatihan Guru', $surat->fresh()->perihal);
        $this->assertCount(1, Storage::disk('local')->allFiles('persuratan'));
        $this->post(route('persuratan.tindakan', [$surat, 'tarik']), ['versi' => $lama, 'catatan' => 'Perbaikan'])->assertSessionHasErrors('surat');
        $this->aksi($surat, 'kembalikan', $this->kepala, ['catatan' => 'Perbaiki tanggal'])->assertSessionHasNoErrors();
        $data['versi'] = $surat->fresh()->versi;
        $this->actingAs($this->tu)->put(route('persuratan.update', $surat), $data)->assertSessionHasNoErrors();
        $this->assertSame('Tidak boleh berubah', $surat->fresh()->perihal);
        $this->assertCount(2, Storage::disk('local')->allFiles('persuratan'));
        $this->assertSame(1, $surat->berkas()->where('aktif', true)->count());
        $this->assertSame(0, $this->guru->notifikasiPengguna()->count());
    }

    public function test_pembatalan_sebelum_dan_sesudah_pengesahan(): void
    {
        $surat = $this->disahkan();
        $this->aksi($surat, 'batal', $this->tu, ['catatan' => 'Tidak jadi'])->assertForbidden();
        $this->aksi($surat, 'batal', $this->kepala)->assertSessionHasErrors('catatan');
        $this->aksi($surat, 'batal', $this->kepala, ['catatan' => 'Kegiatan ditunda'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('dibatalkan', $surat->fresh()->status);
        $this->assertSame(2, $this->guru->notifikasiPengguna()->count());
        $this->actingAs($this->guru)->get(route('surat-saya.show', $surat))->assertOk()->assertSee('Kegiatan ditunda');
        $this->post(route('surat-saya.baca', $surat))->assertSessionHasErrors('surat');
        $this->assertDatabaseCount('surat_tu', 1);
        $draf = $this->buat('keluar', ['nomor_surat' => '421/002/2026']);
        $this->aksi($draf, 'batal', $this->tu, ['catatan' => 'Salah input'])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->guru->notifikasiPengguna()->count());
        $this->actingAs($this->tu)->post(route('persuratan.store'), $this->payload('keluar', ['nomor_surat' => $draf->nomor_surat]))->assertSessionHasErrors('surat');
    }

    public function test_pencatatan_pengiriman_tidak_mengirim_ulang_penugasan(): void
    {
        $surat = $this->disahkan();
        $this->aksi($surat, 'kirim', $this->tu, ['catatan' => 'Diteruskan melalui email resmi sekolah'])->assertSessionHasNoErrors();
        $this->assertSame('dikirim', $surat->fresh()->status);
        $this->assertNotNull($surat->fresh()->dikirim_pada);
        $this->assertSame(1, $this->guru->notifikasiPengguna()->count());
        $this->aksi($surat, 'kirim', $this->tu, ['catatan' => 'Ulang'])->assertSessionHasErrors('surat');
        $this->assertSame(1, $this->guru->notifikasiPengguna()->count());
    }

    public function test_konfirmasi_baca_pribadi_idempoten_dan_berkas_surat_lain_ditolak(): void
    {
        $surat = $this->disahkan();
        $asing = $this->akun('guru_mapel', 'Guru tidak ditugaskan');
        $file = $surat->berkas()->firstOrFail();
        $this->actingAs($asing)->get(route('surat-saya.show', $surat))->assertNotFound();
        $this->get(route('surat-saya.berkas', [$surat, $file]))->assertNotFound();
        $this->post(route('surat-saya.baca', $surat))->assertNotFound();
        $this->actingAs($this->guru)->get(route('surat-saya.show', $surat))->assertOk()->assertDontSee('Guru Dua')->assertDontSee('Riwayat surat');
        $this->get(route('surat-saya.berkas', [$surat, $file]))->assertOk();
        $this->post(route('surat-saya.baca', $surat))->assertSessionHasNoErrors();
        $dibaca = $surat->penugasan()->where('pegawai_id', $this->guru->pegawai_id)->first()->dibaca_pada->toDateTimeString();
        $this->travel(10)->minutes();
        $this->post(route('surat-saya.baca', $surat))->assertSessionHasNoErrors();
        $this->assertSame($dibaca, $surat->penugasan()->where('pegawai_id', $this->guru->pegawai_id)->first()->dibaca_pada->toDateTimeString());
        $this->assertSame(1, $surat->riwayat()->where('aksi', 'dibaca')->count());
        $this->assertNull($surat->penugasan()->where('pegawai_id', $this->guruLain->pegawai_id)->first()->dibaca_pada);
        $lain = $this->buat('masuk', ['nomor_surat' => 'X/55']);
        $this->actingAs($this->guru)->get(route('surat-saya.berkas', [$surat, $lain->berkas()->first()]))->assertNotFound();
    }

    public function test_arsip_rahasia_memerlukan_izin_khusus_dan_penerima_tetap_bisa_membaca(): void
    {
        $surat = $this->disahkan(['sifat' => 'rahasia', 'perihal' => 'Tugas Rahasia Tes']);
        $peran = Peran::create(['kode' => 'pembaca_surat', 'nama' => 'Pembaca surat', 'aktif' => true]);
        $peran->izin()->attach(Izin::where('kode', 'surat.lihat')->value('id'));
        $akun = $this->akun();
        $akun->daftarPeran()->attach($peran);
        $this->actingAs($akun)->get(route('persuratan.index'))->assertOk()->assertDontSee('Tugas Rahasia Tes');
        $this->get(route('persuratan.show', $surat))->assertNotFound();
        $this->get(route('persuratan.berkas', [$surat, $surat->berkas()->first()]))->assertNotFound();
        $this->actingAs($this->guru)->get(route('surat-saya.show', $surat))->assertOk()->assertSee('Tugas Rahasia Tes');
        $push = app(IsiPushNotifikasiService::class)->untuk($this->guru->notifikasiPengguna()->firstOrFail());
        $this->assertStringNotContainsString('Tugas Rahasia Tes', $push['body']);
        $this->assertStringNotContainsString($surat->nomor_surat, $push['body']);
    }

    public function test_berkas_hilang_atau_penerima_nonaktif_memblokir_pengesahan(): void
    {
        $surat = $this->buat();
        $this->aksi($surat, 'ajukan', $this->tu)->assertSessionHasNoErrors();
        $this->guru->update(['aktif' => false]);
        $this->aksi($surat, 'sahkan', $this->kepala, ['konfirmasi_berkas' => 1])->assertSessionHasErrors('surat');
        $this->guru->update(['aktif' => true]);
        Storage::disk('local')->delete($surat->berkas()->first()->lokasi_file);
        $this->aksi($surat, 'sahkan', $this->kepala, ['konfirmasi_berkas' => 1])->assertSessionHasErrors('surat');
        $this->assertSame('diajukan', $surat->fresh()->status);
        $this->assertSame(0, $this->guru->notifikasiPengguna()->count());
    }

    public function test_revisi_baru_menggantikan_surat_asal_setelah_disahkan(): void
    {
        $asal = $this->disahkan();
        $baru = $this->buat('tugas', ['nomor_surat' => '421/002/2026', 'revisi_dari_id' => $asal->id,
            'versi_asal' => $asal->fresh()->versi, 'pegawai_ids' => [$this->guru->pegawai_id]]);
        $this->assertSame('disahkan', $asal->fresh()->status);
        $this->aksi($baru, 'ajukan', $this->tu)->assertSessionHasNoErrors();
        $this->aksi($baru, 'sahkan', $this->kepala, ['konfirmasi_berkas' => 1])->assertSessionHasNoErrors();
        $this->assertSame('dibatalkan', $asal->fresh()->status);
        $this->assertSame('disahkan', $baru->fresh()->status);
        $this->assertSame(3, $this->guru->notifikasiPengguna()->count());
        $this->assertSame(2, $this->guruLain->notifikasiPengguna()->count());
        $this->assertSame(1, $asal->riwayat()->where('aksi', 'digantikan')->count());
        $this->assertSame($asal->id, $baru->revisi_dari_id);
    }

    public function test_pengaju_tidak_bisa_menunjuk_dirinya_sendiri_sebagai_pengesah(): void
    {
        $peran = Peran::where('kode', 'tata_usaha')->firstOrFail();
        $this->kepala->daftarPeran()->attach($peran);
        $this->actingAs($this->kepala)->post(route('persuratan.store'), $this->payload('keluar'))->assertSessionHasErrors('surat');
        $this->assertDatabaseCount('surat_tu', 0);
    }

    public function test_lampiran_lama_privat_dan_hapus_lampiran_asing_ditolak(): void
    {
        $surat = $this->buat('tugas', ['lampiran' => [UploadedFile::fake()->create('jadwal.pdf', 10, 'application/pdf')]]);
        $lama = $surat->berkas()->where('jenis', 'lampiran')->firstOrFail();
        $data = $this->payload('tugas', ['versi' => $surat->versi, 'catatan_revisi' => 'Ganti jadwal', 'hapus_lampiran' => [$lama->id]]);
        $this->actingAs($this->tu)->put(route('persuratan.update', $surat), $data)->assertSessionHasNoErrors();
        $this->assertFalse($lama->fresh()->aktif);
        Storage::disk('local')->assertExists($lama->lokasi_file);
        $this->aksi($surat, 'ajukan', $this->tu)->assertSessionHasNoErrors();
        $this->aksi($surat, 'sahkan', $this->kepala, ['konfirmasi_berkas' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->guru)->get(route('surat-saya.berkas', [$surat, $lama]))->assertNotFound();
        $this->actingAs($this->tu)->get(route('persuratan.berkas', [$surat, $lama]))->assertOk();
        $lain = $this->buat('masuk', ['nomor_surat' => 'X/55']);
        $data['versi'] = $lain->versi;
        $data['jenis'] = 'masuk';
        $data['nomor_surat'] = $lain->nomor_surat;
        $data['nomor_agenda'] = 'AG-001';
        $data['tanggal_diterima'] = '2026-10-12';
        $this->put(route('persuratan.update', $lain), $data)->assertSessionHasErrors('surat');
    }

    public function test_semua_halaman_render_filter_dan_xss_diescape(): void
    {
        $surat = $this->buat('tugas', ['perihal' => '<script>alert(1)</script>']);
        $this->actingAs($this->tu)->get(route('persuratan.show', $surat))->assertOk()->assertSee(e($surat->perihal), false)->assertDontSee($surat->perihal, false);
        $this->get(route('persuratan.index', ['jenis' => 'masuk']))->assertDontSee($surat->nomor_surat);
        $this->get(route('persuratan.index', ['kata_kunci' => 'Guru Satu']))->assertSee($surat->nomor_surat);
        $this->get(route('persuratan.index', ['tahun' => 2027]))->assertDontSee($surat->nomor_surat);
        $surat->update(['perihal' => 'Penugasan Pelatihan Guru']);
        $this->capture('form-masuk', $this->get(route('persuratan.create'))->assertOk());
        $this->capture('form-tugas', $this->get(route('persuratan.create', ['jenis' => 'tugas']))->assertOk());
        $this->capture('edit', $this->get(route('persuratan.edit', $surat))->assertOk());
        $this->aksi($surat, 'ajukan', $this->tu)->assertSessionHasNoErrors();
        $this->actingAs($this->kepala);
        $this->capture('pengesahan', $this->get(route('persuratan.show', $surat))->assertOk()->assertSee('Sahkan surat'));
        $this->aksi($surat, 'sahkan', $this->kepala, ['konfirmasi_berkas' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->tu);
        $this->capture('index', $this->get(route('persuratan.index'))->assertOk());
        $this->capture('detail', $this->get(route('persuratan.show', $surat))->assertOk());
        $this->capture('revisi', $this->get(route('persuratan.create', ['revisi_dari_id' => $surat->id]))->assertOk());
        $this->actingAs($this->guru);
        $this->capture('surat-saya', $this->get(route('surat-saya.show', $surat))->assertOk());
        $this->capture('penugasan', $this->get(route('surat-saya.index'))->assertOk());
    }

    public function test_pejabat_nonaktif_dan_surat_rahasia_tidak_dapat_diajukan_ke_pengesah_tanpa_akses(): void
    {
        $surat = $this->buat('keluar');
        $this->kepala->update(['aktif' => false]);
        $this->aksi($surat, 'ajukan', $this->tu)->assertSessionHasErrors('surat');
        $this->kepala->update(['aktif' => true]);
        $role = Peran::create(['nama' => 'Pengesah terbatas', 'kode' => 'pengesah_terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::where('kode', 'surat.sahkan')->value('id'));
        $akun = $this->akun();
        $akun->daftarPeran()->attach($role);
        $this->actingAs($this->tu)->post(route('persuratan.store'), $this->payload('keluar', ['nomor_surat' => '421/002/2026', 'sifat' => 'rahasia', 'pengesah_pengguna_id' => $akun->id]))->assertSessionHasErrors('surat');
    }

    public function test_integritas_berkas_dan_path_di_luar_arsip_ditolak(): void
    {
        $surat = $this->buat('keluar');
        $file = $surat->berkas()->firstOrFail();
        Storage::disk('local')->put($file->lokasi_file, 'Berkas diganti tanpa revisi');
        $this->get(route('persuratan.berkas', [$surat, $file]))->assertNotFound();
        $this->aksi($surat, 'ajukan', $this->tu)->assertSessionHasErrors('surat');
        Storage::disk('local')->put('bukan-arsip/rahasia.txt', 'File di luar arsip');
        $file->update(['lokasi_file' => 'bukan-arsip/rahasia.txt', 'sha256' => hash('sha256', 'File di luar arsip')]);
        $this->get(route('persuratan.berkas', [$surat, $file]))->assertNotFound();
        $this->aksi($surat, 'ajukan', $this->tu)->assertSessionHasErrors('surat');
    }

    public function test_nomor_agenda_mengikuti_tahun_diterima_bukan_tahun_surat(): void
    {
        $this->buat('masuk', ['tanggal_surat' => '2025-12-30', 'tanggal_diterima' => '2026-01-05']);
        $this->post(route('persuratan.store'), $this->payload('masuk', ['nomor_surat' => 'NOMOR-BARU']))->assertSessionHasErrors('surat');
        $this->post(route('persuratan.store'), $this->payload('masuk', ['tanggal_surat' => '2027-01-01', 'tanggal_diterima' => '2027-01-02']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('surat_tu', 2);
    }

    public function test_perubahan_draf_dari_form_tanpa_unggahan_baru_mempertahankan_berkas(): void
    {
        $surat = $this->buat();
        $file = $surat->berkas()->firstOrFail();
        $data = $this->payload('tugas', ['versi' => $surat->versi, 'catatan_revisi' => 'Koreksi penerima', 'pegawai_ids' => [$this->guru->pegawai_id]]);
        unset($data['berkas_utama']);
        $this->put(route('persuratan.update', $surat), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, $surat->berkas()->count());
        $this->assertSame($file->lokasi_file, $surat->berkas()->first()->lokasi_file);
        $this->assertSame(1, $surat->penugasan()->count());
        $data['perihal'] = 'Stale tidak boleh tersimpan';
        $this->put(route('persuratan.update', $surat), $data)->assertSessionHasErrors('surat');
        $this->assertSame('Penugasan Pelatihan Guru', $surat->fresh()->perihal);
    }

    public function test_penugasan_hanya_untuk_pegawai_aktif_dan_pengesahan_memerlukan_izin_aktif(): void
    {
        $surat = $this->disahkan();
        $this->guru->pegawai->update(['aktif' => false]);
        $this->actingAs($this->guru)->get(route('surat-saya.index'))->assertForbidden();
        $this->get(route('surat-saya.show', $surat))->assertForbidden();
        $lain = $this->buat('keluar', ['nomor_surat' => '421/002/2026']);
        $this->aksi($lain, 'ajukan', $this->tu)->assertSessionHasNoErrors();
        Izin::where('kode', 'surat.sahkan')->update(['aktif' => false]);
        $this->aksi($lain, 'sahkan', $this->kepala, ['konfirmasi_berkas' => 1])->assertForbidden();
        $this->assertSame('diajukan', $lain->fresh()->status);
    }

    public function test_dua_revisi_tidak_bisa_menggantikan_surat_asal_yang_sama(): void
    {
        $asal = $this->disahkan();
        $baru = $this->buat('tugas', ['nomor_surat' => '421/002/2026', 'revisi_dari_id' => $asal->id, 'versi_asal' => $asal->versi]);
        $kedua = $this->buat('tugas', ['nomor_surat' => '421/003/2026', 'revisi_dari_id' => $asal->id, 'versi_asal' => $asal->versi]);
        foreach ([$baru, $kedua] as $item) {
            $this->aksi($item, 'ajukan', $this->tu)->assertSessionHasNoErrors();
        }
        $this->aksi($baru, 'sahkan', $this->kepala, ['konfirmasi_berkas' => 1])->assertSessionHasNoErrors();
        $jumlah = NotifikasiPengguna::count();
        $this->aksi($kedua, 'sahkan', $this->kepala, ['konfirmasi_berkas' => 1])->assertSessionHasErrors('surat');
        $this->assertSame('diajukan', $kedua->fresh()->status);
        $this->assertSame($jumlah, NotifikasiPengguna::count());
        $this->actingAs($this->tu)->get(route('persuratan.create', ['revisi_dari_id' => $asal->id]))->assertNotFound();
    }

    private function akun(string $peran = 'pegawai', string $nama = 'Pegawai Tes'): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'aktif' => true, 'jenis_kelamin' => 'L']);
        $akun = Pengguna::create(['pegawai_id' => $pegawai->id, 'nama' => $nama, 'username' => 'surat.'.uniqid(),
            'kata_sandi' => 'SuratTes12345', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->attach(Peran::where('kode', $peran)->firstOrFail());

        return $akun;
    }

    private function payload(string $jenis = 'tugas', array $tambahan = []): array
    {
        return array_replace(['jenis' => $jenis, 'nomor_surat' => '421/001/2026', 'nomor_agenda' => $jenis === 'masuk' ? 'AG-001' : null,
            'tanggal_surat' => '2026-10-12', 'tanggal_diterima' => $jenis === 'masuk' ? '2026-10-12' : null,
            'perihal' => 'Penugasan Pelatihan Guru', 'pihak' => 'Dinas Pendidikan', 'sifat' => 'biasa',
            'pengesah_pengguna_id' => $jenis !== 'masuk' ? $this->kepala->id : null,
            'mulai_tugas' => $jenis === 'tugas' ? '2026-10-20' : null, 'selesai_tugas' => $jenis === 'tugas' ? '2026-10-21' : null,
            'tempat_tugas' => $jenis === 'tugas' ? 'Aula Dinas Pendidikan' : null,
            'uraian_tugas' => $jenis === 'tugas' ? 'Mengikuti pelatihan dan menyampaikan hasil kepada sekolah.' : null,
            'pegawai_ids' => $jenis === 'tugas' ? [$this->guru->pegawai_id, $this->guruLain->pegawai_id] : [],
            'berkas_utama' => UploadedFile::fake()->create('surat.pdf', 20, 'application/pdf')], $tambahan);
    }

    private function buat(string $jenis = 'tugas', array $tambahan = []): SuratTu
    {
        $this->actingAs($this->tu)->post(route('persuratan.store'), $this->payload($jenis, $tambahan))->assertRedirect()->assertSessionHasNoErrors();

        return SuratTu::latest('id')->firstOrFail();
    }

    private function aksi(SuratTu $surat, string $aksi, Pengguna $akun, array $data = []): TestResponse
    {
        return $this->actingAs($akun)->post(route('persuratan.tindakan', [$surat, $aksi]),
            ['versi' => $surat->fresh()->versi] + $data);
    }

    private function disahkan(array $tambahan = []): SuratTu
    {
        $surat = $this->buat('tugas', $tambahan);
        $this->aksi($surat, 'ajukan', $this->tu)->assertSessionHasNoErrors();
        $this->aksi($surat, 'sahkan', $this->kepala, ['konfirmasi_berkas' => 1])->assertSessionHasNoErrors();

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
