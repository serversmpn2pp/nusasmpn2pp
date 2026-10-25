<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\PengaturanAbsensi;
use App\Models\PengecualianPresensiSiswa;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\LaporanPresensiSiswaService;
use App\Support\PenulisExcelLaporanAbsensi;
use Carbon\CarbonPeriod;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class PersentasePengecualianPresensiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(10, 0));
    }

    public function test_september_17_hadir_dan_lima_hari_pjj_menjadi_100_persen_di_web_api_dan_excel(): void
    {
        $d = $this->fondasi();
        $a = $this->anggota($d);
        $this->hadirDiLuarPjj($a);
        $sebelum = $this->laporan($d);
        $this->assertEquals(77.3, $sebelum['laporanAbsensi']->first()['persentase_hadir']);
        $this->pjj($d);
        $catatan = AbsensiSiswa::get()->toArray();
        $web = $this->actingAs($d['admin'])->get(route('laporan-absensi.index', $this->filter($d)))->assertOk()
            ->assertSeeText('17 hari wajib')->assertSeeText('5 hari pengecualian');
        $laporan = $web->viewData('laporanAbsensi')->first();
        foreach (['hari_efektif' => 17, 'hari_kalender' => 22, 'hadir' => 17, 'alfa' => 0, 'pengecualian' => 5, 'persentase_hadir' => 100] as $key => $value) {
            $this->assertEquals($value, $laporan[$key]);
        }
        $token = $d['admin']->createToken('Laporan PJJ', ['mobile'])->plainTextToken;
        $this->withToken($token)->getJson(route('api.v1.laporan-presensi-siswa.index', $this->filter($d)))->assertOk()
            ->assertJsonPath('data.periode.jumlah_hari_efektif', 17)->assertJsonPath('data.periode.jumlah_hari_kalender', 22)
            ->assertJsonPath('data.items.0.ringkasan.persentase_hadir', 100)->assertJsonPath('data.items.0.ringkasan.pengecualian', 5);
        $api = $this->getJson(route('api.v1.laporan-presensi-siswa.show', ['anggotaKelas' => $a, ...$this->filter($d)]))->assertOk()
            ->assertJsonPath('data.ringkasan.hari_efektif', 17)->assertJsonPath('data.ringkasan.hadir', 17)->assertJsonPath('data.ringkasan.alfa', 0);
        $this->assertCount(5, collect($api->json('data.rincian'))->where('status', 'pengecualian'));
        $xlsx = $this->sheet($this->laporan($d));
        $this->assertSame('17', $xlsx->evaluate('string(//s:c[@r="F11"]/s:v)'));
        $this->assertSame('100', $xlsx->evaluate('string(//s:c[@r="O11"]/s:v)'));
        $this->assertSame('5', $xlsx->evaluate('string(//s:c[@r="P11"]/s:v)'));
        $this->assertSame($catatan, AbsensiSiswa::get()->toArray());
        $this->capture('index', $web->getContent());
        $detail = $this->get(route('laporan-absensi.show', ['anggotaKelas' => $a, ...$this->filter($d)]))->assertOk();
        $this->capture('detail', $detail->getContent());
    }

    public function test_angka_pada_gambar_29_siswa_486_hadir_4_alfa_3_sakit_menjadi_98_6_persen(): void
    {
        $d = $this->fondasi();
        $this->pjj($d);
        foreach (range(0, 28) as $i) {
            $a = $this->anggota($d);
            $this->hadirDiLuarPjj($a);
            if ($i < 7) {
                AbsensiSiswa::where('siswa_id', $a->siswa_id)->whereDate('tanggal', '2026-09-30')->update(['status_kehadiran' => $i < 4 ? 'alfa' : 'sakit']);
            }
        }
        $r = $this->laporan($d)['ringkasan'];
        foreach (['siswa' => 29, 'hari_kalender' => 22, 'hari_efektif' => 17, 'total_hari_wajib' => 493, 'pengecualian' => 145, 'hadir' => 486, 'alfa' => 4, 'sakit' => 3, 'rata_persentase_hadir' => 98.6] as $k => $v) {
            $this->assertEquals($v, $r[$k]);
        }
    }

    public function test_pengecualian_per_kelas_dan_hari_wajib_rentang_tetap_jelas_pada_detail_mobile(): void
    {
        $d = $this->fondasi();
        $a = $this->anggota($d);
        $b = $this->anggota($d, true);
        $this->hadirDiLuarPjj($a);
        $this->hadirDiLuarPjj($b);
        $this->pjj($d, ['kelas_id' => $d['kelas']->id]);
        $filter = $this->filter($d);
        unset($filter['kelas_id']);
        $token = $d['admin']->createToken('Dua kelas', ['mobile'])->plainTextToken;
        $r = $this->withToken($token)->getJson(route('api.v1.laporan-presensi-siswa.index', $filter))->assertOk()
            ->assertJsonPath('data.ringkasan.hari_efektif_min', 17)->assertJsonPath('data.ringkasan.hari_efektif', 22);
        $items = collect($r->json('data.items'))->keyBy('anggota_kelas_id');
        $this->assertEquals(100, $items[$a->id]['ringkasan']['persentase_hadir']);
        $this->assertEquals(77.3, $items[$b->id]['ringkasan']['persentase_hadir']);
        $this->getJson(route('api.v1.laporan-presensi-siswa.show', ['anggotaKelas' => $a, ...$filter]))->assertOk()->assertJsonPath('data.periode.jumlah_hari_efektif', 17);
        $this->getJson(route('api.v1.laporan-presensi-siswa.show', ['anggotaKelas' => $b, ...$filter]))->assertOk()->assertJsonPath('data.periode.jumlah_hari_efektif', 22);
    }

    public function test_catatan_manual_pada_hari_pengecualian_tetap_dihitung_tanpa_kehadiran_fiktif(): void
    {
        $d = $this->fondasi();
        $a = $this->anggota($d);
        $this->hadirDiLuarPjj($a);
        $this->pjj($d);
        foreach (['hadir', 'alfa', 'izin', 'sakit'] as $i => $status) {
            $this->catat($a, '2026-09-'.(14 + $i), $status);
        }
        $item = $this->laporan($d)['laporanAbsensi']->first();
        foreach (['hari_efektif' => 21, 'hadir' => 18, 'alfa' => 1, 'izin' => 1, 'sakit' => 1, 'pengecualian' => 1, 'persentase_hadir' => 85.7] as $k => $v) {
            $this->assertEquals($v, $item[$k]);
        }
        $this->assertDatabaseCount('absensi_siswa', 21);
    }

    public function test_seluruh_periode_dikecualikan_tidak_diberi_100_atau_menurunkan_rata_rata_kelas_lain(): void
    {
        $d = $this->fondasi();
        $a = $this->anggota($d);
        $b = $this->anggota($d, true);
        $this->pjj($d, ['kelas_id' => $d['kelas']->id]);
        foreach (range(14, 18) as $day) {
            $this->catat($b, '2026-09-'.$day, 'hadir');
        }
        $filter = [...$this->filter($d), 'periode' => 'rentang', 'tanggal_mulai' => '2026-09-14', 'tanggal_selesai' => '2026-09-18'];
        $web = $this->actingAs($d['admin'])->get(route('laporan-absensi.index', $filter))->assertOk();
        $item = $web->viewData('laporanAbsensi')->first();
        $this->assertSame(0, $item['hari_efektif']);
        $this->assertSame(5, $item['pengecualian']);
        $this->assertSame(0, $item['persentase_hadir']);
        $xlsx = $this->sheet($this->laporan($d, $filter));
        $this->assertSame('-', $xlsx->evaluate('string(//s:c[@r="O11"]/s:is/s:t)'));
        unset($filter['kelas_id']);
        $both = $this->laporan($d, $filter);
        $this->assertEquals(100, $both['ringkasan']['rata_persentase_hadir']);
        $this->capture('all-excluded', $web->getContent());
    }

    public function test_tanggal_masuk_keluar_dan_catatan_kelas_lama_tidak_menambah_pembagi_atau_pembilang(): void
    {
        $d = $this->fondasi();
        $a = $this->anggota($d);
        $a->update(['tanggal_masuk' => '2026-09-21', 'tanggal_keluar' => '2026-09-25']);
        $this->pjj($d);
        foreach (range(21, 25) as $day) {
            $this->catat($a, '2026-09-'.$day, 'hadir');
        }
        $this->catat($a, '2026-09-01', 'hadir');
        $this->catat($a, '2026-09-02', 'hadir');
        AbsensiSiswa::where('siswa_id', $a->siswa_id)->whereDate('tanggal', '2026-09-02')->update(['kelas_id' => $d['kelasB']->id]);
        $filter = $this->filter($d);
        unset($filter['kelas_id']);
        $item = $this->laporan($d, $filter)['laporanAbsensi']->first();
        $this->assertSame(5, $item['hari_efektif']);
        $this->assertSame(5, $item['hadir']);
        $this->assertEquals(100, $item['persentase_hadir']);
        $token = $d['admin']->createToken('Keanggotaan', ['mobile'])->plainTextToken;
        $this->withToken($token)->getJson(route('api.v1.laporan-presensi-siswa.show', ['anggotaKelas' => $a, ...$filter]))->assertOk()->assertJsonCount(5, 'data.rincian');
        $this->assertDatabaseCount('absensi_siswa', 7);
    }

    public function test_pengecualian_batal_tahun_lain_dan_akhir_pekan_tidak_mengurangi_hari_wajib(): void
    {
        $d = $this->fondasi();
        $a = $this->anggota($d);
        $this->hadirDiLuarPjj($a);
        $this->pjj($d, ['aktif' => false]);
        $tahun = TahunPelajaran::create(['nama' => 'Tahun lain', 'aktif' => false]);
        $this->pjj($d, ['tahun_pelajaran_id' => $tahun->id]);
        $this->pjj($d, ['tanggal_mulai' => '2026-09-19', 'tanggal_selesai' => '2026-09-20']);
        $item = $this->laporan($d)['laporanAbsensi']->first();
        $this->assertSame(22, $item['hari_efektif']);
        $this->assertSame(0, $item['pengecualian']);
        $this->assertSame(5, $item['alfa']);
    }

    public function test_catatan_kelas_lama_tidak_menambah_hadir_atau_menghapus_pengecualian_kelas_sekarang(): void
    {
        $d = $this->fondasi();
        $a = $this->anggota($d);
        $this->hadirDiLuarPjj($a);
        $this->pjj($d, ['kelas_id' => $d['kelas']->id]);
        $this->catat($a, '2026-09-15', 'hadir');
        AbsensiSiswa::where('siswa_id', $a->siswa_id)->whereDate('tanggal', '2026-09-15')->update(['kelas_id' => $d['kelasB']->id]);
        $filter = $this->filter($d);
        unset($filter['kelas_id']);
        $item = $this->laporan($d, $filter)['laporanAbsensi']->first();
        $this->assertSame(17, $item['hadir']);
        $this->assertSame(17, $item['hari_efektif']);
        $this->assertSame(5, $item['pengecualian']);
    }

    public function test_periode_harian_semester_dan_rentang_memakai_aturan_yang_sama(): void
    {
        $d = $this->fondasi();
        $a = $this->anggota($d);
        $this->hadirDiLuarPjj($a);
        $this->pjj($d);
        foreach ([['periode' => 'harian', 'tanggal' => '2026-09-15'], ['periode' => 'rentang', 'tanggal_mulai' => '2026-09-14', 'tanggal_selesai' => '2026-09-18']] as $filter) {
            $item = $this->laporan($d, [...$this->filter($d), ...$filter])['laporanAbsensi']->first();
            $this->assertSame(0, $item['hari_efektif']);
            $this->assertSame(0, $item['alfa']);
        }
        $semester = $this->laporan($d, [...$this->filter($d), 'periode' => 'semester', 'semester' => 'ganjil']);
        $item = $semester['laporanAbsensi']->first();
        $this->assertSame(5, $item['pengecualian']);
        $this->assertSame($item['hari_kalender'] - 5, $item['hari_efektif']);
    }

    public function test_pengecualian_dimuat_sekali_dan_laporan_tidak_menulis_presensi(): void
    {
        $d = $this->fondasi();
        $this->pjj($d);
        foreach (range(1, 40) as $i) {
            $this->anggota($d);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->laporan($d);
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(1, $queries->filter(fn ($q) => str_contains($q['query'], 'pengecualian_presensi_siswa'))->count());
        $this->assertLessThanOrEqual(16, $queries->count());
        $this->assertDatabaseCount('absensi_siswa', 0);
    }

    private function fondasi(): array
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['nama' => 'VIII.A Bilingual', 'tingkat' => 8, 'tahun_pelajaran_id' => $tahun->id, 'aktif' => true]);
        $kelasB = Kelas::create(['nama' => 'VIII.B', 'tingkat' => 8, 'tahun_pelajaran_id' => $tahun->id, 'aktif' => true]);
        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat'] as $i => $hari) {
            PengaturanAbsensi::create(['hari' => $hari, 'urutan_hari' => $i + 1, 'jam_scan_masuk_mulai' => '05:30', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '10:00', 'jam_scan_pulang_mulai' => '13:00', 'jam_pulang' => '14:00', 'jam_scan_pulang_selesai' => '16:00', 'aktif' => true]);
        }

        return compact('admin', 'tahun', 'kelas', 'kelasB');
    }

    private function anggota(array $d, bool $lain = false): AnggotaKelas
    {
        $nomor = Siswa::count() + 1;
        $s = Siswa::create(['nama_lengkap' => 'Siswa Uji Persentase '.$nomor, 'aktif' => true]);

        return AnggotaKelas::create(['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d[$lain ? 'kelasB' : 'kelas']->id, 'siswa_id' => $s->id, 'status_keanggotaan' => 'aktif', 'nomor_absen' => $nomor]);
    }

    private function catat(AnggotaKelas $a, string $tanggal, string $status): void
    {
        AbsensiSiswa::create(['tahun_pelajaran_id' => $a->tahun_pelajaran_id, 'kelas_id' => $a->kelas_id, 'anggota_kelas_id' => $a->id, 'siswa_id' => $a->siswa_id, 'tanggal' => $tanggal, 'status_kehadiran' => $status, 'sumber' => 'manual']);
    }

    private function hadirDiLuarPjj(AnggotaKelas $a): void
    {
        foreach (CarbonPeriod::create('2026-09-01', '2026-09-30') as $tgl) {
            if ($tgl->isoWeekday() <= 5 && ($tgl->day < 14 || $tgl->day > 18)) {
                $this->catat($a, $tgl->toDateString(), 'hadir');
            }
        }
    }

    private function pjj(array $d, array $extra = []): void
    {
        PengecualianPresensiSiswa::create(['tahun_pelajaran_id' => $d['tahun']->id, 'tanggal_mulai' => '2026-09-14', 'tanggal_selesai' => '2026-09-18', 'jenis' => 'pjj', 'alasan' => 'Pembelajaran jarak jauh uji', 'aktif' => true, 'kunci_pratinjau' => uniqid('persen-'), 'dampak_pratinjau' => [], ...$extra]);
    }

    private function filter(array $d): array
    {
        return ['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id, 'periode' => 'bulanan', 'bulan' => '2026-09'];
    }

    private function laporan(array $d, ?array $filter = null): array
    {
        $request = Request::create('/laporan-absensi', 'GET', $filter ?? $this->filter($d));
        $request->setUserResolver(fn () => $d['admin']);

        return app(LaporanPresensiSiswaService::class)->bangun($request);
    }

    private function sheet(array $laporan): DOMXPath
    {
        $file = app(PenulisExcelLaporanAbsensi::class)->buat($laporan);
        try {
            $zip = new ZipArchive;
            $zip->open($file);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $doc = new DOMDocument;
            $doc->loadXML($xml);
            $xpath = new DOMXPath($doc);
            $xpath->registerNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            return $xpath;
        } finally {
            File::delete($file);
        }
    }

    private function capture(string $name,string $html): void
    {
        if (! getenv('NUSA_CAPTURE_PERSENTASE')) {
            return;
        }
        $folder = storage_path('framework/testing/persentase-presensi');
        File::ensureDirectoryExists($folder);
        File::put($folder.'/'.$name.'.html',$html);
    }
}
