<?php

namespace Tests\Feature\Api;

use App\Models\Barang;
use App\Models\Izin;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\MutasiStokBarang;
use App\Models\PenerimaanBarang;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SaldoStokBarang;
use App\Models\SatuanBarang;
use App\Models\SumberPerolehanBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesPenerimaanBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PenerimaanBarangApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_dan_detail_mengikuti_filter_ringkasan_pilihan_serta_menu_native(): void
    {
        $this->getJson(route('api.v1.barang-datang.index'))->assertUnauthorized();

        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$lokasi, $stok, $aset, $sumber] = $this->master();
        $penerimaan = $this->catat($administrator, $lokasi, $stok, $aset, $sumber);

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.barang-datang.index', [
                'cari' => 'EPSON',
                'sumber_perolehan_barang_id' => $sumber->id,
                'tanggal_mulai' => now()->toDateString(),
                'tanggal_selesai' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.ringkasan.hari_ini', 1)
            ->assertJsonPath('data.ringkasan.unit_aset_dibuat', 2)
            ->assertJsonPath('data.ringkasan.jenis_stok_masuk', 1)
            ->assertJsonPath('data.filter.sumber_perolehan_barang_id', $sumber->id)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonPath('data.items.0.nomor', $penerimaan->nomor_penerimaan)
            ->assertJsonPath('data.items.0.nilai_total', 7300000)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonCount(2, 'data.pilihan.barang')
            ->assertJsonCount(3, 'data.pilihan.cara_perolehan');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.barang-datang.show', $penerimaan))
            ->assertOk()
            ->assertJsonPath('data.penerimaan.nomor_dokumen', 'BAST-API-001')
            ->assertJsonPath('data.penerimaan.rincian.0.barang.nama', 'Spidol Hitam')
            ->assertJsonPath('data.penerimaan.rincian.0.mutasi_stok_id', MutasiStokBarang::firstOrFail()->id)
            ->assertJsonPath('data.penerimaan.rincian.1.barang.nama', 'Printer Epson')
            ->assertJsonCount(2, 'data.penerimaan.rincian.1.unit_aset')
            ->assertJsonPath('data.hak_akses.dapat_dibatalkan', true);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'barang-datang',
                'status' => 'tersedia',
                'rute' => '/barang-datang',
            ]);
    }

    public function test_pencatatan_idempoten_membentuk_stok_dan_unit_lalu_pembatalan_mengoreksi_keduanya(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$lokasi, $stok, $aset, $sumber] = $this->master();
        $tokenApi = $this->token($administrator);
        $payload = $this->payload($lokasi, $stok, $aset, $sumber);

        $response = $this->withToken($tokenApi)
            ->postJson(route('api.v1.barang-datang.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'aktif')
            ->assertJsonPath('data.asal_barang', 'CV Maju Bersama')
            ->assertJsonCount(2, 'data.rincian');

        $penerimaan = PenerimaanBarang::firstOrFail();
        $this->assertStringStartsWith('BRG-MSK-'.now()->year.'-', $penerimaan->nomor_penerimaan);
        $this->assertDatabaseCount('penerimaan_barang', 1);
        $this->assertSame('25.00', SaldoStokBarang::where('barang_id', $stok->id)->value('jumlah'));
        $this->assertSame(2, UnitBarang::where('barang_id', $aset->id)->where('aktif', true)->count());

        $this->withToken($tokenApi)
            ->postJson(route('api.v1.barang-datang.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', $penerimaan->id);
        $this->assertDatabaseCount('penerimaan_barang', 1);
        $this->assertDatabaseCount('unit_barang', 2);

        $this->withToken($tokenApi)
            ->patchJson(route('api.v1.barang-datang.batalkan', $penerimaan), [
                'alasan_pembatalan' => 'Penerimaan ini tercatat ganda pada dokumen sumber.',
                'konfirmasi_pembatalan' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'dibatalkan')
            ->assertJsonPath('data.alasan_pembatalan', 'Penerimaan ini tercatat ganda pada dokumen sumber.')
            ->assertJsonPath('data.rincian.0.mutasi_pembatalan_id', fn ($id) => is_int($id));

        $this->assertSame('0.00', SaldoStokBarang::where('barang_id', $stok->id)->value('jumlah'));
        $this->assertSame(0, UnitBarang::where('barang_id', $aset->id)->where('aktif', true)->count());
        $this->assertSame(PenerimaanBarang::STATUS_DIBATALKAN, $penerimaan->fresh()->status);
        $this->assertSame(2, MutasiStokBarang::where('barang_id', $stok->id)->count());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_izin_lihat_hanya_dapat_membaca_bukan_mencatat_atau_membatalkan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$lokasi, $stok, $aset, $sumber] = $this->master();
        $penerimaan = $this->catat($administrator, $lokasi, $stok, $aset, $sumber);
        $pembaca = $this->penggunaDenganIzin('barang.lihat');
        $token = $this->token($pembaca);

        $this->withToken($token)
            ->getJson(route('api.v1.barang-datang.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($token)
            ->getJson(route('api.v1.barang-datang.show', $penerimaan))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_dibatalkan', false);
        $this->withToken($token)
            ->postJson(route('api.v1.barang-datang.store'), $this->payload($lokasi, $stok, $aset, $sumber))
            ->assertForbidden();
        $this->withToken($token)
            ->patchJson(route('api.v1.barang-datang.batalkan', $penerimaan), [
                'alasan_pembatalan' => 'Penerimaan salah.',
                'konfirmasi_pembatalan' => true,
            ])
            ->assertForbidden();
    }

    private function catat(
        Pengguna $pengguna,
        LokasiBarang $lokasi,
        Barang $stok,
        Barang $aset,
        SumberPerolehanBarang $sumber,
    ): PenerimaanBarang {
        $data = $this->payload($lokasi, $stok, $aset, $sumber);
        $data['nomor_dokumen'] = trim($data['nomor_dokumen']);
        $data['asal_barang'] = trim($data['asal_barang']);
        $data['catatan'] = trim($data['catatan']);

        return app(ProsesPenerimaanBarang::class)->catat(
            $data,
            $pengguna->id,
        );
    }

    private function payload(
        LokasiBarang $lokasi,
        Barang $stok,
        Barang $aset,
        SumberPerolehanBarang $sumber,
    ): array {
        return [
            'token_penyimpanan' => (string) Str::uuid(),
            'tanggal_penerimaan' => now()->toDateString(),
            'sumber_perolehan_barang_id' => $sumber->id,
            'cara_perolehan' => 'pembelian',
            'nomor_dokumen' => ' BAST-API-001 ',
            'asal_barang' => ' CV Maju Bersama ',
            'catatan' => ' Diterima dalam keadaan baik. ',
            'rincian' => [
                [
                    'barang_id' => $stok->id,
                    'lokasi_barang_id' => $lokasi->id,
                    'jumlah' => 25,
                    'harga_satuan' => 12000,
                    'keterangan' => 'Stok awal.',
                ],
                [
                    'barang_id' => $aset->id,
                    'lokasi_barang_id' => $lokasi->id,
                    'jumlah' => 2,
                    'harga_satuan' => 3500000,
                    'merek' => 'Epson',
                    'tipe' => 'L3110',
                    'kondisi' => 'baik',
                ],
            ],
        ];
    }

    private function master(): array
    {
        $kategori = KategoriBarang::create(['kode' => 'PERALATAN', 'nama' => 'Peralatan', 'aktif' => true]);
        $satuan = SatuanBarang::create(['kode' => 'UNIT', 'nama' => 'Unit', 'aktif' => true]);
        $lokasi = LokasiBarang::create(['kode' => 'GUDANG', 'nama' => 'Gudang Utama', 'jenis' => 'gudang', 'aktif' => true]);
        $stok = Barang::create([
            'kode' => 'BHP-000001',
            'nama' => 'Spidol Hitam',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'tipe_pengelolaan' => 'habis_pakai',
            'jenis_barang' => 'habis_pakai',
            'aktif' => true,
        ]);
        $aset = Barang::create([
            'kode' => '02.06.01.05.40',
            'nama' => 'Printer Epson',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'tipe_pengelolaan' => 'aset_individual',
            'jenis_barang' => 'tidak_habis_pakai',
            'aktif' => true,
        ]);
        $sumber = SumberPerolehanBarang::where('kode', 'DAK')->firstOrFail();

        return [$lokasi, $stok, $aset, $sumber];
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Barang Datang Mobile',
            'kode' => 'pembaca_barang_datang_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Barang Datang Mobile',
            'username' => 'pembaca.barang.datang.mobile',
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
        return $pengguna->createToken('Perangkat Barang Datang', ['mobile'])->plainTextToken;
    }
}
