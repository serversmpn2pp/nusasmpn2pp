<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruPiketApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_administrator_dapat_mengelola_jadwal_piket_native(): void
    {
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $this->withToken($token)->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'jadwal-guru-piket',
                'status' => 'tersedia',
                'rute' => '/jadwal-guru-piket',
            ]);

        $this->withToken($token)->postJson(route('api.v1.jadwal-guru-piket.store'), [
            'tahun_pelajaran_id' => $data['tahun']->id,
            'hari' => 'kamis',
            'pegawai_ids' => [$data['pegawai']->id],
            'aktif' => true,
            'keterangan' => 'Tim piket Kamis',
        ])->assertCreated();

        $jadwal = JadwalPiketGuru::firstOrFail();
        $this->withToken($token)->getJson(route('api.v1.jadwal-guru-piket.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.jadwal_aktif', 1)
            ->assertJsonPath('data.items.0.pegawai.nama', 'Guru Piket Mobile')
            ->assertJsonPath('data.items.0.hari_label', 'Kamis');

        $this->withToken($token)->patchJson(route('api.v1.jadwal-guru-piket.update', $jadwal), [
            'tahun_pelajaran_id' => $data['tahun']->id,
            'hari' => 'jumat',
            'pegawai_id' => $data['pegawai']->id,
            'aktif' => false,
            'keterangan' => 'Dipindahkan',
        ])->assertOk();

        $this->assertDatabaseHas('jadwal_piket_guru', ['id' => $jadwal->id, 'hari' => 'jumat', 'aktif' => false]);
    }

    public function test_guru_piket_melihat_siswa_dan_mencatat_sakit_dari_mobile(): void
    {
        Carbon::setTestNow('2026-08-13 08:00:00');
        $data = $this->dataDasar();
        JadwalPiketGuru::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'pegawai_id' => $data['pegawai']->id,
            'hari' => 'kamis',
            'aktif' => true,
        ]);
        $token = $this->token($data['akun']);

        $this->withToken($token)->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'piket-saya',
                'status' => 'tersedia',
                'rute' => '/piket-saya',
            ]);

        $this->withToken($token)->getJson(route('api.v1.piket-saya.index'))
            ->assertOk()
            ->assertJsonPath('data.dapat_mencatat_hari_ini', true)
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.items.0.siswa.nama', 'Siswa Piket Mobile')
            ->assertJsonPath('data.items.0.presensi.status', 'belum_scan');

        $this->withToken($token)->patchJson(route('api.v1.piket-saya.kehadiran.update', $data['anggota']), [
            'status_kehadiran' => 'sakit',
            'catatan' => 'Orang tua mengabarkan siswa demam.',
        ])->assertOk()->assertJsonPath('data.status', 'sakit');

        $this->assertDatabaseHas('absensi_siswa', [
            'siswa_id' => $data['siswa']->id,
            'status_kehadiran' => 'sakit',
            'sumber' => 'guru_piket',
        ]);
        $this->assertDatabaseHas('riwayat_perubahan_absensi_siswa', [
            'siswa_id' => $data['siswa']->id,
            'status_sesudah' => 'sakit',
            'dibuat_oleh_pengguna_id' => $data['akun']->id,
        ]);
    }

    public function test_di_luar_jadwal_daftar_tetap_informatif_tetapi_pencatatan_ditolak(): void
    {
        Carbon::setTestNow('2026-08-14 08:00:00');
        $data = $this->dataDasar();
        JadwalPiketGuru::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'pegawai_id' => $data['pegawai']->id,
            'hari' => 'kamis',
            'aktif' => true,
        ]);
        $token = $this->token($data['akun']);

        $this->withToken($token)->getJson(route('api.v1.piket-saya.index'))
            ->assertOk()
            ->assertJsonPath('data.dapat_mencatat_hari_ini', false)
            ->assertJsonCount(0, 'data.items');

        $this->withToken($token)->patchJson(route('api.v1.piket-saya.kehadiran.update', $data['anggota']), [
            'status_kehadiran' => 'izin',
            'catatan' => 'Izin dari orang tua.',
        ])->assertForbidden();
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A Mobile', 'tingkat' => 7, 'aktif' => true,
        ]);
        $mataPelajaran = MataPelajaran::create(['kode' => 'MTK7M', 'nama' => 'Matematika', 'kelompok' => 'Umum', 'aktif' => true]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Piket Mobile', 'nip' => '197901012009011009', 'jenis_pegawai' => 'Guru', 'aktif' => true,
        ]);
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $pegawai->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap, 'username' => $pegawai->nip,
            'kata_sandi' => 'KataSandi-Uji-2026', 'wajib_ganti_kata_sandi' => false, 'peran' => 'pegawai', 'aktif' => true, 'akun_sistem' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::whereIn('kode', ['pegawai', 'guru_mapel'])->pluck('id'));
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Piket Mobile', 'nis' => '26009', 'nisn' => '0123456709', 'aktif' => true]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id,
            'nomor_absen' => 1, 'status_keanggotaan' => 'aktif',
        ]);

        return compact('tahun', 'kelas', 'pegawai', 'akun', 'siswa', 'anggota');
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
