<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\AturanAlfaOtomatisSiswaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlfaOtomatisSiswaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_alfa_otomatis_hanya_hari_aktif_yang_sudah_berakhir_dalam_masa_keanggotaan(): void
    {
        Carbon::setTestNow('2026-10-07 00:00:00');
        $d = $this->fondasi();
        $aturan = app(AturanAlfaOtomatisSiswaService::class);
        $hari = $aturan->hariAktif();
        $this->assertTrue($aturan->menjadiAlfa('2026-10-06', $hari, $d['anggota'][1]));
        foreach (['2026-10-07', '2026-10-08', '2026-10-04', '2026-06-30'] as $tanggal) {
            $this->assertFalse($aturan->menjadiAlfa($tanggal, $hari, $d['anggota'][1]));
        }
        $d['anggota'][1]->update(['tanggal_masuk' => '2026-10-07']);
        $this->assertFalse($aturan->menjadiAlfa('2026-10-06', $hari, $d['anggota'][1]));
        $d['anggota'][1]->update(['tanggal_masuk' => null, 'tanggal_keluar' => '2026-10-05']);
        $this->assertFalse($aturan->menjadiAlfa('2026-10-06', $hari, $d['anggota'][1]));
        $this->assertSame(['2026-10-05', '2026-10-06', '2026-10-07'], $aturan->tanggalEfektif(Carbon::parse('2026-10-04'), Carbon::parse('2026-10-09'), $hari));
    }

    public function test_rekap_harian_berubah_setelah_tengah_malam_tanpa_mengubah_catatan_scan(): void
    {
        Carbon::setTestNow('2026-10-06 23:59:59');
        $d = $this->fondasi();
        $scan = $this->scan($d)->fresh();
        $url = route('rekap-absensi-harian.index', ['tanggal' => '2026-10-06', 'kelas_id' => $d['kelas']->id]);
        $sebelum = $this->actingAs($d['admin'])->get($url)->assertOk()
            ->assertSeeText('Belum dikonfirmasi')
            ->assertViewHas('ringkasan', fn ($r) => $r['alfa'] === 0)
            ->assertViewHas('rekapAbsensi', fn ($r) => $r->last()['status_kehadiran'] === 'belum_scan');
        Carbon::setTestNow('2026-10-07 00:00:00');
        $sesudah = $this->get($url)->assertOk()->assertSeeText('Otomatis: hari berakhir tanpa konfirmasi')
            ->assertViewHas('ringkasan', fn ($r) => $r['alfa'] === 1)
            ->assertViewHas('rekapAbsensi', fn ($r) => $r->last()['status_kehadiran'] === 'alfa' && $r->last()['status_sumber'] === 'otomatis');
        $this->assertSame($scan->toArray(), $scan->fresh()->toArray());
        $this->assertDatabaseCount('absensi_siswa', 1);
        $this->get(route('rekap-absensi-harian.index', ['tanggal' => '2026-10-06', 'kelas_id' => $d['kelas']->id, 'status' => 'alfa']))
            ->assertOk()->assertViewHas('rekapAbsensi', fn ($r) => $r->count() === 1 && $r->first()['anggota_kelas']->is($d['anggota'][1]));
        if (getenv('NUSA_CAPTURE_ALFA_UI')) {
            $dir = storage_path('framework/testing/alfa-otomatis');
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($dir.'/before.html', $sebelum->getContent());
            file_put_contents($dir.'/after.html', $sesudah->getContent());
            $laporan = $this->get(route('laporan-absensi.index', ['periode' => 'harian', 'tanggal' => '2026-10-06', 'kelas_id' => $d['kelas']->id, 'tahun_pelajaran_id' => $d['tahun']->id]))->assertOk();
            file_put_contents($dir.'/report.html', $laporan->getContent());
        }
    }

    public function test_api_rekap_filter_dan_pesan_whatsapp_memakai_alfa_otomatis_dan_koreksi_tetap_berlaku(): void
    {
        Carbon::setTestNow('2026-10-06 23:59:59');
        $d = $this->fondasi();
        $this->scan($d);
        $token = $d['admin']->createToken('Alfa mobile', ['mobile'])->plainTextToken;
        $filter = ['tanggal' => '2026-10-06', 'kelas_id' => $d['kelas']->id, 'tahun_pelajaran_id' => $d['tahun']->id];
        $url = route('api.v1.rekap-presensi-siswa.index', $filter);
        $this->withToken($token)->getJson($url)->assertOk()
            ->assertJsonPath('data.ringkasan.alfa', 0)->assertJsonPath('data.ringkasan.belum_scan', 1);
        Carbon::setTestNow('2026-10-07 00:00:00');
        $this->getJson($url)->assertOk()->assertJsonPath('data.ringkasan.alfa', 1)->assertJsonPath('data.ringkasan.belum_scan', 0)
            ->assertJsonPath('data.items.1.presensi.status', 'alfa')->assertJsonPath('data.items.1.presensi.sumber', 'otomatis');
        $this->getJson(route('api.v1.rekap-presensi-siswa.index', [...$filter, 'status' => 'alfa']))->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.anggota_kelas_id', $d['anggota'][1]->id);
        $this->getJson(route('api.v1.rekap-presensi-siswa.index', [...$filter, 'status' => 'belum_scan']))->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson(route('api.v1.rekap-presensi-siswa.pesan-whatsapp', $filter))->assertOk()
            ->assertJsonPath('data.pesan', fn ($pesan) => str_contains($pesan, 'Alfa: 1') && str_contains($pesan, 'Belum scan: 0'));
        $this->patchJson(route('api.v1.rekap-presensi-siswa.update', $d['anggota'][1]), [
            'tanggal' => '2026-10-06', 'status_kehadiran' => 'izin', 'catatan' => 'Konfirmasi orang tua diterima petugas.',
        ])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data.ringkasan.alfa', 0)->assertJsonPath('data.ringkasan.izin', 1);
        $this->assertDatabaseHas('riwayat_perubahan_absensi_siswa', ['siswa_id' => $d['anggota'][1]->siswa_id, 'status_sesudah' => 'izin']);
    }

    public function test_hari_aktif_tanpa_scan_sama_sekali_dihitung_alfa_tetapi_hari_ini_dan_mendatang_tidak(): void
    {
        Carbon::setTestNow('2026-10-07 10:00:00');
        $d = $this->fondasi();
        $token = $d['admin']->createToken('Laporan alfa', ['mobile'])->plainTextToken;
        $filter = ['periode' => 'harian', 'tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id];
        $this->withToken($token)->getJson(route('api.v1.laporan-presensi-siswa.index', [...$filter, 'tanggal' => '2026-10-06']))
            ->assertOk()->assertJsonPath('data.ringkasan.alfa', 2);
        $this->getJson(route('api.v1.laporan-presensi-siswa.index', [...$filter, 'tanggal' => '2026-10-07']))
            ->assertOk()->assertJsonPath('data.ringkasan.alfa', 0);
        $this->getJson(route('api.v1.laporan-presensi-siswa.show', ['anggotaKelas' => $d['anggota'][1], ...$filter, 'tanggal' => '2026-10-07']))
            ->assertOk()->assertJsonPath('data.rincian.0.status', 'belum_scan')->assertJsonPath('data.rincian.0.status_label', 'Belum dikonfirmasi');
        foreach (['2026-10-08', '2026-10-04', '2026-06-30'] as $tanggal) {
            $this->getJson(route('api.v1.laporan-presensi-siswa.index', [...$filter, 'tanggal' => $tanggal]))
                ->assertOk()->assertJsonPath('data.ringkasan.alfa', 0)->assertJsonPath('data.periode.jumlah_hari_efektif', 0);
        }
        $this->assertDatabaseCount('absensi_siswa', 0);
    }

    public function test_siswa_baru_tidak_mendapat_alfa_sebelum_bergabung_pada_rekap_dan_laporan(): void
    {
        Carbon::setTestNow('2026-10-07 10:00:00');
        $d = $this->fondasi();
        $d['anggota'][1]->update(['tanggal_masuk' => '2026-10-07']);
        $this->scan($d);
        $token = $d['admin']->createToken('Siswa baru', ['mobile'])->plainTextToken;
        $filter = ['tanggal' => '2026-10-06', 'tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id];
        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.index', [...$filter, 'status' => 'alfa']))
            ->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.ringkasan.alfa', 0);
        $this->getJson(route('api.v1.rekap-presensi-siswa.index', [...$filter, 'status' => 'belum_scan']))
            ->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson(route('api.v1.laporan-presensi-siswa.index', [...$filter, 'periode' => 'harian']))
            ->assertOk()->assertJsonPath('data.ringkasan.alfa', 0);
    }

    private function scan(array $d): AbsensiSiswa
    {
        return AbsensiSiswa::create(['tanggal' => '2026-10-06', 'tahun_pelajaran_id' => $d['tahun']->id,
            'kelas_id' => $d['kelas']->id, 'anggota_kelas_id' => $d['anggota'][0]->id, 'siswa_id' => $d['anggota'][0]->siswa_id,
            'status_kehadiran' => 'hadir', 'jam_masuk' => '06:50', 'sumber' => 'scan']);
    }

    private function fondasi(): array
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A Alfa', 'tingkat' => 7, 'aktif' => true]);
        $anggota = collect();
        foreach (['Alya Scan', 'Bima Tanpa Konfirmasi'] as $i => $nama) {
            $siswa = Siswa::create(['nama_lengkap' => $nama, 'nis' => 'ALFA-'.$i, 'nisn' => '770000090'.$i, 'aktif' => true]);
            $anggota->push(AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'nomor_absen' => $i + 1, 'status_keanggotaan' => 'aktif']));
        }
        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat'] as $i => $hari) {
            PengaturanAbsensi::create(['hari' => $hari, 'urutan_hari' => $i + 1, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '08:00', 'jam_scan_pulang_mulai' => '13:00', 'jam_pulang' => '14:00', 'jam_scan_pulang_selesai' => '15:00', 'aktif' => true]);
        }

        return compact('admin', 'tahun', 'kelas', 'anggota');
    }
}
