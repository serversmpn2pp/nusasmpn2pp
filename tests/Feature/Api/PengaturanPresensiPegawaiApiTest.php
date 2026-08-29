<?php

namespace Tests\Feature\Api;

use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanPresensiPegawaiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_memerlukan_token_dan_izin_pengaturan_presensi(): void
    {
        $this->getJson(route('api.v1.pengaturan-presensi-pegawai.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Pegawai Tanpa Izin Pengaturan',
            'username' => 'pegawai.tanpa.izin.pengaturan',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.pengaturan-presensi-pegawai.index'))
            ->assertForbidden();
    }

    public function test_administrator_dapat_melihat_referensi_ringkasan_dan_filter(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $guru = $this->pegawai('Guru Uji Presensi', 'Guru');
        $this->pengaturan('Jadwal Semua Senin', 'senin', 'semua', true);
        $this->pengaturan('Jadwal Guru Selasa', 'selasa', 'jenis_pegawai', false, jenisPegawai: 'Guru');
        $this->pengaturan('Jadwal Khusus Guru', 'rabu', 'pegawai', true, pegawai: $guru);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pengaturan-presensi-pegawai.index', [
                'q' => 'Guru Uji',
                'hari' => 'rabu',
                'cakupan' => 'pegawai',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.nama_jadwal', 'Jadwal Khusus Guru')
            ->assertJsonPath('data.items.0.cakupan_label', 'Pegawai Tertentu')
            ->assertJsonPath('data.items.0.pegawai.nama', 'Guru Uji Presensi')
            ->assertJsonPath('data.ringkasan.total', 3)
            ->assertJsonPath('data.ringkasan.aktif', 2)
            ->assertJsonPath('data.ringkasan.nonaktif', 1)
            ->assertJsonCount(7, 'data.hari')
            ->assertJsonCount(3, 'data.cakupan')
            ->assertJsonFragment(['jenis_pegawai' => 'Guru'])
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);
    }

    public function test_administrator_dapat_menambah_dan_memperbarui_jadwal_pegawai(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = $this->pegawai('Tenaga Kependidikan Mobile', 'Tenaga Kependidikan');
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.pengaturan-presensi-pegawai.store'), $this->dataJadwal([
                'nama_jadwal' => 'Jadwal Khusus TU',
                'cakupan' => 'pegawai',
                'pegawai_id' => $pegawai->id,
                'hari' => 'selasa',
            ]))
            ->assertCreated();

        $pengaturan = PengaturanAbsensiPegawai::where('nama_jadwal', 'Jadwal Khusus TU')->firstOrFail();
        $response->assertJsonPath('data.id', $pengaturan->id);
        $this->assertSame(2, $pengaturan->urutan_hari);
        $this->assertNull($pengaturan->jenis_pegawai);

        $this->withToken($token)
            ->patchJson(
                route('api.v1.pengaturan-presensi-pegawai.update', $pengaturan),
                $this->dataJadwal([
                    'nama_jadwal' => 'Jadwal Tendik Selasa',
                    'cakupan' => 'jenis_pegawai',
                    'jenis_pegawai' => 'Tenaga Kependidikan',
                    'pegawai_id' => null,
                    'hari' => 'selasa',
                    'jam_masuk' => '06:45',
                    'aktif' => false,
                    'keterangan' => 'Diperbarui dari Android',
                ]),
            )
            ->assertOk();

        $this->assertDatabaseHas('pengaturan_absensi_pegawai', [
            'id' => $pengaturan->id,
            'nama_jadwal' => 'Jadwal Tendik Selasa',
            'cakupan' => 'jenis_pegawai',
            'jenis_pegawai' => 'Tenaga Kependidikan',
            'pegawai_id' => null,
            'jam_masuk' => '06:45',
            'aktif' => false,
            'keterangan' => 'Diperbarui dari Android',
        ]);
    }

    public function test_api_menolak_sasaran_ganda_dan_urutan_waktu_tidak_valid(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);
        $this->pengaturan('Jadwal Guru Rabu', 'rabu', 'jenis_pegawai', jenisPegawai: 'Guru');

        $this->withToken($token)
            ->postJson(route('api.v1.pengaturan-presensi-pegawai.store'), $this->dataJadwal([
                'nama_jadwal' => 'Duplikat Guru Rabu',
                'cakupan' => 'jenis_pegawai',
                'jenis_pegawai' => 'Guru',
                'hari' => 'rabu',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hari');

        $this->withToken($token)
            ->postJson(route('api.v1.pengaturan-presensi-pegawai.store'), $this->dataJadwal([
                'nama_jadwal' => 'Urutan Salah',
                'hari' => 'kamis',
                'jam_scan_masuk_mulai' => '07:15',
                'jam_masuk' => '07:00',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jam_masuk');
    }

    private function pegawai(string $nama, string $jenis): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => fake()->unique()->numerify('##################'),
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => $jenis,
            'aktif' => true,
        ]);
    }

    private function pengaturan(
        string $nama,
        string $hari,
        string $cakupan,
        bool $aktif = true,
        ?string $jenisPegawai = null,
        ?Pegawai $pegawai = null,
    ): PengaturanAbsensiPegawai {
        return PengaturanAbsensiPegawai::create([
            ...$this->dataJadwal([
                'nama_jadwal' => $nama,
                'hari' => $hari,
                'cakupan' => $cakupan,
                'jenis_pegawai' => $jenisPegawai,
                'pegawai_id' => $pegawai?->id,
                'aktif' => $aktif,
            ]),
            'urutan_hari' => PengaturanAbsensiPegawai::DAFTAR_HARI[$hari]['urutan'],
        ]);
    }

    private function dataJadwal(array $override = []): array
    {
        return array_replace([
            'nama_jadwal' => 'Jadwal Pegawai Reguler',
            'cakupan' => 'semua',
            'jenis_pegawai' => null,
            'pegawai_id' => null,
            'hari' => 'senin',
            'jam_scan_masuk_mulai' => '06:00',
            'jam_masuk' => '07:00',
            'jam_scan_masuk_selesai' => '07:30',
            'jam_scan_pulang_mulai' => '14:00',
            'jam_pulang' => '14:10',
            'jam_scan_pulang_selesai' => '15:00',
            'aktif' => true,
            'keterangan' => 'Jadwal pengujian',
        ], $override);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
