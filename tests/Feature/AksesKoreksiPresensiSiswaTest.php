<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\KoreksiPresensiSiswaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AksesKoreksiPresensiSiswaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(9, 0));
    }

    public function test_guru_mapel_biasa_tidak_memiliki_menu_rekap_atau_izin_koreksi_web_dan_api(): void
    {
        [$a] = $this->siswaDuaKelas();
        $guru = $this->akun(['guru_mapel', 'pegawai', 'guru_wali', 'panitia_ujian']);
        $this->assertFalse($guru->memilikiIzin('absensi.koreksi'));
        $this->actingAs($guru)->get(route('rekap-absensi-harian.index'))->assertForbidden();
        $this->get(route('rekap-absensi-harian.koreksi.edit', $a))->assertForbidden();
        $token = $guru->createToken('audit-presensi', ['mobile'])->plainTextToken;
        $menu = $this->withToken($token)->getJson(route('api.v1.menu'))->assertOk();
        $this->assertNotContains('rekap-presensi-siswa', collect($menu->json('data.kelompok'))->flatMap(fn ($g) => $g['items'])->pluck('kode')->all());
        $this->getJson(route('api.v1.rekap-presensi-siswa.index'))->assertForbidden();
        foreach (['hadir', 'sakit', 'izin', 'alfa'] as $status) {
            $data = $this->koreksi($status);
            $this->put(route('rekap-absensi-harian.koreksi.update', $a), $data)->assertForbidden();
            $this->patchJson(route('api.v1.rekap-presensi-siswa.update', $a), $data)->assertForbidden();
        }
        $this->assertTidakAdaPerubahan();
    }

    public function test_izin_koreksi_yang_salah_dipasang_pada_guru_mapel_tidak_membuka_tombol_atau_penyimpanan(): void
    {
        [$a] = $this->siswaDuaKelas();
        $this->tambahIzin('guru_mapel', ['absensi.lihat', 'absensi.koreksi', 'absensi.koreksi_hari_ini']);
        $guru = $this->akun(['guru_mapel', 'pegawai', 'guru_wali', 'panitia_ujian']);
        $this->assertTrue($guru->memilikiIzin('absensi.koreksi'));
        $this->actingAs($guru)->get(route('rekap-absensi-harian.index'))->assertOk()
            ->assertDontSee(route('rekap-absensi-harian.koreksi.edit', $a), false);
        $this->get(route('rekap-absensi-harian.koreksi.edit', $a))->assertForbidden();
        $this->getJson(route('api.v1.rekap-presensi-siswa.index'))->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_koreksi', false)->assertJsonPath('data.items.0.koreksi.dapat', false);
        $this->getJson(route('api.v1.rekap-presensi-siswa.show', [$a, 'tanggal' => '2026-11-02']))
            ->assertOk()->assertJsonPath('data.hak_akses.dapat', false);
        foreach (['hadir', 'sakit', 'izin', 'alfa'] as $status) {
            $this->put(route('rekap-absensi-harian.koreksi.update', $a), $this->koreksi($status))->assertForbidden();
            $this->patchJson(route('api.v1.rekap-presensi-siswa.update', $a), $this->koreksi($status))->assertForbidden();
        }
        $this->assertTidakAdaPerubahan();
    }

    public function test_wali_hanya_dapat_mengoreksi_kelas_sendiri_meski_merangkap_bk_dan_kurikulum(): void
    {
        [$a, $b] = $this->siswaDuaKelas();
        $wali = $this->akun(['pegawai', 'guru_mapel', 'wali_kelas', 'bk', 'wakil_pimpinan_kurikulum']);
        $a->kelas->update(['wali_kelas_id' => $wali->pegawai_id]);
        $this->assertFalse($wali->membatasiCakupanWaliKelas());
        $this->actingAs($wali)->get(route('rekap-absensi-harian.index'))->assertOk()
            ->assertSee(route('rekap-absensi-harian.koreksi.edit', $a), false)
            ->assertDontSee(route('rekap-absensi-harian.koreksi.edit', $b), false);
        $this->get(route('rekap-absensi-harian.koreksi.edit', $a))->assertOk();
        $this->get(route('rekap-absensi-harian.koreksi.edit', $b))->assertForbidden();
        $this->getJson(route('api.v1.rekap-presensi-siswa.show', [$b, 'tanggal' => '2026-11-02']))
            ->assertOk()->assertJsonPath('data.hak_akses.dapat', false);
        $this->patchJson(route('api.v1.rekap-presensi-siswa.update', $b), $this->koreksi('alfa'))->assertForbidden();
        $this->put(route('rekap-absensi-harian.koreksi.update', $b), $this->koreksi('izin'))->assertForbidden();
        $this->patchJson(route('api.v1.rekap-presensi-siswa.update', $a), $this->koreksi('sakit'))
            ->assertOk()->assertJsonPath('data.status', 'sakit');
        $this->put(route('rekap-absensi-harian.koreksi.update', $a), $this->koreksi('izin'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('absensi_siswa', ['siswa_id' => $a->siswa_id, 'status_kehadiran' => 'izin']);
        $this->assertDatabaseMissing('absensi_siswa', ['siswa_id' => $b->siswa_id]);
    }

    public function test_role_wali_tanpa_penugasan_kelas_tidak_memberi_koreksi_penuh(): void
    {
        [$a] = $this->siswaDuaKelas();
        $wali = $this->akun(['wali_kelas']);
        $this->assertFalse(app(KoreksiPresensiSiswaService::class)->memilikiKewenanganKoreksi($wali));
        $this->actingAs($wali)->get(route('rekap-absensi-harian.koreksi.edit', $a))->assertForbidden();
        $this->patchJson(route('api.v1.rekap-presensi-siswa.update', $a), $this->koreksi('alfa'))->assertForbidden();
        $this->assertTidakAdaPerubahan();
    }

    public function test_administrator_dan_waka_kesiswaan_tetap_dapat_mengoreksi_semua_kelas(): void
    {
        [$a, $b] = $this->siswaDuaKelas();
        foreach ([Pengguna::where('username', 'administrator')->firstOrFail(), $this->akun(['wakil_pimpinan_kesiswaan'])] as $petugas) {
            $this->withHeader('Authorization', '')->actingAs($petugas, 'web')->get(route('rekap-absensi-harian.koreksi.edit', $b))->assertOk();
            $this->put(route('rekap-absensi-harian.koreksi.update', $a), $this->koreksi('izin'))->assertRedirect()->assertSessionHasNoErrors();
            $token = $petugas->createToken('audit-presensi', ['mobile'])->plainTextToken;
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->patchJson(route('api.v1.rekap-presensi-siswa.update', $b), $this->koreksi('sakit'))
                ->assertOk()->assertJsonPath('data.status', 'sakit');
        }
    }

    public function test_guru_pl_tetap_terbatas_meski_keliru_mendapat_izin_penuh(): void
    {
        [$a, $b] = $this->siswaDuaKelas();
        $this->tambahIzin('guru_pl', ['absensi.koreksi']);
        $guru = $this->akun(['guru_pl', 'guru_mapel']);
        AbsensiSiswa::create(['siswa_id' => $b->siswa_id, 'anggota_kelas_id' => $b->id, 'kelas_id' => $b->kelas_id,
            'tahun_pelajaran_id' => $b->tahun_pelajaran_id, 'tanggal' => '2026-11-02', 'status_kehadiran' => 'hadir',
            'jam_masuk' => '06:35', 'sumber' => 'scan']);
        $this->actingAs($guru)->get(route('rekap-absensi-harian.index'))->assertOk()
            ->assertSee('Akses Guru PL berlaku')->assertDontSee(route('rekap-absensi-harian.koreksi.edit', $b), false);
        $this->getJson(route('api.v1.rekap-presensi-siswa.index'))->assertOk()
            ->assertJsonPath('data.hak_akses.koreksi_hari_ini_terbatas', true);
        $this->get(route('rekap-absensi-harian.koreksi.edit', $b))->assertForbidden();
        $this->patchJson(route('api.v1.rekap-presensi-siswa.update', $b), $this->koreksi('izin'))->assertForbidden();
        $kemarin = array_replace($this->koreksi('izin'), ['tanggal' => '2026-11-01']);
        $this->put(route('rekap-absensi-harian.koreksi.update', $a), $kemarin)->assertForbidden();
        $this->patchJson(route('api.v1.rekap-presensi-siswa.update', $a), $kemarin)->assertForbidden();
        $this->patchJson(route('api.v1.rekap-presensi-siswa.update', $a), $this->koreksi('sakit'))->assertOk();
        $this->assertDatabaseHas('absensi_siswa', ['siswa_id' => $b->siswa_id, 'sumber' => 'scan', 'status_kehadiran' => 'hadir']);
    }

    public function test_akun_nonaktif_atau_siswa_salah_role_tidak_mendapat_koreksi(): void
    {
        [$a] = $this->siswaDuaKelas();
        $u = $this->akun(['wakil_pimpinan_kesiswaan']);
        $service = app(KoreksiPresensiSiswaService::class);
        $u->aktif = false;
        $this->assertFalse($service->evaluasiAkses($u, $a, '2026-11-02')['dapat']);
        $u->aktif = true;
        $u->siswa_id = $a->siswa_id;
        $u->pegawai_id = null;
        $u->peran = 'administrator';
        $this->assertFalse($service->evaluasiAkses($u, $a, '2026-11-02')['dapat']);
    }

    public function test_migrasi_mencabut_hanya_izin_koreksi_guru_mapel_dan_idempoten(): void
    {
        $this->tambahIzin('guru_mapel', ['absensi.koreksi', 'absensi.koreksi_hari_ini']);
        $wali = Peran::where('kode', 'wali_kelas')->firstOrFail();
        $izinWali = $wali->izin()->pluck('izin.id')->all();
        $guru = Peran::where('kode', 'guru_mapel')->firstOrFail();
        $izinLain = $guru->izin()->whereNotIn('kode', ['absensi.koreksi', 'absensi.koreksi_hari_ini'])->pluck('izin.id')->all();
        $migration = require database_path('migrations/2026_11_02_000001_batasi_izin_koreksi_presensi_guru_mapel.php');
        $migration->up();
        $migration->up();
        $this->assertSame($izinLain, $guru->izin()->pluck('izin.id')->all());
        $this->assertSame($izinWali, $wali->izin()->pluck('izin.id')->all());
        $this->assertFalse($guru->memilikiIzin(['absensi.koreksi', 'absensi.koreksi_hari_ini']));
        $this->assertTrue(Peran::where('kode', 'guru_pl')->firstOrFail()->memilikiIzin('absensi.koreksi_hari_ini'));
    }

    private function siswaDuaKelas(): array
    {
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $anggota = [];
        foreach (['VII.A', 'VII.B'] as $nama) {
            $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => $nama, 'tingkat' => 7, 'aktif' => true]);
            $siswa = Siswa::create(['nama_lengkap' => 'Siswa '.$nama, 'aktif' => true]);
            $anggota[] = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id, 'status_keanggotaan' => 'aktif', 'tanggal_masuk' => '2026-07-01']);
        }

        return $anggota;
    }

    private function akun(array $peran): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru uji akses', 'aktif' => true]);
        $u = Pengguna::create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap, 'username' => 'akses-'.Str::random(12),
            'kata_sandi' => 'UjiKoreksi!2026', 'peran' => 'pegawai', 'aktif' => true, 'akun_sistem' => false, 'wajib_ganti_kata_sandi' => false]);
        $u->daftarPeran()->attach(Peran::whereIn('kode', $peran)->pluck('id'));

        return $u;
    }

    private function tambahIzin(string $peran, array $kode): void
    {
        Peran::where('kode', $peran)->firstOrFail()->izin()->syncWithoutDetaching(Izin::whereIn('kode', $kode)->pluck('id'));
    }

    private function koreksi(string $status): array
    {
        return ['tanggal' => '2026-11-02', 'status_kehadiran' => $status, 'jam_masuk' => $status === 'hadir' ? '06:35' : null,
            'catatan' => 'Konfirmasi presensi melalui pemeriksaan petugas.'];
    }

    private function assertTidakAdaPerubahan(): void
    {
        $this->assertDatabaseCount('absensi_siswa', 0);
        $this->assertDatabaseCount('riwayat_perubahan_absensi_siswa', 0);
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
    }
}
