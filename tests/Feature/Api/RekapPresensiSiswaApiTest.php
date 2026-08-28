<?php

namespace Tests\Feature\Api;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapPresensiSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_administrator_melihat_rekap_detail_dan_mengoreksi_presensi_native(): void
    {
        Carbon::setTestNow('2026-08-27 09:00:00');
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $this->withToken($token)->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'rekap-presensi-siswa',
                'label' => 'Rekap & Koreksi Presensi Siswa',
                'status' => 'tersedia',
                'rute' => '/rekap-presensi-siswa',
            ]);

        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.index', [
            'tanggal' => '2026-08-27',
            'kelas_id' => $data['kelas']->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.hadir', 1)
            ->assertJsonPath('data.ringkasan.belum_scan', 1)
            ->assertJsonFragment(['sumber' => 'scan'])
            ->assertJsonFragment(['status' => 'belum_scan']);

        $this->withToken($token)->patchJson(route('api.v1.rekap-presensi-siswa.update', $data['anggotaBelum']), [
            'tanggal' => '2026-08-27',
            'status_kehadiran' => 'sakit',
            'catatan' => 'Surat sakit diterima admin.',
        ])->assertOk()->assertJsonPath('data.status', 'sakit');

        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.show', [
            'anggotaKelas' => $data['anggotaBelum'],
            'tanggal' => '2026-08-27',
        ]))
            ->assertOk()
            ->assertJsonPath('data.item.presensi.status', 'sakit')
            ->assertJsonPath('data.riwayat.0.status_sesudah', 'sakit')
            ->assertJsonPath('data.riwayat.0.sumber', 'koreksi_manual');

        $this->withToken($token)->patchJson(route('api.v1.rekap-presensi-siswa.update', $data['anggotaScan']), [
            'tanggal' => '2026-08-27',
            'status_kehadiran' => 'hadir',
            'jam_masuk' => '07:15',
            'jam_pulang' => '14:00',
            'catatan' => 'Disesuaikan dengan log mesin cadangan.',
        ])->assertOk()->assertJsonPath('data.menit_terlambat', 15);

        $this->assertDatabaseHas('absensi_siswa', [
            'siswa_id' => $data['siswaScan']->id,
            'sumber' => 'manual',
            'menit_terlambat' => 15,
        ]);
    }

    public function test_guru_pl_hanya_mengoreksi_hari_ini_dan_tidak_dapat_mengubah_hasil_scan(): void
    {
        Carbon::setTestNow('2026-08-27 09:00:00');
        $data = $this->dataDasar();
        $guruPl = $this->akunGuruPl();
        $token = $this->token($guruPl);

        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.index', ['tanggal' => '2026-08-27']))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.koreksi_hari_ini_terbatas', true);

        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.show', [
            'anggotaKelas' => $data['anggotaScan'],
            'tanggal' => '2026-08-27',
        ]))->assertOk()->assertJsonPath('data.hak_akses.dapat', false);

        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.show', [
            'anggotaKelas' => $data['anggotaBelum'],
            'tanggal' => '2026-08-27',
        ]))->assertOk()->assertJsonPath('data.hak_akses.dapat', true);

        $this->withToken($token)->patchJson(route('api.v1.rekap-presensi-siswa.update', $data['anggotaScan']), [
            'tanggal' => '2026-08-27',
            'status_kehadiran' => 'izin',
            'catatan' => 'Mencoba mengubah hasil scan.',
        ])->assertForbidden();

        $this->withToken($token)->patchJson(route('api.v1.rekap-presensi-siswa.update', $data['anggotaBelum']), [
            'tanggal' => '2026-08-27',
            'status_kehadiran' => 'izin',
            'catatan' => 'Izin disampaikan orang tua.',
        ])->assertOk();

        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.index', ['tanggal' => '2026-08-26']))
            ->assertForbidden();
    }

    public function test_wali_kelas_hanya_melihat_rekap_kelas_yang_diampu(): void
    {
        Carbon::setTestNow('2026-08-27 09:00:00');
        $data = $this->dataDasar();
        [$pegawai, $wali] = $this->akunWaliKelas();
        $data['kelas']->update(['wali_kelas_id' => $pegawai->id]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'nama' => 'VII.B Mobile',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $siswaLain = Siswa::create([
            'nama_lengkap' => 'Siswa Kelas Lain',
            'nis' => '27003',
            'nisn' => '0011223303',
            'aktif' => true,
        ]);
        $anggotaLain = AnggotaKelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $kelasLain->id,
            'siswa_id' => $siswaLain->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        $token = $this->token($wali);

        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.index', ['tanggal' => '2026-08-27']))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.cakupan_wali_kelas', true)
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonCount(1, 'data.kelas')
            ->assertJsonPath('data.kelas.0.id', $data['kelas']->id);

        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.show', [
            'anggotaKelas' => $anggotaLain,
            'tanggal' => '2026-08-27',
        ]))->assertForbidden();
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A Mobile', 'tingkat' => 7, 'aktif' => true,
        ]);
        $siswaScan = Siswa::create(['nama_lengkap' => 'Siswa Sudah Scan', 'nis' => '27001', 'nisn' => '0011223301', 'aktif' => true]);
        $siswaBelum = Siswa::create(['nama_lengkap' => 'Siswa Belum Scan', 'nis' => '27002', 'nisn' => '0011223302', 'aktif' => true]);
        $anggotaScan = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswaScan->id,
            'nomor_absen' => 1, 'status_keanggotaan' => 'aktif',
        ]);
        $anggotaBelum = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswaBelum->id,
            'nomor_absen' => 2, 'status_keanggotaan' => 'aktif',
        ]);
        PengaturanAbsensi::create([
            'hari' => 'kamis', 'urutan_hari' => 4, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00',
            'jam_scan_masuk_selesai' => '07:30', 'jam_scan_pulang_mulai' => '13:30', 'jam_pulang' => '14:00',
            'jam_scan_pulang_selesai' => '15:00', 'aktif' => true,
        ]);
        AbsensiSiswa::create([
            'tanggal' => '2026-08-27', 'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggotaScan->id, 'siswa_id' => $siswaScan->id, 'jam_masuk' => '06:50',
            'status_masuk' => 'tepat_waktu', 'menit_terlambat' => 0, 'status_kehadiran' => 'hadir', 'sumber' => 'scan',
        ]);

        return compact('tahun', 'kelas', 'siswaScan', 'siswaBelum', 'anggotaScan', 'anggotaBelum');
    }

    private function akunGuruPl(): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru PL Mobile', 'nip' => 'PL-MOBILE', 'aktif' => true]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap, 'username' => 'pl.mobile',
            'kata_sandi' => 'RahasiaNusa123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'guru_pl')->firstOrFail());

        return $akun;
    }

    private function akunWaliKelas(): array
    {
        $pegawai = Pegawai::create(['nama_lengkap' => 'Wali Kelas Mobile', 'nip' => 'WALI-MOBILE', 'aktif' => true]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap, 'username' => 'wali.mobile',
            'kata_sandi' => 'RahasiaNusa123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'wali_kelas')->firstOrFail());

        return [$pegawai, $akun];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
