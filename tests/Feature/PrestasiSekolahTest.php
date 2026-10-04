<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PrestasiSekolah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

class PrestasiSekolahTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 4)->setTime(10, 0));
        Storage::fake('local');
        $this->actingAs($this->akun());
    }

    public function test_izin_menu_humas_pimpinan_admin_dan_role_lain(): void
    {
        $p = $this->buat();
        $this->get(route('prestasi-sekolah.index'))->assertOk()->assertSee('Database Prestasi')->assertSee('Tambah prestasi');
        $this->actingAs($this->akun('pimpinan'))->get(route('prestasi-sekolah.index'))->assertOk()->assertDontSee('Tambah prestasi')->assertDontSee('Ekspor Excel sesuai filter');
        $this->get(route('prestasi-sekolah.show', $p))->assertOk()->assertDontSee('Edit prestasi');
        $this->get(route('prestasi-sekolah.cetak'))->assertOk();
        $this->get(route('prestasi-sekolah.create'))->assertForbidden();
        $this->get(route('prestasi-sekolah.edit', $p))->assertForbidden();
        $this->getJson(route('prestasi-sekolah.pilihan', ['jenis' => 'siswa']))->assertForbidden();
        $this->post(route('prestasi-sekolah.export'))->assertForbidden();
        foreach (['pegawai', 'guru_mapel', 'siswa', 'orang_tua'] as $role) {
            $this->actingAs($this->akun($role))->get(route('prestasi-sekolah.index'))->assertForbidden();
            $this->get(route('prestasi-sekolah.show', $p))->assertForbidden();
            $this->postJson(route('prestasi-sekolah.store'), $this->data())->assertForbidden();
        }
        $this->actingAs($this->akun('administrator'))->get(route('prestasi-sekolah.index'))->assertOk();
        auth()->forgetGuards();
        $this->get(route('prestasi-sekolah.index'))->assertRedirect(route('login'));
    }

    public function test_peserta_siswa_diambil_server_kelas_saat_prestasi_dan_tidak_mengubah_poin(): void
    {
        $s = $this->siswa();
        $tahun = $this->tahun();
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.A', 'tingkat' => 8, 'aktif' => true]);
        AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $s->id, 'nomor_absen' => 1]);
        $awal = $s->fresh()->getAttributes();
        $p = $this->buat(['peserta' => [['siswa_id' => $s->id, 'nama' => 'Nama palsu', 'kelas' => 'Kelas palsu']]]);
        $this->assertSame($s->nama_lengkap, $p->peserta->first()->nama);
        $this->assertSame('VIII.A', $p->peserta->first()->kelas);
        $this->assertSame($awal, $s->fresh()->getAttributes());
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
        $this->assertDatabaseCount('pengurangan_poin_siswa', 0);
    }

    public function test_peserta_pegawai_tanpa_data_pribadi_dan_identitas_salah_ditolak(): void
    {
        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru Berprestasi', 'nip' => '001122334455667788', 'no_hp' => '081299999999', 'aktif' => true]);
        $p = $this->buat(['penerima' => 'pegawai', 'peserta' => [['pegawai_id' => $pegawai->id, 'nama' => 'Palsu', 'kelas' => 'Palsu']]]);
        $this->assertSame('Guru Berprestasi', $p->peserta->first()->nama);
        $this->assertNull($p->peserta->first()->kelas);
        $this->get(route('prestasi-sekolah.show', $p))->assertDontSee($pegawai->nip)->assertDontSee($pegawai->no_hp);
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['peserta' => [['pegawai_id' => $pegawai->id]]]))->assertJsonValidationErrors('peserta.0');
        $s = $this->siswa();
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['penerima' => 'pegawai', 'peserta' => [['siswa_id' => $s->id]]]))->assertJsonValidationErrors('peserta.0');
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['peserta' => [['siswa_id' => $s->id, 'pegawai_id' => $pegawai->id]]]))->assertJsonValidationErrors('peserta.0');
    }

    public function test_manual_historis_tidak_membuat_data_siswa_pegawai_atau_akun(): void
    {
        $awal = Pengguna::count();
        $p = $this->buat(['tanggal_prestasi' => '2020-06-01', 'peserta' => [['nama' => ' Alumni   Lama ', 'kelas' => 'IX.A']]]);
        $this->assertSame('Alumni Lama', $p->peserta->first()->nama);
        $this->assertDatabaseCount('siswa', 0);
        $this->assertDatabaseCount('pegawai', 0);
        $this->assertSame($awal, Pengguna::count());
    }

    public function test_individu_tim_sekolah_dan_peserta_ganda(): void
    {
        foreach ([['peserta' => []], ['peserta' => [['nama' => 'A'], ['nama' => 'B']]], ['bentuk' => 'sekolah'], ['penerima' => 'sekolah'], ['peserta' => [['nama' => '']]], ['bentuk' => 'tim', 'nama_tim' => 'Tim Uji', 'peserta' => [['nama' => 'Sama'], ['nama' => 'sama']]]] as $data) {
            $this->postJson(route('prestasi-sekolah.store'), $this->data($data))->assertUnprocessable();
        }
        $tim = $this->buat(['bentuk' => 'tim', 'nama_tim' => 'Tim Uji', 'peserta' => [['nama' => 'A'], ['nama' => 'B']]]);
        $this->assertSame(2, $tim->peserta()->count());
        $sekolah = $this->buat(['penerima' => 'sekolah', 'bentuk' => 'sekolah', 'peserta' => [], 'kategori' => 'kelembagaan']);
        $this->assertSame(0, $sekolah->peserta()->count());
        $s = $this->siswa();
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['bentuk' => 'tim', 'nama_tim' => 'Tim', 'peserta' => [['siswa_id' => $s->id], ['siswa_id' => $s->id]]]))->assertJsonValidationErrors('peserta');
    }

    public function test_draf_tidak_memerlukan_bukti_tetapi_verifikasi_memerlukan_bukti_dan_konfirmasi(): void
    {
        $p = $this->buat();
        $this->assertNull($p->diverifikasi_pada);
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['status' => 'terverifikasi']))->assertJsonValidationErrors('konfirmasi_prestasi');
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['status' => 'terverifikasi', 'konfirmasi_prestasi' => 1]))->assertJsonValidationErrors('berkas');
        $this->putJson(route('prestasi-sekolah.update', $p), $this->editData($p, $this->verified()))->assertOk();
        $this->assertSame('terverifikasi', $p->fresh()->status);
        $this->assertSame(auth()->id(), $p->fresh()->diverifikasi_oleh_pengguna_id);
        $this->putJson(route('prestasi-sekolah.update', $p), $this->editData($p, ['metode' => 'hapus', 'tautan' => null]))->assertJsonValidationErrors('berkas');
    }

    public function test_idempoten_aktor_versi_server_dan_token_akun_lain_ditolak(): void
    {
        $data = $this->data(['versi' => 99, 'dibuat_oleh_pengguna_id' => 999, 'identitas_hash' => 'palsu']);
        $this->postJson(route('prestasi-sekolah.store'), $data)->assertOk();
        $this->postJson(route('prestasi-sekolah.store'), $data)->assertOk();
        $this->assertDatabaseCount('prestasi_sekolah', 1);
        $this->assertDatabaseCount('riwayat_prestasi_sekolah', 1);
        $p = PrestasiSekolah::firstOrFail();
        $this->assertSame(0, $p->versi);
        $this->assertSame(auth()->id(), $p->dibuat_oleh_pengguna_id);
        $this->assertStringNotContainsString($data['token_pembuatan'], $p->toJson());
        $this->actingAs($this->akun())->postJson(route('prestasi-sekolah.store'), $data)->assertForbidden();
    }

    public function test_prestasi_ganda_termasuk_arsip_dan_urutan_tim_dicegah(): void
    {
        $data = ['bentuk' => 'tim', 'nama_tim' => 'Tim Uji', 'peserta' => [['nama' => 'A'], ['nama' => 'B']], 'status' => 'arsip'];
        $this->buat($data);
        $this->postJson(route('prestasi-sekolah.store'), $this->data(array_replace($data, ['peserta' => array_reverse($data['peserta']), 'status' => 'draf'])))->assertJsonValidationErrors('nama_kegiatan');
        $this->buat(array_replace($data, ['tanggal_prestasi' => '2026-09-02']));
        $this->assertDatabaseCount('prestasi_sekolah', 2);
    }

    public function test_koreksi_optimistis_noop_arsip_revisi_dan_riwayat(): void
    {
        $p = $this->buat($this->verified());
        $stale = $this->editData($p);
        $time = $p->diverifikasi_pada->toIso8601String();
        $this->travel(5)->minutes();
        $this->putJson(route('prestasi-sekolah.update', $p), $stale)->assertOk();
        $this->assertSame(0, $p->fresh()->versi);
        $this->assertSame($time, $p->fresh()->diverifikasi_pada->toIso8601String());
        $this->putJson(route('prestasi-sekolah.update', $p), $this->editData($p, ['capaian' => 'Juara 1 setelah koreksi']))->assertOk();
        $this->assertSame(1, $p->fresh()->versi);
        $this->assertSame('Juara 1 Olimpiade Matematika', $p->riwayat()->reorder()->oldest('id')->first()->snapshot['capaian']);
        $this->putJson(route('prestasi-sekolah.update', $p), $stale)->assertJsonValidationErrors('versi');
        $this->putJson(route('prestasi-sekolah.update', $p), $this->editData($p, ['status' => 'arsip']))->assertOk();
        $this->get(route('prestasi-sekolah.index'))->assertDontSee('Juara 1 setelah koreksi');
        $this->get(route('prestasi-sekolah.index', ['status' => 'arsip']))->assertSee('Juara 1 setelah koreksi');
        $this->putJson(route('prestasi-sekolah.update', $p), $this->editData($p, ['status' => 'draf', 'konfirmasi_prestasi' => null]))->assertOk();
        $this->assertNull($p->fresh()->diverifikasi_pada);
        $this->assertSame(4, $p->riwayat()->count());
    }

    public function test_koreksi_penerima_menyimpan_snapshot_nama_lama(): void
    {
        $p = $this->buat();
        $this->putJson(route('prestasi-sekolah.update', $p), $this->editData($p, ['peserta' => [['nama' => 'Nama diperbaiki', 'kelas' => 'IX.B']]]))->assertOk();
        $this->assertSame('Nama diperbaiki', $p->peserta()->first()->nama);
        $this->assertSame('Siswa manual', $p->riwayat()->reorder()->oldest('id')->first()->snapshot['peserta'][0]['nama']);
    }

    public function test_tahun_pelajaran_dan_tanggal_prestasi_divalidasi(): void
    {
        $tahun = $this->tahun();
        foreach (['2025-06-30', '2026-10-05'] as $date) {
            $this->postJson(route('prestasi-sekolah.store'), $this->data(['tahun_pelajaran_id' => $tahun->id, 'tanggal_prestasi' => $date]))->assertUnprocessable();
        }
        $this->buat(['tahun_pelajaran_id' => $tahun->id, 'tanggal_prestasi' => '2026-07-01']);
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['tanggal_prestasi' => 'bad', 'perolehan' => 'bad', 'kategori' => 'bad']))->assertJsonValidationErrors(['tanggal_prestasi', 'perolehan', 'kategori']);
    }

    public function test_bukti_unggah_privat_penggantian_dan_bukti_lama_tetap_dapat_dibuka(): void
    {
        $p = $this->buat($this->verified(['tautan' => null, 'metode' => 'unggah', 'berkas' => $this->foto()]));
        $old = $p->berkas()->firstOrFail();
        $this->assertDatabaseHas('dokumen_humas', ['id' => $old->dokumen_humas_id, 'kategori' => 'prestasi']);
        $this->get(route('prestasi-sekolah.berkas', [$p, $old]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->putJson(route('prestasi-sekolah.update', $p), $this->editData($p, ['metode' => 'unggah', 'berkas' => $this->foto()]))->assertOk();
        $this->assertNotSame($old->id, $p->fresh()->riwayat_dokumen_humas_id);
        Storage::disk('local')->assertExists($old->lokasi_file);
        $this->get(route('prestasi-sekolah.berkas', [$p, $old]))->assertOk();
        $foreign = $this->dokumen()->riwayat()->firstOrFail();
        $this->get(route('prestasi-sekolah.berkas', [$p, $foreign]))->assertNotFound();
        $this->actingAs($this->akunIzin(['prestasi_sekolah.lihat']))->get(route('prestasi-sekolah.berkas', [$p, $old]))->assertForbidden();
        $this->get(route('prestasi-sekolah.show', $p))->assertOk()->assertDontSee($old->nama_file_asli)->assertDontSee(route('prestasi-sekolah.berkas', [$p, $old]));
    }

    public function test_selection_dokumen_mengunci_versi_dan_memeriksa_aktif_berkas_izin(): void
    {
        $doc = $this->dokumen();
        $old = $doc->riwayat()->firstOrFail();
        $p = $this->buat($this->verified(['tautan' => null, 'metode' => 'dokumen', 'dokumen_humas_id' => $doc->id]));
        $doc->riwayat()->create($old->only(['lokasi_file', 'nama_file_asli', 'tipe_file', 'ukuran_file']) + ['versi' => 2, 'diunggah_pada' => now()]);
        $this->assertSame($old->id, $p->fresh()->riwayat_dokumen_humas_id);
        $doc->update(['status' => 'arsip']);
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['metode' => 'dokumen', 'dokumen_humas_id' => $doc->id]))->assertJsonValidationErrors('dokumen_humas_id');
        $doc->update(['status' => 'aktif']);
        Storage::disk('local')->delete($old->lokasi_file);
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['metode' => 'dokumen', 'dokumen_humas_id' => $doc->id]))->assertJsonValidationErrors('berkas');
        $this->actingAs($this->akunIzin(['prestasi_sekolah.kelola']))->postJson(route('prestasi-sekolah.store'), $this->data(['metode' => 'dokumen', 'dokumen_humas_id' => $doc->id]))->assertForbidden();
    }

    public function test_rollback_dan_idempoten_membersihkan_unggahan_yang_tidak_terpakai(): void
    {
        $data = $this->verified(['tautan' => null, 'metode' => 'unggah']);
        $token = (string) Str::uuid();
        $p = $this->buat($data + ['token_pembuatan' => $token, 'berkas' => $this->foto()]);
        $count = count(Storage::disk('local')->allFiles('dokumen-humas'));
        $this->postJson(route('prestasi-sekolah.store'), $this->data($data + ['token_pembuatan' => $token, 'berkas' => $this->foto()]))->assertOk();
        $this->postJson(route('prestasi-sekolah.store'), $this->data($data + ['berkas' => $this->foto()]))->assertJsonValidationErrors('nama_kegiatan');
        $this->putJson(route('prestasi-sekolah.update', $p), $this->editData($p, ['versi' => 99, 'metode' => 'unggah', 'berkas' => $this->foto()]))->assertJsonValidationErrors('versi');
        $this->assertSame($count, count(Storage::disk('local')->allFiles('dokumen-humas')));
        $this->assertDatabaseCount('dokumen_humas', 1);
        $this->assertDatabaseCount('prestasi_sekolah', 1);
    }

    public function test_pencarian_sumber_dan_dokumen_tidak_mengekspos_kontak(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->siswa(['nama_lengkap' => 'Cari '.$i]);
        }
        $r = $this->getJson(route('prestasi-sekolah.pilihan', ['jenis' => 'siswa', 'q' => 'Cari']))->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('next_page', 2);
        $this->assertSame(['id', 'nama_lengkap'], array_keys($r->json('data.0')));
        $r->assertDontSee('081299999999');
        $this->getJson(route('prestasi-sekolah.pilihan', ['jenis' => 'siswa', 'page' => 2]))->assertJsonCount(1, 'data');
        Pegawai::create(['nama_lengkap' => 'Guru Uji', 'nip' => '001122334455667788', 'no_hp' => '081299999999']);
        $this->getJson(route('prestasi-sekolah.pilihan', ['jenis' => 'pegawai']))->assertJsonCount(1, 'data')->assertDontSee('001122334455667788');
        $this->dokumen();
        $this->getJson(route('prestasi-sekolah.dokumen'))->assertOk()->assertJsonCount(1, 'dokumen')->assertDontSee('lokasi_file');
        $this->getJson(route('prestasi-sekolah.pilihan', ['jenis' => 'bad', 'q' => ['x'], 'page' => 0]))->assertJsonValidationErrors(['jenis', 'q', 'page']);
    }

    public function test_statistik_draf_arsip_tidak_dihitung_tim_satu_prestasi_per_siswa(): void
    {
        $s1 = $this->siswa();
        $s2 = $this->siswa();
        $this->buat($this->verified(['peserta' => [['siswa_id' => $s1->id]]]));
        $this->buat($this->verified(['nama_kegiatan' => 'Lomba tim', 'bentuk' => 'tim', 'nama_tim' => 'Tim Uji', 'peserta' => [['siswa_id' => $s1->id], ['siswa_id' => $s2->id]]]));
        $this->buat($this->verified(['penerima' => 'sekolah', 'bentuk' => 'sekolah', 'kategori' => 'kelembagaan', 'peserta' => []]));
        $this->buat(['capaian' => 'Draf prestasi']);
        $this->buat($this->verified(['capaian' => 'Prestasi arsip', 'status' => 'arsip']));
        $r = $this->get(route('prestasi-sekolah.index', ['tab' => 'statistik']))->assertOk();
        $this->assertSame(['total' => 4, 'terverifikasi' => 3, 'siswa' => 2, 'sekolah' => 1], $r->viewData('statistik'));
        $this->assertSame(3, (int) $r->viewData('perTingkat')['nasional']);
        $this->assertSame(2, (int) $r->viewData('siswaTerbanyak')->first()->jumlah);
        $this->get(route('prestasi-sekolah.index', ['penerima' => 'sekolah']))->assertViewHas('statistik', fn ($s) => $s['total'] === 1 && $s['siswa'] === 0);
        $this->get(route('prestasi-sekolah.index', ['status' => 'draf']))->assertViewHas('statistik', fn ($s) => $s['total'] === 1 && $s['terverifikasi'] === 0);
        $this->get(route('prestasi-sekolah.index', ['kata_kunci' => $s1->nama_lengkap]))->assertViewHas('statistik', fn ($s) => $s['total'] === 2);
        $this->get(route('prestasi-sekolah.index', ['tahun' => 2025]))->assertViewHas('statistik', fn ($s) => $s['total'] === 0);
    }

    public function test_ekspor_cetak_filter_status_teks_excel_aman_tanpa_file_privat(): void
    {
        $p = $this->buat($this->verified(['nama_kegiatan' => '=HYPERLINK("https://example.test")', 'peserta' => [['nama' => '000123 Nama Uji']]]));
        $this->buat(['capaian' => 'Draf tidak terpilih']);
        $r = $this->post(route('prestasi-sekolah.export'), ['status' => 'terverifikasi'])->assertOk();
        $path = $r->baseResponse->getFile()->getPathname();
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $this->assertNotFalse(simplexml_load_string($zip->getFromIndex($i)));
            }
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertStringContainsString('HYPERLINK', $xml);
            $this->assertStringContainsString('000123 Nama Uji', $xml);
            $this->assertStringNotContainsString('<f>', $xml);
            $this->assertStringNotContainsString('Draf tidak terpilih', $xml);
            $this->assertStringNotContainsString('dokumen-humas/', $xml);
            $this->assertStringContainsString('A5:T6', $xml);
        } finally {
            @unlink($path);
        }
        $this->get(route('prestasi-sekolah.cetak', ['status' => 'terverifikasi']))->assertOk()->assertSee('000123 Nama Uji')->assertDontSee('Draf tidak terpilih')->assertSee('size:A4 landscape', false);
        $this->actingAs($this->akunIzin(['prestasi_sekolah.ekspor']))->post(route('prestasi-sekolah.export'))->assertForbidden();
    }

    public function test_tautan_berkredensial_dan_non_http_ditolak_tanpa_flash_rahasia(): void
    {
        foreach (['https://user:secret@example.test/bukti', 'https://example.test?token=secret', 'javascript:alert(1)'] as $url) {
            $this->postJson(route('prestasi-sekolah.store'), $this->data(['tautan' => $url]))->assertJsonValidationErrors('tautan');
        }
        $this->post(route('prestasi-sekolah.store'), $this->data(['nama_kegiatan' => '', 'password' => 'secret', 'tautan' => 'https://example.test?token=secret']))->assertSessionHasErrors('tautan');
        $this->assertStringNotContainsString('secret', json_encode(session()->getOldInput()));
    }

    public function test_validasi_berkas_izin_unggah_dan_bukti_hilang(): void
    {
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['metode' => 'unggah', 'berkas' => UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf')]))->assertJsonValidationErrors('berkas');
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['metode' => 'unggah', 'berkas' => UploadedFile::fake()->create('kode.php', 1, 'text/plain')]))->assertJsonValidationErrors('berkas');
        $p = $this->buat($this->verified(['tautan' => null, 'metode' => 'unggah', 'berkas' => $this->foto()]));
        Storage::disk('local')->delete($p->berkas()->value('lokasi_file'));
        $this->putJson(route('prestasi-sekolah.update', $p), $this->editData($p))->assertJsonValidationErrors('berkas');
        $this->get(route('prestasi-sekolah.berkas', [$p, $p->riwayat_dokumen_humas_id]))->assertNotFound();
        $this->actingAs($this->akunIzin(['prestasi_sekolah.kelola']))->postJson(route('prestasi-sekolah.store'), $this->data(['metode' => 'unggah', 'berkas' => $this->foto()]))->assertForbidden();
    }

    public function test_akun_ortu_siswa_nonaktif_meski_diberi_izin_ditolak(): void
    {
        $p = $this->buat();
        $ortu = $this->akun();
        OrangTuaWali::create(['pengguna_id' => $ortu->id, 'nama_lengkap' => 'Wali Uji']);
        $student = $this->akun();
        $student->update(['siswa_id' => $this->siswa()->id]);
        $inactive = $this->akun();
        $inactive->update(['aktif' => false]);
        foreach ([$ortu, $student, $inactive] as $user) {
            $this->actingAs($user)->get(route('prestasi-sekolah.index'))->assertForbidden();
            $this->get(route('prestasi-sekolah.show', $p))->assertForbidden();
            $this->getJson(route('prestasi-sekolah.pilihan', ['jenis' => 'siswa']))->assertForbidden();
            $this->postJson(route('prestasi-sekolah.store'), $this->data())->assertForbidden();
            $this->post(route('prestasi-sekolah.export'))->assertForbidden();
        }
    }

    public function test_validasi_payload_peserta_versi_dan_filter(): void
    {
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['peserta' => [['siswa_id' => 'bad', 'nomor_wa' => 'secret']]]))->assertJsonValidationErrors(['peserta.0', 'peserta.0.siswa_id']);
        $this->postJson(route('prestasi-sekolah.store'), $this->data(['peserta' => 'bad', 'token_pembuatan' => 'bad']))->assertJsonValidationErrors(['peserta', 'token_pembuatan']);
        $p = $this->buat();
        $this->putJson(route('prestasi-sekolah.update', $p), $this->editData($p, ['versi' => null, 'catatan_perubahan' => '']))->assertJsonValidationErrors(['versi', 'catatan_perubahan']);
        $this->getJson(route('prestasi-sekolah.index', ['tahun' => 2027, 'status' => 'bad', 'kata_kunci' => ['x']]))->assertJsonValidationErrors(['tahun', 'status', 'kata_kunci']);
    }

    public function test_halaman_xss_fixture_ui_dan_bukti(): void
    {
        $this->capture('empty', $this->get(route('prestasi-sekolah.index'))->assertOk());
        $s = $this->siswa(['nama_lengkap' => 'Siswa NUSA Berprestasi']);
        $this->tahun();
        $doc = $this->dokumen();
        $p = $this->buat($this->verified(['capaian' => 'JUARA 1 OLIMPIADE MATEMATIKA TINGKAT NASIONAL', 'nama_kegiatan' => 'Kompetisi Pelajar Nasional dengan Nama Kegiatan Panjang', 'metode' => 'dokumen', 'dokumen_humas_id' => $doc->id, 'peserta' => [['siswa_id' => $s->id]], 'pembina' => 'Guru Pembina Sekolah']));
        $this->buat(['capaian' => '<script>alert(1)</script> Prestasi', 'penerima' => 'sekolah', 'bentuk' => 'sekolah', 'peserta' => []]);
        $this->capture('index', $this->get(route('prestasi-sekolah.index'))->assertOk()->assertDontSee('<script>alert(1)</script>', false));
        $this->capture('statistik', $this->get(route('prestasi-sekolah.index', ['tab' => 'statistik']))->assertOk());
        $this->capture('form', $this->get(route('prestasi-sekolah.create'))->assertOk());
        $this->capture('edit', $this->get(route('prestasi-sekolah.edit', $p))->assertOk());
        $this->capture('show', $this->get(route('prestasi-sekolah.show', $p))->assertOk());
        $this->capture('cetak', $this->get(route('prestasi-sekolah.cetak'))->assertOk());
        $this->capture('pilihan', $this->getJson(route('prestasi-sekolah.pilihan', ['jenis' => 'siswa']))->assertOk(), 'json');
        $this->actingAs($this->akunIzin(['prestasi_sekolah.lihat']));
        $this->capture('readonly', $this->get(route('prestasi-sekolah.show', $p))->assertOk()->assertDontSee('Edit prestasi')->assertDontSee($doc->nama_file_asli));
    }

    private function akun(string $role = 'wakil_pimpinan_humas'): Pengguna
    {
        $p = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'prestasi.'.Str::uuid(), 'kata_sandi' => 'UjiPrestasi123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function akunIzin(array $izin): Pengguna
    {
        $role = Peran::create(['kode' => 'prestasi_'.Str::random(10), 'nama' => 'Prestasi terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $p = $this->akun('pegawai');
        $p->daftarPeran()->attach($role);

        return $p;
    }

    private function data(array $data = []): array
    {
        return array_replace(['token_pembuatan' => (string) Str::uuid(), 'nama_kegiatan' => 'Olimpiade Pelajar', 'cabang' => 'Matematika', 'kategori' => 'akademik', 'tingkat' => 'nasional', 'perolehan' => 'juara_1', 'capaian' => 'Juara 1 Olimpiade Matematika', 'tanggal_prestasi' => '2026-09-01', 'penerima' => 'siswa', 'bentuk' => 'individu', 'nama_tim' => null, 'peserta' => [['nama' => 'Siswa manual', 'kelas' => 'IX.A']], 'status' => 'draf', 'metode' => 'tanpa', 'tautan' => null], $data);
    }

    private function verified(array $data = []): array
    {
        return array_replace(['status' => 'terverifikasi', 'konfirmasi_prestasi' => 1, 'tautan' => 'https://pengumuman.test/prestasi'], $data);
    }

    private function buat(array $data = []): PrestasiSekolah
    {
        $this->postJson(route('prestasi-sekolah.store'), $this->data($data))->assertOk();

        return PrestasiSekolah::latest('id')->firstOrFail();
    }

    private function editData(PrestasiSekolah $p, array $data = []): array
    {
        $p = $p->fresh();

        return array_replace($p->only(PrestasiSekolah::KOLOM), ['tanggal_prestasi' => $p->tanggal_prestasi->format('Y-m-d'), 'peserta' => $p->peserta->map(fn ($s) => $s->only(['siswa_id', 'pegawai_id', 'nama', 'kelas']))->all(), 'metode' => 'tetap', 'versi' => $p->versi, 'catatan_perubahan' => 'Koreksi catatan prestasi.', 'konfirmasi_prestasi' => $p->status === 'terverifikasi' ? 1 : null], $data);
    }

    private function siswa(array $data = []): Siswa
    {
        return Siswa::create(array_replace(['nama_lengkap' => 'Siswa '.(Siswa::count() + 1), 'nisn' => str_pad((string) (Siswa::count() + 100), 10, '0', STR_PAD_LEFT), 'aktif' => true, 'nomor_wa_ayah' => '081299999999'], $data));
    }

    private function tahun(): TahunPelajaran
    {
        return TahunPelajaran::firstOrCreate(['nama' => '2026/2027'], ['tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bukti-prestasi.jpg', file_get_contents(public_path('images/login-sekolah.jpg')));
    }

    private function dokumen(): DokumenHumas
    {
        $file = $this->foto();
        $meta = ['lokasi_file' => $file->storeAs('dokumen-humas', Str::uuid().'.jpg', 'local'), 'nama_file_asli' => 'bukti-prestasi.jpg', 'tipe_file' => 'image/jpeg', 'ukuran_file' => $file->getSize()];
        $doc = DokumenHumas::create($meta + ['judul' => 'Bukti prestasi sekolah', 'kategori' => 'prestasi', 'status' => 'aktif']);
        $doc->riwayat()->create($meta + ['versi' => 1, 'diunggah_pada' => now()]);

        return $doc;
    }

    private function capture(string $name, TestResponse $r, string $extension = 'html'): void
    {
        if (getenv('NUSA_CAPTURE_PRESTASI_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/prestasi-sekolah');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        } file_put_contents($folder.'/'.$name.'.'.$extension, $r->getContent());
    }
}
