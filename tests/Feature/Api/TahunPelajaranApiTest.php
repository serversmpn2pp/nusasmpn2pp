<?php

namespace Tests\Feature\Api;

use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TahunPelajaranApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_tahun_pelajaran_memerlukan_token_dan_izin_yang_sesuai(): void
    {
        $this->getJson(route('api.v1.tahun-pelajaran.index'))->assertUnauthorized();

        $pembaca = Pengguna::create([
            'nama' => 'Pembaca Tahun Pelajaran',
            'username' => 'pembaca.tahun.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pembaca->daftarPeran()->attach(
            Peran::where('kode', 'wakil_pimpinan_kurikulum')->firstOrFail(),
        );

        $this->withToken($this->token($pembaca))
            ->getJson(route('api.v1.tahun-pelajaran.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($this->token($pembaca))
            ->postJson(route('api.v1.tahun-pelajaran.store'), [])
            ->assertForbidden();
    }

    public function test_administrator_dapat_mencari_memfilter_dan_melihat_jumlah_kelas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahunAktif = $this->tahun('2026/2027', true, '2026-07-01', '2027-06-30');
        $this->tahun('2025/2026');
        Kelas::create([
            'tahun_pelajaran_id' => $tahunAktif->id,
            'nama' => 'VII.API.TAHUN',
            'tingkat' => 7,
            'aktif' => true,
        ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.tahun-pelajaran.index', [
                'cari' => '2026/2027',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.nama', '2026/2027')
            ->assertJsonPath('data.items.0.tanggal_mulai', '2026-07-01')
            ->assertJsonPath('data.items.0.tanggal_selesai', '2027-06-30')
            ->assertJsonPath('data.items.0.jumlah_kelas', 1)
            ->assertJsonPath('data.tahun_aktif.id', $tahunAktif->id)
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);
    }

    public function test_mengaktifkan_tahun_baru_otomatis_menonaktifkan_tahun_lama(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahunLama = $this->tahun('2026/2027', true);

        $response = $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.tahun-pelajaran.store'), [
                'nama' => '2027/2028',
                'tanggal_mulai' => '2027-07-01',
                'tanggal_selesai' => '2028-06-30',
                'aktif' => true,
                'keterangan' => 'Tahun baru dari Android',
            ])
            ->assertCreated();

        $tahunBaru = TahunPelajaran::where('nama', '2027/2028')->firstOrFail();
        $response->assertJsonPath('data.id', $tahunBaru->id);
        $this->assertFalse($tahunLama->fresh()->aktif);
        $this->assertTrue($tahunBaru->aktif);
        $this->assertSame(1, TahunPelajaran::where('aktif', true)->count());
    }

    public function test_update_dan_validasi_periode_tahun_pelajaran(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->tahun('2026/2027');
        $token = $this->token($administrator);

        $this->withToken($token)
            ->patchJson(route('api.v1.tahun-pelajaran.update', $tahun), [
                'nama' => '2026/2027 Revisi',
                'tanggal_mulai' => '2026-07-15',
                'tanggal_selesai' => '2027-06-20',
                'aktif' => false,
                'keterangan' => 'Diperbarui dari mobile',
            ])
            ->assertOk();
        $this->assertDatabaseHas('tahun_pelajaran', [
            'id' => $tahun->id,
            'nama' => '2026/2027 Revisi',
            'tanggal_mulai' => '2026-07-15 00:00:00',
            'tanggal_selesai' => '2027-06-20 00:00:00',
            'aktif' => false,
            'keterangan' => 'Diperbarui dari mobile',
        ]);

        $this->withToken($token)
            ->postJson(route('api.v1.tahun-pelajaran.store'), [
                'nama' => 'Periode Tidak Valid',
                'tanggal_mulai' => '2027-07-01',
                'tanggal_selesai' => '2027-06-30',
                'aktif' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tanggal_selesai');
    }

    private function tahun(
        string $nama,
        bool $aktif = false,
        ?string $mulai = null,
        ?string $selesai = null,
    ): TahunPelajaran {
        return TahunPelajaran::create([
            'nama' => $nama,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'aktif' => $aktif,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
