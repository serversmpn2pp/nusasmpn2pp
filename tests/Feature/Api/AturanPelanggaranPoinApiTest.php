<?php

namespace Tests\Feature\Api;

use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AturanPelanggaranPoinApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_siswa_membaca_aturan_aktif_dengan_pencarian_kategori_tanpa_hak_mengubah(): void
    {
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Katalog', 'nisn' => '9998887771', 'aktif' => true]);
        $akun = Pengguna::create(['siswa_id' => $siswa->id, 'nama' => 'Siswa Katalog', 'username' => 'siswa.katalog', 'kata_sandi' => 'Rahasia123', 'peran' => 'siswa', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $kategori = KategoriPembinaanSiswa::create(['kode' => 'UJI_KATALOG', 'nama' => 'Kartu pelajar', 'aktif' => true]);
        $aktif = JenisPelanggaranSiswa::create(['kode' => 'UJI_KARTU_A', 'nama' => 'Tidak membawa kartu', 'kategori_pembinaan_siswa_id' => $kategori->id, 'tingkat' => 'ringan', 'poin' => 10, 'aktif' => true]);
        JenisPelanggaranSiswa::create(['kode' => 'UJI_KARTU_B', 'nama' => 'Aturan tidak aktif', 'tingkat' => 'sedang', 'poin' => 20, 'aktif' => false]);
        $nonaktif = KategoriPembinaanSiswa::create(['kode' => 'UJI_NONAKTIF', 'nama' => 'Kategori nonaktif', 'aktif' => false]);
        JenisPelanggaranSiswa::create(['kode' => 'UJI_KARTU_C', 'nama' => 'Aturan kategori nonaktif', 'kategori_pembinaan_siswa_id' => $nonaktif->id, 'tingkat' => 'sedang', 'poin' => 30, 'aktif' => true]);
        $url = route('api.v1.aturan-pelanggaran-poin.index', ['cari' => 'UJI_KARTU']);
        $this->getJson($url)->assertUnauthorized();
        $this->withToken($akun->createToken('Katalog', ['mobile'])->plainTextToken)->getJson($url)->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.poin', 10)
            ->assertJsonPath('data.items.0.nama', 'Tidak membawa kartu')->assertJsonMissingPath('data.items.0.jumlah_digunakan');
        $this->getJson(route('api.v1.aturan-pelanggaran-poin.index', ['kategori_id' => $kategori->id]))->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson(route('api.v1.menu'))->assertOk()->assertJsonFragment(['kode' => 'aturan-pelanggaran-poin', 'rute' => '/aturan-pelanggaran-poin']);
        $this->getJson(route('api.v1.jenis-pelanggaran-siswa.index'))->assertForbidden();
        $this->patchJson(route('api.v1.jenis-pelanggaran-siswa.update', $aktif), ['poin' => 1])->assertForbidden();
        $this->postJson(route('api.v1.aturan-pelanggaran-poin.index'))->assertStatus(405);
        $this->app['auth']->forgetGuards();
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->withToken($admin->createToken('Admin', ['mobile'])->plainTextToken)->getJson($url)->assertForbidden();
    }
}
