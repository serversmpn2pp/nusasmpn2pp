<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\RekapPeminjamanBarangController as RekapWebController;
use App\Models\Barang;
use App\Models\Izin;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\Pegawai;
use App\Models\PengajuanBarang;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SaldoStokBarang;
use App\Models\SatuanBarang;
use App\Models\Siswa;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesPeminjamanBarang;
use App\Services\Inventaris\ProsesPengajuanBarang;
use App\Services\Mobile\PeminjamanBarangMobileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeminjamanBarangApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_petugas_memeriksa_memenuhi_dan_menolak_pengajuan_barang_dari_mobile(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [, $pegawai, $lokasi, $unit, , $habisPakai] = $this->master();
        $token = $this->token($administrator);
        $proses = app(ProsesPengajuanBarang::class);

        $pengajuanAset = $proses->ajukan($pegawai->id, [
            'barang_id' => $unit->barang_id,
            'jumlah' => 1,
            'tanggal_dibutuhkan' => now()->addDay()->toDateString(),
            'rencana_kembali' => now()->addWeek()->toDateString(),
            'tujuan' => 'Pembelajaran di ruang kelas.',
        ]);
        $pengajuanStok = $proses->ajukan($pegawai->id, [
            'barang_id' => $habisPakai->id,
            'jumlah' => 2,
            'tanggal_dibutuhkan' => now()->addDay()->toDateString(),
            'tujuan' => 'Persediaan alat tulis kelas.',
        ]);
        $pengajuanDitolak = $proses->ajukan($pegawai->id, [
            'barang_id' => $unit->barang_id,
            'jumlah' => 1,
            'tanggal_dibutuhkan' => now()->addDays(2)->toDateString(),
            'rencana_kembali' => now()->addDays(10)->toDateString(),
            'tujuan' => 'Kegiatan rapat di ruang guru.',
        ]);

        $this->withToken($token)
            ->getJson(route('api.v1.pengajuan-barang.index', [
                'kata_kunci' => $pegawai->nama_lengkap,
                'jenis' => 'semua',
                'status' => 'menunggu',
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.semua', 3)
            ->assertJsonPath('data.ringkasan.menunggu', 3)
            ->assertJsonPath('data.ringkasan.peminjaman', 2)
            ->assertJsonPath('data.ringkasan.permintaan', 1)
            ->assertJsonPath('data.filter.kata_kunci', $pegawai->nama_lengkap)
            ->assertJsonCount(3, 'data.items')
            ->assertJsonFragment(['nomor' => $pengajuanAset->nomor_pengajuan, 'status_label' => 'Menunggu petugas'])
            ->assertJsonFragment(['nilai' => 'dipenuhi', 'label' => 'Dipenuhi']);

        $this->withToken($token)
            ->getJson(route('api.v1.pengajuan-barang.show', $pengajuanAset))
            ->assertOk()
            ->assertJsonPath('data.pengajuan.tujuan', 'Pembelajaran di ruang kelas.')
            ->assertJsonPath('data.ketersediaan.unit_dibutuhkan', 1)
            ->assertJsonPath('data.ketersediaan.unit.0.id', $unit->id)
            ->assertJsonPath('data.hak_akses.dapat_memenuhi', true);

        $dipenuhi = $this->withToken($token)
            ->patchJson(route('api.v1.pengajuan-barang.penuhi', $pengajuanAset), [
                'unit_barang_ids' => [$unit->id],
                'catatan_petugas' => ' Diserahkan dalam kondisi baik. ',
            ])
            ->assertOk()
            ->assertJsonPath('data.pengajuan.status', 'dipenuhi')
            ->assertJsonPath('data.pengajuan.catatan_petugas', 'Diserahkan dalam kondisi baik.')
            ->assertJsonPath('data.hak_akses.dapat_memenuhi', false);
        $this->assertNotNull($dipenuhi->json('data.pengajuan.peminjaman_barang_id'));
        $this->assertSame('dipinjam', $unit->fresh()->status_unit);

        $this->withToken($token)
            ->getJson(route('api.v1.pengajuan-barang.show', $pengajuanStok))
            ->assertOk()
            ->assertJsonPath('data.ketersediaan.saldo.0.lokasi_barang_id', $lokasi->id)
            ->assertJsonPath('data.ketersediaan.saldo.0.jumlah', 20);
        $this->withToken($token)
            ->patchJson(route('api.v1.pengajuan-barang.penuhi', $pengajuanStok), [
                'lokasi_barang_id' => $lokasi->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.pengajuan.status', 'dipenuhi');
        $this->assertSame('18.00', SaldoStokBarang::where('barang_id', $habisPakai->id)->where('lokasi_barang_id', $lokasi->id)->value('jumlah'));

        $this->withToken($token)
            ->patchJson(route('api.v1.pengajuan-barang.tolak', $pengajuanDitolak), [
                'catatan_petugas' => 'Stok dialokasikan untuk kegiatan lain.',
            ])
            ->assertOk()
            ->assertJsonPath('data.pengajuan.status', 'ditolak')
            ->assertJsonPath('data.pengajuan.catatan_petugas', 'Stok dialokasikan untuk kegiatan lain.');
        $this->assertSame(2, PengajuanBarang::query()->where('status', 'dipenuhi')->count());

        $this->withToken($token)->getJson(route('api.v1.menu'))
            ->assertOk()->assertJsonFragment([
                'kode' => 'pengajuan-barang',
                'status' => 'tersedia',
                'rute' => '/pengajuan-barang',
            ]);
    }

    public function test_rekap_native_sama_dengan_pemantauan_desktop_dan_menyediakan_dokumen(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$siswa, , $lokasi, , $stokKembali] = $this->master();
        $peminjaman = app(ProsesPeminjamanBarang::class)->catat([
            'jenis_peminjam' => 'siswa',
            'siswa_id' => $siswa->id,
            'cara_input_peminjam' => 'manual',
            'tanggal_peminjaman' => now()->subDays(10)->toDateString(),
            'rencana_kembali' => now()->subDays(3)->toDateString(),
            'items' => [[
                'tipe_item' => 'stok',
                'barang_id' => $stokKembali->id,
                'lokasi_barang_id' => $lokasi->id,
                'jumlah' => 2,
                'cara_input_barang' => 'manual',
            ]],
        ], $administrator->id);
        $token = $this->token($administrator);
        $filter = [
            'status_pemantauan' => 'terlambat',
            'jenis_peminjam' => 'siswa',
            'peminjam' => 'siswa:'.$siswa->id,
            'barang_id' => $stokKembali->id,
            'tanggal_mulai' => now()->subDays(12)->toDateString(),
            'tanggal_selesai' => now()->subDays(8)->toDateString(),
        ];

        $this->assertSame(RekapWebController::DAFTAR_STATUS_PEMANTAUAN, PeminjamanBarangMobileService::STATUS_PEMANTAUAN);

        $this->withToken($token)
            ->getJson(route('api.v1.rekap-peminjaman-barang.index', $filter))
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.terlambat', 1)
            ->assertJsonPath('data.filter.status_pemantauan', 'terlambat')
            ->assertJsonPath('data.items.0.id', $peminjaman->id)
            ->assertJsonPath('data.items.0.items.0.nama_barang', $stokKembali->nama)
            ->assertJsonPath('data.items.0.items.0.jumlah_belum_dikembalikan', 2)
            ->assertJsonPath('data.daftar_terlambat.jumlah', 1)
            ->assertJsonFragment(['nilai' => 'jatuh_tempo', 'label' => 'Jatuh tempo 7 hari'])
            ->assertJsonFragment(['nilai' => 'siswa:'.$siswa->id])
            ->assertJsonFragment(['id' => $stokKembali->id, 'nama' => $stokKembali->nama]);

        $dokumen = $this->withToken($token)
            ->getJson(route('api.v1.rekap-peminjaman-barang.document', $filter))
            ->assertOk()
            ->assertJsonPath('data.paginasi.total', 1)
            ->assertJsonPath('data.items.0.nomor', $peminjaman->nomor_peminjaman)
            ->assertJsonPath('data.daftar_terlambat.jumlah', 1);
        $this->assertStringContainsString('DAFTAR BARANG TERLAMBAT DIKEMBALIKAN', $dokumen->json('data.daftar_terlambat.teks'));
        $this->assertStringContainsString($siswa->nama_lengkap, $dokumen->json('data.daftar_terlambat.teks'));
        $this->assertNotEmpty($dokumen->json('data.dicetak_pada'));
    }

    public function test_peminjaman_campuran_native_memperbarui_unit_stok_dan_detail(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$siswa, $pegawai, $lokasi, $unit, $stokKembali, $habisPakai] = $this->master();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.peminjaman-barang.store'), [
                'jenis_peminjam' => 'siswa',
                'siswa_id' => $siswa->id,
                'cara_input_peminjam' => 'manual',
                'tanggal_peminjaman' => now()->toDateString(),
                'rencana_kembali' => now()->addDays(2)->toDateString(),
                'catatan' => ' Digunakan untuk kegiatan kelas. ',
                'items' => [
                    ['tipe_item' => 'unit', 'unit_barang_id' => $unit->id, 'cara_input_barang' => 'scan'],
                    [
                        'tipe_item' => 'stok',
                        'barang_id' => $stokKembali->id,
                        'lokasi_barang_id' => $lokasi->id,
                        'jumlah' => 2,
                        'cara_input_barang' => 'manual',
                    ],
                    [
                        'tipe_item' => 'stok',
                        'barang_id' => $habisPakai->id,
                        'lokasi_barang_id' => $lokasi->id,
                        'jumlah' => 3,
                        'cara_input_barang' => 'manual',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('pesan', 'Transaksi peminjaman barang berhasil dicatat.')
            ->assertJsonPath('data.peminjaman.nama_peminjam', $siswa->nama_lengkap)
            ->assertJsonPath('data.peminjaman.catatan', 'Digunakan untuk kegiatan kelas.')
            ->assertJsonPath('data.peminjaman.status', 'dipinjam')
            ->assertJsonPath('data.hak_akses.dapat_mengembalikan', true)
            ->assertJsonCount(3, 'data.peminjaman.items');

        $id = $response->json('data.peminjaman.id');
        $this->assertStringStartsWith('PJM-', $response->json('data.peminjaman.nomor'));
        $this->assertSame('dipinjam', $unit->fresh()->status_unit);
        $this->assertSame('8.00', SaldoStokBarang::where('barang_id', $stokKembali->id)->value('jumlah'));
        $this->assertSame('17.00', SaldoStokBarang::where('barang_id', $habisPakai->id)->value('jumlah'));

        $this->withToken($token)
            ->getJson(route('api.v1.peminjaman-barang.show', $id))
            ->assertOk()
            ->assertJsonFragment(['kode' => 'AST-2026-000010', 'wajib_dikembalikan' => true])
            ->assertJsonFragment(['kode' => 'BHP-000020', 'wajib_dikembalikan' => false])
            ->assertJsonFragment(['nama_barang' => 'Spidol', 'jumlah_belum_dikembalikan' => 3]);

        $this->withToken($token)
            ->getJson(route('api.v1.peminjaman-barang.index', [
                'cari' => $siswa->nama_lengkap,
                'jenis_peminjam' => 'siswa',
                'status' => 'dipinjam',
                'tanggal_mulai' => now()->toDateString(),
                'tanggal_selesai' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.hari_ini', 1)
            ->assertJsonPath('data.items.0.id', $id)
            ->assertJsonCount(1, 'data.pilihan.siswa')
            ->assertJsonCount(1, 'data.pilihan.pegawai')
            ->assertJsonCount(2, 'data.pilihan.barang');

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment(['kode' => 'peminjaman-barang', 'status' => 'tersedia', 'rute' => '/peminjaman-barang'])
            ->assertJsonFragment(['kode' => 'pengembalian-barang', 'status' => 'tersedia', 'rute' => '/pengembalian-barang'])
            ->assertJsonFragment(['kode' => 'rekap-peminjaman', 'status' => 'tersedia', 'rute' => '/rekap-peminjaman-barang']);
    }

    public function test_identifikasi_kartu_barcode_dan_pengembalian_sebagian_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$siswa, $pegawai, $lokasi, $unit, $stokKembali] = $this->master();
        $token = $this->token($administrator);

        $this->withToken($token)
            ->getJson(route('api.v1.peminjaman-barang.identifikasi-peminjam', [
                'jenis_peminjam' => 'otomatis',
                'kode' => $siswa->nisn,
            ]))
            ->assertOk()
            ->assertJsonPath('data.jenis_peminjam', 'siswa')
            ->assertJsonPath('data.id', $siswa->id);

        $this->withToken($token)
            ->getJson(route('api.v1.peminjaman-barang.identifikasi-peminjam', [
                'jenis_peminjam' => 'otomatis',
                'kode' => $pegawai->nip,
            ]))
            ->assertOk()
            ->assertJsonPath('data.jenis_peminjam', 'pegawai')
            ->assertJsonPath('data.id', $pegawai->id);

        $this->withToken($token)
            ->getJson(route('api.v1.peminjaman-barang.identifikasi-barang', ['kode' => 'ast-2026-000010']))
            ->assertOk()
            ->assertJsonPath('data.item.tipe_item', 'unit')
            ->assertJsonPath('data.item.wajib_dikembalikan', true);

        $loan = app(ProsesPeminjamanBarang::class)->catat([
            'jenis_peminjam' => 'siswa',
            'siswa_id' => $siswa->id,
            'cara_input_peminjam' => 'scan',
            'tanggal_peminjaman' => now()->subDay()->toDateString(),
            'rencana_kembali' => now()->addDay()->toDateString(),
            'items' => [
                ['tipe_item' => 'unit', 'unit_barang_id' => $unit->id, 'cara_input_barang' => 'scan'],
                [
                    'tipe_item' => 'stok',
                    'barang_id' => $stokKembali->id,
                    'lokasi_barang_id' => $lokasi->id,
                    'jumlah' => 2,
                    'cara_input_barang' => 'manual',
                ],
            ],
        ], $administrator->id);
        $detailUnit = $loan->detailPeminjamanBarang()->where('unit_barang_id', $unit->id)->firstOrFail();
        $detailStok = $loan->detailPeminjamanBarang()->where('barang_id', $stokKembali->id)->firstOrFail();

        $this->withToken($token)
            ->getJson(route('api.v1.pengembalian-barang.identifikasi', ['kode' => 'AST-2026-000010']))
            ->assertOk()
            ->assertJsonPath('data.peminjaman_id', $loan->id)
            ->assertJsonPath('data.detail_id', $detailUnit->id)
            ->assertJsonPath('data.nama_peminjam', $siswa->nama_lengkap);

        $this->withToken($token)
            ->getJson(route('api.v1.pengembalian-barang.index', ['cari' => 'Laptop']))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.jatuh_tempo', 1)
            ->assertJsonPath('data.items.0.id', $loan->id)
            ->assertJsonPath('data.items.0.items_belum_kembali', 2);

        $this->withToken($token)
            ->postJson(route('api.v1.pengembalian-barang.store', $loan), [
                'tanggal_pengembalian' => now()->toDateString(),
                'catatan' => ' Dikembalikan sebagian. ',
                'items' => [
                    [
                        'detail_peminjaman_barang_id' => $detailUnit->id,
                        'jumlah' => 1,
                        'kondisi_pengembalian' => 'rusak_ringan',
                        'cara_input_barang' => 'scan',
                    ],
                    [
                        'detail_peminjaman_barang_id' => $detailStok->id,
                        'jumlah' => 1,
                        'cara_input_barang' => 'manual',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.peminjaman.status', 'sebagian_dikembalikan')
            ->assertJsonPath('data.peminjaman.pengembalian.0.catatan', 'Dikembalikan sebagian.')
            ->assertJsonFragment(['nama_barang' => 'Laptop', 'jumlah_belum_dikembalikan' => 0])
            ->assertJsonFragment(['nama_barang' => 'Bola Basket', 'jumlah_belum_dikembalikan' => 1])
            ->assertJsonFragment(['kondisi_label' => 'Rusak ringan']);

        $this->assertSame('dalam_perbaikan', $unit->fresh()->status_unit);
        $this->assertSame('9.00', SaldoStokBarang::where('barang_id', $stokKembali->id)->value('jumlah'));
    }

    public function test_pembaca_hanya_dapat_melihat_peminjaman_bukan_mencatat_atau_mengembalikan(): void
    {
        $pembaca = $this->penggunaDenganIzin('barang.lihat');
        [$siswa, , $lokasi, $unit] = $this->master();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $loan = app(ProsesPeminjamanBarang::class)->catat([
            'jenis_peminjam' => 'siswa',
            'siswa_id' => $siswa->id,
            'cara_input_peminjam' => 'manual',
            'tanggal_peminjaman' => now()->toDateString(),
            'items' => [['tipe_item' => 'unit', 'unit_barang_id' => $unit->id, 'cara_input_barang' => 'manual']],
        ], $administrator->id);
        $token = $this->token($pembaca);

        $this->withToken($token)->getJson(route('api.v1.peminjaman-barang.index'))
            ->assertOk()->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($token)->getJson(route('api.v1.peminjaman-barang.show', $loan))
            ->assertOk()->assertJsonPath('data.hak_akses.dapat_mengembalikan', false);
        $this->withToken($token)->getJson(route('api.v1.rekap-peminjaman-barang.index'))
            ->assertOk()->assertJsonPath('data.hak_akses.dapat_mengembalikan', false);
        $this->withToken($token)->getJson(route('api.v1.rekap-peminjaman-barang.document'))
            ->assertOk();
        $this->withToken($token)->postJson(route('api.v1.peminjaman-barang.store'), [])->assertForbidden();
        $this->withToken($token)->getJson(route('api.v1.pengembalian-barang.index'))->assertForbidden();
        $this->withToken($token)->postJson(route('api.v1.pengembalian-barang.store', $loan), [])->assertForbidden();
    }

    private function master(): array
    {
        $kategori = KategoriBarang::create(['kode' => 'SAR', 'nama' => 'Sarana', 'aktif' => true]);
        $satuan = SatuanBarang::create(['kode' => 'UNIT', 'nama' => 'Unit', 'aktif' => true]);
        $lokasi = LokasiBarang::create(['kode' => 'GDG', 'nama' => 'Gudang Utama', 'jenis' => 'gudang', 'aktif' => true]);
        $siswa = Siswa::create(['nama_lengkap' => 'Raka Pratama', 'nisn' => '0099887766', 'nis' => '12001', 'aktif' => true]);
        $pegawai = Pegawai::create(['nama_lengkap' => 'Dina Kurnia, S.Pd.', 'nip' => '198505052010012001', 'aktif' => true]);
        $barangAset = Barang::create([
            'kode' => '02.06.01.05.40',
            'nama' => 'Laptop',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'tipe_pengelolaan' => 'aset_individual',
            'jenis_barang' => 'tidak_habis_pakai',
            'aktif' => true,
        ]);
        $unit = UnitBarang::create([
            'barang_id' => $barangAset->id,
            'nomor_unit' => 10,
            'kode_inventaris' => 'AST-2026-000010',
            'nomor_aset_resmi' => '12.03.15.08.10.2026.10',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'aktif' => true,
        ]);
        $stokKembali = Barang::create([
            'kode' => 'BOLA-001',
            'nama' => 'Bola Basket',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'tipe_pengelolaan' => 'stok_dikembalikan',
            'jenis_barang' => 'tidak_habis_pakai',
            'aktif' => true,
        ]);
        $habisPakai = Barang::create([
            'kode' => 'BHP-000020',
            'nama' => 'Spidol',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'lokasi_penyimpanan_id' => $lokasi->id,
            'tipe_pengelolaan' => 'habis_pakai',
            'jenis_barang' => 'habis_pakai',
            'aktif' => true,
        ]);
        SaldoStokBarang::create(['barang_id' => $stokKembali->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 10]);
        SaldoStokBarang::create(['barang_id' => $habisPakai->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 20]);

        return [$siswa, $pegawai, $lokasi, $unit, $stokKembali, $habisPakai];
    }

    private function penggunaDenganIzin(string $izin): Pengguna
    {
        $peran = Peran::create(['nama' => 'Pembaca Peminjaman', 'kode' => 'pembaca_peminjaman', 'aktif' => true, 'sistem' => false]);
        $peran->izin()->attach(Izin::where('kode', $izin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Peminjaman',
            'username' => 'pembaca.peminjaman',
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
        return $pengguna->createToken('Perangkat Peminjaman Barang', ['mobile'])->plainTextToken;
    }
}
