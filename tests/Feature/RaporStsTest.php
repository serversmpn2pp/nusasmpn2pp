<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\KehadiranRaporSts;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\PengecualianRaporSts;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PesertaUjianCbt;
use App\Models\RaporStsKelas;
use App\Models\Siswa;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use App\Services\Absensi\KoreksiPresensiSiswaService;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use App\Services\Cbt\PengacakPenyajianCbt;
use App\Services\Cbt\TerapkanNilaiCbtService;
use App\Services\Nilai\LegerStsService;
use App\Services\Nilai\RaporStsService;
use App\Services\Nilai\StsManualService;
use Carbon\Carbon;
use PDO;
use Tests\TestCase;

class RaporStsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite diperlukan.');
        }
        $this->artisan('migrate:fresh');
        Carbon::setTestNow('2026-09-26 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_keterlambatan_rapor_mengikuti_periode_siswa_dan_tahun_pelajaran(): void
    {
        $d = $this->fondasi();
        $tahunLain = TahunPelajaran::create(['nama' => '2025/2026', 'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'aktif' => false]);
        foreach ([
            ['2026-08-02', 'hadir', 500],
            ['2026-08-03', 'hadir', 10],
            ['2026-08-04', 'hadir', 0],
            ['2026-08-05', 'hadir', 0],
            ['2026-08-06', 'sakit', 11],
            ['2026-08-07', 'izin', 13],
            ['2026-08-08', 'alfa', 17],
            ['2026-08-09', 'hadir', 123, $tahunLain->id],
            ['2026-08-10', 'hadir', 25],
            ['2026-08-11', 'hadir', 400],
        ] as $record) {
            [$tanggal, $status, $menit] = $record;
            AbsensiSiswa::create(['tanggal' => $tanggal, 'tahun_pelajaran_id' => $record[3] ?? $d['tahun']->id,
                'kelas_id' => $d['kelas']->id, 'anggota_kelas_id' => $d['anggota'][0]->id,
                'siswa_id' => $d['anggota'][0]->siswa_id, 'status_kehadiran' => $status, 'menit_terlambat' => $menit]);
        }
        AbsensiSiswa::create(['tanggal' => '2026-08-03', 'tahun_pelajaran_id' => $d['tahun']->id,
            'kelas_id' => $d['kelas']->id, 'anggota_kelas_id' => $d['anggota'][1]->id,
            'siswa_id' => $d['anggota'][1]->siswa_id, 'status_kehadiran' => 'hadir', 'menit_terlambat' => 12]);
        $sebelum = AbsensiSiswa::orderBy('id')->get()->toArray();
        $this->simpanPeriode($d, ['tanggal_awal_presensi' => '2026-08-03', 'tanggal_akhir_presensi' => '2026-08-10']);
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(['jumlah' => 2, 'total_menit' => 35], $laporan['baris'][0]['keterlambatan']);
        $this->assertSame(['jumlah' => 1, 'total_menit' => 12], $laporan['baris'][1]['keterlambatan']);
        $this->assertSame(['sakit' => 1, 'izin' => 1, 'alfa' => 1], $laporan['baris'][0]['kehadiran']);

        $this->simpanPeriode($d, ['tanggal_awal_presensi' => '2026-08-07', 'tanggal_akhir_presensi' => '2026-08-10']);
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(['jumlah' => 1, 'total_menit' => 25], $laporan['baris'][0]['keterlambatan']);
        $this->assertSame(['jumlah' => 0, 'total_menit' => 0], $laporan['baris'][1]['keterlambatan']);
        $this->assertSame($sebelum, AbsensiSiswa::orderBy('id')->get()->toArray());
    }

    public function test_keterlambatan_tampil_di_rekap_pratinjau_dan_cetak_serta_mengikuti_koreksi_presensi(): void
    {
        $d = $this->fondasi();
        PengaturanAbsensi::create(['hari' => 'selasa', 'urutan_hari' => 2, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '08:00', 'jam_scan_pulang_mulai' => '13:00', 'jam_pulang' => '14:00', 'jam_scan_pulang_selesai' => '15:00', 'aktif' => true]);
        foreach (['2026-08-04' => 15, '2026-08-07' => 20] as $tanggal => $menit) {
            AbsensiSiswa::create(['tanggal' => $tanggal, 'tahun_pelajaran_id' => $d['tahun']->id,
                'kelas_id' => $d['kelas']->id, 'anggota_kelas_id' => $d['anggota'][0]->id,
                'siswa_id' => $d['anggota'][0]->siswa_id, 'status_kehadiran' => 'hadir', 'status_masuk' => 'terlambat',
                'jam_masuk' => '07:'.$menit, 'jam_pulang' => null, 'menit_terlambat' => $menit, 'sumber' => 'scan']);
        }
        $sebelum = AbsensiSiswa::orderBy('id')->get()->toArray();
        $this->simpanPeriode($d, ['tanggal_awal_presensi' => '2026-08-04', 'tanggal_akhir_presensi' => '2026-08-07']);
        $payload = $this->payload($d);
        $payload['siswa'][$d['anggota'][0]->id]['sakit'] = 1;
        $payload['siswa'][$d['anggota'][0]->id]['catatan_koreksi'] = 'Koreksi sakit khusus rapor.';
        $payload['siswa'][$d['anggota'][0]->id]['keterlambatan'] = ['jumlah' => 999, 'total_menit' => 999];
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasNoErrors();
        $snapshot = KehadiranRaporSts::orderBy('id')->get()->toArray();
        $this->assertSame(['awal' => '2026-08-04', 'akhir' => '2026-08-07', 'sakit' => 0, 'izin' => 0, 'alfa' => 0, 'hari_tercatat' => 2], $snapshot[0]['rekap_sumber']);

        $index = $this->get(route('rapor-sts.index'))->assertOk()->assertSeeText('Total keterlambatan')
            ->assertSee('<span data-sts-late-count>2</span> kali', false)
            ->assertSee('<span data-sts-late-minutes>35</span> menit', false)
            ->assertSee('<span data-sts-late-count>0</span> kali', false)
            ->assertSee('<span data-sts-late-minutes>0</span> menit', false);
        $url = route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][0]->id]);
        $cetak = $this->get($url)->assertOk()->assertSee('data-sts-late-count>2</span>', false)
            ->assertSee('data-sts-late-minutes>35</span>', false)
            ->assertViewHas('baris', fn ($baris) => $baris->count() === 1 && $baris[0]['siap'] && $baris[0]['kehadiran']['sakit'] === 1);
        $pratinjau = $this->get($url.'&pratinjau=1')->assertOk()->assertSeeText('DRAF PRATINJAU')
            ->assertViewHas('baris', fn ($baris) => $baris[0]['keterlambatan'] === ['jumlah' => 2, 'total_menit' => 35]);
        $this->assertSame($sebelum, AbsensiSiswa::orderBy('id')->get()->toArray());
        $this->assertSame($snapshot, KehadiranRaporSts::orderBy('id')->get()->toArray());

        app(KoreksiPresensiSiswaService::class)->koreksi($d['admin'], $d['anggota'][0], [
            'tanggal' => '2026-08-04', 'status_kehadiran' => 'hadir', 'jam_masuk' => '07:05',
            'catatan' => 'Jam datang dikoreksi sesuai catatan petugas.',
        ]);
        $this->get($url)->assertOk()->assertSee('data-sts-late-minutes>25</span>', false)
            ->assertViewHas('baris', fn ($baris) => $baris[0]['keterlambatan'] === ['jumlah' => 2, 'total_menit' => 25]
                && $baris[0]['diperiksa'] && ! $baris[0]['sumber_berubah'] && $baris[0]['kehadiran']['sakit'] === 1);
        $this->assertSame($snapshot, KehadiranRaporSts::orderBy('id')->get()->toArray());
    }

    public function test_rekap_koreksi_dan_cetak_menjaga_presensi_asli_dan_nilai_sts(): void
    {
        $d = $this->fondasi();
        $this->actingAs($d['admin'])->get(route('rapor-sts.index'))->assertOk()
            ->assertSeeText('Pemeriksaan nilai dan kehadiran')->assertSeeText('Periode belum disimpan');
        $this->assertDatabaseCount('rapor_sts_kelas', 0);
        $this->simpanPeriode($d);
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(100.0, $laporan['baris'][0]['rata']);
        $this->assertSame(0.0, $laporan['baris'][1]['rata']);
        $this->assertSame(['sakit' => 1, 'izin' => 1, 'alfa' => 0], $laporan['baris'][0]['kehadiran']);
        $this->assertFalse($laporan['baris'][0]['diperiksa']);
        $sebelum = AbsensiSiswa::get()->toArray();
        $payload = $this->payload($d);
        $id = $d['anggota'][0]->id;
        $payload['siswa'][$id]['sakit'] = 2;
        $payload['siswa'][$id]['catatan_koreksi'] = 'Surat sakit satu hari baru diterima.';
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($sebelum, AbsensiSiswa::get()->toArray());
        $this->assertDatabaseHas('kehadiran_rapor_sts', ['anggota_kelas_id' => $id, 'sakit' => 2, 'diperiksa_oleh_pengguna_id' => $d['admin']->id]);
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]))->assertOk()
            ->assertSee('100,00')->assertSee('0,00')->assertSeeText('Perlu Bimbingan')
            ->assertSeeText('LAPORAN HASIL CAPAIAN PEMBELAJARAN')
            ->assertSeeText('A4 portrait')
            ->assertSee('images/logo-padang-panjang.png', false)
            ->assertDontSee('images/kartu-pelajar/logo-nusa.png', false)
            ->assertSee('images/kartu-pelajar/logo-smpn2pp.png', false)
            ->assertSee('class="attendance-list"', false)
            ->assertDontSeeText('DRAF PRATINJAU')->assertSeeText('Cetak / Simpan PDF')
            ->assertViewHas('baris', fn ($b) => $b->count() === 2 && $b[0]['kehadiran']['sakit'] === 2 && $b->every(fn ($r) => $r['siap']));
        $this->assertFalse($d['ujian']->fresh()->tampilkan_hasil);
    }

    public function test_rapor_menghitung_alfa_otomatis_dan_meminta_pemeriksaan_ulang_setelah_konfirmasi(): void
    {
        $d = $this->fondasi();
        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat'] as $i => $hari) {
            PengaturanAbsensi::create(['hari' => $hari, 'urutan_hari' => $i + 1, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '08:00', 'jam_scan_pulang_mulai' => '13:00', 'jam_pulang' => '14:00', 'jam_scan_pulang_selesai' => '15:00', 'aktif' => true]);
        }
        $d['anggota'][1]->update(['tanggal_masuk' => '2026-09-17']);
        foreach (['2026-09-14' => 'hadir', '2026-09-15' => 'izin', '2026-09-16' => 'sakit', '2026-09-17' => 'alfa'] as $tanggal => $status) {
            AbsensiSiswa::create(['tanggal' => $tanggal, 'tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id,
                'anggota_kelas_id' => $d['anggota'][0]->id, 'siswa_id' => $d['anggota'][0]->siswa_id, 'status_kehadiran' => $status, 'sumber' => 'manual']);
        }
        $this->simpanPeriode($d, ['tanggal_awal_presensi' => '2026-09-14', 'tanggal_akhir_presensi' => '2026-09-20']);
        $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(['sakit' => 1, 'izin' => 1, 'alfa' => 2], $r['baris'][0]['kehadiran']);
        $this->assertSame(2, $r['baris'][1]['sumber']['alfa']);
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $cetak = route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][0]->id]);
        $this->get($cetak)->assertOk()->assertViewHas('baris', fn ($b) => $b[0]['kehadiran']['alfa'] === 2);
        if (getenv('NUSA_CAPTURE_ALFA_UI')) {
            $dir = storage_path('framework/testing/alfa-otomatis');
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $index = $this->get(route('rapor-sts.index'))->assertOk();
            file_put_contents($dir.'/rapor.html', $index->getContent());
        }
        app(KoreksiPresensiSiswaService::class)->koreksi($d['admin'], $d['anggota'][0], ['tanggal' => '2026-09-18', 'status_kehadiran' => 'hadir', 'jam_masuk' => '06:50', 'catatan' => 'Scanner terganggu; hadir dikonfirmasi petugas.']);
        $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(1, $r['baris'][0]['sumber']['alfa']);
        $this->assertTrue($r['baris'][0]['sumber_berubah']);
        $this->get($cetak)->assertRedirect()->assertSessionHasErrors('cetak');
        $payload = $this->payload($d);
        $payload['siswa'][$d['anggota'][0]->id]['alfa'] = 1;
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasNoErrors();
        $this->get($cetak)->assertOk()->assertViewHas('baris', fn ($b) => $b[0]['kehadiran']['alfa'] === 1);
    }

    public function test_rapor_menunggu_hari_batas_berakhir_sebelum_mencetak_alfa_otomatis(): void
    {
        Carbon::setTestNow('2026-09-25 23:59:59');
        $d = $this->fondasi();
        PengaturanAbsensi::create(['hari' => 'jumat', 'urutan_hari' => 5, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '08:00', 'jam_scan_pulang_mulai' => '13:00', 'jam_pulang' => '14:00', 'jam_scan_pulang_selesai' => '15:00', 'aktif' => true]);
        $this->simpanPeriode($d, ['tanggal_awal_presensi' => '2026-09-25', 'tanggal_akhir_presensi' => '2026-09-25']);
        $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(0, $r['baris'][1]['sumber']['alfa']);
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $cetak = route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][1]->id]);
        $this->get($cetak)->assertRedirect()->assertSessionHasErrors('cetak');
        $this->get(route('rapor-sts.index'))->assertSeeText('Periode presensi belum berakhir.');

        Carbon::setTestNow('2026-09-26 00:00:00');
        $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(1, $r['baris'][1]['sumber']['alfa']);
        $this->assertTrue($r['baris'][1]['sumber_berubah']);
        $this->assertFalse($r['baris'][1]['diperiksa']);
        $payload = $this->payload($d);
        $payload['siswa'][$d['anggota'][1]->id]['alfa'] = 1;
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasNoErrors();
        $this->get($cetak)->assertOk()->assertViewHas('baris', fn ($b) => $b[0]['kehadiran']['alfa'] === 1);
    }

    public function test_cetak_per_siswa_dan_seluruh_kelas_tetap_tersedia_saat_nilai_belum_lengkap(): void
    {
        $d = $this->fondasi();
        foreach ([
            'Pendidikan Agama Islam', 'Pendidikan Pancasila', 'Bahasa Indonesia',
            'Bahasa Inggris', 'Ilmu Pengetahuan Alam (IPA)', 'Ilmu Pengetahuan Sosial (IPS)',
            'Pendidikan Jasmani, Olahraga, dan Kesehatan (PJOK)', 'Informatika',
            'Seni Budaya', 'Keminangkabauan',
        ] as $index => $nama) {
            $mapel = MataPelajaran::create(['kode' => 'CETAK-KOSONG-'.$index, 'nama' => $nama, 'aktif' => true]);
            GuruMataPelajaran::create(['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $d['guru']->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
        }
        $this->simpanPeriode($d);
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $jawabanAsli = $d['peserta']->map(fn ($p) => $p->jawabanPesertaUjianCbt()->get()->toArray())->all();
        $individu = $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][1]->id]))
            ->assertOk()->assertSeeText('Belum tersedia')->assertSee('0,00')->assertDontSeeText('Alya Contoh')
            ->assertDontSeeText('DRAF PRATINJAU')->assertDontSeeText('Rapor final')
            ->assertSeeText('10 mata pelajaran belum tersedia')
            ->assertViewHas('baris', fn ($baris) => $baris->count() === 1 && $baris[0]['siap']
                && ! $baris[0]['nilai_tuntas'] && $baris[0]['jumlah'] === null && $baris[0]['rata'] === null);
        $kelas = $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]))->assertOk()
            ->assertSee('100,00')->assertSee('0,00')->assertSeeText('Cetak / Simpan PDF')
            ->assertHeader('Cache-Control')
            ->assertViewHas('baris', fn ($baris) => $baris->count() === 2 && $baris->every(fn ($b) => $b['siap'] && ! $b['nilai_lengkap']));
        $this->assertStringContainsString('no-store', $kelas->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $kelas->headers->get('Cache-Control'));
        $this->assertSame(20, substr_count($kelas->getContent(), '<td class="description">Belum tersedia</td>'));
        $index = $this->get(route('rapor-sts.index'))->assertOk()->assertSeeText('2 siswa memiliki nilai yang belum tersedia.');
        $this->assertSame(3, substr_count($index->getContent(), 'data-sts-print target='));
        $leger = app(LegerStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertTrue($leger['baris']->every(fn ($b) => $b['ranking'] === null && $b['rata_leger'] === null));
        $this->assertSame(0, $leger['ringkasan']['masuk_ranking']);
        $this->assertSame($jawabanAsli, $d['peserta']->map(fn ($p) => $p->jawabanPesertaUjianCbt()->get()->toArray())->all());
        $this->assertFalse($d['ujian']->fresh()->tampilkan_hasil);
        $this->assertDatabaseCount('pengecualian_rapor_sts', 0);

        if (getenv('NUSA_CAPTURE_STS_PRINT')) {
            $dir = storage_path('framework/testing/rapor-sts-print');
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($dir.'/individual.html', $individu->getContent());
            file_put_contents($dir.'/class.html', $kelas->getContent());
            file_put_contents($dir.'/index.html', $index->getContent());
            $mapel = MataPelajaran::create(['nama' => 'Pendidikan Inklusi', 'aktif' => true]);
            GuruMataPelajaran::create(['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $d['guru']->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
            $d['anggota'][1]->siswa->update(['nama_lengkap' => 'Bima Contoh Nama Siswa yang Lebih Panjang']);
            $padat = $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]))->assertOk();
            file_put_contents($dir.'/dense.html', $padat->getContent());
            foreach (['2026-07-01' => 20, '2026-09-20' => 25] as $tanggal => $menit) {
                AbsensiSiswa::create(['tanggal' => $tanggal, 'tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id,
                    'anggota_kelas_id' => $d['anggota'][1]->id, 'siswa_id' => $d['anggota'][1]->siswa_id,
                    'status_kehadiran' => 'hadir', 'menit_terlambat' => $menit]);
            }
            $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
            $terlambat = $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]))->assertOk();
            $pratinjau = $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][1]->id, 'pratinjau' => 1]))->assertOk();
            file_put_contents($dir.'/late.html', $terlambat->getContent());
            file_put_contents($dir.'/preview.html', $pratinjau->getContent());
        }
    }

    public function test_seluruh_nilai_belum_tersedia_boleh_dicetak_tanpa_memfinalisasi_atau_memberi_nol(): void
    {
        $d = $this->fondasi();
        $d['ujian']->update(['hasil_difinalisasi_pada' => null]);
        $d['peserta'][1]->update(['status' => 'aktif', 'status_kehadiran_ujian' => 'alfa']);
        $this->simpanPeriode($d);
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]))->assertOk()
            ->assertSeeText('Belum tersedia')->assertDontSee('100,00')->assertDontSee('0,00')
            ->assertDontSeeText('Tidak mengikuti STS')->assertDontSeeText('DRAF PRATINJAU')
            ->assertViewHas('baris', fn ($baris) => $baris->every(fn ($b) => $b['siap'] && $b['jumlah_bernilai'] === 0 && $b['jumlah'] === null && $b['rata'] === null));
        $this->assertNull($d['ujian']->fresh()->hasil_difinalisasi_pada);
        $this->assertSame('aktif', $d['peserta'][1]->fresh()->status);
        $this->assertSame('alfa', $d['peserta'][1]->fresh()->status_kehadiran_ujian);
        $this->assertDatabaseCount('nilai_siswa', 0);
        $this->assertDatabaseCount('pengecualian_rapor_sts', 0);
    }

    public function test_cetak_nilai_kosong_tetap_memerlukan_periode_wali_kelas_dan_pemeriksaan_kehadiran(): void
    {
        $d = $this->fondasi();
        $d['ujian']->update(['hasil_difinalisasi_pada' => null]);
        $cetak = route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]);
        $this->actingAs($d['admin'])->get($cetak)->assertRedirect()->assertSessionHasErrors('cetak');
        $this->simpanPeriode($d);
        $this->get($cetak)->assertRedirect()->assertSessionHasErrors('cetak');
        $payload = $this->payload($d);
        $payload['siswa'][$d['anggota'][1]->id]['diperiksa'] = 0;
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasNoErrors();
        $this->get($cetak)->assertRedirect()->assertSessionHasErrors('cetak');
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][0]->id]))->assertOk();
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $d['kelas']->update(['wali_kelas_id' => null]);
        $this->get($cetak)->assertRedirect()->assertSessionHasErrors('cetak');
        $d['kelas']->update(['wali_kelas_id' => $d['guru']->id]);
        $this->get($cetak)->assertOk();
        $this->simpanPeriode($d, ['tanggal_akhir_presensi' => '2026-09-28', 'tanggal_rapor' => '2026-09-30']);
        $payload = $this->payload($d);
        $payload['siswa'][$d['anggota'][0]->id]['alfa'] = 1;
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasNoErrors();
        $this->get($cetak)->assertRedirect()->assertSessionHasErrors('cetak');
    }

    public function test_cetak_rapor_menggabungkan_final_cbt_dengan_praktik_sts_manual(): void
    {
        $d = $this->fondasi();
        $mapel = MataPelajaran::create(['nama' => 'Keminangkabauan', 'urutan' => 2, 'aktif' => true]);
        $penugasan = GuruMataPelajaran::create(['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $d['guru']->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
        $komponen = KomponenNilai::create(['guru_mata_pelajaran_id' => $penugasan->id, 'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'nama' => 'Ujian Praktik KMT tentang Adat Sopan Santun', 'aktif' => true]);
        foreach ([88.5, 90] as $i => $nilai) {
            NilaiSiswa::create(['komponen_nilai_id' => $komponen->id, 'siswa_id' => $d['anggota'][$i]->siswa_id, 'nilai' => $nilai]);
        }
        $service = app(StsManualService::class);
        $service->tetapkan($d['admin'], $komponen, $service->konteks($komponen)['sidik'], true);
        $this->actingAs($d['admin']);
        $this->simpanPeriode($d);
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]))->assertOk()
            ->assertSeeText('Keminangkabauan')->assertSee('88,50')->assertSee('94,25')
            ->assertDontSeeText('DRAF PRATINJAU')->assertViewHas('baris', fn ($baris) => $baris->every(fn ($r) => $r['siap']));
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(100.0, $laporan['baris'][0]['nilai'][0]['nilai']);
        $this->assertSame('cbt', $laporan['baris'][0]['nilai'][0]['sumber_nilai']);
        $this->assertSame('manual', $laporan['baris'][0]['nilai'][1]['sumber_nilai']);
        $this->assertSame(94.25, $laporan['baris'][0]['rata']);
        $this->assertSame(45.0, $laporan['baris'][1]['rata']);
    }

    public function test_koreksi_input_nilai_cbt_mengubah_rapor_cetak_leger_dan_ranking_tanpa_mengubah_jawaban(): void
    {
        $d = $this->fondasi();
        $komponen = $this->terapkanNilaiFondasi($d);
        $jawabanAsli = $d['peserta']->map(fn ($p) => $p->jawabanPesertaUjianCbt()->get()->toArray())->all();
        $guru = $this->akunGuru($d['guru'], 'guru_mapel');
        $this->actingAs($guru)->post(route('input-nilai.store'), [
            'komponen_nilai_id' => $komponen->id,
            'nilai' => [$d['anggota'][0]->siswa_id => '80,25', $d['anggota'][1]->siswa_id => '91,50'],
            'catatan' => [$d['anggota'][0]->siswa_id => 'Koreksi guru setelah ujian.'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame([80.25, 91.5], $laporan['baris']->pluck('rata')->all());
        $this->assertSame('Baik', $laporan['baris'][0]['nilai'][0]['keterangan']);
        $this->assertSame('Sangat Baik', $laporan['baris'][1]['nilai'][0]['keterangan']);
        $leger = app(LegerStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(2, $leger['baris']->firstWhere('anggota.id', $d['anggota'][0]->id)['ranking']);
        $this->assertSame(1, $leger['baris']->first()['ranking']);
        $this->assertSame($d['anggota'][1]->id, $leger['baris']->first()['anggota']->id);
        $this->assertSame(85.88, $leger['ringkasan']['rata_kelas']);
        $this->assertSame(85.88, $leger['statistik_mapel'][0]['rata']);
        $tingkat = app(LegerStsService::class)->bangunTingkat($d['kegiatan'], collect([$d['kelas']]), 9);
        $this->assertSame([91.5, 80.25], $tingkat['baris']->pluck('rata_leger')->all());
        $this->assertSame($d['anggota'][1]->id, $tingkat['baris']->first()['anggota']->id);
        $this->assertSame($jawabanAsli, $d['peserta']->map(fn ($p) => $p->jawabanPesertaUjianCbt()->get()->toArray())->all());

        $this->simpanPeriode($d);
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $this->get(route('rapor-sts.index', ['kegiatan_id' => $d['kegiatan']->id, 'kelas_id' => $d['kelas']->id]))
            ->assertOk()->assertViewHas('laporan', fn ($r) => $r['baris']->pluck('rata')->all() === [80.25, 91.5]);
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]))->assertOk()
            ->assertSee('80,25')->assertSee('91,50')->assertDontSeeText('DRAF PRATINJAU');
        $this->get(route('leger-sts.penghargaan', [
            'cakupan' => 'kelas', 'kegiatan_id' => $d['kegiatan']->id, 'kelas_id' => $d['kelas']->id,
            'kategori' => 'mapel', 'mapel_id' => $d['mapel']->id, 'batas' => 1,
        ]))->assertOk()->assertViewHas('penghargaan', fn ($p) => $p['kandidat']->first()['anggota']->id === $d['anggota'][1]->id);
    }

    public function test_nilai_input_manual_siswa_tidak_ikut_cbt_muncul_di_rapor_cetak_dan_leger(): void
    {
        $d = $this->fondasi();
        $this->tidakMengikuti($d);
        $komponen = $this->hubungkanKomponenStsFondasi($d);
        $diterapkan = app(TerapkanNilaiCbtService::class)->terapkan($d['ujian'], $d['admin']->id);
        $this->assertSame(1, $diterapkan['ringkasan']['diterapkan']);
        $this->assertSame(1, $diterapkan['ringkasan']['belum_selesai']);
        $pesertaAsli = $d['peserta'][1]->fresh()->toArray();
        $jawabanAsli = $d['peserta']->map(fn ($p) => $p->jawabanPesertaUjianCbt()->get()->toArray())->all();
        $guru = $this->akunGuru($d['guru'], 'guru_mapel');
        $this->actingAs($guru)->post(route('input-nilai.store'), [
            'komponen_nilai_id' => $komponen->id,
            'nilai' => [$d['anggota'][0]->siswa_id => '80,25', $d['anggota'][1]->siswa_id => '91,50'],
            'catatan' => [$d['anggota'][1]->siswa_id => 'Nilai tugas pengganti STS.'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame([80.25, 91.5], $r['baris']->pluck('rata')->all());
        $this->assertFalse($r['baris'][1]['nilai'][0]['dapat_dikecualikan']);
        $this->assertSame('Sangat Baik', $r['baris'][1]['nilai'][0]['keterangan']);
        $leger = app(LegerStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame($d['anggota'][1]->id, $leger['baris']->first()['anggota']->id);
        $this->assertSame(1, $leger['baris']->first()['ranking']);
        $this->assertSame(85.88, $leger['ringkasan']['rata_kelas']);
        $this->assertSame(85.88, $leger['statistik_mapel'][0]['rata']);
        $tingkat = app(LegerStsService::class)->bangunTingkat($d['kegiatan'], collect([$d['kelas']]), 9);
        $this->assertSame([91.5, 80.25], $tingkat['baris']->pluck('rata_leger')->all());
        $this->assertSame($pesertaAsli, $d['peserta'][1]->fresh()->toArray());
        $this->assertSame($jawabanAsli, $d['peserta']->map(fn ($p) => $p->jawabanPesertaUjianCbt()->get()->toArray())->all());
        $this->assertNull($d['peserta'][1]->fresh()->nilai_diterapkan_pada);
        $this->assertNull($d['peserta'][1]->fresh()->nilai_siswa_id);
        $this->assertFalse($d['ujian']->fresh()->tampilkan_hasil);

        $this->simpanPeriode($d);
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $this->get(route('rapor-sts.index', ['kegiatan_id' => $d['kegiatan']->id, 'kelas_id' => $d['kelas']->id]))
            ->assertOk()->assertViewHas('laporan', fn ($r) => $r['baris']->pluck('rata')->all() === [80.25, 91.5]);
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][1]->id]))
            ->assertOk()->assertSee('91,50')->assertDontSeeText('Tidak mengikuti STS')
            ->assertDontSeeText('DRAF PRATINJAU');
    }

    public function test_nilai_manual_siswa_tidak_ikut_cbt_menerima_nol_dan_tidak_menganggap_kosong_sebagai_nol(): void
    {
        $d = $this->fondasi();
        $this->tidakMengikuti($d);
        $komponen = $this->hubungkanKomponenStsFondasi($d);
        foreach ([['0', 0.0], ['', null], ['87,65', 87.65], ['', null]] as [$input, $hasil]) {
            $this->actingAs($d['admin'])->post(route('input-nilai.store'), [
                'komponen_nilai_id' => $komponen->id,
                'nilai' => [$d['anggota'][1]->siswa_id => $input],
                'catatan' => [$d['anggota'][1]->siswa_id => 'Catatan penilaian manual.'],
            ])->assertSessionHasNoErrors();
            $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
            $this->assertSame($hasil, $r['baris'][1]['rata']);
            $this->assertSame($hasil, $r['baris'][1]['nilai'][0]['nilai']);
            $this->assertSame($hasil === null ? 'Belum tersedia' : ($hasil === 0.0 ? 'Perlu Bimbingan' : 'Baik'), $r['baris'][1]['nilai'][0]['keterangan']);
        }
        $this->assertSame('aktif', $d['peserta'][1]->fresh()->status);
        $this->assertNull($d['peserta'][1]->fresh()->nilai_diterapkan_pada);
        $this->assertDatabaseCount('jawaban_peserta_ujian_cbt', 1);
    }

    public function test_nilai_manual_di_komponen_cbt_tetap_terbaca_tanpa_baris_peserta_ujian(): void
    {
        $d = $this->fondasi();
        $komponen = $this->hubungkanKomponenStsFondasi($d);
        $d['peserta'][1]->jawabanPesertaUjianCbt()->delete();
        $d['peserta'][1]->delete();
        $this->actingAs($d['admin'])->post(route('input-nilai.store'), [
            'komponen_nilai_id' => $komponen->id, 'nilai' => [$d['anggota'][1]->siswa_id => '88,50'],
        ])->assertSessionHasNoErrors();
        $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(88.5, $r['baris'][1]['rata']);
        $this->assertSame('Baik', $r['baris'][1]['nilai'][0]['keterangan']);
        $this->assertNull($r['baris'][1]['nilai'][0]['peserta_id']);
        $this->assertFalse($r['baris'][1]['nilai'][0]['dapat_dikecualikan']);
        $this->assertSame(100.0, $r['baris'][0]['rata']);
        $d['ujian']->kelasUjianCbt()->update(['komponen_nilai_id' => null]);
        $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertNull($r['baris'][1]['rata']);
        $this->assertDatabaseCount('peserta_ujian_cbt', 1);
    }

    public function test_nilai_manual_siswa_tidak_ikut_cbt_tetap_memeriksa_finalisasi_dan_cakupan(): void
    {
        $d = $this->fondasi();
        $this->tidakMengikuti($d);
        $komponen = $this->hubungkanKomponenStsFondasi($d);
        NilaiSiswa::create(['komponen_nilai_id' => $komponen->id, 'siswa_id' => $d['anggota'][1]->siswa_id, 'nilai' => 89.25]);
        $d['ujian']->update(['hasil_difinalisasi_pada' => null]);
        $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertNull($r['baris'][1]['rata']);
        $this->assertSame('Belum difinalisasi guru mapel', $r['baris'][1]['nilai'][0]['status']);
        $d['ujian']->update(['hasil_difinalisasi_pada' => now()]);
        $d['peserta'][1]->update(['status' => 'sedang_mengerjakan']);
        $this->assertNull(app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas'])['baris'][1]['rata']);
        $d['peserta'][1]->update(['status' => 'nonaktif']);
        $this->assertSame(89.25, app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas'])['baris'][1]['rata']);
        $guru = $komponen->guruMataPelajaran;
        $kelasLain = Kelas::create(['tahun_pelajaran_id' => $d['tahun']->id, 'nama' => 'IX.B', 'tingkat' => 9, 'aktif' => true]);
        $tahunLain = TahunPelajaran::create(['nama' => '2027/2028', 'aktif' => false]);
        $mapelLain = MataPelajaran::create(['kode' => 'MANUAL-IPA', 'nama' => 'IPA', 'aktif' => true]);
        foreach ([
            [$komponen, 'semester', 'genap'], [$komponen, 'jenis_komponen', 'sas_saj'], [$komponen, 'aktif', false],
            [$guru, 'kelas_id', $kelasLain->id], [$guru, 'tahun_pelajaran_id', $tahunLain->id],
            [$guru, 'mata_pelajaran_id', $mapelLain->id], [$guru, 'aktif', false],
            [$d['peserta'][1]->kelasUjianCbt, 'kelas_id', $kelasLain->id],
        ] as [$model, $kolom, $nilai]) {
            $asli = $model->getAttribute($kolom);
            $model->update([$kolom => $nilai]);
            $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
            $hasil = $r['baris'][1]['nilai']->firstWhere('mapel.id', $d['mapel']->id);
            $this->assertNull($hasil['nilai'], 'Nilai tidak boleh melewati batas '.$kolom);
            $this->assertSame('Komponen nilai STS tujuan perlu diperiksa', $hasil['status']);
            $model->update([$kolom => $asli]);
        }
        $komponen->nilaiSiswa()->where('siswa_id', $d['anggota'][1]->siswa_id)->update(['nilai' => 101]);
        $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertNull($r['baris'][1]['rata']);
        $this->assertSame('Nilai STS pada Input Nilai perlu diperiksa', $r['baris'][1]['nilai'][0]['status']);
    }

    public function test_nilai_manual_menggantikan_tampilan_pengecualian_tidak_mengikuti_sts(): void
    {
        $d = $this->fondasi();
        $this->tidakMengikuti($d);
        $komponen = $this->hubungkanKomponenStsFondasi($d);
        $this->simpanPeriode($d);
        $url = route('rapor-sts.pengecualian', [$d['kegiatan'], $d['kelas']]);
        $this->put($url, $this->payloadPengecualian($d))->assertSessionHasNoErrors();
        $pengecualianAsli = PengecualianRaporSts::firstOrFail()->toArray();
        $this->post(route('input-nilai.store'), [
            'komponen_nilai_id' => $komponen->id, 'nilai' => [$d['anggota'][1]->siswa_id => '75,50'],
        ])->assertSessionHasNoErrors();
        $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(75.5, $r['baris'][1]['rata']);
        $this->assertSame(0, $r['baris'][1]['jumlah_pengecualian']);
        $this->assertSame('Cukup', $r['baris'][1]['nilai'][0]['keterangan']);
        $this->assertFalse($r['baris'][1]['nilai'][0]['dikecualikan']);
        $mid = $d['mapel']->id;
        $this->put($url, $this->payloadPengecualian($d))->assertSessionHasErrors("pengecualian.$mid.tidak_mengikuti");
        $this->assertSame($pengecualianAsli, PengecualianRaporSts::firstOrFail()->toArray());
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][1]->id]))
            ->assertOk()->assertSee('75,50')->assertDontSeeText('Tidak mengikuti STS');
    }

    public function test_nilai_diterapkan_yang_dikosongkan_tidak_kembali_ke_skor_asli_dan_bisa_diisi_ulang(): void
    {
        $d = $this->fondasi();
        $komponen = $this->terapkanNilaiFondasi($d);
        $this->actingAs($d['admin'])->post(route('input-nilai.store'), [
            'komponen_nilai_id' => $komponen->id,
            'nilai' => [$d['anggota'][0]->siswa_id => '0', $d['anggota'][1]->siswa_id => ''],
        ])->assertSessionHasNoErrors();
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(0.0, $laporan['baris'][0]['rata']);
        $this->assertNull($laporan['baris'][1]['rata']);
        $this->assertSame('Nilai STS pada Input Nilai belum tersedia', $laporan['baris'][1]['nilai'][0]['status']);
        $this->assertNull($d['peserta'][1]->fresh()->nilai_siswa_id);
        $this->assertNotNull($d['peserta'][1]->fresh()->nilai_diterapkan_pada);

        $this->simpanPeriode($d);
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][1]->id]))
            ->assertOk()->assertSeeText('Belum tersedia')->assertDontSee('0,00')
            ->assertViewHas('baris', fn ($baris) => $baris[0]['siap'] && $baris[0]['nilai'][0]['nilai'] === null);

        $this->post(route('input-nilai.store'), [
            'komponen_nilai_id' => $komponen->id,
            'nilai' => [$d['anggota'][0]->siswa_id => '0', $d['anggota'][1]->siswa_id => '87,65'],
        ])->assertSessionHasNoErrors();
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame([0.0, 87.65], $laporan['baris']->pluck('rata')->all());
        $this->assertNull($d['peserta'][1]->fresh()->nilai_siswa_id);
        $komponen->update(['aktif' => false]);
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertTrue($laporan['baris']->every(fn ($r) => $r['rata'] === null));
    }

    public function test_nilai_komponen_cbt_tetap_memerlukan_finalisasi_dan_cakupan_sts_yang_sesuai(): void
    {
        $d = $this->fondasi();
        $komponen = $this->terapkanNilaiFondasi($d);
        $d['ujian']->update(['hasil_difinalisasi_pada' => null]);
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertNull($laporan['baris'][0]['rata']);
        $d['ujian']->update(['hasil_difinalisasi_pada' => now()]);
        $d['peserta'][0]->update(['status' => 'sedang_mengerjakan']);
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertNull($laporan['baris'][0]['rata']);
        $this->assertSame(0.0, $laporan['baris'][1]['rata']);
        $d['peserta'][0]->update(['status' => 'selesai']);
        $komponen->update(['semester' => 'genap']);
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertTrue($laporan['baris']->every(fn ($r) => $r['rata'] === null));
    }

    public function test_sumber_nilai_diterapkan_tidak_tertukar_dengan_siswa_atau_cakupan_lain(): void
    {
        $d = $this->fondasi();
        $komponen = $this->terapkanNilaiFondasi($d);
        $d['peserta'][0]->update(['nilai_siswa_id' => $d['peserta'][1]->fresh()->nilai_siswa_id]);
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame([100.0, 0.0], $laporan['baris']->pluck('rata')->all());
        $guru = $komponen->guruMataPelajaran;
        $tahunLain = TahunPelajaran::create(['nama' => '2027/2028', 'aktif' => false]);
        $kelasLain = Kelas::create(['tahun_pelajaran_id' => $d['tahun']->id, 'nama' => 'IX.B', 'tingkat' => 9, 'aktif' => true]);
        $mapelLain = MataPelajaran::create(['kode' => 'IPA-UJI', 'nama' => 'IPA', 'aktif' => true]);
        foreach ([
            [$komponen, 'jenis_komponen', 'sas_saj'], [$komponen, 'aktif', false],
            [$guru, 'kelas_id', $kelasLain->id], [$guru, 'tahun_pelajaran_id', $tahunLain->id],
            [$guru, 'mata_pelajaran_id', $mapelLain->id], [$guru, 'aktif', false],
        ] as [$model, $kolom, $nilai]) {
            $asli = $model->getAttribute($kolom);
            $model->update([$kolom => $nilai]);
            $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
            $hasil = $laporan['baris'][0]['nilai']->firstWhere('mapel.id', $d['mapel']->id);
            $this->assertNull($hasil['nilai'], 'Cakupan '.$kolom.' tidak boleh mengambil skor CBT lama.');
            $this->assertSame('Komponen nilai STS tujuan perlu diperiksa', $hasil['status']);
            $model->update([$kolom => $asli]);
        }
        $komponen->nilaiSiswa()->where('siswa_id', $d['anggota'][0]->siswa_id)->update(['nilai' => 101]);
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertNull($laporan['baris'][0]['rata']);
        $this->assertSame('Nilai STS pada Input Nilai perlu diperiksa', $laporan['baris'][0]['nilai'][0]['status']);
    }

    public function test_leger_kelas_menghitung_ranking_ringkasan_dan_statistik_mapel(): void
    {
        $d = $this->fondasi();
        $response = $this->actingAs($d['admin'])->get(route('leger-sts.index'))
            ->assertOk()
            ->assertSeeText('Leger STS')
            ->assertSeeText('Per Kelas')
            ->assertSeeText('Ranking kelas IX.A')
            ->assertSeeText('Mapel tertinggi')
            ->assertSeeText('Matematika')
            ->assertSeeText('Masuk ranking')
            ->assertSeeText('Nilai final')
            ->assertSeeText('1 siswa')
            ->assertViewHas('leger');

        $leger = $response->viewData('leger');
        $this->assertSame([1, 2], $leger['baris']->pluck('ranking')->all());
        $this->assertSame([100.0, 0.0], $leger['baris']->pluck('rata_leger')->all());
        $this->assertSame(2, $leger['ringkasan']['masuk_ranking']);
        $this->assertSame(50.0, $leger['ringkasan']['rata_kelas']);
        $this->assertSame(100.0, $leger['ringkasan']['rata_tertinggi']);
        $this->assertSame(0.0, $leger['ringkasan']['rata_terendah']);
        $this->assertSame($d['mapel']->id, $leger['mapel_tertinggi']['mapel']->id);
        $this->assertSame(50.0, $leger['mapel_tertinggi']['rata']);
        $this->assertSame([1, 0, 0, 1], $leger['distribusi']->pluck('jumlah')->all());
    }

    public function test_leger_memberi_ranking_sama_dan_tidak_meranking_nilai_belum_lengkap(): void
    {
        $d = $this->fondasi();
        $d['peserta'][1]->jawabanPesertaUjianCbt()->update(['skor' => 2]);

        $leger = app(LegerStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame([1, 1], $leger['baris']->pluck('ranking')->all());
        $this->assertSame(100.0, $leger['ringkasan']['rata_kelas']);

        $d['peserta'][0]->jawabanPesertaUjianCbt()->update(['skor' => null]);
        $leger = app(LegerStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $belumLengkap = $leger['baris']->firstWhere('anggota.id', $d['anggota'][0]->id);
        $lengkap = $leger['baris']->firstWhere('anggota.id', $d['anggota'][1]->id);

        $this->assertNull($belumLengkap['ranking']);
        $this->assertNull($belumLengkap['rata_leger']);
        $this->assertSame('Nilai belum lengkap', $belumLengkap['status_ranking']);
        $this->assertSame(1, $lengkap['ranking']);
        $this->assertSame(1, $leger['ringkasan']['masuk_ranking']);
        $this->assertSame(1, $leger['ringkasan']['belum_masuk_ranking']);
    }

    public function test_leger_kelas_dapat_dicetak_dengan_format_landscape(): void
    {
        $d = $this->fondasi();
        $response = $this->actingAs($d['admin'])->get(route('leger-sts.cetak', [
            'mode' => 'kelas',
            'kegiatan_id' => $d['kegiatan']->id,
            'kelas_id' => $d['kelas']->id,
        ]))->assertOk()
            ->assertSeeText('LEGER NILAI SUMATIF TENGAH SEMESTER I')
            ->assertSeeText('IX.A')
            ->assertSeeText('Alya Contoh')
            ->assertSeeText('Cetak / Simpan PDF')
            ->assertSee('size:A4 landscape', false)
            ->assertSeeText('MTK-STS = Matematika');

        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(2, $response->viewData('leger')['baris']->count());
    }

    public function test_kandidat_penghargaan_kelas_mendukung_keseluruhan_mapel_dan_nilai_seri(): void
    {
        $d = $this->fondasi();
        $keseluruhan = $this->actingAs($d['admin'])->get(route('leger-sts.penghargaan', [
            'cakupan' => 'kelas',
            'kegiatan_id' => $d['kegiatan']->id,
            'kelas_id' => $d['kelas']->id,
            'kategori' => 'keseluruhan',
            'batas' => 10,
        ]))->assertOk()
            ->assertSeeText('Kandidat Penghargaan STS')
            ->assertSeeText('Siswa terbaik setiap mata pelajaran')
            ->assertSeeText('Juara Umum')
            ->assertSeeText('Matematika')
            ->assertViewHas('penghargaan');
        $this->assertSame([1, 2], $keseluruhan->viewData('penghargaan')['kandidat']->pluck('ranking')->all());
        $this->assertSame('Alya Contoh', $keseluruhan->viewData('penghargaan')['ringkasan_mapel'][0]['juara'][0]['anggota']->siswa->nama_lengkap);

        $d['peserta'][1]->jawabanPesertaUjianCbt()->update(['skor' => 2]);
        $mapel = $this->get(route('leger-sts.penghargaan', [
            'cakupan' => 'kelas',
            'kegiatan_id' => $d['kegiatan']->id,
            'kelas_id' => $d['kelas']->id,
            'kategori' => 'mapel',
            'mapel_id' => $d['mapel']->id,
            'batas' => 1,
        ]))->assertOk()->assertSeeText('Terbaik Mata Pelajaran');
        $this->assertSame([1, 1], $mapel->viewData('penghargaan')['kandidat']->pluck('ranking')->all());
        $this->assertSame(2, $mapel->viewData('penghargaan')['ringkasan']['jumlah_kandidat']);
        $this->get(route('leger-sts.penghargaan', [
            'cakupan' => 'kelas', 'kegiatan_id' => $d['kegiatan']->id, 'kelas_id' => $d['kelas']->id,
            'kategori' => 'mapel', 'mapel_id' => 999999,
        ]))->assertNotFound();
    }

    public function test_leger_tingkat_membandingkan_kelas_dan_menghitung_ranking_paralel(): void
    {
        $d = $this->fondasi();
        $kelasB = Kelas::create([
            'tahun_pelajaran_id' => $d['tahun']->id,
            'nama' => 'IX.B',
            'tingkat' => 9,
            'aktif' => true,
        ]);
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $d['tahun']->id,
            'kelas_id' => $kelasB->id,
            'mata_pelajaran_id' => $d['mapel']->id,
            'pegawai_id' => $d['guru']->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
        $d['kegiatan']->jadwalUjianCbt()->first()->kelas()->attach($kelasB);
        $kelasUjian = KelasUjianCbt::create(['ujian_cbt_id' => $d['ujian']->id, 'kelas_id' => $kelasB->id]);
        $relasi = $d['ujian']->soalUjianCbt()->first();
        foreach ([['Citra Paralel', 2], ['Dedi Paralel', 1]] as $index => [$nama, $skor]) {
            $siswa = Siswa::create([
                'nama_lengkap' => $nama,
                'nis' => 'PB00'.$index,
                'nisn' => '223456789'.$index,
                'jenis_kelamin' => $index ? 'L' : 'P',
                'aktif' => true,
            ]);
            $anggota = AnggotaKelas::create([
                'tahun_pelajaran_id' => $d['tahun']->id,
                'kelas_id' => $kelasB->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $index + 1,
                'status_keanggotaan' => 'aktif',
            ]);
            $peserta = PesertaUjianCbt::create([
                'ujian_cbt_id' => $d['ujian']->id,
                'kelas_ujian_cbt_id' => $kelasUjian->id,
                'anggota_kelas_id' => $anggota->id,
                'nomor_peserta' => 'PARALEL-'.$index,
                'status' => 'selesai',
            ]);
            $peserta->jawabanPesertaUjianCbt()->create([
                'soal_ujian_cbt_id' => $relasi->id,
                'soal_cbt_id' => $relasi->soal_cbt_id,
                'jawaban' => ['A'],
                'skor' => $skor,
            ]);
        }

        $response = $this->actingAs($d['admin'])->get(route('leger-sts.index', ['mode' => 'tingkat', 'tingkat' => 9]))
            ->assertOk()
            ->assertSeeText('Per Tingkat')
            ->assertSeeText('Perbandingan kelas')
            ->assertSeeText('Ranking paralel tingkat 9')
            ->assertSeeText('Citra Paralel')
            ->assertViewHas('legerTingkat');
        $leger = $response->viewData('legerTingkat');

        $this->assertSame(2, $leger['ringkasan']['jumlah_kelas']);
        $this->assertSame(4, $leger['ringkasan']['jumlah_siswa']);
        $this->assertSame(4, $leger['ringkasan']['masuk_ranking']);
        $this->assertSame(62.5, $leger['ringkasan']['rata_tingkat']);
        $this->assertSame([1, 1, 3, 4], $leger['baris']->pluck('ranking')->all());
        $this->assertSame('IX.B', $leger['statistik_kelas'][0]['kelas']->nama);
        $this->assertSame(1, $leger['statistik_kelas'][0]['ranking']);
        $this->assertSame(75.0, $leger['statistik_kelas'][0]['rata']);
        $this->assertSame('IX.A', $leger['statistik_kelas'][1]['kelas']->nama);
        $this->assertSame(2, $leger['statistik_kelas'][1]['ranking']);
        $this->assertSame([50.0, 75.0], $leger['statistik_mapel'][0]['per_kelas']->pluck('rata')->all());

        $cetak = $this->get(route('leger-sts.cetak', [
            'mode' => 'tingkat',
            'kegiatan_id' => $d['kegiatan']->id,
            'tingkat' => 9,
        ]))->assertOk()
            ->assertSeeText('Tingkat 9')
            ->assertSeeText('Citra Paralel')
            ->assertSeeText('IX.B')
            ->assertSeeText('A4 landscape');
        $penghargaan = $this->get(route('leger-sts.penghargaan', [
            'cakupan' => 'tingkat',
            'kegiatan_id' => $d['kegiatan']->id,
            'tingkat' => 9,
            'kategori' => 'mapel',
            'mapel_id' => $d['mapel']->id,
            'batas' => 3,
        ]))->assertOk()->assertSeeText('Tiga Besar Mata Pelajaran');
        $this->assertSame([1, 1, 3], $penghargaan->viewData('penghargaan')['kandidat']->pluck('ranking')->all());
        if (getenv('LEGER_PENGHARGAAN_FIXTURE')) {
            file_put_contents(storage_path('logs/leger-penghargaan-preview.html'), $penghargaan->getContent());
        }

        if (getenv('LEGER_TINGKAT_FIXTURE')) {
            file_put_contents(storage_path('logs/leger-tingkat-preview.html'), $response->getContent());
        }
        if (getenv('LEGER_CETAK_FIXTURE')) {
            file_put_contents(storage_path('logs/leger-tingkat-cetak-preview.html'), $cetak->getContent());
        }
    }

    public function test_koreksi_memerlukan_alasan_angka_valid_dan_mencegah_tabrakan_perubahan(): void
    {
        $d = $this->fondasi();
        $this->simpanPeriode($d);
        $payload = $this->payload($d);
        $id = $d['anggota'][0]->id;
        $payload['siswa'][$id]['sakit'] = 2;
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasErrors("siswa.$id.catatan_koreksi");
        $payload['siswa'][$id]['catatan_koreksi'] = 'Koreksi';
        $payload['siswa'][$id]['izin'] = -1;
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasErrors("siswa.$id.izin");
        $payload['siswa'][$id]['izin'] = 100;
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasErrors("siswa.$id.sakit");
        $payload = $this->payload($d);
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasNoErrors();
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasErrors('versi');
        $this->assertDatabaseCount('kehadiran_rapor_sts', 2);
    }

    public function test_rincian_sebelas_mapel_terpisah_dari_baris_koreksi_kehadiran(): void
    {
        $d = $this->fondasi();
        $this->simpanPeriode($d);
        foreach ([
            'Pendidikan Agama Islam', 'Pendidikan Pancasila', 'Bahasa Indonesia',
            'Bahasa Inggris', 'Ilmu Pengetahuan Alam (IPA)', 'Ilmu Pengetahuan Sosial (IPS)',
            'Pendidikan Jasmani, Olahraga, dan Kesehatan (PJOK)', 'Informatika',
            'Seni Budaya', 'Keminangkabauan',
        ] as $index => $nama) {
            $mapel = MataPelajaran::create(['kode' => 'UI-STS-'.$index, 'nama' => $nama, 'aktif' => true]);
            GuruMataPelajaran::create(['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $d['guru']->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
        }
        $id = $d['anggota'][0]->id;
        $d['anggota'][1]->siswa->update(['nama_lengkap' => 'Bima Contoh Nama Siswa yang Lebih Panjang']);
        $payload = $this->payload($d);
        $payload['siswa'][$id]['sakit'] = 2;
        $payload['siswa'][$id]['catatan_koreksi'] = 'Surat sakit diterima dari orang tua.';
        $payload['siswa'][$d['anggota'][1]->id]['diperiksa'] = 0;
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasNoErrors();
        $response = $this->get(route('rapor-sts.index'))->assertOk()
            ->assertSee('data-sts-grades="sts-nilai-'.$id.'"', false)
            ->assertSee('<dialog id="sts-grade-dialog"', false)
            ->assertSee('<template id="sts-nilai-'.$id.'">', false)
            ->assertSee('name="siswa['.$id.'][sidik_sumber]"', false)
            ->assertSee('name="siswa['.$id.'][catatan_koreksi]"', false)
            ->assertSee('aria-controls="sts-grade-dialog"', false)
            ->assertSeeText('1/11 mapel final')
            ->assertSeeText('Belum ada paket STS')
            ->assertSee('100,00')->assertSee('0,00')
            ->assertDontSee('<details class="sts-detail"', false)
            ->assertViewHas('laporan', fn ($laporan) => $laporan['mapel']->count() === 11);

        if (getenv('RAPOR_STS_FIXTURE')) {
            $pratinjau = $this->get(route('rapor-sts.cetak', [
                $d['kegiatan'], $d['kelas'], 'anggota_id' => $id, 'pratinjau' => 1,
            ]))->assertOk();
            file_put_contents(storage_path('logs/rapor-sts-preview.html'), $pratinjau->getContent());
        }
        if (getenv('LEGER_STS_FIXTURE')) {
            $leger = $this->get(route('leger-sts.index'))->assertOk();
            file_put_contents(storage_path('logs/leger-sts-preview.html'), $leger->getContent());
        }
        if (getenv('LEGER_CETAK_KELAS_FIXTURE')) {
            $cetakLeger = $this->get(route('leger-sts.cetak', [
                'mode' => 'kelas', 'kegiatan_id' => $d['kegiatan']->id, 'kelas_id' => $d['kelas']->id,
            ]))->assertOk();
            file_put_contents(storage_path('logs/leger-kelas-cetak-preview.html'), $cetakLeger->getContent());
        }

        preg_match('/<table class="sts-table">(.*?)<\/table>/s', $response->getContent(), $table);
        $this->assertStringNotContainsString('Pendidikan Jasmani', $table[1]);
        $this->assertStringNotContainsString('<template', $table[1]);
        $this->assertSame(2, substr_count($table[1], 'data-sts-row'));
        $this->assertSame(6, substr_count($table[1], 'data-sts-original='));

    }

    public function test_perubahan_sumber_dan_periode_memerlukan_pemeriksaan_ulang(): void
    {
        $d = $this->fondasi();
        $this->simpanPeriode($d);
        $lama = $this->payload($d);
        AbsensiSiswa::where('status_kehadiran', 'sakit')->update(['status_kehadiran' => 'izin']);
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $lama)->assertSessionHasErrors('kehadiran');
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $this->simpanPeriode($d, ['tanggal_akhir_presensi' => '2026-09-21']);
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertTrue($laporan['baris'][0]['sumber_berubah']);
        $this->assertFalse($laporan['baris'][0]['siap']);
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]))->assertRedirect()->assertSessionHasErrors('cetak');
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'pratinjau' => 1]))->assertOk()->assertSeeText('DRAF PRATINJAU');
    }

    public function test_nilai_belum_final_tidak_hadir_dan_mapel_tanpa_paket_tidak_dianggap_nol(): void
    {
        $d = $this->fondasi();
        $d['ujian']->update(['hasil_difinalisasi_pada' => null]);
        $this->assertNull(app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas'])['baris'][0]['rata']);
        $d['ujian']->update(['hasil_difinalisasi_pada' => now()]);
        $d['peserta'][1]->update(['status' => 'aktif', 'status_kehadiran_ujian' => 'sakit']);
        $rapor = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertNull($rapor['baris'][1]['nilai'][0]['nilai']);
        $this->assertNull($rapor['baris'][1]['jumlah']);
        $mapel = MataPelajaran::create(['kode' => 'IPA-STS', 'nama' => 'Ilmu Pengetahuan Alam', 'aktif' => true]);
        GuruMataPelajaran::create(['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $d['guru']->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
        $rapor = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertCount(2, $rapor['mapel']);
        $this->assertNull($rapor['baris'][0]['jumlah']);
        $this->assertFalse($rapor['baris'][0]['nilai_lengkap']);
    }

    public function test_hanya_wali_kelas_sendiri_dan_admin_kurikulum_boleh_mengakses(): void
    {
        $d = $this->fondasi();
        $wali = $this->akunGuru($d['guru'], 'wali_kelas');
        $asing = Kelas::create(['tahun_pelajaran_id' => $d['tahun']->id, 'nama' => 'IX.B', 'tingkat' => 9, 'aktif' => true]);
        $this->actingAs($wali)->get(route('rapor-sts.index'))->assertOk()->assertSeeText('Rapor STS');
        $this->get(route('leger-sts.index'))->assertOk()->assertSeeText('Leger STS');
        $this->get(route('leger-sts.index', ['mode' => 'tingkat', 'tingkat' => 9]))->assertOk()
            ->assertViewHas('legerTingkat', fn ($leger) => $leger['ringkasan']['jumlah_kelas'] === 1);
        $this->get(route('leger-sts.penghargaan', ['cakupan' => 'tingkat', 'tingkat' => 9]))->assertOk()
            ->assertViewHas('leger', fn ($leger) => $leger['ringkasan']['jumlah_kelas'] === 1);
        $this->get(route('leger-sts.index', ['mode' => 'tingkat', 'tingkat' => 8]))->assertNotFound();
        $this->get(route('leger-sts.cetak', ['mode' => 'tingkat', 'kegiatan_id' => $d['kegiatan']->id, 'tingkat' => 8]))->assertNotFound();
        $this->get(route('leger-sts.penghargaan', ['cakupan' => 'tingkat', 'tingkat' => 8]))->assertNotFound();
        $this->get(route('rapor-sts.index', ['kelas_id' => $asing->id]))->assertNotFound();
        $this->get(route('leger-sts.index', ['kelas_id' => $asing->id]))->assertNotFound();
        $this->get(route('leger-sts.cetak', ['mode' => 'kelas', 'kegiatan_id' => $d['kegiatan']->id, 'kelas_id' => $asing->id]))->assertNotFound();
        $this->get(route('leger-sts.penghargaan', ['cakupan' => 'kelas', 'kelas_id' => $asing->id]))->assertNotFound();
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $asing, 'pratinjau' => 1]))->assertForbidden();
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $asing]), [])->assertForbidden();
        $this->put(route('rapor-sts.pengaturan', [$d['kegiatan'], $asing]), [])->assertForbidden();
        $wali->daftarPeran()->sync([Peran::where('kode', 'guru_mapel')->value('id')]);
        $this->actingAs($wali->fresh())->get(route('rapor-sts.index'))->assertForbidden();
        $this->get(route('leger-sts.index'))->assertForbidden();
        $this->get(route('leger-sts.penghargaan'))->assertForbidden();
        $wali->daftarPeran()->sync([Peran::where('kode', 'wakil_pimpinan_kurikulum')->value('id')]);
        $this->actingAs($wali->fresh())->get(route('rapor-sts.index', ['kelas_id' => $asing->id]))->assertOk();
        $this->get(route('leger-sts.index', ['kelas_id' => $asing->id]))->assertOk();
    }

    public function test_batas_tahun_jenis_ujian_dan_siswa_luar_kelas_ditolak(): void
    {
        $d = $this->fondasi();
        $this->simpanPeriode($d);
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => 99999, 'pratinjau' => 1]))->assertNotFound();
        $payload = $this->payload($d);
        $payload['siswa'][99999] = array_values($payload['siswa'])[0];
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertNotFound();
        $this->assertDatabaseCount('kehadiran_rapor_sts', 0);
        $this->put(route('rapor-sts.pengaturan', [$d['kegiatan'], $d['kelas']]), [
            'versi' => 1, 'tanggal_awal_presensi' => '2025-07-01', 'tanggal_akhir_presensi' => '2026-09-20', 'tanggal_rapor' => '2026-09-26',
        ])->assertSessionHasErrors('tanggal_awal_presensi');
        $d['kegiatan']->update(['jenis_ujian_cbt_id' => JenisUjianCbt::where('kode', 'SAS')->value('id')]);
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'pratinjau' => 1]))->assertNotFound();
    }

    public function test_nilai_mengikuti_soal_yang_disajikan_dan_tidak_mencampur_kegiatan(): void
    {
        $d = $this->fondasi();
        $kedua = $d['ujian']->soalUjianCbt()->first()->replicate();
        $soal = $d['ujian']->soalUjianCbt()->first()->soalCbt->replicate();
        $soal->kode = 'STS-Q-2';
        $soal->save();
        $kedua->soal_cbt_id = $soal->id;
        $kedua->nomor_urut = 2;
        $kedua->bobot = 4;
        $kedua->save();
        $d['ujian']->update(['acak_soal' => true, 'jumlah_soal' => 1]);
        foreach ($d['peserta'] as $peserta) {
            $terpilih = app(PengacakPenyajianCbt::class)->urutkanSoal($d['ujian'], $peserta, $d['ujian']->soalUjianCbt()->get())->first();
            $peserta->jawabanPesertaUjianCbt()->updateOrCreate(['soal_ujian_cbt_id' => $terpilih->id], ['soal_cbt_id' => $terpilih->soal_cbt_id, 'jawaban' => ['A'], 'skor' => $terpilih->bobot]);
        }
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame([100.0, 100.0], $laporan['baris']->pluck('rata')->all());
        $kegiatanLain = $d['kegiatan']->replicate();
        $kegiatanLain->kode = 'STS-LAIN';
        $kegiatanLain->save();
        $this->assertNull(app(RaporStsService::class)->bangun($kegiatanLain, $d['kelas'])['baris'][0]['rata']);
        $d['ujian']->pesertaUjianCbt()->first()->jawabanPesertaUjianCbt()->update(['skor' => null]);
        $this->assertNull(app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas'])['baris'][0]['rata']);
    }

    public function test_pemeriksaan_bisa_dibatalkan_dan_periode_mendatang_tidak_bisa_cetak_final(): void
    {
        $d = $this->fondasi();
        $this->simpanPeriode($d);
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $payload = $this->payload($d);
        $id = $d['anggota'][0]->id;
        $payload['siswa'][$id]['diperiksa'] = 0;
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasNoErrors();
        $this->assertNull(KehadiranRaporSts::where('anggota_kelas_id', $id)->first()->diperiksa_pada);
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $id]))->assertRedirect()->assertSessionHasErrors('cetak');
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][1]->id]))->assertOk();
        $this->simpanPeriode($d, ['tanggal_akhir_presensi' => '2026-09-28', 'tanggal_rapor' => '2026-09-30']);
        $payload = $this->payload($d);
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasErrors("siswa.$id.catatan_koreksi");
        $payload['siswa'][$id]['alfa'] = 1;
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasNoErrors();
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]))->assertRedirect()->assertSessionHasErrors('cetak');
    }

    public function test_sumber_nilai_ganda_dan_skor_tidak_valid_menahan_rapor_final(): void
    {
        $d = $this->fondasi();
        $d['peserta'][0]->jawabanPesertaUjianCbt()->update(['skor' => 3]);
        $this->assertNull(app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas'])['baris'][0]['rata']);
        $jadwal = $d['kegiatan']->jadwalUjianCbt()->first()->replicate();
        $jadwal->waktu_mulai = '09:00';
        $jadwal->waktu_selesai = '10:00';
        $jadwal->save();
        $jadwal->kelas()->attach($d['kelas']);
        $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertNull($r['baris'][1]['rata']);
        $this->assertSame('Ada beberapa jadwal STS; periksa sumber nilai', $r['baris'][1]['nilai'][0]['status']);
    }

    public function test_tidak_mengikuti_sts_memerlukan_alasan_dan_dapat_dicetak_tanpa_nilai_nol(): void
    {
        $d = $this->fondasi();
        $this->simpanPeriode($d);
        $this->tidakMengikuti($d);
        app(KoreksiOtomatisCbtService::class)->koreksiUjian($d['ujian']);
        $this->assertNull($d['peserta'][1]->jawabanPesertaUjianCbt()->first());
        $url = route('rapor-sts.pengecualian', [$d['kegiatan'], $d['kelas']]);
        $payload = $this->payloadPengecualian($d);
        $mid = $d['mapel']->id;
        $payload['pengecualian'][$mid]['alasan'] = '   ';
        $this->put($url, $payload)->assertSessionHasErrors("pengecualian.$mid.alasan");
        $payload = $this->payloadPengecualian($d);
        $this->put($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pengecualian_rapor_sts', [
            'anggota_kelas_id' => $d['anggota'][1]->id, 'mata_pelajaran_id' => $mid,
            'aktif' => true, 'alasan' => 'Sakit berkepanjangan dan tidak mengikuti susulan.',
            'ditetapkan_oleh_pengguna_id' => $d['admin']->id,
        ]);
        $cetak = route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][1]->id]);
        $this->get($cetak)->assertRedirect()->assertSessionHasErrors('cetak');
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $this->get($cetak)->assertOk()->assertSeeText('Tidak mengikuti STS')
            ->assertDontSeeText('Sakit berkepanjangan dan tidak mengikuti susulan.')
            ->assertDontSeeText('DRAF PRATINJAU')
            ->assertViewHas('baris', fn ($b) => $b->count() === 1 && $b[0]['siap'] && $b[0]['rata'] === null && $b[0]['jumlah'] === null);
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]))->assertOk();
        $this->assertSame('aktif', $d['peserta'][1]->fresh()->status);
        $this->assertSame('sakit', $d['peserta'][1]->fresh()->status_kehadiran_ujian);
        $this->assertFalse($d['ujian']->fresh()->tampilkan_hasil);
    }

    public function test_rata_rata_hanya_dari_mapel_bernilai_termasuk_nol_dan_menunggu_mapel_lain_yang_belum_final(): void
    {
        $d = $this->fondasi();
        $this->simpanPeriode($d);
        $soal = $d['ujian']->soalUjianCbt()->first();
        $tambahan = [];
        foreach ([2, 0] as $i => $skor) {
            $mapel = MataPelajaran::create(['kode' => 'RAPOR-TAMBAH-'.$i, 'nama' => 'Mapel tambahan '.$i, 'aktif' => true]);
            $ujian = $d['ujian']->replicate();
            $ujian->fill(['kode' => 'PAKET-TAMBAH-'.$i, 'mata_pelajaran_id' => $mapel->id])->save();
            $jadwal = $d['kegiatan']->jadwalUjianCbt()->first()->replicate();
            $jadwal->fill(['ujian_cbt_id' => $ujian->id, 'mata_pelajaran_id' => $mapel->id, 'waktu_mulai' => sprintf('%02d:00', 9 + $i), 'waktu_selesai' => sprintf('%02d:00', 10 + $i)])->save();
            $jadwal->kelas()->attach($d['kelas']);
            $kelasUjian = KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $d['kelas']->id]);
            $relasi = $ujian->soalUjianCbt()->create(['soal_cbt_id' => $soal->soal_cbt_id, 'nomor_urut' => 1, 'bobot' => 2]);
            $peserta = $d['peserta'][1]->replicate();
            $peserta->fill(['ujian_cbt_id' => $ujian->id, 'kelas_ujian_cbt_id' => $kelasUjian->id, 'nomor_peserta' => 'TAMBAH-'.$i])->save();
            $peserta->jawabanPesertaUjianCbt()->create(['soal_ujian_cbt_id' => $relasi->id, 'soal_cbt_id' => $soal->soal_cbt_id, 'jawaban' => ['A'], 'skor' => $skor]);
            $tambahan[] = $ujian;
        }
        $this->tidakMengikuti($d);
        $this->put(route('rapor-sts.pengecualian', [$d['kegiatan'], $d['kelas']]), $this->payloadPengecualian($d))->assertSessionHasNoErrors();
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $cetak = route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][1]->id]);
        $this->get($cetak)->assertOk()->assertSeeText('dihitung dari 2 mata pelajaran')
            ->assertViewHas('baris', fn ($b) => $b[0]['jumlah'] === 100.0 && $b[0]['rata'] === 50.0 && $b[0]['jumlah_pengecualian'] === 1);
        $tambahan[0]->update(['hasil_difinalisasi_pada' => null]);
        $this->get($cetak)->assertOk()->assertSeeText('1 mata pelajaran belum tersedia')
            ->assertSeeText('Jumlah dan rata-rata belum dihitung')->assertDontSeeText('dihitung dari 1 mata pelajaran');
        $this->assertNull(app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas'])['baris'][1]['rata']);
    }

    public function test_pengecualian_tidak_bisa_menggantikan_nilai_atau_susulan_yang_masih_berjalan(): void
    {
        $d = $this->fondasi();
        $this->simpanPeriode($d);
        $this->tidakMengikuti($d);
        $mid = $d['mapel']->id;
        $url = route('rapor-sts.pengecualian', [$d['kegiatan'], $d['kelas']]);
        $payload = $this->payloadPengecualian($d);
        foreach ([
            ['status_kehadiran_ujian' => 'belum_absen'], ['status_kehadiran_ujian' => 'hadir'],
            ['status_susulan' => 'dijadwalkan'], ['status_susulan' => 'selesai'],
            ['status' => 'selesai'], ['status' => 'sedang_mengerjakan'],
            ['waktu_mulai' => now()],
        ] as $perubahan) {
            $this->tidakMengikuti($d);
            $d['peserta'][1]->update($perubahan);
            $this->put($url, $payload)->assertSessionHasErrors("pengecualian.$mid.tidak_mengikuti");
            $this->assertDatabaseCount('pengecualian_rapor_sts', 0);
        }
        $this->tidakMengikuti($d);
        $d['ujian']->update(['hasil_difinalisasi_pada' => null]);
        $this->put($url, $payload)->assertSessionHasErrors("pengecualian.$mid.tidak_mengikuti");
        $d['ujian']->update(['hasil_difinalisasi_pada' => now()]);
        $jadwal = $d['kegiatan']->jadwalUjianCbt()->first();
        $jadwal->update(['tanggal' => '2026-09-27']);
        $this->put($url, $payload)->assertSessionHasErrors("pengecualian.$mid.tidak_mengikuti");
        $jadwal->update(['tanggal' => '2026-09-15']);
        $soal = $d['ujian']->soalUjianCbt()->first();
        $d['peserta'][1]->jawabanPesertaUjianCbt()->create(['soal_ujian_cbt_id' => $soal->id, 'soal_cbt_id' => $soal->soal_cbt_id, 'jawaban' => ['B'], 'skor' => 0]);
        $this->put($url, $payload)->assertSessionHasErrors("pengecualian.$mid.tidak_mengikuti");
        $this->assertDatabaseCount('pengecualian_rapor_sts', 0);
    }

    public function test_pengecualian_memeriksa_kewenangan_cakupan_dan_perubahan_data(): void
    {
        $d = $this->fondasi();
        $this->simpanPeriode($d);
        $this->tidakMengikuti($d);
        $mid = $d['mapel']->id;
        $url = route('rapor-sts.pengecualian', [$d['kegiatan'], $d['kelas']]);
        $payload = $this->payloadPengecualian($d);
        $this->put($url, [...$payload, 'anggota_id' => 999999])->assertNotFound();
        $this->put($url, [...$payload, 'pengecualian' => [999999 => $payload['pengecualian'][$mid]]])->assertNotFound();
        $wali = $this->akunGuru($d['guru'], 'wali_kelas');
        $d['kelas']->update(['wali_kelas_id' => null]);
        $this->actingAs($wali)->put($url, $payload)->assertForbidden();
        $d['kelas']->update(['wali_kelas_id' => $d['guru']->id]);
        $wali->daftarPeran()->sync([Peran::where('kode', 'guru_mapel')->value('id')]);
        $this->actingAs($wali->fresh())->put($url, $payload)->assertForbidden();
        $wali->daftarPeran()->sync([Peran::where('kode', 'wali_kelas')->value('id')]);
        $this->actingAs($wali->fresh());
        $d['peserta'][1]->update(['status_kehadiran_ujian' => 'izin']);
        $this->put($url, $payload)->assertSessionHasErrors("pengecualian.$mid.tidak_mengikuti");
        $payload = $this->payloadPengecualian($d);
        $this->put($url, $payload)->assertSessionHasNoErrors();
        $this->put($url, $payload)->assertSessionHasErrors('versi');
        $this->assertSame($wali->id, PengecualianRaporSts::first()->ditetapkan_oleh_pengguna_id);
    }

    public function test_pengecualian_bisa_dicabut_dan_tidak_berlaku_setelah_data_ujian_berubah(): void
    {
        $d = $this->fondasi();
        $this->simpanPeriode($d);
        $this->tidakMengikuti($d);
        $url = route('rapor-sts.pengecualian', [$d['kegiatan'], $d['kelas']]);
        $this->put($url, $this->payloadPengecualian($d))->assertSessionHasNoErrors();
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $d['kelas']]), $this->payload($d))->assertSessionHasNoErrors();
        $cetak = route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][1]->id]);
        $d['peserta'][1]->update(['status_susulan' => 'dijadwalkan']);
        $this->get($cetak)->assertOk()->assertSeeText('Belum tersedia')->assertDontSeeText('Tidak mengikuti STS');
        $d['peserta'][1]->update(['status_susulan' => null]);
        $d['ujian']->update(['hasil_difinalisasi_pada' => now()->addMinute()]);
        $this->get($cetak)->assertOk()->assertSeeText('Belum tersedia')->assertDontSeeText('Tidak mengikuti STS');
        $this->put($url, $this->payloadPengecualian($d))->assertSessionHasNoErrors();
        $this->get($cetak)->assertOk();
        $payload = $this->payloadPengecualian($d);
        $payload['pengecualian'][$d['mapel']->id]['tidak_mengikuti'] = 0;
        $this->put($url, $payload)->assertSessionHasNoErrors();
        $this->get($cetak)->assertOk()->assertSeeText('Belum tersedia')->assertDontSeeText('Tidak mengikuti STS');
        $catatan = PengecualianRaporSts::first();
        $this->assertFalse($catatan->aktif);
        $this->assertNotNull($catatan->dibatalkan_pada);
        $this->assertSame($d['admin']->id, $catatan->dibatalkan_oleh_pengguna_id);
        $this->assertNotEmpty($catatan->alasan);
        $this->put($url, $this->payloadPengecualian($d))->assertSessionHasNoErrors();
        $d['peserta'][1]->update(['status' => 'selesai', 'waktu_mulai' => now()]);
        $soal = $d['ujian']->soalUjianCbt()->first();
        $d['peserta'][1]->jawabanPesertaUjianCbt()->create(['soal_ujian_cbt_id' => $soal->id, 'soal_cbt_id' => $soal->soal_cbt_id, 'jawaban' => ['A'], 'skor' => 2]);
        $this->get($cetak)->assertOk()->assertDontSeeText('Tidak mengikuti STS')
            ->assertViewHas('baris', fn ($b) => $b[0]['rata'] === 100.0 && $b[0]['jumlah_pengecualian'] === 0);
    }

    public function test_pengecualian_mapel_tanpa_paket_ditolak_dan_seluruh_perubahan_dibatalkan(): void
    {
        $d = $this->fondasi();
        $this->simpanPeriode($d);
        $this->tidakMengikuti($d);
        $mapel = MataPelajaran::create(['kode' => 'BELUM-STS', 'nama' => 'Belum ada paket', 'aktif' => true]);
        GuruMataPelajaran::create(['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $d['guru']->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
        $payload = $this->payloadPengecualian($d);
        $payload['pengecualian'][$mapel->id] = ['tidak_mengikuti' => 1, 'alasan' => 'Tidak ikut', 'sidik_kondisi' => str_repeat('a', 64)];
        $this->put(route('rapor-sts.pengecualian', [$d['kegiatan'], $d['kelas']]), $payload)->assertSessionHasErrors('pengecualian.'.$mapel->id.'.tidak_mengikuti');
        $this->assertDatabaseCount('pengecualian_rapor_sts', 0);
        $this->assertSame($payload['versi'], RaporStsKelas::first()->versi);
    }

    private function tidakMengikuti(array $d): void
    {
        $d['peserta'][1]->update(['status' => 'aktif', 'status_kehadiran_ujian' => 'sakit', 'status_susulan' => null, 'waktu_mulai' => null, 'waktu_selesai' => null]);
        $d['peserta'][1]->jawabanPesertaUjianCbt()->delete();
    }

    private function payloadPengecualian(array $d): array
    {
        $laporan = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $nilai = $laporan['baris'][1]['nilai']->firstWhere('mapel.id', $d['mapel']->id);

        return ['versi' => $laporan['pengaturan']->versi, 'anggota_id' => $d['anggota'][1]->id,
            'pengecualian' => [$d['mapel']->id => [
                'tidak_mengikuti' => 1, 'alasan' => 'Sakit berkepanjangan dan tidak mengikuti susulan.',
                'sidik_kondisi' => $nilai['sidik_kondisi'],
            ]],
        ];
    }

    private function simpanPeriode(array $d, array $ganti = []): void
    {
        $versi = RaporStsKelas::where('kelas_id', $d['kelas']->id)->value('versi') ?? 0;
        $this->actingAs($d['admin'])->put(route('rapor-sts.pengaturan', [$d['kegiatan'], $d['kelas']]), [
            'versi' => $versi, 'tanggal_awal_presensi' => '2026-07-01', 'tanggal_akhir_presensi' => '2026-09-20', 'tanggal_rapor' => '2026-09-26', ...$ganti,
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function payload(array $d): array
    {
        $r = app(RaporStsService::class)->bangun($d['kegiatan'], $d['kelas']);

        return ['versi' => $r['pengaturan']->versi, 'siswa' => $r['baris']->mapWithKeys(fn ($b) => [$b['anggota']->id => [
            ...$b['kehadiran'], 'sidik_sumber' => $b['sidik_sumber'], 'diperiksa' => 1, 'catatan_koreksi' => $b['koreksi']?->catatan_koreksi,
        ]])->all()];
    }

    private function akunGuru(Pegawai $guru, string $peran): Pengguna
    {
        $akun = Pengguna::create(['nama' => 'Wali Kelas Uji', 'username' => 'wali-sts', 'kata_sandi' => 'test-pass', 'peran' => 'pegawai', 'pegawai_id' => $guru->id, 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->sync([Peran::where('kode', $peran)->value('id')]);

        return $akun;
    }

    private function terapkanNilaiFondasi(array $d): KomponenNilai
    {
        $komponen = $this->hubungkanKomponenStsFondasi($d);
        $hasil = app(TerapkanNilaiCbtService::class)->terapkan($d['ujian'], $d['admin']->id);
        $this->assertSame(2, $hasil['ringkasan']['diterapkan']);

        return $komponen;
    }

    private function hubungkanKomponenStsFondasi(array $d): KomponenNilai
    {
        $komponen = KomponenNilai::create([
            'guru_mata_pelajaran_id' => $d['kelas']->guruMataPelajaran()->firstOrFail()->id,
            'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'nama' => $d['kegiatan']->nama, 'aktif' => true,
        ]);
        $d['ujian']->kelasUjianCbt()->update(['komponen_nilai_id' => $komponen->id]);

        return $komponen;
    }

    private function fondasi(): array
    {
        $admin = Pengguna::create(['nama' => 'Admin Rapor', 'username' => 'admin-rapor', 'kata_sandi' => 'test-pass', 'peran' => 'administrator', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $guru = Pegawai::create(['nama_lengkap' => 'Guru Wali Kelas, S.Pd.', 'nip' => '198001012005012001', 'jenis_kelamin' => 'P', 'jenis_pegawai' => 'Guru', 'aktif' => true]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'IX.A', 'tingkat' => 9, 'wali_kelas_id' => $guru->id, 'aktif' => true]);
        $mapel = MataPelajaran::create(['kode' => 'MTK-STS', 'nama' => 'Matematika', 'urutan' => 1, 'aktif' => true]);
        GuruMataPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $guru->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
        $jenis = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $kegiatan = KegiatanUjianCbt::create(['jenis_ujian_cbt_id' => $jenis->id, 'tahun_pelajaran_id' => $tahun->id, 'kode' => 'STS-UJI', 'nama' => 'Sumatif Tengah Semester', 'semester' => 'ganjil', 'tanggal_mulai' => '2026-09-15', 'tanggal_selesai' => '2026-09-20', 'status' => 'selesai']);
        $ujian = UjianCbt::create(['jenis_ujian_cbt_id' => $jenis->id, 'tahun_pelajaran_id' => $tahun->id, 'mata_pelajaran_id' => $mapel->id, 'kode' => 'RAPOR-PAKET', 'nama' => 'Matematika STS', 'semester' => 'ganjil', 'tingkat' => 9, 'jumlah_soal' => 1, 'acak_soal' => false, 'alur' => 'terpusat', 'status' => 'selesai', 'hasil_difinalisasi_pada' => now()]);
        $jadwal = $kegiatan->jadwalUjianCbt()->create(['ujian_cbt_id' => $ujian->id, 'mata_pelajaran_id' => $mapel->id, 'tanggal' => '2026-09-15', 'waktu_mulai' => '07:00', 'waktu_selesai' => '08:00', 'tingkat' => 9, 'status' => 'selesai']);
        $jadwal->kelas()->attach($kelas);
        $kelasUjian = KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $kelas->id]);
        $soal = SoalCbt::create(['tahun_pelajaran_id' => $tahun->id, 'mata_pelajaran_id' => $mapel->id, 'tingkat' => 9, 'kode' => 'STS-Q', 'jenis_soal' => 'pilihan_ganda', 'tingkat_kesulitan' => 'sedang', 'pertanyaan' => 'Hasil 1+1?', 'opsi' => ['pilihan' => ['A' => '2', 'B' => '3']], 'kunci_jawaban' => ['jawaban' => 'A'], 'skor_maksimal' => 2, 'status' => 'siap', 'aktif' => true]);
        $relasi = $ujian->soalUjianCbt()->create(['soal_cbt_id' => $soal->id, 'nomor_urut' => 1, 'bobot' => 2]);
        $anggota = collect();
        $peserta = collect();
        foreach (['Alya Contoh', 'Bima Contoh'] as $i => $nama) {
            $siswa = Siswa::create(['nama_lengkap' => $nama, 'nis' => 'N00'.$i, 'nisn' => '123456789'.$i, 'jenis_kelamin' => $i ? 'L' : 'P', 'aktif' => true]);
            $a = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'nomor_absen' => $i + 1, 'status_keanggotaan' => 'aktif']);
            $p = PesertaUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_ujian_cbt_id' => $kelasUjian->id, 'anggota_kelas_id' => $a->id, 'nomor_peserta' => 'RAPOR-'.$i, 'status' => 'selesai']);
            $p->jawabanPesertaUjianCbt()->create(['soal_ujian_cbt_id' => $relasi->id, 'soal_cbt_id' => $soal->id, 'jawaban' => [$i ? 'B' : 'A'], 'skor' => $i ? 0 : 2]);
            $anggota->push($a);
            $peserta->push($p);
        }
        foreach (['2026-07-02' => 'sakit', '2026-07-03' => 'izin', '2026-09-25' => 'alfa'] as $tanggal => $status) {
            AbsensiSiswa::create(['tanggal' => $tanggal, 'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'anggota_kelas_id' => $anggota[0]->id, 'siswa_id' => $anggota[0]->siswa_id, 'status_kehadiran' => $status, 'sumber' => 'manual']);
        }

        return compact('admin', 'tahun', 'guru', 'kelas', 'mapel', 'kegiatan', 'ujian', 'anggota', 'peserta');
    }
}
