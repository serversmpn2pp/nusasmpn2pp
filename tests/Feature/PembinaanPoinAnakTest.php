<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\RiwayatProsesPembinaanSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\AkunOrangTuaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PembinaanPoinAnakTest extends TestCase
{
    use RefreshDatabase;

    public function test_orang_tua_hanya_melihat_laporan_dan_riwayat_poin_anaknya(): void
    {
        [$tahun, $kelas, $siswa, $siswaLain, $akunOrangTua] = $this->dataDasar();
        $laporan = $this->buatLaporan($siswa, $tahun, $kelas, [
            'nomor_laporan' => 'LP-ANAK-001',
            'status_verifikasi' => 'pemeriksaan_bk',
        ]);
        $laporanLain = $this->buatLaporan($siswaLain, $tahun, $kelas, [
            'nomor_laporan' => 'LP-SISWA-LAIN',
            'status_verifikasi' => 'disahkan',
            'total_poin' => 99,
        ]);
        $this->buatTransaksi($siswa, $tahun, 'pelanggaran-anak', 'pelanggaran', 15, 'Pelanggaran resmi anak', $laporan);
        $this->buatTransaksi($siswa, $tahun, 'pengurangan-anak', 'pengurangan', -5, 'Reward kegiatan positif');
        $this->buatTransaksi($siswaLain, $tahun, 'poin-siswa-lain', 'pelanggaran', 99, 'Poin siswa lain', $laporanLain);

        $this->actingAs($akunOrangTua)
            ->get(route('pembinaan-poin-anak.index'))
            ->assertOk()
            ->assertViewIs('pembinaan-poin-anak.index')
            ->assertViewHas('siswa', fn (?Siswa $siswaTampil) => $siswaTampil?->is($siswa) === true)
            ->assertViewHas('ringkasan', fn (array $ringkasan) => $ringkasan['laporan'] === 1
                && $ringkasan['diproses'] === 1
                && $ringkasan['poin_pelanggaran'] === 15
                && $ringkasan['pengurangan'] === 5
                && $ringkasan['saldo'] === 10)
            ->assertSee('Pembinaan &amp; Poin Anak', false)
            ->assertSee('LP-ANAK-001')
            ->assertSee('Sedang diperiksa BK')
            ->assertDontSee('LP-SISWA-LAIN')
            ->assertDontSee('Poin siswa lain');

        $this->actingAs($akunOrangTua)
            ->get(route('pembinaan-poin-anak.index', ['tab' => 'poin']))
            ->assertOk()
            ->assertSee('Pelanggaran resmi anak')
            ->assertSee('+15 poin')
            ->assertSee('Reward kegiatan positif')
            ->assertSee('-5 poin')
            ->assertDontSee('Poin siswa lain');
    }

    public function test_detail_hanya_menampilkan_linimasa_publik_dan_menolak_laporan_siswa_lain(): void
    {
        [$tahun, $kelas, $siswa, $siswaLain, $akunOrangTua] = $this->dataDasar();
        $laporan = $this->buatLaporan($siswa, $tahun, $kelas, [
            'nomor_laporan' => 'LP-ANAK-DETAIL',
            'status' => 'selesai',
            'status_verifikasi' => 'ditetapkan_pembinaan',
            'kronologi' => 'Kejadian anak yang dapat diketahui orang tua.',
            'catatan_rahasia' => 'Catatan rahasia BK tidak boleh tampil.',
        ]);
        $laporanLain = $this->buatLaporan($siswaLain, $tahun, $kelas, [
            'nomor_laporan' => 'LP-LAIN-DETAIL',
        ]);
        RiwayatProsesPembinaanSiswa::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'kode_kegiatan' => 'keputusan_bk',
            'judul' => 'Judul internal BK',
            'keterangan' => 'Uraian internal BK.',
            'terjadi_pada' => now(),
            'data' => ['hasil' => 'pembinaan'],
        ]);

        $this->actingAs($akunOrangTua)
            ->get(route('pembinaan-poin-anak.show', $laporan))
            ->assertOk()
            ->assertSee('Detail Pembinaan Anak')
            ->assertSee('Kejadian anak yang dapat diketahui orang tua.')
            ->assertSee('BK menetapkan pembinaan')
            ->assertSee('tidak ditampilkan pada akun orang tua')
            ->assertDontSee('Catatan rahasia BK tidak boleh tampil.')
            ->assertDontSee('Judul internal BK')
            ->assertDontSee('Uraian internal BK.');

        $this->actingAs($akunOrangTua)
            ->get(route('pembinaan-poin-anak.show', $laporanLain))
            ->assertNotFound();
    }

    public function test_akun_bukan_orang_tua_tidak_dapat_membuka_pembinaan_poin_anak(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('pembinaan-poin-anak.index'))
            ->assertForbidden();
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2031/2032',
            'tanggal_mulai' => '2031-07-01',
            'tanggal_selesai' => '2032-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = $this->buatSiswa($tahun, $kelas, 'Anak Orang Tua Pembinaan', '0310202001', 1);
        $siswaLain = $this->buatSiswa($tahun, $kelas, 'Siswa Lain Tertutup', '0310202002', 2);
        $akunOrangTua = app(AkunOrangTuaService::class)->buat($siswa);
        $akunOrangTua->update(['wajib_ganti_kata_sandi' => false]);

        return [$tahun, $kelas, $siswa, $siswaLain, $akunOrangTua];
    }

    private function buatSiswa(TahunPelajaran $tahun, Kelas $kelas, string $nama, string $nisn, int $nomorAbsen): Siswa
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => '31'.$nomorAbsen,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $nomorAbsen,
            'status_keanggotaan' => 'aktif',
        ]);

        return $siswa;
    }

    private function buatLaporan(Siswa $siswa, TahunPelajaran $tahun, Kelas $kelas, array $tambahan): LaporanPembinaanSiswa
    {
        return LaporanPembinaanSiswa::create(array_merge([
            'nomor_laporan' => 'LP-'.fake()->unique()->numerify('########'),
            'jenis_laporan' => 'kejadian',
            'sumber_laporan' => 'manual',
            'tanggal_kejadian' => '2031-08-01',
            'tempat_kejadian' => 'Lingkungan sekolah',
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'tingkat' => 'ringan',
            'status' => 'diproses',
            'status_verifikasi' => 'diajukan',
            'total_poin' => 0,
            'kronologi' => 'Kejadian sedang ditangani sekolah.',
        ], $tambahan));
    }

    private function buatTransaksi(
        Siswa $siswa,
        TahunPelajaran $tahun,
        string $kunci,
        string $jenis,
        int $poin,
        string $keterangan,
        ?LaporanPembinaanSiswa $laporan = null,
    ): void {
        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'laporan_pembinaan_siswa_id' => $laporan?->id,
            'kunci_sumber' => $kunci,
            'jenis' => $jenis,
            'poin' => $poin,
            'keterangan' => $keterangan,
            'tercatat_pada' => now(),
        ]);
    }
}
