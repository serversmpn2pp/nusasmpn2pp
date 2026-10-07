<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\KehadiranRaporSts;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\PengaturanMapelRaporSts;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\RaporStsKelas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Nilai\LegerStsService;
use App\Services\Nilai\MapelRaporStsService;
use App\Services\Nilai\RaporStsService;
use App\Services\Nilai\StsManualService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MapelRaporStsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_tetap_menampilkan_semua_mapel_dan_mendeteksi_nilai_kosong(): void
    {
        $d = $this->data();
        $r = $this->rapor($d);
        $this->assertCount(3, $r['mapel']);
        $this->assertFalse($r['baris'][0]['nilai_lengkap']);
        $this->assertNull($r['baris'][0]['rata']);
        $this->assertDatabaseCount('pengaturan_mapel_rapor_sts', 0);
        $this->actingAs($d['admin'])->get(route('rapor-sts.index'))->assertOk()->assertSee('Mata pelajaran rapor STS');
        $this->assertDatabaseCount('pengaturan_mapel_rapor_sts', 0);
    }

    public function test_pengecualian_menghitung_rapor_leger_statistik_dan_ranking_seluruh_kelas_paralel(): void
    {
        $d = $this->data();
        $sebelum = NilaiSiswa::get()->toArray();
        $service = app(LegerStsService::class);
        $awal = $service->bangun($d['kegiatan'], $d['kelas'][0]);
        $this->assertTrue($awal['ranking_sementara']);
        $this->assertSame(54.5, $awal['baris']->firstWhere('anggota.siswa.nama_lengkap', 'Alya VIII.A')['rata_leger']);
        $this->assertTrue($service->bangunTingkat($d['kegiatan'], $d['kelas'], 8)['baris']->every(fn ($b) => $b['jumlah_mapel'] === 3));
        $this->simpan($d)->assertSessionHasNoErrors();
        foreach ($d['kelas'] as $kelas) {
            $r = $this->rapor($d, $kelas);
            $this->assertCount(2, $r['mapel']);
            $this->assertTrue($r['baris']->every(fn ($b) => $b['nilai_lengkap']));
            $this->assertFalse($r['mapel']->contains('id', $d['tanpa']->id));
        }
        $r = $this->rapor($d);
        $this->assertSame(163.5, $r['baris'][0]['jumlah']);
        $this->assertSame(81.75, $r['baris'][0]['rata']);
        $service = app(LegerStsService::class);
        $leger = $service->bangun($d['kegiatan'], $d['kelas'][0]);
        $this->assertFalse($leger['ranking_sementara']);
        $this->assertSame(83.38, $leger['ringkasan']['rata_kelas']);
        $this->assertSame(2, $leger['baris']->firstWhere('anggota.siswa.nama_lengkap', 'Alya VIII.A')['ranking']);
        $this->assertSame(1, $leger['baris']->firstWhere('anggota.siswa.nama_lengkap', 'Bima VIII.A')['ranking']);
        $tingkat = $service->bangunTingkat($d['kegiatan'], $d['kelas'], 8);
        $this->assertTrue($tingkat['baris']->every(fn ($b) => $b['jumlah_mapel'] === 2));
        $this->assertFalse($tingkat['ranking_sementara']);
        $this->assertSame(76.69, $tingkat['ringkasan']['rata_tingkat']);
        $this->assertSame(4, $tingkat['ringkasan']['masuk_ranking']);
        $this->assertFalse($tingkat['statistik_mapel']->contains('mapel.id', $d['tanpa']->id));
        $juara = $service->kandidatPenghargaan($tingkat, 'keseluruhan', null, 10);
        $this->assertCount(4, $juara['kandidat']);
        $this->assertSame(85.0, $juara['kandidat'][0]['nilai']);
        $this->assertSame($sebelum, NilaiSiswa::get()->toArray());
        $this->assertDatabaseHas('pengaturan_mapel_rapor_sts', ['tingkat' => 8, 'versi' => 1, 'diubah_oleh_pengguna_id' => $d['admin']->id]);
        $this->assertDatabaseCount('riwayat_mapel_rapor_sts', 1);
        $this->assertSame([$d['tanpa']->id], json_decode(DB::table('riwayat_mapel_rapor_sts')->value('mapel_dikecualikan_sesudah'), true));
    }

    public function test_pengaturan_terpisah_per_kegiatan_semester_dan_tingkat(): void
    {
        $d = $this->data();
        $this->simpan($d)->assertSessionHasNoErrors();
        $lain = $d['kegiatan']->replicate();
        $lain->fill(['kode' => 'STS-GENAP', 'semester' => 'genap'])->save();
        $this->assertTrue(app(MapelRaporStsService::class)->dikecualikan($lain, 8)->isEmpty());
        $kelas9 = Kelas::create(['tahun_pelajaran_id' => $d['tahun']->id, 'nama' => 'IX.A', 'tingkat' => 9, 'aktif' => true]);
        $this->penugasan($d, $kelas9, $d['tanpa']);
        $this->assertTrue(app(MapelRaporStsService::class)->dikecualikan($d['kegiatan'], 9)->isEmpty());
        $this->assertCount(1, $this->rapor($d, $kelas9)['mapel']);
        $this->assertDatabaseCount('pengaturan_mapel_rapor_sts', 1);
    }

    public function test_pilihan_bisa_dikembalikan_dan_riwayat_lama_tetap_tersimpan(): void
    {
        $d = $this->data();
        $this->simpan($d)->assertSessionHasNoErrors();
        $this->simpan($d, [$d['pjok']->id, $d['kmt']->id, $d['tanpa']->id])->assertSessionHasNoErrors();
        $this->assertCount(3, $this->rapor($d)['mapel']);
        $this->assertNull($this->rapor($d)['baris'][0]['rata']);
        $this->assertDatabaseCount('riwayat_mapel_rapor_sts', 2);
        $this->assertSame([], PengaturanMapelRaporSts::firstOrFail()->mapel_dikecualikan);
        $this->simpan($d, [$d['pjok']->id, $d['kmt']->id, $d['tanpa']->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('riwayat_mapel_rapor_sts', 2);
    }

    public function test_wali_kelas_dan_guru_hanya_bisa_melihat_tanpa_mengubah_pilihan(): void
    {
        $d = $this->data();
        $payload = $this->payload($d);
        foreach (['wali_kelas', 'guru_mapel'] as $kode) {
            $akun = $this->akun($d['pegawai'], $kode);
            $this->actingAs($akun)->putJson($this->url($d), $payload)->assertForbidden();
            if ($kode === 'wali_kelas') {
                $this->get(route('rapor-sts.index'))->assertOk()->assertViewHas('dapatMengaturMapel', false)
                    ->assertDontSee('id="sts-mapel-form"', false)->assertSee('Pilihan ditetapkan oleh administrator');
            }
        }
        $this->assertDatabaseCount('pengaturan_mapel_rapor_sts', 0);
    }

    public function test_waka_kurikulum_dapat_mengatur(): void
    {
        $d = $this->data();
        $waka = $this->akun($d['pegawai'], 'wakil_pimpinan_kurikulum');
        $this->actingAs($waka)->put($this->url($d), $this->payload($d))->assertSessionHasNoErrors();
        $this->assertSame($waka->id, PengaturanMapelRaporSts::firstOrFail()->diubah_oleh_pengguna_id);
    }

    public function test_validasi_menolak_mapel_asing_kosong_duplikat_alasan_kosong_dan_cakupan_salah(): void
    {
        $d = $this->data();
        $asing = MataPelajaran::create(['nama' => 'Mapel asing', 'aktif' => true]);
        $this->actingAs($d['admin']);
        $body = $this->payload($d);
        foreach ([['mapel_ids' => []], ['mapel_ids' => [$asing->id]], ['mapel_ids' => [$d['kmt']->id, $d['kmt']->id]],
            ['alasan_mapel' => '  '], ['alasan_mapel' => str_repeat('x', 501)], ['versi_mapel' => -1], ['sidik_mapel' => 'invalid']] as $perubahan) {
            $this->putJson($this->url($d), [...$body, ...$perubahan])->assertUnprocessable();
        }
        $jenisLain = JenisUjianCbt::where('kode', '!=', 'STS')->firstOrFail();
        $d['kegiatan']->update(['jenis_ujian_cbt_id' => $jenisLain->id]);
        $this->putJson($this->url($d), $body)->assertNotFound();
        $this->assertDatabaseCount('pengaturan_mapel_rapor_sts', 0);
    }

    public function test_form_lama_ditolak_setelah_versi_atau_daftar_mapel_berubah(): void
    {
        $d = $this->data();
        $lama = $this->payload($d);
        $this->simpan($d)->assertSessionHasNoErrors();
        $this->putJson($this->url($d), $lama)->assertUnprocessable()->assertJsonValidationErrors('versi_mapel');
        $baru = $this->payload($d);
        $mapel = MataPelajaran::create(['nama' => 'Mapel baru', 'aktif' => true]);
        $this->penugasan($d, $d['kelas'][0], $mapel);
        $this->putJson($this->url($d), $baru)->assertUnprocessable()->assertJsonValidationErrors('versi_mapel');
        $this->assertDatabaseCount('riwayat_mapel_rapor_sts', 1);
        $this->assertCount(3, $this->rapor($d)['mapel']);
        $this->assertNull($this->rapor($d)['baris'][0]['rata']);
    }

    public function test_mapel_praktik_dan_jadwal_cbt_tidak_bisa_disembunyikan_meski_belum_final(): void
    {
        $d = $this->data();
        $this->actingAs($d['admin'])->putJson($this->url($d), $this->payload($d, [$d['pjok']->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('mapel_ids');
        $jadwal = $d['kegiatan']->jadwalUjianCbt()->create(['mata_pelajaran_id' => $d['tanpa']->id, 'tanggal' => '2026-09-15', 'waktu_mulai' => '07:00', 'waktu_selesai' => '08:00', 'tingkat' => 8, 'status' => 'draft']);
        $jadwal->kelas()->attach($d['kelas'][1]);
        $this->simpan($d)->assertSessionHasErrors('mapel_ids');
        $jadwal->update(['status' => 'dibatalkan']);
        $this->simpan($d)->assertSessionHasNoErrors();
    }

    public function test_sumber_sts_baru_memulihkan_mapel_di_semua_kelas_tanpa_menghapus_riwayat(): void
    {
        $d = $this->data();
        $this->simpan($d)->assertSessionHasNoErrors();
        $gmp = GuruMataPelajaran::where('kelas_id', $d['kelas'][1]->id)->where('mata_pelajaran_id', $d['tanpa']->id)->firstOrFail();
        KomponenNilai::create(['guru_mata_pelajaran_id' => $gmp->id, 'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'nama' => 'STS baru', 'aktif' => true]);
        foreach ($d['kelas'] as $kelas) {
            $this->assertCount(3, $this->rapor($d, $kelas)['mapel']);
            $this->assertNull($this->rapor($d, $kelas)['baris'][0]['rata']);
        }
        $this->get(route('rapor-sts.index'))->assertOk()->assertSee('kembali diikutkan karena memiliki sumber STS baru');
        $this->assertDatabaseCount('riwayat_mapel_rapor_sts', 1);
        $this->assertSame([$d['tanpa']->id], PengaturanMapelRaporSts::firstOrFail()->mapel_dikecualikan);
    }

    public function test_rapor_dan_leger_cetak_hanya_menggunakan_mapel_yang_diikutkan(): void
    {
        $d = $this->data();
        $this->simpan($d)->assertSessionHasNoErrors();
        $this->siapkanCetak($d);
        $this->get(route('rapor-sts.cetak', [$d['kegiatan'], $d['kelas'][0]]))->assertOk()->assertSee('81,75')
            ->assertDontSeeText('Pendidikan Inklusi')->assertDontSeeText('DRAF PRATINJAU');
        foreach (['kelas', 'tingkat'] as $mode) {
            $this->get(route('leger-sts.cetak', ['mode' => $mode, 'kegiatan_id' => $d['kegiatan']->id, 'kelas_id' => $d['kelas'][0]->id, 'tingkat' => 8]))
                ->assertOk()->assertDontSeeText('Pendidikan Inklusi')->assertSee('81,75');
        }
    }

    public function test_mapel_predikat_tidak_muncul_dalam_pilihan_dan_tidak_dapat_dikirim(): void
    {
        $d = $this->data();
        $ekskul = MataPelajaran::create(['nama' => 'Pramuka', 'kelompok' => 'Ekstrakurikuler', 'aktif' => true]);
        $this->penugasan($d, $d['kelas'][0], $ekskul);
        $k = app(MapelRaporStsService::class)->konteks($d['kegiatan'], 8);
        $this->assertFalse($k['mapel']->contains('mapel.id', $ekskul->id));
        $this->actingAs($d['admin'])->putJson($this->url($d), $this->payload($d, [$d['kmt']->id, $d['pjok']->id, $ekskul->id]))->assertUnprocessable();
    }

    public function test_render_fixture_admin_wali_dan_perubahan_sumber(): void
    {
        $d = $this->data();
        $this->actingAs($d['admin']);
        $this->capture('default', $this->get(route('rapor-sts.index'))->assertOk()->getContent());
        $this->simpan($d)->assertSessionHasNoErrors();
        $this->siapkanCetak($d);
        $this->capture('selected', $this->get(route('rapor-sts.index'))->assertOk()->getContent());
        $wali = $this->akun($d['pegawai'], 'wali_kelas');
        $this->actingAs($wali);
        $this->capture('readonly', $this->get(route('rapor-sts.index'))->assertOk()->getContent());
    }

    private function capture(string $name, string $html): void
    {
        if (getenv('NUSA_CAPTURE_MAPEL_UI')) {
            $dir = storage_path('framework/testing/mapel-sts');
            File::ensureDirectoryExists($dir);
            File::put($dir.'/'.$name.'.html', $html);
        }
    }

    private function siapkanCetak(array $d): void
    {
        $kelas = $d['kelas'][0];
        $pengaturan = RaporStsKelas::create(['kegiatan_ujian_cbt_id' => $d['kegiatan']->id, 'kelas_id' => $kelas->id,
            'tanggal_awal_presensi' => '2026-07-01', 'tanggal_akhir_presensi' => '2026-09-20', 'tanggal_rapor' => '2026-10-05', 'versi' => 1]);
        foreach ($this->rapor($d)['baris'] as $baris) {
            KehadiranRaporSts::create(['rapor_sts_kelas_id' => $pengaturan->id, 'anggota_kelas_id' => $baris['anggota']->id,
                'sakit' => 0, 'izin' => 0, 'alfa' => 0, 'rekap_sumber' => $baris['sumber'], 'diperiksa_pada' => now(), 'diperiksa_oleh_pengguna_id' => $d['admin']->id]);
        }
    }

    private function rapor(array $d, ?Kelas $kelas = null): array
    {
        return app(RaporStsService::class)->bangun($d['kegiatan']->fresh(), ($kelas ?? $d['kelas'][0])->fresh());
    }

    private function payload(array $d, ?array $mapel = null): array
    {
        $k = app(MapelRaporStsService::class)->konteks($d['kegiatan'], 8);

        return ['versi_mapel' => $k['pengaturan']?->versi ?? 0, 'sidik_mapel' => $k['sidik'],
            'mapel_ids' => $mapel ?? [$d['kmt']->id, $d['pjok']->id], 'alasan_mapel' => 'Pendidikan Inklusi tidak menyelenggarakan STS.'];
    }

    private function url(array $d): string
    {
        return route('rapor-sts.mapel', [$d['kegiatan'], $d['kelas'][0]]);
    }

    private function simpan(array $d, ?array $mapel = null)
    {
        return $this->actingAs($d['admin'])->put($this->url($d), $this->payload($d, $mapel));
    }

    private function akun(Pegawai $pegawai, string $kode): Pengguna
    {
        $akun = Pengguna::create(['nama' => $kode, 'username' => 'mapel-sts-'.$kode, 'pegawai_id' => $pegawai->id,
            'peran' => 'pegawai', 'kata_sandi' => 'test-pass', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $akun->daftarPeran()->attach(Peran::where('kode', $kode)->firstOrFail());

        return $akun;
    }

    private function penugasan(array $d, Kelas $kelas, MataPelajaran $mapel): GuruMataPelajaran
    {
        return GuruMataPelajaran::create(['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $d['pegawai']->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
    }

    private function data(): array
    {
        $this->travelTo(now()->setDate(2026, 10, 5));
        $admin = Pengguna::create(['nama' => 'Admin Mapel STS', 'username' => 'admin-mapel-sts', 'peran' => 'administrator', 'kata_sandi' => 'test-pass', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru Mapel STS', 'aktif' => true]);
        $pjok = MataPelajaran::create(['nama' => 'Pendidikan Jasmani, Olahraga, dan Kesehatan (PJOK)', 'urutan' => 1, 'aktif' => true]);
        $kmt = MataPelajaran::create(['nama' => 'Keminangkabauan', 'urutan' => 2, 'aktif' => true]);
        $tanpa = MataPelajaran::create(['nama' => 'Pendidikan Inklusi', 'urutan' => 3, 'aktif' => true]);
        $kegiatan = KegiatanUjianCbt::create(['jenis_ujian_cbt_id' => JenisUjianCbt::where('kode', 'STS')->firstOrFail()->id,
            'tahun_pelajaran_id' => $tahun->id, 'kode' => 'STS-MAPEL', 'nama' => 'STS Ganjil 2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-09-15', 'tanggal_selesai' => '2026-09-20', 'status' => 'selesai']);
        $kelas = collect();
        $d = compact('admin', 'tahun', 'pegawai', 'pjok', 'kmt', 'tanpa', 'kegiatan');
        foreach (['VIII.A', 'VIII.B'] as $i => $nama) {
            $k = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => $nama, 'tingkat' => 8, 'wali_kelas_id' => $pegawai->id, 'aktif' => true]);
            $kelas->push($k);
            $siswa = collect();
            foreach (['Alya', 'Bima'] as $j => $namaSiswa) {
                $s = Siswa::create(['nama_lengkap' => $namaSiswa.' '.$nama, 'nis' => 'MAPEL-'.$i.$j, 'aktif' => true]);
                AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $k->id, 'siswa_id' => $s->id, 'nomor_absen' => $j + 1, 'status_keanggotaan' => 'aktif']);
                $siswa->push($s);
            }
            foreach ([$pjok, $kmt, $tanpa] as $mapel) {
                $gmp = $this->penugasan($d, $k, $mapel);
                if ($mapel->is($tanpa)) {
                    continue;
                }
                $c = KomponenNilai::create(['guru_mata_pelajaran_id' => $gmp->id, 'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'nama' => 'Praktik '.$mapel->nama, 'aktif' => true]);
                $nilai = $mapel->is($pjok) ? ($i ? [70, 60] : [75, 80]) : ($i ? [80, 70] : [88.5, 90]);
                foreach ($siswa as $j => $s) {
                    NilaiSiswa::create(['komponen_nilai_id' => $c->id, 'siswa_id' => $s->id, 'nilai' => $nilai[$j]]);
                }
                $service = app(StsManualService::class);
                $service->tetapkan($admin, $c, $service->konteks($c)['sidik'], true);
            }
        }

        return [...$d, 'kelas' => $kelas];
    }
}
