<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\PengaturanAbsensi;
use App\Models\PengecualianPresensiSiswa;
use App\Models\Pengguna;
use App\Models\RaporStsKelas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\AturanAlfaOtomatisSiswaService;
use App\Services\AkunOrangTuaService;
use App\Services\Nilai\RaporStsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PengecualianPresensiSiswaTest extends TestCase
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

    public function test_pratinjau_menghitung_dampak_tanpa_mengubah_presensi_dan_terapkan_idempoten(): void
    {
        $d = $this->fondasi();
        $sebelum = AbsensiSiswa::get()->toArray();
        $tahunBaru = TahunPelajaran::create(['nama' => '2027/2028', 'tanggal_mulai' => '2027-07-01', 'tanggal_selesai' => '2028-06-30', 'aktif' => false]);
        Kelas::create(['nama' => 'IX.A Tahun Baru', 'tingkat' => 9, 'tahun_pelajaran_id' => $tahunBaru->id, 'aktif' => true]);
        $index = $this->actingAs($d['admin'])->get(route('pengecualian-presensi.index'))->assertOk();
        $this->capture('index', $index->getContent());
        $preview = $this->preview($d)->assertOk()->assertSeeText('Pratinjau pengecualian')
            ->assertViewHas('dampak', fn ($r) => $r['siswa_terdampak'] === 3 && $r['alfa_otomatis_dibatalkan'] === 12
                && $r['catatan_dipertahankan'] === 3 && $r['alfa_manual'] === 1);
        $this->capture('preview', $preview->getContent());
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 0);
        $token = $preview->viewData('token');
        $this->post(route('pengecualian-presensi.store'), ['token' => $token])->assertSessionHasErrors('konfirmasi');
        $this->post(route('pengecualian-presensi.store'), ['token' => $token, 'konfirmasi' => 1])->assertRedirect(route('pengecualian-presensi.index'));
        $this->post(route('pengecualian-presensi.store'), ['token' => $token, 'konfirmasi' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 1);
        $p = PengecualianPresensiSiswa::firstOrFail();
        $this->assertSame($d['admin']->id, $p->dibuat_oleh_pengguna_id);
        $this->assertSame(12, $p->dampak_pratinjau['alfa_otomatis_dibatalkan']);
        $this->assertSame($sebelum, AbsensiSiswa::get()->toArray());
        $history = $this->get(route('pengecualian-presensi.index'))->assertOk()->assertSeeText('Aktif')->assertSeeText('bencana asap');
        $this->capture('history', $history->getContent());
    }

    public function test_cakupan_kelas_konsisten_di_web_api_laporan_dan_pesan_wa(): void
    {
        $d = $this->fondasi();
        $this->terapkan($d, ['kelas_id' => $d['kelas']->id]);
        $filter = ['tanggal' => '2026-09-15', 'tahun_pelajaran_id' => $d['tahun']->id];
        $this->get(route('rekap-absensi-harian.index', $filter))->assertOk()->assertSeeText('PJJ - scan sekolah tidak diwajibkan')
            ->assertViewHas('ringkasan', fn ($r) => $r['alfa'] === 2 && $r['hadir'] === 0)
            ->assertViewHas('rekapAbsensi', fn ($r) => $r->first()['status_kehadiran'] === 'pengecualian');
        $this->get(route('rekap-absensi-harian.index', [...$filter, 'status' => 'alfa']))->assertOk()
            ->assertViewHas('rekapAbsensi', fn ($r) => $r->pluck('anggota_kelas.id')->all() === [$d['anggota'][1]->id, $d['anggota'][2]->id]);
        $token = $d['admin']->createToken('Pengecualian', ['mobile'])->plainTextToken;
        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.index', $filter))->assertOk()
            ->assertJsonPath('data.ringkasan.alfa', 2)->assertJsonPath('data.ringkasan.pengecualian', 1)->assertJsonPath('data.ringkasan.belum_scan', 0);
        foreach (['alfa' => 2, 'pengecualian' => 1, 'belum_scan' => 0] as $status => $jumlah) {
            $this->getJson(route('api.v1.rekap-presensi-siswa.index', [...$filter, 'status' => $status]))
                ->assertOk()->assertJsonCount($jumlah, 'data.items');
        }
        $this->getJson(route('api.v1.rekap-presensi-siswa.pesan-whatsapp', [...$filter, 'kelas_id' => $d['kelas']->id]))->assertOk()
            ->assertJsonPath('data.pesan', fn ($s) => str_contains($s, 'Alfa: 1') && str_contains($s, 'Pengecualian scan (bukan alfa): 1'));
        $laporan = $this->get(route('laporan-absensi.show', ['anggotaKelas' => $d['anggota'][0], ...$this->filterLaporan($d)]))
            ->assertOk()->assertSeeText('PJJ - scan sekolah tidak diwajibkan')
            ->assertViewHas('item', fn ($r) => $r['hadir'] === 1 && $r['alfa'] === 0)
            ->assertViewHas('rincian', fn ($r) => $r->where('status', 'pengecualian')->count() === 4);
        $this->capture('detail', $laporan->getContent());
        $this->getJson(route('api.v1.laporan-presensi-siswa.show', ['anggotaKelas' => $d['anggota'][0], ...$this->filterLaporan($d)]))->assertOk()
            ->assertJsonPath('data.ringkasan.alfa', 0)->assertJsonFragment(['status' => 'pengecualian', 'jenis' => 'pjj']);
        $this->get(route('laporan-absensi.show', ['anggotaKelas' => $d['anggota'][0], ...$this->filterLaporan($d), 'status_rincian' => 'pengecualian']))
            ->assertOk()->assertViewHas('rincian', fn ($r) => $r->count() === 4);
    }

    public function test_pengecualian_global_tidak_memalsukan_hadir_atau_menghapus_alfa_manual(): void
    {
        $d = $this->fondasi();
        $this->terapkan($d);
        $filter = ['tanggal' => '2026-09-15', 'tahun_pelajaran_id' => $d['tahun']->id];
        $token = $d['admin']->createToken('Global', ['mobile'])->plainTextToken;
        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.index', [...$filter, 'status' => 'alfa']))
            ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.anggota_kelas_id', $d['anggota'][1]->id)
            ->assertJsonPath('data.ringkasan.hadir', 0)->assertJsonPath('data.ringkasan.pengecualian', 2);
        $this->getJson(route('api.v1.rekap-presensi-siswa.index', [...$filter, 'status' => 'pengecualian']))->assertOk()->assertJsonCount(2, 'data.items');
        $this->get(route('laporan-absensi.index', $this->filterLaporan($d)))->assertOk()
            ->assertViewHas('ringkasan', fn ($r) => $r['alfa'] === 1 && $r['hadir'] === 1 && $r['sakit'] === 1);
        $this->assertDatabaseCount('absensi_siswa', 3);
    }

    public function test_rapor_menghitung_sumber_baru_tetapi_tidak_menimpa_koreksi_yang_disimpan(): void
    {
        $d = $this->fondasi();
        $kegiatan = KegiatanUjianCbt::create(['jenis_ujian_cbt_id' => JenisUjianCbt::where('kode', 'STS')->value('id'),
            'tahun_pelajaran_id' => $d['tahun']->id, 'kode' => 'STS-EXCEPTION', 'nama' => 'STS Pengecualian',
            'semester' => 'ganjil', 'tanggal_mulai' => '2026-09-14', 'tanggal_selesai' => '2026-09-18', 'status' => 'selesai']);
        $pengaturan = RaporStsKelas::create(['kegiatan_ujian_cbt_id' => $kegiatan->id, 'kelas_id' => $d['kelas']->id,
            'tanggal_awal_presensi' => '2026-09-14', 'tanggal_akhir_presensi' => '2026-09-18', 'tanggal_rapor' => '2026-09-20', 'versi' => 1]);
        $rapor = app(RaporStsService::class)->bangun($kegiatan, $d['kelas'], $pengaturan);
        $sumber = $rapor['baris']->first()['sumber'];
        $this->assertSame(4, $sumber['alfa']);
        $pengaturan->kehadiran()->create(['anggota_kelas_id' => $d['anggota'][0]->id, 'sakit' => 0, 'izin' => 0, 'alfa' => 4,
            'rekap_sumber' => $sumber, 'diperiksa_pada' => now(), 'diperiksa_oleh_pengguna_id' => $d['admin']->id]);
        $this->terapkan($d);
        $baris = app(RaporStsService::class)->bangun($kegiatan, $d['kelas'], $pengaturan)['baris']->first();
        $this->assertSame(0, $baris['sumber']['alfa']);
        $this->assertSame(4, $baris['kehadiran']['alfa']);
        $this->assertTrue($baris['sumber_berubah']);
        $this->assertFalse($baris['diperiksa']);
    }

    public function test_orang_tua_melihat_pengecualian_kelas_anaknya_saja(): void
    {
        $d = $this->fondasi();
        $this->terapkan($d, ['kelas_id' => $d['kelas']->id]);
        $akun = app(AkunOrangTuaService::class)->buat($d['anggota'][0]->siswa);
        $akun->update(['wajib_ganti_kata_sandi' => false]);
        $this->actingAs($akun)->get(route('presensi-anak.index', ['bulan' => '2026-09']))->assertOk()
            ->assertSeeText('PJJ - scan sekolah tidak diwajibkan')
            ->assertViewHas('riwayatSekolah', fn ($r) => $r->where('status', 'pengecualian')->count() === 4);
        $this->get(route('pengecualian-presensi.index'))->assertForbidden();
        $akunB = app(AkunOrangTuaService::class)->buat($d['anggota'][2]->siswa);
        $akunB->update(['wajib_ganti_kata_sandi' => false]);
        $this->actingAs($akunB)->get(route('presensi-anak.index', ['bulan' => '2026-09']))->assertOk()
            ->assertViewHas('riwayatSekolah', fn ($r) => $r->where('status', 'pengecualian')->count() === 0);
    }

    public function test_pembatalan_mengembalikan_alfa_dan_menyimpan_riwayat(): void
    {
        $d = $this->fondasi();
        $token = $this->preview($d)->assertOk()->viewData('token');
        $this->post(route('pengecualian-presensi.store'), ['token' => $token, 'konfirmasi' => 1])->assertRedirect();
        $p = PengecualianPresensiSiswa::firstOrFail();
        $this->post(route('pengecualian-presensi.batalkan', $p), ['konfirmasi' => 1])->assertSessionHasErrors('alasan_pembatalan');
        $alasan = 'Penetapan tanggal keliru, dikembalikan oleh sekolah.';
        $this->post(route('pengecualian-presensi.batalkan', $p), ['konfirmasi' => 1, 'alasan_pembatalan' => $alasan])->assertRedirect();
        $p->refresh();
        $this->assertFalse($p->aktif);
        $this->assertSame($alasan, $p->alasan_pembatalan);
        $this->assertSame($d['admin']->id, $p->dibatalkan_oleh_pengguna_id);
        $sebelum = $p->toArray();
        $this->post(route('pengecualian-presensi.batalkan', $p), ['konfirmasi' => 1, 'alasan_pembatalan' => 'Tidak boleh menimpa alasan yang sudah tercatat.'])->assertRedirect();
        $this->assertSame($sebelum, $p->fresh()->toArray());
        $this->assertTrue(app(AturanAlfaOtomatisSiswaService::class)->menjadiAlfa('2026-09-15', ['selasa'], $d['anggota'][0]));
        $this->get(route('pengecualian-presensi.index'))->assertOk()->assertSeeText('Dibatalkan')->assertSeeText($alasan);
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 1);
        $this->assertDatabaseCount('absensi_siswa', 3);
        $this->post(route('pengecualian-presensi.store'), ['token' => $token, 'konfirmasi' => 1])->assertSessionHasErrors('token');
        $baru = $this->terapkan($d);
        $this->assertTrue($baru->aktif);
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 2);
    }

    public function test_pratinjau_basi_rusak_kedaluwarsa_dan_akun_berbeda_tidak_dapat_diterapkan(): void
    {
        $d = $this->fondasi();
        $token = $this->preview($d)->assertOk()->viewData('token');
        $this->catat($d, 0, '2026-09-15', 'izin');
        $this->post(route('pengecualian-presensi.store'), ['token' => $token, 'konfirmasi' => 1])->assertSessionHasErrors('token');
        $token = $this->preview($d)->assertOk()->viewData('token');
        $this->post(route('pengecualian-presensi.store'), ['token' => 'rusak', 'konfirmasi' => 1])->assertSessionHasErrors('token');
        $this->post(route('pengecualian-presensi.store'), ['token' => Crypt::encryptString('{"fitur_lain":true}'), 'konfirmasi' => 1])->assertSessionHasErrors('token');
        $lain = Pengguna::create(['nama' => 'Admin lain', 'username' => 'admin-exception', 'kata_sandi' => 'test-pass', 'peran' => 'administrator', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $this->actingAs($lain)->post(route('pengecualian-presensi.store'), ['token' => $token, 'konfirmasi' => 1])->assertSessionHasErrors('token');
        Carbon::setTestNow('2026-10-07 10:16:00');
        $this->actingAs($d['admin'])->post(route('pengecualian-presensi.store'), ['token' => $token, 'konfirmasi' => 1])->assertSessionHasErrors('token');
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 0);
    }

    public function test_validasi_tanggal_tahun_kelas_alasan_dan_tumpang_tindih(): void
    {
        $d = $this->fondasi();
        foreach ([
            ['tanggal_mulai' => '2026-06-30'], ['tanggal_selesai' => '2027-07-01'],
            ['tanggal_selesai' => '2026-09-13'], ['alasan' => '   '], ['jenis' => 'hadir_semua'],
        ] as $ubah) {
            $this->preview($d, $ubah)->assertSessionHasErrors();
        }
        $tahunLain = TahunPelajaran::create(['nama' => '2027/2028', 'tanggal_mulai' => '2027-07-01', 'tanggal_selesai' => '2028-06-30', 'aktif' => false]);
        $kelasLain = Kelas::create(['nama' => 'IX.A Baru', 'tingkat' => 9, 'tahun_pelajaran_id' => $tahunLain->id, 'aktif' => true]);
        $this->preview($d, ['kelas_id' => $kelasLain->id])->assertSessionHasErrors('kelas_id');
        $this->terapkan($d, ['kelas_id' => $d['kelas']->id]);
        $this->preview($d)->assertSessionHasErrors('tanggal_mulai');
        $this->preview($d, ['kelas_id' => $d['kelas']->id, 'tanggal_mulai' => '2026-09-18', 'tanggal_selesai' => '2026-09-21'])->assertSessionHasErrors('tanggal_mulai');
        $this->terapkan($d, ['kelas_id' => $d['kelasB']->id]);
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 2);
    }

    public function test_tanggal_mendatang_hari_nonaktif_dan_siswa_baru_tidak_menambah_dampak_alfa(): void
    {
        $d = $this->fondasi();
        $d['anggota'][1]->update(['tanggal_masuk' => '2026-09-19']);
        $this->preview($d, ['kelas_id' => $d['kelas']->id])->assertOk()
            ->assertViewHas('dampak', fn ($r) => $r['siswa_terdampak'] === 1 && $r['alfa_otomatis_dibatalkan'] === 4);
        $this->preview($d, ['tanggal_mulai' => '2026-10-08', 'tanggal_selesai' => '2026-10-09'])->assertOk()
            ->assertViewHas('dampak', fn ($r) => $r['alfa_otomatis_dibatalkan'] === 0);
        $this->preview($d, ['tanggal_mulai' => '2026-09-19', 'tanggal_selesai' => '2026-09-20'])->assertOk()
            ->assertViewHas('dampak', fn ($r) => $r['alfa_otomatis_dibatalkan'] === 0);
    }

    public function test_pengaturan_memerlukan_izin_dan_penanda_html_tidak_dijalankan(): void
    {
        $d = $this->fondasi();
        $this->get(route('pengecualian-presensi.index'))->assertRedirect(route('login'));
        $akun = Pengguna::create(['nama' => 'Tanpa izin', 'username' => 'tanpa-exception', 'kata_sandi' => 'test-pass', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $this->actingAs($akun)->get(route('pengecualian-presensi.index'))->assertForbidden();
        $this->post(route('pengecualian-presensi.pratinjau'), $this->data($d))->assertForbidden();
        $this->post(route('pengecualian-presensi.store'), ['token' => 'x', 'konfirmasi' => 1])->assertForbidden();
        $html = '<script>alert("unsafe")</script> PJJ karena bencana asap.';
        $preview = $this->preview($d, ['alasan' => $html])->assertOk()->assertSee($html)->assertDontSee($html, false);
        $this->post(route('pengecualian-presensi.store'), ['token' => $preview->viewData('token'), 'konfirmasi' => 1])->assertRedirect();
        $this->get(route('pengecualian-presensi.index'))->assertOk()->assertSee($html)->assertDontSee($html, false);
    }

    public function test_pengecualian_dimuat_sekali_per_aturan_bukan_per_siswa_dan_tanggal(): void
    {
        $d = $this->fondasi();
        $this->terapkan($d);
        DB::enableQueryLog();
        $aturan = app(AturanAlfaOtomatisSiswaService::class);
        foreach ($d['anggota'] as $anggota) {
            foreach (['2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18'] as $tanggal) {
                $this->assertFalse($aturan->menjadiAlfa($tanggal, $aturan->hariAktif(), $anggota));
            }
        }
        $jumlah = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'pengecualian_presensi_siswa'))->count();
        DB::disableQueryLog();
        $this->assertSame(1, $jumlah);
    }

    public function test_pengecualian_tidak_bocor_ke_tahun_pelajaran_lain_pada_filter_api(): void
    {
        $d = $this->fondasi();
        $this->terapkan($d);
        $tahun = TahunPelajaran::create(['nama' => 'Tahun Lain', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => false]);
        $kelas = Kelas::create(['nama' => 'IX.A Tahun Lain', 'tingkat' => 9, 'tahun_pelajaran_id' => $tahun->id, 'aktif' => true]);
        $siswa = Siswa::create(['nama_lengkap' => 'Damar Tahun Lain', 'nis' => 'EX-OTHER', 'aktif' => true]);
        $anggota = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'status_keanggotaan' => 'aktif']);
        $token = $d['admin']->createToken('Tahun lain', ['mobile'])->plainTextToken;
        $filter = ['tanggal' => '2026-09-15', 'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'status' => 'alfa'];
        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.index', $filter))->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.anggota_kelas_id', $anggota->id)
            ->assertJsonPath('data.ringkasan.alfa', 1)->assertJsonPath('data.ringkasan.pengecualian', 0);
    }

    private function preview(array $d, array $ubah = [])
    {
        return $this->actingAs($d['admin'])->post(route('pengecualian-presensi.pratinjau'), $this->data($d, $ubah));
    }

    private function terapkan(array $d, array $ubah = []): PengecualianPresensiSiswa
    {
        $preview = $this->preview($d, $ubah)->assertOk();
        $this->post(route('pengecualian-presensi.store'), ['token' => $preview->viewData('token'), 'konfirmasi' => 1])->assertSessionHasNoErrors();

        return PengecualianPresensiSiswa::latest('id')->firstOrFail();
    }

    private function data(array $d, array $ubah = []): array
    {
        return ['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => null, 'tanggal_mulai' => '2026-09-14',
            'tanggal_selesai' => '2026-09-18', 'jenis' => 'pjj', 'alasan' => 'PJJ akibat bencana asap sesuai keputusan sekolah.', ...$ubah];
    }

    private function filterLaporan(array $d): array
    {
        return ['tahun_pelajaran_id' => $d['tahun']->id, 'periode' => 'rentang', 'tanggal_mulai' => '2026-09-14', 'tanggal_selesai' => '2026-09-18'];
    }

    private function catat(array $d, int $i, string $tanggal, string $status): void
    {
        $a = $d['anggota'][$i];
        AbsensiSiswa::create(['tanggal' => $tanggal, 'tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $a->kelas_id,
            'anggota_kelas_id' => $a->id, 'siswa_id' => $a->siswa_id, 'status_kehadiran' => $status, 'sumber' => $status === 'hadir' ? 'scan' : 'manual']);
    }

    private function capture(string $nama, string $html): void
    {
        if (getenv('NUSA_CAPTURE_PENGECUALIAN')) {
            $dir = storage_path('framework/testing/pengecualian-presensi');
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($dir.'/'.$nama.'.html', $html);
        }
    }

    private function fondasi(): array
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['nama' => 'VIII.A', 'tingkat' => 8, 'tahun_pelajaran_id' => $tahun->id, 'aktif' => true]);
        $kelasB = Kelas::create(['nama' => 'VIII.B', 'tingkat' => 8, 'tahun_pelajaran_id' => $tahun->id, 'aktif' => true]);
        $anggota = collect();
        foreach (['Alya Pengecualian', 'Bima Alfa Manual', 'Citra Kelas Lain'] as $i => $nama) {
            $siswa = Siswa::create(['nama_lengkap' => $nama, 'nis' => 'EX-'.$i, 'nisn' => '880000123'.$i, 'aktif' => true]);
            $anggota->push(AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $i === 2 ? $kelasB->id : $kelas->id,
                'siswa_id' => $siswa->id, 'nomor_absen' => $i + 1, 'status_keanggotaan' => 'aktif']));
        }
        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat'] as $i => $hari) {
            PengaturanAbsensi::create(['hari' => $hari, 'urutan_hari' => $i + 1, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '08:00', 'jam_scan_pulang_mulai' => '13:00', 'jam_pulang' => '14:00', 'jam_scan_pulang_selesai' => '15:00', 'aktif' => true]);
        }
        $d = compact('admin', 'tahun', 'kelas', 'kelasB', 'anggota');
        $this->catat($d, 0, '2026-09-14', 'hadir');
        $this->catat($d, 1, '2026-09-15', 'alfa');
        $this->catat($d, 2, '2026-09-16', 'sakit');

        return $d;
    }
}
