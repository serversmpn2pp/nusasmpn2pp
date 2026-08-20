<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\AkunOrangTuaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AkunOrangTuaTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dapat_membuat_akun_orang_tua_dari_nisn_siswa(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$siswa] = $this->buatSiswaDalamKelas('Anak Orang Tua', '0012345678');
        $siswa->update([
            'nama_ayah' => 'Ayah Anak Orang Tua',
            'nomor_wa_ayah' => '081234567890',
            'kontak_absensi_utama' => 'ayah',
        ]);

        $this->actingAs($administrator)
            ->post(route('akun-orang-tua.store', $siswa))
            ->assertRedirect();

        $orangTua = $siswa->fresh()->orangTuaWali()->with('pengguna')->firstOrFail();
        $akun = $orangTua->pengguna;

        $this->assertSame('Ayah Anak Orang Tua', $orangTua->nama_lengkap);
        $this->assertSame('081234567890', $orangTua->nomor_wa);
        $this->assertSame('ORT-0012345678', $akun->username);
        $this->assertSame('ayah', $orangTua->pivot->hubungan);
        $this->assertTrue((bool) $orangTua->pivot->utama);
        $this->assertMatchesRegularExpression('/^\d{8}$/', $akun->kata_sandi_awal);
        $this->assertTrue(Hash::check($akun->kata_sandi_awal, $akun->kata_sandi));
        $this->assertTrue($akun->wajib_ganti_kata_sandi);
        $this->assertTrue($akun->memilikiPeran('orang_tua'));
    }

    public function test_pembuatan_massal_membuat_satu_akun_per_siswa_dan_melewati_nisn_kosong(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$siswaPertama, $kelas] = $this->buatSiswaDalamKelas('Siswa Pertama', '0011111111');
        $this->tambahkanSiswaKeKelas($kelas, 'Siswa Kedua', '0022222222', 2);
        $this->tambahkanSiswaKeKelas($kelas, 'Tanpa NISN', null, 3);
        app(AkunOrangTuaService::class)->buat($siswaPertama);

        $this->actingAs($administrator)
            ->post(route('akun-orang-tua.buat-massal', $kelas))
            ->assertRedirect()
            ->assertSessionHas('ringkasan_akun_orang_tua', function ($ringkasan) {
                return $ringkasan['dibuat'] === 1 && $ringkasan['dilewati'] === 2;
            });

        $this->assertDatabaseCount('orang_tua_wali', 2);
        $this->assertDatabaseCount('orang_tua_wali_siswa', 2);
    }

    public function test_administrator_dapat_reset_password_dan_mengubah_status_akun_orang_tua(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$siswa] = $this->buatSiswaDalamKelas('Siswa Reset', '0033333333');
        $akun = app(AkunOrangTuaService::class)->buat($siswa);
        $passwordLama = $akun->kata_sandi_awal;

        $this->actingAs($administrator)
            ->patch(route('akun-orang-tua.reset-password', $akun))
            ->assertRedirect();

        $akun->refresh();
        $this->assertMatchesRegularExpression('/^\d{8}$/', $akun->kata_sandi_awal);
        $this->assertNotSame($passwordLama, $akun->kata_sandi_awal);
        $this->assertTrue($akun->wajib_ganti_kata_sandi);

        $this->actingAs($administrator)
            ->patch(route('akun-orang-tua.status', $akun))
            ->assertRedirect();

        $this->assertFalse($akun->fresh()->aktif);
    }

    public function test_daftar_dan_cetak_akun_orang_tua_tersedia_per_kelas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$siswa, $kelas] = $this->buatSiswaDalamKelas('Siswa Cetak Orang Tua', '0044444444');
        $akun = app(AkunOrangTuaService::class)->buat($siswa);

        $this->actingAs($administrator)
            ->get(route('akun-orang-tua.index', ['kelas_id' => $kelas->id]))
            ->assertOk()
            ->assertSee('Akun orang tua')
            ->assertSee('ORT-0044444444')
            ->assertSee('Buat akun kelas')
            ->assertSee('Cetak daftar akun');

        $this->actingAs($administrator)
            ->get(route('akun-orang-tua.cetak', $kelas))
            ->assertOk()
            ->assertSee('Daftar Akun Orang Tua/Wali')
            ->assertSee('Siswa Cetak Orang Tua')
            ->assertSee('ORT-0044444444')
            ->assertSee($akun->kata_sandi_awal)
            ->assertSee('Cetak / Simpan PDF');
    }

    public function test_wali_kelas_hanya_dapat_melihat_dan_mencetak_akun_orang_tua_kelasnya(): void
    {
        [$wali, $akunWali] = $this->buatAkunWaliKelas();
        [$siswaWali, $kelasWali] = $this->buatSiswaDalamKelas('Siswa Kelas Wali', '0055555555', $wali);
        [$siswaLain, $kelasLain] = $this->buatSiswaDalamKelas('Siswa Kelas Lain', '0066666666');
        app(AkunOrangTuaService::class)->buat($siswaWali);
        app(AkunOrangTuaService::class)->buat($siswaLain);

        $this->actingAs($akunWali)
            ->get(route('akun-orang-tua.index'))
            ->assertOk()
            ->assertSee('Siswa Kelas Wali')
            ->assertDontSee('Siswa Kelas Lain')
            ->assertDontSee('Reset password');

        $this->actingAs($akunWali)
            ->get(route('akun-orang-tua.cetak', $kelasWali))
            ->assertOk();

        $this->actingAs($akunWali)
            ->get(route('akun-orang-tua.cetak', $kelasLain))
            ->assertForbidden();

        $this->actingAs($akunWali)
            ->post(route('akun-orang-tua.store', $siswaLain))
            ->assertForbidden();
    }

    public function test_orang_tua_wajib_ganti_password_dan_mendapat_sidebar_khusus(): void
    {
        [$siswa] = $this->buatSiswaDalamKelas('Siswa Login Orang Tua', '0077777777');
        $akun = app(AkunOrangTuaService::class)->buat($siswa);
        $passwordAwal = $akun->kata_sandi_awal;

        $this->post(route('login.store'), [
            'username' => 'ORT-0077777777',
            'password' => $passwordAwal,
        ])->assertRedirect(route('beranda'));

        $this->get(route('beranda'))->assertRedirect(route('kata-sandi.edit'));

        $this->put(route('kata-sandi.update'), [
            'kata_sandi_lama' => $passwordAwal,
            'kata_sandi_baru' => 'KataSandiOrtu123',
            'kata_sandi_baru_confirmation' => 'KataSandiOrtu123',
        ])->assertRedirect(route('beranda'));

        $this->get(route('beranda'))
            ->assertOk()
            ->assertViewIs('beranda.orang-tua')
            ->assertViewHas('siswaLogin', fn (?Siswa $siswaDashboard) => $siswaDashboard?->is($siswa) === true)
            ->assertSee('Dashboard Orang Tua')
            ->assertSee('Siswa Login Orang Tua')
            ->assertSee('Presensi hari ini')
            ->assertSee('Jadwal Pelajaran Hari Ini')
            ->assertSee('Presensi Bulan Ini')
            ->assertSee('Presensi Ibadah Anak')
            ->assertSee('Nilai Anak')
            ->assertSee('Akademik Anak')
            ->assertSee('Pembinaan &amp; Poin', false)
            ->assertSeeText('Poin & Pembinaan')
            ->assertSee('Notifikasi Terbaru')
            ->assertSee('Ganti Password')
            ->assertDontSee('Katalog Barang');
    }

    public function test_perubahan_nisn_menyinkronkan_username_akun_orang_tua(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$siswa] = $this->buatSiswaDalamKelas('Siswa Ganti NISN', '0088888888');
        $akun = app(AkunOrangTuaService::class)->buat($siswa);
        $passwordAwal = $akun->kata_sandi_awal;

        $this->actingAs($administrator)
            ->put(route('siswa.update', $siswa), [
                'nama_lengkap' => $siswa->nama_lengkap,
                'nisn' => '0099999999',
                'aktif' => '1',
            ])
            ->assertRedirect(route('siswa.index'))
            ->assertSessionHas('berhasil', fn (string $pesan) => str_contains($pesan, 'Username login ikut berubah'))
            ->assertSessionHasNoErrors();

        $akun->refresh();
        $this->assertSame('ORT-0099999999', $akun->username);
        $this->assertTrue(Hash::check($passwordAwal, $akun->kata_sandi));
    }

    public function test_pagination_daftar_akun_orang_tua_memakai_tampilan_nusa_yang_ringkas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [, $kelas] = $this->buatSiswaDalamKelas('Siswa Pagination 01', '0100000001');

        foreach (range(2, 21) as $urutan) {
            $this->tambahkanSiswaKeKelas(
                $kelas,
                'Siswa Pagination '.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT),
                '01'.str_pad((string) $urutan, 8, '0', STR_PAD_LEFT),
                $urutan,
            );
        }

        $this->actingAs($administrator)
            ->get(route('akun-orang-tua.index', ['kelas_id' => $kelas->id]))
            ->assertOk()
            ->assertSee('Halaman 1 dari 2')
            ->assertSee('1-20 dari 21 data')
            ->assertSee('Sebelumnya')
            ->assertSee('Berikutnya')
            ->assertDontSee('Previous')
            ->assertDontSee('Showing 1 to 20 of 21 results');
    }

    private function buatSiswaDalamKelas(
        string $nama,
        ?string $nisn,
        ?Pegawai $waliKelas = null,
    ): array {
        $tahun = TahunPelajaran::create([
            'nama' => '2031/2032-'.fake()->unique()->numerify('###'),
            'tanggal_mulai' => '2031-07-01',
            'tanggal_selesai' => '2032-06-30',
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

    private function tambahkanSiswaKeKelas(Kelas $kelas, string $nama, ?string $nisn, int $nomorAbsen): Siswa
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => fake()->unique()->numerify('31#####'),
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
            'nama_lengkap' => 'Wali Kelas Akun Orang Tua',
            'nip' => '198001012010011002',
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
