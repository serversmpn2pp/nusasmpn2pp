<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\DetailPeminjamanBarang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\Pegawai;
use App\Models\PeminjamanBarang;
use App\Models\Pengguna;
use App\Models\SaldoStokBarang;
use App\Models\SatuanBarang;
use App\Models\Siswa;
use App\Models\UnitBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KatalogBarangTest extends TestCase
{
    use RefreshDatabase;

    public function test_pegawai_dapat_melihat_ketersediaan_dan_peminjam_aktif(): void
    {
        [$pengguna, $lokasi, $barangAset, $barangHabisPakai] = $this->buatDataDasar();
        UnitBarang::create([
            'barang_id' => $barangAset->id,
            'nomor_unit' => 1,
            'kode_inventaris' => 'AST-2026-000001',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'aktif' => true,
        ]);
        $unitDipinjam = UnitBarang::create([
            'barang_id' => $barangAset->id,
            'nomor_unit' => 2,
            'kode_inventaris' => 'AST-2026-000002',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'dipinjam',
            'aktif' => true,
        ]);
        SaldoStokBarang::create([
            'barang_id' => $barangHabisPakai->id,
            'lokasi_barang_id' => $lokasi->id,
            'jumlah' => 12,
        ]);

        $peminjam = Pegawai::create([
            'nama_lengkap' => 'Dina Kurnia, S.Pd.',
            'nip' => '198505052010012001',
            'aktif' => true,
        ]);
        $peminjaman = PeminjamanBarang::create([
            'nomor_peminjaman' => 'PJM-20260815-0001',
            'jenis_peminjam' => 'pegawai',
            'pegawai_id' => $peminjam->id,
            'cara_input_peminjam' => 'manual',
            'tanggal_peminjaman' => '2026-08-15',
            'rencana_kembali' => '2026-08-20',
            'status' => 'dipinjam',
        ]);
        DetailPeminjamanBarang::create([
            'peminjaman_barang_id' => $peminjaman->id,
            'barang_id' => $barangAset->id,
            'unit_barang_id' => $unitDipinjam->id,
            'lokasi_barang_id' => $lokasi->id,
            'tipe_pengelolaan' => 'aset_individual',
            'jumlah' => 1,
            'jumlah_dikembalikan' => 0,
            'wajib_dikembalikan' => true,
            'cara_input_barang' => 'manual',
        ]);

        $respons = $this->actingAs($pengguna)
            ->get(route('katalog-barang.index'));

        $respons->assertOk()
            ->assertSee('Katalog barang sekolah')
            ->assertSee('Layanan Sarpras')
            ->assertSee($barangAset->nama)
            ->assertSee($unitDipinjam->kodeBarangUnit())
            ->assertDontSee($unitDipinjam->kode_inventaris)
            ->assertSee('1 dari 2')
            ->assertSee('Dina Kurnia, S.Pd.')
            ->assertSee('20 Agt 2026')
            ->assertSee($barangHabisPakai->nama)
            ->assertSee('12,00')
            ->assertDontSee($peminjam->nip)
            ->assertDontSee('Harga perolehan');
    }

    public function test_filter_katalog_membedakan_barang_dipinjam_dan_tidak_tersedia(): void
    {
        [$pengguna, $lokasi, $barangAset, $barangHabisPakai] = $this->buatDataDasar();
        UnitBarang::create([
            'barang_id' => $barangAset->id,
            'nomor_unit' => 1,
            'kode_inventaris' => 'AST-2026-000001',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'dipinjam',
            'aktif' => true,
        ]);
        SaldoStokBarang::create([
            'barang_id' => $barangHabisPakai->id,
            'lokasi_barang_id' => $lokasi->id,
            'jumlah' => 0,
        ]);

        $this->actingAs($pengguna)
            ->get(route('katalog-barang.index', ['ketersediaan' => 'dipinjam']))
            ->assertOk()
            ->assertSee($barangAset->nama)
            ->assertDontSee($barangHabisPakai->nama);

        $this->get(route('katalog-barang.index', ['ketersediaan' => 'tidak_tersedia']))
            ->assertOk()
            ->assertSee($barangAset->nama)
            ->assertSee($barangHabisPakai->nama);
    }

    public function test_siswa_ditolak_dan_administrator_dapat_memeriksa_katalog(): void
    {
        $siswa = Siswa::create([
            'nama_lengkap' => 'Raka Pratama',
            'nisn' => '0099887766',
            'aktif' => true,
        ]);
        $penggunaSiswa = Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'username' => $siswa->nisn,
            'kata_sandi' => 'kata-sandi-uji',
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
        ]);

        $this->actingAs($penggunaSiswa)
            ->get(route('katalog-barang.index'))
            ->assertForbidden();

        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('katalog-barang.index'))
            ->assertOk()
            ->assertSee('Katalog barang sekolah');
    }

    private function buatDataDasar(): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Antonius Pitra Dana Arista, M.T.',
            'nip' => '199211032019021001',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'kata-sandi-uji',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $kategori = KategoriBarang::create([
            'kode' => 'ATK',
            'nama' => 'Perlengkapan Sekolah',
            'aktif' => true,
        ]);
        $satuan = SatuanBarang::create([
            'kode' => 'UNT',
            'nama' => 'Unit',
            'aktif' => true,
        ]);
        $lokasi = LokasiBarang::create([
            'kode' => 'GDG',
            'nama' => 'Gudang Utama',
            'jenis' => 'gudang',
            'aktif' => true,
        ]);
        $barangAset = Barang::create([
            'kode' => '02.06.01.05.40.01',
            'nama' => 'Laptop Pembelajaran',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'tipe_pengelolaan' => 'aset_individual',
            'jenis_barang' => 'tidak_habis_pakai',
            'aktif' => true,
        ]);
        $barangHabisPakai = Barang::create([
            'kode' => 'BHP-000001',
            'nama' => 'Spidol Papan Tulis',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'tipe_pengelolaan' => 'habis_pakai',
            'jenis_barang' => 'habis_pakai',
            'aktif' => true,
        ]);

        return [$pengguna, $lokasi, $barangAset, $barangHabisPakai];
    }
}
