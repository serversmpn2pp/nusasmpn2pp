<?php

namespace Tests\Feature\Api;

use App\Models\MataPelajaran;
use App\Models\PengaturanMataPelajaran;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MataPelajaranApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_mata_pelajaran_memerlukan_token_dan_izin_yang_sesuai(): void
    {
        $this->getJson(route('api.v1.mata-pelajaran.index'))->assertUnauthorized();

        $pembaca = Pengguna::create([
            'nama' => 'Pembaca Mata Pelajaran',
            'username' => 'pembaca.mapel.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pembaca->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());

        $this->withToken($this->token($pembaca))
            ->getJson(route('api.v1.mata-pelajaran.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($this->token($pembaca))
            ->getJson(route('api.v1.mata-pelajaran.referensi'))
            ->assertForbidden();
        $this->withToken($this->token($pembaca))
            ->postJson(route('api.v1.mata-pelajaran.store'), [])
            ->assertForbidden();
    }

    public function test_administrator_dapat_memuat_referensi_menambah_mencari_dan_memfilter_mapel(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->tahun('2026/2027', true);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->getJson(route('api.v1.mata-pelajaran.referensi'))
            ->assertOk()
            ->assertJsonFragment(['nama' => 'Umum', 'menggunakan_predikat' => false])
            ->assertJsonFragment(['nama' => 'Ekstrakurikuler', 'menggunakan_predikat' => true])
            ->assertJsonPath('data.tingkat.0.label', 'VII');

        $this->withToken($token)
            ->postJson(route('api.v1.mata-pelajaran.store'), [
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'Matematika Mobile Native',
                'kelompok' => 'Umum',
                'urutan' => 2,
                'aktif' => true,
                'keterangan' => 'Dibuat dari Android',
                'pengaturan' => [
                    7 => ['aktif' => true, 'kode' => 'MTKM7', 'kkm' => 72],
                    8 => ['aktif' => true, 'kode' => 'MTKM8', 'kkm' => 74],
                    9 => ['aktif' => false, 'kode' => null, 'kkm' => null],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('pesan', 'Mata pelajaran berhasil ditambahkan.');

        $this->withToken($token)
            ->getJson(route('api.v1.mata-pelajaran.index', [
                'cari' => 'MTKM8',
                'tahun_pelajaran_id' => $tahun->id,
                'tingkat' => '8',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.nama', 'Matematika Mobile Native')
            ->assertJsonPath('data.items.0.jenis_penilaian', 'angka')
            ->assertJsonPath('data.items.0.pengaturan.1.kode', 'MTKM8')
            ->assertJsonPath('data.items.0.pengaturan.1.kkm', 74)
            ->assertJsonPath('data.filter.tahun_pelajaran_id', $tahun->id)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);
    }

    public function test_pengaturan_tahun_baru_tidak_mengubah_tahun_lama_dan_predikat_tanpa_kkm(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahunLama = $this->tahun('2026/2027', true);
        $tahunBaru = $this->tahun('2027/2028');
        $mapel = MataPelajaran::create([
            'nama' => 'Pramuka Mobile',
            'kelompok' => 'Ekstrakurikuler',
            'aktif' => true,
        ]);
        PengaturanMataPelajaran::create([
            'tahun_pelajaran_id' => $tahunLama->id,
            'mata_pelajaran_id' => $mapel->id,
            'tingkat' => 7,
            'kode' => 'PRML7',
            'kkm' => null,
            'aktif' => true,
        ]);

        $this->withToken($this->token($administrator))
            ->patchJson(route('api.v1.mata-pelajaran.update', $mapel), [
                'tahun_pelajaran_id' => $tahunBaru->id,
                'nama' => 'Pramuka Mobile',
                'kelompok' => 'Ekstrakurikuler',
                'urutan' => 20,
                'aktif' => true,
                'pengaturan' => [
                    7 => ['aktif' => true, 'kode' => 'PRMB7', 'kkm' => 99],
                    8 => ['aktif' => true, 'kode' => 'PRMB8', 'kkm' => null],
                    9 => ['aktif' => false, 'kode' => null, 'kkm' => null],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('pengaturan_mata_pelajaran', [
            'tahun_pelajaran_id' => $tahunLama->id,
            'mata_pelajaran_id' => $mapel->id,
            'tingkat' => 7,
            'kode' => 'PRML7',
        ]);
        $this->assertDatabaseHas('pengaturan_mata_pelajaran', [
            'tahun_pelajaran_id' => $tahunBaru->id,
            'mata_pelajaran_id' => $mapel->id,
            'tingkat' => 7,
            'kode' => 'PRMB7',
            'kkm' => null,
        ]);
    }

    public function test_validasi_mewajibkan_tingkat_aktif_kode_dan_kkm_mapel_angka(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->tahun('2026/2027', true);
        $token = $this->token($administrator);
        $dasar = [
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'IPA Validasi Mobile',
            'kelompok' => 'Umum',
            'aktif' => true,
        ];

        $this->withToken($token)
            ->postJson(route('api.v1.mata-pelajaran.store'), $dasar + [
                'pengaturan' => [
                    7 => ['aktif' => false],
                    8 => ['aktif' => false],
                    9 => ['aktif' => false],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pengaturan');

        $this->withToken($token)
            ->postJson(route('api.v1.mata-pelajaran.store'), $dasar + [
                'pengaturan' => [
                    7 => ['aktif' => true],
                    8 => ['aktif' => false],
                    9 => ['aktif' => false],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'pengaturan.7.kode',
                'pengaturan.7.kkm',
            ]);
    }

    private function tahun(string $nama, bool $aktif = false): TahunPelajaran
    {
        return TahunPelajaran::create([
            'nama' => $nama,
            'aktif' => $aktif,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
