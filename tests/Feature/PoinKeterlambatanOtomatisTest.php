<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\PengaturanPoinKeterlambatan;
use App\Models\Pengguna;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoinKeterlambatanOtomatisTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dapat_mengatur_rentang_poin_yang_berurutan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahunPelajaran();

        $this->actingAs($administrator)
            ->put(route('pengaturan-poin-keterlambatan.update', $tahun), $this->dataPengaturan())
            ->assertRedirect(route('pengaturan-poin-keterlambatan.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pengaturan_poin_keterlambatan', [
            'tahun_pelajaran_id' => $tahun->id,
            'aktif' => true,
        ]);
        $this->assertDatabaseHas('rentang_poin_keterlambatan', [
            'menit_mulai' => 11,
            'menit_selesai' => 30,
            'poin' => 15,
        ]);
        $this->get(route('pengaturan-poin-keterlambatan.index'))
            ->assertOk()
            ->assertSee('11-30 menit: 15 poin');

        $dataTidakBerurutan = $this->dataPengaturan();
        $dataTidakBerurutan['rentang'][1]['menit_mulai'] = 12;
        $this->put(route('pengaturan-poin-keterlambatan.update', $tahun), $dataTidakBerurutan)
            ->assertSessionHasErrors('rentang.1.menit_mulai');
    }

    public function test_rekap_membuat_satu_laporan_tetapi_belum_menetapkan_poin(): void
    {
        $data = $this->dataAbsensi(20);

        $this->actingAs($data['administrator'])
            ->post(route('rekap-absensi-harian.proses-poin-keterlambatan'), [
                'tanggal' => '2026-07-23',
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas']->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $laporan = LaporanPembinaanSiswa::where('absensi_siswa_id', $data['absensi']->id)->firstOrFail();
        $this->assertSame('absensi_otomatis', $laporan->sumber_laporan);
        $this->assertSame('diajukan', $laporan->status_verifikasi);
        $this->assertSame(15, $laporan->total_poin);
        $this->assertSame($data['wali']->id, $laporan->wali_kelas_pegawai_id);
        $this->assertSame($data['guru_wali']->id, $laporan->guru_wali_pegawai_id);
        $this->assertDatabaseMissing('transaksi_poin_siswa', ['laporan_pembinaan_siswa_id' => $laporan->id]);

        $this->post(route('rekap-absensi-harian.proses-poin-keterlambatan'), [
            'tanggal' => '2026-07-23',
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, LaporanPembinaanSiswa::where('absensi_siswa_id', $data['absensi']->id)->count());
        $this->assertDatabaseHas('absensi_siswa', [
            'id' => $data['absensi']->id,
            'status_poin_keterlambatan' => 'laporan_diajukan',
            'poin_keterlambatan_terhitung' => 15,
        ]);
    }

    public function test_poin_baru_sah_setelah_bk_menetapkan_sanksi_poin(): void
    {
        $data = $this->dataAbsensi(20);
        $this->actingAs($data['administrator']);
        $this->post(route('rekap-absensi-harian.proses-poin-keterlambatan'), [
            'tanggal' => '2026-07-23',
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
        ]);
        $laporan = LaporanPembinaanSiswa::where('absensi_siswa_id', $data['absensi']->id)->firstOrFail();

        $akunBk = $this->buatAkunPegawai('BK Otomatis', '198101012010011001', 'bk');

        $this->actingAs($akunBk)
            ->post(route('verifikasi-pelanggaran.bk', $laporan), [
                'hasil' => 'sanksi_poin',
                'catatan' => 'Waktu scan sesuai dengan data mesin.',
            ])->assertSessionHasNoErrors();

        $this->assertSame('disahkan', $laporan->fresh()->status_verifikasi);
        $this->assertDatabaseHas('transaksi_poin_siswa', [
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'poin' => 15,
        ]);
    }

    public function test_keterlambatan_dalam_toleransi_tidak_membuat_laporan(): void
    {
        $data = $this->dataAbsensi(5);

        $this->actingAs($data['administrator'])
            ->post(route('rekap-absensi-harian.proses-poin-keterlambatan'), [
                'tanggal' => '2026-07-23',
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas']->id,
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('laporan_pembinaan_siswa', ['absensi_siswa_id' => $data['absensi']->id]);
        $this->assertDatabaseHas('absensi_siswa', [
            'id' => $data['absensi']->id,
            'status_poin_keterlambatan' => 'toleransi',
            'poin_keterlambatan_terhitung' => 0,
        ]);
    }

    public function test_koreksi_absensi_membatalkan_poin_lama_dan_memulai_keputusan_bk_baru(): void
    {
        $data = $this->dataAbsensi(20);
        $this->actingAs($data['administrator']);
        $this->post(route('rekap-absensi-harian.proses-poin-keterlambatan'), [
            'tanggal' => '2026-07-23',
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
        ]);
        $laporanAwal = LaporanPembinaanSiswa::where('absensi_siswa_id', $data['absensi']->id)->firstOrFail();

        $akunBk = $this->buatAkunPegawai('BK Koreksi', '198202022010012002', 'bk');
        $this->actingAs($akunBk)->post(route('verifikasi-pelanggaran.bk', $laporanAwal), ['hasil' => 'sanksi_poin', 'catatan' => 'Sanksi poin ditetapkan.']);
        $this->assertSame('disahkan', $laporanAwal->fresh()->status_verifikasi);

        $this->actingAs($data['administrator'])
            ->put(route('rekap-absensi-harian.koreksi.update', $data['anggota']), [
                'tanggal' => '2026-07-23',
                'status_kehadiran' => 'hadir',
                'jam_masuk' => '07:40',
                'jam_pulang' => '14:10',
                'catatan' => 'Koreksi waktu berdasarkan log mesin.',
            ])
            ->assertRedirect(route('rekap-absensi-harian.index', [
                'tanggal' => '2026-07-23',
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas']->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('absensi_siswa', [
            'id' => $data['absensi']->id,
            'menit_terlambat' => 40,
            'poin_keterlambatan_terhitung' => 25,
        ]);

        $laporanBaru = LaporanPembinaanSiswa::where('absensi_siswa_id', $data['absensi']->id)->latest('id')->firstOrFail();
        $this->assertSame('dibatalkan', $laporanAwal->fresh()->status_verifikasi);
        $this->assertNotSame($laporanAwal->id, $laporanBaru->id);
        $this->assertSame('diajukan', $laporanBaru->status_verifikasi);
        $this->assertSame(25, $laporanBaru->total_poin);
        $this->assertSame(0, (int) $data['absensi']->siswa->transaksiPoinSiswa()->sum('poin'));
    }

    private function dataAbsensi(int $menitTerlambat): array
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahunPelajaran();
        $wali = Pegawai::create(['nama_lengkap' => 'Wali Kelas Otomatis', 'nip' => '197901012005011001', 'aktif' => true]);
        $guruWali = Pegawai::create(['nama_lengkap' => 'Guru Wali Otomatis', 'nip' => '198001012006011002', 'aktif' => true]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_id' => $wali->id,
            'nama' => 'VIII.A',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Terlambat', 'nisn' => '0099887766', 'aktif' => true]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);
        PenugasanGuruWaliSiswa::create([
            'siswa_id' => $siswa->id,
            'guru_wali_pegawai_id' => $guruWali->id,
            'tanggal_mulai' => '2026-07-01',
            'aktif' => true,
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        $pengaturan = PengaturanPoinKeterlambatan::create([
            'tahun_pelajaran_id' => $tahun->id,
            'aktif' => true,
            'diperbarui_oleh_pengguna_id' => $administrator->id,
        ]);
        $pengaturan->rentangPoinKeterlambatan()->createMany([
            ['menit_mulai' => 1, 'menit_selesai' => 10, 'poin' => 0, 'urutan' => 1],
            ['menit_mulai' => 11, 'menit_selesai' => 30, 'poin' => 15, 'urutan' => 2],
            ['menit_mulai' => 31, 'menit_selesai' => null, 'poin' => 25, 'urutan' => 3],
        ]);
        PengaturanAbsensi::create([
            'hari' => 'kamis',
            'urutan_hari' => 4,
            'jam_scan_masuk_mulai' => '06:00',
            'jam_masuk' => '07:00',
            'jam_scan_masuk_selesai' => '07:45',
            'jam_scan_pulang_mulai' => '14:00',
            'jam_pulang' => '14:10',
            'jam_scan_pulang_selesai' => '15:00',
            'aktif' => true,
        ]);
        $jamMasuk = sprintf('07:%02d', $menitTerlambat);
        $absensi = AbsensiSiswa::create([
            'tanggal' => '2026-07-23',
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $siswa->id,
            'jam_masuk' => $jamMasuk,
            'status_masuk' => 'terlambat',
            'menit_terlambat' => $menitTerlambat,
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);

        return compact('administrator', 'tahun', 'wali', 'kelas', 'siswa', 'anggota', 'absensi') + [
            'guru_wali' => $guruWali,
        ];
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

    private function dataPengaturan(): array
    {
        return [
            'aktif' => '1',
            'rentang' => [
                ['menit_mulai' => 1, 'menit_selesai' => 10, 'poin' => 0],
                ['menit_mulai' => 11, 'menit_selesai' => 30, 'poin' => 15],
                ['menit_mulai' => 31, 'menit_selesai' => '', 'poin' => 25],
            ],
        ];
    }

    private function buatAkunPegawai(string $nama, string $nip, string $peran): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'nip' => $nip, 'aktif' => true]);

        return $this->buatAkunUntukPegawai($pegawai, $peran);
    }

    private function buatAkunUntukPegawai(Pegawai $pegawai, string $peran): Pengguna
    {
        $akun = Pengguna::firstOrCreate(
            ['pegawai_id' => $pegawai->id],
            [
                'nama' => $pegawai->nama_lengkap,
                'username' => $pegawai->nip,
                'kata_sandi' => 'KataSandi-Uji-2026',
                'peran' => 'pegawai',
                'aktif' => true,
            ],
        );
        $akun->daftarPeran()->syncWithoutDetaching(Peran::where('kode', $peran)->firstOrFail());

        return $akun->fresh();
    }
}
