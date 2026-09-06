<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\PengaturanInventaris;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanInventarisApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengaturan_memerlukan_token_dan_mengirim_identitas_serta_contoh_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = now()->format('Y');

        $this->getJson(route('api.v1.pengaturan-inventaris.show'))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pengaturan-inventaris.show'))
            ->assertOk()
            ->assertJsonPath('data.kode', 'utama')
            ->assertJsonPath('data.awalan_nomor_aset', '12.03.15.08.10')
            ->assertJsonPath('data.akhiran_nomor_aset', '08')
            ->assertJsonPath('data.nama_pemilik', 'SMPN 2 Padang Panjang')
            ->assertJsonPath('data.jumlah_digit_id_internal', 6)
            ->assertJsonPath('data.tahun_contoh', (int) $tahun)
            ->assertJsonPath('data.contoh_nomor_aset', '12.03.15.08.10.'.$tahun.'.08')
            ->assertJsonPath('data.contoh_kode_barang_habis_pakai', 'BHP-000001')
            ->assertJsonPath('data.contoh_kode_unit_aset', 'AST-'.$tahun.'-000001')
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'diperbarui_oleh',
                    'diperbarui_pada',
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'pengaturan-inventaris',
                'status' => 'tersedia',
                'rute' => '/pengaturan-inventaris',
            ]);
    }

    public function test_administrator_dapat_memperbarui_pengaturan_dan_contoh_kode(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = now()->format('Y');

        $this->withToken($this->token($administrator))
            ->patchJson(route('api.v1.pengaturan-inventaris.update'), [
                'awalan_nomor_aset' => '12.03.15.08.10',
                'akhiran_nomor_aset' => '09',
                'nama_pemilik' => '  SMP Negeri 2 Padang Panjang  ',
                'jumlah_digit_id_internal' => 7,
            ])
            ->assertOk()
            ->assertJsonPath('data.akhiran_nomor_aset', '09')
            ->assertJsonPath('data.nama_pemilik', 'SMP Negeri 2 Padang Panjang')
            ->assertJsonPath('data.jumlah_digit_id_internal', 7)
            ->assertJsonPath('data.contoh_nomor_aset', '12.03.15.08.10.'.$tahun.'.09')
            ->assertJsonPath('data.contoh_kode_barang_habis_pakai', 'BHP-0000001')
            ->assertJsonPath('data.contoh_kode_unit_aset', 'AST-'.$tahun.'-0000001');

        $this->assertDatabaseHas('pengaturan_inventaris', [
            'kode' => 'utama',
            'akhiran_nomor_aset' => '09',
            'nama_pemilik' => 'SMP Negeri 2 Padang Panjang',
            'jumlah_digit_id_internal' => 7,
            'diperbarui_oleh_pengguna_id' => $administrator->id,
        ]);
    }

    public function test_format_nomor_aset_dan_jumlah_digit_divalidasi(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->patchJson(route('api.v1.pengaturan-inventaris.update'), [
                'awalan_nomor_aset' => '12.3.15',
                'akhiran_nomor_aset' => '8',
                'nama_pemilik' => '',
                'jumlah_digit_id_internal' => 11,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'awalan_nomor_aset',
                'akhiran_nomor_aset',
                'nama_pemilik',
                'jumlah_digit_id_internal',
            ]);
    }

    public function test_izin_lihat_barang_tidak_dapat_membuka_atau_mengubah_pengaturan(): void
    {
        $pengguna = $this->penggunaDenganIzin('barang.lihat');
        $token = $this->token($pengguna);

        $this->withToken($token)
            ->getJson(route('api.v1.pengaturan-inventaris.show'))
            ->assertForbidden();

        $this->withToken($token)
            ->patchJson(route('api.v1.pengaturan-inventaris.update'), [
                'awalan_nomor_aset' => '12.03.15.08.10',
                'akhiran_nomor_aset' => '08',
                'nama_pemilik' => 'Tidak Boleh',
                'jumlah_digit_id_internal' => 6,
            ])
            ->assertForbidden();

        $this->assertSame('SMPN 2 Padang Panjang', PengaturanInventaris::utama()->nama_pemilik);
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Inventaris Mobile',
            'kode' => 'pembaca_inventaris_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Inventaris Mobile',
            'username' => 'pembaca.inventaris.mobile',
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
        return $pengguna->createToken('Perangkat Pengaturan Inventaris', ['mobile'])->plainTextToken;
    }
}
