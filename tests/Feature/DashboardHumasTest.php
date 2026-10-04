<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\AlumniHumas;
use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\KerjaSamaHumas;
use App\Models\KlipingBeritaHumas;
use App\Models\KunjunganTamu;
use App\Models\LaporanPelaksanaanHumas;
use App\Models\MitraHumas;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\PengaduanHumas;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PrestasiSekolah;
use App\Models\ProgramKerjaHumas;
use App\Models\PublikasiHumas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 4)->setTime(10, 0));
        $this->actingAs($this->akun());
    }

    public function test_role_akses_sidebar_dan_cetak(): void
    {
        foreach (['wakil_pimpinan_humas', 'pimpinan', 'administrator'] as $role) {
            $akun = $this->akun($role);
            $this->assertTrue($akun->memilikiIzin('dashboard_humas.lihat'));
            $this->actingAs($akun)->get(route('dashboard-humas.index'))->assertOk()->assertSee('Dashboard Humas')->assertSee('Capaian periode')->assertSee('Cetak ringkasan');
            $this->get(route('dashboard-humas.cetak'))->assertOk()->assertSee('RINGKASAN KINERJA HUMAS');
        }
        foreach (['pegawai', 'guru_mapel', 'siswa', 'orang_tua', 'satpam'] as $role) {
            $this->actingAs($this->akun($role))->get(route('dashboard-humas.index'))->assertForbidden();
            $this->get(route('dashboard-humas.cetak'))->assertForbidden();
        }
        auth()->forgetGuards();
        $this->get(route('dashboard-humas.index'))->assertRedirect(route('login'));
    }

    public function test_nonaktif_siswa_orang_tua_tetap_ditolak_meski_role_humas(): void
    {
        $ortu = $this->akun();
        OrangTuaWali::create(['pengguna_id' => $ortu->id, 'nama_lengkap' => 'Orang tua']);
        $siswa = $this->akun();
        $siswa->update(['siswa_id' => Siswa::create(['nama_lengkap' => 'Siswa', 'nisn' => '9911223344', 'aktif' => true])->id]);
        $nonaktif = $this->akun();
        $nonaktif->update(['aktif' => false]);
        foreach ([$ortu, $siswa, $nonaktif] as $akun) {
            $this->actingAs($akun)->get(route('dashboard-humas.index'))->assertForbidden();
            $this->get(route('dashboard-humas.cetak'))->assertForbidden();
        }
    }

    public function test_default_tahun_aktif_semester_dan_rentang_tanggal(): void
    {
        $tahun = $this->tahun();
        $this->get(route('dashboard-humas.index'))->assertViewHas('filter', ['tahun_pelajaran_id' => $tahun->id, 'periode' => 'tahunan', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $this->get(route('dashboard-humas.index', ['periode' => 'ganjil']))->assertViewHas('filter', fn ($f) => $f['tanggal_mulai'] === '2026-07-01' && $f['tanggal_selesai'] === '2026-12-31');
        $this->get(route('dashboard-humas.index', ['periode' => 'genap']))->assertViewHas('filter', fn ($f) => $f['tanggal_mulai'] === '2027-01-01' && $f['tanggal_selesai'] === '2027-06-30');
        $this->get(route('dashboard-humas.index', $this->kustom()))->assertViewHas('filter', fn ($f) => $f['tahun_pelajaran_id'] === null && $f['tanggal_mulai'] === '2026-09-01');
        $this->get(route('dashboard-humas.index', ['periode' => 'ganjil', 'tanggal_mulai' => '2020-01-01', 'tanggal_selesai' => '2020-01-02']))->assertViewHas('filter', fn ($f) => $f['tanggal_mulai'] === '2026-07-01');
    }

    public function test_default_tanpa_tahun_dan_filter_tidak_valid(): void
    {
        $this->get(route('dashboard-humas.index'))->assertViewHas('filter', fn ($f) => $f['periode'] === 'kustom' && $f['tanggal_mulai'] === '2026-09-05' && $f['tanggal_selesai'] === '2026-10-04');
        foreach ([['periode' => 'ganjil'], ['periode' => 'invalid'], ['tahun_pelajaran_id' => 999], ['periode' => ['ganjil']], ['periode' => 'kustom'], $this->kustom(['tanggal_mulai' => 'bad']), $this->kustom(['tanggal_selesai' => '2026-08-31']), $this->kustom(['tanggal_mulai' => '2020-01-01', 'tanggal_selesai' => '2022-01-01']), $this->kustom(['tanggal_mulai' => ['2026-09-01']])] as $filter) {
            $this->getJson(route('dashboard-humas.index', $filter))->assertUnprocessable();
            $this->getJson(route('dashboard-humas.cetak', $filter))->assertUnprocessable();
        }
    }

    public function test_batas_hari_pertama_terakhir_bulanan_dan_status_capaian(): void
    {
        $this->tahun();
        foreach (['2026-09-01', '2026-09-30', '2026-08-31', '2026-10-01'] as $tgl) {
            $this->prestasi(['tanggal_prestasi' => $tgl]);
            $this->tamu(['waktu_datang' => $tgl.' 23:59:59']);
            $this->publikasi(['waktu_tayang' => $tgl.' 00:00:00']);
        }
        $this->prestasi(['status' => 'draf']);
        $this->prestasi(['status' => 'arsip']);
        $this->tamu(['status' => 'dibatalkan']);
        $this->publikasi(['status' => 'disetujui']);
        $this->publikasi(['status' => 'tayang', 'waktu_tayang' => null]);
        $r = $this->get(route('dashboard-humas.index', $this->kustom()))->assertOk();
        foreach (['prestasi', 'tamu', 'publikasi'] as $key) {
            $this->assertSame(2, $r->viewData('metrik')[$key]['jumlah']);
            $this->assertSame(2, $r->viewData('bulan')['2026-09']['jumlah'][$key]);
        }
        $this->get(route('dashboard-humas.index', $this->kustom(['tanggal_selesai' => '2026-10-01'])))->assertViewHas('bulan', fn ($b) => count($b) === 2 && $b['2026-10']['jumlah']['prestasi'] === 1);
    }

    public function test_program_tahunan_beririsan_laporan_final_dan_pembatalan(): void
    {
        $tahun = $this->tahun();
        $program = $this->program(['tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'semester' => 'tahunan']);
        $batal = $this->program(['status' => 'dibatalkan']);
        $this->laporan($program, ['tanggal_selesai' => '2026-09-01']);
        $this->laporan($program, ['tanggal_selesai' => '2026-09-30']);
        $this->laporan($program, ['status' => 'draf']);
        $this->laporan($program, ['tanggal_selesai' => '2026-08-31']);
        $this->laporan($batal);
        $r = $this->get(route('dashboard-humas.index', $this->kustom()))->assertOk();
        $this->assertSame(2, $r->viewData('metrik')['laporan']['jumlah']);
        $this->assertSame(2, $r->viewData('program')->firstWhere('id', $program->id)->realisasi_periode);
        $this->assertSame(1, $r->viewData('distribusi')['program']['jumlah']['dibatalkan']);
        $this->get(route('dashboard-humas.index', ['periode' => 'genap', 'tahun_pelajaran_id' => $tahun->id]))->assertViewHas('jumlahProgram', 1)->assertViewHas('metrik', fn ($m) => $m['laporan']['jumlah'] === 0);
    }

    public function test_tiket_diterima_dan_selesai_memakai_tanggal_berbeda_tanpa_identitas(): void
    {
        $this->aduan(['tanggal_diterima' => '2026-08-01', 'status' => 'selesai', 'diselesaikan_pada' => '2026-09-01 00:00:00']);
        $this->aduan(['tanggal_diterima' => '2026-09-30', 'status' => 'selesai', 'diselesaikan_pada' => '2026-10-01 00:00:00']);
        $this->aduan(['status' => 'ditutup', 'diselesaikan_pada' => '2026-09-02 10:00:00']);
        $this->aduan(['status' => 'baru']);
        $r = $this->get(route('dashboard-humas.index', $this->kustom()))->assertOk();
        $this->assertSame(3, $r->viewData('metrik')['pengaduan_masuk']['jumlah']);
        $this->assertSame(1, $r->viewData('metrik')['pengaduan_selesai']['jumlah']);
        foreach (['dashboard-humas.index', 'dashboard-humas.cetak'] as $route) {
            $this->get(route($route, $this->kustom()))->assertDontSee('IDENTITAS RAHASIA')->assertDontSee('ISI RAHASIA')->assertDontSee('JUDUL RAHASIA')->assertDontSee('081299999999');
        }
    }

    public function test_izin_sumber_dibatasi_dan_dashboard_tanpa_sumber_tidak_bocor(): void
    {
        $this->program(['nama' => 'PROGRAM TERBATAS']);
        $this->agenda(['judul' => 'AGENDA TERBATAS']);
        $this->prestasi();
        $this->actingAs($this->terbatas(['dashboard_humas.lihat', 'prestasi_sekolah.lihat']));
        DB::enableQueryLog();
        $r = $this->get(route('dashboard-humas.index', $this->kustom()))->assertOk()->assertDontSee('PROGRAM TERBATAS')->assertDontSee('AGENDA TERBATAS');
        $this->assertSame(['prestasi'], array_keys($r->viewData('metrik')));
        $this->assertSame([], $r->viewData('distribusi'));
        $this->assertSame(['prestasi'], array_keys($r->viewData('kolomBulanan')));
        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        foreach (['from "program_kerja_humas"', 'from "pengaduan_humas"', 'from "kunjungan_tamu"', 'from "alumni_humas"'] as $query) {
            $this->assertStringNotContainsString($query, $sql);
        }
        $this->actingAs($this->terbatas(['dashboard_humas.lihat']))->get(route('dashboard-humas.index'))->assertViewHas('metrik', [])->assertSee('Belum ada ringkasan')->assertDontSee('data-metric=', false);
    }

    public function test_petugas_hanya_mendapat_statistik_tiket_yang_ditugaskan(): void
    {
        $petugas = $this->terbatas(['dashboard_humas.lihat', 'pengaduan_humas.tangani']);
        $pegawai = Pegawai::create(['nama_lengkap' => 'Petugas pengaduan', 'aktif' => true]);
        $petugas->update(['pegawai_id' => $pegawai->id]);
        $this->aduan(['petugas_pengguna_id' => $petugas->id, 'status' => 'diproses', 'batas_tanggal' => '2026-09-01']);
        $this->aduan(['petugas_pengguna_id' => auth()->id(), 'status' => 'diproses', 'batas_tanggal' => '2026-09-01']);
        $r = $this->actingAs($petugas)->get(route('dashboard-humas.index', $this->kustom()))->assertOk();
        $this->assertSame(1, $r->viewData('metrik')['pengaduan_masuk']['jumlah']);
        $this->assertSame(1, $r->viewData('perhatian')[0]['jumlah']);
        $pegawai->update(['aktif' => false]);
        $this->get(route('dashboard-humas.index', $this->kustom()))->assertViewHas('metrik', fn ($m) => $m['pengaduan_masuk']['jumlah'] === 0);
    }

    public function test_perhatian_seluruh_periode_agenda_14_hari_dan_alumni_tanpa_tanggal(): void
    {
        $this->program(['tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-01-30']);
        $agenda = $this->agenda(['waktu_mulai' => '2026-10-05 10:00:00', 'waktu_selesai' => '2026-10-05 12:00:00', 'status' => 'terjadwal']);
        $this->agenda(['waktu_mulai' => '2026-10-20 10:00:00', 'waktu_selesai' => '2026-10-20 12:00:00', 'status' => 'terjadwal']);
        $mitra = MitraHumas::create(['nama' => 'Mitra', 'jenis' => 'pendidikan', 'status' => 'aktif']);
        foreach ([['2025-01-01', '2026-01-01'], ['2026-09-01', '2026-10-15']] as [$awal, $akhir]) {
            $this->simpan(new KerjaSamaHumas, ['mitra_humas_id' => $mitra->id, 'judul' => 'Kerja sama', 'bidang' => 'pendidikan', 'ruang_lingkup' => 'Kegiatan', 'penanggung_jawab' => 'Humas', 'status' => 'aktif', 'tanggal_mulai' => $awal, 'tanggal_selesai' => $akhir, 'ingatkan_hari_sebelum' => 30]);
        }
        $this->alumni(['tanggal_lulus' => null]);
        $r = $this->get(route('dashboard-humas.index', $this->kustom()))->assertOk();
        $this->assertSame(0, $r->viewData('metrik')['alumni']['jumlah']);
        $this->assertSame(1, $r->viewData('metrik')['kemitraan']['jumlah']);
        $this->assertSame([$agenda->id], $r->viewData('agenda')->pluck('id')->all());
        $peringatan = collect($r->viewData('perhatian'))->keyBy('label');
        foreach (['MoU kedaluwarsa belum diakhiri', 'MoU segera berakhir', 'Program melewati target penyelesaian', 'Alumni belum memiliki tanggal kelulusan'] as $label) {
            $this->assertSame(1, $peringatan[$label]['jumlah']);
        }
        $this->assertStringContainsString('masa_berlaku=kedaluwarsa', $peringatan['MoU kedaluwarsa belum diakhiri']['url']);
    }

    public function test_kliping_alumni_dan_kunjungan_tidak_memuat_kontak_privat(): void
    {
        foreach (['aktif', 'arsip'] as $status) {
            $this->simpan(new KlipingBeritaHumas, ['token_pembuatan' => Str::uuid(), 'judul' => 'Berita', 'nama_media' => 'Media', 'jenis' => 'online', 'topik' => 'prestasi', 'tanggal_terbit' => '2026-09-01', 'status' => $status]);
        }
        $this->alumni();
        $this->tamu();
        $r = $this->get(route('dashboard-humas.index', $this->kustom()))->assertOk();
        $this->assertSame(1, $r->viewData('metrik')['kliping']['jumlah']);
        $this->assertSame(1, $r->viewData('metrik')['alumni']['jumlah']);
        $r->assertDontSee('NAMA TAMU PRIVAT')->assertDontSee('ALUMNI PRIVAT')->assertDontSee('081299999999');
    }

    public function test_baca_saja_cetak_mengikuti_filter_izin_dan_no_store(): void
    {
        $p = $this->program();
        $aduan = $this->aduan();
        $sebelum = [$p->fresh()->getAttributes(), $aduan->fresh()->getAttributes()];
        $r = $this->get(route('dashboard-humas.index', $this->kustom()))->assertOk();
        $cetak = $this->get(route('dashboard-humas.cetak', $this->kustom()))->assertOk()->assertSee('01-09-2026')->assertSee('30-09-2026');
        $this->assertSame($r->viewData('metrik'), $cetak->viewData('metrik'));
        $this->assertStringContainsString('no-store', $cetak->headers->get('Cache-Control'));
        $this->assertSame($sebelum, [$p->fresh()->getAttributes(), $aduan->fresh()->getAttributes()]);
        $this->actingAs($this->terbatas(['dashboard_humas.lihat', 'prestasi_sekolah.lihat']))->get(route('dashboard-humas.cetak', $this->kustom()))->assertViewHas('metrik', fn ($m) => array_keys($m) === ['prestasi'])->assertDontSee('Aspirasi / aduan diterima');
    }

    public function test_fixture_tampilan_kosong_judul_panjang_dan_xss(): void
    {
        $this->tahun();
        $this->capture('empty', $this->get(route('dashboard-humas.index'))->assertOk());
        for ($i = 0; $i < 10; $i++) {
            $p = $this->program(['nama' => $i === 0 ? '<script>window.injected=true</script> Program eksternal' : 'Program hubungan sekolah dengan orang tua dan masyarakat '.$i, 'status' => $i % 3 ? 'berjalan' : 'selesai']);
            $this->laporan($p);
            $this->agenda(['judul' => 'Koordinasi orang tua, komite, dan mitra sekolah dengan judul panjang '.$i, 'waktu_mulai' => now()->addDays($i % 5)->toDateTimeString(), 'waktu_selesai' => now()->addDays($i % 5)->addHours(2)->toDateTimeString(), 'status' => 'terjadwal']);
            $this->prestasi();
            $this->publikasi();
            $this->tamu();
        }
        $this->aduan();
        $this->simpan(new DokumenHumas, ['judul' => 'DOKUMEN PRIVAT', 'kategori' => 'sop', 'status' => 'aktif', 'berlaku_sampai' => '2026-08-01', 'lokasi_file' => 'private/file.pdf', 'nama_file_asli' => 'BERKAS PRIVAT.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => 10]);
        $this->capture('index', $this->get(route('dashboard-humas.index'))->assertOk()->assertDontSee('<script>window.injected=true</script>', false));
        $this->capture('custom', $this->get(route('dashboard-humas.index', $this->kustom()))->assertOk());
        $this->capture('cetak', $this->get(route('dashboard-humas.cetak'))->assertOk());
        $this->actingAs($this->terbatas(['dashboard_humas.lihat', 'prestasi_sekolah.lihat']));
        $this->capture('limited', $this->get(route('dashboard-humas.index'))->assertOk());
    }

    private function akun(string $role = 'wakil_pimpinan_humas'): Pengguna
    {
        $p = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'dashboard.'.Str::uuid(), 'kata_sandi' => 'UjiDashboard123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function terbatas(array $izin): Pengguna
    {
        $role = Peran::create(['kode' => 'dashboard_'.Str::random(10), 'nama' => 'Dashboard terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $p = $this->akun('pegawai');
        $p->daftarPeran()->sync([$role->id]);

        return $p;
    }

    private function tahun(): TahunPelajaran
    {
        return TahunPelajaran::firstOrCreate(['nama' => '2026/2027'], ['tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
    }

    private function kustom(array $filter = []): array
    {
        return array_replace(['periode' => 'kustom', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-30'], $filter);
    }

    private function simpan(Model $model, array $data): Model
    {
        $model->forceFill($data)->save();

        return $model;
    }

    private function program(array $data = []): ProgramKerjaHumas
    {
        return $this->simpan(new ProgramKerjaHumas, array_replace(['token_pembuatan' => Str::uuid(), 'tahun_pelajaran_id' => $this->tahun()->id, 'nama' => 'Program sekolah', 'semester' => 'ganjil', 'bidang' => 'orang_tua', 'tujuan' => 'Koordinasi', 'sasaran' => 'Orang tua', 'target_hasil' => 'Pertemuan', 'target_kegiatan' => 3, 'penanggung_jawab' => 'Waka Humas', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-30', 'status' => 'berjalan'], $data));
    }

    private function laporan(ProgramKerjaHumas $p, array $data = []): LaporanPelaksanaanHumas
    {
        return $this->simpan(new LaporanPelaksanaanHumas, array_replace(['token_pembuatan' => Str::uuid(), 'program_kerja_humas_id' => $p->id, 'judul' => 'Kegiatan', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-20', 'tempat' => 'Sekolah', 'pelaksana' => 'Humas', 'jumlah_peserta' => 10, 'uraian' => 'Pertemuan', 'hasil' => 'Kesepakatan', 'status' => 'final'], $data));
    }

    private function agenda(array $data = []): AgendaHumas
    {
        return $this->simpan(new AgendaHumas, array_replace(['judul' => 'Agenda sekolah', 'jenis' => 'orang_tua', 'waktu_mulai' => '2026-09-01 10:00:00', 'waktu_selesai' => '2026-09-01 12:00:00', 'tempat' => 'Aula sekolah', 'topik' => 'Pertemuan', 'status' => 'selesai'], $data));
    }

    private function prestasi(array $data = []): PrestasiSekolah
    {
        return $this->simpan(new PrestasiSekolah, array_replace(['token_pembuatan' => Str::uuid(), 'identitas_hash' => hash('sha256', Str::uuid()), 'nama_kegiatan' => 'Olimpiade', 'kategori' => 'akademik', 'tingkat' => 'nasional', 'perolehan' => 'juara_1', 'capaian' => 'Juara 1', 'tanggal_prestasi' => '2026-09-20', 'penerima' => 'sekolah', 'bentuk' => 'sekolah', 'status' => 'terverifikasi'], $data));
    }

    private function publikasi(array $data = []): PublikasiHumas
    {
        return $this->simpan(new PublikasiHumas, array_replace(['token_pembuatan' => Str::uuid(), 'judul' => 'Berita sekolah', 'jenis' => 'berita', 'isi' => 'Berita', 'kanal' => 'website', 'status' => 'tayang', 'waktu_tayang' => '2026-09-20 10:00:00'], $data));
    }

    private function tamu(array $data = []): KunjunganTamu
    {
        return $this->simpan(new KunjunganTamu, array_replace(['token_pencatatan' => Str::uuid(), 'nama_tamu' => 'NAMA TAMU PRIVAT', 'nomor_wa' => '081299999999', 'kategori' => 'dinas', 'keperluan' => 'Kunjungan', 'nama_tujuan' => 'Humas', 'waktu_datang' => '2026-09-20 10:00:00', 'status' => 'selesai'], $data));
    }

    private function alumni(array $data = []): AlumniHumas
    {
        return $this->simpan(new AlumniHumas, array_replace(['token_pembuatan' => Str::uuid(), 'nama_lengkap' => 'ALUMNI PRIVAT', 'tahun_lulus' => 2026, 'tanggal_lulus' => '2026-09-30', 'status' => 'aktif', 'kelulusan_dicatat_pada' => now(), 'nomor_wa' => '081299999999'], $data));
    }

    private function aduan(array $data = []): PengaduanHumas
    {
        return $this->simpan(new PengaduanHumas, array_replace(['token_pembuatan' => Str::uuid(), 'judul' => 'JUDUL RAHASIA', 'jenis' => 'pengaduan', 'kategori' => 'layanan', 'kanal' => 'telepon', 'tanggal_diterima' => '2026-09-20', 'isi' => 'ISI RAHASIA', 'nama_pelapor' => 'IDENTITAS RAHASIA', 'kontak_pelapor' => '081299999999', 'prioritas' => 'normal', 'status' => 'baru'], $data));
    }

    private function capture(string $nama, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_DASHBOARD_HUMAS_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/dashboard-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$nama.'.html', $response->getContent());
    }
}
