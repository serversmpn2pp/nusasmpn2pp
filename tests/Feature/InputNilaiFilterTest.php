<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiNilaiSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InputNilaiFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_awal_memilih_tahun_aktif_dan_semester_saat_ini_dalam_cakupan_guru(): void
    {
        $d = $this->data();
        $this->actingAs($d['guru'])->get(route('input-nilai.index'))->assertOk()
            ->assertViewHas('filter', ['tahun_pelajaran_id' => $d['tahun']->id, 'semester' => 'ganjil', 'kelas_id' => null, 'jenis_komponen' => 'semua'])
            ->assertViewHas('daftarKomponenNilai', fn ($items) => $items->pluck('id')->sort()->values()->all() === collect([$d['formatif']->id, $d['sts']->id, $d['formatifB']->id])->sort()->values()->all())
            ->assertViewHas('kelas', fn ($items) => $items->pluck('id')->all() === [$d['kelasA']->id, $d['kelasB']->id])
            ->assertDontSee('Komponen guru lain')->assertDontSee('Komponen nonaktif')->assertDontSee('Penugasan nonaktif');
    }

    public function test_kelas_dan_jenis_menyaring_komponen_tanpa_membuka_nilai_secara_otomatis(): void
    {
        $d = $this->data();
        $this->actingAs($d['guru'])->get(route('input-nilai.index', $this->filter($d)))->assertOk()
            ->assertViewHas('daftarKomponenNilai', fn ($items) => $items->pluck('id')->all() === [$d['formatif']->id])
            ->assertViewHas('komponenDipilih', null)->assertViewHas('anggotaKelas', fn ($items) => $items->isEmpty())
            ->assertSee('1 komponen tersedia')->assertDontSee('Nilai siswa B');
    }

    public function test_semua_tahun_semester_kelas_dan_jenis_tetap_dibatasi_penugasan_guru(): void
    {
        $d = $this->data();
        $response = $this->actingAs($d['guru'])->get(route('input-nilai.index', ['tahun_pelajaran_id' => '', 'semester' => 'semua', 'kelas_id' => '', 'jenis_komponen' => 'semua']));
        $response->assertOk()->assertViewHas('daftarKomponenNilai', fn ($items) => $items->count() === 5)
            ->assertViewHas('kelas', fn ($items) => $items->count() === 3)
            ->assertSee('Formatif tahun sebelumnya')->assertSee('Formatif genap')
            ->assertDontSee('Komponen guru lain')->assertDontSee('Penugasan nonaktif');
    }

    public function test_tautan_langsung_komponen_lama_mengisi_filter_dan_mempertahankan_siswa_yang_tepat(): void
    {
        $d = $this->data();
        $this->actingAs($d['guru'])->get(route('input-nilai.index', ['komponen_nilai_id' => $d['lama']->id]))
            ->assertOk()->assertViewHas('komponenDipilih', fn ($item) => $item->id === $d['lama']->id)
            ->assertViewHas('filter', ['tahun_pelajaran_id' => $d['tahunLama']->id, 'semester' => 'genap', 'kelas_id' => $d['kelasLama']->id, 'jenis_komponen' => 'formatif']);
    }

    public function test_komponen_yang_tidak_cocok_dengan_filter_dikosongkan(): void
    {
        $d = $this->data();
        $this->actingAs($d['guru'])->get(route('input-nilai.index', ['komponen_nilai_id' => $d['formatif']->id, ...$this->filter($d, ['kelas_id' => $d['kelasB']->id])]))
            ->assertOk()->assertViewHas('komponenDipilih', null)->assertViewHas('komponenNilaiId', null)
            ->assertViewHas('daftarKomponenNilai', fn ($items) => $items->pluck('id')->all() === [$d['formatifB']->id])
            ->assertViewHas('anggotaKelas', fn ($items) => $items->isEmpty());
    }

    public function test_komponen_yang_masih_cocok_tetap_terpilih_saat_filter_diperluas(): void
    {
        $d = $this->data();
        $this->actingAs($d['guru'])->get(route('input-nilai.index', ['komponen_nilai_id' => $d['formatif']->id, ...$this->filter($d, ['kelas_id' => '', 'jenis_komponen' => 'semua'])]))
            ->assertOk()->assertViewHas('komponenDipilih', fn ($item) => $item->id === $d['formatif']->id)
            ->assertViewHas('anggotaKelas', fn ($items) => $items->pluck('siswa_id')->all() === [$d['siswa']->id]);
    }

    public function test_filter_dan_id_komponen_tidak_menembus_penugasan_guru(): void
    {
        $d = $this->data();
        $this->actingAs($d['guru'])->get(route('input-nilai.index', $this->filter($d, ['kelas_id' => $d['kelasLain']->id])))->assertNotFound();
        $this->get(route('input-nilai.index', ['komponen_nilai_id' => $d['asing']->id]))->assertNotFound();
        $this->post(route('input-nilai.store'), ['komponen_nilai_id' => $d['asing']->id, 'nilai' => [$d['siswa']->id => '100']])->assertNotFound();
        $this->assertDatabaseCount('nilai_siswa', 1);
    }

    public function test_administrator_dapat_memfilter_penugasan_semua_guru(): void
    {
        $d = $this->data();
        $admin = Pengguna::create(['nama' => 'Admin Nilai', 'username' => 'admin-filter-nilai', 'kata_sandi' => 'rahasia', 'peran' => 'administrator', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $this->actingAs($admin)->get(route('input-nilai.index', $this->filter($d, ['kelas_id' => $d['kelasLain']->id])))
            ->assertOk()->assertViewHas('daftarKomponenNilai', fn ($items) => $items->pluck('id')->all() === [$d['asing']->id]);
    }

    public function test_simpan_nilai_desimal_menjaga_seluruh_filter_dan_tidak_mengubah_kelas_lain(): void
    {
        $d = $this->data();
        $filter = $this->filter($d);
        $this->actingAs($d['guru'])->post(route('input-nilai.store'), ['komponen_nilai_id' => $d['formatif']->id, 'filter' => $filter, 'nilai' => [$d['siswa']->id => '87,50'], 'catatan' => [$d['siswa']->id => 'Baik']])
            ->assertSessionHasNoErrors()->assertRedirect(route('input-nilai.index', ['komponen_nilai_id' => $d['formatif']->id, ...$filter]));
        $this->assertDatabaseHas('nilai_siswa', ['komponen_nilai_id' => $d['formatif']->id, 'siswa_id' => $d['siswa']->id, 'nilai' => 87.5, 'catatan' => 'Baik']);
        $this->assertDatabaseMissing('nilai_siswa', ['siswa_id' => $d['siswaB']->id]);
    }

    public function test_pilihan_semua_tetap_semua_setelah_menyimpan(): void
    {
        $d = $this->data();
        $filter = ['tahun_pelajaran_id' => '', 'semester' => 'semua', 'kelas_id' => '', 'jenis_komponen' => 'semua'];
        $response = $this->actingAs($d['guru'])->post(route('input-nilai.store'), ['komponen_nilai_id' => $d['formatif']->id, 'filter' => $filter, 'nilai' => [$d['siswa']->id => '88.25']]);
        $response->assertRedirect(route('input-nilai.index', ['komponen_nilai_id' => $d['formatif']->id, ...$filter]));
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertViewHas('filter', ['tahun_pelajaran_id' => null, 'semester' => 'semua', 'kelas_id' => null, 'jenis_komponen' => 'semua'])
            ->assertViewHas('daftarKomponenNilai', fn ($items) => $items->count() === 5);
    }

    public function test_filter_dan_isian_tetap_ada_setelah_validasi_nilai_gagal(): void
    {
        $d = $this->data();
        $filter = $this->filter($d, ['kelas_id' => '', 'jenis_komponen' => 'semua']);
        $this->actingAs($d['guru'])->from(route('input-nilai.index', ['komponen_nilai_id' => $d['formatif']->id]))
            ->post(route('input-nilai.store'), ['komponen_nilai_id' => $d['formatif']->id, 'filter' => $filter, 'nilai' => [$d['siswa']->id => '101'], 'catatan' => [$d['siswa']->id => 'Belum disimpan']])
            ->assertSessionHasErrors('nilai.'.$d['siswa']->id);
        $response = $this->get(route('input-nilai.index', ['komponen_nilai_id' => $d['formatif']->id]));
        $response->assertOk()->assertViewHas('filter', ['tahun_pelajaran_id' => $d['tahun']->id, 'semester' => 'ganjil', 'kelas_id' => null, 'jenis_komponen' => 'semua'])
            ->assertSee('value="101"', false)->assertSee('value="Belum disimpan"', false)->assertSee('data-saved-value="80,00"', false);
        $this->assertDatabaseHas('nilai_siswa', ['komponen_nilai_id' => $d['formatif']->id, 'siswa_id' => $d['siswa']->id, 'nilai' => 80]);
    }

    public function test_publikasi_dan_jadikan_draf_mempertahankan_filter(): void
    {
        $d = $this->data();
        $filter = $this->filter($d, ['kelas_id' => '', 'jenis_komponen' => 'semua']);
        $body = ['komponen_nilai_id' => $d['formatif']->id, 'filter' => $filter];
        foreach (['publikasikan', 'jadikan-draf'] as $action) {
            $this->actingAs($d['guru'])->patch(route('publikasi-nilai.'.$action, [$d['mapelA'], 'ganjil']), $body)
                ->assertSessionHasNoErrors()->assertRedirect(route('input-nilai.index', ['komponen_nilai_id' => $d['formatif']->id, ...$filter]));
        }
        $this->assertDatabaseHas('publikasi_nilai_siswa', ['guru_mata_pelajaran_id' => $d['mapelA']->id, 'dipublikasikan' => false]);
    }

    public function test_filter_tidak_valid_ditolak_sebelum_simpan_atau_publikasi(): void
    {
        $d = $this->data();
        foreach (['semester' => 'semester-3', 'jenis_komponen' => 'uraian', 'kelas_id' => ['bukan-id'], 'tahun_pelajaran_id' => 999999] as $key => $value) {
            $this->actingAs($d['guru'])->getJson(route('input-nilai.index', [$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $body = ['komponen_nilai_id' => $d['formatif']->id, 'filter' => ['jenis_komponen' => 'uraian'], 'nilai' => [$d['siswa']->id => '95']];
        $this->postJson(route('input-nilai.store'), $body)->assertUnprocessable()->assertJsonValidationErrors('filter.jenis_komponen');
        $this->patchJson(route('publikasi-nilai.publikasikan', [$d['mapelA'], 'ganjil']), $body)->assertUnprocessable();
        $this->assertDatabaseHas('nilai_siswa', ['komponen_nilai_id' => $d['formatif']->id, 'nilai' => 80]);
        $this->assertDatabaseCount('publikasi_nilai_siswa', 0);
    }

    public function test_tahun_default_mengikuti_tahun_penugasan_jika_tidak_ada_penugasan_tahun_aktif(): void
    {
        $d = $this->data();
        GuruMataPelajaran::where('pegawai_id', $d['guru']->pegawai_id)->where('tahun_pelajaran_id', $d['tahun']->id)->update(['aktif' => false]);
        $this->actingAs($d['guru'])->get(route('input-nilai.index'))->assertOk()
            ->assertViewHas('filter', fn ($filter) => $filter['tahun_pelajaran_id'] === $d['tahunLama']->id)
            ->assertViewHas('tahunPelajaran', fn ($items) => $items->pluck('id')->all() === [$d['tahunLama']->id]);
    }

    public function test_halaman_kosong_tetap_memiliki_filter_dan_status_yang_jelas(): void
    {
        $d = $this->data();
        $this->actingAs($d['guru'])->get(route('input-nilai.index', $this->filter($d, ['jenis_komponen' => 'sas_saj'])))
            ->assertOk()->assertSee('Tidak ada komponen nilai yang sesuai')->assertSee('0 komponen tersedia')
            ->assertViewHas('daftarKomponenNilai', fn ($items) => $items->isEmpty());
    }

    public function test_render_fixture_tampilan_dan_status_publikasi(): void
    {
        $d = $this->data();
        $pages = [
            'index' => [],
            'filtered' => $this->filter($d),
            'empty' => $this->filter($d, ['jenis_komponen' => 'sas_saj']),
            'selected' => ['komponen_nilai_id' => $d['formatif']->id, ...$this->filter($d)],
        ];
        foreach ($pages as $name => $query) {
            $response = $this->actingAs($d['guru'])->get(route('input-nilai.index', $query))->assertOk();
            $this->capture($name, $response->getContent());
        }
        PublikasiNilaiSiswa::create(['guru_mata_pelajaran_id' => $d['mapelA']->id, 'semester' => 'ganjil', 'dipublikasikan' => true, 'dipublikasikan_pada' => now()]);
        $response = $this->get(route('input-nilai.index', $pages['selected']))->assertOk()->assertSee('Jadikan draf');
        $this->capture('published', $response->getContent());
        $response = $this->withSession(['_old_input' => ['komponen_nilai_id' => $d['formatif']->id, 'filter' => $this->filter($d), 'nilai' => [$d['siswa']->id => '88,75']]])
            ->get(route('input-nilai.index', $pages['selected']))->assertOk();
        $this->capture('unsaved', $response->getContent());
    }

    public function test_semester_awal_genap_dan_guru_tanpa_penugasan_tetap_dapat_membuka_halaman(): void
    {
        $d = $this->data();
        $this->travelTo(now()->setDate(2027, 1, 15));
        $this->actingAs($d['guru'])->get(route('input-nilai.index'))->assertOk()
            ->assertViewHas('filter', fn ($filter) => $filter['semester'] === 'genap')
            ->assertViewHas('daftarKomponenNilai', fn ($items) => $items->pluck('id')->all() === [$d['genap']->id]);
        GuruMataPelajaran::where('pegawai_id', $d['guru']->pegawai_id)->update(['aktif' => false]);
        $this->get(route('input-nilai.index'))->assertOk()->assertViewHas('kelas', fn ($items) => $items->isEmpty())
            ->assertViewHas('tahunPelajaran', fn ($items) => $items->isEmpty());
    }

    private function capture(string $name, string $html): void
    {
        if (! getenv('NUSA_CAPTURE_NILAI_UI')) {
            return;
        }
        $dir = storage_path('framework/testing/input-nilai');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/'.$name.'.html', $html);
    }

    private function filter(array $d, array $changes = []): array
    {
        return array_replace(['tahun_pelajaran_id' => $d['tahun']->id, 'semester' => 'ganjil', 'kelas_id' => $d['kelasA']->id, 'jenis_komponen' => 'formatif'], $changes);
    }

    private function data(): array
    {
        $this->travelTo(now()->setDate(2026, 10, 4));
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $tahunLama = TahunPelajaran::create(['nama' => '2025/2026', 'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'aktif' => false]);
        $kelasA = $this->kelas($tahun, 'VIII.A Bilingual');
        $kelasB = $this->kelas($tahun, 'VIII.D');
        $kelasLain = $this->kelas($tahun, 'IX.C');
        $kelasLama = $this->kelas($tahunLama, 'VII.A');
        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru Informatika', 'aktif' => true]);
        $pegawaiLain = Pegawai::create(['nama_lengkap' => 'Guru Lain', 'aktif' => true]);
        $guru = Pengguna::create(['pegawai_id' => $pegawai->id, 'nama' => 'Guru Informatika', 'username' => 'guru-filter-nilai', 'kata_sandi' => 'rahasia', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $guru->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());
        $mapel = MataPelajaran::create(['nama' => 'Informatika', 'aktif' => true]);
        $mapelA = $this->penugasan($tahun, $kelasA, $pegawai, $mapel);
        $mapelB = $this->penugasan($tahun, $kelasB, $pegawai, $mapel);
        $mapelLama = $this->penugasan($tahunLama, $kelasLama, $pegawai, $mapel);
        $mapelLain = $this->penugasan($tahun, $kelasLain, $pegawaiLain, $mapel);
        $nonaktif = $this->penugasan($tahun, $kelasLain, $pegawai, $mapel);
        $nonaktif->update(['aktif' => false]);
        $formatif = $this->komponen($mapelA, 'formatif', 'Bilangan Biner dan Penerapannya dalam Sistem Komputer');
        $sts = $this->komponen($mapelA, 'sts', 'Sumatif Tengah Semester');
        $genap = $this->komponen($mapelA, 'formatif', 'Formatif genap', 'genap');
        $formatifB = $this->komponen($mapelB, 'formatif', 'LKPD 1 - Literasi Digital');
        $lama = $this->komponen($mapelLama, 'formatif', 'Formatif tahun sebelumnya', 'genap');
        $asing = $this->komponen($mapelLain, 'formatif', 'Komponen guru lain');
        $this->komponen($mapelA, 'formatif', 'Komponen nonaktif')->update(['aktif' => false]);
        $this->komponen($nonaktif, 'formatif', 'Penugasan nonaktif');
        $siswa = $this->siswa($tahun, $kelasA, 'Aditya Fathur Rahman', '26001');
        $siswaB = $this->siswa($tahun, $kelasB, 'Nilai siswa B', '26002');
        NilaiSiswa::create(['komponen_nilai_id' => $formatif->id, 'siswa_id' => $siswa->id, 'nilai' => 80]);

        return compact('tahun', 'tahunLama', 'kelasA', 'kelasB', 'kelasLama', 'kelasLain', 'guru', 'mapelA', 'formatif', 'sts', 'genap', 'formatifB', 'lama', 'asing', 'siswa', 'siswaB');
    }

    private function kelas(TahunPelajaran $tahun, string $nama): Kelas
    {
        return Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => $nama, 'tingkat' => 8, 'aktif' => true]);
    }

    private function penugasan(TahunPelajaran $tahun, Kelas $kelas, Pegawai $pegawai, MataPelajaran $mapel): GuruMataPelajaran
    {
        return GuruMataPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'pegawai_id' => $pegawai->id, 'mata_pelajaran_id' => $mapel->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
    }

    private function komponen(GuruMataPelajaran $penugasan, string $jenis, string $nama, string $semester = 'ganjil'): KomponenNilai
    {
        return KomponenNilai::create(['guru_mata_pelajaran_id' => $penugasan->id, 'semester' => $semester, 'jenis_komponen' => $jenis, 'nama' => $nama, 'aktif' => true, 'urutan' => 1]);
    }

    private function siswa(TahunPelajaran $tahun, Kelas $kelas, string $nama, string $nis): Siswa
    {
        $siswa = Siswa::create(['nama_lengkap' => $nama, 'nis' => $nis, 'aktif' => true]);
        AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'nomor_absen' => 1, 'status_keanggotaan' => 'aktif']);

        return $siswa;
    }
}
