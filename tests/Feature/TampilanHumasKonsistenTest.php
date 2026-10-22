<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\AlumniHumas;
use App\Models\AsetPromosiHumas;
use App\Models\DokumenHumas;
use App\Models\KlipingBeritaHumas;
use App\Models\KunjunganTamu;
use App\Models\MediaResmiHumas;
use App\Models\MitraHumas;
use App\Models\PengaduanHumas;
use App\Models\Pengguna;
use App\Models\PeriodeKomiteHumas;
use App\Models\PortofolioAkreditasiHumas;
use App\Models\PrestasiSekolah;
use App\Models\ProgramKerjaHumas;
use App\Models\PublikasiHumas;
use App\Models\TahunPelajaran;
use App\Models\UmpanBalikHumas;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class TampilanHumasKonsistenTest extends TestCase
{
    use RefreshDatabase;

    private array $manifest = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
    }

    public function test_daftar_kosong_berisi_formulir_detail_dan_filter_memakai_tema_humas_yang_sama(): void
    {
        $modules = ['program-kerja-humas', 'akreditasi-humas', 'komite-humas', 'umpan-balik-humas', 'kemitraan-humas',
            'publikasi-humas', 'aset-promosi-humas', 'media-resmi-humas', 'kliping-berita-humas', 'prestasi-sekolah',
            'alumni-humas', 'pengaduan-humas', 'agenda-humas', 'buku-tamu'];
        foreach ($modules as $module) {
            $html = $this->page($module.'-empty', route($module.'.index'), 'list');
            $this->assertStringContainsString('Belum ada', $html, $module);
        }

        $models = $this->records();
        foreach ($modules as $module) {
            $this->page($module, route($module.'.index'), 'list');
            $form = $this->page($module.'-form', route($module.'.create'), 'form');
            $this->assertStringContainsString('name="_token"', $form, $module);
            $this->page($module.'-detail', route($module.'.show', $models[$module]), 'detail');
        }
        foreach (['komite', 'orang_tua', 'kemitraan'] as $jenis) {
            $html = $this->page('agenda-'.$jenis, route('agenda-humas.index', ['jenis' => $jenis]), 'list');
            $this->assertStringContainsString('value="'.$jenis.'" selected', $html);
        }
        foreach (['prestasi-sekolah', 'alumni-humas'] as $module) {
            $this->page($module.'-statistik', route($module.'.index', ['tab' => 'statistik']), 'statistics');
        }
        $this->page('kemitraan-mou', route('kemitraan-humas.index', ['tab' => 'mou']), 'list');
        $this->page('tamu-rekap', route('buku-tamu.index', ['tab' => 'rekap']), 'list');
        $this->page('dokumen-humas', route('dokumen-humas.index'), 'reference');
        $p = $models['komite-humas'];
        $this->page('program-komite', route('komite-humas.program.index', $p), 'list');
        $this->page('program-komite-form', route('komite-humas.program.create', $p), 'form');
        $this->page('program-error', route('program-kerja-humas.create'), 'form', ['nama' => 'Nama program wajib diisi.']);
        if (getenv('NUSA_CAPTURE_HUMAS_THEME') === '1') {
            File::put(storage_path('framework/testing/humas-theme/manifest.json'), json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }

    public function test_gaya_humas_tidak_dimuat_ke_modul_lain_dan_halaman_cetak(): void
    {
        $models = $this->records();
        foreach (['tahun-pelajaran.index', 'persuratan.index', 'beranda'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('css/humas-pages.css');
        }
        foreach (['program-kerja-humas', 'akreditasi-humas'] as $module) {
            $this->get(route($module.'.cetak', $models[$module]))->assertOk()
                ->assertDontSee('css/humas-pages.css')->assertDontSee('css/admin-feature-pages.css')
                ->assertDontSee('data-admin-shell');
        }
    }

    private function page(string $name, string $url, string $kind, array $errors = []): string
    {
        session()->forget(['berhasil', 'errors']);
        $r = $this->get($url)->assertOk()->assertSee('css/humas-pages.css')->assertSee('data-admin-module="humas"', false);
        $html = $r->getContent();
        if ($errors) {
            $html = $r->original->with('errors', (new ViewErrorBag)->put('default', new MessageBag($errors)))->render();
        }
        foreach ($errors as $error) {
            $this->assertStringContainsString($error, $html);
        }
        $d = new DOMDocument;
        @$d->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $x = new DOMXPath($d);
        $this->assertSame(1, $x->query('//main[contains(@class,"content-shell")]//*[contains(@class,"page-header")]')->length, $name);
        if ($kind === 'list') {
            $this->assertSame(1, $x->query('//form[contains(@class,"feature-filter")]')->length, $name);
            $this->assertGreaterThan(0, $x->query('//*[contains(@class,"humas-records") or contains(@class,"agenda-table-shell") or contains(@class,"aset-grid")]')->length, $name);
        }
        $this->manifest[$name] = ['url' => $url, 'kind' => $kind];
        if (getenv('NUSA_CAPTURE_HUMAS_THEME') === '1') {
            File::ensureDirectoryExists(storage_path('framework/testing/humas-theme'));
            File::put(storage_path('framework/testing/humas-theme/'.$name.'.html'), $html);
        }

        return $html;
    }

    private function records(): array
    {
        $t = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $this->store('program-kerja-humas', ['tahun_pelajaran_id' => $t->id, 'semester' => 'ganjil', 'bidang' => 'orang_tua',
            'nama' => 'Program komunikasi dan kolaborasi sekolah bersama orang tua', 'tujuan' => 'Menguatkan kerja sama.', 'sasaran' => 'Orang tua siswa',
            'target_hasil' => 'Pertemuan terlaksana', 'target_kegiatan' => 2, 'penanggung_jawab' => 'Waka Humas', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-12-31', 'status' => 'rencana']);
        $this->store('akreditasi-humas', ['tahun_pelajaran_id' => $t->id, 'nama' => 'Portofolio bukti kerja sama dan partisipasi orang tua', 'instrumen' => 'Instrumen sekolah', 'penanggung_jawab' => 'Waka Humas']);
        $this->store('komite-humas', ['nama' => 'Kepengurusan Komite Sekolah 2026-2029', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2029-06-30',
            'status' => 'draf', 'metode' => 'tanpa', 'pengurus' => [['nama' => 'Ketua Komite Sekolah', 'jabatan' => 'ketua', 'aktif' => true]]]);
        $this->store('umpan-balik-humas', ['tahun_pelajaran_id' => $t->id, 'judul' => 'Evaluasi layanan komunikasi sekolah dan pendampingan siswa', 'pengantar' => 'Evaluasi layanan sekolah.',
            'penanggung_jawab' => 'Waka Humas', 'cakupan' => 'seluruh', 'mulai_pada' => '2026-10-22T09:00', 'selesai_pada' => '2026-10-30T12:00',
            'pertanyaan' => [['jenis' => 'skala', 'teks' => 'Informasi sekolah disampaikan dengan jelas.', 'wajib' => 1]]]);
        $m = MitraHumas::create(['nama' => 'Komunitas Pendidikan dan Pengembangan Literasi', 'jenis' => 'komunitas', 'status' => 'aktif']);
        $m->kerjaSama()->create(['judul' => 'Kerja sama pendidikan dan literasi sekolah', 'bidang' => 'pendidikan', 'ruang_lingkup' => 'Kegiatan literasi bersama', 'status' => 'draf']);
        $this->store('publikasi-humas', ['judul' => 'Kegiatan kolaborasi sekolah bersama orang tua dan masyarakat', 'jenis' => 'berita', 'kanal' => 'website', 'isi' => 'Kegiatan kolaborasi pendidikan dilaksanakan bersama.']);
        $this->store('aset-promosi-humas', ['nama' => 'Dokumentasi dan identitas resmi sekolah', 'kategori' => 'logo', 'tanggal_aset' => '2026-10-04', 'metode' => 'tautan', 'tautan' => 'https://sekolah.test/aset', 'kredit' => 'Humas sekolah', 'ketentuan_penggunaan' => 'Kegiatan resmi sekolah.']);
        $this->store('media-resmi-humas', ['nama' => 'Website resmi SMP Negeri 2 Padang Panjang', 'jenis' => 'website', 'tautan' => 'https://sekolah.test', 'penanggung_jawab' => 'Petugas Humas Sekolah', 'status' => 'aktif', 'tanggal_diperiksa' => '2026-10-04']);
        $this->store('kliping-berita-humas', ['judul' => 'Sekolah meraih prestasi pada kegiatan pendidikan nasional', 'nama_media' => 'Media Pendidikan', 'jenis' => 'online', 'topik' => 'prestasi', 'tanggal_terbit' => '2026-10-04', 'tautan' => 'https://media.test/berita', 'metode' => 'tanpa', 'status' => 'aktif']);
        $this->store('prestasi-sekolah', ['nama_kegiatan' => 'Olimpiade Pelajar Nasional', 'cabang' => 'Matematika', 'kategori' => 'akademik', 'tingkat' => 'nasional', 'perolehan' => 'juara_1', 'capaian' => 'Juara 1 Olimpiade Matematika Tingkat Nasional', 'tanggal_prestasi' => '2026-09-01', 'penerima' => 'siswa', 'bentuk' => 'individu', 'peserta' => [['nama' => 'Siswa Uji Tampilan', 'kelas' => 'IX.A']], 'status' => 'draf', 'metode' => 'tanpa']);
        $this->store('alumni-humas', ['nama_lengkap' => 'Alumni Uji Konsistensi Tampilan', 'jenis_kelamin' => 'L', 'tahun_lulus' => 2026, 'kelas_terakhir' => 'IX.A', 'status' => 'aktif', 'status_penelusuran' => 'belum_terdata', 'konfirmasi_lulus' => 1]);
        $this->store('pengaduan-humas', ['judul' => 'Masukan untuk peningkatan layanan komunikasi sekolah', 'jenis' => 'pengaduan', 'kategori' => 'layanan', 'kanal' => 'tatap_muka', 'tanggal_diterima' => '2026-10-22', 'isi' => 'Mohon peningkatan layanan komunikasi sekolah.', 'anonim' => false, 'nama_pelapor' => 'Pelapor Uji', 'prioritas' => 'normal']);
        foreach (['komite', 'orang_tua', 'kemitraan'] as $jenis) {
            AgendaHumas::create(['judul' => 'Pertemuan koordinasi program sekolah bersama '.$jenis, 'jenis' => $jenis, 'waktu_mulai' => now(), 'waktu_selesai' => now()->addHours(2), 'tempat' => 'Aula SMP Negeri 2 Padang Panjang', 'topik' => 'Koordinasi program', 'status' => 'terjadwal']);
        }
        $this->store('buku-tamu', ['token_pencatatan' => (string) Str::uuid(), 'nama_tamu' => 'Tamu Koordinasi Program Pendidikan', 'instansi' => 'Dinas Pendidikan', 'kategori' => 'dinas', 'keperluan' => 'Koordinasi program pendidikan dan kerja sama sekolah', 'tujuan_lain' => 'Tata Usaha', 'waktu_datang' => '2026-10-22T09:00']);
        DokumenHumas::create(['judul' => 'Program Kerja Humas', 'kategori' => 'program_kerja', 'status' => 'aktif', 'lokasi_file' => 'uji.pdf', 'nama_file_asli' => 'uji.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => 4]);

        return ['program-kerja-humas' => ProgramKerjaHumas::firstOrFail(), 'akreditasi-humas' => PortofolioAkreditasiHumas::firstOrFail(),
            'komite-humas' => PeriodeKomiteHumas::firstOrFail(), 'umpan-balik-humas' => UmpanBalikHumas::firstOrFail(), 'kemitraan-humas' => $m,
            'publikasi-humas' => PublikasiHumas::firstOrFail(), 'aset-promosi-humas' => AsetPromosiHumas::firstOrFail(), 'media-resmi-humas' => MediaResmiHumas::firstOrFail(),
            'kliping-berita-humas' => KlipingBeritaHumas::firstOrFail(), 'prestasi-sekolah' => PrestasiSekolah::firstOrFail(), 'alumni-humas' => AlumniHumas::firstOrFail(),
            'pengaduan-humas' => PengaduanHumas::firstOrFail(), 'agenda-humas' => AgendaHumas::firstOrFail(), 'buku-tamu' => KunjunganTamu::firstOrFail()];
    }

    private function store(string $module, array $data): void
    {
        $r = $this->postJson(route($module.'.store'), $data + ['token_pembuatan' => (string) Str::uuid()]);
        $r->assertSessionHasNoErrors();
        $this->assertLessThan(400, $r->status(), $module.': '.substr($r->getContent(), 0, 1000));
    }
}
