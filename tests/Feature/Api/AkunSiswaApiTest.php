<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\AkunSiswaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AkunSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dapat_melihat_daftar_filter_dan_detail_akun_siswa(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$kelasA, $kelasB] = $this->buatKelasAktif();
        $siswaA = $this->tambahSiswa($kelasA, 'Siswa Akun Mobile', '0012345601', 1);
        $siswaB = $this->tambahSiswa($kelasB, 'Siswa Belum Akun', '0012345602', 1);
        $akun = app(AkunSiswaService::class)->buat($siswaA);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->getJson(route('api.v1.akun-siswa.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.jumlah_siswa', 2)
            ->assertJsonPath('data.ringkasan.sudah_akun', 1)
            ->assertJsonPath('data.ringkasan.belum_akun', 1)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonPath('data.items.0.siswa.nama', 'Siswa Akun Mobile')
            ->assertJsonPath('data.items.0.akun.kata_sandi_awal', $akun->kata_sandi_awal);

        $this->withToken($token)
            ->getJson(route('api.v1.akun-siswa.index', [
                'kelas_id' => $kelasB->id,
                'status_akun' => 'belum',
                'cari' => 'Belum',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.siswa.id', $siswaB->id)
            ->assertJsonPath('data.filter.kelas_id', $kelasB->id);

        $this->withToken($token)
            ->getJson(route('api.v1.akun-siswa.show', $siswaA))
            ->assertOk()
            ->assertJsonPath('data.siswa.nisn', '0012345601')
            ->assertJsonPath('data.akun.wajib_ganti_kata_sandi', true);
    }

    public function test_wali_kelas_hanya_dapat_melihat_akun_siswa_di_kelasnya(): void
    {
        $wali = $this->buatWaliKelas();
        [$kelasWali, $kelasLain] = $this->buatKelasAktif($wali->pegawai);
        $siswaWali = $this->tambahSiswa($kelasWali, 'Siswa Kelas Wali API', '0012345611', 1);
        $siswaLain = $this->tambahSiswa($kelasLain, 'Siswa Kelas Lain API', '0012345612', 1);
        app(AkunSiswaService::class)->buat($siswaWali);

        $this->withToken($this->token($wali))
            ->getJson(route('api.v1.akun-siswa.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.siswa.id', $siswaWali->id)
            ->assertJsonPath('data.hak_akses.dapat_kelola', false)
            ->assertJsonPath('data.hak_akses.dapat_melihat_kredensial', true);

        $this->withToken($this->token($wali))
            ->getJson(route('api.v1.akun-siswa.show', $siswaLain))
            ->assertNotFound();

        $this->withToken($this->token($wali))
            ->postJson(route('api.v1.akun-siswa.store', $siswaLain))
            ->assertForbidden();
    }

    public function test_administrator_dapat_membuat_mereset_dan_mengubah_status_akun_siswa(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$kelas] = $this->buatKelasAktif();
        $siswa = $this->tambahSiswa($kelas, 'Siswa Kelola API', '0012345621', 1);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->postJson(route('api.v1.akun-siswa.store', $siswa))
            ->assertCreated()
            ->assertJsonPath('data.username', '0012345621');

        $akun = $siswa->fresh()->pengguna;
        $kataSandiLama = $akun->kata_sandi_awal;

        $this->withToken($token)
            ->patchJson(route('api.v1.akun-siswa.reset-kata-sandi', $siswa))
            ->assertOk();
        $akun->refresh();
        $this->assertNotSame($kataSandiLama, $akun->kata_sandi_awal);
        $this->assertMatchesRegularExpression('/^\d{8}$/', $akun->kata_sandi_awal);
        $this->assertTrue(Hash::check($akun->kata_sandi_awal, $akun->kata_sandi));

        $this->withToken($token)
            ->patchJson(route('api.v1.akun-siswa.status', $siswa), ['aktif' => false])
            ->assertOk();
        $this->assertFalse($akun->fresh()->aktif);
    }

    public function test_pembuatan_massal_membuat_akun_yang_layak_dalam_kelas_terpilih(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$kelas] = $this->buatKelasAktif();
        $layak = $this->tambahSiswa($kelas, 'Siswa Massal Layak', '0012345631', 1);
        $tanpaNisn = $this->tambahSiswa($kelas, 'Siswa Massal Tanpa NISN', null, 2);

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.akun-siswa.store-massal', $kelas))
            ->assertOk()
            ->assertJsonPath('data.dibuat', 1)
            ->assertJsonPath('data.dilewati', 1);

        $this->assertNotNull($layak->fresh()->pengguna);
        $this->assertNull($tanpaNisn->fresh()->pengguna);
    }

    private function buatKelasAktif(?Pegawai $waliKelas = null): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027-'.fake()->unique()->numerify('###'),
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);

        return [
            Kelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'wali_kelas_id' => $waliKelas?->id,
                'nama' => 'VII.A',
                'tingkat' => 7,
                'kapasitas' => 32,
                'aktif' => true,
            ]),
            Kelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'VII.B',
                'tingkat' => 7,
                'kapasitas' => 32,
                'aktif' => true,
            ]),
        ];
    }

    private function tambahSiswa(Kelas $kelas, string $nama, ?string $nisn, int $absen): Siswa
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => fake()->unique()->numerify('26#####'),
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $absen,
            'status_keanggotaan' => 'aktif',
        ]);

        return $siswa;
    }

    private function buatWaliKelas(): Pengguna
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Wali Kelas API Akun Siswa',
            'nip' => '198001012010011099',
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $akun->daftarPeran()->sync([
            Peran::where('kode', 'pegawai')->value('id'),
            Peran::where('kode', 'wali_kelas')->value('id'),
        ]);

        return $akun->load('pegawai');
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
