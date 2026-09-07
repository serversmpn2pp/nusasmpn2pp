<?php

namespace Tests\Feature\Api;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\SatuanBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesMutasiStokBarang;
use App\Services\Inventaris\ProsesPeminjamanBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanInventarisBulananApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_laporan_native_menyamai_rekap_operasional_desktop(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $kategori = KategoriBarang::create(['kode' => 'TIK', 'nama' => 'Peralatan TIK', 'aktif' => true]);
        $satuan = SatuanBarang::create(['kode' => 'BH', 'nama' => 'Buah', 'aktif' => true]);
        $lokasi = LokasiBarang::create(['kode' => 'LAB', 'nama' => 'Labor Komputer', 'jenis' => 'ruangan', 'aktif' => true]);
        $stok = Barang::create([
            'kode' => 'BHP-001',
            'nama' => 'Spidol Papan Tulis',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'tipe_pengelolaan' => 'habis_pakai',
            'jenis_barang' => 'habis_pakai',
            'stok_minimum' => 12,
            'aktif' => true,
        ]);
        Barang::create([
            'kode' => 'BHP-002',
            'nama' => 'Tinta Printer',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'tipe_pengelolaan' => 'habis_pakai',
            'jenis_barang' => 'habis_pakai',
            'aktif' => true,
        ]);
        $aset = Barang::create([
            'kode' => 'AST-001',
            'nama' => 'Laptop Chromebook',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'tipe_pengelolaan' => 'aset_individual',
            'jenis_barang' => 'tidak_habis_pakai',
            'aktif' => true,
        ]);
        $unit = UnitBarang::create([
            'barang_id' => $aset->id,
            'nomor_unit' => 1,
            'kode_inventaris' => 'AST-2026-000001',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'aktif' => true,
        ]);
        UnitBarang::create([
            'barang_id' => $aset->id,
            'nomor_unit' => 2,
            'kode_inventaris' => 'AST-2026-000002',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'rusak_ringan',
            'status_unit' => 'dalam_perbaikan',
            'aktif' => true,
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Dina Kurnia, S.Pd.',
            'nip' => '198505052010012001',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);

        app(ProsesMutasiStokBarang::class)->catat([
            'barang_id' => $stok->id,
            'lokasi_barang_id' => $lokasi->id,
            'jenis_mutasi' => 'masuk',
            'kategori_mutasi' => 'stok_awal',
            'tanggal_mutasi' => now()->startOfMonth()->toDateString(),
            'jumlah' => 12,
        ], $administrator->id);
        app(ProsesMutasiStokBarang::class)->catat([
            'barang_id' => $stok->id,
            'lokasi_barang_id' => $lokasi->id,
            'jenis_mutasi' => 'keluar',
            'kategori_mutasi' => 'pengeluaran_pemakaian',
            'tanggal_mutasi' => now()->startOfMonth()->toDateString(),
            'jumlah' => 2,
        ], $administrator->id);
        app(ProsesPeminjamanBarang::class)->catat([
            'jenis_peminjam' => 'pegawai',
            'pegawai_id' => $pegawai->id,
            'cara_input_peminjam' => 'manual',
            'tanggal_peminjaman' => now()->toDateString(),
            'rencana_kembali' => now()->addDays(5)->toDateString(),
            'items' => [[
                'tipe_item' => 'unit',
                'unit_barang_id' => $unit->id,
                'cara_input_barang' => 'manual',
            ]],
        ], $administrator->id);

        $this->withToken($administrator->createToken('Laporan Inventaris', ['mobile'])->plainTextToken)
            ->getJson(route('api.v1.laporan-inventaris-bulanan', [
                'periode' => now()->format('Y-m'),
                'lokasi_barang_id' => $lokasi->id,
            ]))
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJsonPath('data.periode', now()->format('Y-m'))
            ->assertJsonPath('data.lokasi_label', $lokasi->nama)
            ->assertJsonPath('data.ringkasan.baris_stok', 1)
            ->assertJsonPath('data.ringkasan.jumlah_mutasi', 2)
            ->assertJsonPath('data.ringkasan.stok_menipis', 1)
            ->assertJsonPath('data.ringkasan.stok_belum_dicatat', 1)
            ->assertJsonPath('data.ringkasan.unit_aset', 2)
            ->assertJsonPath('data.ringkasan.unit_perlu_perhatian', 1)
            ->assertJsonPath('data.rekap_stok.0.saldo_awal', 0)
            ->assertJsonPath('data.rekap_stok.0.stok_masuk', 12)
            ->assertJsonPath('data.rekap_stok.0.stok_keluar', 2)
            ->assertJsonPath('data.rekap_stok.0.saldo_akhir', 10)
            ->assertJsonPath('data.rekap_stok.0.status', 'menipis')
            ->assertJsonPath('data.ringkasan_layanan_pegawai.jumlah_layanan', 1)
            ->assertJsonPath('data.ringkasan_layanan_pegawai.pinjaman_aktif', 1)
            ->assertJsonPath('data.layanan_barang_pegawai.0.pegawai.nama', $pegawai->nama_lengkap)
            ->assertJsonPath('data.layanan_barang_pegawai.0.items.0.barang', $aset->nama)
            ->assertJsonPath('data.unit_perlu_perhatian.0.kode_inventaris', 'AST-2026-000002')
            ->assertJsonCount(2, 'data.mutasi_periode')
            ->assertJsonFragment(['kode' => 'LAB', 'nama' => 'Labor Komputer']);
    }

    public function test_filter_laporan_native_divalidasi(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($administrator->createToken('Laporan Inventaris', ['mobile'])->plainTextToken)
            ->getJson(route('api.v1.laporan-inventaris-bulanan', ['periode' => '09-2026']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('periode');
    }
}
