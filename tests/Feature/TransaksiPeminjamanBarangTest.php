<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SaldoStokBarang;
use App\Models\SatuanBarang;
use App\Models\Siswa;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesMutasiStokBarang;
use App\Services\Inventaris\ProsesPeminjamanBarang;
use App\Services\Inventaris\ProsesPengembalianBarang;
use PDO;
use Tests\TestCase;

class TransaksiPeminjamanBarangTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite belum aktif pada PHP lokal.');
        }

        $this->artisan('migrate:fresh');
    }

    public function test_peminjaman_campuran_dan_pengembalian_memperbarui_unit_serta_stok(): void
    {
        [$siswa, $lokasi, $barangUnit, $barangDikembalikan, $barangHabisPakai] = $this->buatDataDasar();
        $unit = UnitBarang::create([
            'barang_id' => $barangUnit->id,
            'nomor_unit' => 1,
            'kode_inventaris' => 'AST-LAPTOP-001',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'aktif' => true,
        ]);
        SaldoStokBarang::create(['barang_id' => $barangDikembalikan->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 10]);
        SaldoStokBarang::create(['barang_id' => $barangHabisPakai->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 20]);

        $peminjaman = app(ProsesPeminjamanBarang::class)->catat([
            'jenis_peminjam' => 'siswa',
            'siswa_id' => $siswa->id,
            'cara_input_peminjam' => 'scan',
            'tanggal_peminjaman' => '2026-05-31',
            'rencana_kembali' => '2026-06-02',
            'items' => [
                ['tipe_item' => 'unit', 'unit_barang_id' => $unit->id, 'cara_input_barang' => 'scan'],
                ['tipe_item' => 'stok', 'barang_id' => $barangDikembalikan->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 2, 'cara_input_barang' => 'manual'],
                ['tipe_item' => 'stok', 'barang_id' => $barangHabisPakai->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 3, 'cara_input_barang' => 'manual'],
            ],
        ]);

        $this->assertSame('dipinjam', $peminjaman->status);
        $this->assertSame('dipinjam', $unit->fresh()->status_unit);
        $this->assertSame('8.00', SaldoStokBarang::where('barang_id', $barangDikembalikan->id)->value('jumlah'));
        $this->assertSame('17.00', SaldoStokBarang::where('barang_id', $barangHabisPakai->id)->value('jumlah'));
        $this->assertCount(3, $peminjaman->detailPeminjamanBarang);

        $detailUnit = $peminjaman->detailPeminjamanBarang->firstWhere('unit_barang_id', $unit->id);
        $detailStok = $peminjaman->detailPeminjamanBarang->firstWhere('barang_id', $barangDikembalikan->id);

        app(ProsesPengembalianBarang::class)->catat($peminjaman, [
            'tanggal_pengembalian' => '2026-06-01',
            'items' => [
                ['detail_peminjaman_barang_id' => $detailUnit->id, 'jumlah' => 1, 'kondisi_pengembalian' => 'baik', 'cara_input_barang' => 'scan'],
                ['detail_peminjaman_barang_id' => $detailStok->id, 'jumlah' => 2, 'cara_input_barang' => 'manual'],
            ],
        ]);

        $this->assertSame('selesai', $peminjaman->fresh()->status);
        $this->assertSame('tersedia', $unit->fresh()->status_unit);
        $this->assertSame('10.00', SaldoStokBarang::where('barang_id', $barangDikembalikan->id)->value('jumlah'));
        $this->assertSame('17.00', SaldoStokBarang::where('barang_id', $barangHabisPakai->id)->value('jumlah'));
    }

    public function test_peminjaman_mengenali_kartu_dan_barcode_inventaris_baru(): void
    {
        [$siswa, $lokasi, $barangUnit, , $barangHabisPakai] = $this->buatDataDasar();
        $barangHabisPakai->update(['kode' => 'BHP-000001']);
        $unit = UnitBarang::create([
            'barang_id' => $barangUnit->id,
            'nomor_unit' => 1,
            'kode_inventaris' => 'AST-2026-000001',
            'nomor_aset_resmi' => '12.03.15.08.10.2026.08',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'aktif' => true,
        ]);
        SaldoStokBarang::create([
            'barang_id' => $barangHabisPakai->id,
            'lokasi_barang_id' => $lokasi->id,
            'jumlah' => 10,
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Dina Kurnia, S.Pd.',
            'nip' => '198505052010012001',
            'aktif' => true,
        ]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('peminjaman-barang.create'))
            ->assertOk()
            ->assertSee('AST')
            ->assertSee('BHP')
            ->assertSee('Aset individual (wajib kembali)')
            ->assertSee('Barang habis pakai');

        $this->getJson(route('peminjaman-barang.identifikasi-peminjam', [
            'jenis_peminjam' => 'otomatis',
            'kode' => $siswa->nisn,
        ]))
            ->assertOk()
            ->assertJsonPath('jenis_peminjam', 'siswa')
            ->assertJsonPath('id', $siswa->id);

        $this->getJson(route('peminjaman-barang.identifikasi-peminjam', [
            'jenis_peminjam' => 'otomatis',
            'kode' => $pegawai->nip,
        ]))
            ->assertOk()
            ->assertJsonPath('jenis_peminjam', 'pegawai')
            ->assertJsonPath('id', $pegawai->id);

        $this->getJson(route('peminjaman-barang.identifikasi-barang', [
            'kode' => 'ast-2026-000001',
        ]))
            ->assertOk()
            ->assertJsonPath('item.kode', 'AST-2026-000001')
            ->assertJsonPath('item.jenis_tampilan', 'Aset individual')
            ->assertJsonPath('item.wajib_dikembalikan', true);

        $this->getJson(route('peminjaman-barang.identifikasi-barang', [
            'kode' => 'bhp-000001',
        ]))
            ->assertOk()
            ->assertJsonPath('item.kode', 'BHP-000001')
            ->assertJsonPath('item.jenis_tampilan', 'Barang habis pakai')
            ->assertJsonPath('item.saldo', 10)
            ->assertJsonPath('item.wajib_dikembalikan', false);

        $this->post(route('peminjaman-barang.store'), [
            'jenis_peminjam' => 'siswa',
            'siswa_id' => $siswa->id,
            'cara_input_peminjam' => 'scan',
            'tanggal_peminjaman' => '2026-08-15',
            'rencana_kembali' => '2026-08-16',
            'items' => [
                [
                    'tipe_item' => 'unit',
                    'unit_barang_id' => $unit->id,
                    'cara_input_barang' => 'scan',
                ],
                [
                    'tipe_item' => 'stok',
                    'barang_id' => $barangHabisPakai->id,
                    'lokasi_barang_id' => $lokasi->id,
                    'jumlah' => 4,
                    'cara_input_barang' => 'scan',
                ],
            ],
        ])->assertRedirect();

        $this->assertSame('dipinjam', $unit->fresh()->status_unit);
        $this->assertSame('6.00', SaldoStokBarang::where('barang_id', $barangHabisPakai->id)->value('jumlah'));

        $this->getJson(route('peminjaman-barang.identifikasi-barang', [
            'kode' => 'AST-2026-000001',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('pesan', 'Unit aset tersebut sedang tidak tersedia untuk dipinjam.');
    }

    public function test_pengembalian_barcode_ast_menemukan_transaksi_dan_memperbarui_kondisi(): void
    {
        [$siswa, $lokasi, $barangUnit] = $this->buatDataDasar();
        $unit = UnitBarang::create([
            'barang_id' => $barangUnit->id,
            'nomor_unit' => 1,
            'kode_inventaris' => 'AST-2026-000002',
            'nomor_aset_resmi' => '12.03.15.08.10.2026.08',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'aktif' => true,
        ]);
        $peminjaman = app(ProsesPeminjamanBarang::class)->catat([
            'jenis_peminjam' => 'siswa',
            'siswa_id' => $siswa->id,
            'cara_input_peminjam' => 'scan',
            'tanggal_peminjaman' => '2026-08-14',
            'rencana_kembali' => '2026-08-16',
            'items' => [
                [
                    'tipe_item' => 'unit',
                    'unit_barang_id' => $unit->id,
                    'cara_input_barang' => 'scan',
                ],
            ],
        ]);
        $detail = $peminjaman->detailPeminjamanBarang()->firstOrFail();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('pengembalian-barang.index'))
            ->assertOk()
            ->assertSee('Pengembalian barang')
            ->assertSee('Scan atau masukkan kode AST');

        $this->get(route('unit-barang.show', $unit))
            ->assertOk()
            ->assertSee('Keadaan aset saat ini')
            ->assertSee('Dipinjam oleh '.$siswa->nama_lengkap)
            ->assertSee($peminjaman->nomor_peminjaman)
            ->assertSee('Proses pengembalian')
            ->assertSee(route('pengembalian-barang.index', ['kode' => $unit->kode_inventaris]), false);

        $this->get(route('pengembalian-barang.index', ['kode' => $unit->kode_inventaris]))
            ->assertOk()
            ->assertSee('value="AST-2026-000002"', false);

        $hasilIdentifikasi = $this->getJson(route('pengembalian-barang.identifikasi', [
            'kode' => 'ast-2026-000002',
        ]))
            ->assertOk()
            ->assertJsonPath('item.kode', 'AST-2026-000002')
            ->assertJsonPath('item.nama_barang', $barangUnit->nama)
            ->assertJsonPath('item.nama_peminjam', $siswa->nama_lengkap)
            ->assertJsonPath('item.nomor_peminjaman', $peminjaman->nomor_peminjaman);

        $urlKonfirmasi = $hasilIdentifikasi->json('item.url_konfirmasi');

        $this->get($urlKonfirmasi)
            ->assertOk()
            ->assertSee('Aset hasil scan telah dipilih')
            ->assertSee('return-row-selected', false)
            ->assertSee('value="scan"', false);

        $this->post(route('pengembalian-barang.store', $peminjaman), [
            'tanggal_pengembalian' => '2026-08-15',
            'items' => [
                $detail->id => [
                    'detail_peminjaman_barang_id' => $detail->id,
                    'jumlah' => 1,
                    'kondisi_pengembalian' => 'rusak_ringan',
                    'cara_input_barang' => 'scan',
                ],
            ],
        ])->assertRedirect(route('peminjaman-barang.show', $peminjaman));

        $this->assertSame('selesai', $peminjaman->fresh()->status);
        $this->assertSame('rusak_ringan', $unit->fresh()->kondisi);
        $this->assertSame('dalam_perbaikan', $unit->fresh()->status_unit);

        $this->get(route('unit-barang.show', $unit))
            ->assertOk()
            ->assertSee('Aset dikembalikan oleh '.$siswa->nama_lengkap)
            ->assertSee('Kondisi kembali')
            ->assertSee('Rusak ringan')
            ->assertDontSee('Proses pengembalian');

        $this->getJson(route('pengembalian-barang.identifikasi', [
            'kode' => 'AST-2026-000002',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('pesan', 'Tidak ditemukan transaksi peminjaman aktif untuk aset ini. Periksa riwayat unit secara manual.');

        $this->getJson(route('pengembalian-barang.identifikasi', [
            'kode' => 'BHP-000001',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('pesan', 'Barang habis pakai tidak memiliki proses pengembalian.');
    }

    public function test_pengembalian_stok_sebagian_menjaga_transaksi_tetap_aktif(): void
    {
        [$siswa, $lokasi, , $barangDikembalikan] = $this->buatDataDasar();
        SaldoStokBarang::create(['barang_id' => $barangDikembalikan->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 10]);

        $peminjaman = app(ProsesPeminjamanBarang::class)->catat([
            'jenis_peminjam' => 'siswa',
            'siswa_id' => $siswa->id,
            'cara_input_peminjam' => 'manual',
            'tanggal_peminjaman' => '2026-05-31',
            'items' => [
                ['tipe_item' => 'stok', 'barang_id' => $barangDikembalikan->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 5, 'cara_input_barang' => 'manual'],
            ],
        ]);
        $detail = $peminjaman->detailPeminjamanBarang->first();

        app(ProsesPengembalianBarang::class)->catat($peminjaman, [
            'tanggal_pengembalian' => '2026-06-01',
            'items' => [
                ['detail_peminjaman_barang_id' => $detail->id, 'jumlah' => 2, 'cara_input_barang' => 'manual'],
            ],
        ]);

        $this->assertSame('sebagian_dikembalikan', $peminjaman->fresh()->status);
        $this->assertSame(3.0, $detail->fresh()->jumlahBelumDikembalikan());
        $this->assertSame('7.00', SaldoStokBarang::where('barang_id', $barangDikembalikan->id)->value('jumlah'));
    }

    public function test_administrator_dapat_membuka_halaman_transaksi_dan_label_stok(): void
    {
        [$siswa, $lokasi, , $barangDikembalikan] = $this->buatDataDasar();
        SaldoStokBarang::create(['barang_id' => $barangDikembalikan->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 10]);
        $peminjaman = app(ProsesPeminjamanBarang::class)->catat([
            'jenis_peminjam' => 'siswa',
            'siswa_id' => $siswa->id,
            'cara_input_peminjam' => 'manual',
            'tanggal_peminjaman' => '2026-05-31',
            'items' => [
                ['tipe_item' => 'stok', 'barang_id' => $barangDikembalikan->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 2, 'cara_input_barang' => 'manual'],
            ],
        ]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('peminjaman-barang.index'))
            ->assertOk()
            ->assertSee($peminjaman->nomor_peminjaman);

        $this->get(route('peminjaman-barang.show', $peminjaman))
            ->assertOk()
            ->assertSee($barangDikembalikan->nama);

        $this->get(route('pengembalian-barang.create', $peminjaman))
            ->assertOk()
            ->assertSee('Catat pengembalian barang');

        $this->get(route('label-barcode-inventaris.index', ['jenis_label' => 'stok']))
            ->assertOk()
            ->assertSee($barangDikembalikan->kode);
    }

    public function test_rekap_menampilkan_transaksi_terlambat_dan_dapat_dicetak(): void
    {
        [$siswa, $lokasi, , $barangDikembalikan] = $this->buatDataDasar();
        SaldoStokBarang::create(['barang_id' => $barangDikembalikan->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 10]);
        $peminjaman = app(ProsesPeminjamanBarang::class)->catat([
            'jenis_peminjam' => 'siswa',
            'siswa_id' => $siswa->id,
            'cara_input_peminjam' => 'manual',
            'tanggal_peminjaman' => now()->subDays(10)->toDateString(),
            'rencana_kembali' => now()->subDays(3)->toDateString(),
            'items' => [
                ['tipe_item' => 'stok', 'barang_id' => $barangDikembalikan->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 2, 'cara_input_barang' => 'manual'],
            ],
        ]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->assertTrue($peminjaman->terlambat());
        $this->assertSame(3, $peminjaman->jumlahHariTerlambat());
        $this->assertSame('Terlambat 3 hari', $peminjaman->labelPemantauan());

        $this->actingAs($administrator)
            ->get(route('rekap-peminjaman-barang.index', ['status_pemantauan' => 'terlambat']))
            ->assertOk()
            ->assertSee($peminjaman->nomor_peminjaman)
            ->assertSee('Terlambat 3 hari');

        $this->get(route('rekap-peminjaman-barang.cetak', ['status_pemantauan' => 'terlambat']))
            ->assertOk()
            ->assertSee('REKAP PEMINJAMAN BARANG')
            ->assertSee($peminjaman->nomor_peminjaman);
    }

    public function test_administrator_dapat_membuka_dashboard_sarpras(): void
    {
        [, $lokasi, $barangUnit, $barangDikembalikan] = $this->buatDataDasar();
        UnitBarang::create([
            'barang_id' => $barangUnit->id,
            'nomor_unit' => 1,
            'kode_inventaris' => 'AST-LAPTOP-001',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'rusak_ringan',
            'status_unit' => 'dalam_perbaikan',
            'aktif' => true,
        ]);
        SaldoStokBarang::create(['barang_id' => $barangDikembalikan->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 0]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('dashboard-sarana-prasarana.index'))
            ->assertOk()
            ->assertSee('Dashboard inventaris sekolah')
            ->assertSee('Stok perlu perhatian')
            ->assertSee('Unit Aset Perlu Perhatian')
            ->assertSee('AST-LAPTOP-001');
    }

    public function test_laporan_inventaris_bulanan_menghitung_rekap_stok_dan_dapat_dicetak(): void
    {
        [, $lokasi, , $barangDikembalikan] = $this->buatDataDasar();
        $tanggal = now()->startOfMonth()->toDateString();
        $periode = now()->format('Y-m');

        app(ProsesMutasiStokBarang::class)->catat([
            'barang_id' => $barangDikembalikan->id,
            'lokasi_barang_id' => $lokasi->id,
            'jenis_mutasi' => 'masuk',
            'kategori_mutasi' => 'stok_awal',
            'tanggal_mutasi' => $tanggal,
            'jumlah' => 12,
        ]);
        app(ProsesMutasiStokBarang::class)->catat([
            'barang_id' => $barangDikembalikan->id,
            'lokasi_barang_id' => $lokasi->id,
            'jenis_mutasi' => 'keluar',
            'kategori_mutasi' => 'pengeluaran_pemakaian',
            'tanggal_mutasi' => $tanggal,
            'jumlah' => 2,
        ]);
        $this->buatPegawaiPenandatangan('pimpinan', 'Dra. Rahmawati', '197001011995012001');
        $this->buatPegawaiPenandatangan('wakil_pimpinan_sarana_prasarana', 'Budi Santoso, S.Pd.', '198002022006041001');
        $this->buatPegawaiPenandatangan('petugas_inventaris', 'Rina Kurnia, S.Pd.', '198503032010012002');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('laporan-inventaris-bulanan.index', ['periode' => $periode]))
            ->assertOk()
            ->assertSee('Laporan inventaris bulanan')
            ->assertSee($barangDikembalikan->nama)
            ->assertSee('12,00')
            ->assertSee('2,00')
            ->assertSee('10,00');

        $this->get(route('laporan-inventaris-bulanan.cetak', ['periode' => $periode]))
            ->assertOk()
            ->assertSee('LAPORAN INVENTARIS BULANAN')
            ->assertSee('RINCIAN MUTASI STOK')
            ->assertSee('Dra. Rahmawati')
            ->assertSee('Budi Santoso, S.Pd.')
            ->assertSee('Rina Kurnia, S.Pd.')
            ->assertSee($barangDikembalikan->nama);
    }

    private function buatPegawaiPenandatangan(string $kodePeran, string $nama, string $nip): Pegawai
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'kata-sandi-uji',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $pengguna->daftarPeran()->syncWithoutDetaching([
            Peran::where('kode', $kodePeran)->value('id'),
        ]);

        return $pegawai;
    }

    private function buatDataDasar(): array
    {
        $kategori = KategoriBarang::create(['kode' => 'ELK', 'nama' => 'Perlengkapan', 'aktif' => true]);
        $satuan = SatuanBarang::create(['kode' => 'UNT', 'nama' => 'Unit', 'aktif' => true]);
        $lokasi = LokasiBarang::create(['kode' => 'GDG', 'nama' => 'Gudang Utama', 'jenis' => 'gudang', 'aktif' => true]);
        $siswa = Siswa::create(['nama_lengkap' => 'Raka Pratama', 'nisn' => '0099887766', 'aktif' => true]);
        $barangUnit = Barang::create([
            'kode' => 'LAPTOP',
            'nama' => 'Laptop',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'tipe_pengelolaan' => 'aset_individual',
            'aktif' => true,
        ]);
        $barangDikembalikan = Barang::create([
            'kode' => 'BOLA',
            'nama' => 'Bola Basket',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'tipe_pengelolaan' => 'stok_dikembalikan',
            'aktif' => true,
        ]);
        $barangHabisPakai = Barang::create([
            'kode' => 'SPIDOL',
            'nama' => 'Spidol',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'tipe_pengelolaan' => 'habis_pakai',
            'aktif' => true,
        ]);

        return [$siswa, $lokasi, $barangUnit, $barangDikembalikan, $barangHabisPakai];
    }
}
