<?php

namespace Tests\Feature\Api;

use App\Models\Barang;
use App\Models\Izin;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SatuanBarang;
use App\Models\SumberPerolehanBarang;
use App\Models\UnitBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitBarangApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_dan_mengirim_ringkasan_filter_pilihan_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$barang, $lokasi] = $this->master();
        $unit = $this->unit($barang, $lokasi, 'AST-2026-000001');
        $unit->update(['kondisi' => 'rusak_ringan', 'status_unit' => 'dalam_perbaikan']);

        $this->getJson(route('api.v1.unit-aset.index'))->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.unit-aset.index', [
                'cari' => 'Epson',
                'status' => 'aktif',
                'kondisi' => 'rusak_ringan',
                'status_unit' => 'dalam_perbaikan',
                'barang_id' => $barang->id,
                'lokasi_barang_id' => $lokasi->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.tersedia', 0)
            ->assertJsonPath('data.ringkasan.perlu_perhatian', 1)
            ->assertJsonPath('data.filter.kondisi', 'rusak_ringan')
            ->assertJsonPath('data.filter.barang_id', $barang->id)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.kode_barang_unit', '02.06.01.05.40.01')
            ->assertJsonPath('data.items.0.kode_inventaris', 'AST-2026-000001')
            ->assertJsonPath('data.items.0.barang.nama', 'Printer Epson')
            ->assertJsonCount(3, 'data.pilihan.kondisi')
            ->assertJsonCount(5, 'data.pilihan.status_unit');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'unit-aset',
                'status' => 'tersedia',
                'rute' => '/unit-aset',
            ]);
    }

    public function test_administrator_dapat_menambah_beberapa_unit_dengan_identitas_otomatis(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$barang, $lokasi] = $this->master();
        $sumber = SumberPerolehanBarang::where('kode', 'DAK')->firstOrFail();

        $response = $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.unit-aset.store'), [
                'barang_id' => $barang->id,
                'jumlah_unit' => 2,
                'lokasi_barang_id' => $lokasi->id,
                'nomor_seri' => null,
                'merek' => ' Epson ',
                'tipe' => ' L3110 ',
                'kondisi' => 'baik',
                'status_unit' => 'tersedia',
                'tanggal_perolehan' => '2026-07-15',
                'tahun_perolehan' => 2026,
                'sumber_perolehan_barang_id' => $sumber->id,
                'harga_perolehan' => 4500000,
                'keterangan' => ' Dana pengadaan sekolah. ',
                'aktif' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.kode_inventaris', 'AST-2026-000001')
            ->assertJsonPath('data.nomor_aset_resmi', '12.03.15.08.10.2026.08')
            ->assertJsonPath('data.merek', 'Epson')
            ->assertJsonPath('data.sumber_perolehan.kode', 'DAK');

        $this->assertSame(2, UnitBarang::where('barang_id', $barang->id)->count());
        $this->assertSame(
            ['AST-2026-000001', 'AST-2026-000002'],
            UnitBarang::where('barang_id', $barang->id)->orderBy('id')->pluck('kode_inventaris')->all(),
        );
    }

    public function test_detail_dan_perubahan_unit_menyimpan_identitas_serta_riwayat(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$barang, $lokasi] = $this->master();
        $sumber = SumberPerolehanBarang::where('kode', 'BOS')->firstOrFail();
        $unit = $this->unit($barang, $lokasi, 'AST-2025-000008');
        $token = $this->token($administrator);

        $this->withToken($token)
            ->getJson(route('api.v1.unit-aset.show', $unit))
            ->assertOk()
            ->assertJsonPath('data.unit.barang.kategori', 'Peralatan')
            ->assertJsonPath('data.unit.peminjaman_aktif', null)
            ->assertJsonPath('data.unit.riwayat.0.jenis', 'pencatatan')
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);

        $this->withToken($token)
            ->patchJson(route('api.v1.unit-aset.update', $unit), [
                'lokasi_barang_id' => $lokasi->id,
                'nomor_seri' => 'SN-001',
                'merek' => 'Epson',
                'tipe' => 'L3250',
                'kondisi' => 'rusak_ringan',
                'status_unit' => 'dalam_perbaikan',
                'tanggal_perolehan' => '2025-04-12',
                'tahun_perolehan' => 2025,
                'sumber_perolehan_barang_id' => $sumber->id,
                'harga_perolehan' => 5100000,
                'keterangan' => 'Sedang diperiksa teknisi.',
                'aktif' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.nomor_seri', 'SN-001')
            ->assertJsonPath('data.label_kondisi', 'Rusak ringan')
            ->assertJsonPath('data.label_status_unit', 'Dalam perbaikan')
            ->assertJsonPath('data.nomor_aset_resmi', '12.03.15.08.10.2025.08');

        $this->withToken($token)
            ->deleteJson(route('api.v1.unit-aset.destroy', $unit))
            ->assertOk();
        $this->assertDatabaseHas('unit_barang', ['id' => $unit->id, 'aktif' => false]);
    }

    public function test_validasi_jumlah_nomor_seri_tahun_dan_tipe_barang_diterapkan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$barang, $lokasi, $habisPakai] = $this->master();
        $sumber = SumberPerolehanBarang::where('kode', 'DAK')->firstOrFail();
        $token = $this->token($administrator);
        $dasar = [
            'jumlah_unit' => 2,
            'lokasi_barang_id' => $lokasi->id,
            'nomor_seri' => 'SERIAL-GANDA',
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'tanggal_perolehan' => '2025-07-15',
            'tahun_perolehan' => 2026,
            'sumber_perolehan_barang_id' => $sumber->id,
            'aktif' => true,
        ];

        $this->withToken($token)
            ->postJson(route('api.v1.unit-aset.store'), ['barang_id' => $barang->id] + $dasar)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tahun_perolehan']);

        $dasar['tanggal_perolehan'] = '2026-07-15';
        $this->withToken($token)
            ->postJson(route('api.v1.unit-aset.store'), ['barang_id' => $barang->id] + $dasar)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nomor_seri']);

        $dasar['jumlah_unit'] = 1;
        $dasar['nomor_seri'] = null;
        $this->withToken($token)
            ->postJson(route('api.v1.unit-aset.store'), ['barang_id' => $habisPakai->id] + $dasar)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['barang_id']);
    }

    public function test_izin_lihat_hanya_dapat_membaca_bukan_mengubah_unit(): void
    {
        $pengguna = $this->penggunaDenganIzin('barang.lihat');
        [$barang, $lokasi] = $this->master();
        $unit = $this->unit($barang, $lokasi, 'AST-2026-000001');
        $token = $this->token($pengguna);

        $this->withToken($token)
            ->getJson(route('api.v1.unit-aset.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($token)
            ->getJson(route('api.v1.unit-aset.show', $unit))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($token)
            ->deleteJson(route('api.v1.unit-aset.destroy', $unit))
            ->assertForbidden();
    }

    private function master(): array
    {
        $kategori = KategoriBarang::create(['kode' => 'PERALATAN', 'nama' => 'Peralatan', 'aktif' => true]);
        $satuan = SatuanBarang::create(['kode' => 'UNIT', 'nama' => 'Unit', 'aktif' => true]);
        $lokasi = LokasiBarang::create(['kode' => 'GUDANG', 'nama' => 'Gudang Utama', 'jenis' => 'gudang', 'aktif' => true]);
        $barang = Barang::create([
            'kode' => '02.06.01.05.40',
            'nama' => 'Printer Epson',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'tipe_pengelolaan' => 'aset_individual',
            'jenis_barang' => 'tidak_habis_pakai',
            'aktif' => true,
        ]);
        $habisPakai = Barang::create([
            'kode' => 'BHP-000001',
            'nama' => 'Tinta Printer',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'tipe_pengelolaan' => 'habis_pakai',
            'jenis_barang' => 'habis_pakai',
            'aktif' => true,
        ]);

        return [$barang, $lokasi, $habisPakai];
    }

    private function unit(Barang $barang, LokasiBarang $lokasi, string $kode): UnitBarang
    {
        return UnitBarang::create([
            'barang_id' => $barang->id,
            'nomor_unit' => 1,
            'urutan_dalam_penerimaan' => 1,
            'kode_inventaris' => $kode,
            'nomor_aset_resmi' => '12.03.15.08.10.2026.08',
            'lokasi_barang_id' => $lokasi->id,
            'merek' => 'Epson',
            'tipe' => 'L3110',
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'tahun_perolehan' => 2026,
            'aktif' => true,
        ]);
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Unit Aset Mobile',
            'kode' => 'pembaca_unit_aset_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Unit Aset Mobile',
            'username' => 'pembaca.unit.aset.mobile',
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
        return $pengguna->createToken('Perangkat Unit Aset', ['mobile'])->plainTextToken;
    }
}
