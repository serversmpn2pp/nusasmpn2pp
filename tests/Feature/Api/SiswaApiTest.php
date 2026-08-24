<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_siswa_mobile_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.siswa.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Pengguna Tanpa Izin Siswa',
            'username' => 'tanpa.izin.siswa',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.siswa.index'))
            ->assertForbidden();
    }

    public function test_administrator_dapat_mencari_memfilter_dan_memuat_paginasi_siswa(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        foreach (range(1, 7) as $nomor) {
            Siswa::create([
                'nama_lengkap' => sprintf('Putri Pengujian %02d', $nomor),
                'nis' => 'NIS-P-'.$nomor,
                'nisn' => 'NISN-P-'.$nomor,
                'jenis_kelamin' => 'P',
                'aktif' => true,
            ]);
        }

        Siswa::create([
            'nama_lengkap' => 'Putri Tidak Aktif',
            'nisn' => 'NISN-NONAKTIF',
            'aktif' => false,
        ]);
        Siswa::create([
            'nama_lengkap' => 'Siswa Di Luar Pencarian',
            'nisn' => 'NISN-LAIN',
            'aktif' => true,
        ]);

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.siswa.index', [
                'cari' => 'putri',
                'status' => 'aktif',
                'per_halaman' => 5,
            ]))
            ->assertOk()
            ->assertJsonCount(5, 'data.items')
            ->assertJsonPath('data.ringkasan.total', 9)
            ->assertJsonPath('data.ringkasan.aktif', 8)
            ->assertJsonPath('data.ringkasan.nonaktif', 1)
            ->assertJsonPath('data.filter.cari', 'putri')
            ->assertJsonPath('data.filter.status', 'aktif')
            ->assertJsonPath('data.paginasi.halaman', 1)
            ->assertJsonPath('data.paginasi.halaman_terakhir', 2)
            ->assertJsonPath('data.paginasi.total', 7)
            ->assertJsonPath('data.paginasi.ada_halaman_berikutnya', true)
            ->assertJsonMissing(['nama' => 'Putri Tidak Aktif'])
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'nama',
                            'nis',
                            'nisn',
                            'jenis_kelamin',
                            'foto_url',
                            'aktif',
                            'kelas_aktif',
                        ],
                    ],
                    'ringkasan' => ['total', 'aktif', 'nonaktif'],
                    'filter' => ['cari', 'status'],
                    'paginasi',
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_detail_siswa_memuat_identitas_keluarga_dan_kelas_aktif(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
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
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Alya Detail Mobile',
            'nis' => '8123',
            'nisn' => '0012345678',
            'nik' => '1374012345678901',
            'jenis_kelamin' => 'P',
            'tempat_lahir' => 'Padang Panjang',
            'tanggal_lahir' => '2012-04-15',
            'agama' => 'Islam',
            'nama_ayah' => 'Ayah Alya',
            'nomor_wa_ayah' => '081234567890',
            'nama_ibu' => 'Ibu Alya',
            'alamat' => 'Padang Panjang',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 4,
            'status_keanggotaan' => 'aktif',
        ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.siswa.show', $siswa))
            ->assertOk()
            ->assertJsonPath('data.nama', 'Alya Detail Mobile')
            ->assertJsonPath('data.tanggal_lahir', '2012-04-15')
            ->assertJsonPath('data.kelas_aktif.nama', 'VIII.A')
            ->assertJsonPath('data.kelas_aktif.nomor_absen', 1)
            ->assertJsonPath('data.kelas_aktif.tahun_pelajaran', '2026/2027')
            ->assertJsonPath('data.orang_tua.nama_ayah', 'Ayah Alya')
            ->assertJsonPath('data.alamat', 'Padang Panjang');
    }

    public function test_wali_kelas_hanya_dapat_melihat_siswa_dalam_kelasnya(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Wali Kelas Mobile',
            'nip' => '198001012010011099',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $wali = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => 'wali.kelas.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $wali->daftarPeran()->attach(Peran::where('kode', 'wali_kelas')->firstOrFail());

        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'aktif' => true,
        ]);
        $kelasWali = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_id' => $pegawai->id,
            'nama' => 'VII.WALI',
            'aktif' => true,
        ]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.LAIN',
            'aktif' => true,
        ]);
        $siswaWali = Siswa::create([
            'nama_lengkap' => 'Siswa Kelas Wali',
            'nisn' => 'NISN-WALI',
            'aktif' => true,
        ]);
        $siswaLain = Siswa::create([
            'nama_lengkap' => 'Siswa Kelas Lain',
            'nisn' => 'NISN-LAIN-WALI',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasWali->id,
            'siswa_id' => $siswaWali->id,
            'status_keanggotaan' => 'aktif',
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasLain->id,
            'siswa_id' => $siswaLain->id,
            'status_keanggotaan' => 'aktif',
        ]);

        $this->withToken($this->token($wali))
            ->getJson(route('api.v1.siswa.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonFragment(['nama' => 'Siswa Kelas Wali'])
            ->assertJsonMissing(['nama' => 'Siswa Kelas Lain']);

        $this->withToken($this->token($wali))
            ->getJson(route('api.v1.siswa.show', $siswaLain))
            ->assertForbidden();
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
