<?php

namespace Tests\Feature\Api;

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
use App\Services\Inventaris\ProsesPengajuanBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengajuanBarangSayaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pegawai_melihat_katalog_mengajukan_dan_membatalkan_barang_secara_native(): void
    {
        [$pengguna, $pegawai, , $aset, $unit, $stok, $lokasi] = $this->dataDasar();
        $token = $this->token($pengguna);

        $this->withToken($token)
            ->getJson(route('api.v1.pengajuan-saya.katalog', ['kata_kunci' => 'Laptop']))
            ->assertOk()
            ->assertJsonPath('data.filter.kata_kunci', 'Laptop')
            ->assertJsonPath('data.items.0.id', $aset->id)
            ->assertJsonPath('data.items.0.jumlah_tersedia', 1)
            ->assertJsonPath('data.items.0.satuan', 'unit')
            ->assertJsonPath('data.items.0.jenis_layanan', 'peminjaman')
            ->assertJsonPath('data.items.0.tersedia', true);

        $this->withToken($token)
            ->getJson(route('api.v1.pengajuan-saya.katalog', ['kata_kunci' => 'Spidol']))
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $stok->id)
            ->assertJsonPath('data.items.0.jumlah_tersedia', 12)
            ->assertJsonPath('data.items.0.satuan', 'Buah')
            ->assertJsonPath('data.items.0.jenis_layanan', 'permintaan');

        $response = $this->withToken($token)
            ->postJson(route('api.v1.pengajuan-saya.store'), [
                'barang_id' => $aset->id,
                'jumlah' => 1,
                'tanggal_dibutuhkan' => now()->addDay()->toDateString(),
                'rencana_kembali' => now()->addWeek()->toDateString(),
                'tujuan' => ' Pembelajaran Informatika di kelas. ',
            ])
            ->assertCreated()
            ->assertJsonPath('pesan', 'Pengajuan berhasil dikirim kepada petugas inventaris.')
            ->assertJsonPath('data.pengajuan.nama_barang', $aset->nama)
            ->assertJsonPath('data.pengajuan.tujuan', 'Pembelajaran Informatika di kelas.')
            ->assertJsonPath('data.pengajuan.status', 'menunggu')
            ->assertJsonPath('data.hak_akses.dapat_membatalkan', true);
        $pengajuanId = $response->json('data.pengajuan.id');

        $this->assertDatabaseHas('pengajuan_barang', [
            'id' => $pengajuanId,
            'pegawai_id' => $pegawai->id,
            'jenis_pengajuan' => 'peminjaman',
            'status' => 'menunggu',
        ]);
        $this->assertSame('tersedia', $unit->fresh()->status_unit);

        $this->withToken($token)
            ->getJson(route('api.v1.pengajuan-saya.index', ['status' => 'menunggu']))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.semua', 1)
            ->assertJsonPath('data.ringkasan.menunggu', 1)
            ->assertJsonPath('data.filter.status', 'menunggu')
            ->assertJsonPath('data.items.0.id', $pengajuanId)
            ->assertJsonFragment(['nilai' => 'dibatalkan', 'label' => 'Dibatalkan']);

        $this->withToken($token)
            ->patchJson(route('api.v1.pengajuan-saya.batalkan', $pengajuanId))
            ->assertOk()
            ->assertJsonPath('data.pengajuan.status', 'dibatalkan')
            ->assertJsonPath('data.hak_akses.dapat_membatalkan', false);
        $this->assertSame('dibatalkan', PengajuanBarang::findOrFail($pengajuanId)->status);
        $this->assertSame('12.00', SaldoStokBarang::query()->where('barang_id', $stok->id)->where('lokasi_barang_id', $lokasi->id)->value('jumlah'));

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'pengajuan-saya',
                'status' => 'tersedia',
                'rute' => '/pengajuan-saya',
            ]);
    }

    public function test_pegawai_tidak_dapat_membuka_pengajuan_milik_pegawai_lain(): void
    {
        [, $pegawai, $penggunaLain, $aset] = $this->dataDasar();
        $pengajuan = app(ProsesPengajuanBarang::class)->ajukan($pegawai->id, [
            'barang_id' => $aset->id,
            'jumlah' => 1,
            'tanggal_dibutuhkan' => now()->addDay()->toDateString(),
            'rencana_kembali' => now()->addWeek()->toDateString(),
            'tujuan' => 'Pembelajaran Informatika di kelas.',
        ]);

        $this->withToken($this->token($penggunaLain))
            ->getJson(route('api.v1.pengajuan-saya.show', $pengajuan))
            ->assertForbidden();
        $this->withToken($this->token($penggunaLain))
            ->patchJson(route('api.v1.pengajuan-saya.batalkan', $pengajuan))
            ->assertForbidden();
    }

    public function test_pengajuan_saya_memvalidasi_ketersediaan_stok(): void
    {
        [$pengguna, , , , , $stok] = $this->dataDasar();

        $this->withToken($this->token($pengguna))
            ->postJson(route('api.v1.pengajuan-saya.store'), [
                'barang_id' => $stok->id,
                'jumlah' => 99,
                'tanggal_dibutuhkan' => now()->toDateString(),
                'tujuan' => 'Keperluan administrasi kelas.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jumlah');
    }

    public function test_pengajuan_saya_ditolak_untuk_akun_nonpegawai(): void
    {
        $this->dataDasar();

        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Uji', 'nisn' => '0099001122', 'aktif' => true]);
        $akunSiswa = Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'username' => $siswa->nisn,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($akunSiswa))
            ->getJson(route('api.v1.pengajuan-saya.index'))
            ->assertForbidden();
    }

    private function dataDasar(): array
    {
        $pegawai = Pegawai::create(['nama_lengkap' => 'Dina Kurnia, S.Pd.', 'nip' => '198505052010012001', 'aktif' => true]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pegawaiLain = Pegawai::create(['nama_lengkap' => 'Pegawai Lain', 'nip' => '198001012010011001', 'aktif' => true]);
        $penggunaLain = Pengguna::create([
            'pegawai_id' => $pegawaiLain->id,
            'nama' => $pegawaiLain->nama_lengkap,
            'username' => $pegawaiLain->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $kategori = KategoriBarang::create(['kode' => 'SAR', 'nama' => 'Sarana', 'aktif' => true]);
        $satuan = SatuanBarang::create(['kode' => 'B', 'nama' => 'Buah', 'aktif' => true]);
        $lokasi = LokasiBarang::create(['kode' => 'GDG', 'nama' => 'Gudang Utama', 'jenis' => 'gudang', 'aktif' => true]);
        $aset = Barang::create([
            'kode' => 'AST-LAPTOP',
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
        $stok = Barang::create([
            'kode' => 'BHP-SPIDOL',
            'nama' => 'Spidol Papan Tulis',
            'kategori_barang_id' => $kategori->id,
            'satuan_barang_id' => $satuan->id,
            'tipe_pengelolaan' => 'habis_pakai',
            'jenis_barang' => 'habis_pakai',
            'aktif' => true,
        ]);
        SaldoStokBarang::create(['barang_id' => $stok->id, 'lokasi_barang_id' => $lokasi->id, 'jumlah' => 12]);

        return [$pengguna, $pegawai, $penggunaLain, $aset, $unit, $stok, $lokasi];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Pengajuan Saya', ['mobile'])->plainTextToken;
    }
}
