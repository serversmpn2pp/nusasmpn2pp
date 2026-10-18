<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TampilanDataSekolahTest extends TestCase
{
    use RefreshDatabase;

    public function test_seluruh_menu_dan_crud_data_sekolah_memakai_tema_yang_sama(): void
    {
        [$tahun, $tujuan, $kelas, $kelasTujuan, $guru, $siswa] = $this->dataSekolah();
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
        $routes = [
            ['tahun-pelajaran.index', []], ['tahun-pelajaran.create', []], ['tahun-pelajaran.show', [$tahun]], ['tahun-pelajaran.edit', [$tahun]],
            ['pegawai.index', []], ['pegawai.create', []], ['pegawai.show', [$guru]], ['pegawai.edit', [$guru]], ['pegawai.import.create', []],
            ['siswa.index', []], ['siswa.create', []], ['siswa.show', [$siswa]], ['siswa.edit', [$siswa]], ['siswa.import.create', []],
            ['kelas.index', []], ['kelas.create', []], ['kelas.show', [$kelas]], ['kelas.edit', [$kelas]],
            ['penempatan-siswa.index', ['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id]],
            ['kenaikan-kelas.index', ['tahun_asal_id' => $tahun->id, 'tahun_tujuan_id' => $tujuan->id, 'kelas_asal_id' => $kelas->id]],
            ['foto-identitas.index', ['tab' => 'siswa', 'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id]],
            ['foto-identitas.index', ['tab' => 'pegawai']],
            ['kartu-pegawai.index', ['pegawai_id' => $guru->id]],
            ['kartu-pelajar.index', ['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id]],
            ['pegawai.index', ['page' => 2]], ['siswa.index', ['page' => 2]],
            ['pegawai.index', ['status' => 'nonaktif']], ['siswa.index', ['status' => 'nonaktif']],
        ];
        $manifest = [];
        foreach ($routes as $i => [$name, $params]) {
            $url = route($name, $params);
            $html = $this->get($url)->assertOk()->assertSee('admin-school-data')->assertSee('css/admin-data-sekolah.css')->getContent();
            $this->assertSame(1, substr_count($html, 'css/admin-data-sekolah.css'), $name);
            $dom = $this->dom($html);
            $this->assertSame(1, $dom->query('//aside[@id="admin-sidebar"]//a[@aria-current="page"]')->length, $name);
            if (in_array($name, ['pegawai.index', 'siswa.index', 'kelas.index', 'tahun-pelajaran.index'])) {
                $this->assertSame(5, $dom->query('//table/thead/tr/th')->length, $name);
                $this->assertSame(5, $dom->query('//table/thead/tr/th[@scope="col"]')->length, $name);
                $this->assertGreaterThan(0, $dom->query('//table//a[@aria-label][@data-admin-tooltip]')->length, $name);
            }
            if (in_array($name, ['kartu-pegawai.index', 'kartu-pelajar.index'])) {
                $this->assertSame(1, $dom->query('//button[@data-card-export-open]')->length, $name);
                $this->assertSame(2, $dom->query('//*[@data-card-side]')->length, $name);
                $this->assertSame(1, $dom->query('//h3[@class="school-card-name"]')->length, $name);
            }
            $file = sprintf('%02d-%s', $i, str_replace('.', '-', $name));
            $manifest[parse_url($url, PHP_URL_PATH).(parse_url($url, PHP_URL_QUERY) ? '?'.parse_url($url, PHP_URL_QUERY) : '')] = $file;
            $this->capture($file, $html);
        }
        if (getenv('NUSA_CAPTURE_DATA_SEKOLAH')) {
            File::put(storage_path('framework/testing/data-sekolah/manifest.json'), json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
        $this->get(route('beranda'))->assertOk()->assertDontSee('css/admin-data-sekolah.css');
        $this->get(route('persuratan.index'))->assertOk()->assertDontSee('css/admin-data-sekolah.css');
    }

    public function test_keadaan_kosong_tetap_dapat_dibuka_di_semua_sembilan_menu(): void
    {
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
        $manifest = [];
        foreach (['tahun-pelajaran', 'pegawai', 'siswa', 'kelas', 'penempatan-siswa', 'kenaikan-kelas', 'foto-identitas', 'kartu-pegawai', 'kartu-pelajar'] as $name) {
            $url = route($name.'.index');
            $html = $this->get($url)->assertOk()->assertSee('admin-school-data')->getContent();
            $file = 'empty-'.$name;
            $manifest[parse_url($url, PHP_URL_PATH)] = $file;
            $this->capture($file, $html);
        }
        if (getenv('NUSA_CAPTURE_DATA_SEKOLAH')) {
            File::put(storage_path('framework/testing/data-sekolah/manifest-empty.json'), json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }

    public function test_izin_lihat_tidak_mendapat_aksi_edit_import_penempatan_dan_foto(): void
    {
        [$tahun, , $kelas, , $guru, $siswa] = $this->dataSekolah();
        $role = Peran::create(['nama' => 'Pembaca Data Sekolah', 'kode' => 'pembaca_data_sekolah', 'aktif' => true]);
        $role->izin()->sync(Izin::whereIn('kode', ['pegawai.lihat', 'siswa.lihat', 'kelas.lihat', 'tahun_pelajaran.lihat'])->pluck('id'));
        $akun = Pengguna::create(['nama' => 'Pembaca', 'username' => 'pembaca.data', 'kata_sandi' => 'Rahasia123', 'pegawai_id' => $guru->id,
            'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->sync([$role->id]);
        $this->actingAs($akun);
        foreach (['pegawai', 'siswa', 'kelas', 'tahun-pelajaran'] as $name) {
            $html = $this->get(route($name.'.index'))->assertOk()->assertSee('css/admin-data-sekolah.css')->getContent();
            $dom = $this->dom($html);
            $this->assertSame(0, $dom->query('//a[@href="'.route($name.'.create').'"]')->length);
        }
        $this->get(route('pegawai.edit', $guru))->assertForbidden();
        $this->get(route('siswa.edit', $siswa))->assertForbidden();
        $this->get(route('foto-identitas.index'))->assertForbidden();
        $this->get(route('kenaikan-kelas.index'))->assertForbidden();
        $html = $this->get(route('penempatan-siswa.index', ['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id]))->assertOk()->getContent();
        $dom = $this->dom($html);
        $this->assertSame(0, $dom->query('//form[@data-placement-form]')->length);
        $this->assertSame(0, $dom->query('//input[@data-placement-checkbox]')->length);
    }

    public function test_formulir_validasi_dan_isian_lama_tetap_terhubung(): void
    {
        [$tahun] = $this->dataSekolah();
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
        foreach (['pegawai', 'siswa'] as $name) {
            $this->from(route($name.'.create'))->post(route($name.'.store'), ['nama_lengkap' => ''])->assertSessionHasErrors('nama_lengkap');
            $rendered = (string) $this->withViewErrors(['nama_lengkap' => 'Nama lengkap wajib diisi.'])->view($name.'.create');
            $dom = $this->dom($rendered);
            $this->assertSame(1, $dom->query('//input[@name="nama_lengkap"][@aria-invalid="true"][@aria-describedby="nama_lengkap-error"]')->length);
            $this->assertSame(1, $dom->query('//*[@id="nama_lengkap-error"]')->length);
            $this->assertSame(1, $dom->query('//form[@action="'.route($name.'.store').'"]//input[@name="_token"]')->length);
            $this->capture('validation-'.$name, $rendered);
        }
        $this->post(route('kelas.store'), ['nama' => 'VII.UJI', 'tahun_pelajaran_id' => $tahun->id, 'tingkat' => 7, 'kapasitas' => 32, 'aktif' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('kelas', ['nama' => 'VII.UJI', 'tahun_pelajaran_id' => $tahun->id]);
    }

    private function dataSekolah(): array
    {
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $tujuan = TahunPelajaran::create(['nama' => '2027/2028', 'tanggal_mulai' => '2027-07-01', 'tanggal_selesai' => '2028-06-30', 'aktif' => false]);
        $guru = Pegawai::create(['nama_lengkap' => 'Antonius Pitra Dana Arista, M.T.', 'nip' => '199211032019021001', 'nuptk' => '1234567890123456', 'jenis_pegawai' => 'Guru', 'jabatan_utama' => 'Guru mata pelajaran', 'aktif' => true]);
        for ($i = 1; $i <= 16; $i++) {
            Pegawai::create(['nama_lengkap' => sprintf('Guru %02d Muhammad Abdurrahman Harianja, S.Pd., M.Pd.', $i), 'nip' => '19800101202001'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'nuptk' => '123456789012'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'jenis_pegawai' => 'Guru', 'aktif' => $i !== 16]);
        }
        $kelas = Kelas::create(['nama' => 'VII.A Bilingual', 'tahun_pelajaran_id' => $tahun->id, 'wali_kelas_id' => $guru->id, 'tingkat' => 7, 'kapasitas' => 32, 'aktif' => true]);
        $kelasTujuan = Kelas::create(['nama' => 'VIII.A Bilingual', 'tahun_pelajaran_id' => $tujuan->id, 'wali_kelas_id' => $guru->id, 'tingkat' => 8, 'kapasitas' => 32, 'aktif' => true]);
        $siswaPertama = null;
        for ($i = 1; $i <= 24; $i++) {
            $siswa = Siswa::create(['nama_lengkap' => $i === 1 ? 'Abdurrahim Muhammad Al Hafiz' : sprintf('Siswa %02d MuhammadAbdurrahmanHarianjaSiregarPadangPanjang', $i),
                'nis' => '260'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'nisn' => '013120'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'jenis_kelamin' => $i % 2 ? 'L' : 'P', 'tempat_lahir' => 'Padang Panjang', 'tanggal_lahir' => '2013-01-15',
                'nama_ayah' => 'Muhammad Abdurrahman Harianja', 'nama_ibu' => 'Siti Aisyah Nurhaliza', 'aktif' => $i !== 24]);
            $siswaPertama ??= $siswa;
            if ($i <= 4) {
                AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'nomor_absen' => $i, 'status_keanggotaan' => 'aktif']);
            }
        }

        return [$tahun, $tujuan, $kelas, $kelasTujuan, $guru, $siswaPertama];
    }

    private function capture(string $name, string $html): void
    {
        if (getenv('NUSA_CAPTURE_DATA_SEKOLAH')) {
            File::ensureDirectoryExists(storage_path('framework/testing/data-sekolah'));
            File::put(storage_path('framework/testing/data-sekolah/'.$name.'.html'), $html);
        }
    }

    private function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }
}
