<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NotifikasiPengguna;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardSiswaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_siswa_hanya_menampilkan_data_milik_siswa_yang_login(): void
    {
        Carbon::setTestNow('2026-07-27 08:00:00');

        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $waliKelas = $this->buatPegawai('Wali Kelas VII.A', '198001012010011001');
        $guruMatematika = $this->buatPegawai('Guru Matematika', '198101012010011002');
        $kelasSiswa = $this->buatKelas($tahun, 'VII.A', $waliKelas);
        $kelasLain = $this->buatKelas($tahun, 'VII.B');
        [$siswa, $anggota, $akun] = $this->buatSiswaBerakun($kelasSiswa, 'Siswa Dashboard', '0012345678', 1);
        [$siswaLain, $anggotaLain, $akunLain] = $this->buatSiswaBerakun($kelasLain, 'Siswa Kelas Lain', '0099999999', 1);

        $matematika = MataPelajaran::create([
            'kode' => 'MTK-7',
            'nama' => 'Matematika Kelas VII',
            'tingkat' => 7,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $guruMapel = GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasSiswa->id,
            'mata_pelajaran_id' => $matematika->id,
            'pegawai_id' => $guruMatematika->id,
            'aktif' => true,
        ]);
        $jam = JamPelajaran::create([
            'hari' => 'senin',
            'nomor_jam' => 1,
            'label' => 'Jam 1',
            'jam_mulai' => '07:30',
            'jam_selesai' => '08:10',
            'jenis' => 'pelajaran',
            'aktif' => true,
        ]);
        JadwalPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasSiswa->id,
            'hari' => 'senin',
            'jam_pelajaran_id' => $jam->id,
            'guru_mata_pelajaran_id' => $guruMapel->id,
            'aktif' => true,
        ]);

        AbsensiSiswa::create([
            'tanggal' => '2026-07-27',
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasSiswa->id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $siswa->id,
            'jam_masuk' => '07:07',
            'status_masuk' => 'terlambat',
            'menit_terlambat' => 7,
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);
        AbsensiSiswa::create([
            'tanggal' => '2026-07-27',
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasLain->id,
            'anggota_kelas_id' => $anggotaLain->id,
            'siswa_id' => $siswaLain->id,
            'status_kehadiran' => 'alfa',
            'sumber' => 'manual',
        ]);

        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kunci_sumber' => 'dashboard-siswa-pelanggaran',
            'jenis' => 'pelanggaran',
            'poin' => 15,
            'keterangan' => 'Pelanggaran yang sudah disahkan',
            'tercatat_pada' => now(),
        ]);
        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kunci_sumber' => 'dashboard-siswa-pengurangan',
            'jenis' => 'pengurangan',
            'poin' => -5,
            'keterangan' => 'Pengurangan poin siswa login',
            'tercatat_pada' => now()->subMinute(),
        ]);
        TransaksiPoinSiswa::create([
            'siswa_id' => $siswaLain->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kunci_sumber' => 'dashboard-siswa-lain',
            'jenis' => 'pelanggaran',
            'poin' => 100,
            'keterangan' => 'Catatan rahasia siswa lain',
            'tercatat_pada' => now(),
        ]);

        NotifikasiPengguna::create([
            'pengguna_id' => $akun->id,
            'jenis' => 'informasi',
            'judul' => 'Informasi untuk siswa login',
            'pesan' => 'Pesan ini hanya untuk pemilik akun.',
        ]);
        NotifikasiPengguna::create([
            'pengguna_id' => $akunLain->id,
            'jenis' => 'penting',
            'judul' => 'Notifikasi siswa lain',
            'pesan' => 'Tidak boleh terlihat oleh siswa login.',
        ]);

        $this->actingAs($akun)
            ->get(route('beranda'))
            ->assertOk()
            ->assertViewIs('beranda.siswa')
            ->assertViewHas('siswaLogin', fn ($nilai) => $nilai->is($siswa))
            ->assertViewHas('kelasAktif', fn ($nilai) => $nilai->is($kelasSiswa))
            ->assertViewHas('ringkasanKehadiran', fn (array $nilai) => $nilai['hadir'] === 1
                && $nilai['alfa'] === 0
                && $nilai['terlambat'] === 1
                && $nilai['menit_terlambat'] === 7)
            ->assertViewHas('ringkasanPoin', fn (array $nilai) => $nilai['total'] === 10
                && $nilai['pelanggaran'] === 1
                && $nilai['pengurangan'] === 5)
            ->assertSee('Matematika Kelas VII')
            ->assertSee('Pelanggaran yang sudah disahkan')
            ->assertSee('Informasi untuk siswa login')
            ->assertSee('Ganti Password')
            ->assertDontSee('Siswa Kelas Lain')
            ->assertDontSee('Catatan rahasia siswa lain')
            ->assertDontSee('Notifikasi siswa lain')
            ->assertDontSee('Akun Pegawai')
            ->assertDontSee('Akun Siswa')
            ->assertDontSee('Data Saya');
    }

    public function test_akun_dengan_role_siswa_tanpa_relasi_siswa_tetap_mendapat_dashboard_yang_aman(): void
    {
        $akun = Pengguna::create([
            'nama' => 'Akun Siswa Belum Terhubung',
            'username' => 'siswa-belum-terhubung',
            'kata_sandi' => Hash::make('rahasia'),
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $akun->daftarPeran()->sync([
            Peran::where('kode', 'siswa')->value('id'),
        ]);

        $this->actingAs($akun)
            ->get(route('beranda'))
            ->assertOk()
            ->assertViewIs('beranda.siswa')
            ->assertSee('Akun belum terhubung ke data siswa')
            ->assertDontSee('Dashboard Pegawai')
            ->assertDontSee('Akun Pegawai');
    }

    private function buatKelas(TahunPelajaran $tahun, string $nama, ?Pegawai $waliKelas = null): Kelas
    {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_id' => $waliKelas?->id,
            'nama' => $nama,
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
    }

    private function buatPegawai(string $nama, string $nip): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
    }

    private function buatSiswaBerakun(Kelas $kelas, string $nama, string $nisn, int $nomorAbsen): array
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => '26'.$nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $nomorAbsen,
            'status_keanggotaan' => 'aktif',
        ]);
        $akun = Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $nama,
            'username' => $nisn,
            'kata_sandi' => Hash::make('rahasia'),
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $akun->daftarPeran()->sync([
            Peran::where('kode', 'siswa')->value('id'),
        ]);

        return [$siswa, $anggota, $akun];
    }
}
