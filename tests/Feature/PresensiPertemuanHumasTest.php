<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\AkunOrangTuaService;
use App\Services\Humas\UndanganOrangTuaHumasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PresensiPertemuanHumasTest extends TestCase
{
    use RefreshDatabase;

    public function test_cakupan_kelas_tingkat_sekolah_dan_akun_tidak_aktif(): void
    {
        [$agenda, $humas, $ortu, $tahun, $kelas] = $this->data();
        $service = app(UndanganOrangTuaHumasService::class);
        $filter = ['tahun_pelajaran_id' => $tahun->id, 'cakupan' => 'kelas', 'kelas_ids' => [$kelas->id]];
        $preview = $service->pratinjau($filter);
        $this->assertSame(3, $preview['jumlahSiswa']);
        $this->assertCount(1, $preview['undangan']);
        $this->assertCount(1, $preview['tanpaAkun']);
        $this->assertCount(2, $preview['undangan']->first()['anak']);
        $preview = $service->pratinjau(['tahun_pelajaran_id' => $tahun->id, 'cakupan' => 'tingkat', 'tingkat' => 7]);
        $this->assertSame(4, $preview['jumlahSiswa']);
        $this->assertCount(2, $preview['tanpaAkun']);
        $preview = $service->pratinjau(['tahun_pelajaran_id' => $tahun->id, 'cakupan' => 'seluruh']);
        $this->assertSame(5, $preview['jumlahSiswa']);
        $this->assertCount(2, $preview['undangan']);
        $this->actingAs($humas)->get(route('agenda-humas.undangan', [$agenda] + $filter))->assertOk()->assertSee('Siswa Tanpa Akun');
        $this->assertDatabaseCount('peserta_pertemuan_humas', 0);
        $this->post(route('agenda-humas.undangan.store', $agenda), $filter)->assertSessionHasNoErrors();
        $peserta = $agenda->peserta()->firstOrFail();
        $this->assertSame($ortu->orangTuaWali->id, $peserta->orang_tua_wali_id);
        $this->assertCount(2, $peserta->anak_undangan);
        $this->post(route('agenda-humas.undangan.store', $agenda), $filter)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('peserta_pertemuan_humas', 1);
        $this->assertSame('belum_dicatat', $peserta->fresh()->status_kehadiran);
    }

    public function test_undangan_ditolak_untuk_tahun_atau_kelas_tidak_sesuai_dan_agenda_selesai(): void
    {
        [$agenda, $humas, , $tahun, $kelas] = $this->data();
        $lain = TahunPelajaran::create(['nama' => '2025/2026', 'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'aktif' => false]);
        $this->actingAs($humas)->post(route('agenda-humas.undangan.store', $agenda), ['tahun_pelajaran_id' => $lain->id, 'cakupan' => 'kelas', 'kelas_ids' => [$kelas->id]])->assertSessionHasErrors('kelas_ids.0');
        $this->post(route('agenda-humas.undangan.store', $agenda), ['tahun_pelajaran_id' => $tahun->id, 'cakupan' => 'tingkat', 'tingkat' => 12])->assertSessionHasErrors('tingkat');
        $this->get(route('agenda-humas.undangan', [$agenda, 'tahun_pelajaran_id' => ['invalid']]))->assertSessionHasErrors('tahun_pelajaran_id');
        $agenda->update(['status' => 'selesai']);
        $this->post(route('agenda-humas.undangan.store', $agenda), ['tahun_pelajaran_id' => $tahun->id, 'cakupan' => 'seluruh'])->assertSessionHasErrors('undangan');
        $this->assertDatabaseCount('peserta_pertemuan_humas', 0);
    }

    public function test_siswa_keluar_tidak_aktif_dan_kelas_nonaktif_tidak_masuk_undangan(): void
    {
        [, , , $tahun] = $this->data();
        $service = app(UndanganOrangTuaHumasService::class);
        $filter = ['tahun_pelajaran_id' => $tahun->id, 'cakupan' => 'seluruh'];
        AnggotaKelas::whereHas('siswa', fn ($q) => $q->where('nama_lengkap', 'Anak Pertama'))->update(['status_keanggotaan' => 'keluar']);
        $this->assertSame(4, $service->pratinjau($filter)['jumlahSiswa']);
        Siswa::where('nama_lengkap', 'Anak Kedua')->update(['aktif' => false]);
        $preview = $service->pratinjau($filter);
        $this->assertSame(3, $preview['jumlahSiswa']);
        $this->assertCount(1, $preview['undangan']);
        Kelas::where('tingkat', 8)->update(['aktif' => false]);
        $preview = $service->pratinjau($filter);
        $this->assertSame(2, $preview['jumlahSiswa']);
        $this->assertCount(0, $preview['undangan']);
    }

    public function test_akses_humas_pimpinan_dan_orang_tua_terpisah(): void
    {
        [$agenda, $humas, $ortu, $tahun] = $this->data();
        $this->actingAs($ortu)->get(route('agenda-humas.undangan', $agenda))->assertForbidden();
        $this->get(route('agenda-humas.pantau-presensi', $agenda))->assertForbidden();
        $pimpinan = $this->akun('pimpinan');
        $this->actingAs($pimpinan)->get(route('agenda-humas.show', [$agenda, 'tab' => 'qr']))->assertOk()->assertDontSee('Pilih kelas / tingkat');
        $this->get(route('agenda-humas.pantau-presensi', $agenda))->assertOk()->assertJsonPath('rekap.hadir', 0);
        $this->patch(route('agenda-humas.akses-presensi', $agenda), ['dibuka' => 1])->assertForbidden();
        $this->actingAs($humas)->patch(route('agenda-humas.akses-presensi', $agenda), ['dibuka' => 1])->assertSessionHasErrors('presensi');
        $this->actingAs($humas)->get(route('pertemuan-saya.show', $agenda->token_presensi))->assertForbidden()->assertSee('Gunakan akun orang tua/wali');
    }

    public function test_qr_membutuhkan_konfirmasi_dan_kehadiran_tidak_berganda(): void
    {
        [$agenda, $humas, $ortu] = $this->dataDenganUndangan();
        $this->actingAs($humas)->patch(route('agenda-humas.akses-presensi', $agenda), ['dibuka' => 1])->assertSessionHasNoErrors();
        $peserta = $agenda->peserta()->firstOrFail();
        $this->actingAs($ortu)->get(route('pertemuan-saya.show', $agenda->token_presensi))->assertOk()->assertSee('Konfirmasi hadir');
        $this->assertNull($peserta->fresh()->hadir_pada);
        $this->post(route('pertemuan-saya.hadir', $agenda->token_presensi), ['peserta_id' => 999])->assertSessionHasNoErrors();
        $this->assertSame('hadir', $peserta->fresh()->status_kehadiran);
        $this->assertSame('qr', $peserta->fresh()->sumber_kehadiran);
        $this->assertSame($ortu->id, $peserta->fresh()->dicatat_oleh_pengguna_id);
        $this->assertSame(1, $peserta->fresh()->versi_presensi);
        $this->actingAs($humas)->put(route('agenda-humas.presensi', $agenda), ['jumlah_baris' => 1,
            'kehadiran' => [['id' => $peserta->id, 'versi_presensi' => 1, 'status_kehadiran' => 'hadir']]])->assertSessionHasNoErrors();
        $this->assertSame($ortu->id, $peserta->fresh()->dicatat_oleh_pengguna_id);
        $this->assertSame(1, $peserta->fresh()->versi_presensi);
        $waktu = $peserta->fresh()->hadir_pada->toDateTimeString();
        $this->travel(5)->minutes();
        $this->actingAs($ortu)->post(route('pertemuan-saya.hadir', $agenda->token_presensi))->assertSessionHasNoErrors();
        $this->assertSame($waktu, $peserta->fresh()->hadir_pada->toDateTimeString());
        $this->assertSame(1, $peserta->fresh()->versi_presensi);
        $this->actingAs($humas)->post(route('agenda-humas.undangan.store', $agenda), ['tahun_pelajaran_id' => $peserta->anak_undangan[0]['tahun_pelajaran_id'], 'cakupan' => 'seluruh'])->assertSessionHasNoErrors();
        $this->assertSame('hadir', $peserta->fresh()->status_kehadiran);
        $this->get(route('agenda-humas.pantau-presensi', $agenda))->assertJsonPath('rekap.hadir', 1)->assertJsonPath('terbaru.0.sumber_kehadiran', 'qr');
    }

    public function test_presensi_tutup_selesai_dan_batal_tidak_menerima_kehadiran_baru(): void
    {
        [$agenda, $humas, $ortu] = $this->dataDenganUndangan();
        $route = route('pertemuan-saya.hadir', $agenda->token_presensi);
        $this->actingAs($ortu)->post($route)->assertSessionHasErrors('presensi');
        $this->actingAs($humas)->patch(route('agenda-humas.akses-presensi', $agenda), ['dibuka' => 1])->assertSessionHasNoErrors();
        $this->patch(route('agenda-humas.akses-presensi', $agenda), ['dibuka' => 0])->assertSessionHasNoErrors();
        $this->actingAs($ortu)->post($route)->assertSessionHasErrors('presensi');
        $this->actingAs($humas)->patch(route('agenda-humas.akses-presensi', $agenda), ['dibuka' => 1]);
        $agenda->update(['pembahasan' => 'Pembahasan', 'keputusan' => 'Keputusan']);
        $this->patch(route('agenda-humas.status', $agenda), ['status' => 'selesai'])->assertSessionHasNoErrors();
        $this->assertFalse($agenda->fresh()->presensi_dibuka);
        $this->actingAs($ortu)->post($route)->assertSessionHasErrors('presensi');
        $this->actingAs($humas)->patch(route('agenda-humas.akses-presensi', $agenda), ['dibuka' => 1])->assertSessionHasErrors('presensi');
        $this->patch(route('agenda-humas.status', $agenda), ['status' => 'terjadwal']);
        $this->patch(route('agenda-humas.akses-presensi', $agenda), ['dibuka' => 1]);
        $this->patch(route('agenda-humas.status', $agenda), ['status' => 'dibatalkan', 'alasan_pembatalan' => 'Jadwal berubah']);
        $this->assertFalse($agenda->fresh()->presensi_dibuka);
        $this->actingAs($ortu)->post($route)->assertSessionHasErrors('presensi');
        $this->assertSame('belum_dicatat', $agenda->peserta()->first()->status_kehadiran);
    }

    public function test_akun_lain_tidak_bisa_mengaku_hadir_atau_melihat_peserta_lain(): void
    {
        [$agenda, $humas, $ortu, , , $lain] = $this->dataDenganUndangan();
        $this->actingAs($humas)->patch(route('agenda-humas.akses-presensi', $agenda), ['dibuka' => 1]);
        $this->actingAs($lain)->get(route('pertemuan-saya.show', $agenda->token_presensi))->assertOk()
            ->assertSee('tidak terdaftar')->assertDontSee($agenda->judul)->assertDontSee($ortu->nama);
        $this->post(route('pertemuan-saya.hadir', $agenda->token_presensi))->assertSessionHasErrors('presensi');
        $this->get(route('pertemuan-saya.index'))->assertDontSee($agenda->judul);
        $ortu->orangTuaWali->siswa()->detach();
        $this->actingAs($ortu)->post(route('pertemuan-saya.hadir', $agenda->token_presensi))->assertSessionHasErrors('presensi');
        $ortu->update(['aktif' => false]);
        $this->actingAs($ortu->fresh())->post(route('pertemuan-saya.hadir', $agenda->token_presensi))->assertForbidden();
        $this->assertSame('belum_dicatat', $agenda->peserta()->first()->status_kehadiran);
    }

    public function test_formulir_lama_tidak_menimpa_qr_dan_koreksi_manual_tetap_bisa(): void
    {
        [$agenda, $humas, $ortu] = $this->dataDenganUndangan();
        $tamu = $agenda->peserta()->create(['nama' => 'Tamu manual']);
        $peserta = $agenda->peserta()->whereNotNull('orang_tua_wali_id')->firstOrFail();
        $this->actingAs($humas)->patch(route('agenda-humas.akses-presensi', $agenda), ['dibuka' => 1]);
        $this->actingAs($ortu)->post(route('pertemuan-saya.hadir', $agenda->token_presensi));
        $baris = [['id' => $tamu->id, 'versi_presensi' => 0, 'status_kehadiran' => 'hadir'],
            ['id' => $peserta->id, 'versi_presensi' => 0, 'status_kehadiran' => 'belum_dicatat']];
        $this->actingAs($humas)->from(route('agenda-humas.show', [$agenda, 'tab' => 'peserta']))
            ->put(route('agenda-humas.presensi', $agenda), ['jumlah_baris' => 2, 'kehadiran' => $baris])->assertSessionHasErrors('kehadiran');
        $this->assertSame('belum_dicatat', $tamu->fresh()->status_kehadiran);
        $this->assertSame('hadir', $peserta->fresh()->status_kehadiran);
        $baris[1]['versi_presensi'] = 1;
        $baris[1]['status_kehadiran'] = 'izin';
        $this->put(route('agenda-humas.presensi', $agenda), ['jumlah_baris' => 2, 'kehadiran' => $baris])->assertSessionHasNoErrors();
        $this->assertSame('manual', $peserta->fresh()->sumber_kehadiran);
        $this->assertNull($peserta->fresh()->hadir_pada);
        $this->actingAs($ortu)->post(route('pertemuan-saya.hadir', $agenda->token_presensi))->assertSessionHasErrors('presensi');
        $this->assertSame('izin', $peserta->fresh()->status_kehadiran);
    }

    public function test_scan_sebelum_login_kembali_ke_konfirmasi_setelah_ganti_sandi(): void
    {
        [$agenda, , $ortu] = $this->dataDenganUndangan();
        $ortu->update(['wajib_ganti_kata_sandi' => true]);
        $this->get(route('presensi-pertemuan.masuk', $agenda->token_presensi))->assertRedirect(route('login'))
            ->assertSessionHas('humas.presensi_token', $agenda->token_presensi);
        $this->post(route('login.store'), ['username' => $ortu->username, 'password' => 'TesOrangTua123'])->assertRedirect(route('pertemuan-saya.show', $agenda->token_presensi));
        $this->get(route('pertemuan-saya.show', $agenda->token_presensi))->assertRedirect(route('kata-sandi.edit'));
        $this->put(route('kata-sandi.update'), ['kata_sandi_lama' => 'TesOrangTua123', 'kata_sandi_baru' => 'BaruOrangTua123', 'kata_sandi_baru_confirmation' => 'BaruOrangTua123'])
            ->assertSessionHasNoErrors()->assertRedirect(route('pertemuan-saya.show', $agenda->token_presensi));
        $this->get(route('pertemuan-saya.show', $agenda->token_presensi))->assertOk()->assertSessionMissing('humas.presensi_token');
        $this->assertSame('belum_dicatat', $agenda->peserta()->first()->status_kehadiran);
        $this->get(route('presensi-pertemuan.masuk', str_repeat('x', 64)))->assertNotFound();
    }

    public function test_agenda_lama_dengan_token_kosong_dan_peserta_manual_tetap_dapat_dipakai(): void
    {
        [$agenda, $humas] = $this->dataDenganUndangan();
        $agenda->forceFill(['token_presensi' => null])->save();
        $manual = $agenda->peserta()->create(['nama' => 'Tamu lama', 'status_kehadiran' => 'hadir']);
        $this->actingAs($humas)->get(route('agenda-humas.show', [$agenda, 'tab' => 'qr']))->assertOk()->assertSee('QR tersedia');
        $this->patch(route('agenda-humas.akses-presensi', $agenda), ['dibuka' => 1])->assertSessionHasNoErrors();
        $this->assertSame(64, strlen($agenda->fresh()->token_presensi));
        $this->assertSame('hadir', $manual->fresh()->status_kehadiran);
        $this->get(route('agenda-humas.cetak-qr', $agenda))->assertOk()->assertSee('<svg', false);
    }

    public function test_halaman_qr_undangan_orang_tua_dan_cetak_render(): void
    {
        [$agenda, $humas, $ortu, $tahun, $kelas] = $this->dataDenganUndangan();
        $this->actingAs($humas)->patch(route('agenda-humas.akses-presensi', $agenda), ['dibuka' => 1]);
        $this->capture('qr', $this->get(route('agenda-humas.show', [$agenda, 'tab' => 'qr']))->assertOk()->assertSee('Presensi dibuka'));
        $this->capture('undangan', $this->get(route('agenda-humas.undangan', [$agenda, 'tahun_pelajaran_id' => $tahun->id, 'cakupan' => 'kelas', 'kelas_ids' => [$kelas->id]]))->assertOk());
        $this->capture('cetak-qr', $this->get(route('agenda-humas.cetak-qr', $agenda))->assertOk());
        $this->capture('pantau', $this->get(route('agenda-humas.pantau-presensi', $agenda))->assertOk());
        $this->actingAs($ortu);
        $this->capture('pertemuan', $this->get(route('pertemuan-saya.index'))->assertOk()->assertSee($agenda->judul)->assertSee('Pertemuan Saya'));
        $this->capture('konfirmasi', $this->get(route('pertemuan-saya.show', $agenda->token_presensi))->assertOk()->assertSee('Konfirmasi hadir'));
        $this->post(route('pertemuan-saya.hadir', $agenda->token_presensi))->assertSessionHasNoErrors();
        $this->capture('hadir', $this->get(route('pertemuan-saya.show', $agenda->token_presensi))->assertOk()->assertSee('Kehadiran telah tercatat')->assertDontSee('Konfirmasi hadir'));
        $agenda->update(['status' => 'selesai']);
        $this->get(route('pertemuan-saya.index'))->assertDontSee($agenda->judul);
        $this->get(route('pertemuan-saya.index', ['tab' => 'riwayat']))->assertSee($agenda->judul);
        $this->actingAs($humas)->get(route('agenda-humas.cetak', [$agenda, 'jenis' => 'notulen']))->assertSee('1 hadir');
    }

    private function data(): array
    {
        $this->travelTo(now()->setDate(2026, 10, 4)->setTime(8, 0));
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A', 'tingkat' => 7, 'aktif' => true]);
        $kelasB = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.B', 'tingkat' => 7, 'aktif' => true]);
        $kelas8 = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.A', 'tingkat' => 8, 'aktif' => true]);
        $anak = $this->siswa($kelas, 'Anak Pertama', 1);
        $adik = $this->siswa($kelas, 'Anak Kedua', 2);
        $this->siswa($kelas, 'Siswa Tanpa Akun', 3);
        $tidakAktif = $this->siswa($kelasB, 'Akun Wali Tidak Aktif', 4);
        $lain = $this->siswa($kelas8, 'Anak Kelas Lain', 5);
        $ortu = $this->orangTua($anak);
        $ortu->orangTuaWali->siswa()->attach($adik->id, ['hubungan' => 'ibu', 'utama' => true]);
        $this->orangTua($tidakAktif)->update(['aktif' => false]);
        $ortuLain = $this->orangTua($lain);
        $agenda = AgendaHumas::create(['judul' => 'Pertemuan Orang Tua Kelas VII', 'jenis' => 'orang_tua',
            'waktu_mulai' => '2026-10-05 07:00', 'waktu_selesai' => '2026-10-05 09:00', 'tempat' => 'Aula sekolah', 'topik' => 'Koordinasi pembelajaran']);

        return [$agenda, $this->akun('wakil_pimpinan_humas'), $ortu, $tahun, $kelas, $ortuLain];
    }

    private function dataDenganUndangan(): array
    {
        $data = $this->data();
        app(UndanganOrangTuaHumasService::class)->tambahkan($data[0], ['tahun_pelajaran_id' => $data[3]->id, 'cakupan' => 'kelas', 'kelas_ids' => [$data[4]->id]]);

        return $data;
    }

    private function siswa(Kelas $kelas, string $nama, int $nomor): Siswa
    {
        $siswa = Siswa::create(['nama_lengkap' => $nama, 'nisn' => '001100000'.$nomor, 'nama_ibu' => 'Ibu '.$nama, 'kontak_absensi_utama' => 'ibu', 'aktif' => true]);
        AnggotaKelas::create(['tahun_pelajaran_id' => $kelas->tahun_pelajaran_id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'nomor_absen' => $nomor, 'status_keanggotaan' => 'aktif']);

        return $siswa;
    }

    private function orangTua(Siswa $siswa): Pengguna
    {
        $akun = app(AkunOrangTuaService::class)->buat($siswa);
        $akun->forceFill(['kata_sandi' => 'TesOrangTua123', 'kata_sandi_awal' => null, 'wajib_ganti_kata_sandi' => false])->save();

        return $akun;
    }

    private function akun(string $role): Pengguna
    {
        $akun = Pengguna::create(['nama' => 'Petugas Humas', 'username' => 'humas.'.uniqid(), 'kata_sandi' => 'TesHumas123', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $akun;
    }

    private function capture(string $nama, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_AGENDA_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/agenda-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$nama.($nama === 'pantau' ? '.json' : '.html'), $response->getContent());
    }
}
