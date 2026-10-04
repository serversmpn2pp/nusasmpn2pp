<?php

namespace Tests\Feature;

use App\Models\AlumniHumas;
use App\Models\AnggotaKelas;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\OrangTuaWali;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

class AlumniHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 4)->setTime(10, 0));
        $this->actingAs($this->akun());
    }

    public function test_menu_izin_humas_pimpinan_admin_dan_role_lain(): void
    {
        $alumni = $this->alumni();
        $this->get(route('alumni-humas.index'))->assertOk()->assertSee('Tambah alumni')->assertSee('Database Alumni');
        $this->actingAs($this->akun('pimpinan'))->get(route('alumni-humas.index'))->assertOk()->assertDontSee('Tambah alumni')->assertDontSee('Ekspor Excel sesuai filter');
        $this->get(route('alumni-humas.show', $alumni))->assertOk()->assertDontSee('Edit alumni');
        $this->get(route('alumni-humas.cetak'))->assertOk();
        $this->get(route('alumni-humas.create'))->assertForbidden();
        $this->get(route('alumni-humas.siswa'))->assertForbidden();
        $this->post(route('alumni-humas.export'))->assertForbidden();
        $this->put(route('alumni-humas.update', $alumni), $this->editData($alumni))->assertForbidden();
        foreach (['pegawai', 'guru_mapel', 'siswa', 'orang_tua'] as $role) {
            $this->actingAs($this->akun($role))->get(route('alumni-humas.index'))->assertForbidden();
            $this->get(route('alumni-humas.show', $alumni))->assertForbidden();
            $this->postJson(route('alumni-humas.store'), $this->data())->assertForbidden();
        }
        $this->actingAs($this->akun('administrator'))->get(route('alumni-humas.index'))->assertOk();
        auth()->forgetGuards();
        $this->get(route('alumni-humas.index'))->assertRedirect(route('login'));
    }

    public function test_tidak_otomatis_menjadikan_siswa_nonaktif_sebagai_alumni(): void
    {
        $this->siswa(['aktif' => false]);
        $this->get(route('alumni-humas.index'))->assertOk()->assertViewHas('statistik', fn ($s) => $s['total'] === 0);
        $this->assertDatabaseCount('alumni_humas', 0);
    }

    public function test_create_idempoten_aktor_dan_versi_ditentukan_server(): void
    {
        $data = $this->data(['versi' => 999, 'dibuat_oleh_pengguna_id' => 999, 'diubah_oleh_pengguna_id' => 999]);
        $this->post(route('alumni-humas.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('alumni-humas.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('alumni_humas', 1);
        $this->assertDatabaseCount('riwayat_alumni_humas', 1);
        $a = AlumniHumas::firstOrFail();
        $this->assertSame(auth()->id(), $a->dibuat_oleh_pengguna_id);
        $this->assertSame(0, $a->versi);
        $this->assertNotNull($a->kelulusan_dicatat_pada);
        $this->assertStringNotContainsString($data['token_pembuatan'], $a->toJson());
        $this->actingAs($this->akun())->post(route('alumni-humas.store'), $data)->assertForbidden();
    }

    public function test_sumber_siswa_dan_kelas_dari_server_tanpa_mengubah_siswa_akun(): void
    {
        $s = $this->siswa(['nomor_wa_ayah' => '081299999999', 'aktif' => true]);
        $kelas = $this->anggota($s);
        $akun = $this->akun('siswa');
        $akun->update(['siswa_id' => $s->id]);
        $awal = [$s->fresh()->getAttributes(), $kelas->fresh()->getAttributes(), $akun->fresh()->getAttributes()];
        $a = $this->alumni(['siswa_id' => $s->id, 'anggota_kelas_id' => $kelas->id, 'nama_lengkap' => 'Palsu', 'nisn' => '9999999999', 'jenis_kelamin' => 'P', 'kelas_terakhir' => 'Palsu']);
        $this->assertSame($s->nama_lengkap, $a->nama_lengkap);
        $this->assertSame($s->nisn, $a->nisn);
        $this->assertSame($s->jenis_kelamin, $a->jenis_kelamin);
        $this->assertSame('IX.A', $a->kelas_terakhir);
        $this->assertNull($a->nomor_wa);
        $this->assertSame($awal, [$s->fresh()->getAttributes(), $kelas->fresh()->getAttributes(), $akun->fresh()->getAttributes()]);
    }

    public function test_kelas_harus_milik_siswa_kelas_ix_dan_tahun_lulus_sesuai(): void
    {
        $s = $this->siswa();
        $asing = $this->anggota($this->siswa());
        $this->postJson(route('alumni-humas.store'), $this->data(['siswa_id' => $s->id, 'anggota_kelas_id' => $asing->id]))->assertJsonValidationErrors('anggota_kelas_id');
        $bukanIX = $this->anggota($this->siswa(), 8);
        $this->postJson(route('alumni-humas.store'), $this->data(['siswa_id' => $bukanIX->siswa_id, 'anggota_kelas_id' => $bukanIX->id]))->assertJsonValidationErrors('anggota_kelas_id');
        $kelas = $this->anggota($s);
        $this->postJson(route('alumni-humas.store'), $this->data(['siswa_id' => $s->id, 'anggota_kelas_id' => $kelas->id, 'tahun_lulus' => 2025]))->assertJsonValidationErrors('tahun_lulus');
        $this->postJson(route('alumni-humas.store'), $this->data(['anggota_kelas_id' => $kelas->id]))->assertJsonValidationErrors('anggota_kelas_id');
        $this->assertDatabaseCount('alumni_humas', 0);
    }

    public function test_konfirmasi_kelulusan_tanggal_dan_tahun_masuk(): void
    {
        foreach ([['konfirmasi_lulus' => null], ['konfirmasi_lulus' => 0], ['tahun_lulus' => 2027], ['tahun_masuk' => 2026, 'tahun_lulus' => 2025], ['tanggal_lulus' => '2025-06-10'], ['tanggal_lulus' => '2026-10-05']] as $data) {
            $this->postJson(route('alumni-humas.store'), $this->data($data))->assertUnprocessable();
        }
        $this->alumni(['tahun_masuk' => 2023, 'tanggal_lulus' => '2026-06-10']);
        $this->assertDatabaseCount('alumni_humas', 1);
    }

    public function test_nisn_manual_yang_sudah_ada_harus_memilih_siswa_nusa(): void
    {
        $s = $this->siswa();
        $this->postJson(route('alumni-humas.store'), $this->data(['nisn' => $s->nisn]))->assertJsonValidationErrors('nisn');
        $this->alumni(['siswa_id' => $s->id]);
        $this->assertDatabaseCount('alumni_humas', 1);
    }

    public function test_identitas_ganda_termasuk_arsip_ditolak(): void
    {
        $s = $this->siswa();
        $a = $this->alumni(['siswa_id' => $s->id, 'status' => 'arsip']);
        $this->postJson(route('alumni-humas.store'), $this->data(['siswa_id' => $s->id]))->assertJsonValidationErrors('nisn');
        $manual = $this->alumni(['nisn' => '0012345678', 'nis' => '00120', 'status' => 'arsip']);
        foreach ([['nisn' => $manual->nisn], ['nis' => $manual->nis]] as $data) {
            $this->postJson(route('alumni-humas.store'), $this->data($data))->assertJsonValidationErrors('nisn');
        }
        $this->alumni(['nis' => $manual->nis, 'tahun_lulus' => 2025]);
        $this->assertDatabaseCount('alumni_humas', 3);
        $lain = $this->alumni();
        $this->putJson(route('alumni-humas.update', $lain), $this->editData($lain, ['nisn' => $manual->nisn]))->assertJsonValidationErrors('nisn');
    }

    public function test_penelusuran_memerlukan_data_yang_tepat_dan_membersihkan_data_lama(): void
    {
        $this->postJson(route('alumni-humas.store'), $this->data(['status_penelusuran' => 'melanjutkan']))->assertJsonValidationErrors(['jenis_sekolah', 'nama_sekolah', 'tanggal_penelusuran']);
        $this->postJson(route('alumni-humas.store'), $this->data(['status_penelusuran' => 'tidak_melanjutkan']))->assertJsonValidationErrors(['tanggal_penelusuran', 'catatan_penelusuran']);
        $a = $this->alumni($this->lanjut());
        $this->put(route('alumni-humas.update', $a), $this->editData($a, ['status_penelusuran' => 'belum_terdata']))->assertSessionHasNoErrors();
        foreach (['jenis_sekolah', 'nama_sekolah', 'kota_sekolah', 'jurusan', 'tanggal_penelusuran'] as $key) {
            $this->assertNull($a->fresh()->$key);
        }
        $this->put(route('alumni-humas.update', $a), $this->editData($a, ['status_penelusuran' => 'tidak_melanjutkan', 'tanggal_penelusuran' => '2026-10-01', 'catatan_penelusuran' => 'Kondisi keluarga telah dikonfirmasi.']))->assertSessionHasNoErrors();
        $this->assertSame('tidak_melanjutkan', $a->fresh()->status_penelusuran);
    }

    public function test_tanggal_penelusuran_tidak_sebelum_kelulusan_atau_masa_depan(): void
    {
        foreach (['2025-12-31', '2026-06-01', '2026-10-05'] as $date) {
            $this->postJson(route('alumni-humas.store'), $this->data($this->lanjut(['tanggal_lulus' => '2026-06-10', 'tanggal_penelusuran' => $date])))->assertJsonValidationErrors('tanggal_penelusuran');
        }
        $this->alumni($this->lanjut(['tanggal_lulus' => '2026-06-10', 'tanggal_penelusuran' => '2026-06-10']));
    }

    public function test_kontak_dan_riwayat_privat_terenkripsi_dan_tidak_bocor_ke_pimpinan(): void
    {
        $a = $this->alumni(['nomor_wa' => '+62 812-3456-7890', 'email' => 'alumni.private@example.test', 'catatan_penelusuran' => 'Catatan sangat privat.']);
        $this->assertSame('+6281234567890', $a->nomor_wa);
        $raw = DB::table('alumni_humas')->find($a->id);
        foreach (AlumniHumas::PRIVAT as $key) {
            $this->assertStringNotContainsString($a->$key, $raw->$key);
        }
        $history = $a->riwayat()->firstOrFail();
        $this->assertSame($a->email, $history->snapshot_privat['email']);
        $this->assertStringNotContainsString($a->email, $history->getRawOriginal('snapshot_privat'));
        $this->assertStringNotContainsString($a->email, $history->toJson());
        $this->assertStringNotContainsString($a->email, $a->toJson());
        $this->put(route('alumni-humas.update', $a), $this->editData($a, ['nama_lengkap' => 'Nama diperbaiki', 'catatan_perubahan' => 'Alasan perubahan privat.']))->assertSessionHasNoErrors();
        $r = $a->riwayat()->firstOrFail();
        $this->assertStringNotContainsString('Alasan perubahan privat.', $r->getRawOriginal('catatan_perubahan'));
        $this->get(route('alumni-humas.show', $a))->assertOk()->assertSee($a->email)->assertSee('Alasan perubahan privat.');
        $this->actingAs($this->akun('pimpinan'));
        foreach (['index', 'show', 'cetak'] as $action) {
            $response = $this->get(route('alumni-humas.'.$action, $action === 'show' ? $a : []))->assertOk();
            foreach ([$a->nomor_wa, $a->email, $a->catatan_penelusuran, 'Alasan perubahan privat.'] as $secret) {
                $response->assertDontSee($secret);
            }
        }
    }

    public function test_koreksi_optimistis_arsip_pemulihan_dan_snapshot(): void
    {
        $a = $this->alumni(['nama_lengkap' => 'Nama lama']);
        $stale = $this->editData($a);
        $this->put(route('alumni-humas.update', $a), $stale)->assertSessionHasNoErrors();
        $this->assertSame(0, $a->fresh()->versi);
        $this->put(route('alumni-humas.update', $a), $this->editData($a, ['status' => 'arsip', 'nama_lengkap' => 'Nama koreksi']))->assertSessionHasNoErrors();
        $this->assertSame(1, $a->fresh()->versi);
        $this->putJson(route('alumni-humas.update', $a), $stale)->assertJsonValidationErrors('versi');
        $this->assertSame('Nama lama', $a->riwayat()->reorder()->oldest('id')->first()->snapshot['nama_lengkap']);
        $this->get(route('alumni-humas.index'))->assertDontSee('Nama koreksi');
        $this->get(route('alumni-humas.index', ['status' => 'arsip']))->assertSee('Nama koreksi');
        $this->put(route('alumni-humas.update', $a), $this->editData($a, ['status' => 'aktif']))->assertSessionHasNoErrors();
        $this->assertSame(3, $a->riwayat()->count());
        $this->assertSame(2, $a->fresh()->versi);
    }

    public function test_sumber_bisa_dilepas_dengan_alasan_tanpa_mengubah_siswa(): void
    {
        $s = $this->siswa();
        $a = $this->alumni(['siswa_id' => $s->id, 'anggota_kelas_id' => $this->anggota($s)->id]);
        $data = $this->editData($a, ['siswa_id' => null, 'nisn' => null]);
        unset($data['anggota_kelas_id']);
        $this->put(route('alumni-humas.update', $a), $data)->assertSessionHasNoErrors();
        $this->assertNull($a->fresh()->siswa_id);
        $this->assertNull($a->fresh()->anggota_kelas_id);
        $this->assertDatabaseHas('siswa', ['id' => $s->id, 'aktif' => true]);
        $this->assertSame($s->id, $a->riwayat()->reorder()->oldest('id')->first()->snapshot['siswa_id']);
    }

    public function test_pencarian_siswa_paginasi_tanpa_data_orang_tua_dan_tanpa_n_plus_one(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->anggota($this->siswa(['nama_lengkap' => 'Cari siswa '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'nomor_wa_ayah' => '081299999999', 'nik' => str_pad((string) $i, 16, '0', STR_PAD_LEFT)]));
        }
        DB::enableQueryLog();
        $r = $this->getJson(route('alumni-humas.siswa', ['q' => 'Cari siswa']))->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('next_page', 2);
        $this->assertLessThan(25, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->assertSame(['id', 'nama_lengkap', 'nis', 'nisn', 'jenis_kelamin', 'kelas'], array_keys($r->json('data.0')));
        $r->assertDontSee('081299999999')->assertDontSee('0011223344556677');
        $this->getJson(route('alumni-humas.siswa', ['q' => 'Cari siswa', 'page' => 2]))->assertJsonCount(1, 'data')->assertJsonPath('next_page', null);
        $s = Siswa::firstOrFail();
        $a = $this->alumni(['siswa_id' => $s->id, 'status' => 'arsip']);
        $this->getJson(route('alumni-humas.siswa', ['q' => $s->nisn]))->assertJsonCount(0, 'data');
        $this->getJson(route('alumni-humas.siswa', ['q' => $s->nisn, 'alumni_id' => $a->id]))->assertJsonCount(1, 'data');
        $this->getJson(route('alumni-humas.siswa', ['q' => ['bad'], 'page' => 0]))->assertJsonValidationErrors(['q', 'page']);
    }

    public function test_statistik_filter_angkatan_jumlah_penelusuran_dan_sekolah_case_insensitive(): void
    {
        $this->alumni($this->lanjut(['nama_lengkap' => 'Tujuan pertama']));
        $this->alumni($this->lanjut(['nama_sekolah' => 'sma negeri 1 padang panjang', 'kota_sekolah' => 'padang panjang']));
        $this->alumni($this->lanjut(['kota_sekolah' => 'Kota berbeda']));
        $this->alumni(['nama_lengkap' => 'Belum terlacak']);
        $this->alumni(['status_penelusuran' => 'tidak_melanjutkan', 'tanggal_penelusuran' => '2026-09-01', 'catatan_penelusuran' => 'Telah dikonfirmasi.']);
        $this->alumni(['tahun_lulus' => 2025]);
        $this->alumni(['status' => 'arsip']);
        $r = $this->get(route('alumni-humas.index', ['tab' => 'statistik', 'tahun_lulus' => 2026]))->assertOk();
        $this->assertSame(['total' => 5, 'melanjutkan' => 3, 'tidak_melanjutkan' => 1, 'belum_terdata' => 1, 'persen_terdata' => 80.0], $r->viewData('statistik'));
        $this->assertSame(3, (int) $r->viewData('perJenis')['sma']);
        $this->assertSame(2, $r->viewData('sekolahTerbanyak')->count());
        $this->assertSame(2, (int) $r->viewData('sekolahTerbanyak')->first()->jumlah);
        $this->assertSame(1, $r->viewData('perAngkatan')->count());
        $this->get(route('alumni-humas.index', ['jenis_sekolah' => 'sma', 'tahun_lulus' => 2026]))->assertViewHas('statistik', fn ($s) => $s['total'] === 3 && $s['belum_terdata'] === 0);
        $this->get(route('alumni-humas.index', ['status' => 'semua']))->assertViewHas('statistik', fn ($s) => $s['total'] === 7);
        $this->get(route('alumni-humas.index', ['kata_kunci' => 'tidak ada']))->assertViewHas('statistik', fn ($s) => $s['total'] === 0 && $s['persen_terdata'] === 0);
    }

    public function test_ekspor_filter_teks_aman_nisn_nol_awal_dan_optin_kontak(): void
    {
        $a = $this->alumni(['nama_lengkap' => '=HYPERLINK("https://example.test")', 'nisn' => '0012345678', 'nomor_wa' => '081234567890', 'email' => 'alumni.private@example.test', 'catatan_penelusuran' => 'Jangan diekspor catatan privat.']);
        $this->alumni(['nama_lengkap' => 'Alumni tahun berbeda', 'tahun_lulus' => 2025]);
        $xml = $this->excel(['tahun_lulus' => 2026]);
        $this->assertStringContainsString('0012345678', $xml);
        $this->assertStringContainsString('t="inlineStr"', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
        $this->assertStringNotContainsString($a->nomor_wa, $xml);
        $this->assertStringNotContainsString($a->email, $xml);
        $this->assertStringNotContainsString('Alumni tahun berbeda', $xml);
        $this->assertStringContainsString('ref="A5:Q6"', $xml);
        $privat = $this->excel(['tahun_lulus' => 2026, 'sertakan_kontak' => 1]);
        $this->assertStringContainsString($a->nomor_wa, $privat);
        $this->assertStringContainsString($a->email, $privat);
        $this->assertStringNotContainsString($a->catatan_penelusuran, $privat);
        $this->assertStringContainsString('ref="A5:S6"', $privat);
        $this->assertNotFalse(simplexml_load_string($privat));
    }

    public function test_ekspor_memerlukan_izin_baca_dan_optin_memerlukan_kelola(): void
    {
        $this->alumni();
        $this->actingAs($this->akunIzin(['alumni_humas.ekspor']))->post(route('alumni-humas.export'))->assertForbidden();
        $this->actingAs($this->akunIzin(['alumni_humas.lihat', 'alumni_humas.ekspor']));
        $this->excel();
        $this->post(route('alumni-humas.export'), ['sertakan_kontak' => 1])->assertForbidden();
        $this->actingAs($this->akunIzin(['alumni_humas.kelola']))->post(route('alumni-humas.export'))->assertForbidden();
    }

    public function test_cetak_filter_dan_privasi_beserta_logo(): void
    {
        $a = $this->alumni(['nama_lengkap' => 'Alumni cetak', 'nomor_wa' => '081234567890']);
        $this->alumni(['nama_lengkap' => 'Alumni di luar filter', 'tahun_lulus' => 2025]);
        $this->get(route('alumni-humas.cetak', ['tahun_lulus' => 2026]))->assertOk()->assertSee('Alumni cetak')->assertDontSee('Alumni di luar filter')->assertDontSee($a->nomor_wa)->assertSee('size:A4 landscape', false)->assertSee('images/kartu-pelajar/logo-smpn2pp.png')->assertSee('images/logo-padang-panjang.png');
    }

    public function test_akun_orang_tua_siswa_nonaktif_meski_salah_diberi_izin(): void
    {
        $a = $this->alumni();
        $ortu = $this->akun();
        OrangTuaWali::create(['pengguna_id' => $ortu->id, 'nama_lengkap' => 'Orang tua uji']);
        $siswa = $this->akun();
        $siswa->update(['siswa_id' => $this->siswa()->id]);
        $nonaktif = $this->akun();
        $nonaktif->update(['aktif' => false]);
        foreach ([$ortu, $siswa, $nonaktif] as $p) {
            $this->actingAs($p)->get(route('alumni-humas.index'))->assertForbidden();
            $this->get(route('alumni-humas.show', $a))->assertForbidden();
            $this->get(route('alumni-humas.siswa'))->assertForbidden();
            $this->postJson(route('alumni-humas.store'), $this->data())->assertForbidden();
            $this->post(route('alumni-humas.export'))->assertForbidden();
        }
    }

    public function test_validasi_jenis_data_versi_nisn_nomor_wa_dan_filter(): void
    {
        $this->postJson(route('alumni-humas.store'), $this->data(['nisn' => '123', 'nomor_wa' => 'abc', 'email' => 'invalid', 'nama_lengkap' => [], 'siswa_id' => 'bad']))->assertJsonValidationErrors('siswa_id');
        $this->postJson(route('alumni-humas.store'), $this->data(['nisn' => '123', 'nomor_wa' => 'abc', 'email' => 'invalid', 'nama_lengkap' => [], 'token_pembuatan' => 'bad']))->assertJsonValidationErrors(['nisn', 'nomor_wa', 'email', 'nama_lengkap', 'token_pembuatan']);
        $a = $this->alumni();
        $this->putJson(route('alumni-humas.update', $a), $this->editData($a, ['versi' => null, 'catatan_perubahan' => '']))->assertJsonValidationErrors(['versi', 'catatan_perubahan']);
        $this->getJson(route('alumni-humas.index', ['status' => 'bad', 'tahun_lulus' => 2027, 'kata_kunci' => ['bad']]))->assertJsonValidationErrors(['status', 'tahun_lulus', 'kata_kunci']);
    }

    public function test_halaman_lengkap_escape_xss_dan_fixture_ui(): void
    {
        $this->capture('empty', $this->get(route('alumni-humas.index'))->assertOk());
        $a = $this->alumni($this->lanjut(['nama_lengkap' => 'ALUMNI DENGAN NAMA PANJANG UNTUK PENGUJIAN TAMPILAN', 'nisn' => '0012345678', 'nomor_wa' => '081234567890', 'email' => 'alumni.private@example.test', 'catatan_penelusuran' => 'Catatan khusus privat.']));
        $this->alumni(['nama_lengkap' => '<script>alert(1)</script> Alumni']);
        $this->alumni(['nama_lengkap' => 'Belum diketahui sekolahnya', 'tahun_lulus' => 2025]);
        $this->alumni(['nama_lengkap' => 'Tidak melanjutkan pendidikan', 'status_penelusuran' => 'tidak_melanjutkan', 'tanggal_penelusuran' => '2026-09-10', 'catatan_penelusuran' => 'Telah diverifikasi.']);
        $s = $this->siswa(['nama_lengkap' => 'Siswa NUSA untuk dipilih']);
        $member = $this->anggota($s);
        $this->capture('form', $this->get(route('alumni-humas.create'))->assertOk());
        $this->capture('index', $this->get(route('alumni-humas.index'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee(e('<script>alert(1)</script> Alumni'), false));
        $this->capture('statistik', $this->get(route('alumni-humas.index', ['tab' => 'statistik']))->assertOk());
        $this->capture('show', $this->get(route('alumni-humas.show', $a))->assertOk());
        $this->capture('edit', $this->get(route('alumni-humas.edit', $a))->assertOk());
        $this->capture('cetak', $this->get(route('alumni-humas.cetak'))->assertOk());
        $this->capture('siswa', $this->getJson(route('alumni-humas.siswa'))->assertOk(), 'json');
        $linked = $this->alumni(['siswa_id' => $s->id, 'anggota_kelas_id' => $member->id]);
        $this->capture('linked', $this->get(route('alumni-humas.edit', $linked))->assertOk());
        $this->actingAs($this->akun('pimpinan'));
        $this->capture('readonly', $this->get(route('alumni-humas.show', $a))->assertOk()->assertDontSee($a->nomor_wa));
    }

    private function akun(string $role = 'wakil_pimpinan_humas'): Pengguna
    {
        $p = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'alumni.'.Str::uuid(), 'kata_sandi' => 'UjiAlumni123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function akunIzin(array $izin): Pengguna
    {
        $role = Peran::create(['kode' => 'alumni_'.Str::random(10), 'nama' => 'Alumni terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $p = $this->akun('pegawai');
        $p->daftarPeran()->attach($role);

        return $p;
    }

    private function data(array $data = []): array
    {
        return array_replace(['token_pembuatan' => (string) Str::uuid(), 'siswa_id' => null, 'anggota_kelas_id' => null, 'nama_lengkap' => 'Alumni manual', 'nis' => null, 'nisn' => null, 'jenis_kelamin' => 'L', 'tahun_masuk' => null, 'tahun_lulus' => 2026, 'tanggal_lulus' => null, 'kelas_terakhir' => 'IX.A', 'status' => 'aktif', 'status_penelusuran' => 'belum_terdata', 'jenis_sekolah' => null, 'nama_sekolah' => null, 'kota_sekolah' => null, 'jurusan' => null, 'tanggal_penelusuran' => null, 'nomor_wa' => null, 'email' => null, 'catatan_penelusuran' => null, 'konfirmasi_lulus' => 1], $data);
    }

    private function lanjut(array $data = []): array
    {
        return array_replace(['status_penelusuran' => 'melanjutkan', 'jenis_sekolah' => 'sma', 'nama_sekolah' => 'SMA Negeri 1 Padang Panjang', 'kota_sekolah' => 'Padang Panjang', 'tanggal_penelusuran' => '2026-09-01'], $data);
    }

    private function alumni(array $data = []): AlumniHumas
    {
        $this->post(route('alumni-humas.store'), $this->data($data))->assertRedirect()->assertSessionHasNoErrors();

        return AlumniHumas::latest('id')->firstOrFail();
    }

    private function editData(AlumniHumas $a, array $data = []): array
    {
        $a = $a->fresh();

        return array_replace($a->only([...AlumniHumas::PUBLIK, ...AlumniHumas::PRIVAT]), ['tanggal_lulus' => $a->tanggal_lulus?->format('Y-m-d'), 'tanggal_penelusuran' => $a->tanggal_penelusuran?->format('Y-m-d'), 'versi' => $a->versi, 'konfirmasi_lulus' => 1, 'catatan_perubahan' => 'Koreksi data alumni.'], $data);
    }

    private function siswa(array $data = []): Siswa
    {
        $number = Siswa::count() + 100;

        return Siswa::create(array_replace(['nama_lengkap' => 'Siswa Uji '.$number, 'nis' => (string) $number, 'nisn' => str_pad((string) $number, 10, '0', STR_PAD_LEFT), 'jenis_kelamin' => 'L', 'aktif' => true], $data));
    }

    private function anggota(Siswa $s, int $tingkat = 9): AnggotaKelas
    {
        $tahun = TahunPelajaran::firstOrCreate(['nama' => '2025/2026'], ['tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'aktif' => false]);
        $kelas = Kelas::firstOrCreate(['nama' => $tingkat === 9 ? 'IX.A' : 'VIII.A', 'tahun_pelajaran_id' => $tahun->id], ['tingkat' => $tingkat, 'aktif' => true, 'kapasitas' => 30]);

        return AnggotaKelas::create(['siswa_id' => $s->id, 'kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $tahun->id, 'nomor_absen' => $kelas->anggotaKelas()->count() + 1, 'status_keanggotaan' => 'aktif']);
    }

    private function excel(array $filter = []): string
    {
        $r = $this->post(route('alumni-humas.export'), $filter)->assertOk();
        $path = $r->baseResponse->getFile()->getPathname();
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $this->assertNotFalse(simplexml_load_string($zip->getFromIndex($i)));
            }
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            return $xml;
        } finally {
            @unlink($path);
        }
    }

    private function capture(string $name, TestResponse $r, string $extension = 'html'): void
    {
        if (getenv('NUSA_CAPTURE_ALUMNI_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/alumni-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$name.'.'.$extension, $r->getContent());
    }
}
