<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\Pengguna;
use App\Models\PenugasanPendampingIbadahSiswi;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TampilanLintasRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_semua_role_memakai_kerangka_yang_sama_dengan_menu_dan_data_berbeda(): void
    {
        $d = $this->data();
        $routes = [
            'guru' => ['beranda', 'input-nilai.index', 'jadwal-saya.index', 'perangkat-ajar-saya.index', 'pusat-cbt.index', 'supervisi.index', 'surat-saya.index', 'profil-pegawai.edit'],
            'tu' => ['beranda', 'persuratan.index', 'persuratan.create', 'surat-saya.index', 'profil-pegawai.edit'],
            'pimpinan' => ['beranda', 'dashboard-humas.index', 'supervisi.index', 'rekap-absensi-harian.index', 'persuratan.pemeriksaan', 'profil-pegawai.edit'],
            'siswa' => ['beranda', 'ujian-saya.index', 'nilai-saya.index', 'rapor-sts-saya.index', 'progress-kasus-siswa.index', 'profil-siswa.show'],
            'orang_tua' => ['beranda', 'presensi-anak.index', 'akademik-anak.index', 'rapor-sts-saya.index', 'pembinaan-poin-anak.index', 'pertemuan-saya.index', 'umpan-balik-saya.index', 'pengaduan-saya.index', 'pengaduan-saya.create', 'profil-orang-tua.edit'],
        ];
        $manifest = [];
        foreach ($routes as $role => $names) {
            $this->actingAs($d[$role]);
            foreach ($names as $route) {
                $response = $this->get(route($route));
                $this->assertSame(200, $response->status(), $role.' '.$route.' '.$response->status());
                $html = $response->getContent();
                $dom = $this->dom($html);
                $this->assertSame(1, $dom->query('//body[@data-nusa-theme]')->length, $role.' '.$route);
                $this->assertSame(1, $dom->query('//aside[@id="admin-sidebar"]')->length);
                $this->assertSame(1, substr_count($html, 'css/admin-shell.css'));
                $this->assertSame(1, substr_count($html, 'css/nusa-account-pages.css'));
                $this->assertStringNotContainsString('css/dashboard-admin.css', $html);
                if (in_array($role, ['siswa', 'orang_tua'], true)) {
                    $this->assertSame(0, $dom->query('//nav//*[@data-sidebar-section-id="sistem"]')->length, $role.' '.$route);
                    $this->assertSame(0, $dom->query('//aside//a[@href="'.route('input-nilai.index').'"]')->length);
                    $this->assertSame(0, $dom->query('//aside//a[@href="'.route('persuratan.index').'"]')->length);
                    $this->assertStringNotContainsString('SISWA RAHASIA KELAS LAIN', $html);
                }
                $this->capture($role.'-'.str_replace('.', '-', $route), $html, $role, route($route), $manifest);
            }
        }
        $this->actingAs($d['guru']);
        $response = $this->get(route('input-nilai.index', ['komponen_nilai_id' => $d['komponen']->id]))->assertOk()->assertSee('value="87,50"', false)->assertDontSee('SISWA RAHASIA KELAS LAIN');
        $this->capture('guru-nilai-terisi', $response->getContent(), 'guru', route('input-nilai.index', ['komponen_nilai_id' => $d['komponen']->id]), $manifest);
        $this->get(route('input-nilai.index', ['komponen_nilai_id' => $d['komponenAsing']->id]))->assertNotFound();
        foreach (['guru', 'tu', 'pimpinan', 'siswa', 'orang_tua'] as $role) {
            $this->assertFalse($d[$role]->administrator());
            $this->actingAs($d[$role])->get(route('peran.index'))->assertStatus($d[$role]->memilikiIzin(['peran.lihat', 'peran.kelola']) ? 200 : 403);
            $this->get(route('cadangan-database.index'))->assertStatus($d[$role]->memilikiIzin('cadangan_database.kelola') ? 200 : 403);
        }
        foreach (['siswa', 'orang_tua'] as $role) {
            $this->actingAs($d[$role])->get(route('persuratan.index'))->assertForbidden();
            $this->get(route('scan-absensi.index'))->assertForbidden();
            $this->get(route('scan-absensi-pegawai.index'))->assertForbidden();
        }
        $this->actingAs($d['orang_tua'])->get(route('presensi-anak.index', ['siswa_id' => $d['asing']->id]))->assertOk()->assertDontSee('SISWA RAHASIA KELAS LAIN');
        $this->writeManifest('manifest.json', $manifest);
    }

    public function test_terminal_scan_memakai_tema_mandiri_dan_tetap_menjaga_kontrol_dan_privasi(): void
    {
        $d = $this->data();
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->actingAs($admin);
        $petugasIbadah = $this->pegawai('guru_pl', 'Guru PL Pemindai');
        $pendamping = $this->pegawai('guru_mapel', 'Guru Pendamping Siswi');
        $pendamping->pegawai->update(['jenis_kelamin' => 'P', 'jenis_pegawai' => 'Guru']);
        PenugasanPendampingIbadahSiswi::create(['tahun_pelajaran_id' => $d['tahun']->id, 'pegawai_id' => $pendamping->pegawai_id, 'semua_kelas' => true, 'aktif' => true, 'ditugaskan_oleh_pengguna_id' => $admin->id]);
        PengaturanAbsensi::updateOrCreate(['hari' => 'senin'], ['urutan_hari' => 1, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '10:00', 'jam_scan_pulang_mulai' => '14:00', 'jam_pulang' => '14:10', 'jam_scan_pulang_selesai' => '16:00', 'aktif' => true]);
        PengaturanAbsensiPegawai::create(['nama_jadwal' => 'Jadwal Pegawai Senin', 'cakupan' => 'semua', 'hari' => 'senin', 'urutan_hari' => 1, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '10:00', 'jam_scan_pulang_mulai' => '14:00', 'jam_pulang' => '14:10', 'jam_scan_pulang_selesai' => '16:00', 'aktif' => true]);
        JadwalKegiatanIbadah::create(['kegiatan_ibadah_id' => KegiatanIbadah::where('kode', 'sholat_duhur')->value('id'), 'tahun_pelajaran_id' => $d['tahun']->id, 'hari' => 'senin', 'urutan_hari' => 1, 'jam_scan_mulai' => '08:00', 'jam_pelaksanaan' => '08:00', 'jam_scan_selesai' => '09:00', 'aktif' => true]);
        $manifest = [];
        foreach (['08:05:00' => 'aktif', '18:00:00' => 'tutup'] as $time => $state) {
            $this->travelTo(now()->setTimeFromTimeString($time));
            foreach (['scan-absensi' => 'siswa', 'scan-absensi-pegawai' => 'pegawai', 'scan-kegiatan-ibadah' => 'ibadah', 'scan-berhalangan-ibadah' => 'privat'] as $route => $kind) {
                $this->actingAs(match ($kind) {
                    'ibadah' => $petugasIbadah, 'privat' => $pendamping, default => $admin
                });
                $response = $this->get(route($route.'.index'))->assertOk();
                $html = $response->getContent();
                $dom = $this->dom($html);
                $this->assertSame(1, $dom->query('//body[@data-scan-kind="'.$kind.'"]')->length);
                $this->assertSame(1, substr_count($html, 'css/nusa-scanner.css'));
                $this->assertStringNotContainsString('css/admin-shell.css', $html);
                if (in_array($kind, ['siswa', 'pegawai'], true)) {
                    $this->assertSame(1, $dom->query('//input[@id="scanInput"][@autofocus]')->length);
                    $this->assertSame(1, $dom->query('//*[@id="resultCard"]')->length);
                } else {
                    $this->assertSame(1, $dom->query('//video[@id="camera-video"][@playsinline]')->length);
                    $this->assertSame(1, $dom->query('//input[@id="manual-nisn"]')->length);
                    $this->assertSame($state === 'aktif' ? 0 : 1, $dom->query('//button[@id="start-camera"][@disabled]')->length);
                    if ($kind === 'privat') {
                        $this->assertSame(0, $dom->query('//*[@id="recent-list"]')->length);
                        $this->assertStringNotContainsString('SISWA RAHASIA KELAS LAIN', $html);
                    }
                }
                $this->capture('scan-'.$kind.'-'.$state, $html, 'scan', '/scan-audit/'.$kind.'/'.$state, $manifest);
            }
        }
        $this->writeManifest('manifest-scan.json', $manifest);
    }

    private function data(): array
    {
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(8, 5));
        TahunPelajaran::query()->update(['aktif' => false]);
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['nama' => 'VIII.A Bilingual', 'tahun_pelajaran_id' => $tahun->id, 'tingkat' => 8, 'kapasitas' => 32, 'aktif' => true]);
        $kelasAsing = Kelas::create(['nama' => 'VIII.B', 'tahun_pelajaran_id' => $tahun->id, 'tingkat' => 8, 'kapasitas' => 32, 'aktif' => true]);
        $guru = $this->pegawai('guru_mapel', 'Guru Muhammad Abdurrahman Harianja, S.Pd., M.Pd.');
        $tu = $this->pegawai('tata_usaha', 'Petugas Tata Usaha');
        $pimpinan = $this->pegawai('pimpinan', 'Kepala Sekolah');
        $lain = $this->pegawai('guru_mapel', 'Guru Kelas Lain');
        $mapel = MataPelajaran::create(['nama' => 'Informatika', 'tingkat' => 8, 'aktif' => true]);
        foreach ([$kelas, $kelasAsing] as $i => $k) {
            $pengampu = GuruMataPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $k->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => ($i ? $lain : $guru)->pegawai_id, 'aktif' => true]);
            $komponenNilai[] = KomponenNilai::create(['guru_mata_pelajaran_id' => $pengampu->id, 'nama' => $i ? 'KOMPONEN RAHASIA GURU LAIN' : 'Sumatif Tengah Semester Semester Ganjil 2026/2027', 'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'aktif' => true]);
        }
        $komponen = $komponenNilai[0];
        $komponenAsing = $komponenNilai[1];
        $anak = [];
        for ($i = 1; $i <= 12; $i++) {
            $s = Siswa::create(['nama_lengkap' => $i === 1 ? 'ANUGRAH EMMANUEL HARIANJA DENGAN NAMA PANJANG' : 'SISWA DAMPINGAN NOMOR '.$i, 'nisn' => sprintf('013%07d', $i), 'jenis_kelamin' => $i % 2 ? 'L' : 'P', 'aktif' => true]);
            AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $s->id, 'nomor_absen' => $i, 'status_keanggotaan' => 'aktif']);
            NilaiSiswa::create(['komponen_nilai_id' => $komponen->id, 'siswa_id' => $s->id, 'nilai' => 87.5]);
            $anak[] = $s;
        }
        $asing = Siswa::create(['nama_lengkap' => 'SISWA RAHASIA KELAS LAIN', 'nisn' => '0139999999', 'aktif' => true]);
        AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelasAsing->id, 'siswa_id' => $asing->id, 'nomor_absen' => 1, 'status_keanggotaan' => 'aktif']);
        $siswa = Pengguna::create(['nama' => $anak[0]->nama_lengkap, 'username' => $anak[0]->nisn, 'siswa_id' => $anak[0]->id, 'peran' => 'siswa', 'kata_sandi' => 'Rahasia123', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $siswa->daftarPeran()->attach(Peran::where('kode', 'siswa')->firstOrFail());
        $orang_tua = Pengguna::create(['nama' => 'Orang Tua Wali Murid', 'username' => 'ORT-'.$anak[0]->nisn, 'peran' => 'orang_tua', 'kata_sandi' => 'Rahasia123', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $orang_tua->daftarPeran()->attach(Peran::where('kode', 'orang_tua')->firstOrFail());
        $wali = OrangTuaWali::create(['pengguna_id' => $orang_tua->id, 'siswa_acuan_username_id' => $anak[0]->id, 'nama_lengkap' => $orang_tua->nama]);
        $wali->siswa()->attach([$anak[0]->id, $anak[1]->id]);

        return compact('tahun', 'guru', 'tu', 'pimpinan', 'siswa', 'orang_tua', 'komponen', 'komponenAsing', 'asing');
    }

    private function pegawai(string $role, string $name): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $name, 'aktif' => true]);
        $akun = Pengguna::create(['pegawai_id' => $pegawai->id, 'nama' => $name, 'username' => $role.'-'.$pegawai->id, 'peran' => 'pegawai', 'kata_sandi' => 'Rahasia123', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->attach(Peran::whereIn('kode', ['pegawai', $role])->pluck('id'));

        return $akun;
    }

    private function capture(string $name, string $html, string $role, string $url, array &$manifest): void
    {
        if (! getenv('NUSA_CAPTURE_ROLE_THEME')) {
            return;
        }
        File::ensureDirectoryExists(storage_path('framework/testing/role-theme'));
        File::put(storage_path('framework/testing/role-theme/'.$name.'.html'), $html);
        $manifest[$role === 'scan' ? $url : '/role-audit/'.$role.parse_url($url, PHP_URL_PATH).(parse_url($url, PHP_URL_QUERY) ? '?'.parse_url($url, PHP_URL_QUERY) : '')] = ['file' => $name, 'role' => $role];
    }

    private function writeManifest(string $name, array $manifest): void
    {
        if (getenv('NUSA_CAPTURE_ROLE_THEME')) {
            File::put(storage_path('framework/testing/role-theme/'.$name), json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
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
