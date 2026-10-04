<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\Kelas;
use App\Models\KunjunganTamu;
use App\Models\LampiranKunjunganTamu;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use App\Services\Humas\AksesBukuTamuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class BukuTamuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 20, 35));
    }

    public function test_hak_akses_humas_pimpinan_satpam_dan_akun_tanpa_izin(): void
    {
        $this->get(route('buku-tamu.index'))->assertRedirect(route('login'));
        $tamu = $this->tamu();
        foreach (['siswa', 'orang_tua', 'pegawai'] as $role) {
            $this->actingAs($this->akun($role))->get(route('buku-tamu.index'))->assertForbidden();
            $this->get(route('buku-tamu.show', $tamu))->assertForbidden();
            $this->post(route('buku-tamu.store'), $this->data())->assertForbidden();
            $this->get(route('buku-tamu.export'))->assertForbidden();
        }
        $this->actingAs($this->akun('pimpinan'))->get(route('buku-tamu.index', ['tab' => 'rekap']))->assertOk()->assertSee('Ekspor Excel')->assertDontSee('Catat kedatangan');
        $this->get(route('buku-tamu.create'))->assertForbidden();
        $this->post(route('buku-tamu.store'), $this->data())->assertForbidden();
        $this->get(route('buku-tamu.edit', $tamu))->assertForbidden();
        $this->patch(route('buku-tamu.pulang', $tamu))->assertForbidden();
        $this->actingAs($this->akun('satpam'))->get(route('buku-tamu.index'))->assertOk()->assertSee('Catat kedatangan')->assertDontSee('Rekap kunjungan');
        $this->get(route('buku-tamu.index', ['tab' => 'rekap']))->assertForbidden();
        $this->get(route('buku-tamu.export'))->assertForbidden();
        $this->get(route('buku-tamu.cetak'))->assertForbidden();
        $this->put(route('buku-tamu.update', $tamu), $this->data())->assertForbidden();
        $this->patch(route('buku-tamu.batalkan', $tamu), ['alasan_pembatalan' => 'Salah data'])->assertForbidden();
        $this->actingAs($this->akun('wakil_pimpinan_humas'))->get(route('buku-tamu.edit', $tamu))->assertOk();
    }

    public function test_pencatatan_idempoten_dan_petugas_tidak_dapat_memalsukan_waktu_status_atau_pencatat(): void
    {
        $akun = $this->akun('satpam');
        $data = $this->data() + ['status' => 'selesai', 'waktu_pulang' => '2026-10-01T08:00', 'dicatat_oleh_pengguna_id' => 999, 'nama_tujuan' => 'Tujuan palsu'];
        $this->actingAs($akun)->postJson(route('buku-tamu.store'), $data)->assertOk()->assertJsonStructure(['redirect', 'pesan']);
        $tamu = KunjunganTamu::firstOrFail();
        $this->assertSame('2026-10-05 10:20:35', $tamu->waktu_datang->toDateTimeString());
        $this->assertSame('berkunjung', $tamu->status);
        $this->assertSame('Tata Usaha', $tamu->nama_tujuan);
        $this->assertSame($akun->id, $tamu->dicatat_oleh_pengguna_id);
        $this->assertNull($tamu->waktu_pulang);
        $this->travel(2)->minutes();
        $this->postJson(route('buku-tamu.store'), $data)->assertOk();
        $this->assertDatabaseCount('kunjungan_tamu', 1);
        $this->assertSame(1, $tamu->riwayat()->count());
        $this->actingAs($this->akun('satpam'))->postJson(route('buku-tamu.store'), $data)->assertForbidden();
    }

    public function test_validasi_identitas_waktu_dan_tujuan_nonaktif(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $data = $this->data();
        $pegawai = Pegawai::create(['nama_lengkap' => 'Pegawai nonaktif', 'aktif' => false]);
        $this->post(route('buku-tamu.store'), array_replace($data, ['nama_tamu' => '', 'nomor_wa' => 'javascript:alert(1)', 'keperluan' => '', 'tujuan_lain' => '', 'kategori' => 'palsu', 'waktu_datang' => '2026-10-06T09:00']))
            ->assertSessionHasErrors(['nama_tamu', 'nomor_wa', 'keperluan', 'tujuan_lain', 'kategori', 'waktu_datang']);
        $this->post(route('buku-tamu.store'), array_replace($data, ['pegawai_tujuan_id' => $pegawai->id]))->assertSessionHasErrors('pegawai_tujuan_id');
        $pegawai->update(['aktif' => true]);
        $this->post(route('buku-tamu.store'), array_replace($data, ['pegawai_tujuan_id' => $pegawai->id]))->assertRedirect()->assertSessionHasNoErrors();
        $tamu = KunjunganTamu::firstOrFail();
        $this->assertSame('Pegawai nonaktif', $tamu->nama_tujuan);
        $pegawai->update(['aktif' => false, 'nama_lengkap' => 'Nama baru']);
        $this->get(route('buku-tamu.edit', $tamu))->assertOk()->assertSee('Nama baru');
        $this->put(route('buku-tamu.update', $tamu), array_replace($data, ['pegawai_tujuan_id' => $pegawai->id, 'versi' => 0, 'catatan' => 'Koreksi catatan']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Pegawai nonaktif', $tamu->fresh()->nama_tujuan);
    }

    public function test_tandai_pulang_idempoten_dan_koreksi_lama_tidak_membuka_kembali_kunjungan(): void
    {
        $akun = $this->akun('satpam');
        $tamu = $this->tamu();
        $this->actingAs($akun)->patch(route('buku-tamu.pulang', $tamu))->assertRedirect();
        $waktu = $tamu->fresh()->waktu_pulang->toDateTimeString();
        $this->travel(5)->minutes();
        $this->patch(route('buku-tamu.pulang', $tamu))->assertRedirect();
        $this->assertSame($waktu, $tamu->fresh()->waktu_pulang->toDateTimeString());
        $this->assertSame(1, $tamu->riwayat()->where('aksi', 'pulang')->count());
        $this->actingAs($this->akun('wakil_pimpinan_humas'))->put(route('buku-tamu.update', $tamu), $this->data() + ['versi' => 0])->assertSessionHasErrors('versi');
        $this->assertSame('selesai', $tamu->fresh()->status);
        $this->put(route('buku-tamu.update', $tamu), array_replace($this->data(), ['versi' => 1, 'waktu_pulang' => '2026-10-05T08:59']))->assertSessionHasErrors('waktu_pulang');
        $this->put(route('buku-tamu.update', $tamu), array_replace($this->data(), ['versi' => 1, 'waktu_pulang' => '2026-10-06T10:00']))->assertSessionHasErrors('waktu_pulang');
    }

    public function test_koreksi_dan_pembatalan_menyimpan_jejak_dan_kunjungan_batal_tidak_bisa_diubah(): void
    {
        $akun = $this->akun('wakil_pimpinan_humas');
        $tamu = $this->tamu();
        $this->actingAs($akun)->put(route('buku-tamu.update', $tamu), $this->data() + ['versi' => 0])->assertRedirect();
        $audit = $tamu->riwayat()->where('aksi', 'koreksi')->firstOrFail();
        $this->assertSame('Tamu uji', $audit->data_sebelum['nama_tamu']);
        $this->assertSame('Tamu Dinas', $audit->data_sesudah['nama_tamu']);
        $this->assertSame($akun->id, $audit->pengguna_id);
        $this->patch(route('buku-tamu.batalkan', $tamu), ['alasan_pembatalan' => 'x'])->assertSessionHasErrors('alasan_pembatalan');
        $this->patch(route('buku-tamu.batalkan', $tamu), ['alasan_pembatalan' => 'Pencatatan ganda'])->assertRedirect();
        $this->assertSame('dibatalkan', $tamu->fresh()->status);
        $this->patch(route('buku-tamu.pulang', $tamu))->assertSessionHasErrors('kunjungan');
        $this->put(route('buku-tamu.update', $tamu), $this->data() + ['versi' => $tamu->fresh()->versi])->assertSessionHasErrors('kunjungan');
        $this->get(route('buku-tamu.edit', $tamu))->assertSessionHasErrors('kunjungan');
        $this->assertNull($tamu->fresh()->durasiMenit());
        $this->assertModelExists($tamu);
        $this->get(route('buku-tamu.show', $tamu))->assertOk()->assertSee('Pencatatan ganda')->assertDontSee('Tandai sudah pulang');
    }

    public function test_satpam_hanya_melihat_hari_ini_dan_kunjungan_lama_yang_belum_pulang(): void
    {
        $lama = $this->tamu(['nama_tamu' => 'Kunjungan lama selesai', 'waktu_datang' => '2026-10-01 09:00', 'waktu_pulang' => '2026-10-01 09:40', 'status' => 'selesai']);
        $aktifLama = $this->tamu(['nama_tamu' => 'Tamu belum pulang kemarin', 'waktu_datang' => '2026-10-04 09:00']);
        $this->actingAs($this->akun('satpam'))->get(route('buku-tamu.index'))->assertOk()->assertSee($aktifLama->nama_tamu)->assertDontSee($lama->nama_tamu);
        $this->get(route('buku-tamu.show', $lama))->assertForbidden();
        $this->patch(route('buku-tamu.pulang', $lama))->assertForbidden();
        $this->patch(route('buku-tamu.pulang', $aktifLama))->assertRedirect();
        $this->get(route('buku-tamu.show', $aktifLama))->assertForbidden();
        $this->actingAs($this->akun('wakil_pimpinan_humas'))->get(route('buku-tamu.show', $lama))->assertOk();
    }

    public function test_guru_hanya_dapat_mencatat_saat_jadwal_piket_dan_penugasan_mapel_aktif(): void
    {
        [$akun, $jadwal, $penugasan] = $this->guruPiket();
        $this->actingAs($akun)->get(route('buku-tamu.create'))->assertOk();
        $this->get(route('beranda'))->assertOk()->assertSee(route('buku-tamu.index'));
        $this->post(route('buku-tamu.store'), $this->data())->assertRedirect();
        $jadwal->update(['aktif' => false]);
        $this->get(route('buku-tamu.index'))->assertForbidden();
        $this->get(route('beranda'))->assertOk()->assertDontSee(route('buku-tamu.index'));
        $jadwal->update(['aktif' => true, 'hari' => 'selasa']);
        $this->get(route('buku-tamu.create'))->assertForbidden();
        $jadwal->update(['hari' => 'senin']);
        $penugasan->update(['aktif' => false]);
        $this->post(route('buku-tamu.store'), $this->data())->assertForbidden();
        $penugasan->update(['aktif' => true]);
        TahunPelajaran::query()->update(['aktif' => false]);
        $this->get(route('buku-tamu.index'))->assertForbidden();
        $this->assertFalse(app(AksesBukuTamuService::class)->bolehMembuka($akun));
    }

    public function test_akun_nonaktif_ditolak_service(): void
    {
        $akun = $this->akun('wakil_pimpinan_humas');
        $akun->update(['aktif' => false]);
        $akses = app(AksesBukuTamuService::class);
        $this->assertFalse($akses->bolehKelola($akun));
        $this->assertFalse($akses->bolehRekap($akun));
        $this->assertFalse($akses->bolehMencatat($akun));
    }

    public function test_lampiran_privat_berurutan_akses_nested_dan_unggahan_ulang_tidak_ganda(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs($this->akun('satpam'));
        $data = $this->data() + ['surat_tugas' => [UploadedFile::fake()->create('surat.pdf', 20, 'application/pdf')], 'dokumentasi' => [$this->foto('foto.jpg')]];
        $this->postJson(route('buku-tamu.store'), $data)->assertOk();
        $this->postJson(route('buku-tamu.store'), $data)->assertOk();
        $tamu = KunjunganTamu::firstOrFail();
        $this->assertCount(2, $tamu->lampiran);
        $this->assertCount(2, Storage::disk('local')->allFiles('buku-tamu'));
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $pdf = $tamu->lampiran->first();
        $foto = $tamu->lampiran->last();
        $this->get(route('buku-tamu.lampiran.unduh', [$tamu, $pdf]))->assertOk()->assertDownload('surat.pdf');
        $this->get(route('buku-tamu.lampiran.pratinjau', [$tamu, $pdf]))->assertNotFound();
        $pratinjau = $this->get(route('buku-tamu.lampiran.pratinjau', [$tamu, $foto]))->assertOk();
        $this->assertStringContainsString('no-store', $pratinjau->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $pratinjau->headers->get('Cache-Control'));
        $asing = $this->tamu();
        $this->get(route('buku-tamu.lampiran.unduh', [$asing, $pdf]))->assertNotFound();
        $this->delete(route('buku-tamu.lampiran.destroy', [$tamu, $pdf]))->assertForbidden();
        $upload = ['token_unggahan' => (string) Str::uuid(), 'dokumentasi' => [$this->foto('tambahan.jpg')]];
        $this->postJson(route('buku-tamu.lampiran.store', $tamu), $upload)->assertOk();
        $this->postJson(route('buku-tamu.lampiran.store', $tamu), $upload)->assertOk();
        $this->assertSame(3, $tamu->lampiran()->count());
        $this->actingAs($this->akun('orang_tua'))->get(route('buku-tamu.lampiran.unduh', [$tamu, $pdf]))->assertForbidden();
        $this->actingAs($this->akun('wakil_pimpinan_humas'))->delete(route('buku-tamu.lampiran.destroy', [$tamu, $pdf]))->assertRedirect();
        Storage::disk('local')->assertMissing($pdf->lokasi_file);
        $this->assertSame('surat.pdf', $tamu->riwayat()->where('aksi', 'hapus_lampiran')->firstOrFail()->data_sebelum['nama_file']);
    }

    public function test_validasi_jenis_ukuran_dan_jumlah_lampiran(): void
    {
        Storage::fake('local');
        $this->actingAs($this->akun('satpam'));
        $tamu = $this->tamu();
        $url = route('buku-tamu.lampiran.store', $tamu);
        $token = ['token_unggahan' => (string) Str::uuid()];
        $this->postJson($url, $token)->assertUnprocessable()->assertJsonValidationErrors('lampiran');
        $this->postJson($url, $token + ['surat_tugas' => [UploadedFile::fake()->create('program.exe', 10, 'application/octet-stream')]])->assertUnprocessable()->assertJsonValidationErrors('surat_tugas.0');
        $this->postJson($url, $token + ['surat_tugas' => [UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf')]])->assertUnprocessable()->assertJsonValidationErrors('surat_tugas.0');
        $this->postJson($url, $token + ['dokumentasi' => [UploadedFile::fake()->create('bukanfoto.pdf', 20, 'application/pdf')]])->assertUnprocessable()->assertJsonValidationErrors('dokumentasi.0');
        $file = array_map(fn () => $this->foto('foto.jpg')->size(5000), range(1, 5));
        $this->postJson($url, $token + ['dokumentasi' => $file])->assertUnprocessable()->assertJsonValidationErrors('lampiran');
        for ($i = 0; $i < 20; $i++) {
            $tamu->lampiran()->create(['jenis' => 'surat_tugas', 'lokasi_file' => 'buku-tamu/f'.$i, 'nama_file_asli' => 'f.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => 1]);
        }
        $this->postJson($url, $token + ['surat_tugas' => [UploadedFile::fake()->create('surat.pdf', 20, 'application/pdf')]])->assertUnprocessable()->assertJsonValidationErrors('lampiran');
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_kunjungan_lama_dan_batal_tidak_membuka_akses_upload_yang_tidak_sesuai(): void
    {
        Storage::fake('local');
        $lama = $this->tamu(['waktu_datang' => '2026-10-01 09:00', 'waktu_pulang' => '2026-10-01 10:00', 'status' => 'selesai']);
        $batal = $this->tamu(['status' => 'dibatalkan', 'alasan_pembatalan' => 'Salah catat']);
        $lampiran = $lama->lampiran()->create(['jenis' => 'dokumentasi', 'lokasi_file' => 'buku-tamu/lama.jpg', 'nama_file_asli' => 'lama.jpg', 'tipe_file' => 'image/jpeg', 'ukuran_file' => 1]);
        Storage::disk('local')->put($lampiran->lokasi_file, 'file uji');
        $data = ['token_unggahan' => (string) Str::uuid(), 'dokumentasi' => [$this->foto('foto.jpg')]];
        $this->actingAs($this->akun('satpam'))->postJson(route('buku-tamu.lampiran.store', $lama), $data)->assertForbidden();
        $this->get(route('buku-tamu.lampiran.unduh', [$lama, $lampiran]))->assertForbidden();
        $this->actingAs($this->akun('wakil_pimpinan_humas'))->postJson(route('buku-tamu.lampiran.store', $batal), $data)->assertUnprocessable()->assertJsonValidationErrors('kunjungan');
        $this->delete(route('buku-tamu.lampiran.destroy', [$batal, $lampiran]))->assertNotFound();
        $this->actingAs($this->akun('pimpinan'))->postJson(route('buku-tamu.lampiran.store', $lama), $data)->assertForbidden();
        $this->get(route('buku-tamu.lampiran.unduh', [$lama, $lampiran]))->assertOk();
        $this->assertCount(1, Storage::disk('local')->allFiles('buku-tamu'));
    }

    public function test_koreksi_tanpa_perubahan_mempertahankan_detik_dan_tidak_menambah_riwayat(): void
    {
        $tamu = $this->tamu(['nama_tamu' => 'Tamu Dinas', 'instansi' => 'Dinas Pendidikan', 'nomor_wa' => '081234567890', 'keperluan' => 'Koordinasi program pendidikan', 'waktu_datang' => '2026-10-05 09:00:35', 'waktu_pulang' => '2026-10-05 09:40:45', 'status' => 'selesai']);
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $data = $this->data() + ['versi' => 0, 'waktu_pulang' => '2026-10-05T09:40'];
        $this->put(route('buku-tamu.update', $tamu), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2026-10-05 09:00:35', $tamu->fresh()->waktu_datang->toDateTimeString());
        $this->assertSame('2026-10-05 09:40:45', $tamu->fresh()->waktu_pulang->toDateTimeString());
        $this->assertSame(0, $tamu->fresh()->versi);
        $this->assertSame(0, $tamu->riwayat()->count());
    }

    public function test_kegagalan_database_membersihkan_file_yang_sudah_diunggah(): void
    {
        Storage::fake('local');
        $this->actingAs($this->akun('satpam'));
        LampiranKunjunganTamu::creating(fn () => throw new RuntimeException('Simulasi gagal simpan metadata'));
        $this->withoutExceptionHandling();
        try {
            $this->postJson(route('buku-tamu.store'), $this->data() + ['dokumentasi' => [$this->foto('foto.jpg')]]);
            $this->fail('Seharusnya terjadi kegagalan metadata.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulasi gagal simpan metadata', $e->getMessage());
        } finally {
            LampiranKunjunganTamu::flushEventListeners();
        }
        $this->assertDatabaseCount('kunjungan_tamu', 0);
        $this->assertDatabaseCount('lampiran_kunjungan_tamu', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('buku-tamu'));
    }

    public function test_filter_semester_statistik_dan_cetak_menggunakan_rentang_yang_sama(): void
    {
        $tahun = $this->tahun();
        $this->tamu(['nama_tamu' => 'Tamu Juli', 'waktu_datang' => '2026-07-01 00:00']);
        $this->tamu(['nama_tamu' => 'Tamu Desember', 'waktu_datang' => '2026-12-31 23:59:59']);
        $this->tamu(['nama_tamu' => 'Tamu Januari', 'waktu_datang' => '2027-01-01 00:00']);
        $this->tamu(['nama_tamu' => 'Tamu Juni', 'waktu_datang' => '2026-06-30 23:59:59']);
        $this->tamu(['nama_tamu' => 'Tamu Batal', 'status' => 'dibatalkan']);
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $filter = ['tab' => 'rekap', 'periode' => 'semester', 'tahun_pelajaran_id' => $tahun->id, 'semester' => 'ganjil'];
        $response = $this->get(route('buku-tamu.index', $filter))->assertOk()->assertSee('Tamu Juli')->assertSee('Tamu Desember')->assertDontSee('Tamu Januari')->assertDontSee('Tamu Juni');
        $this->assertSame(3, $response->viewData('ringkasan')['total']);
        $this->assertSame(2, (int) $response->viewData('ringkasan')['kategori']['dinas']);
        $this->get(route('buku-tamu.cetak', $filter))->assertOk()->assertSee('Tamu Juli')->assertDontSee('Tamu Januari');
        $this->get(route('buku-tamu.index', array_replace($filter, ['semester' => 'genap'])))->assertOk()->assertSee('Tamu Januari')->assertDontSee('Tamu Juli');
        $this->get(route('buku-tamu.index', ['tab' => 'rekap', 'periode' => 'rentang', 'dari' => '2026-10-05', 'sampai' => '2026-10-05', 'status' => 'dibatalkan']))->assertOk()->assertSee('Tamu Batal')->assertDontSee('Tamu Juli');
        $this->get(route('buku-tamu.index', ['tab' => 'rekap', 'periode' => 'rentang', 'dari' => '2026-10-05', 'sampai' => '2026-10-01']))->assertSessionHasErrors('sampai');
        $this->get(route('buku-tamu.index', ['tab' => 'rekap', 'periode' => 'semester']))->assertSessionHasErrors(['tahun_pelajaran_id', 'semester']);
    }

    public function test_excel_valid_mempertahankan_nomor_wa_dan_teks_tidak_menjadi_formula(): void
    {
        $this->tamu(['nama_tamu' => '=SUM(1,2)', 'nomor_wa' => '081234567890', 'keperluan' => 'A & B < C'.chr(1)]);
        $this->tamu(['nama_tamu' => 'Di luar filter', 'kategori' => 'alumni']);
        $this->actingAs($this->akun('pimpinan'));
        $response = $this->get(route('buku-tamu.export', ['periode' => 'hari_ini', 'kategori' => 'dinas']))->assertOk()->assertDownload();
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
            $this->assertStringNotContainsString('Di luar filter', $sheet);
            $this->assertStringContainsString('A &amp; B &lt; C', $sheet);
            $zip->close();
        } finally {
            @unlink($path);
        }
    }

    public function test_pencarian_paginasi_escape_dan_semua_tampilan_render(): void
    {
        Storage::fake('local');
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $this->tahun();
        Pegawai::create(['nama_lengkap' => 'Pegawai Tujuan Audit', 'aktif' => true]);
        $tamu = $this->tamu(['nama_tamu' => 'Kunjungan Dinas Pendidikan Kota Padang Panjang', 'instansi' => 'Dinas Pendidikan dan Kebudayaan', 'keperluan' => str_repeat('Koordinasi program pendidikan dan kunjungan kerja. ', 10)]);
        $file = $tamu->lampiran()->create(['jenis' => 'dokumentasi', 'lokasi_file' => 'buku-tamu/uji.jpg', 'nama_file_asli' => 'Dokumentasi kunjungan.jpg', 'tipe_file' => 'image/jpeg', 'ukuran_file' => 100]);
        Storage::disk('local')->put($file->lokasi_file, file_get_contents(public_path('images/login-sekolah.jpg')));
        $this->capture('show', $this->get(route('buku-tamu.show', $tamu))->assertOk());
        $this->capture('form', $this->get(route('buku-tamu.create'))->assertOk());
        $this->capture('edit', $this->get(route('buku-tamu.edit', $tamu))->assertOk());
        for ($i = 1; $i <= 29; $i++) {
            $this->tamu(['nama_tamu' => sprintf('Pengunjung %02d', $i), 'instansi' => str_repeat('Instansi ', 15), 'nama_tujuan' => str_repeat('Nama Pegawai ', 12)]);
        }
        $this->capture('index', $this->get(route('buku-tamu.index'))->assertOk());
        $this->capture('rekap', $this->get(route('buku-tamu.index', ['tab' => 'rekap']))->assertOk());
        $this->capture('cetak', $this->get(route('buku-tamu.cetak'))->assertOk());
        $response = $this->get(route('buku-tamu.index'))->assertOk();
        $this->assertSame(25, $response->viewData('kunjungan')->count());
        $this->assertSame(5, $this->get(route('buku-tamu.index', ['page' => 2]))->viewData('kunjungan')->count());
        $this->get(route('buku-tamu.index', ['kata_kunci' => 'Dinas Pendidikan']))->assertOk()->assertSee($tamu->nama_tamu)->assertDontSee('Pengunjung 01');
        $berbahaya = '</title><script>alert(1)</script>';
        $tamu->update(['nama_tamu' => $berbahaya]);
        $this->get(route('buku-tamu.show', $tamu))->assertOk()->assertDontSee($berbahaya, false)->assertSee(e($berbahaya), false);
        $this->actingAs($this->akun('satpam'));
        $this->capture('satpam', $this->get(route('buku-tamu.index'))->assertOk());
    }

    private function foto(string $nama): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, file_get_contents(public_path('images/login-sekolah.jpg')));
    }

    private function akun(string $role): Pengguna
    {
        $akun = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'tamu.'.uniqid(), 'kata_sandi' => 'TesTamu123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $akun;
    }

    private function tamu(array $data = []): KunjunganTamu
    {
        $tamu = new KunjunganTamu(array_replace(['nama_tamu' => 'Tamu uji', 'kategori' => 'dinas', 'keperluan' => 'Koordinasi sekolah', 'nama_tujuan' => 'Tata Usaha', 'waktu_datang' => '2026-10-05 09:00', 'status' => 'berkunjung'], $data));
        $tamu->forceFill(['token_pencatatan' => (string) Str::uuid()])->save();

        return $tamu;
    }

    private function data(): array
    {
        return ['token_pencatatan' => (string) Str::uuid(), 'nama_tamu' => 'Tamu Dinas', 'nomor_wa' => '081234567890', 'instansi' => 'Dinas Pendidikan', 'kategori' => 'dinas', 'keperluan' => 'Koordinasi program pendidikan', 'tujuan_lain' => 'Tata Usaha', 'waktu_datang' => '2026-10-05T09:00'];
    }

    private function tahun(): TahunPelajaran
    {
        TahunPelajaran::query()->update(['aktif' => false]);

        return TahunPelajaran::create(['nama' => '2026/2027 Tamu', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
    }

    private function guruPiket(): array
    {
        $tahun = $this->tahun();
        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru Piket Tamu', 'aktif' => true]);
        $akun = $this->akun('guru_mapel');
        $akun->update(['pegawai_id' => $pegawai->id]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A Tamu', 'tingkat' => 7, 'aktif' => true]);
        $mapel = MataPelajaran::create(['kode' => 'TAMU', 'nama' => 'Mapel uji', 'aktif' => true]);
        $penugasan = GuruMataPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'pegawai_id' => $pegawai->id, 'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
        $jadwal = JadwalPiketGuru::create(['tahun_pelajaran_id' => $tahun->id, 'pegawai_id' => $pegawai->id, 'hari' => 'senin', 'aktif' => true]);

        return [$akun, $jadwal, $penugasan];
    }

    private function capture(string $nama, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_BUKU_TAMU_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/buku-tamu');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$nama.'.html', $response->getContent());
    }
}
