<?php

namespace Tests\Feature;

use App\Models\ButirPelanggaranLaporan;
use App\Models\JenisPelanggaranSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenguranganPoinSiswa;
use App\Models\PersetujuanPelanggaran;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Models\VerifikasiBkPelanggaran;
use App\Services\Pembinaan\ProsesPoinSiswaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SistemPoinSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_pelanggaran_sanksi_role_dan_izin_tersedia(): void
    {
        $this->assertDatabaseCount('jenis_pelanggaran_siswa', 69);
        $this->assertDatabaseCount('aturan_sanksi_poin', 7);
        $this->assertDatabaseHas('peran', ['kode' => 'guru_wali', 'aktif' => true]);
        $this->assertDatabaseHas('izin', ['kode' => 'poin_siswa.verifikasi_bk']);
        $this->assertDatabaseHas('izin', ['kode' => 'poin_siswa.menyetujui']);
        $this->assertDatabaseHas('izin', ['kode' => 'guru_wali.kelola']);
    }

    public function test_poin_baru_resmi_setelah_disetujui_dua_pegawai_berbeda(): void
    {
        [$laporan, $waliKelas, $guruWali] = $this->buatLaporanPelanggaran(25);
        $layanan = app(ProsesPoinSiswaService::class);

        PersetujuanPelanggaran::create($this->dataPersetujuan($laporan, 'wali_kelas', $waliKelas));
        PersetujuanPelanggaran::create($this->dataPersetujuan($laporan, 'guru_wali', $waliKelas));

        $this->assertFalse($layanan->perbaruiStatusPersetujuan($laporan));
        $this->assertSame('disetujui_sebagian', $laporan->fresh()->status_verifikasi);
        $this->assertDatabaseMissing('transaksi_poin_siswa', ['kunci_sumber' => 'pelanggaran:' . $laporan->id]);

        PersetujuanPelanggaran::query()
            ->where('laporan_pembinaan_siswa_id', $laporan->id)
            ->where('jenis_persetujuan', 'guru_wali')
            ->update(['pegawai_id' => $guruWali->id]);

        $this->assertTrue($layanan->perbaruiStatusPersetujuan($laporan->fresh()));
        $this->assertSame('disahkan', $laporan->fresh()->status_verifikasi);
        $this->assertDatabaseHas('transaksi_poin_siswa', [
            'kunci_sumber' => 'pelanggaran:' . $laporan->id,
            'poin' => 25,
        ]);
        $this->assertDatabaseHas('sanksi_poin_siswa', [
            'siswa_id' => $laporan->siswa_id,
            'poin_saat_terpicu' => 25,
            'status' => 'menunggu',
        ]);

        $layanan->perbaruiStatusPersetujuan($laporan->fresh());
        $this->assertSame(1, TransaksiPoinSiswa::where('kunci_sumber', 'pelanggaran:' . $laporan->id)->count());
    }

    public function test_reward_tidak_membuat_saldo_poin_menjadi_negatif(): void
    {
        [$laporan, $waliKelas, $guruWali] = $this->buatLaporanPelanggaran(25);
        $layanan = app(ProsesPoinSiswaService::class);

        PersetujuanPelanggaran::create($this->dataPersetujuan($laporan, 'wali_kelas', $waliKelas));
        PersetujuanPelanggaran::create($this->dataPersetujuan($laporan, 'guru_wali', $guruWali));
        $layanan->perbaruiStatusPersetujuan($laporan);

        $pengurangan = PenguranganPoinSiswa::create([
            'siswa_id' => $laporan->siswa_id,
            'tahun_pelajaran_id' => $laporan->tahun_pelajaran_id,
            'tanggal_kegiatan' => now()->toDateString(),
            'jenis_kegiatan' => 'Prestasi sekolah',
            'poin_pengurangan' => 30,
            'status' => 'diajukan',
            'diajukan_oleh_pengguna_id' => auth()->id(),
        ]);

        $diterapkan = $layanan->setujuiPengurangan($pengurangan, $guruWali->id, 'Disetujui');

        $this->assertSame(25, $diterapkan);
        $this->assertSame(0, $layanan->totalPoin($laporan->siswa_id, $laporan->tahun_pelajaran_id));
        $this->assertDatabaseHas('transaksi_poin_siswa', [
            'kunci_sumber' => 'reward:' . $pengurangan->id,
            'poin' => -25,
        ]);
    }

    public function test_penugasan_siswa_otomatis_memasang_role_guru_wali(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $guruWali = Pegawai::create(['nama_lengkap' => 'Guru Wali Otomatis', 'nip' => '198303032009031003', 'aktif' => true]);
        $akunGuru = Pengguna::create([
            'pegawai_id' => $guruWali->id,
            'nama' => $guruWali->nama_lengkap,
            'username' => '198303032009031003',
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Wali Otomatis', 'nisn' => '0099000002', 'aktif' => true]);

        $this->actingAs($administrator)->post(route('penugasan-guru-wali.store'), [
            'guru_wali_pegawai_id' => $guruWali->id,
            'siswa_ids' => [$siswa->id],
            'tanggal_mulai' => '2026-07-20',
            'nomor_sk' => 'SK/UJI/001',
        ])->assertRedirect(route('penugasan-guru-wali.index'));

        $this->assertDatabaseHas('penugasan_guru_wali_siswa', [
            'siswa_id' => $siswa->id,
            'guru_wali_pegawai_id' => $guruWali->id,
            'aktif' => true,
        ]);
        $this->assertTrue($akunGuru->fresh()->memilikiPeran('guru_wali'));
    }

    public function test_guru_wali_hanya_melihat_siswa_yang_ditugaskan_kepadanya(): void
    {
        $guruWali = Pegawai::create(['nama_lengkap' => 'Guru Wali Terbatas', 'nip' => '198404042010042004', 'aktif' => true]);
        $akunGuru = Pengguna::create([
            'pegawai_id' => $guruWali->id,
            'nama' => $guruWali->nama_lengkap,
            'username' => '198404042010042004',
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $akunGuru->daftarPeran()->attach(Peran::where('kode', 'guru_wali')->firstOrFail());

        $siswaDitugaskan = Siswa::create(['nama_lengkap' => 'Siswa Dalam Perwalian', 'nisn' => '0099000003', 'aktif' => true]);
        Siswa::create(['nama_lengkap' => 'Siswa Di Luar Perwalian', 'nisn' => '0099000004', 'aktif' => true]);

        $this->actingAs($akunGuru)
            ->get(route('rekap-poin-siswa.index'))
            ->assertOk()
            ->assertDontSee('Siswa Di Luar Perwalian')
            ->assertDontSee('Siswa Dalam Perwalian');

        DB::table('penugasan_guru_wali_siswa')->insert([
            'siswa_id' => $siswaDitugaskan->id,
            'guru_wali_pegawai_id' => $guruWali->id,
            'tanggal_mulai' => '2026-07-20',
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('rekap-poin-siswa.index'))
            ->assertOk()
            ->assertSee('Siswa Dalam Perwalian')
            ->assertDontSee('Siswa Di Luar Perwalian');
    }

    private function buatLaporanPelanggaran(int $totalPoin): array
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->actingAs($administrator);

        $tahunPelajaran = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Ananda Uji Sistem',
            'nis' => 'UJI001',
            'nisn' => '0099000001',
            'aktif' => true,
        ]);
        $waliKelas = Pegawai::create(['nama_lengkap' => 'Wali Kelas Uji', 'nip' => '198001012006041001', 'aktif' => true]);
        $guruWali = Pegawai::create(['nama_lengkap' => 'Guru Wali Uji', 'nip' => '198202022008042002', 'aktif' => true]);

        $jenisDipilih = collect();
        $sisaPoin = $totalPoin;
        foreach ([15, 10, 5] as $poin) {
            while ($sisaPoin >= $poin) {
                $jenis = JenisPelanggaranSiswa::where('poin', $poin)
                    ->whereNotIn('id', $jenisDipilih->pluck('id'))
                    ->first();
                if (! $jenis) {
                    break;
                }
                $jenisDipilih->push($jenis);
                $sisaPoin -= $poin;
            }
        }
        $this->assertSame(0, $sisaPoin, 'Kombinasi jenis pelanggaran untuk tes tidak ditemukan.');

        $laporan = LaporanPembinaanSiswa::create([
            'nomor_laporan' => 'PB-UJI-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'jenis_laporan' => 'pelanggaran',
            'tanggal_kejadian' => now()->toDateString(),
            'siswa_id' => $siswa->id,
            'kategori_pembinaan_siswa_id' => $jenisDipilih->first()->kategori_pembinaan_siswa_id,
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'pelapor_pegawai_id' => $waliKelas->id,
            'wali_kelas_pegawai_id' => $waliKelas->id,
            'guru_wali_pegawai_id' => $guruWali->id,
            'tingkat' => 'ringan',
            'status' => 'baru',
            'status_verifikasi' => 'menunggu_persetujuan',
            'total_poin' => $totalPoin,
            'kronologi' => 'Kronologi pengujian sistem poin.',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        foreach ($jenisDipilih as $jenis) {
            ButirPelanggaranLaporan::create([
                'laporan_pembinaan_siswa_id' => $laporan->id,
                'jenis_pelanggaran_siswa_id' => $jenis->id,
                'kode_pelanggaran' => $jenis->kode,
                'nama_pelanggaran' => $jenis->nama,
                'tingkat' => $jenis->tingkat,
                'poin' => $jenis->poin,
            ]);
        }

        VerifikasiBkPelanggaran::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'bk_pegawai_id' => $waliKelas->id,
            'pengguna_id' => $administrator->id,
            'hasil' => 'terbukti',
            'catatan' => 'Fakta telah diperiksa.',
            'diverifikasi_pada' => now(),
        ]);

        return [$laporan, $waliKelas, $guruWali];
    }

    private function dataPersetujuan(LaporanPembinaanSiswa $laporan, string $jenis, Pegawai $pegawai): array
    {
        return [
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'jenis_persetujuan' => $jenis,
            'pegawai_id' => $pegawai->id,
            'pengguna_id' => auth()->id(),
            'keputusan' => 'setuju',
            'catatan' => 'Disetujui dalam pengujian.',
            'diputuskan_pada' => now(),
        ];
    }
}
