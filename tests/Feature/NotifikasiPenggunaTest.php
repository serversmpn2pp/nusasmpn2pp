<?php

namespace Tests\Feature;

use App\Models\NotifikasiPengguna;
use App\Models\Pengguna;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifikasiPenggunaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengguna_dapat_melihat_dan_membuka_notifikasi_miliknya(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $notifikasi = app(NotifikasiPenggunaService::class)->kirim(
            $administrator,
            'peringatan',
            'Perangkat ajar menunggu pemeriksaan',
            'Satu perangkat ajar baru perlu diperiksa.',
            '/beranda',
            'uji-notifikasi-1',
        );

        $this->actingAs($administrator)
            ->get(route('notifikasi.index'))
            ->assertOk()
            ->assertSee('Perangkat ajar menunggu pemeriksaan')
            ->assertSee('1 belum dibaca');

        $this->post(route('notifikasi.buka', $notifikasi))
            ->assertRedirect('/beranda');

        $this->assertNotNull($notifikasi->fresh()->dibaca_pada);
    }

    public function test_pengguna_tidak_dapat_mengubah_notifikasi_milik_pengguna_lain(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $penggunaLain = Pengguna::create([
            'nama' => 'Pengguna Lain',
            'username' => 'pengguna.lain',
            'kata_sandi' => 'rahasia-sekolah',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $notifikasi = NotifikasiPengguna::create([
            'pengguna_id' => $administrator->id,
            'jenis' => 'informasi',
            'judul' => 'Khusus administrator',
            'pesan' => 'Notifikasi ini tidak boleh diakses pengguna lain.',
        ]);

        $this->actingAs($penggunaLain)
            ->patch(route('notifikasi.baca', $notifikasi))
            ->assertForbidden();

        $this->assertNull($notifikasi->fresh()->dibaca_pada);
    }

    public function test_kunci_unik_mencegah_notifikasi_ganda_untuk_pengguna_yang_sama(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $layanan = app(NotifikasiPenggunaService::class);

        $layanan->kirim($administrator, 'informasi', 'Notifikasi pertama', 'Isi pertama.', null, 'kunci-yang-sama');
        $layanan->kirim($administrator, 'informasi', 'Notifikasi kedua', 'Isi kedua.', null, 'kunci-yang-sama');

        $this->assertDatabaseCount('notifikasi_pengguna', 1);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $administrator->id,
            'judul' => 'Notifikasi pertama',
        ]);
    }

    public function test_pengguna_dapat_menandai_semua_notifikasi_sebagai_sudah_dibaca(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        NotifikasiPengguna::insert([
            [
                'pengguna_id' => $administrator->id,
                'jenis' => 'informasi',
                'judul' => 'Notifikasi satu',
                'pesan' => 'Isi satu.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pengguna_id' => $administrator->id,
                'jenis' => 'peringatan',
                'judul' => 'Notifikasi dua',
                'pesan' => 'Isi dua.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($administrator)
            ->patch(route('notifikasi.baca-semua'))
            ->assertRedirect()
            ->assertSessionHas('berhasil');

        $this->assertSame(0, NotifikasiPengguna::belumDibaca()->count());
    }
}
