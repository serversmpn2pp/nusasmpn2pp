<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiNilaiSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use App\Services\Nilai\KomponenNilaiService;
use App\Services\Nilai\LegerStsService;
use App\Services\Nilai\RaporStsService;
use App\Services\Nilai\StsManualService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StsManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_nama_praktik_bebas_nilai_lama_dibaca_setelah_final_tanpa_publikasi(): void
    {
        $d = $this->data();
        $awal = $this->rapor($d)['baris'][0]['nilai'][0];
        $this->assertNull($awal['nilai']);
        $this->assertSame('Nilai STS manual belum difinalisasi', $awal['status']);
        $this->finalisasi($d)->assertSessionHasNoErrors();
        $hasil = $this->rapor($d)['baris'][0];
        $this->assertSame(88.5, $hasil['nilai'][0]['nilai']);
        $this->assertSame('manual', $hasil['nilai'][0]['sumber_nilai']);
        $this->assertTrue($hasil['nilai_lengkap']);
        $this->assertSame(88.5, $hasil['rata']);
        $this->assertDatabaseCount('publikasi_nilai_siswa', 0);
        $this->assertDatabaseHas('komponen_nilai', ['id' => $d['komponen']->id, 'sts_manual_difinalisasi_oleh_pengguna_id' => $d['guru']->id]);
        $this->assertDatabaseCount('nilai_siswa', 2);
    }

    public function test_leger_kelas_tingkat_statistik_dan_penghargaan_memakai_nilai_manual(): void
    {
        $d = $this->data();
        $this->finalisasi($d)->assertSessionHasNoErrors();
        $service = app(LegerStsService::class);
        $kelas = $service->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(44.25, $kelas['ringkasan']['rata_kelas']);
        $this->assertSame(1, $kelas['baris'][0]['ranking']);
        $this->assertSame(2, $kelas['baris'][1]['ranking']);
        $this->assertSame(0.0, $kelas['baris'][1]['nilai'][0]['nilai']);
        $tingkat = $service->bangunTingkat($d['kegiatan'], collect([$d['kelas']]), 8);
        $this->assertSame(44.25, $tingkat['ringkasan']['rata_tingkat']);
        $this->assertSame(44.25, $tingkat['statistik_mapel'][0]['rata']);
        $juara = $service->kandidatPenghargaan($tingkat, 'mapel', $d['mapel']->id, 10);
        $this->assertSame(88.5, $juara['kandidat'][0]['nilai']);
        $this->assertCount(2, $juara['kandidat']);
    }

    public function test_publikasi_semester_tidak_menggantikan_finalisasi_manual(): void
    {
        $d = $this->data();
        $this->actingAs($d['guru'])->patch(route('publikasi-nilai.publikasikan', [$d['penugasan'], 'ganjil']), ['komponen_nilai_id' => $d['komponen']->id])
            ->assertSessionHasNoErrors();
        $this->assertNull($this->rapor($d)['baris'][0]['nilai'][0]['nilai']);
        $this->finalisasi($d)->assertSessionHasNoErrors();
        $this->finalisasi($d, false)->assertSessionHasNoErrors();
        $this->assertNull($this->rapor($d)['baris'][0]['nilai'][0]['nilai']);
        $this->assertTrue(PublikasiNilaiSiswa::firstOrFail()->dipublikasikan);
    }

    public function test_simpan_tanpa_perubahan_mempertahankan_final_tetapi_koreksi_membatalkannya(): void
    {
        $d = $this->data();
        $this->finalisasi($d)->assertSessionHasNoErrors();
        $body = ['komponen_nilai_id' => $d['komponen']->id, 'nilai' => [$d['siswa'][0]->id => '88,50', $d['siswa'][1]->id => '0,00']];
        $this->post(route('input-nilai.store'), $body)->assertSessionHasNoErrors();
        $this->assertTrue($this->konteks($d)['difinalisasi']);
        $body['nilai'][$d['siswa'][0]->id] = '89,25';
        $this->post(route('input-nilai.store'), $body)->assertSessionHasNoErrors()
            ->assertSessionHas('berhasil', fn ($pesan) => str_contains($pesan, 'difinalisasi ulang'));
        $this->assertNull($d['komponen']->fresh()->sts_manual_difinalisasi_pada);
        $this->assertNull($this->rapor($d)['baris'][0]['nilai'][0]['nilai']);
        $this->finalisasi($d)->assertSessionHasNoErrors();
        $this->assertSame(89.25, $this->rapor($d)['baris'][0]['nilai'][0]['nilai']);
    }

    public function test_finalisasi_dengan_sidik_lama_ditolak_dan_pengulangan_tetap_idempoten(): void
    {
        $d = $this->data();
        $sidik = $this->konteks($d)['sidik'];
        $d['komponen']->nilaiSiswa()->where('siswa_id', $d['siswa'][0]->id)->update(['nilai' => 91.25]);
        $this->actingAs($d['guru'])->patchJson(route('input-nilai.sts-manual', $d['komponen']), ['sidik' => $sidik, 'difinalisasi' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('sidik');
        $this->assertNull($d['komponen']->fresh()->sts_manual_difinalisasi_pada);
        $this->finalisasi($d)->assertSessionHasNoErrors();
        $waktu = $d['komponen']->fresh()->sts_manual_difinalisasi_pada->toISOString();
        $this->travel(1)->minutes();
        $this->finalisasi($d)->assertSessionHasNoErrors();
        $this->assertSame($waktu, $d['komponen']->fresh()->sts_manual_difinalisasi_pada->toISOString());
    }

    public function test_perubahan_di_luar_input_atau_daftar_siswa_tidak_memakai_final_lama(): void
    {
        $d = $this->data();
        $this->finalisasi($d)->assertSessionHasNoErrors();
        $d['komponen']->nilaiSiswa()->where('siswa_id', $d['siswa'][0]->id)->update(['catatan' => 'Koreksi']);
        $this->assertTrue($this->konteks($d)['berubah']);
        $this->assertSame('Nilai STS manual berubah; finalisasi ulang', $this->rapor($d)['baris'][0]['nilai'][0]['status']);
        $this->finalisasi($d)->assertSessionHasNoErrors();
        AnggotaKelas::where('siswa_id', $d['siswa'][1]->id)->update(['status_keanggotaan' => 'nonaktif']);
        $this->assertFalse($this->konteks($d)['difinalisasi']);
        $this->assertNull($this->rapor($d)['baris'][0]['nilai'][0]['nilai']);
    }

    public function test_nilai_kosong_tidak_dianggap_nol_dan_siswa_lain_tetap_bisa_final(): void
    {
        $d = $this->data();
        $d['komponen']->nilaiSiswa()->where('siswa_id', $d['siswa'][1]->id)->delete();
        $this->finalisasi($d)->assertSessionHasNoErrors();
        $hasil = $this->rapor($d);
        $this->assertSame(88.5, $hasil['baris'][0]['nilai'][0]['nilai']);
        $this->assertNull($hasil['baris'][1]['nilai'][0]['nilai']);
        $this->assertFalse($hasil['baris'][1]['nilai_lengkap']);
        $this->assertNull($hasil['baris'][1]['rata']);
        $leger = app(LegerStsService::class)->bangun($d['kegiatan'], $d['kelas']);
        $this->assertSame(88.5, $leger['ringkasan']['rata_kelas']);
        $this->assertNull($leger['baris'][1]['ranking']);
        $d['komponen']->nilaiSiswa()->delete();
        $this->finalisasi($d)->assertSessionHasErrors('sts_manual');
    }

    public function test_kelas_semester_tahun_dan_penugasan_nonaktif_tidak_menjadi_sumber(): void
    {
        $d = $this->data();
        $d['komponen']->update(['semester' => 'genap']);
        $this->assertNull($this->rapor($d)['baris'][0]['nilai'][0]['nilai']);
        $d['komponen']->update(['semester' => 'ganjil']);
        $this->finalisasi($d)->assertSessionHasNoErrors();
        $d['penugasan']->update(['aktif' => false]);
        $this->assertCount(0, $this->rapor($d)['mapel']);
        $d['penugasan']->update(['aktif' => true, 'tahun_pelajaran_id' => TahunPelajaran::create(['nama' => '2025/2026', 'aktif' => false])->id]);
        $this->assertNull($this->rapor($d)['baris'][0]['nilai'][0]['nilai']);
        $this->finalisasi($d)->assertSessionHasErrors('sts_manual');
    }

    public function test_duplikat_sts_aktif_tidak_dipilih_secara_sembarang(): void
    {
        $d = $this->data();
        KomponenNilai::create(['guru_mata_pelajaran_id' => $d['penugasan']->id, 'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'nama' => 'Duplikat lama', 'aktif' => true]);
        $this->finalisasi($d)->assertSessionHasErrors('sts_manual');
        $this->assertSame('Komponen STS manual perlu diperiksa', $this->rapor($d)['baris'][0]['nilai'][0]['status']);
        $this->expectException(ValidationException::class);
        app(KomponenNilaiService::class)->tambah($d['guru'], ['guru_mata_pelajaran_id' => $d['penugasan']->id, 'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'nama' => 'STS ketiga', 'aktif' => true]);
    }

    public function test_jadwal_cbt_tidak_bisa_dilewati_dengan_nilai_manual_meski_paket_belum_ada(): void
    {
        $d = $this->data();
        $this->finalisasi($d)->assertSessionHasNoErrors();
        $jadwal = $this->jadwal($d);
        $this->assertFalse($this->konteks($d)['difinalisasi']);
        $this->finalisasi($d)->assertSessionHasErrors('sts_manual');
        $this->assertSame('Belum ada paket STS', $this->rapor($d)['baris'][0]['nilai'][0]['status']);
        $ujian = UjianCbt::create(['jenis_ujian_cbt_id' => $d['kegiatan']->jenis_ujian_cbt_id, 'tahun_pelajaran_id' => $d['tahun']->id, 'mata_pelajaran_id' => $d['mapel']->id, 'kode' => 'MANUAL-CBT', 'nama' => 'Paket CBT', 'semester' => 'ganjil', 'tingkat' => 8, 'jumlah_soal' => 1, 'alur' => 'terpusat', 'status' => 'selesai']);
        $jadwal->update(['ujian_cbt_id' => $ujian->id]);
        $this->assertSame('Belum difinalisasi guru mapel', $this->rapor($d)['baris'][0]['nilai'][0]['status']);
        $ujian->update(['hasil_difinalisasi_pada' => now()]);
        $this->assertSame('Belum mengikuti / menyelesaikan STS', $this->rapor($d)['baris'][0]['nilai'][0]['status']);
        $jadwal->update(['status' => 'dibatalkan']);
        $this->assertSame(88.5, $this->rapor($d)['baris'][0]['nilai'][0]['nilai']);
        KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $d['kelas']->id, 'komponen_nilai_id' => $d['komponen']->id]);
        $this->assertNull($this->rapor($d)['baris'][0]['nilai'][0]['nilai']);
        $this->finalisasi($d)->assertSessionHasErrors('sts_manual');
    }

    public function test_guru_lain_tidak_dapat_finalisasi_dan_filter_tetap_tersimpan(): void
    {
        $d = $this->data();
        $filter = ['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelas']->id, 'semester' => 'ganjil', 'jenis_komponen' => 'sts'];
        $this->actingAs($d['guru'])->patch(route('input-nilai.sts-manual', $d['komponen']), ['sidik' => $this->konteks($d)['sidik'], 'difinalisasi' => true, 'filter' => $filter])
            ->assertRedirect(route('input-nilai.index', ['komponen_nilai_id' => $d['komponen']->id, ...$filter]));
        $asing = $this->guru(Pegawai::create(['nama_lengkap' => 'Guru lain', 'aktif' => true]), 'guru-lain-sts');
        $this->actingAs($asing)->patchJson(route('input-nilai.sts-manual', $d['komponen']), ['sidik' => $this->konteks($d)['sidik'], 'difinalisasi' => false])->assertNotFound();
        $this->assertTrue($this->konteks($d)['difinalisasi']);
    }

    public function test_perubahan_metadata_membatalkan_final_manual(): void
    {
        $d = $this->data();
        $this->finalisasi($d)->assertSessionHasNoErrors();
        app(KomponenNilaiService::class)->ubah($d['guru'], $d['komponen']->fresh(), ['guru_mata_pelajaran_id' => $d['penugasan']->id, 'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'nama' => 'Praktik setelah koreksi', 'aktif' => true]);
        $this->assertNull($d['komponen']->fresh()->sts_manual_difinalisasi_pada);
        $this->finalisasi($d)->assertSessionHasNoErrors();
        app(KomponenNilaiService::class)->nonaktifkan($d['guru'], $d['komponen']->fresh());
        $this->assertNull($d['komponen']->fresh()->sts_manual_difinalisasi_pada);
    }

    public function test_api_memuat_status_final_dan_membatalkannya_saat_nilai_diubah(): void
    {
        $d = $this->data();
        $token = $d['guru']->createToken('Uji manual', ['mobile'])->plainTextToken;
        $this->withToken($token)->getJson(route('api.v1.input-nilai.index', ['komponen_nilai_id' => $d['komponen']->id]))
            ->assertOk()->assertJsonPath('data.sts_manual.dapat_finalisasi', true)->assertJsonPath('data.sts_manual.difinalisasi', false)->assertJsonMissingPath('data.sts_manual.nilai');
        $url = route('api.v1.input-nilai.sts-manual', $d['komponen']);
        $body = ['sidik' => $this->konteks($d)['sidik'], 'difinalisasi' => true];
        $this->patchJson($url, $body)->assertOk()->assertJsonPath('data.difinalisasi', true)->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')->assertJsonMissingPath('data.nilai');
        $this->postJson(route('api.v1.input-nilai.store'), ['komponen_nilai_id' => $d['komponen']->id, 'nilai' => [$d['siswa'][0]->id => '89,75', $d['siswa'][1]->id => 0]])
            ->assertOk()->assertJsonPath('data.finalisasi_sts_dibatalkan', true);
        $this->patchJson($url, $body)->assertUnprocessable()->assertJsonValidationErrors('sidik');
        $this->assertNull($this->rapor($d)['baris'][0]['nilai'][0]['nilai']);
    }

    public function test_api_menolak_tanpa_token_tanpa_izin_dan_guru_asing(): void
    {
        $d = $this->data();
        $url = route('api.v1.input-nilai.sts-manual', $d['komponen']);
        $body = ['sidik' => $this->konteks($d)['sidik'], 'difinalisasi' => true];
        $this->patchJson($url, $body)->assertUnauthorized();
        $tanpaIzin = Pengguna::create(['nama' => 'Tanpa izin', 'username' => 'sts-tanpa-izin', 'kata_sandi' => 'test-pass', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $this->withToken($tanpaIzin->createToken('Uji', ['mobile'])->plainTextToken)->patchJson($url, $body)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $asing = $this->guru(Pegawai::create(['nama_lengkap' => 'Guru lain', 'aktif' => true]), 'guru-api-lain-sts');
        $this->withToken($asing->createToken('Uji', ['mobile'])->plainTextToken)->patchJson($url, $body)->assertNotFound();
        $this->assertNull($d['komponen']->fresh()->sts_manual_difinalisasi_pada);
    }

    public function test_render_tampilan_draf_final_dan_cbt(): void
    {
        $d = $this->data();
        $query = ['komponen_nilai_id' => $d['komponen']->id];
        $response = $this->actingAs($d['guru'])->get(route('input-nilai.index', $query))->assertOk()->assertSee('Finalisasi STS manual');
        $this->capture('manual-draft', $response->getContent());
        $this->finalisasi($d)->assertSessionHasNoErrors();
        $response = $this->get(route('input-nilai.index', $query))->assertOk()->assertSee('Final manual')->assertSee('Jadikan draf STS');
        $this->capture('manual-final', $response->getContent());
        $this->jadwal($d);
        $response = $this->get(route('input-nilai.index', $query))->assertOk()->assertSee('Finalisasikan melalui hasil ujian CBT.');
        $this->capture('manual-cbt', $response->getContent());
    }

    private function capture(string $name, string $html): void
    {
        if (getenv('NUSA_CAPTURE_NILAI_UI')) {
            $dir = storage_path('framework/testing/input-nilai');
            File::ensureDirectoryExists($dir);
            File::put($dir.'/'.$name.'.html', $html);
        }
    }

    private function konteks(array $d): array
    {
        return app(StsManualService::class)->konteks($d['komponen']->fresh());
    }

    private function finalisasi(array $d, bool $final = true)
    {
        return $this->actingAs($d['guru'])->patch(route('input-nilai.sts-manual', $d['komponen']), ['sidik' => $this->konteks($d)['sidik'], 'difinalisasi' => $final]);
    }

    private function rapor(array $d): array
    {
        return app(RaporStsService::class)->bangun($d['kegiatan']->fresh(), $d['kelas']->fresh());
    }

    private function jadwal(array $d)
    {
        $jadwal = $d['kegiatan']->jadwalUjianCbt()->create(['mata_pelajaran_id' => $d['mapel']->id, 'tanggal' => '2026-09-15', 'waktu_mulai' => '07:00', 'waktu_selesai' => '08:00', 'tingkat' => 8, 'status' => 'selesai']);
        $jadwal->kelas()->attach($d['kelas']);

        return $jadwal;
    }

    private function guru(Pegawai $pegawai, string $username): Pengguna
    {
        $guru = Pengguna::create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap, 'username' => $username, 'kata_sandi' => 'test-pass', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $guru->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());

        return $guru;
    }

    private function data(): array
    {
        $this->travelTo(now()->setDate(2026, 10, 5));
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru PJOK, S.Pd.', 'aktif' => true]);
        $guru = $this->guru($pegawai, 'guru-sts-manual');
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.A Bilingual', 'tingkat' => 8, 'wali_kelas_id' => $pegawai->id, 'aktif' => true]);
        $mapel = MataPelajaran::create(['nama' => 'Pendidikan Jasmani, Olahraga, dan Kesehatan (PJOK)', 'aktif' => true, 'urutan' => 1]);
        $penugasan = GuruMataPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $pegawai->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
        $komponen = KomponenNilai::create(['guru_mata_pelajaran_id' => $penugasan->id, 'semester' => 'ganjil', 'jenis_komponen' => 'sts', 'nama' => 'Ujian Praktik PJOK', 'aktif' => true]);
        $kegiatan = KegiatanUjianCbt::create(['jenis_ujian_cbt_id' => JenisUjianCbt::where('kode', 'STS')->firstOrFail()->id, 'tahun_pelajaran_id' => $tahun->id, 'kode' => 'STS-MANUAL', 'nama' => 'Sumatif Tengah Semester Semester Ganjil 2026/2027', 'semester' => 'ganjil', 'tanggal_mulai' => '2026-09-15', 'tanggal_selesai' => '2026-09-20', 'status' => 'selesai']);
        $siswa = collect();
        foreach ([88.5, 0] as $i => $nilai) {
            $s = Siswa::create(['nama_lengkap' => $i ? 'Bima Siswa' : 'Alya Siswa', 'nis' => 'M00'.$i, 'jenis_kelamin' => $i ? 'L' : 'P', 'aktif' => true]);
            AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $s->id, 'nomor_absen' => $i + 1, 'status_keanggotaan' => 'aktif']);
            NilaiSiswa::create(['komponen_nilai_id' => $komponen->id, 'siswa_id' => $s->id, 'nilai' => $nilai]);
            $siswa->push($s);
        }

        return compact('tahun', 'guru', 'kelas', 'mapel', 'penugasan', 'komponen', 'kegiatan', 'siswa');
    }
}
