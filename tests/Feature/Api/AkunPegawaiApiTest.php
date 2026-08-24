<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AkunPegawaiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_akun_pegawai_memerlukan_token_dan_membedakan_izin_baca_dengan_kelola(): void
    {
        $this->getJson(route('api.v1.akun-pegawai.index'))->assertUnauthorized();

        $pembaca = $this->penggunaDenganIzin('akun.lihat', 'pembaca_akun_pegawai_api');
        $pegawai = $this->pegawai('Pegawai Pembaca Akun', '198801012026081101');
        $token = $this->token($pembaca);

        $this->withToken($token)
            ->getJson(route('api.v1.akun-pegawai.index'))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->withToken($token)
            ->getJson(route('api.v1.akun-pegawai.show', $pegawai))
            ->assertOk()
            ->assertJsonPath('data.pegawai.nama', 'Pegawai Pembaca Akun');
        $this->withToken($token)
            ->postJson(route('api.v1.akun-pegawai.store', $pegawai))
            ->assertForbidden();
    }

    public function test_daftar_akun_dapat_dicari_difilter_dan_memuat_ringkasan_serta_peran(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $sudah = $this->pegawai('Guru Sudah Punya Akun', '198801012026081102');
        $this->akun($sudah);
        $this->pegawai('Guru Belum Punya Akun', '198801012026081103');
        $this->pegawai('Pegawai Tanpa NIP', null);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.akun-pegawai.index', [
                'cari' => 'Belum',
                'status_akun' => 'belum',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.pegawai.nama', 'Guru Belum Punya Akun')
            ->assertJsonPath('data.items.0.status_akun', 'belum')
            ->assertJsonPath('data.ringkasan.akun_pegawai', 1)
            ->assertJsonPath('data.ringkasan.belum_akun', 1)
            ->assertJsonPath('data.filter.status_akun', 'belum')
            ->assertJsonFragment(['kode' => 'pegawai']);
    }

    public function test_administrator_dapat_membuat_akun_dengan_username_nip_dan_peran_dasar(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = $this->pegawai('Guru Akun Baru Mobile', '1988 0101 2026 081104');

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.akun-pegawai.store', $pegawai))
            ->assertCreated()
            ->assertJsonPath('data.username', '198801012026081104');

        $akun = Pengguna::where('pegawai_id', $pegawai->id)->firstOrFail();
        $this->assertTrue(Hash::check(config('nusa.kata_sandi_default_pegawai'), $akun->kata_sandi));
        $this->assertTrue($akun->daftarPeran()->where('kode', 'pegawai')->exists());
        $this->assertTrue($akun->aktif);

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.akun-pegawai.store', $pegawai))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('akun');
    }

    public function test_status_reset_kata_sandi_dan_peran_dapat_dikelola_tanpa_melepas_peran_pegawai(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = $this->pegawai('Guru Kelola Akun Mobile', '198801012026081105');
        $akun = $this->akun($pegawai, 'KataSandiKhusus123');
        $peranTambahan = Peran::create([
            'nama' => 'Koordinator Mobile',
            'kode' => 'koordinator_mobile',
            'deskripsi' => 'Peran tambahan pengujian.',
            'aktif' => true,
            'sistem' => false,
        ]);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->patchJson(route('api.v1.akun-pegawai.peran', $pegawai), [
                'peran_ids' => [$peranTambahan->id],
            ])
            ->assertOk();
        $this->assertTrue($akun->daftarPeran()->where('kode', 'pegawai')->exists());
        $this->assertTrue($akun->daftarPeran()->where('kode', 'koordinator_mobile')->exists());

        $this->withToken($token)
            ->patchJson(route('api.v1.akun-pegawai.status', $pegawai), ['aktif' => false])
            ->assertOk();
        $this->assertFalse($akun->fresh()->aktif);

        $this->withToken($token)
            ->patchJson(route('api.v1.akun-pegawai.reset-kata-sandi', $pegawai))
            ->assertOk();
        $this->assertTrue(Hash::check(config('nusa.kata_sandi_default_pegawai'), $akun->fresh()->kata_sandi));
        $this->assertTrue($akun->fresh()->harusMenggantiKataSandi());
    }

    public function test_pembuatan_massal_hanya_membuat_akun_pegawai_aktif_yang_memiliki_nip(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $layak = $this->pegawai('Pegawai Layak Massal', '198801012026081106');
        $sudah = $this->pegawai('Pegawai Sudah Akun', '198801012026081107');
        $this->akun($sudah);
        $this->pegawai('Pegawai Nonaktif', '198801012026081108', false);
        $this->pegawai('Pegawai Tanpa NIP Massal', null);

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.akun-pegawai.store-massal'))
            ->assertOk()
            ->assertJsonPath('data.dibuat', 1)
            ->assertJsonPath('data.dilewati', 0);

        $this->assertDatabaseHas('pengguna', [
            'pegawai_id' => $layak->id,
            'username' => '198801012026081106',
        ]);
        $this->assertSame(2, Pengguna::whereNotNull('pegawai_id')->count());
    }

    private function penggunaDenganIzin(string $kodeIzin, string $kodePeran): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Akun Pegawai API',
            'kode' => $kodePeran,
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());

        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Akun Pegawai API',
            'username' => 'pembaca.akun.pegawai.api',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach($peran);

        return $pengguna;
    }

    private function pegawai(string $nama, ?string $nip, bool $aktif = true): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'Guru',
            'jabatan_utama' => 'Guru Mata Pelajaran',
            'aktif' => $aktif,
        ]);
    }

    private function akun(Pegawai $pegawai, string $kataSandi = 'RahasiaNusa123'): Pengguna
    {
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => preg_replace('/\s+/', '', (string) $pegawai->nip),
            'kata_sandi' => $kataSandi,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $peranPegawai = Peran::where('kode', 'pegawai')->firstOrFail();
        $akun->daftarPeran()->attach($peranPegawai);

        return $akun;
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
