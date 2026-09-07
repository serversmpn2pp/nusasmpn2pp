<?php

namespace Tests\Feature\Api;

use App\Models\AbsensiPegawai;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NotifikasiPengguna;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\AkunOrangTuaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BerandaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 08:15:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_beranda_mobile_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.beranda'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Akun Tanpa Izin',
            'username' => 'tanpa.izin',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.beranda'))
            ->assertForbidden();
    }

    public function test_beranda_pegawai_mengembalikan_ringkasan_pribadi_dan_notifikasi_miliknya(): void
    {
        [$pengguna, $pegawai] = $this->buatAkunPegawai();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);

        AbsensiPegawai::create([
            'tanggal' => '2026-08-22',
            'pegawai_id' => $pegawai->id,
            'status_kehadiran' => 'hadir',
            'jam_masuk' => '07:05:00',
            'jam_pulang' => '15:00:00',
            'menit_terlambat' => 5,
        ]);
        AbsensiPegawai::create([
            'tanggal' => '2026-08-23',
            'pegawai_id' => $pegawai->id,
            'status_kehadiran' => 'sakit',
        ]);
        AbsensiPegawai::create([
            'tanggal' => '2026-08-24',
            'pegawai_id' => $pegawai->id,
            'status_kehadiran' => 'hadir',
            'jam_masuk' => '06:55:00',
        ]);
        JadwalPiketGuru::create([
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $pegawai->id,
            'hari' => 'senin',
            'aktif' => true,
            'keterangan' => 'Piket gerbang utama',
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_id' => $pegawai->id,
            'nama' => 'VII A',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $jadwalUtama = $this->buatJadwalPelajaran($tahun, $kelas, $pegawai);
        foreach ([
            [1, '07:00:00', '07:40:00'],
            [3, '09:10:00', '10:00:00'],
            [4, '10:10:00', '11:00:00'],
        ] as [$nomor, $mulai, $selesai]) {
            $jamTambahan = JamPelajaran::create([
                'hari' => 'senin',
                'nomor_jam' => $nomor,
                'label' => 'Jam '.$nomor,
                'jam_mulai' => $mulai,
                'jam_selesai' => $selesai,
                'jenis' => 'pelajaran',
                'aktif' => true,
            ]);
            JadwalPelajaran::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'hari' => 'senin',
                'jam_pelajaran_id' => $jamTambahan->id,
                'guru_mata_pelajaran_id' => $jadwalUtama->guru_mata_pelajaran_id,
                'aktif' => true,
            ]);
        }

        foreach (range(1, 11) as $urutan) {
            NotifikasiPengguna::create([
                'pengguna_id' => $pengguna->id,
                'jenis' => $urutan === 11 ? 'penting' : 'informasi',
                'judul' => "Notifikasi mobile {$urutan}",
                'pesan' => "Isi notifikasi {$urutan}.",
                'tautan' => $urutan === 11 ? '/pengajuan-barang/77' : null,
                'dibaca_pada' => $urutan === 1 ? now() : null,
            ]);
        }

        $penggunaLain = Pengguna::where('username', 'administrator')->firstOrFail();
        NotifikasiPengguna::create([
            'pengguna_id' => $penggunaLain->id,
            'jenis' => 'penting',
            'judul' => 'Notifikasi pengguna lain',
            'pesan' => 'Tidak boleh tampil di perangkat pegawai.',
        ]);

        $response = $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.beranda'))
            ->assertOk()
            ->assertJsonPath('data.salam', 'Selamat pagi')
            ->assertJsonPath('data.tanggal.hari', 'Senin')
            ->assertJsonPath('data.tahun_pelajaran.nama', '2026/2027')
            ->assertJsonPath('data.pegawai.nip', $pegawai->nip)
            ->assertJsonPath('data.presensi.hari_ini.label_status', 'Hadir')
            ->assertJsonPath('data.presensi.hari_ini.jam_masuk', '06:55')
            ->assertJsonPath('data.presensi.bulan_ini.hadir', 2)
            ->assertJsonPath('data.presensi.bulan_ini.sakit', 1)
            ->assertJsonPath('data.presensi.bulan_ini.terlambat', 1)
            ->assertJsonPath('data.piket_hari_ini.label_hari', 'Senin')
            ->assertJsonPath('data.perwalian.jumlah_kelas', 1)
            ->assertJsonPath('data.jadwal_hari_ini.mode', 'guru')
            ->assertJsonPath('data.jadwal_hari_ini.judul', 'Jadwal Mengajar Hari Ini')
            ->assertJsonPath('data.jadwal_hari_ini.rute_aksi', '/jadwal-mengajar-saya')
            ->assertJsonPath('data.jadwal_hari_ini.items.0.waktu', '08:00 - 09:00')
            ->assertJsonPath('data.jadwal_hari_ini.items.0.judul', 'Bahasa Indonesia')
            ->assertJsonPath('data.jadwal_hari_ini.items.0.subjudul', 'VII A')
            ->assertJsonPath('data.jadwal_hari_ini.items.0.sedang_berlangsung', true)
            ->assertJsonCount(2, 'data.jadwal_hari_ini.items')
            ->assertJsonPath('data.jadwal_hari_ini.items.1.waktu', '09:10 - 10:00')
            ->assertJsonCount(4, 'data.jadwal_hari_ini.semua_items')
            ->assertJsonPath('data.notifikasi.jumlah_belum_dibaca', 10)
            ->assertJsonCount(10, 'data.notifikasi.terbaru')
            ->assertJsonPath('data.notifikasi.terbaru.0.judul', 'Notifikasi mobile 11')
            ->assertJsonPath('data.notifikasi.terbaru.0.tautan_mobile', '/pengajuan-barang/77')
            ->assertJsonMissing(['judul' => 'Notifikasi pengguna lain'])
            ->assertJsonStructure([
                'data' => [
                    'dihasilkan_pada',
                    'tanggal' => ['iso', 'hari', 'label', 'bulan'],
                    'pegawai',
                    'presensi' => ['hari_ini', 'bulan_ini'],
                    'piket_hari_ini',
                    'perwalian',
                    'jadwal_hari_ini' => [
                        'mode',
                        'judul',
                        'label_aksi',
                        'rute_aksi',
                        'pesan_kosong',
                        'items',
                        'semua_items',
                    ],
                    'notifikasi' => ['jumlah_belum_dibaca', 'terbaru'],
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_jadwal_hari_ini_siswa_dan_orang_tua_mengikuti_kelas_aktif(): void
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $guru = Pegawai::create([
            'nama_lengkap' => 'Guru Bahasa Indonesia',
            'nip' => '198008242026081002',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII B',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $this->buatJadwalPelajaran($tahun, $kelas, $guru);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Ananda Mobile',
            'nis' => '26001',
            'nisn' => '0011223344',
            'jenis_kelamin' => 'P',
            'nama_ayah' => 'Orang Tua Ananda',
            'nomor_wa_ayah' => '081234567890',
            'kontak_absensi_utama' => 'ayah',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        $akunSiswa = Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'username' => $siswa->nisn,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $akunSiswa->daftarPeran()->attach(Peran::where('kode', 'siswa')->firstOrFail());

        $this->withToken($this->token($akunSiswa))
            ->getJson(route('api.v1.beranda'))
            ->assertOk()
            ->assertJsonPath('data.jadwal_hari_ini.mode', 'siswa')
            ->assertJsonPath('data.jadwal_hari_ini.judul', 'Jadwal Hari Ini')
            ->assertJsonPath('data.jadwal_hari_ini.items.0.judul', 'Bahasa Indonesia')
            ->assertJsonPath('data.jadwal_hari_ini.items.0.subjudul', 'VIII B · Guru Bahasa Indonesia');

        $akunOrangTua = app(AkunOrangTuaService::class)->buat($siswa);
        $akunOrangTua->update(['wajib_ganti_kata_sandi' => false]);
        $this->app['auth']->forgetGuards();

        $this->withToken($this->token($akunOrangTua))
            ->getJson(route('api.v1.beranda'))
            ->assertOk()
            ->assertJsonPath('data.jadwal_hari_ini.mode', 'orang_tua')
            ->assertJsonPath('data.jadwal_hari_ini.items.0.judul', 'Bahasa Indonesia')
            ->assertJsonPath('data.jadwal_hari_ini.items.0.subjudul', 'Ananda Mobile · VIII B');
    }

    public function test_administrator_tanpa_data_pegawai_tetap_dapat_membuka_beranda_mobile(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.beranda'))
            ->assertOk()
            ->assertJsonPath('data.pegawai', null)
            ->assertJsonPath('data.presensi', null)
            ->assertJsonPath('data.perwalian', null)
            ->assertJsonPath('data.jadwal_hari_ini.mode', 'administrator')
            ->assertJsonPath('data.jadwal_hari_ini.judul', 'Pantauan Hari Ini')
            ->assertJsonCount(2, 'data.jadwal_hari_ini.items')
            ->assertJsonPath('data.jadwal_hari_ini.items.0.judul', 'Presensi Siswa')
            ->assertJsonPath('data.jadwal_hari_ini.items.1.judul', 'Presensi Pegawai');
    }

    public function test_pegawai_non_guru_mendapat_ringkasan_hari_ini_bukan_jadwal_pelajaran(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Tenaga Administrasi Mobile',
            'nip' => '199008242026081003',
            'jenis_pegawai' => 'Tenaga Kependidikan',
            'jabatan_utama' => 'Tenaga Administrasi',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'pegawai')->firstOrFail());

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.beranda'))
            ->assertOk()
            ->assertJsonPath('data.jadwal_hari_ini.mode', 'pegawai')
            ->assertJsonPath('data.jadwal_hari_ini.judul', 'Ringkasan Anda Hari Ini')
            ->assertJsonPath('data.jadwal_hari_ini.items.0.judul', 'Presensi Pegawai')
            ->assertJsonPath('data.jadwal_hari_ini.items.0.subjudul', 'Belum tercatat');
    }

    public function test_fitur_beranda_dikunci_sampai_kata_sandi_awal_diganti(): void
    {
        [$pengguna] = $this->buatAkunPegawai([
            'wajib_ganti_kata_sandi' => true,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.beranda'))
            ->assertStatus(428)
            ->assertJsonPath('wajib_ganti_kata_sandi', true);
    }

    private function buatAkunPegawai(array $atributPengguna = []): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Mobile Uji',
            'nip' => '198808242026081001',
            'email' => 'guru.mobile@example.test',
            'jenis_pegawai' => 'Guru',
            'jabatan_utama' => 'Guru Mata Pelajaran',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create(array_merge([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ], $atributPengguna));
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'pegawai')->firstOrFail());

        return [$pengguna, $pegawai];
    }

    private function buatJadwalPelajaran(
        TahunPelajaran $tahun,
        Kelas $kelas,
        Pegawai $guru,
    ): JadwalPelajaran {
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'BIN-'.$kelas->tingkat,
            'nama' => 'Bahasa Indonesia',
            'kelompok' => 'Pelajaran Umum',
            'tingkat' => $kelas->tingkat,
            'kkm' => 75,
            'urutan' => 1,
            'aktif' => true,
        ]);
        $penugasan = GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
        $jam = JamPelajaran::create([
            'hari' => 'senin',
            'nomor_jam' => 2,
            'label' => 'Jam 2',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '09:00:00',
            'jenis' => 'pelajaran',
            'aktif' => true,
        ]);

        return JadwalPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'hari' => 'senin',
            'jam_pelajaran_id' => $jam->id,
            'guru_mata_pelajaran_id' => $penugasan->id,
            'aktif' => true,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
