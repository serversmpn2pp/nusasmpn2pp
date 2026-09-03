<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruBkTingkat;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenugasanGuruBkTingkatTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_mengelola_penugasan_tingkat_dan_guru_bk_tidak_boleh_mengelolanya(): void
    {
        $tahun = $this->buatTahun();
        [, $bk] = $this->buatAkunPegawai('Guru BK Tingkat VII', '197701012007011001', 'bk');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('penugasan-guru-bk-tingkat.index'))
            ->assertOk()
            ->assertSee('Penugasan Tingkat Guru BK')
            ->assertSee('Tetap dapat melihat semua laporan');

        $this->post(route('penugasan-guru-bk-tingkat.store'), [
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $bk->pegawai_id,
            'tingkat' => [7, 8],
        ])->assertRedirect(route('penugasan-guru-bk-tingkat.index', ['tahun_pelajaran_id' => $tahun->id]));

        $this->assertDatabaseHas('penugasan_guru_bk_tingkat', [
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $bk->pegawai_id,
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $this->assertDatabaseHas('penugasan_guru_bk_tingkat', [
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $bk->pegawai_id,
            'tingkat' => 8,
            'aktif' => true,
        ]);

        $this->actingAs($bk)
            ->get(route('penugasan-guru-bk-tingkat.index'))
            ->assertForbidden();
    }

    public function test_guru_bk_melihat_semua_laporan_tetapi_hanya_memproses_tingkat_tugasnya(): void
    {
        $tahun = $this->buatTahun();
        $kelas7 = $this->buatKelas($tahun, 'VII.A', 7);
        $kelas8 = $this->buatKelas($tahun, 'VIII.A', 8);
        $siswa7 = Siswa::create(['nama_lengkap' => 'Siswa Tingkat Tujuh', 'nisn' => '0077000001', 'aktif' => true]);
        $siswa8 = Siswa::create(['nama_lengkap' => 'Siswa Tingkat Delapan', 'nisn' => '0088000001', 'aktif' => true]);
        [, $bk7] = $this->buatAkunPegawai('Guru BK Tujuh', '197702022007021002', 'bk');
        [, $bk8] = $this->buatAkunPegawai('Guru BK Delapan', '197703032007031003', 'bk');
        $this->tugaskan($tahun, $bk7, 7);
        $this->tugaskan($tahun, $bk8, 8);
        $laporan7 = $this->buatLaporan($tahun, $kelas7, $siswa7, 'BK-T7-001');
        $laporan8 = $this->buatLaporan($tahun, $kelas8, $siswa8, 'BK-T8-001');

        $this->actingAs($bk7)
            ->get(route('pusat-verifikasi-pelanggaran.index'))
            ->assertOk()
            ->assertSee($laporan7->nomor_laporan)
            ->assertSee($laporan8->nomor_laporan)
            ->assertSee('Ditangani Guru BK tingkat lain');

        $this->get(route('laporan-pembinaan-siswa.show', $laporan8))
            ->assertOk()
            ->assertSee('Mode lihat saja')
            ->assertDontSee('name="hasil"', false);

        $this->post(route('verifikasi-pelanggaran.bk', $laporan8), [
            'hasil' => 'pembinaan',
            'catatan' => 'Percobaan lintas tingkat.',
        ])->assertForbidden();
        $this->assertSame('diajukan', $laporan8->fresh()->status_verifikasi);

        $this->post(route('verifikasi-pelanggaran.bk', $laporan7), [
            'hasil' => 'pembinaan',
            'catatan' => 'Ditangani Guru BK tingkat tujuh.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('ditetapkan_pembinaan', $laporan7->fresh()->status_verifikasi);

        $laporan8Api = $this->buatLaporan($tahun, $kelas8, $siswa8, 'BK-T8-API');
        $token = $bk7->createToken('Uji pembatasan BK', ['mobile'])->plainTextToken;
        $this->withToken($token)
            ->getJson(route('api.v1.pemeriksaan-pengesahan.show', $laporan8Api))
            ->assertOk()
            ->assertJsonPath('data.hak_aksi.dapat_verifikasi_bk', false)
            ->assertJsonPath('data.hak_aksi.mode_baca_bk', true);
        $this->withToken($token)
            ->postJson(route('api.v1.pemeriksaan-pengesahan.verifikasi-bk', $laporan8Api), [
                'hasil' => 'pembinaan',
            ])
            ->assertForbidden();
    }

    public function test_notifikasi_laporan_baru_hanya_dikirim_ke_guru_bk_tingkat_siswa(): void
    {
        $tahun = $this->buatTahun();
        $kelas7 = $this->buatKelas($tahun, 'VII.B', 7);
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Laporan Tingkat Tujuh', 'nisn' => '0077000002', 'aktif' => true]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas7->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
        ]);
        [, $bk7] = $this->buatAkunPegawai('Penerima BK Tujuh', '197704042007041004', 'bk');
        [, $bk8] = $this->buatAkunPegawai('Penerima BK Delapan', '197705052007051005', 'bk');
        [, $pelapor] = $this->buatAkunPegawai('Pegawai Pelapor', '197706062007061006', 'pegawai');
        $this->tugaskan($tahun, $bk7, 7);
        $this->tugaskan($tahun, $bk8, 8);

        $this->actingAs($pelapor)
            ->post(route('laporan-pembinaan-siswa.store'), [
                'tanggal_kejadian' => now()->toDateString(),
                'tempat_kejadian' => 'Ruang kelas',
                'siswa_id' => $siswa->id,
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas7->id,
                'kronologi' => 'Kejadian dicatat untuk menguji pembagian notifikasi BK per tingkat.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $laporan = LaporanPembinaanSiswa::latest('id')->firstOrFail();
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $bk7->id,
            'kunci_unik' => "laporan-pembinaan-baru:{$laporan->id}",
        ]);
        $this->assertDatabaseMissing('notifikasi_pengguna', [
            'pengguna_id' => $bk8->id,
            'kunci_unik' => "laporan-pembinaan-baru:{$laporan->id}",
        ]);
    }

    private function buatTahun(): TahunPelajaran
    {
        return TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
    }

    private function buatKelas(TahunPelajaran $tahun, string $nama, int $tingkat): Kelas
    {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => $nama,
            'tingkat' => $tingkat,
            'aktif' => true,
        ]);
    }

    private function buatAkunPegawai(string $nama, string $nip, string $kodePeran): array
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'nip' => $nip, 'aktif' => true]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', $kodePeran)->firstOrFail());

        return [$pegawai, $pengguna];
    }

    private function tugaskan(TahunPelajaran $tahun, Pengguna $bk, int $tingkat): void
    {
        PenugasanGuruBkTingkat::create([
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $bk->pegawai_id,
            'tingkat' => $tingkat,
            'tanggal_mulai' => now()->toDateString(),
            'aktif' => true,
        ]);
    }

    private function buatLaporan(
        TahunPelajaran $tahun,
        Kelas $kelas,
        Siswa $siswa,
        string $nomor,
    ): LaporanPembinaanSiswa {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        return LaporanPembinaanSiswa::create([
            'nomor_laporan' => $nomor,
            'jenis_laporan' => 'kejadian',
            'sumber_laporan' => 'manual',
            'tanggal_kejadian' => now()->toDateString(),
            'tempat_kejadian' => 'Lingkungan sekolah',
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'tingkat' => 'ringan',
            'status' => 'baru',
            'status_verifikasi' => 'diajukan',
            'total_poin' => 0,
            'kronologi' => 'Kronologi pengujian penugasan Guru BK per tingkat.',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
    }
}
