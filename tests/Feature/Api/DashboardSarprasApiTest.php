<?php

namespace Tests\Feature\Api;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\Pegawai;
use App\Models\PeminjamanBarang;
use App\Models\Pengguna;
use App\Models\SaldoStokBarang;
use App\Models\SatuanBarang;
use App\Models\UnitBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSarprasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_sarpras_memerlukan_token(): void
    {
        $this->getJson(route('api.v1.dashboard-sarpras'))->assertUnauthorized();
    }

    public function test_administrator_menerima_ringkasan_sarpras_yang_sama_dengan_data_operasional(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $kategori = KategoriBarang::create([
            'kode' => 'ELK-MOBILE',
            'nama' => 'Elektronik Mobile',
            'aktif' => true,
        ]);
        $satuan = SatuanBarang::create([
            'kode' => 'UNIT-MOBILE',
            'nama' => 'Unit Mobile',
            'aktif' => true,
        ]);
        $lokasi = LokasiBarang::create([
            'kode' => 'GDG-MOBILE',
            'nama' => 'Gudang Mobile',
            'jenis' => 'gudang',
            'aktif' => true,
        ]);
        $stok = $this->buatBarang($kategori->id, $satuan->id, $lokasi->id, 'STK-MOBILE', 'Kertas Mobile', 'habis_pakai', 5);
        $this->buatBarang($kategori->id, $satuan->id, $lokasi->id, 'BLM-MOBILE', 'Spidol Mobile', 'stok_dikembalikan', 2);
        $aset = $this->buatBarang($kategori->id, $satuan->id, $lokasi->id, 'AST-MOBILE', 'Laptop Mobile', 'aset_individual', 0);

        SaldoStokBarang::create([
            'barang_id' => $stok->id,
            'lokasi_barang_id' => $lokasi->id,
            'jumlah' => 2,
        ]);
        UnitBarang::create([
            'barang_id' => $aset->id,
            'nomor_unit' => 1,
            'urutan_dalam_penerimaan' => 1,
            'kode_inventaris' => 'AST-MOBILE.01',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'aktif' => true,
        ]);
        UnitBarang::create([
            'barang_id' => $aset->id,
            'nomor_unit' => 2,
            'urutan_dalam_penerimaan' => 2,
            'kode_inventaris' => 'AST-MOBILE.02',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'rusak_berat',
            'status_unit' => 'dalam_perbaikan',
            'aktif' => true,
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Peminjam Sarpras Mobile',
            'nip' => '198001012026091001',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        PeminjamanBarang::create([
            'nomor_peminjaman' => 'PJM-MOBILE-001',
            'jenis_peminjam' => 'pegawai',
            'pegawai_id' => $pegawai->id,
            'cara_input_peminjam' => 'manual',
            'tanggal_peminjaman' => now()->subDays(4)->toDateString(),
            'rencana_kembali' => now()->subDay()->toDateString(),
            'status' => 'dipinjam',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.dashboard-sarpras'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_mengelola_barang', true)
            ->assertJsonPath('data.ringkasan.jenis_barang', 3)
            ->assertJsonPath('data.ringkasan.unit_aset', 2)
            ->assertJsonPath('data.ringkasan.unit_tersedia', 1)
            ->assertJsonPath('data.ringkasan.peminjaman_aktif', 1)
            ->assertJsonPath('data.ringkasan.peminjaman_terlambat', 1)
            ->assertJsonPath('data.ringkasan.stok_menipis', 1)
            ->assertJsonPath('data.ringkasan.unit_perlu_perhatian', 1)
            ->assertJsonPath('data.ringkasan.stok_belum_dicatat', 1)
            ->assertJsonPath('data.stok_perlu_perhatian.0.nama', 'Kertas Mobile')
            ->assertJsonPath('data.peminjaman_terlambat.0.peminjam', 'Peminjam Sarpras Mobile')
            ->assertJsonPath('data.unit_perlu_perhatian.0.kode_inventaris', 'AST-MOBILE.02')
            ->assertJsonFragment([
                'kode' => 'inventaris-barang',
                'status' => 'tersedia',
                'rute' => '/barang',
            ])
            ->assertJsonFragment([
                'kode' => 'unit-aset',
                'status' => 'tersedia',
                'rute' => '/unit-aset',
            ])
            ->assertJsonFragment([
                'kode' => 'label-inventaris',
                'status' => 'tersedia',
                'rute' => '/label-inventaris',
            ])
            ->assertJsonMissing(['kode' => 'dashboard-sarpras'])
            ->assertJsonFragment([
                'kode' => 'kategori-barang',
                'status' => 'tersedia',
                'rute' => '/kategori-barang',
            ])
            ->assertJsonFragment([
                'kode' => 'satuan-barang',
                'status' => 'tersedia',
                'rute' => '/satuan-barang',
            ])
            ->assertJsonFragment([
                'kode' => 'lokasi-barang',
                'status' => 'tersedia',
                'rute' => '/lokasi-barang',
            ])
            ->assertJsonFragment([
                'kode' => 'sumber-perolehan',
                'status' => 'tersedia',
                'rute' => '/sumber-perolehan',
            ])
            ->assertJsonFragment([
                'kode' => 'pengaturan-inventaris',
                'status' => 'tersedia',
                'rute' => '/pengaturan-inventaris',
            ])
            ->assertJsonStructure([
                'data' => [
                    'tanggal_label',
                    'menu',
                    'distribusi_status_unit',
                    'aktivitas_terbaru',
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_pengguna_tanpa_izin_sarpras_ditolak(): void
    {
        $pengguna = Pengguna::create([
            'nama' => 'Pegawai Tanpa Sarpras',
            'username' => 'tanpa.sarpras.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.dashboard-sarpras'))
            ->assertForbidden();
    }

    private function buatBarang(
        int $kategoriId,
        int $satuanId,
        int $lokasiId,
        string $kode,
        string $nama,
        string $tipe,
        float $minimum,
    ): Barang {
        return Barang::create([
            'kode' => $kode,
            'nama' => $nama,
            'kategori_barang_id' => $kategoriId,
            'satuan_barang_id' => $satuanId,
            'lokasi_penyimpanan_id' => $lokasiId,
            'tipe_pengelolaan' => $tipe,
            'stok_minimum' => $minimum,
            'aktif' => true,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
