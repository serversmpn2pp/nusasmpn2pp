<?php

namespace Tests\Feature\Api;

use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PengaturanMataPelajaran;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruMataPelajaranApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_penugasan_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.guru-mata-pelajaran.index'))->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Tanpa Izin Guru Mapel',
            'username' => 'tanpa.izin.guru.mapel',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.guru-mata-pelajaran.index'))
            ->assertForbidden();
    }

    public function test_administrator_dapat_memuat_referensi_menambah_mencari_dan_mengubah_penugasan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $guru, $mapel, $kelasA, $kelasB] = $this->dataAkademik();
        $token = $this->token($administrator);

        $this->withToken($token)
            ->getJson(route('api.v1.guru-mata-pelajaran.referensi'))
            ->assertOk()
            ->assertJsonFragment(['nama' => 'Guru IPA Mobile'])
            ->assertJsonFragment(['nama' => 'IPA Mobile Native'])
            ->assertJsonPath('data.mata_pelajaran.0.kelas_ids_tersedia.0', $kelasA->id)
            ->assertJsonPath('data.mata_pelajaran.0.kelas_ids_tersedia.1', $kelasB->id);

        $this->withToken($token)
            ->postJson(route('api.v1.guru-mata-pelajaran.store'), [
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_ids' => [$kelasA->id, $kelasB->id],
                'mata_pelajaran_id' => $mapel->id,
                'pegawai_id' => $guru->id,
                'jenis_penugasan' => 'pengampu',
                'aktif' => true,
                'keterangan' => 'Dibuat dari NUSA Mobile',
            ])
            ->assertCreated()
            ->assertJsonPath('data.jumlah_baru', 2);

        $penugasan = GuruMataPelajaran::where('kelas_id', $kelasA->id)->firstOrFail();
        $this->withToken($token)
            ->getJson(route('api.v1.guru-mata-pelajaran.index', [
                'cari' => 'IPA Mobile',
                'tahun_pelajaran_id' => $tahun->id,
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.pegawai.nama', 'Guru IPA Mobile')
            ->assertJsonPath('data.items.0.mata_pelajaran.nama', 'IPA Mobile Native')
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);

        $this->withToken($token)
            ->patchJson(route('api.v1.guru-mata-pelajaran.update', $penugasan), [
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelasA->id,
                'mata_pelajaran_id' => $mapel->id,
                'pegawai_id' => $guru->id,
                'jenis_penugasan' => 'koordinator',
                'aktif' => false,
                'keterangan' => 'Diperbarui dari mobile',
            ])
            ->assertOk();
        $this->assertDatabaseHas('guru_mata_pelajaran', [
            'id' => $penugasan->id,
            'jenis_penugasan' => 'koordinator',
            'aktif' => false,
            'keterangan' => 'Diperbarui dari mobile',
        ]);
    }

    public function test_mapel_yang_tidak_tersedia_ditolak_dan_pengguna_baca_tidak_dapat_mengubah(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $guru, $mapel, $kelasA] = $this->dataAkademik();
        $kelasSembilan = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'IX.MOBILE',
            'tingkat' => 9,
            'aktif' => true,
        ]);

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.guru-mata-pelajaran.store'), [
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_ids' => [$kelasSembilan->id],
                'mata_pelajaran_id' => $mapel->id,
                'pegawai_id' => $guru->id,
                'jenis_penugasan' => 'pengampu',
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mata_pelajaran_id');

        $pembaca = Pengguna::create([
            'pegawai_id' => $guru->id,
            'nama' => $guru->nama_lengkap,
            'username' => 'guru.mapel.baca.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pembaca->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());
        $this->assertFalse($pembaca->fresh()->administrator());
        $this->assertFalse($pembaca->fresh()->memilikiIzin('guru_mapel.kelola'));
        $this->app['auth']->forgetGuards();

        $this->withToken($this->token($pembaca))
            ->getJson(route('api.v1.guru-mata-pelajaran.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($this->token($pembaca))
            ->getJson(route('api.v1.guru-mata-pelajaran.referensi'))
            ->assertForbidden();
        $this->assertNotNull($kelasA);
    }

    private function dataAkademik(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'aktif' => true,
        ]);
        $guru = Pegawai::create([
            'nama_lengkap' => 'Guru IPA Mobile',
            'nip' => '198501012010011055',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $mapel = MataPelajaran::create([
            'kode' => 'IPA-NATIVE',
            'nama' => 'IPA Mobile Native',
            'kelompok' => 'Wajib',
            'aktif' => true,
        ]);
        foreach ([7, 8] as $tingkat) {
            PengaturanMataPelajaran::create([
                'tahun_pelajaran_id' => $tahun->id,
                'mata_pelajaran_id' => $mapel->id,
                'tingkat' => $tingkat,
                'kode' => "IPA{$tingkat}",
                'kkm' => 75,
                'aktif' => true,
            ]);
        }
        $kelasA = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.MOBILE.A',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $kelasB = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.MOBILE.B',
            'tingkat' => 8,
            'aktif' => true,
        ]);

        return [$tahun, $guru, $mapel, $kelasA, $kelasB];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
