<?php

namespace Tests\Feature\Api;

use App\Models\OrangTuaWali;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuHumasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_humas_tunggal_dan_izin_sesuai_web(): void
    {
        $user = $this->user('humas');
        $user->daftarPeran()->attach(Peran::where('kode', 'wakil_pimpinan_humas')->firstOrFail());
        $response = $this->withToken($user->createToken('test', ['mobile'])->plainTextToken)
            ->getJson(route('api.v1.menu'))->assertOk()
            ->assertJsonFragment(['kode' => 'agenda-humas', 'rute' => '/humas/agenda'])
            ->assertJsonFragment(['kode' => 'dokumen-humas', 'rute' => '/humas/dokumen'])
            ->assertJsonFragment(['kode' => 'pengaduan-humas', 'rute' => '/humas/pengaduan'])
            ->assertJsonMissing(['kode' => 'pertemuan-saya']);
        $groups = collect($response->json('data.kelompok'))->where('kode', 'humas');
        $this->assertCount(1, $groups);
        $this->assertCount(5, $groups->first()['items']);
        $codes = collect($response->json('data.kelompok'))->pluck('items')->flatten(1)->pluck('kode');
        $this->assertCount($codes->count(), $codes->unique());
    }

    public function test_menu_orang_tua_memakai_identitas_bukan_role(): void
    {
        $user = $this->user('parent');
        // Even an accidental staff role must not expose Humas administrative menus.
        $user->daftarPeran()->attach(Peran::where('kode', 'wakil_pimpinan_humas')->firstOrFail());
        OrangTuaWali::create(['pengguna_id' => $user->id, 'nama_lengkap' => 'Wali Mobile']);
        $this->withToken($user->createToken('test', ['mobile'])->plainTextToken)
            ->getJson(route('api.v1.menu'))->assertOk()
            ->assertJsonFragment(['kode' => 'pertemuan-saya', 'rute' => '/humas/pertemuan-saya'])
            ->assertJsonFragment(['kode' => 'pengaduan-saya', 'rute' => '/humas/pengaduan-saya'])
            ->assertJsonFragment(['kode' => 'umpan-balik-saya', 'rute' => '/humas/umpan-balik-saya'])
            ->assertJsonMissing(['kode' => 'dashboard-humas'])
            ->assertJsonMissing(['kode' => 'pengaduan-humas']);
    }

    public function test_role_parent_tanpa_identitas_tidak_mendapat_menu_parent(): void
    {
        $user = $this->user('role-parent');
        $user->daftarPeran()->attach(Peran::where('kode', 'orang_tua')->firstOrFail());
        $this->withToken($user->createToken('test', ['mobile'])->plainTextToken)
            ->getJson(route('api.v1.menu'))->assertOk()
            ->assertJsonMissing(['kode' => 'pertemuan-saya'])
            ->assertJsonMissing(['kode' => 'pengaduan-saya']);
    }

    public function test_siswa_tidak_mendapat_menu_administrasi_humas_meski_role_salah(): void
    {
        $user = $this->user('student');
        $student = Siswa::create(['nama_lengkap' => 'Siswa Humas', 'nis' => 'HUMAS01',
            'nisn' => '9999900101', 'jenis_kelamin' => 'L', 'aktif' => true]);
        $user->update(['siswa_id' => $student->id]);
        $user->daftarPeran()->attach(Peran::where('kode', 'wakil_pimpinan_humas')->firstOrFail());
        $this->withToken($user->createToken('test', ['mobile'])->plainTextToken)
            ->getJson(route('api.v1.menu'))->assertOk()->assertJsonMissing(['kode' => 'humas']);
    }

    private function user(string $username): Pengguna
    {
        return Pengguna::create(['nama' => $username, 'username' => 'menu.humas.'.$username,
            'kata_sandi' => 'RahasiaNusa123!', 'peran' => 'pegawai', 'aktif' => true,
            'akun_sistem' => false, 'wajib_ganti_kata_sandi' => false]);
    }
}
