<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\AkunSiswaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_siswa_membuka_profil_miliknya_dan_tautan_tidak_mengarah_ke_profil_orang_tua(): void
    {
        [$akun, $siswa, $kelas] = $this->buatDataDasar();
        Siswa::create([
            'nama_lengkap' => 'Siswa Lain Tidak Ditampilkan',
            'nisn' => '0011223399',
            'aktif' => true,
        ]);

        $this->actingAs($akun)
            ->get(route('profil-siswa.show'))
            ->assertOk()
            ->assertSee('Profil & Akun')
            ->assertSee($siswa->nama_lengkap)
            ->assertSee($kelas->nama)
            ->assertSee($akun->username)
            ->assertDontSee('Siswa Lain Tidak Ditampilkan');

        $this->actingAs($akun)
            ->get(route('beranda'))
            ->assertOk()
            ->assertSee(route('profil-siswa.show'), false)
            ->assertDontSee(route('profil-orang-tua.edit'), false);
    }

    public function test_akun_selain_siswa_tidak_dapat_membuka_profil_siswa(): void
    {
        $administrator = Pengguna::query()->where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('profil-siswa.show'))
            ->assertForbidden();
    }

    private function buatDataDasar(): array
    {
        $tahunPelajaran = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Profil Sendiri',
            'nis' => '26001',
            'nisn' => '0011223388',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Padang Panjang',
            'tanggal_lahir' => '2013-01-20',
            'agama' => 'Islam',
            'alamat' => 'Padang Panjang',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahunPelajaran->tanggal_mulai,
        ]);
        $akun = app(AkunSiswaService::class)->buat($siswa);
        $akun->forceFill([
            'kata_sandi_awal' => null,
            'wajib_ganti_kata_sandi' => false,
        ])->save();

        return [$akun, $siswa, $kelas];
    }
}
