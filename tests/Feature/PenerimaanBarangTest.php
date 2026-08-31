<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\DetailPeminjamanBarang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\MutasiStokBarang;
use App\Models\PeminjamanBarang;
use App\Models\PenerimaanBarang;
use App\Models\Pengguna;
use App\Models\SaldoStokBarang;
use App\Models\SatuanBarang;
use App\Models\SumberPerolehanBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesMutasiStokBarang;
use App\Support\PenulisTemplateExcelPenerimaanBarang;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class PenerimaanBarangTest extends TestCase
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

    public function test_administrator_dapat_membuka_halaman_barang_datang(): void
    {
        $this->buatDataDasar();

        $this->get(route('penerimaan-barang.index'))
            ->assertOk()
            ->assertSee('Barang datang');

        $this->get(route('penerimaan-barang.create'))
            ->assertOk()
            ->assertSee('Catat barang datang')
            ->assertSee('Proses otomatis')
            ->assertSee('name="token_penyimpanan"', false)
            ->assertSee('data-simpan-penerimaan', false)
            ->assertSee('Sedang menyimpan...');

        $this->get(route('penerimaan-barang.import.create'))
            ->assertOk()
            ->assertSee('Import barang datang')
            ->assertSee('Unduh template Excel');

        $this->get(route('penerimaan-barang.import.template'))
            ->assertOk()
            ->assertDownload('template_import_barang_datang_nusa.xlsx');
    }

    public function test_import_excel_mempratinjau_dan_menyimpan_barang_lama_serta_baru(): void
    {
        [$lokasi, $barangHabisPakai, , $sumber] = $this->buatDataDasar();
        $kategori = $barangHabisPakai->kategoriBarang()->firstOrFail();
        $satuan = $barangHabisPakai->satuanBarang()->firstOrFail();
        $nomorDokumen = 'IMPORT-BAST-001';
        $lokasiBerkas = app(PenulisTemplateExcelPenerimaanBarang::class)->buat(
            $this->referensiTemplateTest($barangHabisPakai, $kategori, $satuan, $lokasi, $sumber),
            [
                'tanggal_penerimaan' => now()->toDateString(),
                'sumber_perolehan' => $sumber->kode,
                'cara_perolehan' => 'pembelian',
                'nomor_dokumen' => $nomorDokumen,
                'asal_barang' => 'CV Sumber Ilmu',
                'catatan' => 'Import percobaan.',
            ],
            [
                [
                    'kode_barang' => $barangHabisPakai->kode,
                    'nama_barang' => $barangHabisPakai->nama,
                    'kode_lokasi' => $lokasi->kode,
                    'jumlah' => 5,
                    'harga_satuan' => 12000,
                ],
                [
                    'kode_barang' => '02.06.01.05.40.02',
                    'nama_barang' => 'Laptop Pembelajaran',
                    'jenis_barang' => 'tidak_habis_pakai',
                    'kode_kategori' => $kategori->kode,
                    'kode_satuan' => $satuan->kode,
                    'kode_lokasi' => $lokasi->kode,
                    'jumlah' => 2,
                    'harga_satuan' => 7500000,
                    'merek' => 'Acer',
                    'tipe' => 'TravelMate',
                    'kondisi' => 'baik',
                ],
                [
                    'kode_barang' => 'OTOMATIS',
                    'nama_barang' => 'Kertas HVS A4',
                    'jenis_barang' => 'habis_pakai',
                    'kode_kategori' => $kategori->kode,
                    'kode_satuan' => $satuan->kode,
                    'kode_lokasi' => $lokasi->kode,
                    'jumlah' => 10,
                    'harga_satuan' => 65000,
                ],
            ],
        );

        try {
            $respons = $this->post(route('penerimaan-barang.import.unggah'), [
                'berkas_excel' => new UploadedFile(
                    $lokasiBerkas,
                    'barang-datang.xlsx',
                    PenulisTemplateExcelPenerimaanBarang::MIME,
                    null,
                    true,
                ),
            ]);

            $respons->assertRedirect();
            $urlPratinjau = $respons->headers->get('Location');
            $token = basename((string) parse_url($urlPratinjau, PHP_URL_PATH));

            $this->get($urlPratinjau)
                ->assertOk()
                ->assertSee('Pratinjau import')
                ->assertSee('Laptop Pembelajaran')
                ->assertSee('Kertas HVS A4')
                ->assertSee('Barang baru')
                ->assertSee('Semua data sudah valid')
                ->assertViewHas('pratinjau', fn (array $pratinjau) => $pratinjau['valid']
                    && $pratinjau['jumlah_baris'] === 3
                    && $pratinjau['jumlah_barang_baru'] === 2);

            $this->post(route('penerimaan-barang.import.konfirmasi'), [
                'token_import' => $token,
            ])->assertRedirect();

            $penerimaan = PenerimaanBarang::where('nomor_dokumen', $nomorDokumen)->firstOrFail();
            $laptop = Barang::where('kode', '02.06.01.05.40.02')->firstOrFail();
            $kertas = Barang::where('nama', 'Kertas HVS A4')->firstOrFail();

            $this->assertSame('5.00', SaldoStokBarang::where('barang_id', $barangHabisPakai->id)->value('jumlah'));
            $this->assertSame('10.00', SaldoStokBarang::where('barang_id', $kertas->id)->value('jumlah'));
            $this->assertStringStartsWith('BHP-', $kertas->kode);
            $this->assertSame('tidak_habis_pakai', $laptop->jenis_barang);
            $this->assertCount(2, UnitBarang::where('barang_id', $laptop->id)->get());
            $this->assertCount(3, $penerimaan->detailPenerimaanBarang);
            $this->assertFalse(session()->has('import_penerimaan_barang.'.$token));
        } finally {
            if (is_file($lokasiBerkas)) {
                unlink($lokasiBerkas);
            }
        }
    }

    public function test_import_excel_dengan_baris_tidak_valid_tidak_dapat_dikonfirmasi(): void
    {
        [$lokasi, $barangHabisPakai, , $sumber] = $this->buatDataDasar();
        $kategori = $barangHabisPakai->kategoriBarang()->firstOrFail();
        $satuan = $barangHabisPakai->satuanBarang()->firstOrFail();
        $lokasiBerkas = app(PenulisTemplateExcelPenerimaanBarang::class)->buat(
            $this->referensiTemplateTest($barangHabisPakai, $kategori, $satuan, $lokasi, $sumber),
            [
                'tanggal_penerimaan' => now()->toDateString(),
                'sumber_perolehan' => $sumber->kode,
                'cara_perolehan' => 'pembelian',
            ],
            [[
                'kode_barang' => '02.06.01.05.40.03',
                'nama_barang' => 'Proyektor Baru',
                'jenis_barang' => 'tidak_habis_pakai',
                'kode_kategori' => $kategori->kode,
                'kode_satuan' => $satuan->kode,
                'kode_lokasi' => 'LOKASI-TIDAK-ADA',
                'jumlah' => 1.5,
            ]],
        );

        try {
            $respons = $this->post(route('penerimaan-barang.import.unggah'), [
                'berkas_excel' => new UploadedFile(
                    $lokasiBerkas,
                    'barang-tidak-valid.xlsx',
                    PenulisTemplateExcelPenerimaanBarang::MIME,
                    null,
                    true,
                ),
            ]);
            $respons->assertRedirect();

            $this->get($respons->headers->get('Location'))
                ->assertOk()
                ->assertSee('Lokasi tidak ditemukan')
                ->assertSee('Jumlah aset harus berupa bilangan bulat')
                ->assertSee('Perbaiki Excel terlebih dahulu')
                ->assertDontSee('Simpan import barang datang')
                ->assertViewHas('pratinjau', fn (array $pratinjau) => ! $pratinjau['valid'] && $pratinjau['jumlah_kesalahan'] === 2);

            $this->assertDatabaseCount('penerimaan_barang', 0);
        } finally {
            if (is_file($lokasiBerkas)) {
                unlink($lokasiBerkas);
            }
        }
    }

    public function test_penerimaan_campuran_menambah_stok_dan_membuat_unit_aset(): void
    {
        [$lokasi, $barangHabisPakai, $barangAset, $sumber] = $this->buatDataDasar();
        $tanggal = now()->toDateString();
        $tahun = now()->year;

        $respons = $this->post(route('penerimaan-barang.store'), [
            'token_penyimpanan' => (string) Str::uuid(),
            'tanggal_penerimaan' => $tanggal,
            'sumber_perolehan_barang_id' => $sumber->id,
            'cara_perolehan' => 'pembelian',
            'nomor_dokumen' => 'BAST-001/2026',
            'asal_barang' => 'CV Maju Bersama',
            'catatan' => 'Penerimaan tahap pertama.',
            'rincian' => [
                [
                    'barang_id' => $barangHabisPakai->id,
                    'lokasi_barang_id' => $lokasi->id,
                    'jumlah' => 25,
                    'harga_satuan' => 12000,
                    'keterangan' => 'Spidol warna hitam.',
                ],
                [
                    'barang_id' => $barangAset->id,
                    'lokasi_barang_id' => $lokasi->id,
                    'jumlah' => 2,
                    'harga_satuan' => 3500000,
                    'merek' => 'Epson',
                    'tipe' => 'L3110',
                    'kondisi' => 'baik',
                ],
            ],
        ]);

        $penerimaan = PenerimaanBarang::firstOrFail();
        $respons->assertRedirect(route('penerimaan-barang.show', $penerimaan));

        $this->assertMatchesRegularExpression('/^BRG-MSK-'.$tahun.'-\d{6}$/', $penerimaan->nomor_penerimaan);
        $this->assertCount(2, $penerimaan->detailPenerimaanBarang);
        $this->assertSame('25.00', SaldoStokBarang::where('barang_id', $barangHabisPakai->id)->value('jumlah'));

        $mutasi = MutasiStokBarang::where('barang_id', $barangHabisPakai->id)->firstOrFail();
        $this->assertSame('masuk', $mutasi->jenis_mutasi);
        $this->assertSame('pembelian', $mutasi->kategori_mutasi);
        $this->assertSame($penerimaan->nomor_penerimaan, $mutasi->referensi);

        $unit = UnitBarang::where('barang_id', $barangAset->id)->orderBy('nomor_unit')->get();
        $this->assertCount(2, $unit);
        $this->assertSame([
            'AST-'.$tahun.'-000001',
            'AST-'.$tahun.'-000002',
        ], $unit->pluck('kode_inventaris')->all());
        $this->assertSame([
            '12.03.15.08.10.'.$tahun.'.08',
        ], $unit->pluck('nomor_aset_resmi')->unique()->values()->all());
        $this->assertSame(['Epson'], $unit->pluck('merek')->unique()->values()->all());
        $this->assertTrue($unit->every(fn (UnitBarang $item) => filled($item->detail_penerimaan_barang_id)));

        $this->get(route('unit-barang.show', $unit->first()))
            ->assertOk()
            ->assertSee('Riwayat aset')
            ->assertSee('Aset diterima dan dicatat')
            ->assertSee($penerimaan->nomor_penerimaan)
            ->assertSee('CV Maju Bersama')
            ->assertSee('DAK')
            ->assertSee('Kondisi awal')
            ->assertSee('Baik');

        $this->get(route('penerimaan-barang.show', $penerimaan))
            ->assertOk()
            ->assertSee('BAST-001/2026')
            ->assertSee('CV Maju Bersama')
            ->assertSee('25,00')
            ->assertSee('2 unit dibuat');

        $this->get(route('penerimaan-barang.index'))
            ->assertOk()
            ->assertSee($penerimaan->nomor_penerimaan);

        $this->get(route('dashboard-sarana-prasarana.index'))
            ->assertOk()
            ->assertSee($penerimaan->nomor_penerimaan)
            ->assertSee('Barang datang');

        $this->get(route('label-barcode-inventaris.index', [
            'penerimaan_barang_id' => $penerimaan->id,
        ]))
            ->assertOk()
            ->assertSee('12.03.15.08.10.'.$tahun.'.08')
            ->assertSee('02.06.01.05.40.01')
            ->assertSee('DAK '.$tahun)
            ->assertSee('SMPN 2 Padang Panjang')
            ->assertSee('AST-'.$tahun.'-000001')
            ->assertViewHas('labelBarcode', fn ($label) => $label->count() === 2);

        $this->get(route('label-barcode-inventaris.index', [
            'jenis_label' => 'unit',
            'penerimaan_barang_id' => $penerimaan->id,
            'seleksi' => 1,
            'unit_barang_id' => [$unit->first()->id],
            'ukuran' => 'kecil',
            'salinan' => 2,
        ]))
            ->assertOk()
            ->assertSee('50 x 30 mm')
            ->assertViewHas('labelBarcode', fn ($label) => $label->count() === 2
                && $label->every(fn (array $item) => $item['kode'] === 'AST-'.$tahun.'-000001'));

        $this->get(route('label-barcode-inventaris.index', [
            'jenis_label' => 'stok',
            'penerimaan_barang_id' => $penerimaan->id,
        ]))
            ->assertOk()
            ->assertSee('BARANG HABIS PAKAI')
            ->assertSee('BHP-000001')
            ->assertSee('Gudang Utama')
            ->assertViewHas('labelBarcode', fn ($label) => $label->count() === 1);
    }

    public function test_pengiriman_form_yang_sama_dua_kali_tidak_menggandakan_penerimaan_dan_stok(): void
    {
        [$lokasi, $barangHabisPakai, $barangAset, $sumber] = $this->buatDataDasar();
        $payload = [
            'token_penyimpanan' => (string) Str::uuid(),
            'tanggal_penerimaan' => now()->toDateString(),
            'sumber_perolehan_barang_id' => $sumber->id,
            'cara_perolehan' => 'pembelian',
            'nomor_dokumen' => 'UJI-KIRIM-GANDA-001',
            'rincian' => [
                [
                    'barang_id' => $barangHabisPakai->id,
                    'lokasi_barang_id' => $lokasi->id,
                    'jumlah' => 2,
                    'harga_satuan' => 12000,
                ],
                [
                    'barang_id' => $barangAset->id,
                    'lokasi_barang_id' => $lokasi->id,
                    'jumlah' => 2,
                    'harga_satuan' => 3500000,
                    'kondisi' => 'baik',
                ],
            ],
        ];

        $responsPertama = $this->post(route('penerimaan-barang.store'), $payload);
        $penerimaan = PenerimaanBarang::firstOrFail();
        $responsPertama->assertRedirect(route('penerimaan-barang.show', $penerimaan));

        $this->post(route('penerimaan-barang.store'), $payload)
            ->assertRedirect(route('penerimaan-barang.show', $penerimaan));

        $this->assertDatabaseCount('penerimaan_barang', 1);
        $this->assertDatabaseCount('detail_penerimaan_barang', 2);
        $this->assertDatabaseCount('mutasi_stok_barang', 1);
        $this->assertSame('2.00', SaldoStokBarang::where('barang_id', $barangHabisPakai->id)->value('jumlah'));
        $this->assertCount(2, UnitBarang::where('barang_id', $barangAset->id)->get());
    }

    public function test_penerimaan_campuran_dapat_dibatalkan_dengan_jejak_audit(): void
    {
        [$lokasi, $barangHabisPakai, $barangAset, $sumber] = $this->buatDataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->post(route('penerimaan-barang.store'), [
            'token_penyimpanan' => (string) Str::uuid(),
            'tanggal_penerimaan' => now()->toDateString(),
            'sumber_perolehan_barang_id' => $sumber->id,
            'cara_perolehan' => 'pembelian',
            'nomor_dokumen' => 'DUPLIKAT-001',
            'rincian' => [
                [
                    'barang_id' => $barangHabisPakai->id,
                    'lokasi_barang_id' => $lokasi->id,
                    'jumlah' => 8,
                    'harga_satuan' => 12000,
                ],
                [
                    'barang_id' => $barangAset->id,
                    'lokasi_barang_id' => $lokasi->id,
                    'jumlah' => 2,
                    'harga_satuan' => 3500000,
                    'kondisi' => 'baik',
                ],
            ],
        ])->assertRedirect();

        $penerimaan = PenerimaanBarang::firstOrFail();
        $alasan = 'Penerimaan tercatat dua kali akibat pengiriman formulir berulang.';

        $this->patch(route('penerimaan-barang.batalkan', $penerimaan), [
            'alasan_pembatalan' => $alasan,
            'konfirmasi_pembatalan' => 1,
        ])->assertRedirect(route('penerimaan-barang.show', $penerimaan));

        $penerimaan->refresh();
        $detailStok = $penerimaan->detailPenerimaanBarang()->where('barang_id', $barangHabisPakai->id)->firstOrFail();
        $unit = UnitBarang::where('barang_id', $barangAset->id)->get();

        $this->assertSame(PenerimaanBarang::STATUS_DIBATALKAN, $penerimaan->status);
        $this->assertSame($alasan, $penerimaan->alasan_pembatalan);
        $this->assertSame($administrator->id, $penerimaan->dibatalkan_oleh_pengguna_id);
        $this->assertNotNull($penerimaan->dibatalkan_pada);
        $this->assertNotNull($detailStok->mutasi_pembatalan_stok_barang_id);
        $this->assertDatabaseCount('mutasi_stok_barang', 2);
        $this->assertDatabaseHas('mutasi_stok_barang', [
            'id' => $detailStok->mutasi_pembatalan_stok_barang_id,
            'jenis_mutasi' => 'keluar',
            'kategori_mutasi' => 'lainnya',
            'jumlah_perubahan' => '-8.00',
            'referensi' => 'BATAL-'.$penerimaan->nomor_penerimaan,
        ]);
        $this->assertSame('0.00', SaldoStokBarang::where('barang_id', $barangHabisPakai->id)->value('jumlah'));
        $this->assertCount(2, $unit);
        $this->assertTrue($unit->every(fn (UnitBarang $item) => ! $item->aktif && $item->status_unit === 'dihapuskan'));

        $this->get(route('penerimaan-barang.show', $penerimaan))
            ->assertOk()
            ->assertSee('Penerimaan dibatalkan')
            ->assertSee($alasan)
            ->assertSee('Lihat koreksi')
            ->assertSee('2 unit dinonaktifkan')
            ->assertDontSee('Batalkan penerimaan');

        $this->get(route('penerimaan-barang.index'))
            ->assertOk()
            ->assertSee($penerimaan->nomor_penerimaan)
            ->assertSee('Dibatalkan');
    }

    public function test_pembatalan_ditolak_jika_stok_tidak_mencukupi(): void
    {
        [$lokasi, $barangHabisPakai, , $sumber] = $this->buatDataDasar();

        $this->post(route('penerimaan-barang.store'), [
            'token_penyimpanan' => (string) Str::uuid(),
            'tanggal_penerimaan' => now()->toDateString(),
            'sumber_perolehan_barang_id' => $sumber->id,
            'cara_perolehan' => 'pembelian',
            'rincian' => [[
                'barang_id' => $barangHabisPakai->id,
                'lokasi_barang_id' => $lokasi->id,
                'jumlah' => 5,
            ]],
        ])->assertRedirect();

        app(ProsesMutasiStokBarang::class)->catat([
            'barang_id' => $barangHabisPakai->id,
            'lokasi_barang_id' => $lokasi->id,
            'jenis_mutasi' => 'keluar',
            'kategori_mutasi' => 'pengeluaran_pemakaian',
            'tanggal_mutasi' => now()->toDateString(),
            'jumlah' => 2,
        ]);

        $penerimaan = PenerimaanBarang::firstOrFail();

        $this->from(route('penerimaan-barang.show', $penerimaan))
            ->patch(route('penerimaan-barang.batalkan', $penerimaan), [
                'alasan_pembatalan' => 'Penerimaan ini merupakan data duplikat.',
                'konfirmasi_pembatalan' => 1,
            ])
            ->assertRedirect(route('penerimaan-barang.show', $penerimaan))
            ->assertSessionHasErrors('pembatalan');

        $penerimaan->refresh();
        $this->assertSame(PenerimaanBarang::STATUS_AKTIF, $penerimaan->status);
        $this->assertNull($penerimaan->dibatalkan_pada);
        $this->assertSame('3.00', SaldoStokBarang::where('barang_id', $barangHabisPakai->id)->value('jumlah'));
        $this->assertDatabaseCount('mutasi_stok_barang', 2);
    }

    public function test_pembatalan_ditolak_jika_aset_sudah_memiliki_riwayat_peminjaman(): void
    {
        [$lokasi, , $barangAset, $sumber] = $this->buatDataDasar();

        $this->post(route('penerimaan-barang.store'), [
            'token_penyimpanan' => (string) Str::uuid(),
            'tanggal_penerimaan' => now()->toDateString(),
            'sumber_perolehan_barang_id' => $sumber->id,
            'cara_perolehan' => 'pembelian',
            'rincian' => [[
                'barang_id' => $barangAset->id,
                'lokasi_barang_id' => $lokasi->id,
                'jumlah' => 1,
                'kondisi' => 'baik',
            ]],
        ])->assertRedirect();

        $penerimaan = PenerimaanBarang::firstOrFail();
        $unit = UnitBarang::where('barang_id', $barangAset->id)->firstOrFail();
        $peminjaman = PeminjamanBarang::create([
            'nomor_peminjaman' => 'PJM-UJI-001',
            'jenis_peminjam' => 'pegawai',
            'cara_input_peminjam' => 'manual',
            'tanggal_peminjaman' => now()->toDateString(),
            'status' => 'selesai',
        ]);
        DetailPeminjamanBarang::create([
            'peminjaman_barang_id' => $peminjaman->id,
            'barang_id' => $barangAset->id,
            'unit_barang_id' => $unit->id,
            'lokasi_barang_id' => $lokasi->id,
            'tipe_pengelolaan' => 'aset_individual',
            'jumlah' => 1,
            'jumlah_dikembalikan' => 1,
            'wajib_dikembalikan' => true,
            'cara_input_barang' => 'manual',
        ]);

        $this->from(route('penerimaan-barang.show', $penerimaan))
            ->patch(route('penerimaan-barang.batalkan', $penerimaan), [
                'alasan_pembatalan' => 'Penerimaan ini merupakan data duplikat.',
                'konfirmasi_pembatalan' => 1,
            ])
            ->assertRedirect(route('penerimaan-barang.show', $penerimaan))
            ->assertSessionHasErrors('pembatalan');

        $this->assertSame(PenerimaanBarang::STATUS_AKTIF, $penerimaan->fresh()->status);
        $this->assertTrue($unit->fresh()->aktif);
        $this->assertSame('tersedia', $unit->fresh()->status_unit);
    }

    public function test_jumlah_aset_desimal_ditolak_dan_transaksi_dibatalkan_seluruhnya(): void
    {
        [$lokasi, , $barangAset, $sumber] = $this->buatDataDasar();

        $this->from(route('penerimaan-barang.create'))
            ->post(route('penerimaan-barang.store'), [
                'token_penyimpanan' => (string) Str::uuid(),
                'tanggal_penerimaan' => now()->toDateString(),
                'sumber_perolehan_barang_id' => $sumber->id,
                'cara_perolehan' => 'pembelian',
                'rincian' => [[
                    'barang_id' => $barangAset->id,
                    'lokasi_barang_id' => $lokasi->id,
                    'jumlah' => 1.5,
                    'kondisi' => 'baik',
                ]],
            ])
            ->assertRedirect(route('penerimaan-barang.create'))
            ->assertSessionHasErrors('rincian.0.jumlah');

        $this->assertDatabaseCount('penerimaan_barang', 0);
        $this->assertDatabaseCount('detail_penerimaan_barang', 0);
        $this->assertDatabaseCount('unit_barang', 0);
    }

    private function buatDataDasar(): array
    {
        $kategori = KategoriBarang::create(['kode' => 'ATK', 'nama' => 'Perlengkapan', 'aktif' => true]);
        $satuan = SatuanBarang::create(['kode' => 'UNIT', 'nama' => 'Unit', 'aktif' => true]);
        $lokasi = LokasiBarang::create(['kode' => 'GUDANG', 'nama' => 'Gudang Utama', 'jenis' => 'gudang', 'aktif' => true]);
        $barangHabisPakai = Barang::create([
            'kode' => 'BHP-000001',
            'nama' => 'Spidol Whiteboard',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'jenis_barang' => 'habis_pakai',
            'tipe_pengelolaan' => 'habis_pakai',
            'aktif' => true,
        ]);
        $barangAset = Barang::create([
            'kode' => '02.06.01.05.40.01',
            'nama' => 'Printer',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'jenis_barang' => 'tidak_habis_pakai',
            'tipe_pengelolaan' => 'aset_individual',
            'aktif' => true,
        ]);
        $sumber = SumberPerolehanBarang::where('kode', 'DAK')->firstOrFail();

        return [$lokasi, $barangHabisPakai, $barangAset, $sumber];
    }

    private function referensiTemplateTest(
        Barang $barang,
        KategoriBarang $kategori,
        SatuanBarang $satuan,
        LokasiBarang $lokasi,
        SumberPerolehanBarang $sumber,
    ): array {
        return [
            'barang' => [[
                'kode' => $barang->kode,
                'nama' => $barang->nama,
                'jenis' => $barang->jenis_barang,
                'kategori' => $kategori->kode,
                'satuan' => $satuan->kode,
                'lokasi' => $lokasi->kode,
            ]],
            'kategori' => [['kode' => $kategori->kode, 'nama' => $kategori->nama]],
            'satuan' => [['kode' => $satuan->kode, 'nama' => $satuan->nama]],
            'lokasi' => [['kode' => $lokasi->kode, 'nama' => $lokasi->nama]],
            'sumber' => [['kode' => $sumber->kode, 'nama' => $sumber->nama]],
        ];
    }
}
