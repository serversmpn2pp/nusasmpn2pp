<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalKegiatanIbadah;
use App\Models\JadwalPiketGuru;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\PresensiBerhalanganIbadah;
use App\Models\PresensiKegiatanIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesScanKegiatanIbadah;
use App\Services\Ibadah\ProsesScanKegiatanIbadah;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanKegiatanIbadahTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_administrator_dapat_membuka_halaman_kamera_dan_mencatat_presensi(): void
    {
        Carbon::setTestNow('2026-08-13 12:10:00');
        $data = $this->dataDasar('kamis');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('scan-kegiatan-ibadah.index'))
            ->assertOk()
            ->assertSee('Scan QR Kartu Pelajar')
            ->assertSee('Mulai kamera')
            ->assertSee($data['kegiatan']->nama);

        $this->postJson(route('scan-kegiatan-ibadah.store'), [
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'isi_scan' => $data['siswa']->nisn,
        ])->assertOk()
            ->assertJsonPath('berhasil', true)
            ->assertJsonPath('baru', true)
            ->assertJsonPath('siswa.nama_lengkap', $data['siswa']->nama_lengkap)
            ->assertJsonPath('siswa.kelas', $data['kelas']->nama);

        $this->assertTrue(PresensiKegiatanIbadah::query()
            ->where('kegiatan_ibadah_id', $data['kegiatan']->id)
            ->where('siswa_id', $data['siswa']->id)
            ->whereDate('tanggal', '2026-08-13')
            ->where('dipindai_oleh_pengguna_id', $administrator->id)
            ->exists());
    }

    public function test_scan_qr_yang_sama_tetap_hanya_membuat_satu_presensi(): void
    {
        $data = $this->dataDasar('senin');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $proses = app(ProsesScanKegiatanIbadah::class);
        $waktu = Carbon::parse('2026-08-10 12:10:00');

        $pertama = $proses->proses($data['jadwal'], $data['siswa']->nisn, $administrator, $waktu);
        $kedua = $proses->proses($data['jadwal'], $data['siswa']->nisn, $administrator, $waktu->copy()->addSeconds(2));

        $this->assertTrue($pertama['berhasil']);
        $this->assertTrue($pertama['baru']);
        $this->assertTrue($kedua['berhasil']);
        $this->assertFalse($kedua['baru']);
        $this->assertSame('sudah_tercatat', $kedua['status']);
        $this->assertDatabaseCount('presensi_kegiatan_ibadah', 1);
        $this->assertDatabaseCount('log_scan_kegiatan_ibadah', 2);
    }

    public function test_scan_ibadah_biasa_otomatis_menyelesaikan_periode_berhalangan(): void
    {
        $data = $this->dataDasar('senin');
        $data['siswa']->update(['jenis_kelamin' => 'P']);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $periode = PeriodeBerhalanganIbadah::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'siswa_id' => $data['siswa']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota']->id,
            'tanggal_mulai' => '2026-08-05',
            'status' => PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI,
            'batas_hari_konfirmasi' => 5,
            'perlu_konfirmasi_sejak' => '2026-08-10',
            'dimulai_oleh_pengguna_id' => $administrator->id,
        ]);
        PresensiBerhalanganIbadah::create([
            'periode_berhalangan_ibadah_id' => $periode->id,
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota']->id,
            'siswa_id' => $data['siswa']->id,
            'dipindai_oleh_pengguna_id' => $administrator->id,
            'tanggal' => '2026-08-10',
            'waktu_scan' => '11:50:00',
            'sumber' => 'kamera',
        ]);

        $hasil = app(ProsesScanKegiatanIbadah::class)->proses(
            $data['jadwal'],
            $data['siswa']->nisn,
            $administrator,
            Carbon::parse('2026-08-10 12:10:00'),
        );

        $this->assertTrue($hasil['berhasil']);
        $this->assertTrue($hasil['baru']);
        $this->assertNotNull($hasil['periode_berhalangan_ditutup']);
        $this->assertSame('Presensi ibadah berhasil dicatat.', $hasil['pesan']);
        $this->assertDatabaseHas('periode_berhalangan_ibadah', [
            'id' => $periode->id,
            'status' => PeriodeBerhalanganIbadah::STATUS_SELESAI,
            'cara_selesai' => 'scan_ibadah',
            'diselesaikan_oleh_pengguna_id' => $administrator->id,
        ]);
        $this->assertSame('2026-08-10', $periode->fresh()->tanggal_selesai->toDateString());
        $this->assertDatabaseCount('presensi_berhalangan_ibadah', 0);
        $this->assertDatabaseCount('presensi_kegiatan_ibadah', 1);
    }

    public function test_scan_di_luar_jadwal_dan_qr_tidak_valid_tidak_membuat_presensi(): void
    {
        $data = $this->dataDasar('senin');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $proses = app(ProsesScanKegiatanIbadah::class);

        $diLuarJadwal = $proses->proses(
            $data['jadwal'],
            $data['siswa']->nisn,
            $administrator,
            Carbon::parse('2026-08-10 14:00:00'),
        );
        $qrTidakValid = $proses->proses(
            $data['jadwal'],
            'BUKAN-NISN',
            $administrator,
            Carbon::parse('2026-08-10 12:00:00'),
        );

        $this->assertFalse($diLuarJadwal['berhasil']);
        $this->assertSame('di_luar_jadwal', $diLuarJadwal['status']);
        $this->assertFalse($qrTidakValid['berhasil']);
        $this->assertSame('format_tidak_valid', $qrTidakValid['status']);
        $this->assertDatabaseCount('presensi_kegiatan_ibadah', 0);
        $this->assertDatabaseCount('log_scan_kegiatan_ibadah', 2);
    }

    public function test_halaman_scan_dapat_diakses_guru_pai_dan_guru_yang_sedang_piket(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');
        $data = $this->dataDasar('senin');
        $pai = MataPelajaran::create([
            'kode' => 'PAI7',
            'nama' => 'Pendidikan Agama Islam',
            'kelompok' => 'Agama dan Budi Pekerti',
            'aktif' => true,
        ]);
        $guruPai = $this->buatGuru($data['tahun'], $data['kelas'], $pai, 'Guru PAI Uji', '197701012007011001');
        $matematika = MataPelajaran::create([
            'kode' => 'MTK7',
            'nama' => 'Matematika',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);
        $guruPiket = $this->buatGuru($data['tahun'], $data['kelas'], $matematika, 'Guru Piket Uji', '197801012008011002');
        $akses = app(AksesScanKegiatanIbadah::class);

        $this->assertTrue($akses->dapatMemindai($guruPai['akun'], $data['tahun'], now()));
        $this->assertFalse($akses->dapatMemindai($guruPiket['akun'], $data['tahun'], now()));

        JadwalPiketGuru::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'pegawai_id' => $guruPiket['pegawai']->id,
            'hari' => 'senin',
            'aktif' => true,
        ]);

        $this->assertTrue($akses->dapatMemindai($guruPiket['akun'], $data['tahun'], now()));
        $this->actingAs($guruPiket['akun'])
            ->get(route('scan-kegiatan-ibadah.index'))
            ->assertOk();
    }

    private function dataDasar(string $hari): array
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
            'nama_lengkap' => 'Siswa Presensi Duhur',
            'nis' => '26001',
            'nisn' => '0131201150',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        $kegiatan = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();
        $jadwal = JadwalKegiatanIbadah::create([
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => $hari,
            'urutan_hari' => JadwalKegiatanIbadah::DAFTAR_HARI[$hari]['urutan'],
            'jam_scan_mulai' => '11:30',
            'jam_pelaksanaan' => '12:00',
            'jam_scan_selesai' => '13:00',
            'aktif' => true,
        ]);

        return compact('tahun', 'kelas', 'siswa', 'anggota', 'kegiatan', 'jadwal');
    }

    private function buatGuru(TahunPelajaran $tahun, Kelas $kelas, MataPelajaran $mataPelajaran, string $nama, string $nip): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $pegawai->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::whereIn('kode', ['pegawai', 'guru_mapel'])->pluck('id'));

        return compact('pegawai', 'akun');
    }
}
