<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GantiKataSandiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pegawai_diarahkan_ke_dashboard_dengan_pesan_setelah_mengganti_password_default(): void
    {
        $passwordDefault = config('nusa.kata_sandi_default_pegawai');
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Uji Ganti Password',
            'nip' => '198707072015071007',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => $passwordDefault,
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'pegawai')->firstOrFail());

        $this->actingAs($akun)
            ->get(route('beranda'))
            ->assertRedirect(route('kata-sandi.edit'));

        $this->put(route('kata-sandi.update'), [
            'kata_sandi_lama' => $passwordDefault,
            'kata_sandi_baru' => 'PasswordBaruNusa123',
            'kata_sandi_baru_confirmation' => 'PasswordBaruNusa123',
        ])
            ->assertRedirect(route('beranda'))
            ->assertSessionHas('berhasil', 'Kata sandi berhasil diganti. Anda sekarang dapat menggunakan NUSA.');

        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee('Kata sandi berhasil diganti. Anda sekarang dapat menggunakan NUSA.');

        $this->assertTrue(Hash::check('PasswordBaruNusa123', $akun->fresh()->kata_sandi));
    }
}
