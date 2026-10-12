<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\SuratTu;
use App\Models\TindakLanjutSuratTu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PersuratanApiTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $tu;

    private Pengguna $kepala;

    private Pengguna $guru;

    private Pengguna $lain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['services.firebase.push_enabled' => false]);
        Storage::fake('local');
        $this->tu = $this->akun('tata_usaha', 'Petugas TU');
        $this->kepala = $this->akun('pimpinan', 'Kepala Sekolah');
        $this->guru = $this->akun('guru_mapel', 'Guru Satu');
        $this->lain = $this->akun('guru_mapel', 'Guru Dua');
    }

    public function test_semua_endpoint_memerlukan_token_mobile_aktif_dan_sandi_baru(): void
    {
        $routes = collect(Route::getRoutes())->filter(fn ($r) => preg_match('/^api\.v1\.(persuratan|surat-saya|tindak-lanjut-surat)\./', $r->getName() ?? ''));
        $this->assertCount(20, $routes);
        foreach ($routes as $r) {
            foreach (['auth:sanctum', 'abilities:mobile', 'akun_api_aktif', 'kata_sandi_api_bukan_default', 'throttle:120,1,persuratan-api:'] as $guard) {
                $this->assertContains($guard, $r->gatherMiddleware(), $r->uri());
            }
            if (array_intersect($r->methods(), ['POST', 'PATCH'])) {
                $this->assertGreaterThan(1, count(array_filter($r->gatherMiddleware(), fn ($m) => str_starts_with($m, 'throttle:'))));
            }
        }
        $this->getJson($this->url())->assertUnauthorized();
        $this->token($this->tu, ['other']);
        $this->getJson($this->url())->assertForbidden();
        $this->tu->update(['wajib_ganti_kata_sandi' => true]);
        $this->token($this->tu);
        $this->getJson($this->url())->assertStatus(428);
        $this->tu->update(['wajib_ganti_kata_sandi' => false, 'aktif' => false]);
        $this->token($this->tu);
        $this->getJson($this->url())->assertUnauthorized();
    }

    public function test_menu_tu_pimpinan_guru_dan_admin_mengikuti_identitas_sebenarnya(): void
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        foreach ([[$this->tu, 4], [$this->kepala, 5], [$this->guru, 2], [$admin, 3]] as [$akun, $jumlah]) {
            $this->token($akun);
            $r = $this->getJson(route('api.v1.menu'))->assertOk();
            $grup = collect($r->json('data.kelompok'))->firstWhere('kode', 'persuratan-arsip');
            $this->assertCount($jumlah, $grup['items']);
            foreach ($grup['items'] as $menu) {
                $this->assertSame('segera_hadir', $menu['status']);
                $this->assertTrue($menu['api_tersedia']);
                $this->getJson(url($menu['api']))->assertOk();
            }
            if ($akun->id === $this->tu->id) {
                $this->assertEmpty(array_intersect(['arsip-surat', 'monitoring-tindak-lanjut-surat', 'surat-saya', 'tindak-lanjut-surat-saya'], $r->json('data.akses_cepat.kode_menu')));
            }
        }
    }

    public function test_siswa_ortu_pegawai_nonaktif_dan_role_tu_tanpa_identitas_pegawai_ditolak(): void
    {
        $siswa = Siswa::create(['nisn' => '991201003', 'nama_lengkap' => 'Siswa', 'jenis_kelamin' => 'L', 'aktif' => true]);
        $akunSiswa = $this->akun('tata_usaha');
        $akunSiswa->update(['siswa_id' => $siswa->id]);
        $ortu = $this->akun('tata_usaha');
        $ortu->update(['pegawai_id' => null]);
        OrangTuaWali::create(['pengguna_id' => $ortu->id, 'nama_lengkap' => 'Orang Tua', 'aktif' => true]);
        $tanpa = $this->akun('tata_usaha');
        $tanpa->update(['pegawai_id' => null]);
        $this->guru->pegawai->update(['aktif' => false]);
        foreach ([$akunSiswa, $ortu, $tanpa, $this->guru] as $akun) {
            $this->token($akun);
            foreach (['persuratan', 'persuratan/referensi', 'persuratan/tindak-lanjut', 'surat-saya', 'tindak-lanjut-surat'] as $path) {
                $this->getJson($this->url($path))->assertForbidden();
            }
            $menu = $this->getJson(route('api.v1.menu'))->assertOk();
            $this->assertNull(collect($menu->json('data.kelompok'))->firstWhere('kode', 'persuratan-arsip'));
        }
    }

    public function test_referensi_statistik_kosong_dan_id_rute_memiliki_kontrak_yang_stabil(): void
    {
        $this->token($this->tu);
        $this->getJson($this->url('persuratan/referensi'))->assertOk()
            ->assertJsonPath('data.batas_berkas_mb', 10)->assertJsonPath('data.maksimal_lampiran', 5)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)->assertJsonCount(4, 'data.pegawai')
            ->assertDontSee('kata_sandi')->assertDontSee('username');
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.statistik.draf', 0)->assertJsonPath('data.paginasi.total', 0);
        $this->getJson($this->url('persuratan/tindak-lanjut'))->assertOk()->assertJsonPath('data.statistik.belum_mulai', 0);
        $this->getJson($this->url('persuratan/abc'))->assertNotFound();
        $this->getJson($this->url('persuratan/999999999999999999999'))->assertNotFound();
        $this->token($this->kepala);
        $this->getJson($this->url('persuratan/referensi'))->assertOk()->assertJsonCount(0, 'data.pegawai')->assertJsonCount(0, 'data.pengesah');
        $this->token($this->guru);
        $this->getJson($this->url('persuratan/referensi'))->assertForbidden();
    }

    public function test_tu_dapat_mengarsipkan_surat_masuk_dan_menyaring_daftar_berpaginasi(): void
    {
        $masuk = $this->buat('masuk');
        $this->assertSame('tercatat', $masuk->status);
        $this->assertNull($masuk->pengesah_pengguna_id);
        $this->buat('keluar', ['nomor_surat' => '421/002/2026']);
        $this->buat('tugas', ['nomor_surat' => '421/003/2026', 'perihal' => 'Rapat Guru']);
        $this->getJson($this->url().'?jenis=masuk&kata_kunci=AG-001&tahun=2026&sifat=biasa&per_halaman=1')->assertOk()
            ->assertJsonPath('data.paginasi.total', 1)->assertJsonPath('data.items.0.id', $masuk->id)
            ->assertJsonPath('data.statistik.draf', 2)->assertJsonPath('data.statistik_mengikuti_filter', false)
            ->assertJsonPath('data.items.0.jumlah.tindak_lanjut', 0);
        $this->getJson($this->url().'?per_halaman=1&halaman=2')->assertOk()
            ->assertJsonPath('data.paginasi.total', 3)->assertJsonPath('data.paginasi.halaman', 2)
            ->assertJsonPath('data.paginasi.ada_halaman_berikutnya', true);
        foreach (['per_halaman=51', 'halaman=0', 'status=asal', 'tahun=1999'] as $query) {
            $this->getJson($this->url().'?'.$query)->assertUnprocessable();
        }
        $this->assertSame(0, NotifikasiPengguna::count());
    }

    public function test_validasi_unggahan_nomor_ganda_dan_penerima_tidak_meninggalkan_berkas(): void
    {
        $this->token($this->tu);
        $data = $this->payload('tugas', ['berkas_utama' => UploadedFile::fake()->create('surat.docx', 20), 'selesai_tugas' => '2026-10-10']);
        $this->post($this->url(), $data)->assertUnprocessable()->assertJsonValidationErrors(['berkas_utama', 'selesai_tugas']);
        $this->post($this->url(), $this->payload('tugas', ['pegawai_ids' => [$this->guru->pegawai_id, $this->guru->pegawai_id]]))->assertUnprocessable();
        $this->post($this->url(), $this->payload('tugas', ['pengesah_pengguna_id' => $this->guru->id]))->assertUnprocessable();
        $this->assertDatabaseCount('surat_tu', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('persuratan'));
        $this->buat();
        $this->post($this->url(), $this->payload('keluar'))->assertUnprocessable();
        $this->buat('masuk');
        $this->post($this->url(), $this->payload('masuk', ['nomor_surat' => 'D-002']))->assertUnprocessable();
        $this->assertDatabaseCount('surat_tu', 2);
        $this->assertCount(2, Storage::disk('local')->allFiles('persuratan'));
    }

    public function test_pembaruan_multipart_memakai_method_spoof_dan_mengunci_versi_lama(): void
    {
        $surat = $this->buat();
        $data = $this->payload('tugas', ['_method' => 'PATCH', 'versi' => $surat->versi, 'catatan_revisi' => 'Koreksi perihal', 'perihal' => 'Pelatihan baru']);
        $this->post($this->url('persuratan/'.$surat->id), $data)->assertOk()->assertJsonPath('data.perihal', 'Pelatihan baru');
        $this->assertSame(2, $surat->fresh()->versi);
        $this->assertSame(1, $surat->berkas()->where('aktif', true)->count());
        $this->assertSame(2, $surat->berkas()->count());
        $this->post($this->url('persuratan/'.$surat->id), $data)->assertUnprocessable();
        $this->assertCount(2, Storage::disk('local')->allFiles('persuratan'));
        $this->aksiSurat($surat, 'ajukan', $this->tu)->assertOk();
        $data['versi'] = $surat->fresh()->versi;
        $this->post($this->url('persuratan/'.$surat->id), $data)->assertUnprocessable();
        $this->assertSame('Pelatihan baru', $surat->fresh()->perihal);
    }

    public function test_pengesahan_hanya_pemeriksa_terpilih_konfirmasi_wajib_dan_notifikasi_tidak_ganda(): void
    {
        $surat = $this->buat();
        $this->aksiSurat($surat, 'ajukan', $this->tu)->assertOk()->assertJsonPath('data.status', 'diajukan');
        $this->assertSame(1, $this->kepala->notifikasiPengguna()->count());
        $this->assertSame(0, $this->guru->notifikasiPengguna()->count());
        $versi = $surat->fresh()->versi;
        $this->aksiSurat($surat, 'sahkan', $this->tu, ['konfirmasi_berkas' => true])->assertForbidden();
        $pejabat = $this->akun('pimpinan');
        $this->aksiSurat($surat, 'sahkan', $pejabat, ['konfirmasi_berkas' => true])->assertForbidden();
        $this->getJson($this->url('persuratan/pengesahan'))->assertOk()->assertJsonPath('data.paginasi.total', 0);
        $this->token($this->kepala);
        $this->getJson($this->url('persuratan/pengesahan'))->assertOk()->assertJsonPath('data.paginasi.total', 1);
        $this->aksiSurat($surat, 'sahkan', $this->kepala)->assertUnprocessable()->assertJsonValidationErrors('konfirmasi_berkas');
        $this->aksiSurat($surat, 'sahkan', $this->kepala, ['konfirmasi_berkas' => true])->assertOk()->assertJsonPath('data.status', 'disahkan');
        $this->postJson($this->url('persuratan/'.$surat->id.'/tindakan/sahkan'), ['versi' => $versi, 'konfirmasi_berkas' => true])->assertUnprocessable();
        $this->assertSame(1, $this->guru->notifikasiPengguna()->count());
        $this->assertSame(1, $this->lain->notifikasiPengguna()->count());
        $this->assertSame(2, $surat->tindakLanjut()->count());
        $this->aksiSurat($surat, 'kirim', $this->tu, ['catatan' => 'Dikirim kepada dinas'])->assertOk()->assertJsonPath('data.status', 'dikirim');
        $this->assertSame(1, $this->guru->notifikasiPengguna()->count());
    }

    public function test_surat_pribadi_hanya_milik_guru_dan_tidak_membocorkan_penerima_lain(): void
    {
        $surat = $this->buat();
        $this->token($this->guru);
        $this->getJson($this->url('surat-saya'))->assertOk()->assertJsonPath('data.paginasi.total', 0);
        $this->getJson($this->url('surat-saya/'.$surat->id))->assertNotFound();
        $this->aksiSurat($surat, 'ajukan', $this->tu)->assertOk();
        $this->aksiSurat($surat, 'sahkan', $this->kepala, ['konfirmasi_berkas' => true])->assertOk();
        $this->token($this->guru);
        $r = $this->getJson($this->url('surat-saya/'.$surat->id))->assertOk()->assertJsonCount(1, 'data.penugasan')
            ->assertJsonCount(1, 'data.tindak_lanjut')->assertJsonMissingPath('data.riwayat')->assertDontSee('Guru Dua');
        $this->assertPribadi($r);
        $this->getJson($this->url('surat-saya').'?belum_dibaca=1')->assertOk()->assertJsonPath('data.paginasi.total', 1);
        $this->getJson($this->url('surat-saya').'?jenis=masuk')->assertUnprocessable();
        $this->token($this->akun('guru_mapel', 'Tidak ditugaskan'));
        $this->getJson($this->url('surat-saya/'.$surat->id))->assertNotFound();
        $this->getJson($this->url('surat-saya'))->assertOk()->assertJsonPath('data.paginasi.total', 0);
    }

    public function test_surat_rahasia_dan_revisi_terkait_mengikuti_scope_arsip(): void
    {
        $asal = $this->disahkan(['sifat' => 'rahasia']);
        $revisi = $this->buat('tugas', ['nomor_surat' => '421/002/2026', 'revisi_dari_id' => $asal->id, 'versi_asal' => $asal->versi]);
        $this->tu->daftarPeran()->first()->izin()->detach(Izin::where('kode', 'surat.rahasia')->firstOrFail());
        $this->token($this->tu);
        $this->getJson($this->url('persuratan/'.$asal->id))->assertNotFound();
        $this->getJson($this->url('persuratan/'.$revisi->id))->assertOk()->assertJsonCount(0, 'data.surat_terkait');
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.paginasi.total', 1)->assertJsonPath('data.statistik.disahkan', 0);
        $this->getJson($this->url('persuratan/referensi'))->assertOk()->assertJsonMissingPath('data.sifat.rahasia');
        $this->token($this->guru);
        $this->getJson($this->url('surat-saya/'.$asal->id))->assertOk()->assertDontSee('Guru Dua');
        $this->getJson($this->url('persuratan/'.$asal->id))->assertForbidden();
    }

    public function test_unduhan_privat_memerlukan_token_akses_parent_dan_integritas_berkas(): void
    {
        $surat = $this->disahkan();
        $file = $surat->berkas()->firstOrFail();
        $this->token($this->guru);
        $url = $this->url('surat-saya/'.$surat->id.'/berkas/'.$file->id);
        $r = $this->getJson($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $autre = $this->buat('masuk', ['nomor_surat' => 'D-002']);
        $this->token($this->guru);
        $this->getJson($this->url('surat-saya/'.$surat->id.'/berkas/'.$autre->berkas()->first()->id))->assertNotFound();
        $this->token($this->akun('guru_mapel'));
        $this->getJson($url)->assertNotFound();
        $this->token($this->guru);
        Storage::disk('local')->put($file->lokasi_file, 'berkas diubah');
        $this->getJson($url)->assertNotFound();
        Auth::forgetGuards();
        $this->withHeader('Authorization', '')->getJson($url)->assertUnauthorized();
    }

    public function test_revisi_disahkan_membatalkan_surat_dan_tugas_lama_secara_atomik(): void
    {
        $asal = $this->disahkan();
        $revisi = $this->buat('tugas', ['nomor_surat' => '421/002/2026', 'revisi_dari_id' => $asal->id, 'versi_asal' => $asal->versi]);
        $this->assertSame('disahkan', $asal->fresh()->status);
        $this->aksiSurat($revisi, 'ajukan', $this->tu)->assertOk();
        $this->aksiSurat($revisi, 'sahkan', $this->kepala, ['konfirmasi_berkas' => true])->assertOk();
        $this->assertSame('dibatalkan', $asal->fresh()->status);
        $this->assertSame(2, $asal->tindakLanjut()->where('status', 'dibatalkan')->count());
        $this->assertSame(3, $this->guru->notifikasiPengguna()->count());
        $this->token($this->guru);
        $this->getJson($this->url('surat-saya/'.$asal->id))->assertOk()->assertJsonPath('data.hak_akses.dapat_konfirmasi_baca', false);
        $this->postJson($this->url('surat-saya/'.$asal->id.'/baca'))->assertUnprocessable();
        $this->getJson($this->url('surat-saya/'.$revisi->id))->assertOk()->assertJsonPath('data.status', 'disahkan');
    }

    public function test_baca_surat_idempoten_tidak_menyelesaikan_tugas_atau_notifikasi(): void
    {
        $surat = $this->disahkan();
        $t = $surat->tindakLanjut()->where('pegawai_id', $this->guru->pegawai_id)->firstOrFail();
        $this->token($this->guru);
        $this->getJson($this->url('surat-saya/'.$surat->id))->assertOk();
        $this->assertNull($t->fresh()->dibaca_pada);
        $this->postJson($this->url('surat-saya/'.$surat->id.'/baca'))->assertOk();
        $waktu = $t->fresh()->dibaca_pada->toIso8601String();
        $this->travel(10)->minutes();
        $this->postJson($this->url('surat-saya/'.$surat->id.'/baca'))->assertOk();
        $this->assertSame($waktu, $t->fresh()->dibaca_pada->toIso8601String());
        $this->assertSame('belum_mulai', $t->fresh()->status);
        $this->assertNull($t->fresh()->selesai_pada);
        $this->assertSame(1, $surat->riwayat()->where('aksi', 'dibaca')->count());
        $this->assertNull($this->guru->notifikasiPengguna()->first()->dibaca_pada);
        $this->getJson($this->url('surat-saya').'?belum_dibaca=1')->assertOk()->assertJsonPath('data.paginasi.total', 0);
    }

    public function test_disposisi_hanya_pimpinan_dengan_versi_terbaru_dan_tidak_boleh_ke_diri_sendiri(): void
    {
        $surat = $this->buat('masuk');
        $payload = $this->isiDisposisi($surat);
        $this->postJson($this->url('persuratan/'.$surat->id.'/disposisi'), $payload)->assertForbidden();
        $this->token($this->kepala);
        $this->postJson($this->url('persuratan/'.$surat->id.'/disposisi'), array_replace($payload, ['pegawai_ids' => [$this->kepala->pegawai_id]]))->assertUnprocessable();
        $this->postJson($this->url('persuratan/'.$surat->id.'/disposisi'), $payload)->assertOk()->assertJsonCount(1, 'data.tindak_lanjut');
        $this->postJson($this->url('persuratan/'.$surat->id.'/disposisi'), $payload)->assertUnprocessable();
        $payload['versi'] = $surat->fresh()->versi;
        $this->postJson($this->url('persuratan/'.$surat->id.'/disposisi'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('tindak_lanjut_surat_tu', 1);
        $this->assertSame(1, $this->guru->notifikasiPengguna()->count());
        $this->assertFalse($surat->fresh()->dapatDiedit());
    }

    public function test_disposisi_rahasia_penerima_hanya_dapat_membuka_tugas_sendiri(): void
    {
        $t = $this->disposisi($this->buat('masuk', ['sifat' => 'rahasia']));
        $this->token($this->guru);
        $r = $this->getJson($this->url('tindak-lanjut-surat/'.$t->id))->assertOk()->assertJsonPath('data.hak_akses.milik_saya', true)
            ->assertJsonPath('data.hak_akses.akses_arsip', false)->assertJsonPath('data.hak_akses.dapat_periksa', false);
        $this->assertPribadi($r);
        $this->getJson($r->json('data.berkas_surat.0.url'))->assertOk();
        $this->getJson($this->url('persuratan/tindak-lanjut'))->assertForbidden();
        $this->token($this->lain);
        $this->getJson($this->url('tindak-lanjut-surat/'.$t->id))->assertNotFound();
        $this->getJson($r->json('data.berkas_surat.0.url'))->assertNotFound();
        $this->getJson($this->url('tindak-lanjut-surat'))->assertOk()->assertJsonPath('data.paginasi.total', 0);
    }

    public function test_daftar_tindak_lanjut_filter_terlambat_monitoring_dan_pemeriksaan_saya(): void
    {
        $t = $this->disposisi();
        $this->travelTo(now()->setDate(2026, 10, 22));
        $this->token($this->guru);
        $this->getJson($this->url('tindak-lanjut-surat').'?terlambat=1&jenis=disposisi&kata_kunci=421&per_halaman=1')->assertOk()
            ->assertJsonPath('data.paginasi.total', 1)->assertJsonPath('data.items.0.terlambat', true)
            ->assertJsonPath('data.statistik.belum_mulai', 1)->assertJsonPath('data.statistik_mengikuti_filter', true);
        $this->getJson($this->url('tindak-lanjut-surat').'?status=selesai')->assertOk()->assertJsonPath('data.statistik.belum_mulai', 0);
        $this->getJson($this->url('tindak-lanjut-surat').'?pemeriksaan_saya=1')->assertUnprocessable();
        $this->lapor($t, 'ajukan')->assertCreated();
        $this->token($this->kepala);
        $this->getJson($this->url('persuratan/tindak-lanjut').'?pemeriksaan_saya=1')->assertOk()->assertJsonPath('data.paginasi.total', 1);
        $this->getJson($this->url('persuratan/tindak-lanjut').'?terlambat=1')->assertOk()->assertJsonPath('data.paginasi.total', 0);
        $this->token($this->tu);
        $this->getJson($this->url('persuratan/tindak-lanjut'))->assertOk()->assertJsonPath('data.paginasi.total', 1);
        $this->getJson($this->url('persuratan/tindak-lanjut').'?pemeriksaan_saya=1')->assertOk()->assertJsonPath('data.paginasi.total', 0);
    }

    public function test_laporan_progres_pengajuan_perbaikan_dan_penerimaan_mempertahankan_semua_versi(): void
    {
        $t = $this->disposisi();
        $this->aksiTindak($t, 'baca', $this->guru)->assertOk()->assertJsonPath('data.status', 'belum_mulai');
        $this->aksiTindak($t, 'mulai', $this->guru)->assertOk()->assertJsonPath('data.status', 'diproses');
        $this->lapor($t, 'draf', ['bukti' => [$this->pdf('bukti.pdf')]])->assertCreated();
        $awal = $t->laporan()->firstOrFail();
        $bukti = $awal->bukti()->firstOrFail();
        $this->lapor($t, 'ajukan', ['bukti_lama' => [$bukti->id]])->assertCreated()->assertJsonPath('data.status', 'menunggu_pemeriksaan');
        $this->lapor($t, 'draf')->assertUnprocessable();
        $this->aksiTindak($t, 'terima', $this->tu)->assertForbidden();
        $this->aksiTindak($t, 'kembalikan', $this->kepala)->assertUnprocessable()->assertJsonValidationErrors('catatan');
        $this->aksiTindak($t, 'kembalikan', $this->kepala, ['catatan' => 'Lengkapi bukti'])->assertOk()->assertJsonPath('data.status', 'perlu_perbaikan');
        $buktiTerakhir = $t->laporan()->first()->bukti()->firstOrFail();
        $this->lapor($t, 'ajukan', ['hasil' => 'Laporan diperbaiki', 'bukti_lama' => [$buktiTerakhir->id]])->assertCreated();
        $r = $this->aksiTindak($t, 'terima', $this->kepala, ['catatan' => 'Sesuai'])->assertOk()
            ->assertJsonPath('data.status', 'selesai')->assertJsonCount(3, 'data.laporan')->assertJsonPath('data.hak_akses.dapat_periksa', false);
        $this->assertPribadi($r);
        $this->assertSame('Hasil tindak lanjut', $awal->fresh()->hasil);
        $this->assertNotNull($t->fresh()->selesai_pada);
        $this->lapor($t, 'ajukan')->assertUnprocessable();
        $this->aksiTindak($t, 'terima', $this->kepala)->assertUnprocessable();
    }

    public function test_bukti_salah_parent_tidak_boleh_diunduh_atau_disertakan_dan_versi_usang_ditolak(): void
    {
        $t = $this->disposisi();
        $this->lapor($t, 'draf', ['bukti' => [$this->pdf('bukti.pdf')]])->assertCreated();
        $bukti = $t->laporan()->first()->bukti()->first();
        $versi = $t->fresh()->versi;
        $lain = $this->disposisi($this->buat('masuk', ['nomor_surat' => 'D-002', 'nomor_agenda' => 'AG-002']));
        $this->token($this->guru);
        $this->getJson($this->url('tindak-lanjut-surat/'.$lain->id.'/bukti/'.$bukti->id))->assertNotFound();
        $this->lapor($lain, 'draf', ['bukti_lama' => [$bukti->id], 'bukti' => [$this->pdf('gagal.pdf')]])->assertUnprocessable();
        $this->assertCount(1, Storage::disk('local')->allFiles('persuratan-tindak-lanjut'));
        $this->lapor($t, 'draf', ['hasil' => 'Versi baru'])->assertCreated();
        $this->post($this->url('tindak-lanjut-surat/'.$t->id.'/laporan'), ['versi' => $versi, 'aksi' => 'ajukan', 'hasil' => 'Versi usang'])->assertUnprocessable();
        $this->lapor($t, 'ajukan', ['bukti_lama' => [$bukti->id]])->assertUnprocessable();
        $this->assertSame(2, $t->laporan()->count());
        $this->getJson($this->url('tindak-lanjut-surat/'.$t->id.'/bukti/'.$bukti->id))->assertOk();
        Storage::disk('local')->put($bukti->lokasi_file, 'berubah');
        $this->getJson($this->url('tindak-lanjut-surat/'.$t->id.'/bukti/'.$bukti->id))->assertNotFound();
    }

    public function test_pemeriksa_tidak_boleh_menerima_laporan_sendiri_dan_dapat_diganti(): void
    {
        $surat = $this->disahkan(['pegawai_ids' => [$this->kepala->pegawai_id]]);
        $t = $surat->tindakLanjut()->firstOrFail();
        $this->lapor($t, 'ajukan', [], $this->kepala)->assertUnprocessable();
        $this->aksiTindak($t, 'terima', $this->kepala)->assertForbidden();
        $pengganti = $this->akun('pimpinan', 'Pejabat pengganti');
        $data = ['catatan' => 'Menghindari pemeriksaan diri sendiri', 'instruksi' => 'Laporkan kegiatan', 'batas_tanggal' => '2026-10-22', 'pemeriksa_pengguna_id' => $pengganti->id];
        $this->aksiTindak($t, 'ubah', $this->kepala, $data)->assertOk()->assertJsonPath('data.pemeriksa.id', $pengganti->id);
        $this->lapor($t, 'ajukan', [], $this->kepala)->assertCreated();
        $this->aksiTindak($t, 'terima', $this->kepala)->assertForbidden();
        $this->aksiTindak($t, 'terima', $pengganti)->assertOk()->assertJsonPath('data.status', 'selesai');
    }

    public function test_pembatalan_surat_menutup_tugas_aktif_tanpa_menghapus_laporan(): void
    {
        $surat = $this->disahkan();
        $t = $surat->tindakLanjut()->where('pegawai_id', $this->guru->pegawai_id)->firstOrFail();
        $this->lapor($t, 'draf', ['bukti' => [$this->pdf('bukti.pdf')]])->assertCreated();
        $this->aksiSurat($surat, 'batal', $this->tu, ['catatan' => 'Batal'])->assertForbidden();
        $this->aksiSurat($surat, 'batal', $this->kepala, ['catatan' => 'Pelatihan ditunda'])->assertOk();
        $this->assertSame('dibatalkan', $t->fresh()->status);
        $this->assertSame(1, $t->laporan()->count());
        $this->token($this->guru);
        $this->getJson($this->url('tindak-lanjut-surat/'.$t->id))->assertOk()->assertJsonPath('data.hak_akses.dapat_laporkan', false);
        $this->lapor($t, 'ajukan')->assertUnprocessable();
        $this->aksiTindak($t, 'mulai', $this->guru)->assertUnprocessable();
    }

    public function test_notifikasi_surat_memiliki_fallback_sampai_layar_native_tersedia_dan_tidak_otomatis_membaca(): void
    {
        $surat = $this->disahkan();
        $n = $this->guru->notifikasiPengguna()->firstOrFail();
        $this->token($this->guru);
        $this->getJson(route('api.v1.notifikasi.tujuan', $n))->assertOk()->assertJsonPath('data.tautan_mobile', null);
        $this->assertNull($n->fresh()->dibaca_pada);
        $this->assertNull($surat->penugasan()->where('pegawai_id', $this->guru->pegawai_id)->first()->dibaca_pada);
        $this->token($this->lain);
        $this->getJson(route('api.v1.notifikasi.tujuan', $n))->assertForbidden();
        $t = $this->disposisi($this->buat('masuk', ['nomor_surat' => 'D-002']));
        $n = $this->guru->notifikasiPengguna()->latest('id')->firstOrFail();
        $this->token($this->guru);
        $this->getJson(route('api.v1.notifikasi.tujuan', $n))->assertOk()->assertJsonPath('data.tautan_mobile', null);
        $this->assertNull($t->fresh()->dibaca_pada);
    }

    private function akun(string $peran, string $nama = 'Pegawai Tes'): Pengguna
    {
        $p = Pegawai::create(['nama_lengkap' => $nama, 'aktif' => true, 'jenis_kelamin' => 'L']);
        $u = Pengguna::create(['pegawai_id' => $p->id, 'nama' => $nama, 'username' => 'api.surat.'.uniqid(),
            'kata_sandi' => 'SuratTes12345', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $u->daftarPeran()->attach(Peran::where('kode', $peran)->firstOrFail());

        return $u;
    }

    public function test_lampiran_pdf_dapat_dilepas_dan_berkas_utama_lama_hanya_dapat_diunduh_arsip(): void
    {
        $surat = $this->buat('tugas', ['lampiran' => [$this->pdf('lampiran.pdf')]]);
        $utamaLama = $surat->berkas()->where('jenis', 'utama')->firstOrFail();
        $lampiran = $surat->berkas()->where('jenis', 'lampiran')->firstOrFail();
        $data = $this->payload('tugas', ['_method' => 'PATCH', 'versi' => $surat->versi, 'catatan_revisi' => 'Ganti berkas final', 'hapus_lampiran' => [$lampiran->id]]);
        $this->post($this->url('persuratan/'.$surat->id), $data)->assertOk()->assertJsonCount(3, 'data.berkas');
        $this->getJson($this->url('persuratan/'.$surat->id.'/berkas/'.$utamaLama->id))->assertOk();
        $this->aksiSurat($surat, 'ajukan', $this->tu)->assertOk();
        $this->aksiSurat($surat, 'sahkan', $this->kepala, ['konfirmasi_berkas' => true])->assertOk();
        $this->token($this->guru);
        $r = $this->getJson($this->url('surat-saya/'.$surat->id))->assertOk()->assertJsonCount(1, 'data.berkas');
        $this->getJson($r->json('data.berkas.0.url'))->assertOk();
        $this->getJson($this->url('surat-saya/'.$surat->id.'/berkas/'.$utamaLama->id))->assertNotFound();
        $this->getJson($this->url('surat-saya/'.$surat->id.'/berkas/'.$lampiran->id))->assertNotFound();
    }

    public function test_pengesah_yang_juga_pembuat_tidak_boleh_mengesahkan_suratnya_sendiri(): void
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->token($admin);
        $this->post($this->url(), $this->payload('keluar', ['pengesah_pengguna_id' => $admin->id]))->assertUnprocessable();
        $surat = $this->buat('keluar');
        // Also reject malformed legacy records whose creator is the approver.
        $surat->update(['dibuat_oleh_pengguna_id' => $this->kepala->id]);
        $this->aksiSurat($surat, 'ajukan', $this->tu)->assertOk();
        $this->token($this->kepala);
        $this->getJson($this->url('persuratan/'.$surat->id))->assertOk()->assertJsonPath('data.hak_akses.dapat_sahkan', false);
        $this->aksiSurat($surat, 'sahkan', $this->kepala, ['konfirmasi_berkas' => true])->assertUnprocessable();
        $this->assertSame('diajukan', $surat->fresh()->status);
        $this->assertSame(0, $this->guru->notifikasiPengguna()->count());
    }

    public function test_guru_dan_pimpinan_tidak_boleh_memodifikasi_arsip_tanpa_izin_kelola(): void
    {
        $surat = $this->buat();
        foreach ([$this->guru, $this->kepala] as $akun) {
            $this->token($akun);
            $this->post($this->url(), $this->payload('keluar', ['nomor_surat' => 'X-002']))->assertForbidden();
            $data = $this->payload('tugas', ['_method' => 'PATCH', 'versi' => $surat->versi, 'catatan_revisi' => 'Tidak diizinkan']);
            $this->post($this->url('persuratan/'.$surat->id), $data)->assertForbidden();
        }
        $this->postJson($this->url('persuratan/'.$surat->id.'/tindakan/hapus'), ['versi' => $surat->versi])->assertNotFound();
        $this->assertSame('draf', $surat->fresh()->status);
        $this->assertCount(1, Storage::disk('local')->allFiles('persuratan'));
        $this->assertSame(1, $surat->riwayat()->count());
    }

    public function test_batas_permohonan_unggah_diterapkan_dengan_retry_after(): void
    {
        $this->withMiddleware(ThrottleRequests::class);
        $this->token($this->guru);
        for ($i = 0; $i < 15; $i++) {
            $this->postJson($this->url(), [])->assertForbidden();
        }
        $this->postJson($this->url(), [])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertDatabaseCount('surat_tu', 0);
    }

    private function token(Pengguna $u, array $abilities = ['mobile']): void
    {
        Auth::forgetGuards();
        $this->withToken($u->createToken('Android Persuratan Tes', $abilities)->plainTextToken)->withHeader('Accept', 'application/json');
    }

    private function url(string $path = 'persuratan'): string
    {
        return url('/api/v1/'.$path);
    }

    private function pdf(string $nama = 'surat.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 20, 'application/pdf');
    }

    private function payload(string $jenis = 'tugas', array $extra = []): array
    {
        return array_replace(['jenis' => $jenis, 'nomor_surat' => '421/001/2026', 'nomor_agenda' => $jenis === 'masuk' ? 'AG-001' : null,
            'tanggal_surat' => '2026-10-12', 'tanggal_diterima' => $jenis === 'masuk' ? '2026-10-12' : null,
            'perihal' => 'Penugasan Pelatihan Guru', 'pihak' => 'Dinas Pendidikan', 'sifat' => 'biasa',
            'pengesah_pengguna_id' => $jenis !== 'masuk' ? $this->kepala->id : null,
            'mulai_tugas' => $jenis === 'tugas' ? '2026-10-20' : null, 'selesai_tugas' => $jenis === 'tugas' ? '2026-10-21' : null,
            'tempat_tugas' => $jenis === 'tugas' ? 'Aula Dinas' : null, 'uraian_tugas' => $jenis === 'tugas' ? 'Mengikuti pelatihan guru' : null,
            'pegawai_ids' => $jenis === 'tugas' ? [$this->guru->pegawai_id, $this->lain->pegawai_id] : [], 'berkas_utama' => $this->pdf()], $extra);
    }

    private function buat(string $jenis = 'tugas', array $extra = []): SuratTu
    {
        $this->token($this->tu);
        $r = $this->post($this->url(), $this->payload($jenis, $extra))->assertCreated();
        $this->assertPribadi($r);

        return SuratTu::findOrFail($r->json('data.id'));
    }

    private function aksiSurat(SuratTu $s, string $aksi, Pengguna $u, array $data = []): TestResponse
    {
        $this->token($u);

        return $this->postJson($this->url('persuratan/'.$s->id.'/tindakan/'.$aksi), ['versi' => $s->fresh()->versi] + $data);
    }

    private function disahkan(array $extra = []): SuratTu
    {
        $s = $this->buat('tugas', $extra);
        $this->aksiSurat($s, 'ajukan', $this->tu)->assertOk();
        $this->aksiSurat($s, 'sahkan', $this->kepala, ['konfirmasi_berkas' => true])->assertOk();

        return $s->fresh();
    }

    private function isiDisposisi(SuratTu $s): array
    {
        return ['versi' => $s->fresh()->versi, 'pegawai_ids' => [$this->guru->pegawai_id], 'instruksi' => 'Siapkan tindak lanjut surat', 'batas_tanggal' => '2026-10-20'];
    }

    private function disposisi(?SuratTu $s = null): TindakLanjutSuratTu
    {
        $s ??= $this->buat('masuk');
        $this->token($this->kepala);
        $this->postJson($this->url('persuratan/'.$s->id.'/disposisi'), $this->isiDisposisi($s))->assertOk();

        return $s->tindakLanjut()->firstOrFail();
    }

    private function aksiTindak(TindakLanjutSuratTu $t, string $aksi, Pengguna $u, array $extra = []): TestResponse
    {
        $this->token($u);

        return $this->postJson($this->url('tindak-lanjut-surat/'.$t->id.'/tindakan/'.$aksi), ['versi' => $t->fresh()->versi] + $extra);
    }

    private function lapor(TindakLanjutSuratTu $t, string $aksi, array $extra = [], ?Pengguna $u = null): TestResponse
    {
        $this->token($u ?? $this->guru);

        return $this->post($this->url('tindak-lanjut-surat/'.$t->id.'/laporan'), array_replace(['versi' => $t->fresh()->versi, 'aksi' => $aksi, 'hasil' => 'Hasil tindak lanjut'], $extra));
    }

    private function assertPribadi(TestResponse $r): void
    {
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        foreach (['lokasi_file', 'sha256', 'nomor_unik', 'agenda_unik', 'kata_sandi', 'remember_token'] as $key) {
            $r->assertDontSee('"'.$key.'"', false);
        }
    }
}
