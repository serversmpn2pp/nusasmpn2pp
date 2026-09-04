<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\ProsesScanAbsensi;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalPulangJumatSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_pulang_jumat_mengikuti_jenis_kelamin_siswa(): void
    {
        [$siswi, $siswaLakiLaki, $siswaTanpaJenisKelamin] = $this->siapkanSiswaDanJadwal();
        $proses = app(ProsesScanAbsensi::class);

        $hasilSiswi = $proses->proses($siswi->nisn, Carbon::parse('2026-09-04 12:00:00'));
        $hasilLakiLakiTerlaluAwal = $proses->proses($siswaLakiLaki->nisn, Carbon::parse('2026-09-04 12:00:00'));
        $hasilTanpaJenisKelamin = $proses->proses($siswaTanpaJenisKelamin->nisn, Carbon::parse('2026-09-04 12:00:00'));

        $this->assertTrue($hasilSiswi['berhasil']);
        $this->assertSame('berhasil_pulang', $hasilSiswi['status']);
        $this->assertSame('normal', $hasilSiswi['status_pulang']);

        $this->assertFalse($hasilLakiLakiTerlaluAwal['berhasil']);
        $this->assertSame('pulang_jumat_belum_dibuka', $hasilLakiLakiTerlaluAwal['status']);
        $this->assertSame('pulang', $hasilLakiLakiTerlaluAwal['jenis_scan']);
        $this->assertStringContainsString('mulai pukul 12:50', $hasilLakiLakiTerlaluAwal['pesan']);
        $this->assertDatabaseMissing('absensi_siswa', [
            'siswa_id' => $siswaLakiLaki->id,
            'tanggal' => '2026-09-04',
        ]);

        $this->assertFalse($hasilTanpaJenisKelamin['berhasil']);
        $this->assertSame('pulang_jumat_belum_dibuka', $hasilTanpaJenisKelamin['status']);
        $this->assertStringContainsString('Jenis kelamin siswa belum dilengkapi', $hasilTanpaJenisKelamin['pesan']);

        $hasilLakiLaki = $proses->proses($siswaLakiLaki->nisn, Carbon::parse('2026-09-04 12:50:00'));

        $this->assertTrue($hasilLakiLaki['berhasil']);
        $this->assertSame('berhasil_pulang', $hasilLakiLaki['status']);
        $this->assertSame('normal', $hasilLakiLaki['status_pulang']);
        $this->assertDatabaseHas('log_scan_absensi', [
            'siswa_id' => $siswaLakiLaki->id,
            'status_scan' => 'pulang_jumat_belum_dibuka',
            'berhasil' => false,
        ]);
    }

    public function test_pengaturan_dan_monitor_scan_menampilkan_dua_jadwal_pulang_jumat(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('pengaturan-absensi.store'), [
                'hari' => 'jumat',
                'jam_scan_masuk_mulai' => '05:30',
                'jam_masuk' => '06:25',
                'jam_scan_masuk_selesai' => '07:00',
                'jam_scan_pulang_mulai' => '12:50',
                'jam_pulang' => '12:50',
                'jam_scan_pulang_selesai' => '14:00',
                'pulang_jumat_dibedakan' => '1',
                'jam_scan_pulang_perempuan_mulai' => '11:50',
                'jam_pulang_perempuan' => '11:50',
                'jam_scan_pulang_perempuan_selesai' => '14:00',
                'aktif' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pengaturan_absensi', [
            'hari' => 'jumat',
            'pulang_jumat_dibedakan' => true,
            'jam_scan_pulang_mulai' => '12:50',
            'jam_scan_pulang_perempuan_mulai' => '11:50',
        ]);

        $this->get(route('scan-absensi.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Jam Pulang Jumat',
                'Siswi',
                '11:50',
                'Scan 11:50 - 14:00',
                'Siswa laki-laki',
                '12:50',
                'Scan 12:50 - 14:00',
            ])
            ->assertSee('Pulang siswi')
            ->assertSee('pulang_jumat_belum_dibuka');

        Carbon::setTestNow();
    }

    /** @return array{Siswa, Siswa, Siswa} */
    private function siapkanSiswaDanJadwal(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $siswi = $this->buatSiswa($tahun, $kelas, 'Siswi Jumat', '7700000001', 'P', 1);
        $siswaLakiLaki = $this->buatSiswa($tahun, $kelas, 'Siswa Jumat', '7700000002', 'L', 2);
        $siswaTanpaJenisKelamin = $this->buatSiswa($tahun, $kelas, 'Siswa Belum Lengkap', '7700000003', null, 3);

        PengaturanAbsensi::create([
            'hari' => 'jumat',
            'urutan_hari' => 5,
            'jam_scan_masuk_mulai' => '05:30',
            'jam_masuk' => '06:25',
            'jam_scan_masuk_selesai' => '07:00',
            'jam_scan_pulang_mulai' => '12:50',
            'jam_pulang' => '12:50',
            'jam_scan_pulang_selesai' => '14:00',
            'pulang_jumat_dibedakan' => true,
            'jam_scan_pulang_perempuan_mulai' => '11:50',
            'jam_pulang_perempuan' => '11:50',
            'jam_scan_pulang_perempuan_selesai' => '14:00',
            'aktif' => true,
        ]);

        return [$siswi, $siswaLakiLaki, $siswaTanpaJenisKelamin];
    }

    private function buatSiswa(
        TahunPelajaran $tahun,
        Kelas $kelas,
        string $nama,
        string $nisn,
        ?string $jenisKelamin,
        int $nomorAbsen,
    ): Siswa {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nisn' => $nisn,
            'jenis_kelamin' => $jenisKelamin,
            'aktif' => true,
        ]);

        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $nomorAbsen,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);

        return $siswa;
    }
}
