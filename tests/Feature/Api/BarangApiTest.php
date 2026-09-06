<?php

namespace Tests\Feature\Api;

use App\Models\Barang;
use App\Models\Izin;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SaldoStokBarang;
use App\Models\SatuanBarang;
use App\Models\UnitBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_dan_mengirim_ringkasan_filter_pilihan_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$kategori, $satuan, $lokasi] = $this->master();
        $this->barang($kategori, $satuan, $lokasi, '02.06.01.05.40', 'Laptop', 'tidak_habis_pakai');
        $kertas = $this->barang($kategori, $satuan, $lokasi, 'BHP-000001', 'Kertas', 'habis_pakai', 10);
        SaldoStokBarang::create([
            'barang_id' => $kertas->id,
            'lokasi_barang_id' => $lokasi->id,
            'jumlah' => 8,
        ]);

        $this->getJson(route('api.v1.barang.index'))->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.barang.index', [
                'cari' => 'kertas',
                'status' => 'aktif',
                'jenis_barang' => 'habis_pakai',
                'kategori_barang_id' => $kategori->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.aktif', 2)
            ->assertJsonPath('data.ringkasan.tidak_habis_pakai', 1)
            ->assertJsonPath('data.ringkasan.habis_pakai', 1)
            ->assertJsonPath('data.filter.jenis_barang', 'habis_pakai')
            ->assertJsonPath('data.filter.kategori_barang_id', $kategori->id)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.nama', 'Kertas')
            ->assertJsonPath('data.items.0.saldo_stok', 8)
            ->assertJsonPath('data.items.0.ringkasan_kuantitas', '8 Unit')
            ->assertJsonPath('data.items.0.kategori.nama', 'Peralatan')
            ->assertJsonCount(2, 'data.pilihan.jenis_barang')
            ->assertJsonCount(1, 'data.pilihan.kategori')
            ->assertJsonCount(1, 'data.pilihan.satuan')
            ->assertJsonCount(1, 'data.pilihan.lokasi');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'inventaris-barang',
                'status' => 'tersedia',
                'rute' => '/barang',
            ]);
    }

    public function test_administrator_dapat_menambah_melihat_mengubah_dan_menonaktifkan_barang(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$kategori, $satuan, $lokasi] = $this->master();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.barang.store'), [
                'kode' => '0206010540',
                'nama' => '  Laptop Chromebook  ',
                'kategori_barang_id' => $kategori->id,
                'satuan_barang_id' => $satuan->id,
                'lokasi_penyimpanan_id' => $lokasi->id,
                'jenis_barang' => 'tidak_habis_pakai',
                'stok_minimum' => 25,
                'deskripsi' => '  Perangkat pembelajaran.  ',
                'aktif' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.kode', '02.06.01.05.40')
            ->assertJsonPath('data.nama', 'Laptop Chromebook')
            ->assertJsonPath('data.stok_minimum', 0)
            ->assertJsonPath('data.jenis_dapat_diubah', true);
        $id = (int) $response->json('data.id');

        $this->withToken($token)
            ->getJson(route('api.v1.barang.show', $id))
            ->assertOk()
            ->assertJsonPath('data.barang.deskripsi', 'Perangkat pembelajaran.')
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);

        $this->withToken($token)
            ->patchJson(route('api.v1.barang.update', $id), [
                'kode' => null,
                'nama' => 'Kertas A4',
                'kategori_barang_id' => $kategori->id,
                'satuan_barang_id' => $satuan->id,
                'lokasi_penyimpanan_id' => null,
                'jenis_barang' => 'habis_pakai',
                'stok_minimum' => 12.5,
                'deskripsi' => null,
                'aktif' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.nama', 'Kertas A4')
            ->assertJsonPath('data.jenis_barang', 'habis_pakai')
            ->assertJsonPath('data.stok_minimum', 12.5);

        $this->assertMatchesRegularExpression('/^BHP-\d+$/', (string) Barang::findOrFail($id)->kode);

        $this->withToken($token)
            ->deleteJson(route('api.v1.barang.destroy', $id))
            ->assertOk();
        $this->assertDatabaseHas('barang', ['id' => $id, 'aktif' => false]);
    }

    public function test_jenis_barang_tidak_dapat_diubah_setelah_memiliki_unit(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$kategori, $satuan, $lokasi] = $this->master();
        $barang = $this->barang($kategori, $satuan, $lokasi, '02.06.01.05.40', 'Laptop', 'tidak_habis_pakai');
        UnitBarang::create([
            'barang_id' => $barang->id,
            'nomor_unit' => 1,
            'urutan_dalam_penerimaan' => 1,
            'kode_inventaris' => 'AST-2026-000001',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'aktif' => true,
        ]);

        $this->withToken($this->token($administrator))
            ->patchJson(route('api.v1.barang.update', $barang), [
                'kode' => null,
                'nama' => 'Laptop',
                'kategori_barang_id' => $kategori->id,
                'satuan_barang_id' => $satuan->id,
                'lokasi_penyimpanan_id' => $lokasi->id,
                'jenis_barang' => 'habis_pakai',
                'stok_minimum' => 5,
                'deskripsi' => null,
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jenis_barang']);
    }

    public function test_izin_lihat_hanya_dapat_membaca_bukan_mengubah_barang(): void
    {
        $pengguna = $this->penggunaDenganIzin('barang.lihat');
        [$kategori, $satuan, $lokasi] = $this->master();
        $barang = $this->barang($kategori, $satuan, $lokasi, '02.06.01.05.40', 'Laptop', 'tidak_habis_pakai');
        $token = $this->token($pengguna);

        $this->withToken($token)
            ->getJson(route('api.v1.barang.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($token)
            ->getJson(route('api.v1.barang.show', $barang))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($token)
            ->deleteJson(route('api.v1.barang.destroy', $barang))
            ->assertForbidden();
    }

    private function master(): array
    {
        return [
            KategoriBarang::create(['kode' => 'PERALATAN', 'nama' => 'Peralatan', 'aktif' => true]),
            SatuanBarang::create(['kode' => 'UNIT', 'nama' => 'Unit', 'aktif' => true]),
            LokasiBarang::create(['kode' => 'GUDANG', 'nama' => 'Gudang', 'jenis' => 'gudang', 'aktif' => true]),
        ];
    }

    private function barang(
        KategoriBarang $kategori,
        SatuanBarang $satuan,
        LokasiBarang $lokasi,
        string $kode,
        string $nama,
        string $jenis,
        float $minimum = 0,
    ): Barang {
        return Barang::create([
            'kode' => $kode,
            'nama' => $nama,
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'tipe_pengelolaan' => $jenis === 'habis_pakai' ? 'habis_pakai' : 'aset_individual',
            'jenis_barang' => $jenis,
            'stok_minimum' => $minimum,
            'aktif' => true,
        ]);
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Inventaris Barang Mobile',
            'kode' => 'pembaca_inventaris_barang_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Inventaris Barang Mobile',
            'username' => 'pembaca.inventaris.barang.mobile',
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
        return $pengguna->createToken('Perangkat Inventaris Barang', ['mobile'])->plainTextToken;
    }
}
