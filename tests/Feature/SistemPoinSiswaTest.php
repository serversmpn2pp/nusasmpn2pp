<?php

namespace Tests\Feature;

use App\Models\ButirPelanggaranLaporan;
use App\Models\JenisPelanggaranSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Pengguna;
use App\Models\PenguranganPoinSiswa;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
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
        $this->assertDatabaseHas('izin', ['kode' => 'poin_siswa.sahkan_wakil']);
        $this->assertDatabaseMissing('izin', ['kode' => 'poin_siswa.menyetujui']);
        $this->assertDatabaseHas('izin', ['kode' => 'guru_wali.kelola']);
    }

    public function test_poin_baru_resmi_setelah_disahkan_wakil_kesiswaan(): void
    {
        [$laporan] = $this->buatLaporanPelanggaran(25);
        $this->assertDatabaseMissing('transaksi_poin_siswa', ['kunci_sumber' => 'pelanggaran:' . $laporan->id]);

        $this->post(route('verifikasi-pelanggaran.bk', $laporan), [
            'hasil' => 'sanksi_poin',
            'catatan' => 'BK menetapkan sanksi poin berdasarkan bukti.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('menunggu_pengesahan_wakil', $laporan->fresh()->status_verifikasi);
        $this->assertDatabaseMissing('transaksi_poin_siswa', [
            'kunci_sumber' => 'pelanggaran:' . $laporan->id,
        ]);
        $this->assertDatabaseMissing('sanksi_poin_siswa', [
            'siswa_id' => $laporan->siswa_id,
        ]);

        $this->post(route('verifikasi-pelanggaran.wakil', $laporan), [
            'keputusan' => 'sahkan',
            'catatan' => 'Rekomendasi BK sesuai bukti pemeriksaan.',
        ])->assertRedirect()->assertSessionHasNoErrors();

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

        $this->assertSame(1, TransaksiPoinSiswa::where('kunci_sumber', 'pelanggaran:' . $laporan->id)->count());
    }

    public function test_guru_hanya_melaporkan_kejadian_dan_bk_yang_menentukan_pelanggaran(): void
    {
        $tahunPelajaran = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Laporan Kejadian',
            'nisn' => '0099000099',
            'aktif' => true,
        ]);
        $guru = Pegawai::create([
            'nama_lengkap' => 'Guru Pelapor Kejadian',
            'nip' => '198505052011051005',
            'aktif' => true,
        ]);
        $akunGuru = Pengguna::create([
            'pegawai_id' => $guru->id,
            'nama' => $guru->nama_lengkap,
            'username' => $guru->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $akunGuru->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());

        $this->actingAs($akunGuru)->get(route('laporan-pembinaan-siswa.create'))
            ->assertOk()
            ->assertSee('Laporan Kejadian')
            ->assertDontSee('Dugaan Butir Pelanggaran');

        $this->post(route('laporan-pembinaan-siswa.store'), [
            'jenis_laporan' => 'pelanggaran',
            'tanggal_kejadian' => now()->toDateString(),
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'kronologi' => 'Guru melaporkan fakta kejadian tanpa menentukan jenis dan poin.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $laporan = LaporanPembinaanSiswa::where('siswa_id', $siswa->id)->firstOrFail();
        $this->assertSame('kejadian', $laporan->jenis_laporan);
        $this->assertSame('diajukan', $laporan->status_verifikasi);
        $this->assertSame(0, $laporan->total_poin);
        $this->assertDatabaseMissing('butir_pelanggaran_laporan', [
            'laporan_pembinaan_siswa_id' => $laporan->id,
        ]);

        $jenisPelanggaran = JenisPelanggaranSiswa::where('aktif', true)->where('poin', '>', 0)->firstOrFail();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->actingAs($administrator)->get(route('laporan-pembinaan-siswa.show', $laporan))
            ->assertOk()
            ->assertSee('Butir pelanggaran dan poin')
            ->assertSee('Pilih hasil');

        $this->post(route('verifikasi-pelanggaran.bk', $laporan), [
            'hasil' => 'sanksi_poin',
            'jenis_pelanggaran_ids' => [$jenisPelanggaran->id],
            'catatan' => 'BK menetapkan butir setelah pemeriksaan.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $laporan->refresh();
        $this->assertSame('pelanggaran', $laporan->jenis_laporan);
        $this->assertSame('menunggu_pengesahan_wakil', $laporan->status_verifikasi);
        $this->assertSame($jenisPelanggaran->poin, $laporan->total_poin);
        $this->assertDatabaseMissing('transaksi_poin_siswa', [
            'kunci_sumber' => 'pelanggaran:' . $laporan->id,
        ]);
        $this->assertDatabaseHas('butir_pelanggaran_laporan', [
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'jenis_pelanggaran_siswa_id' => $jenisPelanggaran->id,
        ]);
    }

    public function test_bk_dapat_menetapkan_pembinaan_tanpa_menambah_poin(): void
    {
        [$laporan] = $this->buatLaporanPelanggaran(15);

        $this->post(route('verifikasi-pelanggaran.bk', $laporan), [
            'hasil' => 'pembinaan',
            'catatan' => 'Siswa cukup diberikan pembinaan dan pendampingan.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('ditetapkan_pembinaan', $laporan->fresh()->status_verifikasi);
        $this->assertSame('pembinaan', $laporan->fresh()->jenis_laporan);
        $this->assertSame(0, $laporan->fresh()->total_poin);
        $this->assertDatabaseMissing('transaksi_poin_siswa', ['laporan_pembinaan_siswa_id' => $laporan->id]);
    }

    public function test_reward_tidak_membuat_saldo_poin_menjadi_negatif(): void
    {
        [$laporan, , $guruWali] = $this->buatLaporanPelanggaran(25);
        $layanan = app(ProsesPoinSiswaService::class);

        $this->post(route('verifikasi-pelanggaran.bk', $laporan), [
            'hasil' => 'sanksi_poin',
            'catatan' => 'BK menetapkan sanksi poin.',
        ])->assertSessionHasNoErrors();

        $this->post(route('verifikasi-pelanggaran.wakil', $laporan), [
            'keputusan' => 'sahkan',
            'catatan' => 'Poin disahkan untuk pengujian reward.',
        ])->assertSessionHasNoErrors();

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

    public function test_penugasan_ulang_ke_guru_wali_yang_sama_tidak_membuat_riwayat_ganda(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $guruWali = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Tetap',
            'nip' => '198606062012061006',
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Tetap Dalam Perwalian',
            'nisn' => '0099000061',
            'aktif' => true,
        ]);
        $penugasan = PenugasanGuruWaliSiswa::create([
            'siswa_id' => $siswa->id,
            'guru_wali_pegawai_id' => $guruWali->id,
            'tanggal_mulai' => '2026-07-01',
            'aktif' => true,
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        $this->actingAs($administrator)->post(route('penugasan-guru-wali.store'), [
            'guru_wali_pegawai_id' => $guruWali->id,
            'siswa_ids' => [$siswa->id],
            'tanggal_mulai' => '2026-08-01',
        ])->assertRedirect(route('penugasan-guru-wali.index'))
            ->assertSessionHas('berhasil');

        $this->assertSame(1, PenugasanGuruWaliSiswa::where('siswa_id', $siswa->id)->count());
        $this->assertTrue($penugasan->fresh()->aktif);
        $this->assertNull($penugasan->fresh()->tanggal_selesai);
    }

    public function test_memindahkan_guru_wali_menutup_penugasan_lama_dan_menyimpan_riwayatnya(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $guruLama = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Lama',
            'nip' => '198707072013071007',
            'aktif' => true,
        ]);
        $guruBaru = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Baru',
            'nip' => '198808082014081008',
            'aktif' => true,
        ]);
        $akunGuruBaru = Pengguna::create([
            'pegawai_id' => $guruBaru->id,
            'nama' => $guruBaru->nama_lengkap,
            'username' => $guruBaru->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Pindah Guru Wali',
            'nisn' => '0099000062',
            'aktif' => true,
        ]);
        $penugasanLama = PenugasanGuruWaliSiswa::create([
            'siswa_id' => $siswa->id,
            'guru_wali_pegawai_id' => $guruLama->id,
            'tanggal_mulai' => '2026-07-01',
            'aktif' => true,
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        $this->actingAs($administrator)->post(route('penugasan-guru-wali.store'), [
            'guru_wali_pegawai_id' => $guruBaru->id,
            'siswa_ids' => [$siswa->id],
            'tanggal_mulai' => '2026-08-01',
            'nomor_sk' => 'SK/GW/2026/002',
        ])->assertRedirect(route('penugasan-guru-wali.index'))
            ->assertSessionHas('berhasil');

        $this->assertFalse($penugasanLama->fresh()->aktif);
        $this->assertSame('2026-08-01', $penugasanLama->fresh()->tanggal_selesai->toDateString());
        $this->assertDatabaseHas('penugasan_guru_wali_siswa', [
            'siswa_id' => $siswa->id,
            'guru_wali_pegawai_id' => $guruBaru->id,
            'nomor_sk' => 'SK/GW/2026/002',
            'aktif' => true,
        ]);
        $this->assertSame(2, PenugasanGuruWaliSiswa::where('siswa_id', $siswa->id)->count());
        $this->assertTrue($akunGuruBaru->fresh()->memilikiPeran('guru_wali'));
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
            'status_verifikasi' => 'diajukan',
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

        return [$laporan, $waliKelas, $guruWali];
    }
}
