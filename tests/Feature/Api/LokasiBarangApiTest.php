<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\LokasiBarang;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LokasiBarangApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_dan_mengirim_ringkasan_filter_pilihan_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = $this->buatPegawai('Ibu Ratna', '198001012026092001');
        LokasiBarang::create([
            'nama' => 'Gudang Utama',
            'kode' => 'GUDANG_UTAMA',
            'jenis' => 'gudang',
            'penanggung_jawab_pegawai_id' => $pegawai->id,
            'deskripsi' => 'Tempat penyimpanan utama.',
            'aktif' => true,
        ]);
        LokasiBarang::create([
            'nama' => 'Kelas Lama',
            'kode' => 'KELAS_LAMA',
            'jenis' => 'kelas',
            'aktif' => false,
        ]);

        $this->getJson(route('api.v1.lokasi-barang.index'))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.lokasi-barang.index', [
                'cari' => 'gudang',
                'status' => 'aktif',
                'jenis' => 'gudang',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.cari', 'gudang')
            ->assertJsonPath('data.filter.status', 'aktif')
            ->assertJsonPath('data.filter.jenis', 'gudang')
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.dengan_penanggung_jawab', 1)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.kode', 'GUDANG_UTAMA')
            ->assertJsonPath('data.items.0.label_jenis', 'Gudang')
            ->assertJsonPath('data.items.0.penanggung_jawab.nama', 'Ibu Ratna')
            ->assertJsonPath('data.items.0.jumlah_barang', 0)
            ->assertJsonCount(4, 'data.pilihan.jenis')
            ->assertJsonPath('data.pilihan.pegawai.0.nama', 'Ibu Ratna')
            ->assertJsonStructure([
                'data' => [
                    'items' => [[
                        'id',
                        'nama',
                        'kode',
                        'jenis',
                        'label_jenis',
                        'penanggung_jawab',
                        'deskripsi',
                        'aktif',
                        'jumlah_barang',
                    ]],
                    'paginasi',
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'lokasi-barang',
                'status' => 'tersedia',
                'rute' => '/lokasi-barang',
            ]);
    }

    public function test_administrator_dapat_menambah_mengubah_dan_menonaktifkan_tanpa_menghapus_data(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = $this->buatPegawai('Bapak Andi', '198101012026091001');
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.lokasi-barang.store'), [
                'nama' => '  Laboratorium Informatika  ',
                'kode' => ' lab informatika ',
                'jenis' => 'ruangan',
                'penanggung_jawab_pegawai_id' => $pegawai->id,
                'deskripsi' => '  Ruang pembelajaran informatika.  ',
                'aktif' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nama', 'Laboratorium Informatika')
            ->assertJsonPath('data.kode', 'LAB_INFORMATIKA')
            ->assertJsonPath('data.label_jenis', 'Ruangan')
            ->assertJsonPath('data.penanggung_jawab.nama', 'Bapak Andi')
            ->assertJsonPath('data.deskripsi', 'Ruang pembelajaran informatika.')
            ->assertJsonPath('data.aktif', true);
        $id = (int) $response->json('data.id');

        $this->withToken($token)
            ->patchJson(route('api.v1.lokasi-barang.update', $id), [
                'nama' => 'Lab Komputer',
                'kode' => 'lab-komputer',
                'jenis' => 'ruangan',
                'penanggung_jawab_pegawai_id' => null,
                'deskripsi' => null,
                'aktif' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('lokasi_barang', [
            'id' => $id,
            'nama' => 'Lab Komputer',
            'kode' => 'LAB_KOMPUTER',
            'jenis' => 'ruangan',
            'penanggung_jawab_pegawai_id' => null,
            'deskripsi' => null,
            'aktif' => true,
        ]);

        $this->withToken($token)
            ->deleteJson(route('api.v1.lokasi-barang.destroy', $id))
            ->assertOk();

        $this->assertDatabaseHas('lokasi_barang', [
            'id' => $id,
            'aktif' => false,
        ]);
        $this->assertSame(1, LokasiBarang::count());
    }

    public function test_nama_dan_kode_harus_unik_setelah_kode_dirapikan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        LokasiBarang::create([
            'nama' => 'Gudang Utama',
            'kode' => 'GUDANG_UTAMA',
            'jenis' => 'gudang',
            'aktif' => true,
        ]);

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.lokasi-barang.store'), [
                'nama' => 'Gudang Utama',
                'kode' => 'gudang utama',
                'jenis' => 'gudang',
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nama', 'kode']);
    }

    public function test_izin_lihat_hanya_dapat_membuka_daftar_bukan_mengubah(): void
    {
        $pengguna = $this->penggunaDenganIzin('barang.lihat');
        $token = $this->token($pengguna);

        $this->withToken($token)
            ->getJson(route('api.v1.lokasi-barang.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);

        $this->withToken($token)
            ->postJson(route('api.v1.lokasi-barang.store'), [
                'nama' => 'Tidak Boleh Dibuat',
                'kode' => 'TIDAK_BOLEH',
                'jenis' => 'lainnya',
                'aktif' => true,
            ])
            ->assertForbidden();
    }

    private function buatPegawai(string $nama, string $nip): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Lokasi Barang Mobile',
            'kode' => 'pembaca_lokasi_barang_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Lokasi Barang Mobile',
            'username' => 'pembaca.lokasi.barang.mobile',
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
        return $pengguna->createToken('Perangkat Lokasi Barang', ['mobile'])->plainTextToken;
    }
}
