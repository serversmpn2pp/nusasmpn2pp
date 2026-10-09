<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\AturanSanksiPoin;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\KehadiranRaporSts;
use App\Models\Kelas;
use App\Models\LampiranPerilakuSts;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruBkTingkat;
use App\Models\Peran;
use App\Models\RaporStsKelas;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Nilai\LampiranPerilakuStsService;
use App\Services\Nilai\RaporStsService;
use Carbon\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LampiranPerilakuStsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        Carbon::setTestNow('2026-10-09 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_hanya_kasus_terverifikasi_dalam_periode_dan_tahun_yang_diambil(): void
    {
        $d = $this->fondasi();
        $valid = $this->kasus($d);
        $teguran = $this->kasus($d, ['jenis_laporan' => 'kejadian', 'status_verifikasi' => 'tidak_perlu', 'status' => 'selesai', 'total_poin' => 0]);
        foreach ([['status_verifikasi' => 'diajukan'], ['status_verifikasi' => 'tidak_terbukti'], ['status' => 'dibatalkan'],
            ['jenis_laporan' => 'pembinaan', 'status_verifikasi' => 'tidak_perlu', 'status' => 'selesai'],
            ['tanggal_kejadian' => '2026-07-31'], ['tanggal_kejadian' => '2026-10-03'], ['siswa_id' => $d['anggota'][1]->siswa_id]] as $ganti) {
            $this->kasus($d, $ganti);
        }
        $tahun = TahunPelajaran::create(['nama' => '2025/2026', 'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30']);
        $this->kasus($d, ['tahun_pelajaran_id' => $tahun->id]);
        $r = $this->konteks($d)['baris'][0];
        $this->assertCount(2, $r['sumber']);
        $this->assertEqualsCanonicalizing(['laporan-'.$valid->id, 'laporan-'.$teguran->id], $r['sumber']->pluck('kunci')->all());
        $this->assertSame(0, $r['sumber']->firstWhere('kunci', 'laporan-'.$teguran->id)['poin']);
        $this->actingAs($d['bk'])->get(route('lampiran-perilaku-sts.index'))->assertOk()
            ->assertDontSeeText('RAHASIA-KONSELING')->assertDontSeeText('KRONOLOGI-INTERNAL')->assertDontSeeText('LAPORAN HASIL CAPAIAN PEMBELAJARAN');
    }

    public function test_bk_hanya_memeriksa_tingkatnya_dan_tidak_dapat_mengakses_nilai(): void
    {
        $d = $this->fondasi();
        $kelas8 = Kelas::create(['tahun_pelajaran_id' => $d['tahun']->id, 'nama' => 'VIII.A', 'tingkat' => 8, 'aktif' => true]);
        $this->actingAs($d['bk'])->get(route('lampiran-perilaku-sts.index'))->assertOk()->assertDontSeeText('VIII.A');
        $this->get(route('lampiran-perilaku-sts.index', ['kelas_id' => $kelas8->id]))->assertNotFound();
        $this->get(route('rapor-sts.index'))->assertForbidden();
        $this->actingAs($d['wali'])->put($this->url($d), $this->payload($d))->assertForbidden();
        $siswa = Pengguna::create(['nama' => 'Siswa', 'username' => 'siswa-perilaku', 'kata_sandi' => 'test-pass', 'peran' => 'siswa', 'siswa_id' => $d['anggota'][0]->siswa_id, 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $this->actingAs($siswa)->get(route('lampiran-perilaku-sts.index'))->assertForbidden();
    }

    public function test_penandatangan_harus_bk_sesuai_tingkat_dan_wakil_kesiswaan(): void
    {
        $d = $this->fondasi();
        $bk8 = $this->akun('bk', 'Guru BK Delapan');
        PenugasanGuruBkTingkat::create(['tahun_pelajaran_id' => $d['tahun']->id, 'pegawai_id' => $bk8->pegawai_id, 'tingkat' => 8, 'tanggal_mulai' => '2026-07-01', 'aktif' => true]);
        $p = $this->payload($d);
        $p['guru_bk_id'] = $bk8->pegawai_id;
        $this->actingAs($d['admin'])->put($this->url($d), $p)->assertSessionHasErrors('guru_bk_id');
        $p = $this->payload($d);
        $p['wakil_kesiswaan_id'] = $d['wali']->pegawai_id;
        $this->put($this->url($d), $p)->assertSessionHasErrors('wakil_kesiswaan_id');
        $this->assertDatabaseCount('lampiran_perilaku_sts', 0);
    }

    public function test_simpan_memerlukan_konfirmasi_dan_ringkasan_semua_kasus(): void
    {
        $d = $this->fondasi();
        $this->kasus($d);
        $p = $this->payload($d);
        $this->actingAs($d['bk'])->put($this->url($d), [...$p, 'diperiksa' => 0])->assertSessionHasErrors('diperiksa');
        $this->put($this->url($d), [...$p, 'baris' => []])->assertSessionHasErrors('baris');
        $p['baris']['laporan-palsu'] = ['kejadian' => 'Palsu', 'tindakan' => 'Palsu'];
        $this->put($this->url($d), $p)->assertSessionHasErrors('baris');
        $this->assertDatabaseCount('lampiran_perilaku_sts', 0);
    }

    public function test_sumber_berubah_dan_pemeriksaan_serentak_ditolak(): void
    {
        $d = $this->fondasi();
        $kasus = $this->kasus($d);
        $p = $this->payload($d);
        $kasus->update(['total_poin' => 10]);
        $this->actingAs($d['bk'])->put($this->url($d), $p)->assertSessionHasErrors('lampiran');
        $p = $this->payload($d);
        $this->put($this->url($d), $p)->assertSessionHasNoErrors();
        $this->put($this->url($d), $p)->assertSessionHasErrors('lampiran');
        $this->assertSame(1, LampiranPerilakuSts::first()->versi);
    }

    public function test_koreksi_poin_periode_dan_penugasan_membatalkan_kesiapan_lampiran(): void
    {
        $d = $this->fondasi();
        $kasus = $this->kasus($d);
        $this->periksa($d, 0);
        $this->assertTrue($this->konteks($d)['baris'][0]['siap']);
        $kasus->update(['status' => 'dibatalkan', 'status_verifikasi' => 'dibatalkan', 'total_poin' => 0]);
        $this->assertFalse($this->konteks($d)['baris'][0]['siap']);
        $this->periksa($d, 0);
        $this->transaksi($d, -15, '2026-09-02 12:00:00');
        $this->assertFalse($this->konteks($d)['baris'][0]['siap']);
        $this->periksa($d, 0);
        $d['rapor']->update(['tanggal_akhir_presensi' => '2026-10-01']);
        $this->assertFalse($this->konteks($d)['baris'][0]['siap']);
        $this->periksa($d, 0);
        PenugasanGuruBkTingkat::query()->update(['aktif' => false]);
        $this->assertFalse($this->konteks($d)['baris'][0]['siap']);
    }

    public function test_saldo_memakai_ledger_sampai_batas_laporan_tanpa_menggandakan_sanksi(): void
    {
        $d = $this->fondasi();
        $this->kasus($d);
        $this->transaksi($d, 30, '2026-07-10 12:00:00');
        $this->transaksi($d, 15, '2026-09-01 12:00:00');
        $this->transaksi($d, -5, '2026-09-02 12:00:00');
        $this->transaksi($d, 100, '2026-10-03 12:00:00');
        SanksiPoinSiswa::create(['siswa_id' => $d['anggota'][0]->siswa_id, 'tahun_pelajaran_id' => $d['tahun']->id,
            'aturan_sanksi_poin_id' => AturanSanksiPoin::first()->id, 'poin_saat_terpicu' => 45, 'status' => 'selesai', 'terpicu_pada' => '2026-09-01 13:00:00', 'catatan' => 'RAHASIA-SANKSI']);
        $r = $this->konteks($d)['baris'][0];
        $this->assertCount(2, $r['sumber']);
        $this->assertSame(['jumlah_kejadian' => 1, 'poin_masuk' => 15, 'poin_dikurangi' => 5, 'saldo' => 40], $r['ringkasan']);
        $this->assertNull($r['sumber']->first(fn ($b) => str_starts_with($b['kunci'], 'sanksi-'))['poin']);
    }

    public function test_cetak_gabungan_diblokir_sebelum_diperiksa_tetapi_nilai_saja_tetap_tersedia(): void
    {
        $d = $this->fondasi();
        $url = route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas']]);
        $this->actingAs($d['admin'])->get($url)->assertOk();
        $this->get($url.'?perilaku=1')->assertRedirect()->assertSessionHasErrors('cetak');
        $this->get($url.'?perilaku=1&pratinjau=1')->assertRedirect()->assertSessionHasErrors('cetak');
        $this->periksa($d, 0);
        $this->actingAs($d['admin'])->get($url.'?perilaku=1')->assertRedirect();
        $this->get($url.'?perilaku=1&anggota_id='.$d['anggota'][0]->id)->assertOk();
    }

    public function test_cetak_menggunakan_ringkasan_disetujui_tanda_tangan_dan_urutan_per_siswa(): void
    {
        $d = $this->fondasi();
        $kasus = $this->kasus($d);
        $this->transaksi($d, 15, '2026-09-01 12:00:00');
        $p = $this->payload($d);
        $p['baris']['laporan-'.$kasus->id] = ['kejadian' => 'Terlambat datang ke sekolah', 'tindakan' => 'Teguran lisan dan pengingat jadwal.'];
        $p['catatan'] = 'Mohon pendampingan untuk datang tepat waktu.';
        $this->actingAs($d['bk'])->put($this->url($d), $p)->assertSessionHasNoErrors();
        $this->periksa($d, 1);
        $this->actingAs($d['admin']);
        $cetak = $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'perilaku' => 1]))->assertOk()
            ->assertSeeText('LAPORAN PERILAKU DAN PEMBINAAN SISWA')->assertSeeText('Guru BK Tujuh')
            ->assertSeeText('Wakil Kesiswaan Contoh')->assertSeeText('Teguran lisan dan pengingat jadwal.')
            ->assertSeeText('Tidak ada catatan pelanggaran terverifikasi pada periode ini.')
            ->assertDontSeeText('RAHASIA-KONSELING')->assertDontSeeText('KRONOLOGI-INTERNAL');
        $html = $cetak->getContent();
        $this->assertStringNotContainsString('<th>Status</th>', $html);
        $a = strpos($html, 'data-behavior-member="'.$d['anggota'][0]->id.'"');
        $b = strpos($html, 'data-behavior-member="'.$d['anggota'][1]->id.'"');
        $this->assertLessThan($b, $a);
        $this->assertSame(2, substr_count($html, 'data-behavior-sheet'));
        $this->assertStringNotContainsString('catatan_rahasia', json_encode(LampiranPerilakuSts::first()->baris));
        $this->capture('class', $html);
        $this->capture('individual', $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'perilaku' => 1, 'anggota_id' => $d['anggota'][0]->id]))->getContent());
        $this->capture('preview', $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'perilaku' => 1, 'pratinjau' => 1]))->getContent());
        $this->capture('combinedpreview', $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'perilaku' => 1, 'pratinjau' => 1, 'anggota_id' => $d['anggota'][0]->id]))->getContent());
        $this->capture('index', $this->get(route('rapor-sts.index'))->getContent());
        $this->capture('review', $this->actingAs($d['bk'])->get(route('lampiran-perilaku-sts.index', ['kelas_id' => $d['kelas']->id]))->getContent());
    }

    public function test_banyak_kasus_dan_catatan_panjang_tidak_dihilangkan(): void
    {
        $d = $this->fondasi();
        foreach (range(1, 28) as $i) {
            $this->kasus($d);
        }
        $p = $this->payload($d);
        foreach ($p['baris'] as &$b) {
            $b['kejadian'] = str_repeat('W', 200);
            $b['tindakan'] = str_repeat('Ringkasan panjang. ', 10);
        }
        unset($b);
        $p['catatan'] = str_repeat('Catatan pembinaan untuk keluarga. ', 17);
        $this->actingAs($d['bk'])->put($this->url($d), $p)->assertSessionHasNoErrors();
        $url = route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'anggota_id' => $d['anggota'][0]->id, 'perilaku' => 1]);
        $html = $this->actingAs($d['admin'])->get($url)->assertOk()->getContent();
        $this->assertSame(28, substr_count($html, 'data-behavior-key='));
        $this->assertGreaterThan(1, substr_count($html, 'data-behavior-sheet'));
        $this->capture('long', $html);
    }

    public function test_tanggal_selesai_penugasan_dan_periode_belum_berakhir_dihormati(): void
    {
        $d = $this->fondasi();
        PenugasanGuruBkTingkat::query()->update(['tanggal_selesai' => '2026-09-01']);
        $this->assertCount(0, $this->konteks($d)['guruBk']);
        PenugasanGuruBkTingkat::query()->update(['tanggal_selesai' => null]);
        $d['rapor']->update(['tanggal_akhir_presensi' => '2026-10-10', 'tanggal_rapor' => '2026-10-10']);
        $this->actingAs($d['bk'])->put($this->url($d), $this->payload($d))->assertSessionHasErrors('lampiran');
    }

    public function test_angka_poin_dan_status_dari_klien_diabaikan(): void
    {
        $d = $this->fondasi();
        $kasus = $this->kasus($d);
        $p = $this->payload($d);
        $p['baris']['laporan-'.$kasus->id] += ['poin' => 0, 'status' => 'Dibatalkan', 'tanggal' => '2020-01-01'];
        $this->actingAs($d['bk'])->put($this->url($d), $p)->assertSessionHasNoErrors();
        $r = LampiranPerilakuSts::first()->baris[0];
        $this->assertSame(15, $r['poin']);
        $this->assertSame('Selesai', $r['status']);
        $this->assertSame('2026-09-01', $r['tanggal']);
        $this->assertSame(15, $kasus->fresh()->total_poin);
    }

    public function test_beberapa_guru_bk_memerlukan_pilihan_tanpa_mengambil_pegawai_pertama(): void
    {
        $d = $this->fondasi();
        $bk2 = $this->akun('bk', 'Guru BK Tujuh Kedua');
        PenugasanGuruBkTingkat::create(['tahun_pelajaran_id' => $d['tahun']->id, 'pegawai_id' => $bk2->pegawai_id, 'tingkat' => 7, 'tanggal_mulai' => '2026-07-01', 'aktif' => true]);
        $this->assertCount(2, $this->konteks($d)['guruBk']);
        $html = $this->actingAs($d['bk'])->get(route('lampiran-perilaku-sts.index'))->assertOk()->getContent();
        $dom = new \DOMDocument;
        $errors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($errors);
        $this->assertCount(0, (new \DOMXPath($dom))->query('//select[@id="behavior-bk-'.$d['anggota'][0]->id.'"]/option[@selected]'));
        $this->assertCount(0, (new \DOMXPath($dom))->query('//select[@id="behavior-bulk-bk"]/option[@selected]'));
        $p = $this->payload($d);
        $p['guru_bk_id'] = $bk2->pegawai_id;
        $this->put($this->url($d), $p)->assertSessionHasNoErrors();
        $this->assertSame($bk2->pegawai_id, (int) LampiranPerilakuSts::first()->guru_bk_id);
    }

    public function test_kolektif_menyimpan_siswa_dipilih_dengan_penandatangan_bersama_dan_koreksi_ringkasan(): void
    {
        $d = $this->fondasi();
        $kasus = $this->kasus($d);
        $bk2 = $this->akun('bk', 'Guru BK Kolektif');
        PenugasanGuruBkTingkat::create(['tahun_pelajaran_id' => $d['tahun']->id, 'pegawai_id' => $bk2->pegawai_id, 'tingkat' => 7, 'tanggal_mulai' => '2026-07-01', 'aktif' => true]);
        $p = $this->payloadKolektif($d);
        $p['guru_bk_id'] = $bk2->pegawai_id;
        $id = $d['anggota'][0]->id;
        $p['siswa'][$id]['baris']['laporan-'.$kasus->id] = ['kejadian' => 'Ringkasan kolektif dikoreksi', 'tindakan' => 'Pendampingan orang tua', 'poin' => 0, 'status' => 'Palsu'];
        $p['siswa'][$id]['catatan'] = 'Catatan yang belum disimpan per siswa.';
        $p['siswa'][$d['anggota'][1]->id]['catatan'] = 'Pertahankan kedisiplinan.';
        $this->actingAs($d['bk']);
        $this->putKolektif($d, $p)->assertSessionHasNoErrors()->assertRedirect()
            ->assertSessionHas('berhasil', '2 lampiran perilaku siswa telah diperiksa dan disimpan secara kolektif.');
        $this->assertDatabaseCount('lampiran_perilaku_sts', 2);
        $this->assertTrue($this->konteks($d)['baris']->every('siap'));
        foreach (LampiranPerilakuSts::all() as $r) {
            $this->assertSame($bk2->pegawai_id, (int) $r->guru_bk_id);
            $this->assertSame($d['wakil']->pegawai_id, (int) $r->wakil_kesiswaan_id);
            $this->assertSame($d['bk']->id, (int) $r->diperiksa_oleh_pengguna_id);
        }
        $saved = LampiranPerilakuSts::where('anggota_kelas_id', $id)->first();
        $this->assertSame('Ringkasan kolektif dikoreksi', $saved->baris[0]['kejadian']);
        $this->assertSame('Pendampingan orang tua', $saved->baris[0]['tindakan']);
        $this->assertSame(15, $saved->baris[0]['poin']);
        $this->assertSame('Selesai', $saved->baris[0]['status']);
        $this->assertSame($p['siswa'][$id]['catatan'], $saved->catatan);
        $this->get(route('lampiran-perilaku-sts.index'))->assertOk()->assertDontSee('<th>Status</th>', false);
    }

    public function test_kolektif_tidak_mengubah_siswa_yang_tidak_dipilih(): void
    {
        $d = $this->fondasi();
        $this->periksa($d, 1);
        $untouched = LampiranPerilakuSts::first()->getAttributes();
        $this->putKolektif($d, $this->payloadKolektif($d, [0]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('lampiran_perilaku_sts', 2);
        $this->assertSame($untouched, LampiranPerilakuSts::where('anggota_kelas_id', $d['anggota'][1]->id)->first()->getAttributes());
    }

    public function test_kolektif_memperbarui_pemeriksaan_tersimpan_tanpa_menggandakan_lampiran(): void
    {
        $d = $this->fondasi();
        $kasus = $this->kasus($d);
        $this->periksa($d, 0);
        $this->periksa($d, 1);
        $p = $this->payloadKolektif($d);
        $p['siswa'][$d['anggota'][0]->id]['baris']['laporan-'.$kasus->id]['tindakan'] = 'Tindak lanjut terbaru';
        $p['siswa'][$d['anggota'][1]->id]['catatan'] = 'Catatan terbaru untuk keluarga';
        $this->actingAs($d['admin']);
        $this->putKolektif($d, $p)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('lampiran_perilaku_sts', 2);
        $this->assertSame([2, 2], LampiranPerilakuSts::orderBy('anggota_kelas_id')->pluck('versi')->all());
        $this->assertSame('Tindak lanjut terbaru', LampiranPerilakuSts::where('anggota_kelas_id', $d['anggota'][0]->id)->first()->baris[0]['tindakan']);
        $this->assertDatabaseHas('lampiran_perilaku_sts', ['anggota_kelas_id' => $d['anggota'][1]->id,
            'catatan' => 'Catatan terbaru untuk keluarga', 'diperiksa_oleh_pengguna_id' => $d['admin']->id]);
        $ulang = $this->payloadKolektif($d);
        $ulang['siswa'][$d['anggota'][0]->id]['catatan'] = 'Tidak boleh tersimpan sebagian';
        $ulang['siswa'][$d['anggota'][1]->id]['versi'] = 1;
        $this->putKolektif($d, $ulang)->assertSessionHasErrors('lampiran');
        $this->assertNull(LampiranPerilakuSts::where('anggota_kelas_id', $d['anggota'][0]->id)->first()->catatan);
        $this->assertSame([2, 2], LampiranPerilakuSts::orderBy('anggota_kelas_id')->pluck('versi')->all());
    }

    public function test_kolektif_dibatalkan_seluruhnya_jika_versi_satu_siswa_berubah(): void
    {
        $d = $this->fondasi();
        $p = $this->payloadKolektif($d);
        $this->periksa($d, 1);
        $p['siswa'][$d['anggota'][0]->id]['catatan'] = 'Tidak boleh tersimpan sebagian.';
        $this->putKolektif($d, $p)->assertSessionHasErrors('lampiran');
        $this->assertDatabaseCount('lampiran_perilaku_sts', 1);
        $this->assertDatabaseMissing('lampiran_perilaku_sts', ['anggota_kelas_id' => $d['anggota'][0]->id]);
        $this->assertSame(1, LampiranPerilakuSts::first()->versi);
    }

    public function test_kolektif_dibatalkan_seluruhnya_jika_sumber_satu_siswa_berubah(): void
    {
        $d = $this->fondasi();
        $kasus = $this->kasus($d, ['siswa_id' => $d['anggota'][1]->siswa_id, 'anggota_kelas_id' => $d['anggota'][1]->id]);
        $p = $this->payloadKolektif($d);
        $kasus->update(['total_poin' => 25]);
        $this->actingAs($d['bk']);
        $this->putKolektif($d, $p)->assertSessionHasErrors('lampiran');
        $this->assertDatabaseCount('lampiran_perilaku_sts', 0);
    }

    public function test_kolektif_memerlukan_pilihan_konfirmasi_dan_payload_yang_cocok(): void
    {
        $d = $this->fondasi();
        $p = $this->payloadKolektif($d);
        $this->actingAs($d['bk']);
        $this->putKolektif($d, [...$p, 'diperiksa' => 0])->assertSessionHasErrors('diperiksa');
        $this->putKolektif($d, [...$p, 'anggota_ids' => []])->assertSessionHasErrors('anggota_ids');
        $this->putKolektif($d, [...$p, 'anggota_ids' => [$d['anggota'][0]->id]])->assertSessionHasErrors('anggota_ids');
        $this->putKolektif($d, [...$p, 'anggota_ids' => [$d['anggota'][0]->id, $d['anggota'][0]->id]])->assertSessionHasErrors('anggota_ids.0');
        $this->put(route('lampiran-perilaku-sts.kolektif', [$d['kegiatan'], $d['kelas']]), [...$p, 'siswa_json' => '{rusak'])
            ->assertSessionHasErrors('siswa_json');
        $this->putKolektif($d, [...$p, 'siswa' => ['asing' => reset($p['siswa'])]])->assertSessionHasErrors('anggota_ids');
        $this->assertDatabaseCount('lampiran_perilaku_sts', 0);
    }

    public function test_kolektif_tetap_melindungi_cakupan_kelas_dan_penandatangan(): void
    {
        $d = $this->fondasi();
        $p = $this->payloadKolektif($d);
        $this->actingAs($d['wali']);
        $this->putKolektif($d, $p)->assertForbidden();
        $kelas8 = Kelas::create(['tahun_pelajaran_id' => $d['tahun']->id, 'nama' => 'VIII.A', 'tingkat' => 8, 'aktif' => true]);
        $this->actingAs($d['bk'])->put(route('lampiran-perilaku-sts.kolektif', [$d['kegiatan'], $kelas8]), [])
            ->assertForbidden();
        $this->putKolektif($d, [...$p, 'guru_bk_id' => $d['wali']->pegawai_id])->assertSessionHasErrors('guru_bk_id');
        $this->putKolektif($d, [...$p, 'wakil_kesiswaan_id' => $d['bk']->pegawai_id])->assertSessionHasErrors('wakil_kesiswaan_id');
        $siswaAsing = Siswa::create(['nama_lengkap' => 'Siswa Kelas Lain', 'nisn' => '9876543210', 'jenis_kelamin' => 'P', 'aktif' => true]);
        $asing = AnggotaKelas::create(['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $kelas8->id,
            'siswa_id' => $siswaAsing->id, 'status_keanggotaan' => 'aktif']);
        $p['anggota_ids'] = [$d['anggota'][0]->id, $asing->id];
        $p['siswa'][$asing->id] = $p['siswa'][$d['anggota'][1]->id];
        unset($p['siswa'][$d['anggota'][1]->id]);
        $this->putKolektif($d, $p)->assertNotFound();
        $this->assertDatabaseCount('lampiran_perilaku_sts', 0);
    }

    public function test_kolektif_tidak_boleh_menghilangkan_kasus_dan_mengembalikan_edit_saat_validasi_gagal(): void
    {
        $d = $this->fondasi();
        $kasus = $this->kasus($d);
        $p = $this->payloadKolektif($d);
        $id = $d['anggota'][0]->id;
        $this->actingAs($d['bk']);
        $kosong = $p;
        $kosong['siswa'][$id]['baris'] = [];
        $this->putKolektif($d, $kosong)->assertSessionHasErrors('baris');
        $p['siswa'][$id]['baris']['laporan-'.$kasus->id]['kejadian'] = 'Koreksi ringkasan tetap terjaga';
        $p['siswa'][$id]['catatan'] = 'Catatan koreksi tetap terjaga';
        $this->putKolektif($d, [...$p, 'wakil_kesiswaan_id' => $d['wali']->pegawai_id])->assertSessionHasErrors('wakil_kesiswaan_id');
        $this->get(route('lampiran-perilaku-sts.index'))->assertOk()->assertSeeText('Koreksi ringkasan tetap terjaga')
            ->assertSeeText('Catatan koreksi tetap terjaga');
        $this->assertDatabaseCount('lampiran_perilaku_sts', 0);
    }

    public function test_kolektif_memvalidasi_tiap_ringkasan_catatan_dan_periode(): void
    {
        $d = $this->fondasi();
        $kasus = $this->kasus($d);
        $p = $this->payloadKolektif($d);
        $id = $d['anggota'][0]->id;
        $this->actingAs($d['bk']);
        $salah = $p;
        $salah['siswa'][$id]['baris']['laporan-'.$kasus->id]['tindakan'] = '   ';
        $this->putKolektif($d, $salah)->assertSessionHasErrors('siswa.'.$id.'.baris.laporan-'.$kasus->id.'.tindakan');
        $salah = $p;
        $salah['siswa'][$id]['catatan'] = str_repeat('a', 601);
        $this->putKolektif($d, $salah)->assertSessionHasErrors('siswa.'.$id.'.catatan');
        $salah = $p;
        unset($salah['siswa'][$id]['versi']);
        $this->putKolektif($d, $salah)->assertSessionHasErrors('siswa.'.$id.'.versi');
        $d['rapor']->update(['tanggal_akhir_presensi' => '2026-10-10', 'tanggal_rapor' => '2026-10-10']);
        $this->putKolektif($d, $this->payloadKolektif($d))->assertSessionHasErrors('lampiran');
        $this->assertDatabaseCount('lampiran_perilaku_sts', 0);
    }

    public function test_mode_poin_final_hanya_mengambil_pelanggaran_disahkan_positif_termasuk_presensi_otomatis(): void
    {
        $d = $this->fondasi();
        $manual = $this->kasus($d, ['tindakan_awal' => 'Poin manual sudah disahkan.']);
        $terlambat = $this->kasus($d, ['kunci_presensi_otomatis' => 'uji-terlambat', 'jenis_presensi_otomatis' => 'terlambat',
            'sumber_laporan' => 'absensi_otomatis', 'status' => 'diproses', 'tindakan_awal' => 'Poin terlambat otomatis.']);
        $alfa = $this->kasus($d, ['kunci_presensi_otomatis' => 'uji-alfa', 'jenis_presensi_otomatis' => 'alfa',
            'sumber_laporan' => 'absensi_otomatis', 'total_poin' => 25, 'status' => 'diproses', 'tindakan_awal' => 'Poin alfa otomatis.']);
        foreach ([['status_verifikasi' => 'diajukan'], ['status_verifikasi' => 'menunggu_pengesahan_wakil'],
            ['status_verifikasi' => 'tidak_terbukti'], ['status_verifikasi' => 'dibatalkan'], ['status' => 'dibatalkan'],
            ['status_verifikasi' => 'ditetapkan_pembinaan', 'total_poin' => 0], ['total_poin' => 0],
            ['jenis_laporan' => 'kejadian', 'status_verifikasi' => 'tidak_perlu', 'total_poin' => 0],
            ['jenis_laporan' => 'pembinaan'], ['tanggal_kejadian' => '2026-10-03'], ['tanggal_kejadian' => '2026-07-31']] as $ganti) {
            $this->kasus($d, $ganti);
        }
        SanksiPoinSiswa::create(['siswa_id' => $d['anggota'][0]->siswa_id, 'tahun_pelajaran_id' => $d['tahun']->id,
            'aturan_sanksi_poin_id' => AturanSanksiPoin::first()->id, 'poin_saat_terpicu' => 55, 'status' => 'selesai', 'terpicu_pada' => '2026-09-02']);
        $this->transaksi($d, 55, '2026-09-01');
        $this->transaksi($d, -5, '2026-09-02');
        $sebelum = $this->konteks($d)['baris'][0]['ringkasan'];
        $kasusSebelum = LaporanPembinaanSiswa::orderBy('id')->get()->map->getAttributes()->all();
        $ledgerSebelum = TransaksiPoinSiswa::orderBy('id')->get()->map->getAttributes()->all();
        $this->actingAs($d['bk']);
        $this->pilihIsi($d)->assertSessionHasNoErrors()->assertRedirect();
        $k = $this->konteks($d);
        $this->assertSame('poin_final', $k['isiLampiran']);
        $this->assertEqualsCanonicalizing(['laporan-'.$manual->id, 'laporan-'.$terlambat->id, 'laporan-'.$alfa->id], $k['baris'][0]['sumber']->pluck('kunci')->all());
        $this->assertSame(3, $k['baris'][0]['ringkasan']['jumlah_kejadian']);
        foreach (['poin_masuk', 'poin_dikurangi', 'saldo'] as $key) {
            $this->assertSame($sebelum[$key], $k['baris'][0]['ringkasan'][$key]);
        }
        $this->assertSame($kasusSebelum, LaporanPembinaanSiswa::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($ledgerSebelum, TransaksiPoinSiswa::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('sanksi_poin_siswa', 1);
        $this->putKolektif($d, $this->payloadKolektif($d))->assertSessionHasNoErrors();
        $review = $this->get(route('lampiran-perilaku-sts.index'))->assertOk()->assertSeeText('Termasuk poin otomatis terlambat dan alfa.')
            ->assertSeeText('Tidak ada pelanggaran berpoin final pada periode ini.');
        $this->capture('pointreview', $review->getContent());
        $print = $this->actingAs($d['admin'])->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'perilaku' => 1,
            'isi_lampiran_perilaku' => 'semua_terverifikasi']))->assertOk()->assertSeeText('Isi lampiran: Hanya pelanggaran berpoin final')
            ->assertSeeText('Poin manual sudah disahkan.')->assertSeeText('Poin terlambat otomatis.')->assertSeeText('Poin alfa otomatis.')
            ->assertSeeText('Tidak ada pelanggaran berpoin final pada periode ini.');
        $this->assertSame(3, substr_count($print->getContent(), 'data-behavior-key='));
        $this->assertStringNotContainsString('data-behavior-key="sanksi-', $print->getContent());
        $this->capture('pointprint', $print->getContent());
    }

    public function test_pilihan_isi_mengharuskan_pemeriksaan_ulang_meski_siswa_tidak_memiliki_kasus(): void
    {
        $d = $this->fondasi();
        $this->periksa($d, 0);
        $this->periksa($d, 1);
        $sidikLama = $this->konteks($d)['baris']->pluck('sidik_sumber')->all();
        $this->assertTrue($this->konteks($d)['baris']->every('siap'));
        $this->pilihIsi($d)->assertSessionHasNoErrors();
        $this->assertFalse($this->konteks($d)['baris']->contains('siap', true));
        $this->assertNotSame($sidikLama, $this->konteks($d)['baris']->pluck('sidik_sumber')->all());
        $this->actingAs($d['admin'])->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'], 'perilaku' => 1]))
            ->assertRedirect()->assertSessionHasErrors('cetak');
        $this->pilihIsi($d, 'semua_terverifikasi')->assertSessionHasNoErrors();
        $this->assertFalse($this->konteks($d)['baris']->contains('siap', true));
        $this->assertSame(2, $d['rapor']->fresh()->versi_isi_perilaku);
        $this->putKolektif($d, $this->payloadKolektif($d))->assertSessionHasNoErrors();
        $this->assertTrue($this->konteks($d)['baris']->every('siap'));
        $this->assertSame(1, $d['rapor']->fresh()->versi);
    }

    public function test_pilihan_isi_menolak_pemeriksaan_dari_mode_lama_dan_kasus_di_luar_mode(): void
    {
        $d = $this->fondasi();
        $this->kasus($d);
        $teguran = $this->kasus($d, ['status_verifikasi' => 'ditetapkan_pembinaan', 'total_poin' => 0]);
        $lama = $this->payloadKolektif($d);
        $this->actingAs($d['bk']);
        $this->pilihIsi($d)->assertSessionHasNoErrors();
        $this->putKolektif($d, $lama)->assertSessionHasErrors('lampiran');
        $palsu = $this->payloadKolektif($d);
        $palsu['siswa'][$d['anggota'][0]->id]['baris']['laporan-'.$teguran->id] = ['kejadian' => 'Teguran', 'tindakan' => 'Pembinaan'];
        $this->putKolektif($d, $palsu)->assertSessionHasErrors('baris');
        $this->assertDatabaseCount('lampiran_perilaku_sts', 0);
        $this->putKolektif($d, $this->payloadKolektif($d))->assertSessionHasNoErrors();
        $this->assertCount(1, LampiranPerilakuSts::where('anggota_kelas_id', $d['anggota'][0]->id)->first()->baris);
    }

    public function test_perubahan_kasus_yang_tidak_ditampilkan_tidak_membatalkan_lampiran_poin_final(): void
    {
        $d = $this->fondasi();
        $valid = $this->kasus($d);
        $teguran = $this->kasus($d, ['jenis_laporan' => 'kejadian', 'status_verifikasi' => 'tidak_perlu', 'total_poin' => 0]);
        $this->actingAs($d['bk']);
        $this->pilihIsi($d)->assertSessionHasNoErrors();
        $this->periksa($d, 0);
        $teguran->update(['tindakan_awal' => 'Pembinaan diperbarui.']);
        $this->assertTrue($this->konteks($d)['baris'][0]['siap']);
        $valid->update(['status_verifikasi' => 'dibatalkan', 'status' => 'dibatalkan', 'total_poin' => 0]);
        $this->assertFalse($this->konteks($d)['baris'][0]['siap']);
        $this->assertCount(0, $this->konteks($d)['baris'][0]['sumber']);
    }

    public function test_pilihan_isi_memvalidasi_mode_versi_periode_dan_cakupan_petugas(): void
    {
        $d = $this->fondasi();
        $url = route('lampiran-perilaku-sts.isi', [$d['kegiatan'], $d['kelas']]);
        $data = ['isi_lampiran_perilaku' => 'poin_final', 'versi_isi_perilaku' => 0, 'versi_rapor' => 1];
        $this->actingAs($d['wali'])->put($url, $data)->assertForbidden();
        $this->actingAs($d['bk'])->put($url, [...$data, 'isi_lampiran_perilaku' => 'palsu'])->assertSessionHasErrors('isi_lampiran_perilaku');
        $this->put($url, [...$data, 'versi_isi_perilaku' => 2])->assertSessionHasErrors('isi_lampiran_perilaku');
        $this->put($url, [...$data, 'versi_rapor' => 0])->assertSessionHasErrors('isi_lampiran_perilaku');
        $kelas8 = Kelas::create(['tahun_pelajaran_id' => $d['tahun']->id, 'nama' => 'VIII.A', 'tingkat' => 8, 'aktif' => true]);
        $this->put(route('lampiran-perilaku-sts.isi', [$d['kegiatan'], $kelas8]), $data)->assertForbidden();
        $this->put($url, $data)->assertSessionHasNoErrors();
        $this->put($url, $data)->assertSessionHasErrors('isi_lampiran_perilaku');
        $this->assertSame('poin_final', $d['rapor']->fresh()->isi_lampiran_perilaku);
        $this->assertSame($d['bk']->id, (int) $d['rapor']->fresh()->isi_perilaku_diubah_oleh_pengguna_id);
        $this->assertNotNull($d['rapor']->fresh()->isi_perilaku_diubah_pada);
        PenugasanGuruBkTingkat::query()->update(['tanggal_selesai' => '2026-09-01']);
        $this->pilihIsi($d, 'semua_terverifikasi')->assertForbidden();
        $this->assertSame('poin_final', $d['rapor']->fresh()->isi_lampiran_perilaku);
    }

    public function test_mode_awal_mempertahankan_sidik_lama_dan_simpan_pilihan_sama_tidak_membatalkan_pemeriksaan(): void
    {
        $d = $this->fondasi();
        $this->kasus($d);
        $this->periksa($d, 0);
        $k = $this->konteks($d);
        $r = $k['baris'][0];
        $a = $r['anggota'];
        $p = $k['pengaturan'];
        $lama = hash('sha256', json_encode([$a->id, $a->kelas_id, $a->siswa_id, $a->siswa->nama_lengkap,
            $p->tanggal_awal_presensi->toDateString(), $p->tanggal_akhir_presensi->toDateString(), $p->tanggal_rapor->toDateString(),
            $r['sumber']->all(), $r['ringkasan'], [], [$k['guruBk']->map(fn ($pegawai) => [$pegawai->id, $pegawai->nama_lengkap, $pegawai->nip])->all(),
                $k['wakilKesiswaan']->map(fn ($pegawai) => [$pegawai->id, $pegawai->nama_lengkap, $pegawai->nip])->all()]]));
        $this->assertSame($lama, $r['sidik_sumber']);
        $this->pilihIsi($d, 'semua_terverifikasi')->assertSessionHasNoErrors();
        $this->assertTrue($this->konteks($d)['baris'][0]['siap']);
        $this->assertSame(0, $d['rapor']->fresh()->versi_isi_perilaku);
        $this->assertNull($d['rapor']->fresh()->isi_perilaku_diubah_oleh_pengguna_id);
        $this->pilihIsi($d)->assertSessionHasNoErrors();
        $this->periksa($d, 0);
        $this->pilihIsi($d)->assertSessionHasNoErrors();
        $this->assertSame(1, $d['rapor']->fresh()->versi_isi_perilaku);
        $this->assertTrue($this->konteks($d)['baris'][0]['siap']);
    }

    public function test_pilihan_isi_hanya_berlaku_pada_kelas_kegiatan_dan_memerlukan_periode_tersimpan(): void
    {
        $d = $this->fondasi();
        $lain = Kelas::create(['tahun_pelajaran_id' => $d['tahun']->id, 'nama' => 'VII.B', 'tingkat' => 7, 'aktif' => true]);
        $pLain = RaporStsKelas::create(['kegiatan_ujian_cbt_id' => $d['kegiatan']->id, 'kelas_id' => $lain->id,
            'tanggal_awal_presensi' => '2026-08-01', 'tanggal_akhir_presensi' => '2026-10-02', 'tanggal_rapor' => '2026-10-02']);
        $this->actingAs($d['wakil']);
        $this->pilihIsi($d)->assertSessionHasNoErrors();
        $this->assertSame('semua_terverifikasi', $pLain->fresh()->isi_lampiran_perilaku);
        $this->assertSame(0, $pLain->fresh()->versi_isi_perilaku);
        $this->assertSame('semua_terverifikasi', app(LampiranPerilakuStsService::class)->konteks($d['kegiatan'], $lain)['isiLampiran']);
        $pLain->delete();
        $this->put(route('lampiran-perilaku-sts.isi', [$d['kegiatan'], $lain]), ['isi_lampiran_perilaku' => 'poin_final',
            'versi_isi_perilaku' => 0, 'versi_rapor' => 0])->assertSessionHasErrors('isi_lampiran_perilaku');
        $this->assertDatabaseCount('rapor_sts_kelas', 1);
    }

    private function fondasi(): array
    {
        $admin = Pengguna::create(['nama' => 'Admin', 'username' => 'admin-perilaku', 'kata_sandi' => 'test-pass', 'peran' => 'administrator', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $bk = $this->akun('bk', 'Guru BK Tujuh');
        $wakil = $this->akun('wakil_pimpinan_kesiswaan', 'Wakil Kesiswaan Contoh');
        $wali = $this->akun('wali_kelas', 'Wali Kelas Contoh');
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A', 'tingkat' => 7, 'wali_kelas_id' => $wali->pegawai_id, 'aktif' => true]);
        $kegiatan = KegiatanUjianCbt::create(['tahun_pelajaran_id' => $tahun->id, 'jenis_ujian_cbt_id' => JenisUjianCbt::where('kode', 'STS')->value('id'), 'kode' => 'STS-PERILAKU', 'nama' => 'Sumatif Tengah Semester I', 'semester' => 'ganjil', 'tanggal_mulai' => '2026-09-15', 'tanggal_selesai' => '2026-09-20', 'status' => 'selesai']);
        $rapor = RaporStsKelas::create(['kegiatan_ujian_cbt_id' => $kegiatan->id, 'kelas_id' => $kelas->id, 'tanggal_awal_presensi' => '2026-08-01', 'tanggal_akhir_presensi' => '2026-10-02', 'tanggal_rapor' => '2026-10-02', 'versi' => 1]);
        PenugasanGuruBkTingkat::create(['tahun_pelajaran_id' => $tahun->id, 'pegawai_id' => $bk->pegawai_id, 'tingkat' => 7, 'tanggal_mulai' => '2026-07-01', 'aktif' => true]);
        $anggota = collect();
        foreach (['Alya Contoh', 'Bima Contoh'] as $i => $nama) {
            $s = Siswa::create(['nama_lengkap' => $nama, 'nisn' => '123456789'.$i, 'jenis_kelamin' => 'P', 'aktif' => true]);
            $anggota->push(AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $s->id, 'nomor_absen' => $i + 1, 'status_keanggotaan' => 'aktif']));
        }
        foreach (app(RaporStsService::class)->bangun($kegiatan, $kelas)['baris'] as $r) {
            KehadiranRaporSts::create(['rapor_sts_kelas_id' => $rapor->id, 'anggota_kelas_id' => $r['anggota']->id,
                ...$r['kehadiran'], 'rekap_sumber' => $r['sumber'], 'diperiksa_pada' => now(), 'diperiksa_oleh_pengguna_id' => $admin->id]);
        }

        return compact('admin', 'bk', 'wakil', 'wali', 'tahun', 'kelas', 'kegiatan', 'rapor', 'anggota');
    }

    private function akun(string $peran, string $nama): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'nip' => '198001012005'.str_pad((string) (Pegawai::count() + 1), 6, '0', STR_PAD_LEFT), 'jenis_kelamin' => 'P', 'jenis_pegawai' => 'Guru', 'aktif' => true]);
        $p = Pengguna::create(['nama' => $nama, 'username' => 'perilaku-'.$pegawai->id, 'kata_sandi' => 'test-pass', 'peran' => 'pegawai', 'pegawai_id' => $pegawai->id, 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->sync([Peran::where('kode', $peran)->value('id')]);

        return $p;
    }

    private function kasus(array $d, array $ganti = []): LaporanPembinaanSiswa
    {
        return LaporanPembinaanSiswa::create(['nomor_laporan' => 'PERILAKU-'.(LaporanPembinaanSiswa::count() + 1),
            'siswa_id' => $d['anggota'][0]->siswa_id, 'anggota_kelas_id' => $d['anggota'][0]->id,
            'kelas_id' => $d['kelas']->id, 'tahun_pelajaran_id' => $d['tahun']->id, 'tanggal_kejadian' => '2026-09-01',
            'jenis_laporan' => 'pelanggaran', 'status' => 'selesai', 'status_verifikasi' => 'disahkan', 'total_poin' => 15,
            'kronologi' => 'KRONOLOGI-INTERNAL', 'catatan_rahasia' => 'RAHASIA-KONSELING', 'tindakan_awal' => 'Teguran lisan.', ...$ganti]);
    }

    private function transaksi(array $d, int $poin, string $tanggal): void
    {
        TransaksiPoinSiswa::create(['siswa_id' => $d['anggota'][0]->siswa_id, 'tahun_pelajaran_id' => $d['tahun']->id,
            'kunci_sumber' => 'uji-perilaku-'.(TransaksiPoinSiswa::count() + 1), 'poin' => $poin, 'jenis' => $poin > 0 ? 'pelanggaran' : 'reward', 'keterangan' => 'Transaksi uji.', 'tercatat_pada' => $tanggal]);
    }

    private function konteks(array $d): array
    {
        return app(LampiranPerilakuStsService::class)->konteks($d['kegiatan'], $d['kelas']);
    }

    private function payload(array $d, int $index = 0): array
    {
        $r = $this->konteks($d)['baris'][$index];

        return ['anggota_id' => $r['anggota']->id, 'versi' => $r['tersimpan']?->versi ?? 0, 'sidik_sumber' => $r['sidik_sumber'],
            'guru_bk_id' => $d['bk']->pegawai_id, 'wakil_kesiswaan_id' => $d['wakil']->pegawai_id, 'diperiksa' => 1,
            'baris' => $r['sumber']->mapWithKeys(fn ($r) => [$r['kunci'] => ['kejadian' => $r['kejadian'], 'tindakan' => $r['tindakan']]])->all()];
    }

    private function periksa(array $d, int $index): void
    {
        $this->actingAs($d['bk'])->put($this->url($d), $this->payload($d, $index))->assertSessionHasNoErrors()->assertRedirect();
    }

    private function payloadKolektif(array $d, array $indexes = [0, 1]): array
    {
        $siswa = [];
        foreach ($indexes as $index) {
            $p = $this->payload($d, $index);
            $siswa[$p['anggota_id']] = ['versi' => $p['versi'], 'sidik_sumber' => $p['sidik_sumber'], 'baris' => $p['baris']];
        }

        return ['anggota_ids' => array_keys($siswa), 'guru_bk_id' => $d['bk']->pegawai_id,
            'wakil_kesiswaan_id' => $d['wakil']->pegawai_id, 'diperiksa' => 1, 'siswa' => $siswa];
    }

    private function putKolektif(array $d, array $data): TestResponse
    {
        $data['siswa_json'] = json_encode($data['siswa']);
        unset($data['siswa']);

        return $this->put(route('lampiran-perilaku-sts.kolektif', [$d['kegiatan'], $d['kelas']]), $data);
    }

    private function pilihIsi(array $d, string $isi = 'poin_final'): TestResponse
    {
        $p = $d['rapor']->fresh();

        return $this->put(route('lampiran-perilaku-sts.isi', [$d['kegiatan'], $d['kelas']]), [
            'isi_lampiran_perilaku' => $isi, 'versi_isi_perilaku' => $p->versi_isi_perilaku, 'versi_rapor' => $p->versi,
        ]);
    }

    private function url(array $d): string
    {
        return route('lampiran-perilaku-sts.simpan', [$d['kegiatan'], $d['kelas']]);
    }

    private function capture(string $nama, string $html): void
    {
        if (! getenv('NUSA_CAPTURE_PERILAKU_STS')) {
            return;
        }
        $dir = storage_path('framework/testing/perilaku-sts');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/'.$nama.'.html', $html);
    }
}
