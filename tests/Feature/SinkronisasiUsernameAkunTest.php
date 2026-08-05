<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SinkronisasiUsernameAkunTest extends TestCase
{
    use RefreshDatabase;

    public function test_perubahan_nip_pegawai_menyinkronkan_username_tanpa_mengubah_password(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Sinkronisasi NIP',
            'nip' => '198001012010011001',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'Password-Lama-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->put(route('pegawai.update', $pegawai), [
                'nama_lengkap' => $pegawai->nama_lengkap,
                'nip' => '198001012010011099',
                'aktif' => '1',
            ])
            ->assertRedirect(route('pegawai.index'))
            ->assertSessionHas('berhasil', fn (string $pesan) => str_contains($pesan, 'Username login ikut berubah'))
            ->assertSessionHasNoErrors();

        $this->assertSame('198001012010011099', $pegawai->fresh()->nip);
        $this->assertSame('198001012010011099', $akun->fresh()->username);
        $this->assertTrue(Hash::check('Password-Lama-2026', $akun->fresh()->kata_sandi));
    }

    public function test_perubahan_nisn_siswa_menyinkronkan_username_tanpa_mengubah_password(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Sinkronisasi NISN',
            'nisn' => '0011223301',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'username' => $siswa->nisn,
            'kata_sandi' => 'Password-Siswa-2026',
            'peran' => 'siswa',
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->put(route('siswa.update', $siswa), [
                'nama_lengkap' => $siswa->nama_lengkap,
                'nisn' => '0011223399',
                'aktif' => '1',
            ])
            ->assertRedirect(route('siswa.index'))
            ->assertSessionHas('berhasil', fn (string $pesan) => str_contains($pesan, 'Username login ikut berubah'))
            ->assertSessionHasNoErrors();

        $this->assertSame('0011223399', $siswa->fresh()->nisn);
        $this->assertSame('0011223399', $akun->fresh()->username);
        $this->assertTrue(Hash::check('Password-Siswa-2026', $akun->fresh()->kata_sandi));
    }

    public function test_identitas_baru_ditolak_jika_username_sudah_dipakai_akun_lain(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Benturan Username',
            'nip' => '198101012011011001',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'Password-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        Pengguna::create([
            'nama' => 'Pemilik Username Lain',
            'username' => '199901012020011999',
            'kata_sandi' => 'Password-Lain-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->from(route('pegawai.edit', $pegawai))
            ->put(route('pegawai.update', $pegawai), [
                'nama_lengkap' => $pegawai->nama_lengkap,
                'nip' => '199901012020011999',
                'aktif' => '1',
            ])
            ->assertRedirect(route('pegawai.edit', $pegawai))
            ->assertSessionHasErrors('nip');

        $this->assertSame('198101012011011001', $pegawai->fresh()->nip);
        $this->assertSame('198101012011011001', $akun->fresh()->username);
    }

    public function test_identitas_login_tidak_boleh_dikosongkan_setelah_akun_dibuat(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Identitas Wajib',
            'nisn' => '0011223344',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'username' => $siswa->nisn,
            'kata_sandi' => 'Password-Siswa-2026',
            'peran' => 'siswa',
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->from(route('siswa.edit', $siswa))
            ->put(route('siswa.update', $siswa), [
                'nama_lengkap' => $siswa->nama_lengkap,
                'nisn' => '',
                'aktif' => '1',
            ])
            ->assertRedirect(route('siswa.edit', $siswa))
            ->assertSessionHasErrors('nisn');

        $this->assertSame('0011223344', $siswa->fresh()->nisn);
        $this->assertSame('0011223344', $akun->fresh()->username);
    }
}
