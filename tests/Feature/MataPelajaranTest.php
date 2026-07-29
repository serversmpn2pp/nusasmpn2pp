<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\PengaturanMataPelajaran;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MataPelajaranTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dapat_menyimpan_satu_mapel_untuk_tiga_tingkat(): void
    {
        [$administrator, $tahun] = $this->dataDasar();

        $response = $this->actingAs($administrator)
            ->post(route('mata-pelajaran.store'), [
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'Matematika',
                'kelompok' => 'Umum',
                'urutan' => 1,
                'aktif' => '1',
                'pengaturan' => [
                    7 => ['aktif' => '1', 'kode' => 'MTK7', 'kkm' => 70],
                    8 => ['aktif' => '1', 'kode' => 'MTK8', 'kkm' => 73],
                    9 => ['aktif' => '1', 'kode' => 'MTK9', 'kkm' => 75],
                ],
            ]);

        $mataPelajaran = MataPelajaran::where('nama', 'Matematika')->firstOrFail();

        $response
            ->assertRedirect(route('mata-pelajaran.show', [
                'mata_pelajaran' => $mataPelajaran,
                'tahun_pelajaran_id' => $tahun->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('mata_pelajaran', 1);
        $this->assertDatabaseCount('pengaturan_mata_pelajaran', 3);
        $this->assertDatabaseHas('pengaturan_mata_pelajaran', [
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 8,
            'kode' => 'MTK8',
            'kkm' => 73,
            'aktif' => true,
        ]);
    }

    public function test_pengaturan_tahun_baru_tidak_mengubah_tahun_lama(): void
    {
        [$administrator, $tahunLama] = $this->dataDasar();
        $tahunBaru = TahunPelajaran::create([
            'nama' => '2027/2028',
            'tanggal_mulai' => '2027-07-01',
            'tanggal_selesai' => '2028-06-30',
            'aktif' => false,
        ]);
        $mataPelajaran = MataPelajaran::create([
            'nama' => 'Ilmu Pengetahuan Alam',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);
        PengaturanMataPelajaran::create([
            'tahun_pelajaran_id' => $tahunLama->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 7,
            'kode' => 'IPA7',
            'kkm' => 70,
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->put(route('mata-pelajaran.update', $mataPelajaran), [
                'tahun_pelajaran_id' => $tahunBaru->id,
                'nama' => 'Ilmu Pengetahuan Alam',
                'kelompok' => 'Umum',
                'urutan' => 2,
                'aktif' => '1',
                'pengaturan' => [
                    7 => ['aktif' => '1', 'kode' => 'IPA7', 'kkm' => 75],
                    8 => ['aktif' => '0'],
                    9 => ['aktif' => '0'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pengaturan_mata_pelajaran', [
            'tahun_pelajaran_id' => $tahunLama->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 7,
            'kkm' => 70,
        ]);
        $this->assertDatabaseHas('pengaturan_mata_pelajaran', [
            'tahun_pelajaran_id' => $tahunBaru->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 7,
            'kkm' => 75,
        ]);
    }

    public function test_minimal_satu_tingkat_harus_diaktifkan(): void
    {
        [$administrator, $tahun] = $this->dataDasar();

        $this->actingAs($administrator)
            ->from(route('mata-pelajaran.create'))
            ->post(route('mata-pelajaran.store'), [
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'Prakarya',
                'aktif' => '1',
                'pengaturan' => [
                    7 => ['aktif' => '0'],
                    8 => ['aktif' => '0'],
                    9 => ['aktif' => '0'],
                ],
            ])
            ->assertRedirect(route('mata-pelajaran.create'))
            ->assertSessionHasErrors('pengaturan');

        $this->assertDatabaseMissing('mata_pelajaran', ['nama' => 'Prakarya']);
    }

    public function test_daftar_mapel_menampilkan_kode_dan_kkm_per_tingkat(): void
    {
        [$administrator, $tahun] = $this->dataDasar();
        $mataPelajaran = MataPelajaran::create([
            'nama' => 'Bahasa Indonesia',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);
        PengaturanMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 8,
            'kode' => 'BINDO8',
            'kkm' => 73,
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->get(route('mata-pelajaran.index', ['tahun_pelajaran_id' => $tahun->id]))
            ->assertOk()
            ->assertSee('Bahasa Indonesia')
            ->assertSee('BINDO8')
            ->assertSee('Kelas VIII')
            ->assertSee('73');
    }

    public function test_pengembangan_diri_dapat_disimpan_tanpa_kkm_dan_memakai_predikat(): void
    {
        [$administrator, $tahun] = $this->dataDasar();

        $response = $this->actingAs($administrator)
            ->post(route('mata-pelajaran.store'), [
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'Pramuka',
                'kelompok' => 'Ekstrakurikuler',
                'urutan' => 20,
                'aktif' => '1',
                'pengaturan' => [
                    7 => ['aktif' => '1', 'kode' => 'PRAM7'],
                    8 => ['aktif' => '1', 'kode' => 'PRAM8'],
                    9 => ['aktif' => '0'],
                ],
            ]);

        $mataPelajaran = MataPelajaran::where('nama', 'Pramuka')->firstOrFail();

        $response->assertSessionHasNoErrors();
        $this->assertTrue($mataPelajaran->menggunakanPredikat());
        $this->assertDatabaseHas('pengaturan_mata_pelajaran', [
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 7,
            'kkm' => null,
            'aktif' => true,
        ]);
    }

    public function test_mapel_angka_tetap_mewajibkan_kkm_pada_tingkat_aktif(): void
    {
        [$administrator, $tahun] = $this->dataDasar();

        $this->actingAs($administrator)
            ->from(route('mata-pelajaran.create'))
            ->post(route('mata-pelajaran.store'), [
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'Matematika',
                'kelompok' => 'Umum',
                'aktif' => '1',
                'pengaturan' => [
                    7 => ['aktif' => '1', 'kode' => 'MTK7'],
                    8 => ['aktif' => '0'],
                    9 => ['aktif' => '0'],
                ],
            ])
            ->assertRedirect(route('mata-pelajaran.create'))
            ->assertSessionHasErrors('pengaturan.7.kkm');

        $this->assertDatabaseMissing('mata_pelajaran', ['nama' => 'Matematika']);
    }

    private function dataDasar(): array
    {
        $administrator = Pengguna::create([
            'nama' => 'Administrator Uji',
            'username' => 'administrator-uji',
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'administrator',
            'aktif' => true,
            'akun_sistem' => true,
        ]);
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);

        return [$administrator, $tahun];
    }
}
