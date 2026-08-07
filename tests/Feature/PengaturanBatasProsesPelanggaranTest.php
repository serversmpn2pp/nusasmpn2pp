<?php

namespace Tests\Feature;

use App\Models\JenisPelanggaranSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanBatasProsesPelanggaranTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_administrator_dapat_mengatur_batas_dan_laporan_menyimpan_snapshot(): void
    {
        CarbonImmutable::setTestNow('2026-07-22 08:00:00');
        [$administrator, $tahun, $siswa, $jenis] = $this->dataDasar();

        $this->actingAs($administrator)
            ->put(route('pengaturan-batas-proses-pelanggaran.update', $tahun), $this->dataPengaturan(4))
            ->assertRedirect(route('pengaturan-batas-proses-pelanggaran.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pengaturan_batas_proses_pelanggaran', [
            'tahun_pelajaran_id' => $tahun->id,
            'batas_hari_pemeriksaan_bk' => 4,
        ]);
        $this->get(route('pengaturan-batas-proses-pelanggaran.index'))
            ->assertOk()
            ->assertSee('2026/2027')
            ->assertSee('Pemeriksaan BK 4 hari')
            ->assertSee('Pengesahan Wakil 2 hari');
        $this->get(route('pengaturan-batas-proses-pelanggaran.edit', $tahun))
            ->assertOk()
            ->assertSee('Tahun pelajaran 2026/2027');

        $this->post(route('laporan-pembinaan-siswa.store'), $this->dataLaporan($tahun, $siswa, $jenis))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $laporan = $siswa->laporanPembinaanSiswa()->latest('id')->firstOrFail();
        $this->assertSame('pemeriksaan_bk', $laporan->tahap_batas_proses);
        $this->assertSame('2026-07-26 08:00:00', $laporan->batas_proses_pada->format('Y-m-d H:i:s'));

        $this->put(route('pengaturan-batas-proses-pelanggaran.update', $tahun), $this->dataPengaturan(7))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-07-26 08:00:00', $laporan->fresh()->batas_proses_pada->format('Y-m-d H:i:s'));
    }

    public function test_rekomendasi_bk_memulai_tenggat_pengesahan_wakil(): void
    {
        CarbonImmutable::setTestNow('2026-07-22 08:00:00');
        [$administrator, $tahun, $siswa, $jenis] = $this->dataDasar();
        $this->actingAs($administrator)
            ->put(route('pengaturan-batas-proses-pelanggaran.update', $tahun), $this->dataPengaturan(2));
        $this->post(route('laporan-pembinaan-siswa.store'), $this->dataLaporan($tahun, $siswa, $jenis));
        $laporan = $siswa->laporanPembinaanSiswa()->latest('id')->firstOrFail();

        CarbonImmutable::setTestNow('2026-07-23 10:30:00');
        $this->post(route('verifikasi-pelanggaran.bk', $laporan), [
            'hasil' => 'sanksi_poin',
            'jenis_pelanggaran_ids' => [$jenis->id],
            'catatan' => 'Fakta dan klarifikasi telah lengkap.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $laporan->refresh();
        $this->assertSame('menunggu_pengesahan_wakil', $laporan->status_verifikasi);
        $this->assertSame('pengesahan_wakil', $laporan->tahap_batas_proses);
        $this->assertSame('2026-07-25 10:30:00', $laporan->batas_proses_pada->format('Y-m-d H:i:s'));
    }

    public function test_pengguna_tanpa_izin_tidak_dapat_membuka_pengaturan(): void
    {
        [, $tahun] = $this->dataDasar();
        $pegawai = Pegawai::create(['nama_lengkap' => 'Pegawai Tanpa Izin', 'nip' => '199001012020011001', 'aktif' => true]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);

        $this->actingAs($pengguna)
            ->get(route('pengaturan-batas-proses-pelanggaran.edit', $tahun))
            ->assertForbidden();
    }

    public function test_scheduler_mengirim_satu_pengingat_kepada_bk(): void
    {
        CarbonImmutable::setTestNow('2026-07-22 06:00:00');
        [$administrator, $tahun, $siswa, $jenis] = $this->dataDasar();
        $this->actingAs($administrator)
            ->put(route('pengaturan-batas-proses-pelanggaran.update', $tahun), $this->dataPengaturan(2));
        $this->post(route('laporan-pembinaan-siswa.store'), $this->dataLaporan($tahun, $siswa, $jenis));
        $laporan = $siswa->laporanPembinaanSiswa()->latest('id')->firstOrFail();
        $laporan->update(['batas_proses_pada' => now()->addDay()]);

        $pegawaiBk = Pegawai::create(['nama_lengkap' => 'BK Pengingat', 'nip' => '198801012018011001', 'aktif' => true]);
        $akunBk = Pengguna::create([
            'pegawai_id' => $pegawaiBk->id,
            'nama' => $pegawaiBk->nama_lengkap,
            'username' => $pegawaiBk->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $akunBk->daftarPeran()->attach(Peran::where('kode', 'bk')->firstOrFail());

        $this->artisan('pembinaan:ingatkan-batas-proses')->assertSuccessful();
        $this->artisan('pembinaan:ingatkan-batas-proses')->assertSuccessful();

        $this->assertDatabaseCount('notifikasi_pengguna', 2);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $akunBk->id,
            'judul' => 'Batas proses pelanggaran segera tiba',
        ]);
    }

    private function dataDasar(): array
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Batas Proses', 'nisn' => '0077665501', 'aktif' => true]);
        $jenis = JenisPelanggaranSiswa::where('aktif', true)->firstOrFail();

        return [$administrator, $tahun, $siswa, $jenis];
    }

    private function dataPengaturan(int $bk): array
    {
        return [
            'batas_hari_pemeriksaan_bk' => $bk,
            'batas_hari_persetujuan' => 2,
            'pengingat_hari_sebelum_batas' => 1,
            'notifikasi_pengingat_aktif' => '1',
            'notifikasi_terlambat_aktif' => '1',
        ];
    }

    private function dataLaporan(TahunPelajaran $tahun, Siswa $siswa, JenisPelanggaranSiswa $jenis): array
    {
        return [
            'jenis_laporan' => 'pelanggaran',
            'tanggal_kejadian' => now()->toDateString(),
            'siswa_id' => $siswa->id,
            'jenis_pelanggaran_ids' => [$jenis->id],
            'tahun_pelajaran_id' => $tahun->id,
            'kronologi' => 'Kronologi pengujian batas proses pelanggaran.',
        ];
    }
}
