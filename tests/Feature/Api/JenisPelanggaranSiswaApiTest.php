<?php

namespace Tests\Feature\Api;

use App\Models\ButirPelanggaranLaporan;
use App\Models\Izin;
use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JenisPelanggaranSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_dan_mengirim_filter_ringkasan_referensi_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->getJson(route('api.v1.jenis-pelanggaran-siswa.index'))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.jenis-pelanggaran-siswa.index', [
                'cari' => 'R001',
                'status' => 'aktif',
                'tingkat' => 'ringan',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.cari', 'R001')
            ->assertJsonPath('data.filter.status', 'aktif')
            ->assertJsonPath('data.filter.tingkat', 'ringan')
            ->assertJsonPath('data.ringkasan.total', 69)
            ->assertJsonPath('data.ringkasan.aktif', 69)
            ->assertJsonPath('data.ringkasan.nonaktif', 0)
            ->assertJsonPath('data.ringkasan.per_tingkat.ringan', 24)
            ->assertJsonPath('data.ringkasan.per_tingkat.sedang', 25)
            ->assertJsonPath('data.ringkasan.per_tingkat.berat', 20)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.kode', 'R001')
            ->assertJsonPath('data.items.0.poin', 15)
            ->assertJsonPath('data.items.0.tingkat_label', 'Ringan')
            ->assertJsonStructure([
                'data' => [
                    'referensi' => ['tingkat', 'kategori'],
                    'items' => [[
                        'id',
                        'kode',
                        'nama',
                        'tingkat',
                        'poin',
                        'urutan',
                        'aktif',
                        'kategori',
                        'jumlah_pemakaian',
                    ]],
                    'paginasi',
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'jenis-pelanggaran-poin',
                'status' => 'tersedia',
                'rute' => '/jenis-pelanggaran-siswa',
            ]);
    }

    public function test_administrator_dapat_menambah_mengubah_dan_menonaktifkan_tanpa_mengubah_bobot_laporan_lama(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $kategori = KategoriPembinaanSiswa::where('kode', 'KEDISIPLINAN')->firstOrFail();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.jenis-pelanggaran-siswa.store'), [
                'kategori_pembinaan_siswa_id' => $kategori->id,
                'kode' => ' x 001 ',
                'nama' => '  Tidak menjaga ketenangan perpustakaan  ',
                'tingkat' => 'ringan',
                'poin' => 5,
                'urutan' => 70,
                'aktif' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.kode', 'X_001')
            ->assertJsonPath('data.nama', 'Tidak menjaga ketenangan perpustakaan')
            ->assertJsonPath('data.kategori.kode', 'KEDISIPLINAN')
            ->assertJsonPath('data.poin', 5);
        $id = (int) $response->json('data.id');

        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Riwayat Poin API',
            'nis' => 'API-JP-001',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $laporan = LaporanPembinaanSiswa::create([
            'nomor_laporan' => 'LP-API-JENIS-001',
            'tanggal_kejadian' => now()->toDateString(),
            'siswa_id' => $siswa->id,
            'tingkat' => 'ringan',
            'kronologi' => 'Data uji riwayat poin.',
        ]);
        ButirPelanggaranLaporan::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'jenis_pelanggaran_siswa_id' => $id,
            'kode_pelanggaran' => 'X_001',
            'nama_pelanggaran' => 'Tidak menjaga ketenangan perpustakaan',
            'tingkat' => 'ringan',
            'poin' => 5,
        ]);

        $this->withToken($token)
            ->patchJson(route('api.v1.jenis-pelanggaran-siswa.update', $id), [
                'kategori_pembinaan_siswa_id' => null,
                'kode' => 'X001',
                'nama' => 'Mengganggu ketenangan perpustakaan',
                'tingkat' => 'sedang',
                'poin' => 25,
                'urutan' => 71,
                'aktif' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('jenis_pelanggaran_siswa', [
            'id' => $id,
            'kode' => 'X001',
            'tingkat' => 'sedang',
            'poin' => 25,
        ]);
        $this->assertDatabaseHas('butir_pelanggaran_laporan', [
            'jenis_pelanggaran_siswa_id' => $id,
            'kode_pelanggaran' => 'X_001',
            'nama_pelanggaran' => 'Tidak menjaga ketenangan perpustakaan',
            'tingkat' => 'ringan',
            'poin' => 5,
        ]);

        $this->withToken($token)
            ->deleteJson(route('api.v1.jenis-pelanggaran-siswa.destroy', $id))
            ->assertOk();

        $this->assertDatabaseHas('jenis_pelanggaran_siswa', ['id' => $id, 'aktif' => false]);
        $this->assertSame(70, JenisPelanggaranSiswa::count());
        $this->assertDatabaseHas('butir_pelanggaran_laporan', ['jenis_pelanggaran_siswa_id' => $id, 'poin' => 5]);
    }

    public function test_kode_harus_unik_dan_tingkat_serta_poin_harus_valid(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.jenis-pelanggaran-siswa.store'), [
                'kode' => 'r001',
                'nama' => 'Data tidak valid',
                'tingkat' => 'sangat_berat',
                'poin' => 0,
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kode', 'tingkat', 'poin']);
    }

    public function test_pengguna_tanpa_izin_pengaturan_poin_tidak_dapat_membuka_modul(): void
    {
        $pengguna = $this->penggunaDenganIzin('bk.lihat');

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.jenis-pelanggaran-siswa.index'))
            ->assertForbidden();
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca BK Jenis Pelanggaran',
            'kode' => 'pembaca_bk_jenis_pelanggaran',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca BK Jenis Pelanggaran',
            'username' => 'pembaca.bk.jenis',
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
        return $pengguna->createToken('Perangkat Jenis Pelanggaran', ['mobile'])->plainTextToken;
    }
}
