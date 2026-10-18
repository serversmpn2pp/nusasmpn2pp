<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JenisUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\KomponenNilai;
use App\Models\LaporanPembinaanSiswa;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PesertaUjianCbt;
use App\Models\Siswa;
use App\Models\SuratTu;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TampilanKelompokFiturAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_formulir_dan_ruang_kerja_semua_kelompok_memakai_tema_yang_sama(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(8, 30));
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->actingAs($admin);
        [$komponen, $ujian, $laporan, $surat] = $this->dataOperasional($admin);
        $kelompok = [
            'akademik' => ['mata-pelajaran', 'guru-mata-pelajaran', 'jam-pelajaran', 'jadwal-pelajaran', 'skema-bobot-nilai', 'komponen-nilai', 'input-nilai', 'rekap-nilai-rapor', 'rapor-sts', 'leger-sts', 'jenis-perangkat-ajar', 'pemeriksaan-perangkat-ajar', 'monitoring-survei', 'pertanyaan-survei-pembelajaran'],
            'kehadiran' => ['jadwal-piket-guru', 'kegiatan-ibadah', 'jadwal-kegiatan-ibadah', 'pengaturan-berhalangan-ibadah', 'konfirmasi-berhalangan-ibadah', 'rekap-berhalangan-ibadah', 'rekap-kegiatan-ibadah', 'pengaturan-absensi', 'rekap-absensi-harian', 'laporan-absensi', 'pengaturan-absensi-pegawai', 'rekap-absensi-pegawai-harian', 'laporan-absensi-pegawai-bulanan', 'pengecualian-presensi'],
            'kesiswaan-bk' => ['pusat-verifikasi-pelanggaran', 'laporan-pembinaan-siswa', 'pendampingan-siswa', 'sanksi-poin-siswa', 'peringatan-dini-siswa', 'rekap-poin-siswa', 'pengurangan-poin-siswa', 'penugasan-guru-wali', 'jenis-pelanggaran-siswa', 'aturan-sanksi-poin', 'pengaturan-poin-keterlambatan', 'pengaturan-peringatan-dini-poin', 'pengaturan-batas-proses-pelanggaran', 'penugasan-guru-bk-tingkat', 'kategori-pembinaan-siswa'],
            'ujian-asesmen' => ['pusat-cbt', 'ujian-terpusat', 'paket-soal-terpusat', 'soal-cbt', 'asesmen-kelas-cbt', 'presensi-ujian-cbt'],
            'humas' => ['dashboard-humas', 'akreditasi-humas', 'umpan-balik-humas', 'dokumen-humas', 'program-kerja-humas', 'alumni-humas', 'prestasi-sekolah', 'komite-humas', 'agenda-humas', 'buku-tamu', 'kemitraan-humas', 'publikasi-humas', 'aset-promosi-humas', 'media-resmi-humas', 'kliping-berita-humas', 'pengaduan-humas'],
            'supervisi' => ['supervisi', 'supervisi.program', 'supervisi.instrumen', 'supervisi.rekap'],
            'persuratan' => ['persuratan'],
            'sarpras' => ['dashboard-sarana-prasarana', 'barang', 'unit-barang', 'label-barcode-inventaris', 'penerimaan-barang', 'saldo-stok-barang', 'mutasi-stok-barang', 'peminjaman-barang', 'pengajuan-barang', 'pengembalian-barang', 'rekap-peminjaman-barang', 'laporan-inventaris-bulanan', 'kategori-barang', 'satuan-barang', 'lokasi-barang', 'sumber-perolehan-barang', 'pengaturan-inventaris'],
            'sistem' => ['akun-pegawai', 'akun-siswa', 'akun-orang-tua', 'peran', 'aktivitas-login', 'cadangan-database'],
        ];
        $pages = [];
        foreach ($kelompok as $group => $routes) {
            foreach ($routes as $route) {
                $pages[] = [$route.'.index', [], $group];
            }
        }
        foreach (['mata-pelajaran', 'guru-mata-pelajaran', 'komponen-nilai', 'pengaturan-absensi', 'jadwal-piket-guru', 'laporan-pembinaan-siswa', 'soal-cbt', 'asesmen-kelas-cbt', 'dokumen-humas', 'agenda-humas', 'supervisi.program', 'supervisi.instrumen', 'persuratan', 'barang', 'penerimaan-barang', 'peran'] as $route) {
            $group = collect($kelompok)->search(fn ($routes) => in_array($route, $routes, true));
            $pages[] = [$route.'.create', [], $group];
        }
        $pages = [...$pages,
            ['tindak-lanjut-surat.monitoring', [], 'persuratan'],
            ['input-nilai.index', ['komponen_nilai_id' => $komponen->id], 'akademik'],
            ['ujian-cbt.monitoring.index', [$ujian], 'ujian-asesmen'],
            ['ujian-cbt.hasil.index', [$ujian], 'ujian-asesmen'],
            ['laporan-pembinaan-siswa.show', [$laporan], 'kesiswaan-bk'],
            ['persuratan.show', [$surat], 'persuratan'],
        ];
        $manifest = [];
        foreach ($pages as $i => [$route, $params, $group]) {
            $response = $this->get(route($route, $params));
            $this->assertSame(200, $response->status(), $route.' returned '.$response->status());
            $html = $response->getContent();
            $dom = $this->dom($html);
            $this->assertSame(1, $dom->query('//body[@data-admin-module="'.$group.'"]')->length, $route);
            $this->assertSame(1, substr_count($html, 'css/admin-feature-pages.css'), $route);
            $this->assertSame(1, $dom->query('//aside[@id="admin-sidebar"]')->length, $route);
            $this->assertStringNotContainsString('css/admin-data-sekolah.css', $html, $route);
            foreach ($dom->query('//form[translate(@method,"post","POST")="POST"]') as $form) {
                $this->assertSame(1, $dom->query('.//input[@name="_token"]', $form)->length, $route.' CSRF');
            }
            if ($route === 'input-nilai.index' && isset($params['komponen_nilai_id'])) {
                $this->assertSame(24, $dom->query('//*[@data-grade-form]//input[@inputmode="decimal"][@aria-label]')->length);
                $this->assertSame(1, $dom->query('//*[@aria-label="Daftar nilai siswa"][@tabindex="0"]')->length);
                $this->assertStringContainsString('value="87,50"', $html);
            }
            if ($route === 'ujian-cbt.monitoring.index') {
                $this->assertSame(1, $dom->query('//*[@aria-label="Pemantauan peserta ujian"][@tabindex="0"]')->length);
                $this->assertStringContainsString('Sedang mengerjakan', $html);
            }
            if (getenv('NUSA_CAPTURE_FEATURE_GROUPS')) {
                $name = sprintf('%03d-%s', $i, str_replace('.', '-', $route));
                File::ensureDirectoryExists(storage_path('framework/testing/feature-groups'));
                File::put(storage_path('framework/testing/feature-groups/'.$name.'.html'), $html);
                $url = parse_url(route($route, $params));
                $manifest[$url['path'].(isset($url['query']) ? '?'.$url['query'] : '')] = ['file' => $name, 'group' => $group];
            }
        }
        if (getenv('NUSA_CAPTURE_FEATURE_GROUPS')) {
            File::put(storage_path('framework/testing/feature-groups/manifest.json'), json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }

    public function test_guru_memakai_tema_bersama_sedangkan_login_dan_data_sekolah_terpisah(): void
    {
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail())
            ->get(route('tahun-pelajaran.index'))->assertOk()->assertDontSee('css/admin-feature-pages.css');
        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru Tema', 'aktif' => true]);
        $guru = Pengguna::create(['nama' => 'Guru Tema', 'username' => 'guru-tema', 'pegawai_id' => $pegawai->id, 'kata_sandi' => 'Rahasia123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $guru->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());
        $this->actingAs($guru)->get(route('input-nilai.index'))->assertOk()->assertSee('css/admin-feature-pages.css')->assertSee('data-nusa-theme');
        $this->get(route('peran.index'))->assertForbidden();
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertDontSee('css/admin-feature-pages.css');
    }

    private function dataOperasional(Pengguna $admin): array
    {
        TahunPelajaran::query()->update(['aktif' => false]);
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.A Bilingual', 'tingkat' => 8, 'kapasitas' => 32, 'aktif' => true]);
        $pegawai = Pegawai::create(['nama_lengkap' => 'Antonius Pitra Dana Arista, M.T.', 'aktif' => true]);
        $mapel = MataPelajaran::create(['nama' => 'Informatika', 'tingkat' => 8, 'aktif' => true]);
        $guru = GuruMataPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $pegawai->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
        $komponen = KomponenNilai::create(['guru_mata_pelajaran_id' => $guru->id, 'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'nama' => 'Sumatif Tengah Semester Semester Ganjil 2026/2027', 'aktif' => true]);
        $ujian = UjianCbt::create(['alur' => 'asesmen_kelas', 'jenis_ujian_cbt_id' => JenisUjianCbt::where('kode', 'STS')->value('id'), 'tahun_pelajaran_id' => $tahun->id, 'mata_pelajaran_id' => $mapel->id, 'kode' => 'CBT-TEMA-001', 'nama' => 'Asesmen Informatika Semester Ganjil 2026/2027', 'semester' => 'ganjil', 'tingkat' => 8, 'tanggal_mulai' => now()->subMinutes(20), 'tanggal_selesai' => now()->addHour(), 'durasi_menit' => 90, 'jumlah_soal' => 30, 'status' => 'berlangsung', 'token' => '777555', 'dibuat_oleh_pengguna_id' => $admin->id]);
        $kelasUjian = KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $kelas->id, 'komponen_nilai_id' => $komponen->id]);
        for ($i = 1; $i <= 24; $i++) {
            $siswa = Siswa::create(['nama_lengkap' => $i === 1 ? 'ANUGRAH EMMANUEL HARIANJA DENGAN NAMA PANJANG' : 'SISWA KELAS VIII NOMOR '.$i, 'nisn' => sprintf('013%07d', $i), 'nis' => '2600'.$i, 'jenis_kelamin' => $i % 2 ? 'L' : 'P', 'aktif' => true]);
            $anggota = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'nomor_absen' => $i, 'aktif' => true]);
            NilaiSiswa::create(['komponen_nilai_id' => $komponen->id, 'siswa_id' => $siswa->id, 'nilai' => 87.5, 'catatan' => 'Diterapkan dari CBT dan dapat dikoreksi oleh guru.']);
            AbsensiSiswa::create(['tanggal' => today(), 'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'anggota_kelas_id' => $anggota->id, 'siswa_id' => $siswa->id, 'status_kehadiran' => 'hadir', 'jam_masuk' => '07:12', 'status_masuk' => 'terlambat', 'menit_terlambat' => 12, 'sumber' => 'scan']);
            PesertaUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_ujian_cbt_id' => $kelasUjian->id, 'anggota_kelas_id' => $anggota->id, 'nomor_peserta' => 'P-'.$i, 'status' => 'sedang_mengerjakan', 'status_kehadiran_ujian' => 'hadir', 'waktu_mulai' => now()->subMinutes(10)]);
        }
        $laporan = LaporanPembinaanSiswa::create(['nomor_laporan' => 'LP-TEMA-001', 'jenis_laporan' => 'pelanggaran', 'tanggal_kejadian' => today(), 'siswa_id' => Siswa::first()->id, 'tingkat' => 'sedang', 'status' => 'baru', 'status_verifikasi' => 'diajukan', 'total_poin' => 0, 'kronologi' => 'Keterangan kejadian untuk pemeriksaan dan tindak lanjut.', 'dibuat_oleh_pengguna_id' => $admin->id]);
        $surat = SuratTu::create(['jenis' => 'masuk', 'nomor_surat' => '421/123/SMP/2026', 'nomor_unik' => hash('sha256', 'surat-tema'), 'tanggal_surat' => today(), 'tanggal_diterima' => today(), 'perihal' => 'Undangan koordinasi pelaksanaan kegiatan pembelajaran semester ganjil', 'pihak' => 'Dinas Pendidikan Kota Padang Panjang', 'status' => 'tercatat', 'dibuat_oleh_pengguna_id' => $admin->id, 'diubah_oleh_pengguna_id' => $admin->id]);

        return [$komponen, $ujian, $laporan, $surat];
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
