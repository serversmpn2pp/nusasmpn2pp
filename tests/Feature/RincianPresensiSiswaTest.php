<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RincianPresensiSiswaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_tombol_rincian_membawa_periode_dan_rincian_menampilkan_hari_status_dan_total_terlambat(): void
    {
        $d = $this->fondasi();
        $filter = $this->filter($d);
        $sebelum = AbsensiSiswa::get()->toArray();
        $index = $this->actingAs($d['admin'])->get(route('laporan-absensi.index', $filter))->assertOk()
            ->assertSee(route('laporan-absensi.show', ['anggotaKelas' => $d['anggota'], ...$filter]));
        $this->assertSame(4, substr_count($index->getContent(), 'data-presensi-rincian'));
        $detail = $this->get($this->url($d))->assertOk()->assertSeeText('Rincian presensi')
            ->assertSeeText('Senin')->assertSeeText('28 September 2026')->assertSeeText('Selasa')
            ->assertSeeText('Sakit')->assertSeeText('Izin')->assertSeeText('Alfa otomatis: hari berakhir tanpa konfirmasi')
            ->assertSeeText('12 menit')->assertSeeText('5 menit')->assertSeeText('Total terlambat')
            ->assertSeeText('Belum scan pulang')->assertDontSeeText('CATATAN-SISWA-LAIN')
            ->assertDontSeeText('DI-LUAR-RENTANG')
            ->assertSee('<script>alert("catatan")</script>')->assertDontSee('<script>alert("catatan")</script>', false)
            ->assertViewHas('item', fn ($item) => $item['hadir'] === 3 && $item['sakit'] === 1 && $item['izin'] === 1
                && $item['alfa'] === 2 && $item['terlambat'] === 2 && $item['menit_terlambat'] === 17)
            ->assertViewHas('rincian', fn ($r) => $r->count() === 8 && $r->first()['tanggal'] === '2026-10-07'
                && $r->first()['status'] === 'belum_scan' && $r->firstWhere('tanggal', '2026-10-06')['alfa_otomatis']
                && ! $r->firstWhere('tanggal', '2026-09-30')['alfa_otomatis'])
            ->assertHeader('Cache-Control');
        $this->assertStringContainsString('no-store', $detail->headers->get('Cache-Control'));
        $this->assertSame($sebelum, AbsensiSiswa::get()->toArray());
        $this->capture('index', $index->getContent());
        $this->capture('detail', $detail->getContent());
    }

    public function test_filter_status_dan_terlambat_hanya_memilih_hari_yang_sesuai_tanpa_mengubah_ringkasan(): void
    {
        $d = $this->fondasi();
        $this->actingAs($d['admin']);
        foreach ([
            'sakit' => ['2026-09-28'], 'izin' => ['2026-09-29'], 'alfa' => ['2026-10-06', '2026-09-30'],
            'terlambat' => ['2026-10-02', '2026-10-01'], 'hadir' => ['2026-10-05', '2026-10-02', '2026-10-01'],
            'belum_scan' => ['2026-10-07'],
        ] as $status => $tanggal) {
            $response = $this->get($this->url($d, ['status_rincian' => $status]))->assertOk()
                ->assertViewHas('statusRincian', $status)
                ->assertViewHas('rincian', fn ($r) => $r->pluck('tanggal')->all() === $tanggal)
                ->assertViewHas('item', fn ($item) => $item['terlambat'] === 2 && $item['menit_terlambat'] === 17);
            $this->capture($status, $response->getContent());
        }
        $this->get($this->url($d, ['status_rincian' => 'rahasia']))->assertSessionHasErrors('status_rincian');
    }

    public function test_bulanan_mengikuti_bulan_dan_rentang_kosong_tidak_mengarang_kehadiran(): void
    {
        $d = $this->fondasi();
        $this->actingAs($d['admin'])->get($this->url($d, ['periode' => 'bulanan', 'bulan' => '2026-10']))
            ->assertOk()->assertViewHas('rincian', fn ($r) => $r->count() === 5 && $r->every(fn ($row) => str_starts_with($row['tanggal'], '2026-10')))
            ->assertViewHas('item', fn ($item) => $item['sakit'] === 0 && $item['izin'] === 0 && $item['alfa'] === 1);
        foreach ([
            ['tanggal_mulai' => '2026-10-10', 'tanggal_selesai' => '2026-10-12'],
            ['tanggal_mulai' => '2026-10-04', 'tanggal_selesai' => '2026-10-04'],
            ['tanggal_mulai' => '2026-06-29', 'tanggal_selesai' => '2026-06-30'],
        ] as $rentang) {
            $response = $this->get($this->url($d, $rentang))->assertOk()
                ->assertSeeText('Tidak ada riwayat presensi pada periode ini.')
                ->assertViewHas('rincian', fn ($r) => $r->isEmpty())
                ->assertViewHas('item', fn ($item) => $item['alfa'] === 0);
            $this->capture('empty', $response->getContent());
        }
        $this->get($this->url($d, ['tanggal_mulai' => '2026-10-07', 'tanggal_selesai' => '2026-10-01']))
            ->assertSessionHasErrors('tanggal_selesai');
    }

    public function test_rincian_web_dan_api_sama_serta_koreksi_manual_menggantikan_alfa_otomatis(): void
    {
        $d = $this->fondasi();
        $response = $this->actingAs($d['admin'])->get($this->url($d))->assertOk();
        $token = $d['admin']->createToken('Rincian presensi', ['mobile'])->plainTextToken;
        $urlApi = route('api.v1.laporan-presensi-siswa.show', ['anggotaKelas' => $d['anggota'], ...$this->filter($d)]);
        $api = $this->withToken($token)->getJson($urlApi)->assertOk()
            ->assertJsonPath('data.ringkasan.menit_terlambat', 17)
            ->assertJsonFragment(['tanggal' => '2026-10-06', 'alfa_otomatis' => true]);
        $this->assertSame($response->viewData('rincian')->sortBy('tanggal')->values()->all(), $api->json('data.rincian'));
        $this->catat($d, '2026-10-06', 'izin', ['catatan' => 'Konfirmasi wali siswa.']);
        $this->get($this->url($d, ['status_rincian' => 'alfa']))->assertOk()
            ->assertViewHas('rincian', fn ($r) => $r->pluck('tanggal')->all() === ['2026-09-30'])
            ->assertViewHas('item', fn ($item) => $item['alfa'] === 1 && $item['izin'] === 2);
        $this->getJson($urlApi)->assertOk()->assertJsonPath('data.ringkasan.alfa', 1)->assertJsonPath('data.ringkasan.izin', 2);
    }

    public function test_wali_kelas_dan_filter_kelas_tidak_bisa_membuka_siswa_di_luar_cakupan(): void
    {
        $d = $this->fondasi();
        $pegawai = Pegawai::create(['nama_lengkap' => 'Wali Rincian', 'aktif' => true]);
        $wali = Pengguna::create(['nama' => 'Wali Rincian', 'username' => 'wali-rincian', 'kata_sandi' => 'test-pass',
            'pegawai_id' => $pegawai->id, 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $wali->daftarPeran()->attach(Peran::where('kode', 'wali_kelas')->firstOrFail());
        $d['kelas']->update(['wali_kelas_id' => $pegawai->id]);
        $kelasLain = Kelas::create(['nama' => 'VIII.B Rincian', 'tingkat' => 8, 'tahun_pelajaran_id' => $d['tahun']->id, 'aktif' => true]);
        $d['lain']->update(['kelas_id' => $kelasLain->id]);
        $this->actingAs($wali)->get($this->url($d))->assertOk();
        $this->get(route('laporan-absensi.show', ['anggotaKelas' => $d['lain'], ...$this->filter($d), 'kelas_id' => $kelasLain->id]))->assertNotFound();
        $this->actingAs($d['admin'])->get($this->url($d, ['kelas_id' => $kelasLain->id]))->assertNotFound();
        $tahunLain = TahunPelajaran::create(['nama' => '2027/2028', 'tanggal_mulai' => '2027-07-01', 'aktif' => false]);
        $this->get($this->url($d, ['tahun_pelajaran_id' => $tahunLain->id]))->assertNotFound();
        $d['anggota']->update(['status_keanggotaan' => 'keluar']);
        $this->get($this->url($d))->assertNotFound();
    }

    public function test_rincian_memerlukan_login_dan_izin_laporan(): void
    {
        $d = $this->fondasi();
        $this->get($this->url($d))->assertRedirect(route('login'));
        $akun = Pengguna::create(['nama' => 'Tanpa izin', 'username' => 'tanpa-izin-rincian', 'kata_sandi' => 'test-pass',
            'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $this->actingAs($akun)->get($this->url($d))->assertForbidden();
    }

    private function filter(array $d): array
    {
        return ['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id, 'periode' => 'rentang',
            'tanggal' => '2026-10-07', 'bulan' => '2026-10', 'semester' => 'ganjil', 'tanggal_mulai' => '2026-09-28', 'tanggal_selesai' => '2026-10-07'];
    }

    private function url(array $d, array $filter = []): string
    {
        return route('laporan-absensi.show', ['anggotaKelas' => $d['anggota'], ...$this->filter($d), ...$filter]);
    }

    private function capture(string $nama, string $html): void
    {
        if (! getenv('NUSA_CAPTURE_RINCIAN_PRESENSI')) {
            return;
        }
        $dir = storage_path('framework/testing/rincian-presensi');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/'.$nama.'.html', $html);
    }

    private function catat(array $d, string $tanggal, string $status, array $tambahan = []): void
    {
        AbsensiSiswa::create(['tanggal' => $tanggal, 'tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id,
            'anggota_kelas_id' => $d['anggota']->id, 'siswa_id' => $d['anggota']->siswa_id, 'status_kehadiran' => $status,
            'sumber' => $status === 'hadir' ? 'scan' : 'manual', ...$tambahan]);
    }

    private function fondasi(): array
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.A Rincian', 'tingkat' => 8, 'aktif' => true]);
        $anggota = null;
        $lain = null;
        foreach (['Alya Siswa Rincian', 'Bima Siswa Lain'] as $i => $nama) {
            $siswa = Siswa::create(['nama_lengkap' => $nama, 'nis' => 'RINC-'.$i, 'nisn' => '770000011'.$i, 'aktif' => true]);
            $a = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'nomor_absen' => $i + 1, 'status_keanggotaan' => 'aktif']);
            if ($i === 0) {
                $anggota = $a;
            } else {
                $lain = $a;
            }
        }
        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat'] as $i => $hari) {
            PengaturanAbsensi::create(['hari' => $hari, 'urutan_hari' => $i + 1, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '08:00', 'jam_scan_pulang_mulai' => '13:00', 'jam_pulang' => '14:00', 'jam_scan_pulang_selesai' => '15:00', 'aktif' => true]);
        }
        $d = compact('admin', 'tahun', 'kelas', 'anggota', 'lain');
        $this->catat($d, '2026-09-28', 'sakit', ['catatan' => 'Demam, surat orang tua diterima. <script>alert("catatan")</script>']);
        $this->catat($d, '2026-09-29', 'izin', ['catatan' => 'Keperluan keluarga.']);
        $this->catat($d, '2026-09-30', 'alfa', ['catatan' => 'Tidak hadir, dikonfirmasi wali kelas.']);
        $this->catat($d, '2026-10-01', 'hadir', ['jam_masuk' => '07:12', 'menit_terlambat' => 12, 'catatan' => 'Kendaraan terlambat.']);
        $this->catat($d, '2026-10-02', 'hadir', ['jam_masuk' => '07:05', 'jam_pulang' => '14:00', 'menit_terlambat' => 5]);
        $this->catat($d, '2026-10-05', 'hadir', ['jam_masuk' => '06:55', 'jam_pulang' => '14:00']);
        $this->catat($d, '2026-09-25', 'sakit', ['catatan' => 'DI-LUAR-RENTANG']);
        $this->catat([...$d, 'anggota' => $lain], '2026-10-01', 'sakit', ['catatan' => 'CATATAN-SISWA-LAIN']);

        return $d;
    }
}
