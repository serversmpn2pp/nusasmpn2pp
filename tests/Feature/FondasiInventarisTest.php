<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\PengaturanInventaris;
use App\Models\Pengguna;
use App\Models\SatuanBarang;
use App\Models\SumberPerolehanBarang;
use App\Models\UnitBarang;
use PDO;
use Tests\TestCase;

class FondasiInventarisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite belum aktif pada PHP lokal.');
        }

        $this->artisan('migrate:fresh');
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
    }

    public function test_pengaturan_dan_sumber_perolehan_bawaan_tersedia(): void
    {
        $this->get(route('pengaturan-inventaris.index'))
            ->assertOk()
            ->assertSee('12.03.15.08.10.'.now()->format('Y').'.08')
            ->assertSee('SMPN 2 Padang Panjang');

        $this->get(route('sumber-perolehan-barang.index'))
            ->assertOk()
            ->assertSee('BOS')
            ->assertSee('DAK');
    }

    public function test_pengaturan_identitas_inventaris_dapat_diperbarui(): void
    {
        $this->put(route('pengaturan-inventaris.update'), [
            'awalan_nomor_aset' => '12.03.15.08.10',
            'akhiran_nomor_aset' => '08',
            'nama_pemilik' => 'SMP Negeri 2 Padang Panjang',
            'jumlah_digit_id_internal' => 7,
        ])->assertRedirect(route('pengaturan-inventaris.index'));

        $pengaturan = PengaturanInventaris::utama();
        $this->assertSame('SMP Negeri 2 Padang Panjang', $pengaturan->nama_pemilik);
        $this->assertSame(7, $pengaturan->jumlah_digit_id_internal);
        $this->assertSame('12.03.15.08.10.2024.08', $pengaturan->contohNomorAset(2024));
    }

    public function test_sumber_perolehan_dapat_ditambahkan(): void
    {
        $this->post(route('sumber-perolehan-barang.store'), [
            'kode' => 'hibah komite',
            'nama' => 'Hibah Komite',
            'deskripsi' => 'Bantuan dari komite sekolah.',
            'aktif' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('sumber_perolehan_barang', [
            'kode' => 'HIBAH_KOMITE',
            'nama' => 'Hibah Komite',
            'aktif' => true,
        ]);
    }

    public function test_barang_habis_pakai_mendapat_kode_otomatis_dan_aset_memakai_kode_baku(): void
    {
        [$kategori, $satuan, $lokasi] = $this->buatMasterBarang();

        $this->post(route('barang.store'), [
            'nama' => 'Spidol Whiteboard',
            'jenis_barang' => 'habis_pakai',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'stok_minimum' => 12,
            'aktif' => 1,
        ])->assertRedirect();

        $this->post(route('barang.store'), [
            'nama' => 'Printer',
            'kode' => '0206010540',
            'jenis_barang' => 'tidak_habis_pakai',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'aktif' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('barang', [
            'nama' => 'Spidol Whiteboard',
            'kode' => 'BHP-000001',
            'jenis_barang' => 'habis_pakai',
            'tipe_pengelolaan' => 'habis_pakai',
        ]);
        $this->assertDatabaseHas('barang', [
            'nama' => 'Printer',
            'kode' => '02.06.01.05.40',
            'jenis_barang' => 'tidak_habis_pakai',
            'tipe_pengelolaan' => 'aset_individual',
        ]);
    }

    public function test_nomor_unit_tidak_boleh_dimasukkan_ke_kode_master_barang(): void
    {
        [$kategori, $satuan, $lokasi] = $this->buatMasterBarang();

        $this->post(route('barang.store'), [
            'nama' => 'Printer dengan nomor unit',
            'kode' => '02.06.01.05.40.01',
            'jenis_barang' => 'tidak_habis_pakai',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'aktif' => 1,
        ])->assertSessionHasErrors('kode');

        $this->assertDatabaseMissing('barang', [
            'nama' => 'Printer dengan nomor unit',
        ]);
    }

    public function test_unit_aset_memperoleh_id_internal_unik_dan_nomor_aset_resmi_tetap(): void
    {
        [$kategori, $satuan, $lokasi] = $this->buatMasterBarang();
        $barang = Barang::create([
            'nama' => 'Printer',
            'kode' => '02.06.01.05.40',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'jenis_barang' => 'tidak_habis_pakai',
            'tipe_pengelolaan' => 'aset_individual',
            'aktif' => true,
        ]);
        $sumber = SumberPerolehanBarang::where('kode', 'DAK')->firstOrFail();

        $this->post(route('unit-barang.store'), [
            'barang_id' => $barang->id,
            'jumlah_unit' => 2,
            'lokasi_barang_id' => $lokasi->id,
            'merek' => 'Epson',
            'tipe' => 'L3110',
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'tanggal_perolehan' => '2024-07-15',
            'tahun_perolehan' => 2024,
            'sumber_perolehan_barang_id' => $sumber->id,
            'aktif' => 1,
        ])->assertRedirect();

        $unit = UnitBarang::where('barang_id', $barang->id)->orderBy('nomor_unit')->get();
        $this->assertSame(['AST-2024-000001', 'AST-2024-000002'], $unit->pluck('kode_inventaris')->all());
        $this->assertSame([1, 2], $unit->pluck('urutan_dalam_penerimaan')->all());
        $this->assertSame(['02.06.01.05.40.01', '02.06.01.05.40.02'], $unit->map->kodeBarangUnit()->all());
        $this->assertSame(['12.03.15.08.10.2024.08'], $unit->pluck('nomor_aset_resmi')->unique()->values()->all());
        $this->assertSame(['DAK'], $unit->pluck('sumber_perolehan')->unique()->values()->all());
    }

    public function test_unit_lama_tetap_dapat_diedit_tanpa_menghapus_sumber_lama(): void
    {
        [$kategori, $satuan, $lokasi] = $this->buatMasterBarang();
        $barang = Barang::create([
            'nama' => 'Proyektor Lama',
            'kode' => '02.06.01.05.41',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'jenis_barang' => 'tidak_habis_pakai',
            'tipe_pengelolaan' => 'aset_individual',
            'aktif' => true,
        ]);
        $unit = UnitBarang::create([
            'barang_id' => $barang->id,
            'nomor_unit' => 1,
            'kode_inventaris' => 'AST-LAMA-001',
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'sumber_perolehan' => 'Bantuan Lama',
            'aktif' => true,
        ]);

        $this->put(route('unit-barang.update', $unit), [
            'kondisi' => 'rusak_ringan',
            'status_unit' => 'dalam_perbaikan',
            'keterangan' => 'Sedang diperiksa teknisi.',
            'aktif' => 1,
        ])->assertRedirect(route('unit-barang.show', $unit));

        $unit->refresh();
        $this->assertSame('rusak_ringan', $unit->kondisi);
        $this->assertSame('Bantuan Lama', $unit->sumber_perolehan);
        $this->assertNull($unit->tahun_perolehan);
    }

    public function test_halaman_edit_unit_tetap_memuat_sumber_perolehan_yang_sudah_nonaktif(): void
    {
        [$kategori, $satuan, $lokasi] = $this->buatMasterBarang();
        $barang = Barang::create([
            'nama' => 'Laptop Pembelajaran',
            'kode' => '02.06.01.05.42',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'jenis_barang' => 'tidak_habis_pakai',
            'tipe_pengelolaan' => 'aset_individual',
            'aktif' => true,
        ]);
        $sumber = SumberPerolehanBarang::where('kode', 'DAK')->firstOrFail();
        $sumber->update(['aktif' => false]);
        $unit = UnitBarang::create([
            'barang_id' => $barang->id,
            'nomor_unit' => 1,
            'kode_inventaris' => 'AST-2026-000099',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'sumber_perolehan_barang_id' => $sumber->id,
            'sumber_perolehan' => $sumber->nama,
            'aktif' => true,
        ]);

        $this->get(route('unit-barang.edit', $unit))
            ->assertOk()
            ->assertSee('Edit unit aset')
            ->assertSee($sumber->nama)
            ->assertSee('(nonaktif)');
    }

    private function buatMasterBarang(): array
    {
        $kategori = KategoriBarang::create(['kode' => 'ATK', 'nama' => 'Perlengkapan', 'aktif' => true]);
        $satuan = SatuanBarang::create(['kode' => 'UNIT', 'nama' => 'Unit', 'aktif' => true]);
        $lokasi = LokasiBarang::create(['kode' => 'GUDANG', 'nama' => 'Gudang Utama', 'jenis' => 'gudang', 'aktif' => true]);

        return [$kategori, $satuan, $lokasi];
    }
}
