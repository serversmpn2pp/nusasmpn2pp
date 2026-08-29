<?php

namespace Tests\Feature\Api;

use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanPresensiPegawaiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_melihat_laporan_bulanan_dan_rincian_harian(): void
    {
        $jadwal = $this->jadwal();
        $guru = $this->pegawai('Guru Laporan Mobile', '198601012026081201');
        $this->pegawai('Guru Alfa Mobile', '198601012026081202');
        AbsensiPegawai::create([
            'tanggal' => '2026-08-27',
            'pegawai_id' => $guru->id,
            'pengaturan_absensi_pegawai_id' => $jadwal->id,
            'jam_masuk' => '07:10',
            'status_masuk' => 'terlambat',
            'menit_terlambat' => 10,
            'jam_pulang' => '14:00',
            'status_pulang' => 'normal',
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);
        AbsensiPegawai::create([
            'tanggal' => '2026-08-28',
            'pegawai_id' => $guru->id,
            'status_kehadiran' => 'izin',
            'sumber' => 'manual',
            'catatan' => 'Izin kegiatan keluarga.',
        ]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $this->withToken($token)->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'laporan-presensi-pegawai',
                'status' => 'tersedia',
                'rute' => '/laporan-presensi-pegawai',
            ]);

        $this->withToken($token)->getJson(route('api.v1.laporan-presensi-pegawai.index', [
            'bulan' => '2026-08',
            'jenis_pegawai' => 'Guru',
        ]))
            ->assertOk()
            ->assertJsonPath('data.periode.label', 'Agustus 2026')
            ->assertJsonPath('data.ringkasan.pegawai', 2)
            ->assertJsonPath('data.ringkasan.hari_efektif', 9)
            ->assertJsonPath('data.ringkasan.hadir', 1)
            ->assertJsonPath('data.ringkasan.izin', 1)
            ->assertJsonPath('data.ringkasan.alfa', 7)
            ->assertJsonPath('data.ringkasan.terlambat', 1)
            ->assertJsonPath('data.ringkasan.manual', 1)
            ->assertJsonPath('data.hak_akses.cakupan_pribadi', false)
            ->assertJsonFragment(['persentase_hadir' => 20]);

        $this->withToken($token)->getJson(route('api.v1.laporan-presensi-pegawai.show', [
            'pegawai' => $guru,
            'bulan' => '2026-08',
        ]))
            ->assertOk()
            ->assertJsonPath('data.pegawai.nama', 'Guru Laporan Mobile')
            ->assertJsonPath('data.ringkasan.hari_efektif', 5)
            ->assertJsonCount(5, 'data.rincian')
            ->assertJsonPath('data.rincian.4.tanggal', '2026-08-28')
            ->assertJsonPath('data.rincian.4.jadwal', null)
            ->assertJsonPath('data.rincian.4.status', 'izin')
            ->assertJsonPath('data.rincian.4.keterangan', 'Izin kegiatan keluarga.');
    }

    public function test_pegawai_hanya_melihat_laporan_milik_sendiri(): void
    {
        $sendiri = $this->pegawai('Pegawai Laporan Pribadi', '198601012026081203');
        $lain = $this->pegawai('Pegawai Laporan Lain', '198601012026081204');
        $pengguna = Pengguna::create([
            'pegawai_id' => $sendiri->id,
            'nama' => $sendiri->nama_lengkap,
            'username' => 'pegawai.laporan.pribadi',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'pegawai')->firstOrFail());
        $token = $this->token($pengguna);

        $this->withToken($token)->getJson(route('api.v1.laporan-presensi-pegawai.index', [
            'bulan' => '2026-08',
            'pegawai_id' => $lain->id,
            'status_pegawai' => 'nonaktif',
        ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.pegawai', 1)
            ->assertJsonPath('data.items.0.id', $sendiri->id)
            ->assertJsonPath('data.hak_akses.cakupan_pribadi', true)
            ->assertJsonCount(0, 'data.pegawai');

        $this->withToken($token)->getJson(route('api.v1.laporan-presensi-pegawai.show', [
            'pegawai' => $lain,
            'bulan' => '2026-08',
        ]))->assertForbidden();
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
            'nama_jadwal' => 'Jadwal Kamis Guru',
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
        return $pengguna->createToken('Laporan Pegawai Mobile', ['mobile'])->plainTextToken;
    }
}
