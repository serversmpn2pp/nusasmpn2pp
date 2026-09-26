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
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PengecualianRaporSts;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PesertaUjianCbt;
use App\Models\RaporStsKelas;
use App\Models\Siswa;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use App\Services\Cbt\PengacakPenyajianCbt;
use App\Services\Nilai\RaporStsService;
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
            ->assertDontSeeText('DRAF PRATINJAU')->assertSeeText('Cetak / Simpan PDF')
            ->assertViewHas('baris', fn ($b) => $b->count() === 2 && $b[0]['kehadiran']['sakit'] === 2 && $b->every(fn ($r) => $r['siap']));
        $this->assertFalse($d['ujian']->fresh()->tampilkan_hasil);
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
        $this->get(route('rapor-sts.index', ['kelas_id' => $asing->id]))->assertNotFound();
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $asing, 'pratinjau' => 1]))->assertForbidden();
        $this->put(route('rapor-sts.kehadiran', [$d['kegiatan'], $asing]), [])->assertForbidden();
        $this->put(route('rapor-sts.pengaturan', [$d['kegiatan'], $asing]), [])->assertForbidden();
        $wali->daftarPeran()->sync([Peran::where('kode', 'guru_mapel')->value('id')]);
        $this->actingAs($wali->fresh())->get(route('rapor-sts.index'))->assertForbidden();
        $wali->daftarPeran()->sync([Peran::where('kode', 'wakil_pimpinan_kurikulum')->value('id')]);
        $this->actingAs($wali->fresh())->get(route('rapor-sts.index', ['kelas_id' => $asing->id]))->assertOk();
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
        $this->assertNotNull($d['peserta'][1]->jawabanPesertaUjianCbt()->first());
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
        $this->get($cetak)->assertRedirect()->assertSessionHasErrors('cetak');
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
        $this->get($cetak)->assertRedirect()->assertSessionHasErrors('cetak');
        $d['peserta'][1]->update(['status_susulan' => null]);
        $d['ujian']->update(['hasil_difinalisasi_pada' => now()->addMinute()]);
        $this->get($cetak)->assertRedirect()->assertSessionHasErrors('cetak');
        $this->put($url, $this->payloadPengecualian($d))->assertSessionHasNoErrors();
        $this->get($cetak)->assertOk();
        $payload = $this->payloadPengecualian($d);
        $payload['pengecualian'][$d['mapel']->id]['tidak_mengikuti'] = 0;
        $this->put($url, $payload)->assertSessionHasNoErrors();
        $this->get($cetak)->assertRedirect()->assertSessionHasErrors('cetak');
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
