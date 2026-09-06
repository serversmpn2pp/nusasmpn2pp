<?php

namespace Tests\Feature\Api;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\Pengguna;
use App\Models\SaldoStokBarang;
use App\Models\SatuanBarang;
use App\Models\SumberPerolehanBarang;
use App\Models\UnitBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class LabelInventarisApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_label_memerlukan_token_dan_tersedia_di_menu_native(): void
    {
        $this->getJson(route('api.v1.label-inventaris'))->assertUnauthorized();

        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'label-inventaris',
                'status' => 'tersedia',
                'rute' => '/label-inventaris',
            ]);
    }

    public function test_label_unit_mobile_sama_dengan_label_desktop(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$barang, $lokasi, $sumber] = $this->masterAset();
        $unit = UnitBarang::create([
            'barang_id' => $barang->id,
            'nomor_unit' => 1,
            'urutan_dalam_penerimaan' => 1,
            'kode_inventaris' => 'AST-2026-000001',
            'nomor_aset_resmi' => '12.03.15.08.10.2026.08',
            'lokasi_barang_id' => $lokasi->id,
            'merek' => 'Epson',
            'tipe' => 'L3110',
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'tahun_perolehan' => 2026,
            'sumber_perolehan_barang_id' => $sumber->id,
            'sumber_perolehan' => $sumber->nama,
            'aktif' => true,
        ]);

        $desktop = $this->actingAs($administrator)
            ->get(route('label-barcode-inventaris.index', [
                'jenis_label' => 'unit',
                'tahun_perolehan' => 2026,
                'lokasi_barang_id' => $lokasi->id,
            ]))
            ->assertOk()
            ->viewData('labelBarcode')
            ->first();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.label-inventaris', [
                'jenis_label' => 'unit',
                'tahun_perolehan' => 2026,
                'lokasi_barang_id' => $lokasi->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.aturan_cetak.format_kertas', 'A4')
            ->assertJsonPath('data.aturan_cetak.margin_mm', 8)
            ->assertJsonPath('data.aturan_cetak.jarak_label_mm', 3)
            ->assertJsonPath('data.aturan_cetak.maksimal_pilihan', 500)
            ->assertJsonPath('data.aturan_cetak.maksimal_salinan', 20)
            ->assertJsonPath('data.pilihan.ukuran.0.label', '50 x 30 mm')
            ->assertJsonPath('data.pilihan.ukuran.1.label', '65 x 35 mm')
            ->assertJsonPath('data.pilihan.ukuran.2.label', '80 x 45 mm')
            ->assertJsonPath('data.ringkasan.jumlah_pilihan', 1)
            ->assertJsonPath('data.items.0.id', $unit->id)
            ->assertJsonPath('data.items.0.kode', 'AST-2026-000001')
            ->assertJsonPath('data.items.0.sumber_tahun', 'Dana BOS 2026');

        $mobile = $response->json('data.items.0');
        $this->assertSame(
            Arr::except($desktop, ['barcode_svg']),
            Arr::only($mobile, array_keys(Arr::except($desktop, ['barcode_svg']))),
        );
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_label_stok_mobile_sama_dengan_label_desktop_dan_mendukung_filter(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$barangAset, $lokasi] = $this->masterAset();
        $barangStok = Barang::create([
            'kode' => 'BHP-000001',
            'nama' => 'Tinta Printer',
            'kategori_barang_id' => $barangAset->kategori_barang_id,
            'satuan_barang_id' => $barangAset->satuan_barang_id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'jenis_barang' => 'habis_pakai',
            'tipe_pengelolaan' => 'habis_pakai',
            'aktif' => true,
        ]);
        $saldo = SaldoStokBarang::create([
            'barang_id' => $barangStok->id,
            'lokasi_barang_id' => $lokasi->id,
            'jumlah' => 8,
        ]);

        $desktop = $this->actingAs($administrator)
            ->get(route('label-barcode-inventaris.index', [
                'jenis_label' => 'stok',
                'barang_id' => $barangStok->id,
            ]))
            ->assertOk()
            ->viewData('labelBarcode')
            ->first();

        $mobile = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.label-inventaris', [
                'jenis_label' => 'stok',
                'barang_id' => $barangStok->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $saldo->id)
            ->assertJsonPath('data.items.0.judul', 'BARANG HABIS PAKAI')
            ->assertJsonPath('data.items.0.kode', 'BHP-000001')
            ->json('data.items.0');

        $this->assertSame(
            Arr::except($desktop, ['barcode_svg']),
            Arr::only($mobile, array_keys(Arr::except($desktop, ['barcode_svg']))),
        );
    }

    public function test_filter_dan_batas_tahun_divalidasi(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.label-inventaris', [
                'jenis_label' => 'lainnya',
                'tahun_perolehan' => 1800,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jenis_label', 'tahun_perolehan']);
    }

    private function masterAset(): array
    {
        $kategori = KategoriBarang::create(['kode' => 'ELEKTRONIK', 'nama' => 'Elektronik', 'aktif' => true]);
        $satuan = SatuanBarang::create(['kode' => 'UNIT', 'nama' => 'Unit', 'aktif' => true]);
        $lokasi = LokasiBarang::create(['kode' => 'LAB', 'nama' => 'Labor Komputer', 'jenis' => 'ruangan', 'aktif' => true]);
        $sumber = SumberPerolehanBarang::query()->where('aktif', true)->firstOrFail();
        $sumber->update(['nama' => 'Dana BOS', 'kode' => 'BOS']);
        $barang = Barang::create([
            'kode' => '02.06.01.05.40',
            'nama' => 'Printer Epson',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'jenis_barang' => 'tidak_habis_pakai',
            'tipe_pengelolaan' => 'aset_individual',
            'aktif' => true,
        ]);

        return [$barang, $lokasi, $sumber];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Label Inventaris Mobile', ['mobile'])->plainTextToken;
    }
}
