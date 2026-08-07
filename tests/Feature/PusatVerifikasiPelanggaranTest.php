<?php

namespace Tests\Feature;

use App\Models\ButirPelanggaranLaporan;
use App\Models\JenisPelanggaranSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PusatVerifikasiPelanggaranTest extends TestCase
{
    use RefreshDatabase;

    public function test_bk_melihat_seluruh_laporan_yang_menunggu_keputusan(): void
    {
        [$tahun, $siswa, $jenis] = $this->dataDasar();
        [, $akunBk] = $this->buatAkunPegawai('BK Penguji', '198001012010011001', 'bk');
        $laporanBaru = $this->buatLaporan('PB-BK-001', 'diajukan', $tahun, $siswa, $jenis);
        $laporanLama = $this->buatLaporan('PB-LAMA-001', 'menunggu_persetujuan', $tahun, $siswa, $jenis);
        DB::table('laporan_pembinaan_siswa')->where('id', $laporanBaru->id)->update([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $this->actingAs($akunBk)
            ->get(route('pusat-verifikasi-pelanggaran.index'))
            ->assertOk()
            ->assertSee($laporanBaru->nomor_laporan)
            ->assertSee($laporanLama->nomor_laporan)
            ->assertSee('BK memeriksa laporan; Wakil Kesiswaan mengesahkan rekomendasi pelanggaran berpoin.');

        $this->get(route('pusat-verifikasi-pelanggaran.index', ['antrean' => 'terlambat']))
            ->assertOk()
            ->assertSee($laporanBaru->nomor_laporan)
            ->assertSee('3 hari diproses');
    }

    public function test_wali_kelas_tidak_mendapat_antrean_keputusan(): void
    {
        [$tahun, $siswa, $jenis] = $this->dataDasar();
        [, $akunWali] = $this->buatAkunPegawai('Wali Kelas Penguji', '198101012011011002', 'wali_kelas');
        $laporan = $this->buatLaporan('PB-WALI-001', 'diajukan', $tahun, $siswa, $jenis);

        $this->actingAs($akunWali)
            ->get(route('pusat-verifikasi-pelanggaran.index'))
            ->assertOk()
            ->assertDontSee($laporan->nomor_laporan)
            ->assertSee('Tidak ada laporan dalam antrean ini.');
    }

    public function test_pimpinan_dapat_memantau_hasil_tanpa_memberi_keputusan(): void
    {
        [$tahun, $siswa, $jenis] = $this->dataDasar();
        [, $akunPimpinan] = $this->buatAkunPegawai('Pimpinan Penguji', '198201012012011003', 'pimpinan');
        $laporan = $this->buatLaporan('PB-PIMPINAN-001', 'diajukan', $tahun, $siswa, $jenis);

        $this->actingAs($akunPimpinan)
            ->get(route('pusat-verifikasi-pelanggaran.index'))
            ->assertOk()
            ->assertSee($laporan->nomor_laporan)
            ->assertDontSee('Simpan keputusan BK');
    }

    public function test_rekomendasi_bk_baru_menetapkan_poin_setelah_disahkan_wakil(): void
    {
        [$tahun, $siswa, $jenis] = $this->dataDasar();
        [, $akunBk] = $this->buatAkunPegawai('BK Pemutus', '198301012013011004', 'bk');
        [, $akunWakil] = $this->buatAkunPegawai('Wakil Kesiswaan', '198401012014011005', 'wakil_pimpinan_kesiswaan');
        $laporan = $this->buatLaporan('PB-PUTUS-001', 'diajukan', $tahun, $siswa, $jenis);

        $this->assertFalse(Route::has('verifikasi-pelanggaran.persetujuan'));

        $this->actingAs($akunBk)->post(route('verifikasi-pelanggaran.bk', $laporan), [
            'hasil' => 'sanksi_poin',
            'catatan' => 'Fakta lengkap dan sanksi poin ditetapkan.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('menunggu_pengesahan_wakil', $laporan->fresh()->status_verifikasi);
        $this->assertDatabaseMissing('transaksi_poin_siswa', [
            'kunci_sumber' => 'pelanggaran:'.$laporan->id,
        ]);

        $this->actingAs($akunWakil)->post(route('verifikasi-pelanggaran.wakil', $laporan), [
            'keputusan' => 'sahkan',
            'catatan' => 'Rekomendasi BK sesuai bukti.',
            'total_poin' => 999,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('disahkan', $laporan->fresh()->status_verifikasi);
        $this->assertSame($jenis->poin, $laporan->fresh()->total_poin);
        $this->assertDatabaseHas('transaksi_poin_siswa', [
            'kunci_sumber' => 'pelanggaran:'.$laporan->id,
            'poin' => $jenis->poin,
        ]);

        $this->post(route('verifikasi-pelanggaran.bk', $laporan), [
            'hasil' => 'pembinaan',
            'catatan' => 'Mencoba mengubah keputusan final.',
        ])->assertStatus(422);
    }

    public function test_wakil_dapat_mengembalikan_rekomendasi_kepada_bk_tanpa_mencatat_poin(): void
    {
        [$tahun, $siswa, $jenis] = $this->dataDasar();
        [, $akunBk] = $this->buatAkunPegawai('BK Pemeriksa', '198501012015011006', 'bk');
        [, $akunWakil] = $this->buatAkunPegawai('Wakil Kesiswaan Penguji', '198601012016011007', 'wakil_pimpinan_kesiswaan');
        $laporan = $this->buatLaporan('PB-KEMBALI-001', 'diajukan', $tahun, $siswa, $jenis);

        $this->actingAs($akunBk)->post(route('verifikasi-pelanggaran.bk', $laporan), [
            'hasil' => 'sanksi_poin',
            'catatan' => 'Rekomendasi awal BK.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($akunWakil)->post(route('verifikasi-pelanggaran.wakil', $laporan), [
            'keputusan' => 'kembalikan',
            'catatan' => 'Bukti kejadian perlu diperjelas oleh BK.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('dikembalikan_bk', $laporan->fresh()->status_verifikasi);
        $this->assertDatabaseMissing('transaksi_poin_siswa', [
            'kunci_sumber' => 'pelanggaran:'.$laporan->id,
        ]);
        $this->assertDatabaseHas('persetujuan_pelanggaran', [
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'jenis_persetujuan' => 'wakil_kesiswaan',
            'keputusan' => 'tidak_setuju',
            'catatan' => 'Bukti kejadian perlu diperjelas oleh BK.',
        ]);

        $this->actingAs($akunBk)->post(route('verifikasi-pelanggaran.bk', $laporan), [
            'hasil' => 'pembinaan',
            'catatan' => 'Setelah diperiksa ulang, cukup dilakukan pembinaan.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('ditetapkan_pembinaan', $laporan->fresh()->status_verifikasi);
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Antrean Uji', 'nisn' => '0088776601', 'aktif' => true]);
        $jenis = JenisPelanggaranSiswa::where('aktif', true)->firstOrFail();

        return [$tahun, $siswa, $jenis];
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

    private function buatLaporan(
        string $nomor,
        string $status,
        TahunPelajaran $tahun,
        Siswa $siswa,
        JenisPelanggaranSiswa $jenis,
    ): LaporanPembinaanSiswa {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $laporan = LaporanPembinaanSiswa::create([
            'nomor_laporan' => $nomor,
            'jenis_laporan' => 'pelanggaran',
            'tanggal_kejadian' => now()->toDateString(),
            'siswa_id' => $siswa->id,
            'kategori_pembinaan_siswa_id' => $jenis->kategori_pembinaan_siswa_id,
            'tahun_pelajaran_id' => $tahun->id,
            'tingkat' => $jenis->tingkat,
            'status' => 'baru',
            'status_verifikasi' => $status,
            'total_poin' => $jenis->poin,
            'kronologi' => 'Kronologi untuk pengujian pusat verifikasi.',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        ButirPelanggaranLaporan::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'jenis_pelanggaran_siswa_id' => $jenis->id,
            'kode_pelanggaran' => $jenis->kode,
            'nama_pelanggaran' => $jenis->nama,
            'tingkat' => $jenis->tingkat,
            'poin' => $jenis->poin,
        ]);

        return $laporan;
    }
}
