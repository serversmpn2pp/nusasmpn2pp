<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\JadwalUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PusatCbtApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pusat_cbt_memerlukan_token(): void
    {
        $this->getJson(route('api.v1.pusat-cbt'))->assertUnauthorized();
    }

    public function test_administrator_menerima_fondasi_pengelolaan_cbt(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pusat-cbt'))
            ->assertOk()
            ->assertJsonPath('data.akses.dapat_mengelola', true)
            ->assertJsonPath('data.akses.memiliki_tugas_pengawas', false)
            ->assertJsonPath('data.akses.akun_siswa', false)
            ->assertJsonStructure([
                'data' => [
                    'pengelolaan' => [
                        'ringkasan' => [
                            'soal_siap',
                            'kegiatan_terpusat',
                            'asesmen_kelas',
                            'paket_terjadwal',
                        ],
                        'alur',
                        'alat',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'kode' => 'asesmen-kelas',
                'status' => 'tersedia',
                'rute' => '/asesmen-kelas',
            ])
            ->assertJsonFragment([
                'kode' => 'bank-soal',
                'status' => 'tersedia',
                'rute' => '/bank-soal',
            ])
            ->assertJsonFragment([
                'kode' => 'paket-soal',
                'status' => 'tersedia',
                'rute' => '/paket-soal',
            ])
            ->assertJsonFragment([
                'kode' => 'ujian-terpusat',
                'status' => 'tersedia',
                'rute' => '/pelaksanaan-ujian-terpusat',
            ])
            ->assertJsonFragment([
                'kode' => 'hasil-ujian-terpusat',
                'status' => 'tersedia',
                'rute' => '/hasil-ujian-terpusat',
            ])
            ->assertJsonPath('data.pengawas', null)
            ->assertJsonPath('data.siswa', null);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_siswa_hanya_menerima_ringkasan_ujian_miliknya(): void
    {
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa CBT Mobile',
            'nis' => 'CBT26001',
            'nisn' => '0099002601',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'username' => $siswa->nisn,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.A',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'MTK-CBT-M',
            'nama' => 'Matematika',
            'tingkat' => 8,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $ujian = UjianCbt::create([
            'alur' => 'kelas',
            'jenis_ujian_cbt_id' => JenisUjianCbt::query()->firstOrFail()->id,
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'kode' => 'CBT-MOBILE-SISWA',
            'nama' => 'Asesmen Matematika Bab 1',
            'semester' => 'ganjil',
            'tingkat' => 8,
            'tanggal_mulai' => now()->subMinutes(15),
            'tanggal_selesai' => now()->addHour(),
            'durasi_menit' => 75,
            'jumlah_soal' => 20,
            'status' => 'berlangsung',
        ]);
        $kelasUjian = KelasUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_id' => $kelas->id,
        ]);
        PesertaUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_ujian_cbt_id' => $kelasUjian->id,
            'anggota_kelas_id' => $anggota->id,
            'nomor_peserta' => 'CBT-26001',
            'status' => 'aktif',
            'menit_tersisa' => 75,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.pusat-cbt'))
            ->assertOk()
            ->assertJsonPath('data.akses.dapat_mengelola', false)
            ->assertJsonPath('data.akses.akun_siswa', true)
            ->assertJsonPath('data.siswa.ringkasan.aktif', 1)
            ->assertJsonPath('data.siswa.ringkasan.total', 1)
            ->assertJsonPath('data.siswa.items.0.nama', 'Asesmen Matematika Bab 1')
            ->assertJsonPath('data.siswa.items.0.mata_pelajaran', 'Matematika')
            ->assertJsonPath('data.siswa.items.0.label_status', 'Siap dimulai')
            ->assertJsonPath('data.siswa.pengerjaan_native', true)
            ->assertJsonPath('data.pengawas', null)
            ->assertJsonPath('data.pengelolaan', null);
    }

    public function test_pegawai_yang_ditugaskan_menerima_ringkasan_pengawas_dan_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pengawas CBT Mobile',
            'nip' => '198901012026091001',
            'jenis_kelamin' => 'P',
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
        [$jadwal, $ruang] = $this->buatJadwalDanRuang($administrator);
        PengawasRuangUjianTerpusat::create([
            'jadwal_ujian_cbt_id' => $jadwal->id,
            'ruang_kegiatan_ujian_cbt_id' => $ruang->id,
            'pengawas_utama_pegawai_id' => $pegawai->id,
            'ditugaskan_oleh_pengguna_id' => $administrator->id,
        ]);

        $token = $this->token($pengguna);
        $this->withToken($token)
            ->getJson(route('api.v1.pusat-cbt'))
            ->assertOk()
            ->assertJsonPath('data.akses.dapat_mengelola', false)
            ->assertJsonPath('data.akses.memiliki_tugas_pengawas', true)
            ->assertJsonPath('data.pengawas.ringkasan.jumlah', 1)
            ->assertJsonPath('data.pengawas.ringkasan.hari_ini', 1)
            ->assertJsonPath('data.pengawas.items.0.ruang', 'Labor Komputer 1')
            ->assertJsonPath('data.pengawas.items.0.peran', 'Pengawas utama')
            ->assertJsonPath('data.pengawas.operasional_native', true)
            ->assertJsonPath('data.pengelolaan', null);

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'tugas-pengawas-saya',
                'status' => 'tersedia',
                'rute' => '/tugas-pengawas-ujian',
            ])
            ->assertJsonMissing(['kode' => 'pusat-cbt']);
    }

    public function test_pengguna_tanpa_akses_cbt_ditolak(): void
    {
        $pengguna = Pengguna::create([
            'nama' => 'Pengguna Tanpa CBT',
            'username' => 'tanpa.cbt.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.pusat-cbt'))
            ->assertForbidden();
    }

    private function buatJadwalDanRuang(Pengguna $administrator): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $jenis = JenisUjianCbt::query()->firstOrFail();
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kode' => 'CBT-MOBILE-001',
            'nama' => 'Simulasi Ujian Sekolah',
            'semester' => 'ganjil',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'status' => 'aktif',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        $jadwal = JadwalUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'tanggal' => now()->toDateString(),
            'waktu_mulai' => '07:30',
            'waktu_selesai' => '09:00',
            'label_sesi' => 'Sesi Pagi',
            'tingkat' => 9,
            'urutan' => 1,
            'status' => 'siap',
        ]);
        $ruang = RuangKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'kode' => 'LAB-1',
            'nama' => 'Labor Komputer 1',
            'kapasitas' => 30,
            'urutan' => 1,
            'aktif' => true,
        ]);

        return [$jadwal, $ruang];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
