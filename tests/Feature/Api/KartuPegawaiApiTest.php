<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KartuPegawaiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_kartu_pegawai_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.kartu-pegawai.index'))->assertUnauthorized();

        $tanpaIzin = Pengguna::create([
            'nama' => 'Tanpa Izin Kartu Pegawai',
            'username' => 'tanpa.izin.kartu.pegawai',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($tanpaIzin))
            ->getJson(route('api.v1.kartu-pegawai.index'))
            ->assertForbidden();
    }

    public function test_pembaca_dapat_memfilter_dan_melihat_data_kartu_pegawai(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pegawai/foto/guru-kartu.jpg', 'foto');
        $guru = Pegawai::create([
            'nama_lengkap' => 'Antonius Kartu Mobile',
            'nip' => '199211032019021001',
            'foto' => 'pegawai/foto/guru-kartu.jpg',
            'jenis_pegawai' => 'Guru',
            'jabatan_utama' => 'Guru Mata Pelajaran',
            'aktif' => true,
        ]);
        Pegawai::create([
            'nama_lengkap' => 'Pegawai Nonaktif Mobile',
            'nip' => '197001012020011002',
            'jenis_pegawai' => 'Tenaga Kependidikan',
            'aktif' => false,
        ]);
        $pembaca = $this->penggunaDenganIzin('pegawai.lihat');

        $this->withToken($this->token($pembaca))
            ->getJson(route('api.v1.kartu-pegawai.index', [
                'jenis_pegawai' => 'Guru',
                'pegawai_id' => $guru->id,
                'status' => 'aktif',
                'cari' => 'Antonius',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.nama', 'Antonius Kartu Mobile')
            ->assertJsonPath('data.items.0.jabatan', 'Guru Mata Pelajaran')
            ->assertJsonPath('data.items.0.qr_data', '199211032019021001')
            ->assertJsonPath('data.items.0.qr_bisa_dibuat', true)
            ->assertJsonPath('data.items.0.punya_foto', true)
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.ringkasan.siap_qr', 1)
            ->assertJsonPath('data.ringkasan.dengan_foto', 1)
            ->assertJsonPath('data.ukuran_kartu.lebar_mm', 53.98)
            ->assertJsonPath('data.ukuran_kartu.tinggi_mm', 85.6)
            ->assertJsonPath('data.hak_akses.dapat_kelola_foto', false)
            ->assertJsonFragment(['Tenaga Kependidikan']);
    }

    public function test_api_menandai_kartu_tanpa_nip_numerik_dan_foto_fisik(): void
    {
        Storage::fake('public');
        Pegawai::create([
            'nama_lengkap' => 'Pegawai QR Belum Siap',
            'nip' => 'NIP-BELUM-VALID',
            'foto' => 'pegawai/foto/tidak-ada.jpg',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.kartu-pegawai.index'))
            ->assertOk()
            ->assertJsonPath('data.items.0.qr_data', null)
            ->assertJsonPath('data.items.0.qr_bisa_dibuat', false)
            ->assertJsonPath('data.items.0.punya_foto', false)
            ->assertJsonPath('data.ringkasan.dengan_foto', 0)
            ->assertJsonPath('data.hak_akses.dapat_kelola_foto', true);
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Kartu Pegawai API',
            'kode' => 'pembaca_kartu_pegawai_api',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Kartu Pegawai API',
            'username' => 'pembaca.kartu.pegawai.api',
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
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
