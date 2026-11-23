<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\JenisPelanggaranSiswa;
use App\Models\Kelas;
use App\Models\LogScanAbsensi;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\RiwayatPerubahanAbsensiSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Absensi\ProsesScanAbsensi;
use App\Services\Pembinaan\AturanPresensiManualService;
use App\Services\Pembinaan\ProsesPoinKeterlambatanService;
use App\Services\Piket\AksesCatatanPresensiPiketService;
use App\Services\Piket\GuruPiketHariIniService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class KoreksiPresensiGuruPiketTest extends TestCase
{
    use RefreshDatabase;

    public function test_piket_dapat_memperbaiki_input_manual_admin_hari_ini_dari_web_dan_api(): void
    {
        [$guru, $anggota] = $this->data();
        $absensi = $this->manual($anggota);
        $this->actingAs($guru);
        $web = $this->get(route('piket-kehadiran-siswa.index'))->assertOk()->assertSee('Ubah catatan manual')
            ->assertSee('Input manual')->assertSee('Petugas Simulasi Admin');
        $this->capture('manual', $web->getContent());
        $this->getJson(route('api.v1.piket-saya.index'))->assertOk()
            ->assertJsonPath('data.items.0.presensi.dapat_dicatat', true)
            ->assertJsonPath('data.items.0.presensi.sumber_masuk', 'manual')
            ->assertJsonPath('data.items.0.presensi.pencatat_terakhir.nama', 'Petugas Simulasi Admin');
        $this->put(route('piket-kehadiran-siswa.update', $anggota), $this->isian())->assertRedirect()->assertSessionHasNoErrors();
        $this->patchJson(route('api.v1.piket-saya.kehadiran.update', $anggota), $this->isian('06:23'))->assertOk();
        $this->assertDatabaseCount('absensi_siswa', 1);
        $this->assertDatabaseHas('absensi_siswa', [
            'id' => $absensi->id, 'jam_masuk' => null, 'status_masuk' => 'tepat_waktu', 'sumber' => 'guru_piket',
        ]);
        $this->assertDatabaseHas('riwayat_perubahan_absensi_siswa', ['absensi_siswa_id' => $absensi->id,
            'sumber' => 'guru_piket', 'dibuat_oleh_pengguna_id' => $guru->id]);
    }

    public function test_scan_pulang_tidak_mengunci_input_masuk_manual_dan_tidak_dihapus_saat_koreksi(): void
    {
        [$guru, $anggota] = $this->data();
        $absensi = $this->manual($anggota);
        $scan = app(ProsesScanAbsensi::class)->proses($anggota->siswa->nisn, Carbon::parse('2026-11-19 14:40:00'), 'pulang');
        $this->assertTrue($scan['berhasil']);
        $this->assertSame('scan', $absensi->fresh()->sumber);
        $this->actingAs($guru);
        $this->getJson(route('api.v1.piket-saya.index'))->assertOk()
            ->assertJsonPath('data.items.0.presensi.dapat_dicatat', true)
            ->assertJsonPath('data.items.0.presensi.sumber_masuk', 'manual')
            ->assertJsonPath('data.items.0.presensi.sumber_pulang', 'scan')
            ->assertJsonPath('data.items.0.presensi.jam_pulang', '14:40');
        $web = $this->get(route('piket-kehadiran-siswa.index'))->assertOk()
            ->assertSee('Ubah catatan manual')->assertSee('14:40')->assertSee('Scanner');
        $this->capture('checkout', $web->getContent());
        $this->patchJson(route('api.v1.piket-saya.kehadiran.update', $anggota), $this->isian())->assertOk();
        $this->assertDatabaseHas('absensi_siswa', ['id' => $absensi->id, 'jam_pulang' => '14:40:00', 'status_pulang' => 'normal']);
        $this->patchJson(route('api.v1.piket-saya.kehadiran.update', $anggota), [
            'status_kehadiran' => 'sakit', 'catatan' => 'Mencoba mengubah setelah pulang.',
        ])->assertUnprocessable()->assertJsonValidationErrors('status_kehadiran');
        $this->assertDatabaseHas('absensi_siswa', ['id' => $absensi->id, 'status_kehadiran' => 'hadir', 'jam_pulang' => '14:40:00']);
    }

    public function test_bukti_scan_masuk_tetap_dilindungi_meski_sumber_harian_diganti_manual(): void
    {
        [$guru, $anggota] = $this->data();
        $absensi = $this->manual($anggota);
        $this->scan($absensi, 'masuk', '06:22:00');
        $this->actingAs($guru);
        $this->getJson(route('api.v1.piket-saya.index'))->assertOk()
            ->assertJsonPath('data.items.0.presensi.dapat_dicatat', false)
            ->assertJsonPath('data.items.0.presensi.sumber_masuk', 'scan');
        $web = $this->get(route('piket-kehadiran-siswa.index'))->assertOk()
            ->assertSee('Ada bukti scan masuk berhasil.')->assertDontSee('Ubah catatan manual');
        $this->capture('locked', $web->getContent());
        $awal = $absensi->fresh()->getRawOriginal();
        $this->patchJson(route('api.v1.piket-saya.kehadiran.update', $anggota), $this->isian())->assertForbidden();
        $this->put(route('piket-kehadiran-siswa.update', $anggota), $this->isian())->assertForbidden();
        $this->assertSame($awal, $absensi->fresh()->getRawOriginal());
        $this->assertDatabaseCount('riwayat_perubahan_absensi_siswa', 1);
    }

    public function test_scan_lama_tanpa_log_dan_keputusan_verifikasi_tetap_terkunci(): void
    {
        [$guru, $anggota] = $this->data();
        $absensi = AbsensiSiswa::create(['siswa_id' => $anggota->siswa_id, 'anggota_kelas_id' => $anggota->id,
            'tahun_pelajaran_id' => $anggota->tahun_pelajaran_id, 'kelas_id' => $anggota->kelas_id,
            'tanggal' => '2026-11-19', 'sumber' => 'scan', 'jam_masuk' => '06:22:00', 'status_kehadiran' => 'hadir']);
        $this->actingAs($guru);
        $this->patchJson(route('api.v1.piket-saya.kehadiran.update', $anggota), $this->isian())->assertForbidden();
        foreach ([AbsensiSiswa::SCAN_BERMASALAH, AbsensiSiswa::GANGGUAN_SCAN_TERVERIFIKASI] as $alasan) {
            $absensi->update(['sumber' => 'manual', 'alasan_hadir_manual' => $alasan]);
            $this->patchJson(route('api.v1.piket-saya.kehadiran.update', $anggota), $this->isian())->assertForbidden();
        }
        $this->assertDatabaseCount('riwayat_perubahan_absensi_siswa', 0);
    }

    public function test_hari_lain_akun_nonaktif_dan_tanpa_izin_tidak_mendapat_koreksi_piket(): void
    {
        [$guru, $anggota] = $this->data();
        $this->manual($anggota);
        $this->actingAs($guru);
        $this->travelTo(now()->addDay());
        $this->patchJson(route('api.v1.piket-saya.kehadiran.update', $anggota), $this->isian())->assertForbidden();
        $this->travelBack();
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(16, 0));
        $guru->aktif = false;
        $this->assertNull(app(GuruPiketHariIniService::class)->jadwalHariIni($guru, $anggota->tahunPelajaran));
        $guru->aktif = true;
        $guru->daftarPeran()->detach();
        $guru->unsetRelation('daftarPeran');
        $this->patchJson(route('api.v1.piket-saya.kehadiran.update', $anggota), $this->isian())->assertForbidden();
        $this->assertDatabaseCount('riwayat_perubahan_absensi_siswa', 1);
    }

    public function test_perubahan_status_manual_mengembalikan_poin_tanpa_penggandaan(): void
    {
        [$guru, $anggota] = $this->data();
        $aturan = app(AturanPresensiManualService::class)->aturan($anggota->tahun_pelajaran_id);
        JenisPelanggaranSiswa::whereKey($aturan['id'])->update(['poin' => 5]);
        $absensi = $this->manual($anggota);
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($absensi);
        $this->assertSame(5, (int) TransaksiPoinSiswa::sum('poin'));
        $this->actingAs($guru);
        $url = route('api.v1.piket-saya.kehadiran.update', $anggota);
        $this->patchJson($url, $this->isian())->assertOk();
        $this->patchJson($url, $this->isian())->assertOk();
        $this->assertSame(5, (int) TransaksiPoinSiswa::sum('poin'));
        $this->patchJson($url, ['status_kehadiran' => 'izin', 'catatan' => 'Koreksi berdasarkan konfirmasi wali siswa.'])->assertOk();
        $this->assertSame(0, (int) TransaksiPoinSiswa::sum('poin'));
        $this->assertDatabaseHas('absensi_siswa', ['id' => $absensi->id, 'status_kehadiran' => 'izin', 'sanksi_manual_menit' => 0]);
    }

    public function test_jam_masa_depan_dan_jam_melebihi_pulang_ditolak_tanpa_mengubah_presensi(): void
    {
        [$guru, $anggota] = $this->data();
        $absensi = $this->manual($anggota);
        $this->actingAs($guru);
        $url = route('api.v1.piket-saya.kehadiran.update', $anggota);
        $this->patchJson($url, $this->isian('17:00'))->assertUnprocessable()->assertJsonValidationErrors('jam_masuk');
        $absensi->update(['jam_pulang' => '14:40:00']);
        $this->patchJson($url, $this->isian('15:00'))->assertUnprocessable()->assertJsonValidationErrors('jam_masuk');
        $this->assertDatabaseHas('absensi_siswa', ['id' => $absensi->id, 'jam_masuk' => '06:22:00', 'jam_pulang' => '14:40:00']);
    }

    public function test_catatan_tanggal_lain_dan_penempatan_berbeda_tidak_diizinkan_oleh_kebijakan(): void
    {
        [$guru, $anggota] = $this->data();
        $absensi = $this->manual($anggota);
        $akses = app(AksesCatatanPresensiPiketService::class);
        $absensi->tanggal = '2026-11-18';
        $this->assertFalse($akses->rincian($absensi, $anggota)['dapat_dicatat']);
        $absensi->tanggal = '2026-11-19';
        $absensi->kelas_id = 999;
        $this->assertFalse($akses->rincian($absensi, $anggota)['dapat_dicatat']);
        $absensi->kelas_id = $anggota->kelas_id;
        $absensi->penugasan_presensi_id = 999;
        $this->assertFalse($akses->rincian($absensi, $anggota)['dapat_dicatat']);
    }

    private function data(): array
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(16, 0));
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $kelas = Kelas::create(['nama' => 'VII.A Simulasi', 'tingkat' => 7, 'tahun_pelajaran_id' => $tahun->id, 'aktif' => true]);
        $siswa = Siswa::create(['nama_lengkap' => 'Murid Uji Piket', 'nisn' => '1110009999', 'aktif' => true]);
        $anggota = AnggotaKelas::create(['siswa_id' => $siswa->id, 'kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $tahun->id,
            'status_keanggotaan' => 'aktif', 'tanggal_masuk' => '2026-07-01']);
        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru Piket Simulasi', 'jenis_pegawai' => 'Guru', 'aktif' => true]);
        $guru = Pengguna::create(['nama' => $pegawai->nama_lengkap, 'pegawai_id' => $pegawai->id, 'username' => 'guru-piket-koreksi',
            'kata_sandi' => 'UjiPiket2026', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $guru->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->sole());
        $mapel = MataPelajaran::create(['nama' => 'Informatika Simulasi', 'aktif' => true]);
        GuruMataPelajaran::create(['pegawai_id' => $pegawai->id, 'kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mapel->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
        JadwalPiketGuru::create(['pegawai_id' => $pegawai->id, 'tahun_pelajaran_id' => $tahun->id, 'hari' => 'kamis', 'aktif' => true]);
        PengaturanAbsensi::create(['hari' => 'kamis', 'urutan_hari' => 4, 'aktif' => true, 'jam_masuk' => '06:40:00',
            'jam_scan_masuk_mulai' => '05:30:00', 'jam_scan_masuk_selesai' => '07:30:00',
            'jam_pulang' => '14:40:00', 'jam_scan_pulang_mulai' => '14:30:00', 'jam_scan_pulang_selesai' => '17:00:00']);

        return [$guru, $anggota];
    }

    private function manual(AnggotaKelas $anggota): AbsensiSiswa
    {
        $absensi = AbsensiSiswa::create(['siswa_id' => $anggota->siswa_id, 'kelas_id' => $anggota->kelas_id,
            'anggota_kelas_id' => $anggota->id, 'tahun_pelajaran_id' => $anggota->tahun_pelajaran_id,
            'tanggal' => '2026-11-19', 'status_kehadiran' => 'hadir', 'jam_masuk' => '06:22:00', 'sumber' => 'manual',
            'alasan_hadir_manual' => 'tidak_membawa_kartu', 'sanksi_manual_menit' => 5, 'status_masuk' => 'terlambat', 'menit_terlambat' => 5]);
        $admin = Pengguna::where('username', 'administrator')->sole();
        $admin->update(['nama' => 'Petugas Simulasi Admin']);
        RiwayatPerubahanAbsensiSiswa::create(['absensi_siswa_id' => $absensi->id, 'siswa_id' => $anggota->siswa_id,
            'tanggal' => '2026-11-19', 'sumber' => 'koreksi_manual', 'status_sesudah' => 'hadir', 'dibuat_oleh_pengguna_id' => $admin->id]);

        return $absensi;
    }

    private function scan(AbsensiSiswa $absensi, string $jenis, string $jam): void
    {
        LogScanAbsensi::create(['absensi_siswa_id' => $absensi->id, 'siswa_id' => $absensi->siswa_id,
            'tanggal' => '2026-11-19', 'jenis_scan' => $jenis, 'waktu_scan' => '2026-11-19 '.$jam,
            'berhasil' => true, 'isi_scan' => 'KODE-KARTU-UJI', 'status_scan' => 'berhasil_'.$jenis]);
    }

    private function isian(string $jam = '06:22'): array
    {
        return ['status_kehadiran' => 'hadir', 'catatan' => 'Koreksi catatan manual sesuai konfirmasi.',
            'alasan_hadir_manual' => 'tidak_membawa_kartu', 'jam_masuk' => $jam];
    }

    private function capture(string $nama, string $html): void
    {
        if (getenv('NUSA_CAPTURE_PIKET_KOREKSI')) {
            $folder = storage_path('framework/testing/piket-koreksi');
            File::ensureDirectoryExists($folder);
            File::put($folder.'/'.$nama.'.html', $html);
        }
    }
}
