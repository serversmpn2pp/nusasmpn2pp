<?php

namespace Tests\Feature\Api;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\SaldoStokBarang;
use App\Models\SatuanBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesPeminjamanBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KatalogBarangApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_katalog_native_menyamai_filter_ringkasan_lokasi_dan_peminjam_desktop(): void
    {
        [$administrator, , $pegawai, $kategori, $lokasi, $aset, , $unitDipinjam] = $this->dataDasar();
        app(ProsesPeminjamanBarang::class)->catat([
            'jenis_peminjam' => 'pegawai',
            'pegawai_id' => $pegawai->id,
            'cara_input_peminjam' => 'manual',
            'tanggal_peminjaman' => now()->toDateString(),
            'rencana_kembali' => now()->addDays(5)->toDateString(),
            'items' => [[
                'tipe_item' => 'unit',
                'unit_barang_id' => $unitDipinjam->id,
                'cara_input_barang' => 'manual',
            ]],
        ], $administrator->id);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->getJson(route('api.v1.katalog-barang.index', [
                'kata_kunci' => 'Laptop',
                'kategori_barang_id' => $kategori->id,
                'jenis_barang' => 'tidak_habis_pakai',
                'ketersediaan' => 'dipinjam',
            ]))
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJsonPath('data.ringkasan.barang_aktif', 3)
            ->assertJsonPath('data.ringkasan.unit_tersedia', 1)
            ->assertJsonPath('data.ringkasan.unit_dipinjam', 1)
            ->assertJsonPath('data.ringkasan.stok_tersedia', 1)
            ->assertJsonPath('data.ringkasan.stok_habis', 1)
            ->assertJsonPath('data.filter.ketersediaan', 'dipinjam')
            ->assertJsonPath('data.items.0.id', $aset->id)
            ->assertJsonPath('data.items.0.jumlah_tersedia', 1)
            ->assertJsonPath('data.items.0.jumlah_unit', 2)
            ->assertJsonPath('data.items.0.jumlah_dipinjam', 1)
            ->assertJsonPath('data.hak_akses.dapat_mengajukan', false)
            ->assertJsonFragment(['nilai' => 'tidak_tersedia', 'label' => 'Tidak tersedia']);

        $this->withToken($token)
            ->getJson(route('api.v1.katalog-barang.show', $aset))
            ->assertOk()
            ->assertJsonPath('data.barang.lokasi.0.lokasi', $lokasi->nama)
            ->assertJsonPath('data.barang.lokasi.0.jumlah', 2)
            ->assertJsonPath('data.barang.lokasi.0.tersedia', 1)
            ->assertJsonPath('data.barang.lokasi.0.dipinjam', 1)
            ->assertJsonPath('data.barang.peminjam_aktif.0.nama', $pegawai->nama_lengkap)
            ->assertJsonPath('data.barang.peminjam_aktif.0.kode_inventaris', $unitDipinjam->kode_inventaris)
            ->assertJsonCount(2, 'data.barang.unit');
    }

    public function test_akun_pegawai_dapat_membuka_pengajuan_dari_detail_katalog(): void
    {
        [, $pengguna, , , , $aset] = $this->dataDasar();

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.katalog-barang.show', $aset))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_mengajukan', true)
            ->assertJsonPath('data.barang.jenis_layanan', 'peminjaman');

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'katalog-barang',
                'status' => 'tersedia',
                'rute' => '/katalog-barang',
            ]);
    }

    private function dataDasar(): array
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = Pegawai::create(['nama_lengkap' => 'Dina Kurnia, S.Pd.', 'nip' => '198505052010012001', 'aktif' => true]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $kategori = KategoriBarang::create(['kode' => 'TIK', 'nama' => 'Peralatan TIK', 'aktif' => true]);
        $satuan = SatuanBarang::create(['kode' => 'BH', 'nama' => 'Buah', 'aktif' => true]);
        $lokasi = LokasiBarang::create(['kode' => 'LAB', 'nama' => 'Labor Komputer', 'jenis' => 'ruangan', 'aktif' => true]);
        $aset = Barang::create([
            'kode' => '02.06.01.05.40',
            'nama' => 'Laptop Chromebook',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'tipe_pengelolaan' => 'aset_individual',
            'jenis_barang' => 'tidak_habis_pakai',
            'deskripsi' => 'Perangkat pembelajaran siswa.',
            'aktif' => true,
        ]);
        $unitTersedia = UnitBarang::create([
            'barang_id' => $aset->id,
            'nomor_unit' => 1,
            'kode_inventaris' => 'AST-2026-000001',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'aktif' => true,
        ]);
        $unitDipinjam = UnitBarang::create([
            'barang_id' => $aset->id,
            'nomor_unit' => 2,
            'kode_inventaris' => 'AST-2026-000002',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'aktif' => true,
        ]);
        $stok = Barang::create([
            'kode' => 'BHP-001',
            'nama' => 'Spidol',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'tipe_pengelolaan' => 'habis_pakai',
            'jenis_barang' => 'habis_pakai',
            'aktif' => true,
        ]);
        SaldoStokBarang::create(['barang_id' => $stok->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 10]);
        Barang::create([
            'kode' => 'BHP-002',
            'nama' => 'Tinta Printer',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'tipe_pengelolaan' => 'habis_pakai',
            'jenis_barang' => 'habis_pakai',
            'aktif' => true,
        ]);

        return [$administrator, $pengguna, $pegawai, $kategori, $lokasi, $aset, $unitTersedia, $unitDipinjam];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Katalog Barang', ['mobile'])->plainTextToken;
    }
}
