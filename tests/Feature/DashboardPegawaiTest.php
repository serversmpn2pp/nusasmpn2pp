<?php

namespace Tests\Feature;

use App\Models\AbsensiPegawai;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\NotifikasiPengguna;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\ProgramSupervisi;
use App\Models\SesiSupervisi;
use App\Models\Siswa;
use App\Models\SuratTu;
use App\Models\TahunPelajaran;
use App\Models\VersiInstrumenSupervisi;
use App\Services\DashboardPegawaiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardPegawaiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(8, 5));
        TahunPelajaran::query()->update(['aktif' => false]);
    }

    public function test_ringkasan_dan_grafik_hanya_memakai_catatan_pribadi_sampai_hari_ini(): void
    {
        $tahun = $this->tahun();
        $guru = $this->akun();
        $lain = $this->akun('guru_mapel', 'Guru lain');
        $this->hadir($guru, '2026-10-19', 7);
        $this->hadir($guru, '2026-10-16', 0);
        $this->hadir($guru, '2026-10-20', 90);
        $this->hadir($lain, '2026-10-19', 80);
        AbsensiPegawai::create(['pegawai_id' => $guru->pegawai_id, 'tanggal' => '2026-10-15', 'status_kehadiran' => 'sakit']);
        $response = $this->actingAs($guru)->get(route('beranda', ['pegawai_id' => $lain->pegawai_id]))->assertOk()
            ->assertViewIs('beranda.index')->assertSee('Riwayat kedatangan')->assertSee('dashboard-pegawai.js')
            ->assertDontSee('Data contoh')->assertDontSee('Akses Cepat');
        $rekap = $response->viewData('ringkasanAbsensiPegawaiPribadi');
        $this->assertSame(2, $rekap['hadir']);
        $this->assertSame(1, $rekap['terlambat']);
        $this->assertSame(7, $rekap['total_menit_terlambat']);
        $this->assertSame(1, $rekap['sakit']);
        $grafik = $response->viewData('grafikKedatanganPegawai');
        $this->assertCount(7, $grafik['minggu']);
        $this->assertCount(19, $grafik['bulan']);
        $this->assertSame(7, $grafik['minggu'][6]['menit']);
        $this->assertSame(0, $grafik['minggu'][3]['menit']);
        $this->assertNull($grafik['minggu'][0]['menit']);
        $this->assertNull($grafik['minggu'][2]['menit']);
        $this->assertSame('sakit', $response->viewData('rekapAbsensiPegawaiBulan')[1]['kode']);
        $this->assertSame('08:07:00', $response->viewData('presensiPegawaiHariIni')->jam_masuk);
        $this->assertNull($response->viewData('presensiPegawaiHariIni')->jam_pulang);
    }

    public function test_rentang_tujuh_hari_melintasi_bulan_dan_hari_kosong_bukan_hadir(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2));
        $akun = $this->akun();
        $this->hadir($akun, '2026-10-30', 0);
        $data = app(DashboardPegawaiService::class)->data($akun, now(), null);
        $this->assertCount(7, $data['grafikKedatanganPegawai']['minggu']);
        $this->assertSame(0, $data['grafikKedatanganPegawai']['minggu'][3]['menit']);
        $this->assertCount(2, $data['grafikKedatanganPegawai']['bulan']);
        $this->assertNull($data['presensiPegawaiHariIni']);
    }

    public function test_jadwal_presensi_mengikuti_prioritas_dan_jadwal_yang_tercatat(): void
    {
        $akun = $this->akun();
        $semua = $this->jadwalPresensi();
        $jenis = $this->jadwalPresensi(['cakupan' => 'jenis_pegawai', 'jenis_pegawai' => 'Guru', 'jam_masuk' => '07:10']);
        $khusus = $this->jadwalPresensi(['cakupan' => 'pegawai', 'pegawai_id' => $akun->pegawai_id, 'jam_masuk' => '07:20']);
        $service = app(DashboardPegawaiService::class);
        $this->assertSame($khusus->id, $service->data($akun, now(), null)['jadwalPresensiPegawai']->id);
        $a = $this->hadir($akun, '2026-10-19', 0);
        $a->update(['pengaturan_absensi_pegawai_id' => $jenis->id]);
        $this->assertSame($jenis->id, $service->data($akun, now(), null)['jadwalPresensiPegawai']->id);
        $a->delete();
        $khusus->update(['aktif' => false]);
        $this->assertSame($jenis->id, $service->data($akun, now(), null)['jadwalPresensiPegawai']->id);
        $jenis->update(['aktif' => false]);
        $this->assertSame($semua->id, $service->data($akun, now(), null)['jadwalPresensiPegawai']->id);
    }

    public function test_agenda_nilai_dan_notifikasi_tidak_bocor_antar_pegawai(): void
    {
        $tahun = $this->tahun();
        $guru = $this->akun();
        $lain = $this->akun('guru_mapel', 'PEGAWAI ASING');
        $mapel = $this->pengampu($guru, $tahun);
        $asing = $this->pengampu($lain, $tahun, 'KELAS RAHASIA');
        $jam = JamPelajaran::create(['hari' => 'senin', 'nomor_jam' => 1, 'jam_mulai' => '08:00', 'jam_selesai' => '08:40', 'aktif' => true]);
        foreach ([$mapel, $asing] as $m) {
            JadwalPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $m->kelas_id, 'guru_mata_pelajaran_id' => $m->id, 'hari' => 'senin', 'jam_pelajaran_id' => $jam->id, 'aktif' => true]);
            KomponenNilai::create(['guru_mata_pelajaran_id' => $m->id, 'semester' => 'ganjil', 'jenis_komponen' => 'formatif', 'nama' => 'Tugas', 'aktif' => true]);
            NotifikasiPengguna::create(['pengguna_id' => $m->pegawai_id === $guru->pegawai_id ? $guru->id : $lain->id, 'jenis' => 'informasi', 'judul' => $m->pegawai_id === $guru->pegawai_id ? 'Kegiatan saya' : 'NOTIFIKASI RAHASIA', 'pesan' => 'Informasi']);
        }
        $response = $this->actingAs($guru)->get(route('beranda'))->assertOk()->assertSee('Berlangsung')->assertSee('VIII.A Bilingual')
            ->assertSee('Kegiatan saya')->assertDontSee('NOTIFIKASI RAHASIA')->assertDontSee('KELAS RAHASIA');
        $this->assertCount(1, $response->viewData('agendaPegawai'));
        $this->assertSame(1, $response->viewData('jumlahTugasPegawai'));
        $this->assertSame('Komponen nilai belum lengkap', $response->viewData('tugasPegawai')->first()['label']);
        $mapel->update(['aktif' => false]);
        $data = app(DashboardPegawaiService::class)->data($guru->fresh(), now(), $tahun);
        $this->assertCount(0, $data['agendaPegawai']);
        $this->assertSame(0, $data['jumlahTugasPegawai']);
    }

    public function test_surat_tugas_hanya_yang_sudah_disahkan_dan_penerimanya_sendiri(): void
    {
        $akun = $this->akun();
        $lain = $this->akun('guru_mapel', 'Guru lain');
        foreach (['disahkan', 'draf', 'dibatalkan'] as $status) {
            $surat = $this->surat($akun, ['status' => $status, 'disahkan_pada' => $status === 'draf' ? null : now()]);
            $surat->penugasan()->create(['pegawai_id' => $akun->pegawai_id, 'nama_pegawai' => $akun->nama]);
        }
        $surat = $this->surat($lain, ['status' => 'disahkan', 'disahkan_pada' => now(), 'perihal' => 'SURAT ASING']);
        $surat->penugasan()->create(['pegawai_id' => $lain->pegawai_id, 'nama_pegawai' => $lain->nama]);
        $response = $this->actingAs($akun)->get(route('beranda'))->assertOk()->assertDontSee('SURAT ASING');
        $this->assertCount(1, $response->viewData('agendaPegawai'));
        $this->assertSame(1, $response->viewData('jumlahTugasPegawai'));
        $surat->penugasan()->where('pegawai_id', $lain->pegawai_id)->update(['dibaca_pada' => now()]);
        $this->assertSame(1, app(DashboardPegawaiService::class)->data($akun, now(), null)['jumlahTugasPegawai']);
    }

    public function test_nilai_nol_sah_dan_komponen_sebagian_terisi_tetap_menunggu(): void
    {
        $tahun = $this->tahun();
        $akun = $this->akun();
        $m = $this->pengampu($akun, $tahun);
        $siswa = AnggotaKelas::where('kelas_id', $m->kelas_id)->value('siswa_id');
        $komponen = KomponenNilai::create(['guru_mata_pelajaran_id' => $m->id, 'semester' => 'ganjil', 'jenis_komponen' => 'formatif', 'nama' => 'Tugas', 'aktif' => true]);
        $service = app(DashboardPegawaiService::class);
        $this->assertSame(1, $service->data($akun, now(), $tahun)['jumlahTugasPegawai']);
        NilaiSiswa::create(['komponen_nilai_id' => $komponen->id, 'siswa_id' => $siswa, 'nilai' => 0]);
        $this->assertSame(0, $service->data($akun, now(), $tahun)['jumlahTugasPegawai']);
        $kedua = Siswa::create(['nama_lengkap' => 'Siswa kedua', 'aktif' => true]);
        AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $m->kelas_id, 'siswa_id' => $kedua->id, 'status_keanggotaan' => 'aktif']);
        $this->assertSame(1, $service->data($akun, now(), $tahun)['jumlahTugasPegawai']);
        $komponen->update(['tanggal_penilaian' => '2026-10-20']);
        $this->assertSame(0, $service->data($akun, now(), $tahun)['jumlahTugasPegawai']);
    }

    public function test_pengelola_tu_dan_pimpinan_memakai_izin_dan_penugasan_pengesah(): void
    {
        $tu = $this->akun('tata_usaha', 'Petugas TU');
        $kepala = $this->akun('pimpinan', 'Kepala Sekolah');
        $lain = $this->akun('pimpinan', 'Pengesah lain');
        $this->surat($tu, ['jenis' => 'keluar', 'status' => 'draf']);
        $this->surat($tu, ['jenis' => 'keluar', 'status' => 'diajukan', 'pengesah_pengguna_id' => $kepala->id]);
        $this->surat($tu, ['jenis' => 'keluar', 'status' => 'diajukan', 'pengesah_pengguna_id' => $lain->id]);
        $tuData = app(DashboardPegawaiService::class)->data($tu, now(), null);
        $kepalaData = app(DashboardPegawaiService::class)->data($kepala, now(), null);
        $this->assertSame(1, $tuData['jumlahTugasPegawai']);
        $this->assertSame('Draf surat saya', $tuData['tugasPegawai']->first()['label']);
        $this->assertSame(1, $kepalaData['jumlahTugasPegawai']);
        $this->assertSame('Pengesahan surat', $kepalaData['tugasPegawai']->first()['label']);
        $this->assertContains('Tambah surat', $tuData['aksiPegawai']->pluck('label'));
        $this->assertNotContains('Pemeriksaan surat', $tuData['aksiPegawai']->pluck('label'));
        $this->assertContains('Pemeriksaan surat', $kepalaData['aksiPegawai']->pluck('label'));
    }

    public function test_perwalian_dan_piket_tetap_tersedia_tanpa_mengubah_lingkup_kelas(): void
    {
        $tahun = $this->tahun();
        $akun = $this->akun();
        $m = $this->pengampu($akun, $tahun);
        $m->kelas->update(['wali_kelas_id' => $akun->pegawai_id]);
        JadwalPiketGuru::create(['tahun_pelajaran_id' => $tahun->id, 'pegawai_id' => $akun->pegawai_id, 'hari' => 'senin', 'aktif' => true]);
        $this->actingAs($akun)->get(route('beranda'))->assertOk()->assertSee('Perwalian Siswa')->assertSee('Kehadiran Siswa Wali')
            ->assertSee('Pembinaan &amp; Poin Siswa Wali', false)->assertSee('Jadwal Piket Saya')->assertSee('Anda bertugas piket hari ini');
    }

    public function test_tanpa_tahun_aktif_tidak_mengambil_penugasan_tahun_lama(): void
    {
        $tahun = $this->tahun();
        $akun = $this->akun();
        $m = $this->pengampu($akun, $tahun);
        KomponenNilai::create(['guru_mata_pelajaran_id' => $m->id, 'semester' => 'ganjil', 'jenis_komponen' => 'formatif', 'nama' => 'Tugas lama', 'aktif' => true]);
        $tahun->update(['aktif' => false]);
        $response = $this->actingAs($akun)->get(route('beranda'))->assertOk()->assertSee('Belum ada tahun pelajaran aktif');
        $this->assertSame(0, $response->viewData('jumlahTugasPegawai'));
        $this->assertCount(0, $response->viewData('agendaPegawai'));
    }

    public function test_supervisi_pribadi_tidak_menggunakan_lingkup_global_pimpinan(): void
    {
        $tahun = $this->tahun();
        $guru = $this->akun();
        $kepala = $this->akun('pimpinan', 'Kepala Sekolah');
        $lain = $this->akun('guru_mapel', 'Supervisor lain');
        $m = $this->pengampu($guru, $tahun);
        $program = ProgramSupervisi::create(['token_pembuatan' => (string) Str::uuid(), 'sidik_pembuatan' => str_repeat('a', 64), 'nama' => 'Program', 'tujuan' => 'Pendampingan', 'tahun_pelajaran_id' => $tahun->id, 'semester' => 'ganjil', 'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-11-30', 'status' => 'aktif']);
        $sesi = SesiSupervisi::create(['token_pembuatan' => (string) Str::uuid(), 'sidik_pembuatan' => str_repeat('a', 64), 'program_supervisi_id' => $program->id, 'versi_instrumen_supervisi_id' => VersiInstrumenSupervisi::firstOrFail()->id,
            'guru_mata_pelajaran_id' => $m->id, 'guru_pegawai_id' => $guru->pegawai_id, 'supervisor_pegawai_id' => $lain->pegawai_id, 'kelas_id' => $m->kelas_id,
            'waktu_mulai' => '2026-10-19 08:00', 'waktu_selesai' => '2026-10-19 09:00', 'tempat' => 'Kelas', 'status' => 'terjadwal', 'diterbitkan_pada' => now(), 'konteks' => []]);
        $service = app(DashboardPegawaiService::class);
        $this->assertCount(1, $service->data($guru, now(), $tahun)['agendaPegawai']);
        $this->assertSame(1, $service->data($guru, now(), $tahun)['jumlahTugasPegawai']);
        $this->assertCount(0, $service->data($kepala, now(), $tahun)['agendaPegawai']);
        $this->assertSame(0, $service->data($kepala, now(), $tahun)['jumlahTugasPegawai']);
        $sesi->update(['diterbitkan_pada' => null]);
        $this->assertCount(0, $service->data($guru, now(), $tahun)['agendaPegawai']);
        $this->assertSame(0, $service->data($guru, now(), $tahun)['jumlahTugasPegawai']);
    }

    public function test_keadaan_kosong_dan_html_dinamis_aman_serta_fixture_lintas_role(): void
    {
        $tahun = $this->tahun();
        foreach (['guru_mapel' => 'Guru Muhammad Abdurrahman Harianja, S.Pd., M.Pd.', 'tata_usaha' => 'Petugas Tata Usaha', 'pimpinan' => 'Kepala Sekolah'] as $role => $nama) {
            $akun = $this->akun($role, $nama);
            $this->jadwalPresensi();
            $this->hadir($akun, '2026-10-19', 7);
            $this->hadir($akun, '2026-10-16', 0);
            if ($role === 'guru_mapel') {
                $mapel = $this->pengampu($akun, $tahun);
                $jam = JamPelajaran::create(['hari' => 'senin', 'nomor_jam' => 2, 'jam_mulai' => '08:00', 'jam_selesai' => '09:00', 'aktif' => true]);
                JadwalPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $mapel->kelas_id, 'guru_mata_pelajaran_id' => $mapel->id, 'hari' => 'senin', 'jam_pelajaran_id' => $jam->id, 'aktif' => true]);
                KomponenNilai::create(['guru_mata_pelajaran_id' => $mapel->id, 'semester' => 'ganjil', 'jenis_komponen' => 'formatif', 'nama' => 'Tugas', 'aktif' => true]);
            }
            $surat = $this->surat($akun, ['status' => 'disahkan', 'disahkan_pada' => now(), 'perihal' => 'Pendampingan kegiatan literasi dan numerasi sekolah']);
            $surat->penugasan()->create(['pegawai_id' => $akun->pegawai_id, 'nama_pegawai' => $nama]);
            NotifikasiPengguna::create(['pengguna_id' => $akun->id, 'jenis' => 'informasi', 'judul' => 'Surat tugas baru', 'pesan' => 'Pendampingan kegiatan literasi sekolah telah disahkan.']);
            $response = $this->actingAs($akun)->get(route('beranda'))->assertOk()->assertSee('Presensi hari ini')->assertDontSee('Akses Cepat');
            if (getenv('NUSA_CAPTURE_DASHBOARD_PEGAWAI')) {
                File::ensureDirectoryExists(storage_path('framework/testing/dashboard-pegawai'));
                File::put(storage_path('framework/testing/dashboard-pegawai/'.$role.'.html'), $response->getContent());
            }
        }
        $kosong = $this->akun('pegawai', '<script>RAHASIA</script>');
        $response = $this->actingAs($kosong)->get(route('beranda'))->assertOk()->assertSee('Belum ada agenda terjadwal')->assertSee('Belum ada notifikasi')
            ->assertDontSee('<script>RAHASIA</script>', false)->assertSee('&lt;script&gt;RAHASIA&lt;/script&gt;', false);
        $this->assertSame(0, $response->viewData('jumlahTugasPegawai'));
        if (getenv('NUSA_CAPTURE_DASHBOARD_PEGAWAI')) {
            File::put(storage_path('framework/testing/dashboard-pegawai/kosong.html'), $response->getContent());
        }
    }

    private function akun(string $role = 'guru_mapel', string $nama = 'Guru Pemilik Akun'): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'jenis_pegawai' => 'Guru', 'aktif' => true]);
        $akun = Pengguna::create(['pegawai_id' => $pegawai->id, 'nama' => $nama, 'username' => 'pegawai-'.$pegawai->id, 'peran' => 'pegawai', 'kata_sandi' => 'Rahasia123', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->attach(Peran::whereIn('kode', ['pegawai', $role])->pluck('id'));

        return $akun;
    }

    private function tahun(): TahunPelajaran
    {
        return TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
    }

    private function hadir(Pengguna $akun, string $tanggal, int $menit): AbsensiPegawai
    {
        return AbsensiPegawai::create(['pegawai_id' => $akun->pegawai_id, 'tanggal' => $tanggal, 'jam_masuk' => $menit ? '08:07:00' : '07:00:00', 'status_kehadiran' => 'hadir', 'menit_terlambat' => $menit]);
    }

    private function jadwalPresensi(array $data = []): PengaturanAbsensiPegawai
    {
        return PengaturanAbsensiPegawai::create(array_replace(['nama_jadwal' => 'Jadwal Senin', 'cakupan' => 'semua', 'hari' => 'senin', 'urutan_hari' => 1, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '08:00', 'jam_scan_masuk_selesai' => '10:00', 'jam_scan_pulang_mulai' => '14:00', 'jam_pulang' => '14:10', 'jam_scan_pulang_selesai' => '16:00', 'aktif' => true], $data));
    }

    private function pengampu(Pengguna $akun, TahunPelajaran $tahun, string $kelas = 'VIII.A Bilingual'): GuruMataPelajaran
    {
        $k = Kelas::create(['nama' => $kelas, 'tahun_pelajaran_id' => $tahun->id, 'tingkat' => 8, 'aktif' => true]);
        $mapel = MataPelajaran::firstOrCreate(['nama' => 'Informatika', 'tingkat' => 8], ['aktif' => true]);
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa '.$k->id, 'aktif' => true]);
        AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $k->id, 'siswa_id' => $siswa->id, 'status_keanggotaan' => 'aktif']);

        return GuruMataPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $k->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $akun->pegawai_id, 'aktif' => true]);
    }

    private function surat(Pengguna $akun, array $data = []): SuratTu
    {
        return SuratTu::create(array_replace(['jenis' => 'tugas', 'nomor_surat' => 'T/'.Str::random(6), 'nomor_unik' => hash('sha256', Str::random(16)), 'tanggal_surat' => '2026-10-19',
            'perihal' => 'Kegiatan sekolah', 'pihak' => 'Sekolah', 'sifat' => 'biasa', 'mulai_tugas' => '2026-10-19', 'selesai_tugas' => '2026-10-20', 'status' => 'draf',
            'dibuat_oleh_pengguna_id' => $akun->id, 'diubah_oleh_pengguna_id' => $akun->id], $data));
    }
}
