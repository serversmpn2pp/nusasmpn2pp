<?php

namespace Tests\Feature\Api;

use App\Models\AturanSanksiPoin;
use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AturanSanksiPoinApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_dan_mengirim_ringkasan_filter_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->getJson(route('api.v1.aturan-sanksi-poin.index'))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.aturan-sanksi-poin.index', [
                'cari' => 'teguran',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.cari', 'teguran')
            ->assertJsonPath('data.filter.status', 'aktif')
            ->assertJsonPath('data.ringkasan.total', 7)
            ->assertJsonPath('data.ringkasan.aktif', 7)
            ->assertJsonPath('data.ringkasan.nonaktif', 0)
            ->assertJsonPath('data.ringkasan.jumlah_sanksi_terpicu', 0)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.batas_poin', 25)
            ->assertJsonStructure([
                'data' => [
                    'items' => [[
                        'id',
                        'batas_poin',
                        'nama',
                        'deskripsi',
                        'urutan',
                        'aktif',
                        'jumlah_sanksi_terpicu',
                    ]],
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'aturan-sanksi-poin',
                'status' => 'tersedia',
                'rute' => '/aturan-sanksi-poin',
            ]);
    }

    public function test_administrator_dapat_menambah_mengubah_dan_menonaktifkan_tanpa_menghapus_sanksi_terpicu(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.aturan-sanksi-poin.store'), [
                'batas_poin' => 60,
                'nama' => '  Pembinaan Bersama Wali Kelas  ',
                'deskripsi' => '  Pembinaan terjadwal bersama wali kelas.  ',
                'urutan' => 3,
                'aktif' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.batas_poin', 60)
            ->assertJsonPath('data.nama', 'Pembinaan Bersama Wali Kelas')
            ->assertJsonPath('data.deskripsi', 'Pembinaan terjadwal bersama wali kelas.')
            ->assertJsonPath('data.aktif', true);
        $id = (int) $response->json('data.id');

        $tahun = TahunPelajaran::create([
            'nama' => '2031/2032',
            'tanggal_mulai' => '2031-07-01',
            'tanggal_selesai' => '2032-06-30',
            'aktif' => false,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Sanksi API',
            'nis' => 'API-AS-001',
            'aktif' => true,
        ]);
        $sanksi = SanksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'aturan_sanksi_poin_id' => $id,
            'poin_saat_terpicu' => 63,
            'status' => 'menunggu',
            'terpicu_pada' => now(),
        ]);

        $this->withToken($token)
            ->patchJson(route('api.v1.aturan-sanksi-poin.update', $id), [
                'batas_poin' => 65,
                'nama' => 'Pembinaan Bersama Orang Tua',
                'deskripsi' => 'Pertemuan pembinaan bersama orang tua.',
                'urutan' => 4,
                'aktif' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('aturan_sanksi_poin', [
            'id' => $id,
            'batas_poin' => 65,
            'nama' => 'Pembinaan Bersama Orang Tua',
        ]);
        $this->assertDatabaseHas('sanksi_poin_siswa', [
            'id' => $sanksi->id,
            'aturan_sanksi_poin_id' => $id,
            'poin_saat_terpicu' => 63,
            'status' => 'menunggu',
        ]);

        $this->withToken($token)
            ->deleteJson(route('api.v1.aturan-sanksi-poin.destroy', $id))
            ->assertOk();

        $this->assertDatabaseHas('aturan_sanksi_poin', ['id' => $id, 'aktif' => false]);
        $this->assertDatabaseHas('sanksi_poin_siswa', ['id' => $sanksi->id]);
        $this->assertSame(8, AturanSanksiPoin::count());

        $this->withToken($token)
            ->getJson(route('api.v1.aturan-sanksi-poin.index', ['status' => 'nonaktif']))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.jumlah_sanksi_terpicu', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.jumlah_sanksi_terpicu', 1);
    }

    public function test_ambang_poin_harus_unik_dan_nilai_wajib_valid(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.aturan-sanksi-poin.store'), [
                'batas_poin' => 25,
                'nama' => '',
                'deskripsi' => '',
                'urutan' => -1,
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['batas_poin', 'nama', 'deskripsi', 'urutan']);
    }

    public function test_pengguna_tanpa_izin_pengaturan_poin_tidak_dapat_membuka_modul(): void
    {
        $pengguna = $this->penggunaDenganIzin('poin_siswa.lihat');

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.aturan-sanksi-poin.index'))
            ->assertForbidden();
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Aturan Sanksi Mobile',
            'kode' => 'pembaca_aturan_sanksi_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Aturan Sanksi Mobile',
            'username' => 'pembaca.aturan.sanksi',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach($peran);

        return $pengguna;
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Aturan Sanksi', ['mobile'])->plainTextToken;
    }
}
