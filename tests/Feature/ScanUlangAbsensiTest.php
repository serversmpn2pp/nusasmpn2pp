<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\ProsesScanAbsensi;
use App\Services\Absensi\ProsesScanAbsensiPegawai;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanUlangAbsensiTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_ulang_siswa_mengembalikan_absensi_dan_waktu_yang_sudah_tercatat(): void
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
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Uji Scan Ulang',
            'nisn' => '0011223344',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);
        PengaturanAbsensi::create([
            'hari' => 'kamis',
            'urutan_hari' => 4,
            'jam_scan_masuk_mulai' => '06:00',
            'jam_masuk' => '07:00',
            'jam_scan_masuk_selesai' => '07:30',
            'jam_scan_pulang_mulai' => '14:00',
            'jam_pulang' => '14:10',
            'jam_scan_pulang_selesai' => '15:00',
            'aktif' => true,
        ]);

        $proses = app(ProsesScanAbsensi::class);
        $hasilPertama = $proses->proses($siswa->nisn, Carbon::parse('2026-08-06 06:25:15'), 'masuk');
        $hasilUlang = $proses->proses($siswa->nisn, Carbon::parse('2026-08-06 06:25:20'), 'masuk');

        $this->assertTrue($hasilPertama['berhasil']);
        $this->assertFalse($hasilUlang['berhasil']);
        $this->assertSame('duplikat_cepat', $hasilUlang['status']);
        $this->assertSame($hasilPertama['absensi']->id, $hasilUlang['absensi']->id);
        $this->assertSame('06:25:15', $hasilUlang['absensi']->jam_masuk);
        $this->assertSame(
            'Absensi masuk sudah tercatat pukul 06:25. Tidak perlu scan ulang.',
            $hasilUlang['pesan'],
        );
    }

    public function test_scan_ulang_pegawai_mengembalikan_absensi_dan_waktu_yang_sudah_tercatat(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Uji Scan Ulang',
            'nip' => '199001012015011001',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        PengaturanAbsensiPegawai::create([
            'nama_jadwal' => 'Jadwal Pegawai Kamis',
            'cakupan' => 'semua',
            'hari' => 'kamis',
            'urutan_hari' => 4,
            'jam_scan_masuk_mulai' => '06:00',
            'jam_masuk' => '07:00',
            'jam_scan_masuk_selesai' => '07:30',
            'jam_scan_pulang_mulai' => '13:00',
            'jam_pulang' => '14:00',
            'jam_scan_pulang_selesai' => '16:00',
            'aktif' => true,
        ]);

        $proses = app(ProsesScanAbsensiPegawai::class);
        $hasilPertama = $proses->proses($pegawai->nip, Carbon::parse('2026-08-06 06:30:10'), 'masuk');
        $hasilUlang = $proses->proses($pegawai->nip, Carbon::parse('2026-08-06 06:30:15'), 'masuk');

        $this->assertTrue($hasilPertama['berhasil']);
        $this->assertFalse($hasilUlang['berhasil']);
        $this->assertSame('duplikat_cepat', $hasilUlang['status']);
        $this->assertSame($hasilPertama['absensi']->id, $hasilUlang['absensi']->id);
        $this->assertSame('06:30:10', $hasilUlang['absensi']->jam_masuk);
        $this->assertSame(
            'Absensi masuk sudah tercatat pukul 06:30. Tidak perlu scan ulang.',
            $hasilUlang['pesan'],
        );
    }

    public function test_halaman_scan_memiliki_status_visual_yang_tidak_menyebut_scan_ulang_sebagai_gagal(): void
    {
        Carbon::setTestNow('2026-08-06 06:30:00');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        PengaturanAbsensi::create([
            'hari' => 'kamis',
            'urutan_hari' => 4,
            'jam_scan_masuk_mulai' => '06:00',
            'jam_masuk' => '07:00',
            'jam_scan_masuk_selesai' => '07:30',
            'jam_scan_pulang_mulai' => '14:00',
            'jam_pulang' => '14:10',
            'jam_scan_pulang_selesai' => '15:00',
            'aktif' => true,
        ]);
        PengaturanAbsensiPegawai::create([
            'nama_jadwal' => 'Jadwal Pegawai Kamis',
            'cakupan' => 'semua',
            'hari' => 'kamis',
            'urutan_hari' => 4,
            'jam_scan_masuk_mulai' => '03:00',
            'jam_masuk' => '06:25',
            'jam_scan_masuk_selesai' => '07:30',
            'jam_scan_pulang_mulai' => '13:00',
            'jam_pulang' => '13:00',
            'jam_scan_pulang_selesai' => '16:00',
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->get(route('scan-absensi.index'))
            ->assertOk()
            ->assertSee('Absensi sudah tercatat')
            ->assertSee('Belum waktunya scan')
            ->assertSee('Scan gagal')
            ->assertSeeInOrder([
                'Batas Tepat Waktu',
                '07:00',
                'Waktu scan masuk:',
                '06:00 - 07:30',
                'Jam Pulang Resmi',
                '14:10',
                'Waktu scan pulang:',
                '14:00 - 15:00',
            ]);

        $this->get(route('scan-absensi-pegawai.index'))
            ->assertOk()
            ->assertSee('Absensi sudah tercatat')
            ->assertSee('Belum waktunya scan')
            ->assertSee('Scan gagal')
            ->assertSeeInOrder([
                'Batas Tepat Waktu',
                '06:25',
                'Waktu scan masuk:',
                '03:00 - 07:30',
                'Jam Pulang Resmi',
                '13:00',
                'Waktu scan pulang:',
                '13:00 - 16:00',
            ]);

        Carbon::setTestNow();
    }
}
