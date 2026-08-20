<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanPendampingIbadahSiswi;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanBerhalanganIbadahTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_hanya_menampilkan_guru_perempuan_aktif(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatTahunPelajaranDanKelas();
        $guruPerempuan = $this->buatPegawai('Guru Perempuan Pilihan', 'P', 'Guru');
        $guruLakiLaki = $this->buatPegawai('Guru Laki-laki Tidak Tampil', 'L', 'Guru');
        $tenagaKependidikan = $this->buatPegawai('Pegawai Perempuan Bukan Guru', 'P', 'Tenaga Kependidikan');

        $this->actingAs($administrator)
            ->get(route('pengaturan-berhalangan-ibadah.index'))
            ->assertOk()
            ->assertSee($guruPerempuan->nama_lengkap)
            ->assertDontSee($guruLakiLaki->nama_lengkap)
            ->assertDontSee($tenagaKependidikan->nama_lengkap)
            ->assertSee('Batas konfirmasi')
            ->assertSee('Tambah pendamping');
    }

    public function test_administrator_dapat_menyimpan_batas_dan_pendamping_beberapa_kelas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $kelas] = $this->buatTahunPelajaranDanKelas();
        $guru = $this->buatPegawai('Guru Pendamping Uji', 'P', 'Guru');

        $this->actingAs($administrator)
            ->put(route('pengaturan-berhalangan-ibadah.update'), [
                'batas_hari_konfirmasi' => 6,
                'aktif' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pengaturan_berhalangan_ibadah', [
            'tahun_pelajaran_id' => $tahun->id,
            'batas_hari_konfirmasi' => 6,
            'aktif' => true,
        ]);

        $this->post(route('pengaturan-berhalangan-ibadah.pendamping.store'), [
            'pegawai_id' => $guru->id,
            'semua_kelas' => 0,
            'kelas_ids' => $kelas->pluck('id')->all(),
        ])->assertRedirect(route('pengaturan-berhalangan-ibadah.index'))
            ->assertSessionHasNoErrors();

        $penugasan = PenugasanPendampingIbadahSiswi::query()->firstOrFail();
        $this->assertFalse($penugasan->semua_kelas);
        $this->assertTrue($penugasan->aktif);
        $this->assertEqualsCanonicalizing($kelas->pluck('id')->all(), $penugasan->kelas()->pluck('kelas.id')->all());
    }

    public function test_guru_laki_laki_dan_cakupan_kelas_kosong_ditolak(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatTahunPelajaranDanKelas();
        $guruLakiLaki = $this->buatPegawai('Guru Laki-laki', 'L', 'Guru');
        $guruPerempuan = $this->buatPegawai('Guru Perempuan', 'P', 'Guru');

        $this->actingAs($administrator)
            ->post(route('pengaturan-berhalangan-ibadah.pendamping.store'), [
                'pegawai_id' => $guruLakiLaki->id,
                'semua_kelas' => 1,
            ])
            ->assertSessionHasErrors('pegawai_id');

        $this->post(route('pengaturan-berhalangan-ibadah.pendamping.store'), [
            'pegawai_id' => $guruPerempuan->id,
            'semua_kelas' => 0,
            'kelas_ids' => [],
        ])->assertSessionHasErrors('kelas_ids');

        $this->assertDatabaseCount('penugasan_pendamping_ibadah_siswi', 0);
    }

    public function test_penugasan_dinonaktifkan_tanpa_menghapus_riwayat_cakupan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [, $kelas] = $this->buatTahunPelajaranDanKelas();
        $guru = $this->buatPegawai('Guru Pendamping Riwayat', 'P', 'Guru');

        $this->actingAs($administrator)->post(route('pengaturan-berhalangan-ibadah.pendamping.store'), [
            'pegawai_id' => $guru->id,
            'semua_kelas' => 0,
            'kelas_ids' => [$kelas->first()->id],
        ]);

        $penugasan = PenugasanPendampingIbadahSiswi::query()->firstOrFail();

        $this->delete(route('pengaturan-berhalangan-ibadah.pendamping.destroy', $penugasan))
            ->assertRedirect(route('pengaturan-berhalangan-ibadah.index'));

        $this->assertDatabaseHas('penugasan_pendamping_ibadah_siswi', [
            'id' => $penugasan->id,
            'aktif' => false,
        ]);
        $this->assertDatabaseHas('kelas_pendamping_ibadah_siswi', [
            'penugasan_pendamping_ibadah_siswi_id' => $penugasan->id,
            'kelas_id' => $kelas->first()->id,
        ]);
    }

    private function buatTahunPelajaranDanKelas(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);

        $kelas = collect([
            Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A', 'tingkat' => 7, 'kapasitas' => 32, 'aktif' => true]),
            Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.A', 'tingkat' => 8, 'kapasitas' => 32, 'aktif' => true]),
        ]);

        return [$tahun, $kelas];
    }

    private function buatPegawai(string $nama, string $jenisKelamin, string $jenisPegawai): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => (string) random_int(100000000000000000, 999999999999999999),
            'jenis_kelamin' => $jenisKelamin,
            'jenis_pegawai' => $jenisPegawai,
            'aktif' => true,
        ]);
    }
}
