<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\RiwayatLogin;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AktivitasLoginApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_api_aktivitas_login_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.aktivitas-login.index'))->assertUnauthorized();

        $pembaca = $this->penggunaDenganIzin('aktivitas_login.lihat');
        $this->withToken($this->token($pembaca))
            ->getJson(route('api.v1.aktivitas-login.index'))
            ->assertOk()
            ->assertHeader('Cache-Control');
    }

    public function test_pengguna_tanpa_izin_tidak_dapat_membaca_aktivitas_login(): void
    {
        $tanpaIzin = $this->buatPengguna('Tanpa Izin Aktivitas', 'tanpa.izin.aktivitas');

        $this->withToken($this->token($tanpaIzin))
            ->getJson(route('api.v1.aktivitas-login.index'))
            ->assertForbidden();
    }

    public function test_daftar_pengguna_memuat_ringkasan_role_perangkat_dan_filter(): void
    {
        Carbon::setTestNow('2026-08-26 10:00:00');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $administrator->forceFill(['terakhir_login_pada' => now()->subHour()])->save();
        $belumLogin = $this->buatPengguna('Belum Login Mobile', 'belum.login.mobile');

        RiwayatLogin::create([
            'pengguna_id' => $administrator->id,
            'username' => $administrator->username,
            'berhasil' => true,
            'alamat_ip' => '10.0.0.10',
            'user_agent' => 'Mozilla/5.0 (Android 16) Chrome/151.0',
        ]);
        RiwayatLogin::create([
            'pengguna_id' => $administrator->id,
            'username' => $administrator->username,
            'berhasil' => false,
            'alamat_ip' => '10.0.0.11',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Edge/151.0',
        ]);

        $token = $this->token($administrator);
        $this->withToken($token)
            ->getJson(route('api.v1.aktivitas-login.index', [
                'cari' => 'administrator',
                'jenis_akun' => 'administrator',
                'status_login' => 'pernah',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.username', 'administrator')
            ->assertJsonPath('data.items.0.jenis_akun.kode', 'administrator')
            ->assertJsonPath('data.items.0.perangkat_terakhir', 'Android - Chrome')
            ->assertJsonPath('data.items.0.jumlah_login_berhasil', 1)
            ->assertJsonPath('data.items.0.jumlah_login_gagal', 1)
            ->assertJsonPath('data.ringkasan.login_hari_ini', 1)
            ->assertJsonPath('data.ringkasan.gagal_hari_ini', 1)
            ->assertJsonPath('data.filter.tampilan', 'pengguna')
            ->assertJsonPath('data.paginasi.total', 1);

        $this->withToken($token)
            ->getJson(route('api.v1.aktivitas-login.index', [
                'cari' => 'belum.login.mobile',
                'status_login' => 'belum',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $belumLogin->id)
            ->assertJsonPath('data.items.0.terakhir_login_pada', null);
    }

    public function test_riwayat_dapat_difilter_berdasarkan_hasil_perangkat_dan_tanggal(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $android = $this->buatRiwayat(
            $administrator,
            true,
            'Mozilla/5.0 (Linux; Android 16) Chrome/151.0',
            '2026-08-25 08:15:00',
        );
        $this->buatRiwayat(
            $administrator,
            false,
            'Mozilla/5.0 (Windows NT 10.0) Edge/151.0',
            '2026-08-25 08:20:00',
        );
        $this->buatRiwayat(
            $administrator,
            true,
            'Mozilla/5.0 (Linux x86_64) Firefox/141.0',
            '2026-08-20 08:20:00',
        );

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.aktivitas-login.index', [
                'tampilan' => 'riwayat',
                'jenis_akun' => 'administrator',
                'status_percobaan' => 'berhasil',
                'perangkat' => 'android',
                'tanggal_mulai' => '2026-08-25',
                'tanggal_selesai' => '2026-08-25',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $android->id)
            ->assertJsonPath('data.items.0.berhasil', true)
            ->assertJsonPath('data.items.0.perangkat.kode', 'android')
            ->assertJsonPath('data.items.0.perangkat.label', 'Android - Chrome')
            ->assertJsonPath('data.items.0.perangkat.user_agent', null)
            ->assertJsonPath('data.filter.tampilan', 'riwayat')
            ->assertJsonPath('data.filter.perangkat', 'android')
            ->assertJsonPath('data.paginasi.total', 1);
    }

    public function test_detail_riwayat_memuat_user_agent_dan_akun_tidak_dikenal(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $riwayat = RiwayatLogin::create([
            'pengguna_id' => null,
            'username' => 'akun-tidak-dikenal',
            'berhasil' => false,
            'alamat_ip' => '192.168.10.90',
            'user_agent' => 'NUSA-Mobile/1.0 (Android 16)',
        ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.aktivitas-login.show', $riwayat))
            ->assertOk()
            ->assertJsonPath('data.riwayat.id', $riwayat->id)
            ->assertJsonPath('data.riwayat.username', 'akun-tidak-dikenal')
            ->assertJsonPath('data.riwayat.berhasil', false)
            ->assertJsonPath('data.riwayat.pengguna', null)
            ->assertJsonPath('data.riwayat.alamat_ip', '192.168.10.90')
            ->assertJsonPath('data.riwayat.perangkat.kode', 'android')
            ->assertJsonPath(
                'data.riwayat.perangkat.user_agent',
                'NUSA-Mobile/1.0 (Android 16)',
            );
    }

    private function buatPengguna(string $nama, string $username): Pengguna
    {
        return Pengguna::create([
            'nama' => $nama,
            'username' => $username,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Aktivitas Login API',
            'kode' => 'pembaca_aktivitas_login_api',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = $this->buatPengguna(
            'Pembaca Aktivitas Login API',
            'pembaca.aktivitas.login.api',
        );
        $pengguna->daftarPeran()->attach($peran);

        return $pengguna;
    }

    private function buatRiwayat(
        Pengguna $pengguna,
        bool $berhasil,
        string $userAgent,
        string $waktu,
    ): RiwayatLogin {
        $riwayat = RiwayatLogin::create([
            'pengguna_id' => $pengguna->id,
            'username' => $pengguna->username,
            'berhasil' => $berhasil,
            'alamat_ip' => '10.10.10.20',
            'user_agent' => $userAgent,
        ]);
        $riwayat->forceFill([
            'created_at' => Carbon::parse($waktu),
            'updated_at' => Carbon::parse($waktu),
        ])->save();

        return $riwayat;
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
