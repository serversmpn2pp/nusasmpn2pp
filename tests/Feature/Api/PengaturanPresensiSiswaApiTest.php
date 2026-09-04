<?php

namespace Tests\Feature\Api;

use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanPresensiSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_memerlukan_token_dan_izin_pengaturan_presensi(): void
    {
        $this->getJson(route('api.v1.pengaturan-presensi-siswa.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Pegawai Tanpa Izin Presensi',
            'username' => 'pegawai.tanpa.izin.presensi',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.pengaturan-presensi-siswa.index'))
            ->assertForbidden();
    }

    public function test_administrator_dapat_melihat_ringkasan_dan_memfilter_pengaturan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->pengaturan('senin', true);
        $this->pengaturan('jumat', false);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pengaturan-presensi-siswa.index', [
                'hari' => 'senin',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.hari', 'senin')
            ->assertJsonPath('data.items.0.jam_masuk', '07:00')
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.nonaktif', 1)
            ->assertJsonPath('data.ringkasan.belum_diatur', 5)
            ->assertJsonCount(7, 'data.hari')
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);
    }

    public function test_administrator_dapat_menambah_dan_memperbarui_pengaturan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.pengaturan-presensi-siswa.store'), [
                'hari' => 'selasa',
                'jam_scan_masuk_mulai' => '06:00',
                'jam_masuk' => '07:00',
                'jam_scan_masuk_selesai' => '07:30',
                'jam_scan_pulang_mulai' => '14:00',
                'jam_pulang' => '14:10',
                'jam_scan_pulang_selesai' => '15:00',
                'aktif' => true,
                'keterangan' => 'Jadwal reguler',
            ])
            ->assertCreated();

        $pengaturan = PengaturanAbsensi::where('hari', 'selasa')->firstOrFail();
        $response->assertJsonPath('data.id', $pengaturan->id);
        $this->assertSame(2, $pengaturan->urutan_hari);

        $this->withToken($token)
            ->patchJson(route('api.v1.pengaturan-presensi-siswa.update', $pengaturan), [
                'hari' => 'selasa',
                'jam_scan_masuk_mulai' => '05:45',
                'jam_masuk' => '06:45',
                'jam_scan_masuk_selesai' => '07:15',
                'jam_scan_pulang_mulai' => '13:30',
                'jam_pulang' => '13:40',
                'jam_scan_pulang_selesai' => '14:30',
                'aktif' => false,
                'keterangan' => 'Jadwal diperbarui dari Android',
            ])
            ->assertOk();

        $this->assertDatabaseHas('pengaturan_absensi', [
            'id' => $pengaturan->id,
            'hari' => 'selasa',
            'jam_masuk' => '06:45',
            'jam_pulang' => '13:40',
            'aktif' => false,
            'keterangan' => 'Jadwal diperbarui dari Android',
        ]);
    }

    public function test_api_menolak_hari_ganda_dan_urutan_waktu_yang_tidak_valid(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);
        $this->pengaturan('rabu');
        $data = [
            'hari' => 'rabu',
            'jam_scan_masuk_mulai' => '07:15',
            'jam_masuk' => '07:00',
            'jam_scan_masuk_selesai' => '07:30',
            'jam_scan_pulang_mulai' => '14:00',
            'jam_pulang' => '14:10',
            'jam_scan_pulang_selesai' => '15:00',
            'aktif' => true,
        ];

        $this->withToken($token)
            ->postJson(route('api.v1.pengaturan-presensi-siswa.store'), $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hari');

        $data['hari'] = 'kamis';
        $this->withToken($token)
            ->postJson(route('api.v1.pengaturan-presensi-siswa.store'), $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jam_masuk');
    }

    public function test_api_menyimpan_dan_memvalidasi_jadwal_pulang_jumat_perempuan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);
        $data = [
            'hari' => 'jumat',
            'jam_scan_masuk_mulai' => '05:30',
            'jam_masuk' => '06:25',
            'jam_scan_masuk_selesai' => '07:00',
            'jam_scan_pulang_mulai' => '12:50',
            'jam_pulang' => '12:50',
            'jam_scan_pulang_selesai' => '14:00',
            'pulang_jumat_dibedakan' => true,
            'jam_scan_pulang_perempuan_mulai' => '11:50',
            'jam_pulang_perempuan' => '11:50',
            'jam_scan_pulang_perempuan_selesai' => '14:00',
            'aktif' => true,
        ];

        $this->withToken($token)
            ->postJson(route('api.v1.pengaturan-presensi-siswa.store'), $data)
            ->assertCreated();

        $this->withToken($token)
            ->getJson(route('api.v1.pengaturan-presensi-siswa.index', ['hari' => 'jumat']))
            ->assertOk()
            ->assertJsonPath('data.items.0.pulang_jumat_dibedakan', true)
            ->assertJsonPath('data.items.0.jam_scan_pulang_perempuan_mulai', '11:50')
            ->assertJsonPath('data.items.0.jam_pulang_perempuan', '11:50');

        PengaturanAbsensi::query()->delete();
        unset($data['jam_pulang_perempuan']);

        $this->withToken($token)
            ->postJson(route('api.v1.pengaturan-presensi-siswa.store'), $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jam_pulang_perempuan');
    }

    private function pengaturan(string $hari, bool $aktif = true): PengaturanAbsensi
    {
        return PengaturanAbsensi::create([
            'hari' => $hari,
            'urutan_hari' => PengaturanAbsensi::DAFTAR_HARI[$hari]['urutan'],
            'jam_scan_masuk_mulai' => '06:00',
            'jam_masuk' => '07:00',
            'jam_scan_masuk_selesai' => '07:30',
            'jam_scan_pulang_mulai' => '14:00',
            'jam_pulang' => '14:10',
            'jam_scan_pulang_selesai' => '15:00',
            'aktif' => $aktif,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
