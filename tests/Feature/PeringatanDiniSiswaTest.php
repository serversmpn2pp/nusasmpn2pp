<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\AturanSanksiPoin;
use App\Models\KategoriPembinaanSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\NotifikasiPengguna;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PeringatanDiniSiswa;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Pembinaan\ProsesPeringatanDiniSiswaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PeringatanDiniSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_proses_membuat_memperbarui_menyelesaikan_dan_mengaktifkan_kembali_peringatan(): void
    {
        [$tahun, $kelas, $siswa, $anggota] = $this->buatSiswaDalamKelas('Siswa Peringatan Dini', '0099220001');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $kategori = KategoriPembinaanSiswa::firstOrFail();

        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kunci_sumber' => 'peringatan-uji:poin:1',
            'jenis' => 'pelanggaran',
            'poin' => 20,
            'keterangan' => 'Saldo untuk deteksi mendekati ambang.',
            'tercatat_pada' => now(),
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        foreach (range(1, 3) as $urutan) {
            LaporanPembinaanSiswa::create([
                'nomor_laporan' => 'PB-PD-00'.$urutan,
                'jenis_laporan' => 'pelanggaran',
                'tanggal_kejadian' => today()->subDays($urutan)->toDateString(),
                'siswa_id' => $siswa->id,
                'kategori_pembinaan_siswa_id' => $kategori->id,
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'anggota_kelas_id' => $anggota->id,
                'tingkat' => 'ringan',
                'status' => 'selesai',
                'status_verifikasi' => 'disahkan',
                'total_poin' => 5,
                'kronologi' => 'Kronologi pelanggaran berulang untuk pengujian.',
                'dibuat_oleh_pengguna_id' => $administrator->id,
            ]);

            AbsensiSiswa::create([
                'tanggal' => today()->subDays($urutan)->toDateString(),
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'anggota_kelas_id' => $anggota->id,
                'siswa_id' => $siswa->id,
                'jam_masuk' => '07:10:00',
                'status_masuk' => 'terlambat',
                'menit_terlambat' => 10,
                'status_kehadiran' => 'hadir',
                'sumber' => 'scan',
            ]);
        }

        $aturan = AturanSanksiPoin::where('batas_poin', 25)->firstOrFail();
        $sanksi = SanksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'aturan_sanksi_poin_id' => $aturan->id,
            'poin_saat_terpicu' => 25,
            'status' => 'menunggu',
            'terpicu_pada' => now(),
        ]);

        $service = app(ProsesPeringatanDiniSiswaService::class);
        $hasilPertama = $service->proses($tahun->id);

        $this->assertSame(4, $hasilPertama['peringatan_baru']);
        $this->assertDatabaseHas('peringatan_dini_siswa', ['siswa_id' => $siswa->id, 'jenis' => 'mendekati_sanksi', 'status' => 'aktif']);
        $this->assertDatabaseHas('peringatan_dini_siswa', ['siswa_id' => $siswa->id, 'jenis' => 'pelanggaran_berulang', 'status' => 'aktif']);
        $this->assertDatabaseHas('peringatan_dini_siswa', ['siswa_id' => $siswa->id, 'jenis' => 'sering_terlambat', 'status' => 'aktif']);
        $this->assertDatabaseHas('peringatan_dini_siswa', ['sanksi_poin_siswa_id' => $sanksi->id, 'jenis' => 'sanksi_belum_selesai', 'status' => 'aktif']);

        $jumlahNotifikasi = NotifikasiPengguna::count();
        $hasilKedua = $service->proses($tahun->id);
        $this->assertSame(0, $hasilKedua['peringatan_baru']);
        $this->assertSame(4, $hasilKedua['peringatan_diperbarui']);
        $this->assertSame($jumlahNotifikasi, NotifikasiPengguna::count());

        TransaksiPoinSiswa::query()->where('siswa_id', $siswa->id)->delete();
        LaporanPembinaanSiswa::query()->where('siswa_id', $siswa->id)->update(['status_verifikasi' => 'dibatalkan']);
        AbsensiSiswa::query()->where('siswa_id', $siswa->id)->delete();
        $sanksi->update(['status' => 'selesai', 'dilaksanakan_pada' => now()]);

        $hasilSelesai = $service->proses($tahun->id);
        $this->assertSame(4, $hasilSelesai['peringatan_diselesaikan']);
        $this->assertSame(0, PeringatanDiniSiswa::aktif()->count());

        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kunci_sumber' => 'peringatan-uji:poin:2',
            'jenis' => 'pelanggaran',
            'poin' => 20,
            'keterangan' => 'Saldo kembali mendekati ambang.',
            'tercatat_pada' => now(),
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        $hasilAktifKembali = $service->proses($tahun->id);
        $peringatanPoin = PeringatanDiniSiswa::where('jenis', 'mendekati_sanksi')->firstOrFail();

        $this->assertSame(1, $hasilAktifKembali['peringatan_baru']);
        $this->assertSame('aktif', $peringatanPoin->status);
        $this->assertSame(2, $peringatanPoin->siklus);
        $this->assertGreaterThan($jumlahNotifikasi, NotifikasiPengguna::count());
    }

    public function test_pengaturan_dapat_diubah_dan_guru_wali_hanya_melihat_peringatannya(): void
    {
        [$tahun, $kelas, $siswaDitugaskan] = $this->buatSiswaDalamKelas('Siswa Peringatan Wali', '0099220002');
        [, , $siswaLain] = $this->buatSiswaDalamKelas('Siswa Peringatan Lain', '0099220003', $tahun, $kelas);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator);
        $this->get(route('pengaturan-peringatan-dini-poin.edit', $tahun))
            ->assertOk()
            ->assertSee('Atur Peringatan Dini')
            ->assertSee('Pelanggaran berulang')
            ->assertSee('Keterlambatan berulang');

        $this
            ->put(route('pengaturan-peringatan-dini-poin.update', $tahun), [
                'aktif' => '1',
                'persentase_mendekati_ambang' => 85,
                'jumlah_pelanggaran_berulang' => 4,
                'periode_pelanggaran_hari' => 45,
                'jumlah_keterlambatan_berulang' => 5,
                'periode_keterlambatan_hari' => 30,
                'notifikasi_aktif' => '1',
            ])
            ->assertRedirect(route('pengaturan-peringatan-dini-poin.index'));

        $this->assertDatabaseHas('pengaturan_peringatan_dini_poin', [
            'tahun_pelajaran_id' => $tahun->id,
            'persentase_mendekati_ambang' => 85,
            'jumlah_pelanggaran_berulang' => 4,
            'jumlah_keterlambatan_berulang' => 5,
        ]);

        foreach ([$siswaDitugaskan, $siswaLain] as $index => $siswa) {
            PeringatanDiniSiswa::create([
                'siswa_id' => $siswa->id,
                'tahun_pelajaran_id' => $tahun->id,
                'jenis' => 'sering_terlambat',
                'tingkat' => 'peringatan',
                'status' => 'aktif',
                'kunci_unik' => 'peringatan-uji-akses:'.$index,
                'judul' => 'Keterlambatan siswa berulang',
                'pesan' => 'Peringatan akses untuk '.$siswa->nama_lengkap,
                'siklus' => 1,
                'terdeteksi_pada' => now(),
                'terakhir_terdeteksi_pada' => now(),
            ]);
        }

        $guruWali = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Peringatan',
            'nip' => '198606062012061006',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $guruWali->id,
            'nama' => $guruWali->nama_lengkap,
            'username' => $guruWali->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'guru_wali')->firstOrFail());
        DB::table('penugasan_guru_wali_siswa')->insert([
            'siswa_id' => $siswaDitugaskan->id,
            'guru_wali_pegawai_id' => $guruWali->id,
            'tanggal_mulai' => now()->toDateString(),
            'aktif' => true,
            'dibuat_oleh_pengguna_id' => $administrator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($akun)
            ->get(route('peringatan-dini-siswa.index', ['tahun_pelajaran_id' => $tahun->id]))
            ->assertOk()
            ->assertSee($siswaDitugaskan->nama_lengkap)
            ->assertDontSee($siswaLain->nama_lengkap);
    }

    private function buatSiswaDalamKelas(
        string $nama,
        string $nisn,
        ?TahunPelajaran $tahun = null,
        ?Kelas $kelas = null,
    ): array {
        $tahun ??= TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas ??= Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.B',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => 'NIS-'.$nisn,
            'nisn' => $nisn,
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahun->tanggal_mulai,
        ]);

        return [$tahun, $kelas, $siswa, $anggota];
    }
}
