<?php

namespace Tests\Feature\Api;

use App\Models\Barang;
use App\Models\Izin;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\MutasiStokBarang;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SaldoStokBarang;
use App\Models\SatuanBarang;
use App\Services\Inventaris\ProsesMutasiStokBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StokBarangApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutasi_masuk_keluar_dan_penyesuaian_memperbarui_saldo_dengan_jejak_audit(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$barang, $lokasi] = $this->master();
        $token = $this->token($administrator);

        $this->withToken($token)
            ->postJson(route('api.v1.mutasi-stok.store'), $this->payload($barang, $lokasi, [
                'jenis_mutasi' => 'masuk',
                'kategori_mutasi' => 'pembelian',
                'jumlah' => 12.5,
                'referensi' => ' BAST-001 ',
                'keterangan' => ' Stok diterima lengkap. ',
            ]))
            ->assertCreated()
            ->assertJsonPath('pesan', 'Mutasi stok berhasil dicatat.')
            ->assertJsonPath('data.jumlah_perubahan', 12.5)
            ->assertJsonPath('data.saldo_sebelum', 0)
            ->assertJsonPath('data.saldo_sesudah', 12.5)
            ->assertJsonPath('data.referensi', 'BAST-001')
            ->assertJsonPath('data.keterangan', 'Stok diterima lengkap.');

        $this->withToken($token)
            ->postJson(route('api.v1.mutasi-stok.store'), $this->payload($barang, $lokasi, [
                'jenis_mutasi' => 'keluar',
                'kategori_mutasi' => 'pengeluaran_pemakaian',
                'jumlah' => 4,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.jumlah_perubahan', -4)
            ->assertJsonPath('data.saldo_sebelum', 12.5)
            ->assertJsonPath('data.saldo_sesudah', 8.5);

        $this->withToken($token)
            ->postJson(route('api.v1.mutasi-stok.store'), $this->payload($barang, $lokasi, [
                'jenis_mutasi' => 'penyesuaian',
                'kategori_mutasi' => 'penyesuaian_fisik',
                'jumlah' => 6,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.jumlah_perubahan', -2.5)
            ->assertJsonPath('data.saldo_sesudah', 6);

        $this->assertSame('6.00', SaldoStokBarang::firstOrFail()->jumlah);
        $this->assertDatabaseCount('mutasi_stok_barang', 3);

        $mutasi = MutasiStokBarang::query()->where('jenis_mutasi', 'masuk')->firstOrFail();
        $response = $this->withToken($token)
            ->getJson(route('api.v1.mutasi-stok.show', $mutasi))
            ->assertOk()
            ->assertJsonPath('data.barang.nama', 'Spidol Hitam')
            ->assertJsonPath('data.barang.kategori', 'Alat Tulis')
            ->assertJsonPath('data.lokasi.nama', 'Gudang Utama')
            ->assertJsonPath('data.dibuat_oleh', $administrator->nama);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_saldo_dan_mutasi_mengikuti_ringkasan_filter_pilihan_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$barang, $lokasi, $kategori, $satuan] = $this->master();
        $lokasiKelas = LokasiBarang::create([
            'kode' => 'KLS-8A',
            'nama' => 'Kelas 8A',
            'jenis' => 'ruang',
            'aktif' => true,
        ]);
        $barangHabis = $this->barang($kategori, $satuan, $lokasi, 'BHP-000002', 'Tinta Printer', 2);
        $barangAman = $this->barang($kategori, $satuan, $lokasi, 'BHP-000003', 'Kertas A4', 5);
        $token = $this->token($administrator);

        SaldoStokBarang::create(['barang_id' => $barang->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 3]);
        SaldoStokBarang::create(['barang_id' => $barangHabis->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 0]);
        SaldoStokBarang::create(['barang_id' => $barangAman->id, 'lokasi_barang_id' => $lokasiKelas->id, 'jumlah' => 20]);

        $this->withToken($token)
            ->postJson(route('api.v1.mutasi-stok.store'), $this->payload($barang, $lokasi, [
                'jenis_mutasi' => 'masuk',
                'kategori_mutasi' => 'pembelian',
                'jumlah' => 2,
                'referensi' => 'PO-SPIDOL-01',
            ]))
            ->assertCreated();

        $this->withToken($token)
            ->getJson(route('api.v1.saldo-stok', [
                'cari' => 'spidol',
                'status_stok' => 'menipis',
                'kategori_barang_id' => $kategori->id,
                'lokasi_barang_id' => $lokasi->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.baris_saldo', 3)
            ->assertJsonPath('data.ringkasan.lokasi_stok', 2)
            ->assertJsonPath('data.ringkasan.menipis', 1)
            ->assertJsonPath('data.ringkasan.habis', 1)
            ->assertJsonPath('data.filter.status_stok', 'menipis')
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonPath('data.items.0.barang.nama', 'Spidol Hitam')
            ->assertJsonPath('data.items.0.jumlah', 5)
            ->assertJsonPath('data.items.0.status', 'menipis')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonCount(2, 'data.pilihan.lokasi');

        $this->withToken($token)
            ->getJson(route('api.v1.mutasi-stok.index', [
                'cari' => 'PO-SPIDOL',
                'jenis_mutasi' => 'masuk',
                'barang_id' => $barang->id,
                'lokasi_barang_id' => $lokasi->id,
                'tanggal_mulai' => now()->toDateString(),
                'tanggal_selesai' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.ringkasan.hari_ini', 1)
            ->assertJsonPath('data.ringkasan.masuk_hari_ini', 2)
            ->assertJsonPath('data.ringkasan.keluar_hari_ini', 0)
            ->assertJsonPath('data.filter.barang_id', $barang->id)
            ->assertJsonPath('data.items.0.referensi', 'PO-SPIDOL-01')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonCount(3, 'data.pilihan.jenis_mutasi')
            ->assertJsonPath('data.pilihan.kategori_per_jenis.keluar.0', 'pengeluaran_pemakaian');

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment(['kode' => 'saldo-stok', 'status' => 'tersedia', 'rute' => '/saldo-stok'])
            ->assertJsonFragment(['kode' => 'mutasi-stok', 'status' => 'tersedia', 'rute' => '/mutasi-stok']);
    }

    public function test_validasi_mencegah_kategori_salah_stok_minus_dan_perubahan_nol(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$barang, $lokasi] = $this->master();
        $token = $this->token($administrator);

        $this->withToken($token)
            ->postJson(route('api.v1.mutasi-stok.store'), $this->payload($barang, $lokasi, [
                'jenis_mutasi' => 'masuk',
                'kategori_mutasi' => 'rusak',
                'jumlah' => 5,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kategori_mutasi');

        $this->withToken($token)
            ->postJson(route('api.v1.mutasi-stok.store'), $this->payload($barang, $lokasi, [
                'jenis_mutasi' => 'keluar',
                'kategori_mutasi' => 'rusak',
                'jumlah' => 1,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jumlah');

        $this->withToken($token)
            ->postJson(route('api.v1.mutasi-stok.store'), $this->payload($barang, $lokasi, [
                'jenis_mutasi' => 'penyesuaian',
                'kategori_mutasi' => 'penyesuaian_fisik',
                'jumlah' => 0,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jumlah');
    }

    public function test_izin_lihat_hanya_dapat_membaca_saldo_mutasi_dan_detail(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$barang, $lokasi] = $this->master();
        $mutasi = app(ProsesMutasiStokBarang::class)->catat(
            $this->payload($barang, $lokasi, [
                'jenis_mutasi' => 'masuk',
                'kategori_mutasi' => 'stok_awal',
                'jumlah' => 10,
            ]),
            $administrator->id,
        );
        $pembaca = $this->penggunaDenganIzin('barang.lihat');
        $token = $this->token($pembaca);

        $this->withToken($token)
            ->getJson(route('api.v1.saldo-stok'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($token)
            ->getJson(route('api.v1.mutasi-stok.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($token)
            ->getJson(route('api.v1.mutasi-stok.show', $mutasi))
            ->assertOk();
        $this->withToken($token)
            ->postJson(route('api.v1.mutasi-stok.store'), $this->payload($barang, $lokasi, []))
            ->assertForbidden();
    }

    private function master(): array
    {
        $kategori = KategoriBarang::create(['kode' => 'ATK', 'nama' => 'Alat Tulis', 'aktif' => true]);
        $satuan = SatuanBarang::create(['kode' => 'PCS', 'nama' => 'Pcs', 'aktif' => true]);
        $lokasi = LokasiBarang::create(['kode' => 'GUDANG', 'nama' => 'Gudang Utama', 'jenis' => 'gudang', 'aktif' => true]);
        $barang = $this->barang($kategori, $satuan, $lokasi, 'BHP-000001', 'Spidol Hitam', 5);

        return [$barang, $lokasi, $kategori, $satuan];
    }

    private function barang(
        KategoriBarang $kategori,
        SatuanBarang $satuan,
        LokasiBarang $lokasi,
        string $kode,
        string $nama,
        float $stokMinimum,
    ): Barang {
        return Barang::create([
            'kode' => $kode,
            'nama' => $nama,
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'tipe_pengelolaan' => 'habis_pakai',
            'jenis_barang' => 'habis_pakai',
            'stok_minimum' => $stokMinimum,
            'aktif' => true,
        ]);
    }

    private function payload(Barang $barang, LokasiBarang $lokasi, array $ubah): array
    {
        return array_replace([
            'barang_id' => $barang->id,
            'lokasi_barang_id' => $lokasi->id,
            'jenis_mutasi' => 'masuk',
            'kategori_mutasi' => 'pembelian',
            'tanggal_mutasi' => now()->toDateString(),
            'jumlah' => 5,
            'referensi' => null,
            'keterangan' => null,
        ], $ubah);
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Stok Mobile',
            'kode' => 'pembaca_stok_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Stok Mobile',
            'username' => 'pembaca.stok.mobile',
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
        return $pengguna->createToken('Perangkat Stok Barang', ['mobile'])->plainTextToken;
    }
}
