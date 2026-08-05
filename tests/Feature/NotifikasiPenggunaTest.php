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

    public function test_ringkasan_notifikasi_diperbarui_dengan_enam_data_terbaru_milik_pengguna(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $penggunaLain = Pengguna::create([
            'nama' => 'Pengguna Notifikasi Lain',
            'username' => 'pengguna.notifikasi.lain',
            'kata_sandi' => 'rahasia-sekolah',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);

        foreach (range(1, 7) as $urutan) {
            NotifikasiPengguna::create([
                'pengguna_id' => $administrator->id,
                'jenis' => 'informasi',
                'judul' => "Notifikasi {$urutan}",
                'pesan' => "Isi notifikasi {$urutan}.",
                'dibaca_pada' => $urutan === 1 ? now() : null,
            ]);
        }

        NotifikasiPengguna::create([
            'pengguna_id' => $penggunaLain->id,
            'jenis' => 'penting',
            'judul' => 'Notifikasi pengguna lain',
            'pesan' => 'Tidak boleh muncul pada ringkasan administrator.',
        ]);

        $respons = $this->actingAs($administrator)
            ->getJson(route('notifikasi.ringkasan'))
            ->assertOk()
            ->assertJsonPath('jumlah_belum_dibaca', 6)
            ->assertJsonCount(6, 'notifikasi')
            ->assertJsonPath('notifikasi.0.judul', 'Notifikasi 7')
            ->assertJsonPath('notifikasi.5.judul', 'Notifikasi 2')
            ->assertJsonMissing(['judul' => 'Notifikasi pengguna lain']);

        $this->assertStringContainsString('no-store', (string) $respons->headers->get('Cache-Control'));

        $respons->assertJsonStructure([
            'notifikasi' => [[
                'id',
                'jenis',
                'judul',
                'pesan',
                'belum_dibaca',
                'dibuat_pada',
                'waktu_relatif',
                'url_buka',
            ]],
        ]);
    }

    public function test_layout_memeriksa_notifikasi_setiap_tiga_puluh_detik_tanpa_refresh(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('beranda'))
            ->assertOk()
            ->assertSee('data-notification-list', false)
            ->assertSee('data-notification-read-all', false)
            ->assertSee('window.setInterval(perbaruiNotifikasi, 30000)', false)
            ->assertSee("document.visibilityState !== 'visible'", false);
    }
}
