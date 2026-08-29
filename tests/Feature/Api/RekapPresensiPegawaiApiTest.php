<?php

namespace Tests\Feature\Api;

use App\Models\AbsensiPegawai;
use App\Models\Izin;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapPresensiPegawaiApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_administrator_melihat_rekap_detail_dan_mengoreksi_presensi_pegawai(): void
    {
        Carbon::setTestNow('2026-08-27 09:00:00');
        $jadwal = $this->jadwal();
        $guru = $this->pegawai('Guru Hadir Mobile', '198601012026081101');
        $belum = $this->pegawai('Guru Belum Tercatat', '198601012026081102');
        AbsensiPegawai::create([
            'tanggal' => '2026-08-27',
            'pegawai_id' => $guru->id,
            'pengaturan_absensi_pegawai_id' => $jadwal->id,
            'jam_masuk' => '07:10',
            'status_masuk' => 'terlambat',
            'menit_terlambat' => 10,
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $this->withToken($token)->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'rekap-presensi-pegawai',
                'status' => 'tersedia',
                'rute' => '/rekap-presensi-pegawai',
            ]);

        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-pegawai.index', [
            'tanggal' => '2026-08-27',
            'jenis_pegawai' => 'Guru',
        ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.hadir', 1)
            ->assertJsonPath('data.ringkasan.alfa', 1)
            ->assertJsonPath('data.ringkasan.terlambat', 1)
            ->assertJsonPath('data.hak_akses.cakupan_pribadi', false)
            ->assertJsonPath('data.hak_akses.dapat_koreksi', true)
            ->assertJsonFragment(['sumber_label' => 'Mesin scanner'])
            ->assertJsonFragment(['sumber_label' => 'Inferensi belum tercatat']);

        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-pegawai.show', [
            'pegawai' => $guru,
            'tanggal' => '2026-08-27',
        ]))
            ->assertOk()
            ->assertJsonPath('data.jadwal_presensi.nama', 'Jadwal Guru Mobile')
            ->assertJsonPath('data.item.presensi.menit_terlambat', 10);

        $this->withToken($token)->patchJson(route('api.v1.rekap-presensi-pegawai.update', $belum), [
            'tanggal' => '2026-08-27',
            'status_kehadiran' => 'hadir',
            'jam_masuk' => '07:15',
            'jam_pulang' => '13:45',
            'catatan' => 'Disesuaikan dengan catatan petugas.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'hadir')
            ->assertJsonPath('data.menit_terlambat', 15)
            ->assertJsonPath('data.menit_pulang_cepat', 15);

        $this->assertDatabaseHas('absensi_pegawai', [
            'pegawai_id' => $belum->id,
            'sumber' => 'manual',
            'menit_terlambat' => 15,
            'menit_pulang_cepat' => 15,
        ]);
    }

    public function test_pegawai_hanya_melihat_rekap_pribadi_dan_tidak_dapat_mengoreksi(): void
    {
        Carbon::setTestNow('2026-08-27 09:00:00');
        $sendiri = $this->pegawai('Pegawai Pribadi Mobile', '198601012026081103');
        $lain = $this->pegawai('Pegawai Lain Mobile', '198601012026081104');
        $pengguna = Pengguna::create([
            'pegawai_id' => $sendiri->id,
            'nama' => $sendiri->nama_lengkap,
            'username' => 'pegawai.pribadi.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $peran = Peran::where('kode', 'pegawai')->firstOrFail();
        $pengguna->daftarPeran()->attach($peran);
        $token = $this->token($pengguna);

        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-pegawai.index', [
            'tanggal' => '2026-08-27',
            'pegawai_id' => $lain->id,
            'status' => 'hadir',
        ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.items.0.pegawai.id', $sendiri->id)
            ->assertJsonPath('data.hak_akses.cakupan_pribadi', true)
            ->assertJsonPath('data.hak_akses.dapat_koreksi', false)
            ->assertJsonCount(0, 'data.pegawai');

        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-pegawai.show', [
            'pegawai' => $lain,
            'tanggal' => '2026-08-27',
        ]))->assertForbidden();

        $this->withToken($token)->patchJson(route('api.v1.rekap-presensi-pegawai.update', $sendiri), [
            'tanggal' => '2026-08-27',
            'status_kehadiran' => 'izin',
        ])->assertForbidden();
    }

    public function test_akun_pribadi_tanpa_relasi_pegawai_ditolak(): void
    {
        $peran = Peran::create([
            'nama' => 'Presensi Pegawai Pribadi Mobile',
            'kode' => 'presensi_pegawai_pribadi_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', 'absensi_pegawai.pribadi')->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Akun Tanpa Pegawai',
            'username' => 'tanpa.pegawai.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach($peran);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.rekap-presensi-pegawai.index'))
            ->assertForbidden();
    }

    private function pegawai(string $nama, string $nip): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'Guru',
            'jabatan_utama' => 'Guru Mata Pelajaran',
            'status_kepegawaian' => 'PNS',
            'aktif' => true,
        ]);
    }

    private function jadwal(): PengaturanAbsensiPegawai
    {
        return PengaturanAbsensiPegawai::create([
            'nama_jadwal' => 'Jadwal Guru Mobile',
            'cakupan' => 'jenis_pegawai',
            'jenis_pegawai' => 'Guru',
            'hari' => 'kamis',
            'urutan_hari' => 4,
            'jam_scan_masuk_mulai' => '06:00',
            'jam_masuk' => '07:00',
            'jam_scan_masuk_selesai' => '07:30',
            'jam_scan_pulang_mulai' => '13:30',
            'jam_pulang' => '14:00',
            'jam_scan_pulang_selesai' => '15:00',
            'aktif' => true,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
