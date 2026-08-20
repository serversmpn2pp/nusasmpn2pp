<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\AkunOrangTuaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilOrangTuaTest extends TestCase
{
    use RefreshDatabase;

    public function test_orang_tua_dapat_melihat_profil_akun_dan_anak_yang_terhubung(): void
    {
        [$akunOrangTua, $siswa, $kelas] = $this->buatDataDasar();
        Siswa::create([
            'nama_lengkap' => 'Siswa Lain Tidak Terhubung',
            'nisn' => '0099000012',
            'aktif' => true,
        ]);

        $this->actingAs($akunOrangTua)
            ->get(route('profil-orang-tua.edit'))
            ->assertOk()
            ->assertSee('Profil & Akun')
            ->assertSee($akunOrangTua->username)
            ->assertSee($siswa->nama_lengkap)
            ->assertSee($kelas->nama)
            ->assertSee('Ganti kata sandi')
            ->assertDontSee('Siswa Lain Tidak Terhubung');
    }

    public function test_orang_tua_dapat_memperbarui_nama_dan_nomor_whatsapp_tanpa_mengubah_username(): void
    {
        [$akunOrangTua] = $this->buatDataDasar();
        $usernameSebelum = $akunOrangTua->username;

        $this->actingAs($akunOrangTua)
            ->put(route('profil-orang-tua.update'), [
                'nama_lengkap' => 'Wali Murid Diperbarui',
                'nomor_wa' => '+62 812-3456-7890',
            ])
            ->assertRedirect(route('profil-orang-tua.edit'))
            ->assertSessionHas('berhasil', 'Profil orang tua berhasil diperbarui.');

        $this->assertDatabaseHas('orang_tua_wali', [
            'pengguna_id' => $akunOrangTua->id,
            'nama_lengkap' => 'Wali Murid Diperbarui',
            'nomor_wa' => '+62 812-3456-7890',
        ]);
        $this->assertDatabaseHas('pengguna', [
            'id' => $akunOrangTua->id,
            'nama' => 'Wali Murid Diperbarui',
            'username' => $usernameSebelum,
        ]);
    }

    public function test_nomor_whatsapp_dengan_karakter_tidak_valid_ditolak(): void
    {
        [$akunOrangTua] = $this->buatDataDasar();

        $this->actingAs($akunOrangTua)
            ->from(route('profil-orang-tua.edit'))
            ->put(route('profil-orang-tua.update'), [
                'nama_lengkap' => 'Orang Tua Penguji',
                'nomor_wa' => 'nomor-wa-tidak-valid',
            ])
            ->assertRedirect(route('profil-orang-tua.edit'))
            ->assertSessionHasErrors('nomor_wa');
    }

    public function test_akun_selain_orang_tua_tidak_dapat_membuka_profil_orang_tua(): void
    {
        $administrator = Pengguna::query()->where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('profil-orang-tua.edit'))
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
            'nama_lengkap' => 'Anak Profil Orang Tua',
            'nisn' => '0099000011',
            'nama_ibu' => 'Orang Tua Penguji',
            'nomor_wa_ibu' => '081234567890',
            'kontak_absensi_utama' => 'ibu',
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
        $akunOrangTua = app(AkunOrangTuaService::class)->buat($siswa);
        $akunOrangTua->forceFill([
            'kata_sandi_awal' => null,
            'wajib_ganti_kata_sandi' => false,
        ])->save();

        return [$akunOrangTua, $siswa, $kelas];
    }
}
