<?php

namespace Tests\Feature;

use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KegiatanIbadahTest extends TestCase
{
    use RefreshDatabase;

    public function test_kegiatan_duhur_dan_hak_akses_pengelola_tersedia(): void
    {
        $this->assertDatabaseHas('kegiatan_ibadah', [
            'kode' => 'sholat_duhur',
            'nama' => 'Sholat Duhur Berjamaah',
            'aktif' => true,
        ]);
        $this->assertTrue(Peran::where('kode', 'administrator')->firstOrFail()->memilikiIzin('ibadah.pengaturan_kelola'));
        $this->assertTrue(Peran::where('kode', 'wakil_pimpinan_kesiswaan')->firstOrFail()->memilikiIzin('ibadah.pengaturan_kelola'));
        $this->assertFalse(Peran::where('kode', 'guru_mapel')->firstOrFail()->memilikiIzin('ibadah.pengaturan_kelola'));
    }

    public function test_administrator_dapat_mengelola_kegiatan_ibadah(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('kegiatan-ibadah.index'))
            ->assertOk()
            ->assertSee('Sholat Duhur Berjamaah');

        $this->post(route('kegiatan-ibadah.store'), [
            'kode' => 'Tadarus Pagi',
            'nama' => 'Tadarus Pagi Bersama',
            'aktif' => 1,
            'keterangan' => 'Kegiatan membaca Al-Quran sebelum pelajaran.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $kegiatan = KegiatanIbadah::where('kode', 'tadarus_pagi')->firstOrFail();
        $this->assertSame('Tadarus Pagi Bersama', $kegiatan->nama);

        $this->put(route('kegiatan-ibadah.update', $kegiatan), [
            'kode' => 'tadarus_pagi',
            'nama' => 'Tadarus Pagi',
            'aktif' => 1,
            'keterangan' => 'Kegiatan rutin sebelum pelajaran.',
        ])->assertRedirect(route('kegiatan-ibadah.show', $kegiatan))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kegiatan_ibadah', [
            'id' => $kegiatan->id,
            'nama' => 'Tadarus Pagi',
        ]);
    }

    public function test_jadwal_duhur_dapat_diterapkan_ke_beberapa_hari_dan_diperbarui(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahunPelajaran();
        $duhur = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('jadwal-kegiatan-ibadah.store'), [
                'kegiatan_ibadah_id' => $duhur->id,
                'tahun_pelajaran_id' => $tahun->id,
                'hari' => ['senin', 'selasa', 'rabu', 'kamis'],
                'jam_scan_mulai' => '11:45',
                'jam_pelaksanaan' => '12:15',
                'jam_scan_selesai' => '13:15',
                'aktif' => 1,
                'keterangan' => 'Mushalla sekolah',
            ])->assertRedirect(route('jadwal-kegiatan-ibadah.index', [
                'tahun_pelajaran_id' => $tahun->id,
                'kegiatan_ibadah_id' => $duhur->id,
            ]))->assertSessionHasNoErrors();

        $this->assertSame(4, JadwalKegiatanIbadah::where('kegiatan_ibadah_id', $duhur->id)->count());
        $this->assertDatabaseHas('jadwal_kegiatan_ibadah', [
            'hari' => 'senin',
            'jam_pelaksanaan' => '12:15',
            'aktif' => true,
        ]);

        $this->post(route('jadwal-kegiatan-ibadah.store'), [
            'kegiatan_ibadah_id' => $duhur->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => ['senin'],
            'jam_scan_mulai' => '11:50',
            'jam_pelaksanaan' => '12:20',
            'jam_scan_selesai' => '13:10',
            'aktif' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, JadwalKegiatanIbadah::where('kegiatan_ibadah_id', $duhur->id)->count());
        $this->assertDatabaseHas('jadwal_kegiatan_ibadah', [
            'hari' => 'senin',
            'jam_scan_mulai' => '11:50',
            'jam_pelaksanaan' => '12:20',
        ]);
    }

    public function test_waktu_pelaksanaan_harus_berada_dalam_jendela_scan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahunPelajaran();
        $duhur = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('jadwal-kegiatan-ibadah.store'), [
                'kegiatan_ibadah_id' => $duhur->id,
                'tahun_pelajaran_id' => $tahun->id,
                'hari' => ['senin'],
                'jam_scan_mulai' => '12:30',
                'jam_pelaksanaan' => '12:15',
                'jam_scan_selesai' => '13:00',
                'aktif' => 1,
            ])->assertSessionHasErrors('jam_pelaksanaan');

        $this->assertDatabaseMissing('jadwal_kegiatan_ibadah', [
            'kegiatan_ibadah_id' => $duhur->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => 'senin',
        ]);
    }

    public function test_wakil_kesiswaan_dapat_membuka_pengaturan_ibadah(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Wakil Kesiswaan Uji',
            'nip' => '197801012008011001',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::whereIn('kode', ['pegawai', 'wakil_pimpinan_kesiswaan'])->pluck('id'));

        $this->actingAs($akun)->get(route('kegiatan-ibadah.index'))->assertOk();
        $this->get(route('jadwal-kegiatan-ibadah.index'))->assertOk();
    }

    private function buatTahunPelajaran(): TahunPelajaran
    {
        return TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
    }
}
