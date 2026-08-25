<?php

namespace Tests\Feature\Api;

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

class AkunOrangTuaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dapat_melihat_daftar_filter_dan_detail_akun_orang_tua(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$kelasA, $kelasB] = $this->buatKelasAktif();
        $siswaA = $this->tambahSiswa($kelasA, 'Anak Akun Orang Tua Mobile', '1012345601', 1);
        $siswaB = $this->tambahSiswa($kelasB, 'Anak Belum Akun Orang Tua', '1012345602', 1);
        $akun = app(AkunOrangTuaService::class)->buat($siswaA);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->getJson(route('api.v1.akun-orang-tua.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.jumlah_siswa', 2)
            ->assertJsonPath('data.ringkasan.akun_aktif', 1)
            ->assertJsonPath('data.ringkasan.belum_akun', 1)
            ->assertJsonPath('data.items.0.orang_tua.nama', 'Ayah Anak Akun Orang Tua Mobile')
            ->assertJsonPath('data.items.0.akun.username', 'ORT-1012345601')
            ->assertJsonPath('data.items.0.akun.kata_sandi_awal', $akun->kata_sandi_awal);

        $this->withToken($token)
            ->getJson(route('api.v1.akun-orang-tua.index', [
                'kelas_id' => $kelasB->id,
                'status_akun' => 'belum',
                'cari' => 'Belum',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.siswa.id', $siswaB->id);

        $this->withToken($token)
            ->getJson(route('api.v1.akun-orang-tua.show', $siswaA))
            ->assertOk()
            ->assertJsonPath('data.orang_tua.hubungan', 'ayah')
            ->assertJsonPath('data.kontak_keluarga.0.utama', true)
            ->assertJsonPath('data.akun.wajib_ganti_kata_sandi', true);
    }

    public function test_wali_kelas_hanya_dapat_melihat_akun_orang_tua_di_kelasnya(): void
    {
        $wali = $this->buatWaliKelas();
        [$kelasWali, $kelasLain] = $this->buatKelasAktif($wali->pegawai);
        $siswaWali = $this->tambahSiswa($kelasWali, 'Anak Kelas Wali API', '1012345611', 1);
        $siswaLain = $this->tambahSiswa($kelasLain, 'Anak Kelas Lain API', '1012345612', 1);
        app(AkunOrangTuaService::class)->buat($siswaWali);
        app(AkunOrangTuaService::class)->buat($siswaLain);

        $this->withToken($this->token($wali))
            ->getJson(route('api.v1.akun-orang-tua.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.siswa.id', $siswaWali->id)
            ->assertJsonPath('data.hak_akses.dapat_kelola', false)
            ->assertJsonPath('data.hak_akses.dapat_melihat_kredensial', true);

        $this->withToken($this->token($wali))
            ->getJson(route('api.v1.akun-orang-tua.show', $siswaLain))
            ->assertNotFound();

        $this->withToken($this->token($wali))
            ->postJson(route('api.v1.akun-orang-tua.store', $siswaLain))
            ->assertForbidden();
    }

    public function test_administrator_dapat_membuat_mereset_dan_mengubah_status_akun_orang_tua(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$kelas] = $this->buatKelasAktif();
        $siswa = $this->tambahSiswa($kelas, 'Anak Kelola Akun Orang Tua', '1012345621', 1);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->postJson(route('api.v1.akun-orang-tua.store', $siswa))
            ->assertCreated()
            ->assertJsonPath('data.username', 'ORT-1012345621');

        $akun = $siswa->fresh()->orangTuaWali()->firstOrFail()->pengguna;
        $kataSandiLama = $akun->kata_sandi_awal;

        $this->withToken($token)
            ->patchJson(route('api.v1.akun-orang-tua.reset-kata-sandi', $siswa))
            ->assertOk();
        $akun->refresh();
        $this->assertNotSame($kataSandiLama, $akun->kata_sandi_awal);
        $this->assertMatchesRegularExpression('/^\d{8}$/', $akun->kata_sandi_awal);
        $this->assertTrue(Hash::check($akun->kata_sandi_awal, $akun->kata_sandi));

        $this->withToken($token)
            ->patchJson(route('api.v1.akun-orang-tua.status', $siswa), ['aktif' => false])
            ->assertOk();
        $this->assertFalse($akun->fresh()->aktif);
    }

    public function test_pembuatan_massal_membuat_akun_orang_tua_yang_layak_per_kelas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$kelas] = $this->buatKelasAktif();
        $layak = $this->tambahSiswa($kelas, 'Anak Massal Layak', '1012345631', 1);
        $tanpaNisn = $this->tambahSiswa($kelas, 'Anak Massal Tanpa NISN', null, 2);

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.akun-orang-tua.store-massal', $kelas))
            ->assertOk()
            ->assertJsonPath('data.dibuat', 1)
            ->assertJsonPath('data.dilewati', 1);

        $this->assertTrue($layak->fresh()->orangTuaWali()->exists());
        $this->assertFalse($tanpaNisn->fresh()->orangTuaWali()->exists());
    }

    private function buatKelasAktif(?Pegawai $waliKelas = null): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2032/2033-'.fake()->unique()->numerify('###'),
            'tanggal_mulai' => '2032-07-01',
            'tanggal_selesai' => '2033-06-30',
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
            'nis' => fake()->unique()->numerify('32#####'),
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'nama_ayah' => 'Ayah '.$nama,
            'nomor_wa_ayah' => '081234567890',
            'kontak_absensi_utama' => 'ayah',
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
            'nama_lengkap' => 'Wali Kelas API Akun Orang Tua',
            'nip' => '198001012010011098',
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
