<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalKegiatanIbadah;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanIbadah;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PresensiKegiatanIbadah;
use App\Models\PublikasiNilaiSiswa;
use App\Models\PublikasiRaporSts;
use App\Models\RaporStsKelas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DashboardKeluargaTest extends TestCase
{
    use RefreshDatabase;

    private TahunPelajaran $tahun;

    private Pengguna $siswa;

    private Pengguna $ortu;

    private AnggotaKelas $anggota;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(8, 5));
        TahunPelajaran::query()->update(['aktif' => false]);
        $this->tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        [$this->siswa, $this->anggota] = $this->siswa('ANUGRAH EMMANUEL HARIANJA DENGAN NAMA LENGKAP PANJANG', '0130000001', 'VIII.A Bilingual');
        $this->ortu = $this->orangTua($this->siswa);
    }

    public function test_tema_data_presensi_dan_notifikasi_tetap_pribadi_dengan_link_sesuai_identitas(): void
    {
        [$asing, $anggotaAsing] = $this->siswa('SISWA RAHASIA', '0130000002', 'VIII.B');
        $this->presensi($this->anggota, '2026-10-19', 7);
        $this->presensi($this->anggota, '2026-10-16', 0);
        $this->presensi($this->anggota, '2026-10-20', 99);
        $this->presensi($anggotaAsing, '2026-10-19', 80);
        foreach ([$this->siswa, $this->ortu, $asing] as $akun) {
            NotifikasiPengguna::create(['pengguna_id' => $akun->id, 'jenis' => 'informasi', 'judul' => 'NOTIFIKASI-'.$akun->username, 'pesan' => 'Pesan pemilik akun']);
        }
        foreach ([$this->siswa, $this->ortu] as $akun) {
            $r = $this->actingAs($akun)->get(route('beranda', ['siswa_id' => $asing->siswa_id, 'anak_id' => $asing->siswa_id]))->assertOk()
                ->assertSee('class="family-home account-home"', false)->assertSee('dashboard-keluarga.css')->assertSee('Riwayat kedatangan')
                ->assertSee('NOTIFIKASI-'.$akun->username)->assertDontSee('NOTIFIKASI-'.$asing->username)->assertDontSee('SISWA RAHASIA');
            $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
            $this->assertSame(2, $r->viewData('ringkasanKehadiran')['hadir']);
            $this->assertSame(7, $r->viewData('ringkasanKehadiran')['menit_terlambat']);
            $this->assertCount(7, $r->viewData('grafikKedatanganSiswa')['minggu']);
            $this->assertSame(7, $r->viewData('grafikKedatanganSiswa')['minggu'][6]['menit']);
            $this->assertNull($r->viewData('grafikKedatanganSiswa')['minggu'][0]['menit']);
            $this->assertNull($r->viewData('absensiHariIni')->jam_pulang);
            $this->assertSame($this->siswa->siswa_id, $r->viewData('siswaLogin')->id);
            if ($akun->akunOrangTua()) {
                $r->assertSee('Perkembangan anak')->assertSee(route('presensi-anak.index'))->assertSee(route('pengaduan-saya.index'))
                    ->assertDontSee('href="'.route('ujian-saya.index').'"', false);
            } else {
                $r->assertSee('Ringkasan belajar')->assertSee(route('ujian-saya.index'))->assertSee(route('profil-siswa.show'))
                    ->assertDontSee('href="'.route('pengaduan-saya.index').'"', false);
            }
        }
    }

    public function test_rapor_hanya_yang_aktif_sesuai_penerima_dan_anak_yang_ditampilkan(): void
    {
        $ortuSaja = $this->rapor($this->anggota, 'orang_tua', 'RAPOR ORTU SAJA');
        $bersama = $this->rapor($this->anggota, 'orang_tua_dan_siswa', 'RAPOR BERSAMA', 2);
        $dibatalkan = $this->rapor($this->anggota, 'orang_tua_dan_siswa', 'RAPOR DIBATALKAN', 3);
        $dibatalkan->update(['aktif' => false]);
        [$asing, $anggotaAsing] = $this->siswa('SISWA ASING', '0130000002', 'VIII.B');
        $this->rapor($anggotaAsing, 'orang_tua_dan_siswa', 'RAPOR ANAK LAIN');
        $r = $this->actingAs($this->siswa)->get(route('beranda'))->assertOk()->assertSee('RAPOR BERSAMA')
            ->assertDontSee('RAPOR ORTU SAJA')->assertDontSee('RAPOR DIBATALKAN')->assertDontSee('RAPOR ANAK LAIN');
        $this->assertSame(1, $r->viewData('jumlahRaporDashboard'));
        $this->assertSame([$bersama->id], $r->viewData('raporDashboard')->pluck('id')->all());
        $r = $this->actingAs($this->ortu)->get(route('beranda'))->assertOk()->assertSee('RAPOR ORTU SAJA')->assertSee('RAPOR BERSAMA')
            ->assertDontSee('RAPOR DIBATALKAN')->assertDontSee('RAPOR ANAK LAIN');
        $this->assertSame(2, $r->viewData('jumlahRaporDashboard'));
        $this->assertSame([$bersama->id, $ortuSaja->id], $r->viewData('raporDashboard')->pluck('id')->all());
        $this->actingAs($this->orangTua($asing))->get(route('beranda'))->assertOk()->assertSee('RAPOR ANAK LAIN')->assertDontSee('RAPOR BERSAMA');
    }

    public function test_grafik_melintasi_bulan_dan_status_tanpa_scan_tidak_menjadi_hadir(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2));
        $this->presensi($this->anggota, '2026-10-30', 0);
        $this->presensi($this->anggota, '2026-11-01', null, 'sakit');
        $r = $this->actingAs($this->siswa)->get(route('beranda'))->assertOk();
        $grafik = $r->viewData('grafikKedatanganSiswa');
        $this->assertCount(2, $grafik['bulan']);
        $this->assertCount(7, $grafik['minggu']);
        $this->assertSame(0, $grafik['minggu'][3]['menit']);
        $this->assertNull($grafik['bulan'][0]['menit']);
        $this->assertSame('Sakit', $grafik['bulan'][0]['status']);
        $this->assertSame(0, $r->viewData('ringkasanKehadiran')['hadir']);
        $this->assertSame(1, $r->viewData('ringkasanKehadiran')['sakit']);
    }

    public function test_nilai_draf_dan_poin_siswa_lain_tidak_ikut_dalam_ringkasan(): void
    {
        $guru = $this->pengampu($this->anggota);
        $nilai = PublikasiNilaiSiswa::create(['guru_mata_pelajaran_id' => $guru->id, 'semester' => 'ganjil', 'dipublikasikan' => false]);
        [$asing, $anggotaAsing] = $this->siswa('SISWA ASING', '0130000002', 'VIII.B');
        $guruAsing = $this->pengampu($anggotaAsing);
        PublikasiNilaiSiswa::create(['guru_mata_pelajaran_id' => $guruAsing->id, 'semester' => 'ganjil', 'dipublikasikan' => true]);
        TransaksiPoinSiswa::create(['siswa_id' => $asing->siswa_id, 'tahun_pelajaran_id' => $this->tahun->id, 'kunci_sumber' => 'asing', 'jenis' => 'pelanggaran', 'poin' => 99, 'keterangan' => 'POIN RAHASIA', 'tercatat_pada' => now()]);
        foreach ([$this->siswa, $this->ortu] as $akun) {
            $r = $this->actingAs($akun)->get(route('beranda'))->assertOk()->assertSee('Belum ada nilai yang dipublikasikan')->assertDontSee('POIN RAHASIA');
            $this->assertSame(0, $r->viewData('jumlahNilaiDipublikasikan'));
            $this->assertSame(0, $r->viewData('ringkasanPoin')['total']);
        }
        $nilai->update(['dipublikasikan' => true]);
        $r = $this->actingAs($this->ortu)->get(route('beranda'))->assertOk()->assertSee('1 publikasi nilai tersedia');
        $this->assertSame(1, $r->viewData('jumlahNilaiDipublikasikan'));
    }

    public function test_keadaan_kosong_terbaca_tanpa_angka_contoh_dan_nama_html_diescape(): void
    {
        $this->siswa->siswa->update(['nama_lengkap' => '<script>BUKAN KODE</script>']);
        $r = $this->actingAs($this->siswa)->get(route('beranda'))->assertOk()->assertSee('&lt;script&gt;BUKAN KODE&lt;/script&gt;', false)
            ->assertDontSee('<script>BUKAN KODE</script>', false)->assertSee('Tidak ada jadwal pelajaran untuk hari ini.')
            ->assertSee('Belum ada rapor yang dipublikasikan untuk akun ini')->assertSee('Belum ada notifikasi baru.')->assertDontSee('Data contoh');
        $this->assertCount(0, $r->viewData('raporDashboard'));
        $this->ortu->orangTuaWali->siswa()->detach();
        $this->actingAs($this->ortu)->get(route('beranda'))->assertOk()->assertSee('Akun belum terhubung ke data siswa')
            ->assertDontSee('data-chart-canvas', false);
    }

    public function test_fixture_visual_dashboard_siswa_dan_orang_tua(): void
    {
        $guru = $this->pengampu($this->anggota);
        $jam = JamPelajaran::create(['hari' => 'senin', 'nomor_jam' => 1, 'jam_mulai' => '08:00', 'jam_selesai' => '09:00', 'aktif' => true]);
        JadwalPelajaran::create(['tahun_pelajaran_id' => $this->tahun->id, 'kelas_id' => $this->anggota->kelas_id, 'hari' => 'senin', 'jam_pelajaran_id' => $jam->id, 'guru_mata_pelajaran_id' => $guru->id, 'aktif' => true]);
        PublikasiNilaiSiswa::create(['guru_mata_pelajaran_id' => $guru->id, 'semester' => 'ganjil', 'dipublikasikan' => true]);
        $this->presensi($this->anggota, '2026-10-19', 7);
        $this->presensi($this->anggota, '2026-10-16', 0);
        $this->presensi($this->anggota, '2026-10-15', null, 'sakit');
        $this->rapor($this->anggota, 'orang_tua_dan_siswa', 'Sumatif Tengah Semester Ganjil 2026/2027');
        TransaksiPoinSiswa::create(['siswa_id' => $this->siswa->siswa_id, 'tahun_pelajaran_id' => $this->tahun->id, 'kunci_sumber' => 'sendiri', 'jenis' => 'pelanggaran', 'poin' => 15, 'keterangan' => 'Terlambat datang ke sekolah', 'tercatat_pada' => now()]);
        $kegiatan = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();
        $jadwal = JadwalKegiatanIbadah::create(['tahun_pelajaran_id' => $this->tahun->id, 'kegiatan_ibadah_id' => $kegiatan->id, 'hari' => 'senin', 'urutan_hari' => 1, 'jam_scan_mulai' => '11:30', 'jam_pelaksanaan' => '12:00', 'jam_scan_selesai' => '13:00', 'aktif' => true]);
        PresensiKegiatanIbadah::create(['jadwal_kegiatan_ibadah_id' => $jadwal->id, 'kegiatan_ibadah_id' => $kegiatan->id, 'tahun_pelajaran_id' => $this->tahun->id, 'kelas_id' => $this->anggota->kelas_id, 'anggota_kelas_id' => $this->anggota->id, 'siswa_id' => $this->siswa->siswa_id, 'tanggal' => '2026-10-19', 'waktu_scan' => '12:00', 'sumber' => 'kamera']);
        foreach (['siswa' => $this->siswa, 'orang_tua' => $this->ortu] as $nama => $akun) {
            NotifikasiPengguna::create(['pengguna_id' => $akun->id, 'jenis' => 'informasi', 'judul' => 'Rapor STS telah tersedia', 'pesan' => 'Sekolah telah menerbitkan Rapor STS untuk akun Anda.']);
            $this->capture($nama, $this->actingAs($akun)->get(route('beranda'))->assertOk()->getContent());
        }
        AbsensiSiswa::query()->delete();
        $this->capture('siswa-kosong', $this->actingAs($this->siswa)->get(route('beranda'))->assertOk()->getContent());
        $this->ortu->orangTuaWali->siswa()->detach();
        $this->capture('ortu-belum-terhubung', $this->actingAs($this->ortu)->get(route('beranda'))->assertOk()->getContent());
    }

    private function capture(string $nama, string $html): void
    {
        if (getenv('NUSA_CAPTURE_DASHBOARD_KELUARGA')) {
            File::ensureDirectoryExists(storage_path('framework/testing/dashboard-keluarga'));
            File::put(storage_path('framework/testing/dashboard-keluarga/'.$nama.'.html'), $html);
        }
    }

    private function siswa(string $nama, string $nisn, string $kelas): array
    {
        $siswa = Siswa::create(['nama_lengkap' => $nama, 'nisn' => $nisn, 'aktif' => true]);
        $k = Kelas::create(['nama' => $kelas, 'tahun_pelajaran_id' => $this->tahun->id, 'tingkat' => 8, 'aktif' => true]);
        $anggota = AnggotaKelas::create(['tahun_pelajaran_id' => $this->tahun->id, 'kelas_id' => $k->id, 'siswa_id' => $siswa->id, 'nomor_absen' => 1, 'status_keanggotaan' => 'aktif']);
        $akun = Pengguna::create(['siswa_id' => $siswa->id, 'nama' => $nama, 'username' => $nisn, 'peran' => 'siswa', 'kata_sandi' => 'Rahasia123', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'siswa')->firstOrFail());

        return [$akun, $anggota];
    }

    private function orangTua(Pengguna $siswa): Pengguna
    {
        $akun = Pengguna::create(['nama' => 'Orang Tua Wali Murid', 'username' => 'ORT-'.$siswa->username, 'peran' => 'orang_tua', 'kata_sandi' => 'Rahasia123', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'orang_tua')->firstOrFail());
        $profil = OrangTuaWali::create(['pengguna_id' => $akun->id, 'nama_lengkap' => $akun->nama, 'siswa_acuan_username_id' => $siswa->siswa_id]);
        $profil->siswa()->attach($siswa->siswa_id);

        return $akun;
    }

    private function presensi(AnggotaKelas $anggota, string $tanggal, ?int $menit, string $status = 'hadir'): void
    {
        AbsensiSiswa::create(['tanggal' => $tanggal, 'tahun_pelajaran_id' => $this->tahun->id, 'kelas_id' => $anggota->kelas_id, 'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $anggota->siswa_id, 'status_kehadiran' => $status, 'jam_masuk' => $menit === null ? null : ($menit > 0 ? '07:07' : '07:00'), 'menit_terlambat' => $menit ?? 0]);
    }

    private function pengampu(AnggotaKelas $anggota): GuruMataPelajaran
    {
        $pegawai = Pegawai::create(['nama_lengkap' => 'Muhammad Abdurrahman Harianja, S.Pd., M.Pd.', 'aktif' => true]);
        $mapel = MataPelajaran::firstOrCreate(['nama' => 'Ilmu Pengetahuan Alam dan Literasi Digital', 'tingkat' => 8], ['aktif' => true]);

        return GuruMataPelajaran::create(['tahun_pelajaran_id' => $this->tahun->id, 'kelas_id' => $anggota->kelas_id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $pegawai->id, 'aktif' => true]);
    }

    private function rapor(AnggotaKelas $anggota, string $penerima, string $nama, int $versi = 1): PublikasiRaporSts
    {
        $kegiatan = KegiatanUjianCbt::firstOrCreate(['kode' => 'STS-DASHBOARD'], ['nama' => 'STS Ganjil', 'tahun_pelajaran_id' => $this->tahun->id,
            'jenis_ujian_cbt_id' => JenisUjianCbt::where('kode', 'STS')->value('id'), 'semester' => 'ganjil', 'tanggal_mulai' => '2026-09-15', 'tanggal_selesai' => '2026-09-20', 'status' => 'selesai']);
        $pengaturan = RaporStsKelas::firstOrCreate(['kegiatan_ujian_cbt_id' => $kegiatan->id, 'kelas_id' => $anggota->kelas_id], ['tanggal_awal_presensi' => '2026-08-01', 'tanggal_akhir_presensi' => '2026-09-20', 'tanggal_rapor' => '2026-10-02']);

        return PublikasiRaporSts::create(['rapor_sts_kelas_id' => $pengaturan->id, 'anggota_kelas_id' => $anggota->id, 'siswa_id' => $anggota->siswa_id,
            'versi' => $versi, 'penerima' => $penerima, 'aktif' => true, 'salinan' => ['kegiatan' => ['nama' => $nama, 'tahun_pelajaran' => $this->tahun->nama]],
            'sidik_sumber' => str_repeat('a', 64), 'dipublikasikan_pada' => now(), 'dipublikasikan_oleh_pengguna_id' => Pengguna::where('username', 'administrator')->value('id')]);
    }
}
