<?php

namespace Tests\Feature\Api;

use App\Models\Pengguna;
use App\Models\PertanyaanSurveiPembelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PernyataanSurveiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_memerlukan_token_dan_izin_kelola(): void
    {
        $this->getJson(route('api.v1.pernyataan-survei.index'))->assertUnauthorized();

        $pegawai = Pengguna::create([
            'nama' => 'Pegawai Tanpa Izin Survei',
            'username' => 'pegawai.tanpa.izin.survei',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pegawai))
            ->getJson(route('api.v1.pernyataan-survei.index'))
            ->assertForbidden();
    }

    public function test_administrator_dapat_mencari_memfilter_dan_melihat_ringkasan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        PertanyaanSurveiPembelajaran::where('kode', 'umpan_balik')->update(['aktif' => false]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pernyataan-survei.index', [
                'cari' => 'umpan balik',
                'status' => 'nonaktif',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.kode', 'umpan_balik')
            ->assertJsonPath('data.items.0.aktif', false)
            ->assertJsonPath('data.ringkasan.total', 6)
            ->assertJsonPath('data.ringkasan.aktif', 5)
            ->assertJsonPath('data.ringkasan.nonaktif', 1)
            ->assertJsonPath('data.urutan_berikutnya', 7);
    }

    public function test_administrator_dapat_menambah_dan_mengubah_pernyataan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.pernyataan-survei.store'), [
                'pernyataan' => 'Guru menggunakan media pembelajaran yang membantu pemahaman saya.',
                'urutan' => 7,
                'aktif' => true,
            ])
            ->assertCreated();

        $pertanyaan = PertanyaanSurveiPembelajaran::findOrFail($response->json('data.id'));
        $kodeAwal = $pertanyaan->kode;

        $this->withToken($token)
            ->patchJson(route('api.v1.pernyataan-survei.update', $pertanyaan), [
                'pernyataan' => 'Guru menggunakan media pembelajaran yang sesuai dengan materi.',
                'urutan' => 3,
            ])
            ->assertOk();

        $this->assertDatabaseHas('pertanyaan_survei_pembelajaran', [
            'id' => $pertanyaan->id,
            'kode' => $kodeAwal,
            'pernyataan' => 'Guru menggunakan media pembelajaran yang sesuai dengan materi.',
            'urutan' => 3,
            'aktif' => true,
        ]);
    }

    public function test_status_dapat_diubah_tetapi_minimal_satu_pernyataan_tetap_aktif(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);
        $pertanyaan = PertanyaanSurveiPembelajaran::firstOrFail();

        $this->withToken($token)
            ->patchJson(route('api.v1.pernyataan-survei.status', $pertanyaan), ['aktif' => false])
            ->assertOk();
        $this->assertFalse($pertanyaan->fresh()->aktif);

        PertanyaanSurveiPembelajaran::whereKeyNot($pertanyaan->id)->update(['aktif' => false]);
        $pertanyaan->refresh()->update(['aktif' => true]);

        $this->withToken($token)
            ->patchJson(route('api.v1.pernyataan-survei.status', $pertanyaan), ['aktif' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('aktif');
        $this->assertTrue($pertanyaan->fresh()->aktif);
    }

    public function test_validasi_pernyataan_dan_urutan_diterapkan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.pernyataan-survei.store'), [
                'pernyataan' => '',
                'urutan' => 0,
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pernyataan', 'urutan']);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
