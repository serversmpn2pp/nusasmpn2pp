<?php

namespace Tests\Feature;

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

class AkunSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dapat_membuat_akun_siswa_dengan_nisn_dan_password_delapan_digit(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$siswa] = $this->buatSiswaDalamKelas('Siswa Akun', '0012345678');

        $this->actingAs($administrator)
            ->post(route('akun-siswa.store', $siswa))
            ->assertRedirect();

        $akun = $siswa->fresh()->pengguna;

        $this->assertNotNull($akun);
        $this->assertSame('0012345678', $akun->username);
        $this->assertMatchesRegularExpression('/^\d{8}$/', $akun->kata_sandi_awal);
        $this->assertTrue(Hash::check($akun->kata_sandi_awal, $akun->kata_sandi));
        $this->assertTrue($akun->wajib_ganti_kata_sandi);
        $this->assertTrue($akun->memilikiPeran('siswa'));
    }

    public function test_pembuatan_massal_hanya_membuat_siswa_yang_memiliki_nisn_dan_belum_punya_akun(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [, $kelas] = $this->buatSiswaDalamKelas('Siswa Pertama', '0011111111');
        $this->tambahkanSiswaKeKelas($kelas, 'Siswa Kedua', '0022222222', 2);
        $tanpaNisn = $this->tambahkanSiswaKeKelas($kelas, 'Tanpa NISN', null, 3);

        $this->actingAs($administrator)
            ->post(route('akun-siswa.buat-massal', $kelas))
            ->assertRedirect()
            ->assertSessionHas('ringkasan_akun_siswa', function ($ringkasan) {
                return $ringkasan['dibuat'] === 2 && $ringkasan['dilewati'] === 1;
            });

        $this->assertSame(2, Pengguna::whereNotNull('siswa_id')->count());
        $this->assertNull($tanpaNisn->fresh()->pengguna);
    }

    public function test_reset_password_membuat_password_acak_baru_dan_mewajibkan_ganti_password(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$siswa] = $this->buatSiswaDalamKelas('Siswa Reset', '0033333333');
        $akun = app(AkunSiswaService::class)->buat($siswa);
        $passwordLama = $akun->kata_sandi_awal;

        $this->actingAs($administrator)
            ->patch(route('akun-siswa.reset-password', $akun))
            ->assertRedirect();

        $akun->refresh();

        $this->assertMatchesRegularExpression('/^\d{8}$/', $akun->kata_sandi_awal);
        $this->assertNotSame($passwordLama, $akun->kata_sandi_awal);
        $this->assertTrue(Hash::check($akun->kata_sandi_awal, $akun->kata_sandi));
        $this->assertTrue($akun->wajib_ganti_kata_sandi);
    }

    public function test_daftar_cetak_menampilkan_username_dan_password_awal_per_kelas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$siswa, $kelas] = $this->buatSiswaDalamKelas('Siswa Cetak', '0044444444');
        $akun = app(AkunSiswaService::class)->buat($siswa);

        $this->actingAs($administrator)
            ->get(route('akun-siswa.cetak', $kelas))
            ->assertOk()
            ->assertSee('Daftar Akun Siswa')
            ->assertSee('Siswa Cetak')
            ->assertSee('0044444444')
            ->assertSee($akun->kata_sandi_awal)
            ->assertSee('Cetak / Simpan PDF');
    }

    public function test_wali_kelas_hanya_dapat_melihat_dan_mencetak_akun_kelasnya(): void
    {
        [$wali, $akunWali] = $this->buatAkunWaliKelas();
        [, $kelasWali] = $this->buatSiswaDalamKelas('Siswa Kelas Wali', '0055555555', $wali);
        [$siswaLain, $kelasLain] = $this->buatSiswaDalamKelas('Siswa Kelas Lain', '0066666666');

        $this->actingAs($akunWali)
            ->get(route('akun-siswa.index'))
            ->assertOk()
            ->assertSee('Siswa Kelas Wali')
            ->assertDontSee('Siswa Kelas Lain');

        $this->actingAs($akunWali)
            ->get(route('akun-siswa.cetak', $kelasWali))
            ->assertOk();

        $this->actingAs($akunWali)
            ->get(route('akun-siswa.cetak', $kelasLain))
            ->assertForbidden();

        $this->actingAs($akunWali)
            ->post(route('akun-siswa.store', $siswaLain))
            ->assertForbidden();
    }

    public function test_siswa_wajib_mengganti_password_awal_dan_password_awal_dihapus_setelah_diganti(): void
    {
        [$siswa] = $this->buatSiswaDalamKelas('Siswa Login', '0077777777');
        $akun = app(AkunSiswaService::class)->buat($siswa);
        $passwordAwal = $akun->kata_sandi_awal;

        $this->post(route('login.store'), [
            'username' => '0077777777',
            'password' => $passwordAwal,
        ])->assertRedirect(route('beranda'));

        $this->get(route('beranda'))
            ->assertRedirect(route('kata-sandi.edit'));

        $this->put(route('kata-sandi.update'), [
            'kata_sandi_lama' => $passwordAwal,
            'kata_sandi_baru' => 'KataSandiBaru123',
            'kata_sandi_baru_confirmation' => 'KataSandiBaru123',
        ])->assertRedirect();

        $akun->refresh();

        $this->assertNull($akun->kata_sandi_awal);
        $this->assertFalse($akun->wajib_ganti_kata_sandi);
        $this->assertTrue(Hash::check('KataSandiBaru123', $akun->kata_sandi));
    }

    private function buatSiswaDalamKelas(
        string $nama,
        ?string $nisn,
        ?Pegawai $waliKelas = null,
    ): array {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027-'.fake()->unique()->numerify('###'),
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_id' => $waliKelas?->id,
            'nama' => 'VII.'.fake()->unique()->randomLetter(),
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = $this->tambahkanSiswaKeKelas($kelas, $nama, $nisn, 1);

        return [$siswa, $kelas, $tahun];
    }

    private function tambahkanSiswaKeKelas(
        Kelas $kelas,
        string $nama,
        ?string $nisn,
        int $nomorAbsen,
    ): Siswa {
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
            'nomor_absen' => $nomorAbsen,
            'status_keanggotaan' => 'aktif',
        ]);

        return $siswa;
    }

    private function buatAkunWaliKelas(): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Wali Kelas Akun Siswa',
            'nip' => '198001012010011001',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => Hash::make('rahasia-wali'),
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akun->daftarPeran()->sync([
            Peran::where('kode', 'pegawai')->value('id'),
            Peran::where('kode', 'wali_kelas')->value('id'),
        ]);

        return [$pegawai, $akun];
    }
}
