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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanPresensiSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_melihat_laporan_detail_harian_dan_export_excel(): void
    {
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $administrator->createToken('Laporan Mobile', ['mobile'])->plainTextToken;
        $filter = [
            'periode' => 'harian', 'tanggal' => '2026-08-27',
            'tahun_pelajaran_id' => $data['tahun']->id, 'kelas_id' => $data['kelas']->id,
        ];

        $this->withToken($token)->getJson(route('api.v1.menu'))
            ->assertOk()->assertJsonFragment([
                'kode' => 'laporan-presensi-siswa', 'status' => 'tersedia', 'rute' => '/laporan-presensi-siswa',
            ]);

        $this->withToken($token)->getJson(route('api.v1.laporan-presensi-siswa.index', $filter))
            ->assertOk()
            ->assertJsonPath('data.periode.jenis', 'harian')
            ->assertJsonPath('data.periode.jumlah_hari_efektif', 1)
            ->assertJsonPath('data.ringkasan.siswa', 2)
            ->assertJsonPath('data.ringkasan.hadir', 1)
            ->assertJsonPath('data.ringkasan.alfa', 1)
            ->assertJsonFragment(['persentase_hadir' => 100])
            ->assertJsonPath('data.hak_akses.dapat_export', true);

        $this->withToken($token)->getJson(route('api.v1.laporan-presensi-siswa.show', [
            'anggotaKelas' => $data['anggotaBelum'], ...$filter,
        ]))
            ->assertOk()
            ->assertJsonPath('data.rincian.0.status', 'alfa')
            ->assertJsonPath('data.rincian.0.inferensi', true);

        $this->withToken($token)->get(route('api.v1.laporan-presensi-siswa.export', $filter))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_wali_kelas_hanya_melihat_laporan_kelas_yang_diampu(): void
    {
        $data = $this->dataDasar();
        $pegawai = Pegawai::create(['nama_lengkap' => 'Wali Laporan', 'nip' => 'WALI-LAPORAN', 'aktif' => true]);
        $wali = Pengguna::create([
            'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap, 'username' => 'wali.laporan',
            'kata_sandi' => 'RahasiaNusa123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false,
        ]);
        $wali->daftarPeran()->attach(Peran::where('kode', 'wali_kelas')->firstOrFail());
        $data['kelas']->update(['wali_kelas_id' => $pegawai->id]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id, 'nama' => 'VII.B Laporan', 'tingkat' => 7, 'aktif' => true,
        ]);
        $siswaLain = Siswa::create(['nama_lengkap' => 'Siswa Di Luar Cakupan', 'nis' => 'L003', 'nisn' => '7700000003', 'aktif' => true]);
        $anggotaLain = AnggotaKelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id, 'kelas_id' => $kelasLain->id, 'siswa_id' => $siswaLain->id,
            'nomor_absen' => 1, 'status_keanggotaan' => 'aktif',
        ]);
        $token = $wali->createToken('Laporan Wali', ['mobile'])->plainTextToken;
        $filter = ['periode' => 'harian', 'tanggal' => '2026-08-27', 'tahun_pelajaran_id' => $data['tahun']->id];

        $this->withToken($token)->getJson(route('api.v1.laporan-presensi-siswa.index', $filter))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.cakupan_wali_kelas', true)
            ->assertJsonPath('data.ringkasan.siswa', 2)
            ->assertJsonCount(1, 'data.kelas');

        $this->withToken($token)->getJson(route('api.v1.laporan-presensi-siswa.show', [
            'anggotaKelas' => $anggotaLain, ...$filter,
        ]))->assertNotFound();
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true,
        ]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A Laporan', 'tingkat' => 7, 'aktif' => true]);
        $siswaHadir = Siswa::create(['nama_lengkap' => 'Siswa Hadir Laporan', 'nis' => 'L001', 'nisn' => '7700000001', 'aktif' => true]);
        $siswaBelum = Siswa::create(['nama_lengkap' => 'Siswa Alfa Laporan', 'nis' => 'L002', 'nisn' => '7700000002', 'aktif' => true]);
        $anggotaHadir = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswaHadir->id,
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
            'anggota_kelas_id' => $anggotaHadir->id, 'siswa_id' => $siswaHadir->id, 'jam_masuk' => '06:50',
            'status_masuk' => 'tepat_waktu', 'menit_terlambat' => 0, 'status_kehadiran' => 'hadir', 'sumber' => 'scan',
        ]);

        return compact('tahun', 'kelas', 'anggotaHadir', 'anggotaBelum');
    }
}
