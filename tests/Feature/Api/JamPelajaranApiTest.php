<?php

namespace Tests\Feature\Api;

use App\Models\JamPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JamPelajaranApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_jam_pelajaran_hanya_dapat_diakses_administrator(): void
    {
        $this->getJson(route('api.v1.jam-pelajaran.index'))->assertUnauthorized();

        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Wali Jam Mobile',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $wali = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => 'wali.jam.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $wali->daftarPeran()->attach(Peran::where('kode', 'wali_kelas')->firstOrFail());

        $this->withToken($this->token($wali))
            ->getJson(route('api.v1.jam-pelajaran.index'))
            ->assertForbidden();
    }

    public function test_administrator_dapat_menyisipkan_memfilter_dan_mengubah_jam_pelajaran(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $seninPertama = $this->buatJam('senin', 1, 'Jam 1', '07:00', '07:40');
        $seninKedua = $this->buatJam('senin', 2, 'Jam 2', '07:40', '08:20');
        $this->buatJam('selasa', 1, 'Jam 1', '07:00', '07:40');
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.jam-pelajaran.store'), [
                'hari' => ['senin', 'selasa'],
                'posisi_sisip' => 'setelah:1',
                'label' => 'Literasi',
                'jam_mulai' => '07:40',
                'jam_selesai' => '07:55',
                'jenis' => 'lainnya',
                'aktif' => true,
                'keterangan' => 'Dibuat dari NUSA Mobile',
            ])
            ->assertCreated()
            ->assertJsonPath('data.jumlah_baru', 2)
            ->assertJsonPath('data.jumlah_digeser', 1);

        $slotSeninId = $response->json('data.ids.0');
        $this->assertDatabaseHas('jam_pelajaran', [
            'id' => $slotSeninId,
            'hari' => 'senin',
            'nomor_jam' => 2,
            'label' => 'Literasi',
            'jenis' => 'lainnya',
        ]);
        $this->assertSame(3, $seninKedua->fresh()->nomor_jam);
        $this->assertSame('Jam 3', $seninKedua->fresh()->label);
        $this->assertSame(1, $seninPertama->fresh()->nomor_jam);

        $this->withToken($token)
            ->getJson(route('api.v1.jam-pelajaran.index', [
                'hari' => 'senin',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.filter.hari', 'senin')
            ->assertJsonPath('data.items.1.label', 'Literasi')
            ->assertJsonPath('data.items.1.jam_mulai', '07:40')
            ->assertJsonPath('data.items.1.jenis_label', 'Lainnya');

        $this->withToken($token)
            ->patchJson(route('api.v1.jam-pelajaran.update', $slotSeninId), [
                'label' => 'Literasi Pagi',
                'jam_mulai' => '07:40',
                'jam_selesai' => '08:00',
                'jenis' => 'lainnya',
                'aktif' => false,
                'keterangan' => null,
            ])
            ->assertOk();
        $this->assertDatabaseHas('jam_pelajaran', [
            'id' => $slotSeninId,
            'label' => 'Literasi Pagi',
            'aktif' => false,
        ]);

        $this->withToken($token)
            ->postJson(route('api.v1.jam-pelajaran.store'), [
                'hari' => ['rabu'],
                'posisi_sisip' => 'akhir',
                'jam_mulai' => '09:00',
                'jam_selesai' => '08:00',
                'jenis' => 'pelajaran',
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jam_selesai');
    }

    private function buatJam(string $hari, int $nomor, string $label, string $mulai, string $selesai): JamPelajaran
    {
        return JamPelajaran::create([
            'hari' => $hari,
            'nomor_jam' => $nomor,
            'label' => $label,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis' => 'pelajaran',
            'aktif' => true,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
