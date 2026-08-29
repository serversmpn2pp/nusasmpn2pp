<?php

namespace Tests\Feature\Api;

use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KegiatanIbadahApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_kegiatan_ibadah_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.kegiatan-ibadah.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Guru Tanpa Izin Ibadah',
            'username' => 'guru.tanpa.izin.ibadah',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.kegiatan-ibadah.index'))
            ->assertForbidden();
    }

    public function test_administrator_dapat_mencari_memfilter_dan_melihat_ringkasan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        KegiatanIbadah::create([
            'kode' => 'tadarus_mobile',
            'nama' => 'Tadarus Mobile',
            'aktif' => true,
            'keterangan' => 'Kegiatan membaca Al-Quran.',
        ]);
        KegiatanIbadah::create([
            'kode' => 'dhuha_lama_mobile',
            'nama' => 'Dhuha Lama Mobile',
            'aktif' => false,
        ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.kegiatan-ibadah.index', [
                'cari' => 'tadarus',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.kode', 'tadarus_mobile')
            ->assertJsonPath('data.items.0.jumlah_jadwal', 0)
            ->assertJsonPath('data.filter.status', 'aktif')
            ->assertJsonStructure([
                'data' => [
                    'ringkasan' => ['total', 'aktif', 'nonaktif'],
                    'paginasi' => ['halaman', 'total', 'ada_halaman_berikutnya'],
                ],
            ]);
    }

    public function test_administrator_dapat_menambah_dan_mengubah_kegiatan_dengan_kode_rapi(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.kegiatan-ibadah.store'), [
                'kode' => ' Tadarus Pagi-Mobile ',
                'nama' => '  Tadarus Pagi Bersama  ',
                'aktif' => true,
                'keterangan' => '  Membaca Al-Quran sebelum belajar.  ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.kode', 'tadarus_pagi_mobile')
            ->assertJsonPath('data.nama', 'Tadarus Pagi Bersama');

        $kegiatan = KegiatanIbadah::findOrFail($response->json('data.id'));
        $this->assertSame('Membaca Al-Quran sebelum belajar.', $kegiatan->keterangan);

        $this->withToken($token)
            ->patchJson(route('api.v1.kegiatan-ibadah.update', $kegiatan), [
                'kode' => 'tadarus pagi baru',
                'nama' => 'Tadarus Pagi Baru',
                'aktif' => true,
                'keterangan' => null,
            ])
            ->assertOk();

        $this->assertDatabaseHas('kegiatan_ibadah', [
            'id' => $kegiatan->id,
            'kode' => 'tadarus_pagi_baru',
            'nama' => 'Tadarus Pagi Baru',
        ]);

        $this->withToken($token)
            ->postJson(route('api.v1.kegiatan-ibadah.store'), [
                'kode' => 'tadarus-pagi-baru',
                'nama' => 'Kegiatan Berbeda',
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kode');
    }

    public function test_menonaktifkan_kegiatan_juga_menonaktifkan_seluruh_jadwal(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2031/2032',
            'tanggal_mulai' => '2031-07-01',
            'tanggal_selesai' => '2032-06-30',
            'aktif' => true,
        ]);
        $kegiatan = KegiatanIbadah::create([
            'kode' => 'dhuha_mobile',
            'nama' => 'Sholat Dhuha Mobile',
            'aktif' => true,
        ]);
        $jadwal = JadwalKegiatanIbadah::create([
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => 'senin',
            'urutan_hari' => 1,
            'jam_scan_mulai' => '07:00',
            'jam_pelaksanaan' => '07:15',
            'jam_scan_selesai' => '07:30',
            'aktif' => true,
        ]);

        $this->withToken($this->token($administrator))
            ->deleteJson(route('api.v1.kegiatan-ibadah.destroy', $kegiatan))
            ->assertOk()
            ->assertJsonPath(
                'pesan',
                'Kegiatan dan seluruh jadwalnya berhasil dinonaktifkan.',
            );

        $this->assertDatabaseHas('kegiatan_ibadah', ['id' => $kegiatan->id, 'aktif' => false]);
        $this->assertDatabaseHas('jadwal_kegiatan_ibadah', ['id' => $jadwal->id, 'aktif' => false]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
