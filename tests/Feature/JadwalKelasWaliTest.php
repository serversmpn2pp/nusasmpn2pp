<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalKelasWaliTest extends TestCase
{
    use RefreshDatabase;

    public function test_wali_kelas_hanya_melihat_jadwal_kelas_yang_diwalinya(): void
    {
        $data = $this->dataDasar();

        $this->actingAs($data['wali'])
            ->get(route('jadwal-pelajaran.index'))
            ->assertOk()
            ->assertSee('Jadwal Kelas Saya')
            ->assertSee('Hanya menampilkan jadwal kelas yang Anda wali.')
            ->assertSee($data['kelas_wali']->nama)
            ->assertSee($data['mapel_wali']->nama)
            ->assertDontSee($data['kelas_lain']->nama)
            ->assertDontSee($data['mapel_lain']->nama)
            ->assertDontSee('href="'.route('jam-pelajaran.index').'"', false);
    }

    public function test_wali_kelas_tidak_dapat_membuka_detail_jadwal_kelas_lain(): void
    {
        $data = $this->dataDasar();

        $this->actingAs($data['wali'])
            ->get(route('jadwal-pelajaran.show', $data['jadwal_wali']))
            ->assertOk();

        $this->actingAs($data['wali'])
            ->get(route('jadwal-pelajaran.show', $data['jadwal_lain']))
            ->assertForbidden();
    }

    public function test_jam_pelajaran_hanya_dapat_diakses_administrator(): void
    {
        $data = $this->dataDasar();

        $this->actingAs($data['wali'])
            ->get(route('jam-pelajaran.index'))
            ->assertForbidden();

        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('jam-pelajaran.index'))
            ->assertOk();
    }

    private function dataDasar(): array
    {
        $pegawaiWali = Pegawai::create([
            'nama_lengkap' => 'Wali Kelas Pengujian',
            'nip' => '198001012010011001',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $wali = Pengguna::create([
            'pegawai_id' => $pegawaiWali->id,
            'nama' => $pegawaiWali->nama_lengkap,
            'username' => 'wali-kelas-pengujian',
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $wali->daftarPeran()->attach(Peran::where('kode', 'wali_kelas')->firstOrFail());

        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelasWali = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_id' => $pegawaiWali->id,
            'nama' => 'VII.WALI',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.LAIN',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $mapelWali = MataPelajaran::create([
            'nama' => 'Mapel Kelas Wali',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);
        $mapelLain = MataPelajaran::create([
            'nama' => 'Mapel Kelas Lain',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);
        $jam = JamPelajaran::create([
            'hari' => 'senin',
            'nomor_jam' => 1,
            'label' => 'Jam 1',
            'jam_mulai' => '06:40',
            'jam_selesai' => '07:20',
            'jenis' => 'pelajaran',
            'aktif' => true,
        ]);
        $jadwalWali = JadwalPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasWali->id,
            'hari' => 'senin',
            'jam_pelajaran_id' => $jam->id,
            'mata_pelajaran_id' => $mapelWali->id,
            'aktif' => true,
        ]);
        $jadwalLain = JadwalPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasLain->id,
            'hari' => 'senin',
            'jam_pelajaran_id' => $jam->id,
            'mata_pelajaran_id' => $mapelLain->id,
            'aktif' => true,
        ]);

        return [
            'wali' => $wali,
            'kelas_wali' => $kelasWali,
            'kelas_lain' => $kelasLain,
            'mapel_wali' => $mapelWali,
            'mapel_lain' => $mapelLain,
            'jadwal_wali' => $jadwalWali,
            'jadwal_lain' => $jadwalLain,
        ];
    }
}
