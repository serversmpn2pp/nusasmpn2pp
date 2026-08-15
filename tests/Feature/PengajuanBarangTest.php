<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\Pegawai;
use App\Models\PengajuanBarang;
use App\Models\Pengguna;
use App\Models\SaldoStokBarang;
use App\Models\SatuanBarang;
use App\Models\Siswa;
use App\Models\UnitBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengajuanBarangTest extends TestCase
{
    use RefreshDatabase;

    public function test_pegawai_mengajukan_aset_dan_petugas_memenuhinya_sebagai_peminjaman(): void
    {
        [$pengguna, $pegawai, $administrator, $lokasi, $barangAset] = $this->buatDataDasar();
        $unit = UnitBarang::create([
            'barang_id' => $barangAset->id,
            'nomor_unit' => 1,
            'kode_inventaris' => 'AST-2026-000001',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'aktif' => true,
        ]);

        $this->actingAs($pengguna)
            ->get(route('katalog-barang.index'))
            ->assertOk()
            ->assertSee('Ajukan peminjaman');

        $this->post(route('pengajuan-barang-saya.store'), [
            'barang_id' => $barangAset->id,
            'jumlah' => 1,
            'tanggal_dibutuhkan' => now()->addDay()->toDateString(),
            'rencana_kembali' => now()->addWeek()->toDateString(),
            'tujuan' => 'Pembelajaran Informatika kelas VIII.A.',
        ])->assertRedirect();

        $pengajuan = PengajuanBarang::firstOrFail();
        $this->assertSame('peminjaman', $pengajuan->jenis_pengajuan);
        $this->assertSame('menunggu', $pengajuan->status);
        $this->assertSame($pegawai->id, $pengajuan->pegawai_id);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $administrator->id,
            'judul' => 'Pengajuan barang baru',
        ]);

        $this->get(route('pengajuan-barang-saya.show', $pengajuan))
            ->assertOk()
            ->assertSee($barangAset->nama)
            ->assertSee('Menunggu petugas');

        $pegawaiLain = Pegawai::create(['nama_lengkap' => 'Pegawai Lain', 'nip' => '198001012010011001', 'aktif' => true]);
        $penggunaLain = Pengguna::create([
            'pegawai_id' => $pegawaiLain->id,
            'nama' => $pegawaiLain->nama_lengkap,
            'username' => $pegawaiLain->nip,
            'kata_sandi' => 'kata-sandi-uji',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);

        $this->actingAs($penggunaLain)
            ->get(route('pengajuan-barang-saya.show', $pengajuan))
            ->assertForbidden();

        $this->actingAs($administrator)
            ->get(route('pengajuan-barang.show', $pengajuan))
            ->assertOk()
            ->assertSee($unit->kode_inventaris)
            ->assertSee('Penuhi dan serahkan');

        $this->patch(route('pengajuan-barang.penuhi', $pengajuan), [
            'unit_barang_ids' => [$unit->id],
            'catatan_petugas' => 'Diserahkan dalam kondisi baik.',
        ])->assertRedirect(route('pengajuan-barang.show', $pengajuan));

        $pengajuan->refresh();
        $this->assertSame('dipenuhi', $pengajuan->status);
        $this->assertNotNull($pengajuan->peminjaman_barang_id);
        $this->assertSame('dipinjam', $unit->fresh()->status_unit);
        $this->assertSame('dipinjam', $pengajuan->peminjamanBarang->status);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $pengguna->id,
            'judul' => 'Pengajuan barang dipenuhi',
        ]);
    }

    public function test_permintaan_barang_habis_pakai_mengurangi_stok_saat_dipenuhi(): void
    {
        [$pengguna, , $administrator, $lokasi, , $barangHabisPakai] = $this->buatDataDasar();
        SaldoStokBarang::create([
            'barang_id' => $barangHabisPakai->id,
            'lokasi_barang_id' => $lokasi->id,
            'jumlah' => 10,
        ]);

        $this->actingAs($pengguna)
            ->post(route('pengajuan-barang-saya.store'), [
                'barang_id' => $barangHabisPakai->id,
                'jumlah' => 3,
                'tanggal_dibutuhkan' => now()->toDateString(),
                'tujuan' => 'Digunakan untuk menulis di papan kelas.',
            ])->assertRedirect();

        $pengajuan = PengajuanBarang::firstOrFail();
        $this->assertSame('permintaan', $pengajuan->jenis_pengajuan);
        $this->assertNull($pengajuan->rencana_kembali);

        $this->actingAs($administrator)
            ->patch(route('pengajuan-barang.penuhi', $pengajuan), [
                'lokasi_barang_id' => $lokasi->id,
            ])->assertRedirect(route('pengajuan-barang.show', $pengajuan));

        $pengajuan->refresh();
        $this->assertSame('dipenuhi', $pengajuan->status);
        $this->assertSame('selesai', $pengajuan->peminjamanBarang->status);
        $this->assertSame('7.00', SaldoStokBarang::where('barang_id', $barangHabisPakai->id)->value('jumlah'));

        $this->get(route('laporan-inventaris-bulanan.index', ['periode' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Layanan Barang Pegawai')
            ->assertSee($pengguna->pegawai->nama_lengkap)
            ->assertSee($barangHabisPakai->nama)
            ->assertSee($pengajuan->nomor_pengajuan)
            ->assertSee('Barang habis pakai');

        $this->get(route('laporan-inventaris-bulanan.cetak', ['periode' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('RINCIAN MUTASI STOK &amp; LAYANAN BARANG', false)
            ->assertSee($pengguna->pegawai->nama_lengkap)
            ->assertSee($pengajuan->nomor_pengajuan);
    }

    public function test_pengajuan_dapat_dibatalkan_ditolak_dan_tidak_dapat_dibuka_siswa(): void
    {
        [$pengguna, , $administrator, $lokasi, $barangAset] = $this->buatDataDasar();
        UnitBarang::create([
            'barang_id' => $barangAset->id,
            'nomor_unit' => 1,
            'kode_inventaris' => 'AST-2026-000001',
            'lokasi_barang_id' => $lokasi->id,
            'kondisi' => 'baik',
            'status_unit' => 'tersedia',
            'aktif' => true,
        ]);
        $data = [
            'barang_id' => $barangAset->id,
            'jumlah' => 1,
            'tanggal_dibutuhkan' => now()->addDay()->toDateString(),
            'rencana_kembali' => now()->addWeek()->toDateString(),
            'tujuan' => 'Kegiatan pembelajaran di ruang kelas.',
        ];

        $this->actingAs($pengguna)->post(route('pengajuan-barang-saya.store'), $data);
        $dibatalkan = PengajuanBarang::firstOrFail();
        $this->patch(route('pengajuan-barang-saya.batalkan', $dibatalkan))
            ->assertRedirect(route('pengajuan-barang-saya.show', $dibatalkan));
        $this->assertSame('dibatalkan', $dibatalkan->fresh()->status);

        $this->post(route('pengajuan-barang-saya.store'), $data);
        $ditolak = PengajuanBarang::latest('id')->firstOrFail();
        $this->actingAs($administrator)
            ->patch(route('pengajuan-barang.tolak', $ditolak), [
                'catatan_petugas' => 'Barang digunakan untuk kegiatan lain.',
            ])->assertRedirect(route('pengajuan-barang.show', $ditolak));
        $this->assertSame('ditolak', $ditolak->fresh()->status);

        $siswa = Siswa::create(['nama_lengkap' => 'Raka Pratama', 'nisn' => '0011223344', 'aktif' => true]);
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
            ->get(route('pengajuan-barang-saya.index'))
            ->assertForbidden();
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
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $kategori = KategoriBarang::create(['kode' => 'ATK', 'nama' => 'Perlengkapan Sekolah', 'aktif' => true]);
        $satuan = SatuanBarang::create(['kode' => 'UNT', 'nama' => 'Unit', 'aktif' => true]);
        $lokasi = LokasiBarang::create(['kode' => 'GDG', 'nama' => 'Gudang Utama', 'jenis' => 'gudang', 'aktif' => true]);
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

        return [$pengguna, $pegawai, $administrator, $lokasi, $barangAset, $barangHabisPakai];
    }
}
