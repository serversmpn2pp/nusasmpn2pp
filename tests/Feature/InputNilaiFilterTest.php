<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JenisUjianCbt;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiNilaiSiswa;
use App\Models\SkemaBobotNilai;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use App\Services\Nilai\RekapNilaiRaporService;
use App\Support\PenulisExcelNilaiRapor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use SimpleXMLElement;
use Tests\TestCase;
use ZipArchive;

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

    public function test_keterangan_horizontal_dan_tabel_penuh_tetap_memuat_nilai_dan_publikasi(): void
    {
        $d = $this->data();
        $d['formatif']->update(['nama' => 'Sumatif Tengah Semester Semester Ganjil 2026/2027']);
        $d['mapelA']->pegawai->update(['nama_lengkap' => 'Antonius Pitra Dana Arista, M.T.']);
        for ($i = 2; $i <= 32; $i++) {
            $siswa = $this->siswa($d['tahun'], $d['kelasA'], 'Siswa dengan Nama Lengkap yang Panjang Nomor '.$i, '270'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), $i);
            $siswa->update(['nisn' => '013'.str_pad((string) $i, 7, '0', STR_PAD_LEFT)]);
            NilaiSiswa::create(['komponen_nilai_id' => $d['formatif']->id, 'siswa_id' => $siswa->id, 'nilai' => 49.76, 'catatan' => 'Nilai hasil ujian STS yang sudah difinalisasi.']);
        }
        $response = $this->actingAs($d['guru'])->get(route('input-nilai.index', ['komponen_nilai_id' => $d['formatif']->id]))
            ->assertOk()->assertViewHas('jumlahSiswa', 32)->assertViewHas('jumlahTerisi', 32)
            ->assertSee('class="grade-workspace"', false)->assertSee('class="grade-overview"', false)
            ->assertSee('class="panel grade-entry"', false)->assertSee('Daftar nilai siswa')
            ->assertDontSee('class="detail-shell"', false)->assertDontSee('min-width: 1000px', false)
            ->assertSee('value="49,76"', false)->assertViewHas('targetNilaiPublikasi', 64)->assertSee('32 dari 64 entri terisi');
        $this->capture('wide', $response->getContent());
        PublikasiNilaiSiswa::create(['guru_mata_pelajaran_id' => $d['mapelA']->id, 'semester' => 'ganjil', 'dipublikasikan' => true, 'dipublikasikan_pada' => now()]);
        $response = $this->get(route('input-nilai.index', ['komponen_nilai_id' => $d['formatif']->id]))->assertOk()->assertSee('Jadikan draf');
        $this->capture('wide-published', $response->getContent());
        $d['mapelA']->mataPelajaran->update(['nama' => 'Pramuka', 'kelompok' => 'Ekstrakurikuler']);
        $response = $this->get(route('input-nilai.index', ['komponen_nilai_id' => $d['formatif']->id]))->assertOk()
            ->assertSee('Predikat (SB/B/C/K)')->assertSee('grade-table--predikat');
        $this->capture('predikat', $response->getContent());
        AnggotaKelas::where('kelas_id', $d['kelasA']->id)->delete();
        $response = $this->get(route('input-nilai.index', ['komponen_nilai_id' => $d['formatif']->id]))->assertOk()->assertSee('Belum ada siswa aktif di kelas ini.');
        $this->capture('no-students', $response->getContent());
    }

    public function test_input_kolektif_hanya_memuat_kolom_terpilih_pada_satu_penugasan_dan_semester(): void
    {
        $d = $this->data();
        $sumatif = $this->komponen($d['mapelA'], 'sumatif', 'Sumatif 1');
        $d['sts']->forceFill(['sts_manual_difinalisasi_pada' => now()])->save();
        $this->siswa($d['tahun'], $d['kelasA'], 'Siswa Kedua', '26003', 2);
        $parameter = [
            'tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelasA']->id,
            'guru_mata_pelajaran_id' => $d['mapelA']->id, 'semester' => 'ganjil',
        ];
        $this->actingAs($d['guru'])->get(route('input-nilai.index', ['komponen_nilai_id' => $d['formatif']->id]))
            ->assertOk()->assertSee('Beberapa komponen');
        $this->get(route('input-nilai.kolektif', $parameter))->assertOk()
            ->assertSee($sumatif->nama)->assertSee('STS sudah final')->assertDontSee('data-bulk-cell', false);
        $respons = $this->get(route('input-nilai.kolektif', [...$parameter, 'komponen' => [
            $d['formatif']->id, $sumatif->id, $d['sts']->id, $d['genap']->id, $d['asing']->id,
        ]]))->assertOk()
            ->assertSee('Simpan semua sebagai draf')->assertSee('data-nilai-awal="80.00"', false)
            ->assertSee($d['siswa']->nama_lengkap)->assertSee('Siswa Kedua')
            ->assertDontSee($d['genap']->nama)->assertDontSee($d['asing']->nama)->assertDontSee($d['siswaB']->nama_lengkap)
            ->assertViewHas('komponenDipilih', fn ($items) => $items->modelKeys() === [$d['formatif']->id, $sumatif->id]);
        $this->capture('kolektif', $respons->getContent());
    }

    public function test_simpan_kolektif_menjaga_catatan_nilai_lama_dan_membedakan_nol_dari_kosong(): void
    {
        $d = $this->data();
        $sumatif = $this->komponen($d['mapelA'], 'sumatif', 'Sumatif 1');
        $siswaKedua = $this->siswa($d['tahun'], $d['kelasA'], 'Siswa Kedua', '26003', 2);
        $lama = $d['formatif']->nilaiSiswa()->firstOrFail();
        $lama->update(['catatan' => 'Catatan dipertahankan']);
        $tidakDisentuh = $d['formatif']->nilaiSiswa()->create(['siswa_id' => $siswaKedua->id, 'nilai' => 94]);
        $publikasi = $d['mapelA']->publikasiNilaiSiswa()->create(['semester' => 'ganjil', 'dipublikasikan' => true, 'dipublikasikan_pada' => now()]);

        $this->actingAs($d['guru']);
        $this->simpanKolektif($d, [$this->sel($d['formatif'], $d['siswa'], '80', '80.00')])
            ->assertOk()->assertJsonPath('jumlah', 0)->assertJsonPath('kembali_draf', false);
        $this->assertTrue($publikasi->fresh()->dipublikasikan);

        $this->simpanKolektif($d, [
            $this->sel($d['formatif'], $d['siswa'], '0', '80.00'),
            $this->sel($sumatif, $d['siswa'], '87,50'),
        ])->assertOk()->assertJsonPath('jumlah', 2)->assertJsonPath('kembali_draf', true);
        $this->assertSame('0.00', $lama->fresh()->nilai);
        $this->assertSame('Catatan dipertahankan', $lama->fresh()->catatan);
        $this->assertSame('87.50', $sumatif->nilaiSiswa()->firstOrFail()->nilai);
        $this->assertSame('94.00', $tidakDisentuh->fresh()->nilai);
        $this->assertFalse($publikasi->fresh()->dipublikasikan);
        $this->assertDatabaseMissing('nilai_siswa', ['komponen_nilai_id' => $sumatif->id, 'siswa_id' => $siswaKedua->id]);

        $this->simpanKolektif($d, [
            $this->sel($d['formatif'], $d['siswa'], null, '0.00'),
            $this->sel($sumatif, $d['siswa'], null, '87.50'),
        ])->assertOk()->assertJsonPath('jumlah', 2);
        $this->assertNull($lama->fresh()->nilai);
        $this->assertSame('Catatan dipertahankan', $lama->fresh()->catatan);
        $this->assertFalse($sumatif->nilaiSiswa()->exists());
    }

    public function test_kolektif_menolak_batch_tidak_valid_duplikat_atau_yang_sudah_berubah(): void
    {
        $d = $this->data();
        $sumatif = $this->komponen($d['mapelA'], 'sumatif', 'Sumatif 1');
        $sumatif->nilaiSiswa()->create(['siswa_id' => $d['siswa']->id, 'nilai' => 91]);
        $valid = $this->sel($d['formatif'], $d['siswa'], '85', '80.00');
        $this->actingAs($d['guru']);
        $this->simpanKolektif($d, [$valid, $this->sel($sumatif, $d['siswa'], '101', '91.00')])
            ->assertUnprocessable()->assertJsonValidationErrors('sel.1');
        $this->simpanKolektif($d, [$valid, $valid])
            ->assertUnprocessable()->assertJsonValidationErrors('sel.1');
        $this->simpanKolektif($d, [$valid, $this->sel($sumatif, $d['siswa'], '93', '88.00')])
            ->assertUnprocessable()->assertJsonValidationErrors('sel.1')->assertJsonPath('message', 'Nilai ini sudah berubah. Muat ulang untuk memeriksa sebelum menyimpan.');
        $this->assertSame('80.00', $d['formatif']->nilaiSiswa()->firstOrFail()->nilai);
        $this->assertSame('91.00', $sumatif->nilaiSiswa()->firstOrFail()->nilai);
    }

    public function test_kolektif_menolak_penugasan_lain_siswa_lain_sts_final_dan_nilai_cbt(): void
    {
        $d = $this->data();
        $this->actingAs($d['guru']);
        $this->simpanKolektif($d, [$this->sel($d['asing'], $d['siswa'], '100')], $d['asing']->guru_mata_pelajaran_id)->assertNotFound();
        foreach (['formatifB', 'genap', 'asing'] as $key) {
            $this->simpanKolektif($d, [$this->sel($d[$key], $d['siswa'], '100')])
                ->assertUnprocessable()->assertJsonValidationErrors('perubahan');
        }
        $this->simpanKolektif($d, [$this->sel($d['formatif'], $d['siswaB'], '100')])
            ->assertUnprocessable()->assertJsonValidationErrors('perubahan');
        $d['sts']->forceFill(['sts_manual_difinalisasi_pada' => now()])->save();
        $this->simpanKolektif($d, [$this->sel($d['sts'], $d['siswa'], '100')])
            ->assertUnprocessable()->assertJsonValidationErrors('sel.0');
        $cbt = UjianCbt::create([
            'jenis_ujian_cbt_id' => JenisUjianCbt::query()->firstOrFail()->id,
            'tahun_pelajaran_id' => $d['tahun']->id, 'mata_pelajaran_id' => $d['mapelA']->mata_pelajaran_id,
            'kode' => 'CBT-TERKAIT', 'nama' => 'Ujian terkait', 'semester' => 'ganjil', 'tingkat' => 8,
        ]);
        $cbt->kelasUjianCbt()->create(['kelas_id' => $d['kelasA']->id, 'komponen_nilai_id' => $d['formatif']->id]);
        $this->simpanKolektif($d, [$this->sel($d['formatif'], $d['siswa'], '100', '80.00')])
            ->assertUnprocessable()->assertJsonValidationErrors('sel.0')
            ->assertJsonPath('message', 'Dikelola melalui CBT. Nilai tidak diubah.');
        $this->assertDatabaseCount('nilai_siswa', 1);
        $this->assertSame('80.00', $d['formatif']->nilaiSiswa()->firstOrFail()->nilai);
    }

    public function test_kolektif_menggunakan_predikat_pada_mapel_yang_sesuai(): void
    {
        $d = $this->data();
        $d['mapelA']->mataPelajaran->update(['kelompok' => 'Ekstrakurikuler']);
        $this->actingAs($d['guru']);
        $this->simpanKolektif($d, [$this->sel($d['formatif'], $d['siswa'], 'SB')])
            ->assertOk()->assertJsonPath('jumlah', 1);
        $this->assertDatabaseHas('nilai_siswa', ['komponen_nilai_id' => $d['formatif']->id, 'nilai' => null, 'predikat' => 'SB']);
        $this->simpanKolektif($d, [$this->sel($d['formatif'], $d['siswa'], '90', 'SB')])
            ->assertUnprocessable()->assertJsonValidationErrors('sel.0');
    }

    public function test_export_excel_mencakup_semua_komponen_aktif_dan_mengikuti_bobot_rapor(): void
    {
        $d = $this->data();
        $tambahan = [
            [$this->komponen($d['mapelA'], 'formatif', 'Tugas 2'), 0],
            [$this->komponen($d['mapelA'], 'formatif', 'Tugas 3'), 82.25],
            [$this->komponen($d['mapelA'], 'sumatif', 'Sumatif 1'), 85],
            [$this->komponen($d['mapelA'], 'sumatif', 'Sumatif 2'), 91],
            [$d['sts'], 86.25],
            [$this->komponen($d['mapelA'], 'sas_saj', 'Sumatif Akhir Semester'), 91],
        ];
        foreach ($tambahan as [$komponen, $nilai]) {
            $komponen->nilaiSiswa()->create(['siswa_id' => $d['siswa']->id, 'nilai' => $nilai]);
        }
        $kedua = $this->siswa($d['tahun'], $d['kelasA'], 'Siswa Kedua', '26003', 2);
        $this->siswa($d['tahun'], $d['kelasA'], 'Siswa Ketiga', '26004', 3);
        $d['formatif']->nilaiSiswa()->create(['siswa_id' => $kedua->id, 'nilai' => 90]);
        $this->skemaExport($d, [25, 25, 25, 25], null);
        $this->skemaExport($d, [40, 25, 15, 20], 8);
        $jumlahSebelum = NilaiSiswa::count();

        $parameter = ['guru_mata_pelajaran_id' => $d['mapelA']->id, 'semester' => 'ganjil'];
        $url = route('input-nilai.kolektif.export', $parameter);
        $this->actingAs($d['guru'])->get(route('input-nilai.kolektif', [
            ...$parameter, 'tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['kelasA']->id,
        ]))->assertOk()->assertSee('Ekspor Excel')->assertSee($url);
        $respons = $this->get(route('input-nilai.kolektif.export', [...$parameter, 'komponen' => [$d['formatif']->id]]))
            ->assertOk()->assertHeader('Content-Type', PenulisExcelNilaiRapor::MIME)
            ->assertDownload('nilai-informatika-viiia-bilingual-ganjil-20262027.xlsx');
        [$nilai, $bobot] = $this->bacaExport($respons, 'export-nilai');

        $identitas = ['No.', 'Nama', 'Kelas', $d['formatif']->nama, 'Tugas 2', 'Tugas 3',
            'Sumatif 1', 'Sumatif 2', $d['sts']->nama, 'Sumatif Akhir Semester', 'Nilai Akhir Sementara'];
        foreach (range('A', 'K') as $index => $huruf) {
            $this->assertSame($identitas[$index], (string) $this->selExcel($nilai, $huruf.'5')->is->t);
        }
        $this->assertSame('Formatif', (string) $this->selExcel($nilai, 'D4')->is->t);
        $this->assertSame('Sumatif Harian', (string) $this->selExcel($nilai, 'G4')->is->t);
        $this->assertSame('STS', (string) $this->selExcel($nilai, 'I4')->is->t);
        $this->assertSame('SAS', (string) $this->selExcel($nilai, 'J4')->is->t);
        $this->assertSame('0', (string) $this->selExcel($nilai, 'E6')->v);
        $this->assertFalse(isset($this->selExcel($nilai, 'E7')->v));
        $this->assertEqualsWithDelta(0.4, (float) $this->selExcel($bobot, 'B2')->v, 0.000001);
        $this->assertSame('0.2', (string) $this->selExcel($bobot, 'B5')->v);
        $rumus = (string) $this->selExcel($nilai, 'K6')->f;
        $this->assertStringContainsString('ROUND(SUM(D6:F6)/COLUMNS(D6:F6),2)*Bobot!$B$2', $rumus);
        $this->assertStringContainsString('ROUND(SUM(G6:H6)/COLUMNS(G6:H6),2)*Bobot!$B$3', $rumus);
        $this->assertStringContainsString('ROUND(SUM(J6:J6)/COLUMNS(J6:J6),2)*Bobot!$B$5', $rumus);
        $this->assertSame('74.77', (string) $this->selExcel($nilai, 'K6')->v);
        $rekap = app(RekapNilaiRaporService::class)->hitung($d['mapelA']->id, 'ganjil', $d['guru']);
        $this->assertSame($rekap['rekapNilai']->first()['nilai_akhir'], (float) $this->selExcel($nilai, 'K6')->v);
        $this->assertSame('12', (string) $this->selExcel($nilai, 'K7')->v);
        $this->assertStringContainsString('ROUND(SUM(D7:F7)/COLUMNS(D7:F7),2)', (string) $this->selExcel($nilai, 'K7')->f);
        $this->assertSame('0', (string) $this->selExcel($nilai, 'K8')->v);
        $this->assertNull($rekap['rekapNilai'][1]['nilai_akhir']);
        $this->assertNull($rekap['rekapNilai'][2]['nilai_akhir']);
        $this->assertFalse(isset($this->selExcel($nilai, 'I7')->v));
        $this->assertFalse(isset($this->selExcel($nilai, 'J7')->v));
        $this->assertStringNotContainsString('Komponen nonaktif', $nilai->asXML());
        $this->assertStringNotContainsString($d['genap']->nama, $nilai->asXML());
        $this->assertDatabaseCount('nilai_siswa', $jumlahSebelum);
        $this->assertDatabaseCount('publikasi_nilai_siswa', 0);
    }

    public function test_export_excel_menandai_komponen_yang_belum_dibuat_dan_memerlukan_skema_bobot(): void
    {
        $d = $this->data();
        $this->actingAs($d['guru']);
        $url = route('input-nilai.kolektif.export', ['guru_mata_pelajaran_id' => $d['mapelA']->id, 'semester' => 'ganjil']);
        [$nilai, $bobot] = $this->bacaExport($this->get($url)->assertOk());
        $this->assertFalse(isset($this->selExcel($bobot, 'B2')->v));
        $this->assertSame('', (string) $this->selExcel($nilai, 'F6')->v);
        $this->assertStringContainsString('COUNT(Bobot!$B$2:$B$5)=4', (string) $this->selExcel($nilai, 'F6')->f);

        $skema = $this->skemaExport($d, [50, 50, 0, 0], null);
        [$nilai, $bobot] = $this->bacaExport($this->get($url)->assertOk(), 'export-nilai-komponen-belum-dibuat');
        $this->assertSame('40', (string) $this->selExcel($nilai, 'F6')->v);
        $this->assertStringNotContainsString('Bobot!$B$3', (string) $this->selExcel($nilai, 'F6')->f);
        $this->assertSame('Komponen belum dibuat', (string) $this->selExcel($bobot, 'C3')->is->t);
        $this->assertSame('Komponen belum dibuat', (string) $this->selExcel($bobot, 'C5')->is->t);
        $this->assertSame('1', (string) $this->selExcel($bobot, 'C2')->v);
        $skema->update(['bobot_formatif' => 100, 'bobot_sumatif' => 0]);
        [$nilai] = $this->bacaExport($this->get($url)->assertOk());
        $this->assertSame('80', (string) $this->selExcel($nilai, 'F6')->v);
    }

    public function test_export_excel_membatasi_cakupan_dan_tidak_mengubah_predikat_menjadi_angka(): void
    {
        $d = $this->data();
        $this->actingAs($d['guru']);
        $this->get(route('input-nilai.kolektif.export', [
            'guru_mata_pelajaran_id' => $d['asing']->guru_mata_pelajaran_id, 'semester' => 'ganjil',
        ]))->assertNotFound();
        $this->getJson(route('input-nilai.kolektif.export', [
            'guru_mata_pelajaran_id' => $d['mapelA']->id, 'semester' => 'semua',
        ]))->assertUnprocessable()->assertJsonValidationErrors('semester');

        $d['mapelA']->mataPelajaran->update(['kelompok' => 'Ekstrakurikuler']);
        $d['formatif']->nilaiSiswa()->firstOrFail()->update(['nilai' => null, 'predikat' => 'SB']);
        [$nilai] = $this->bacaExport($this->get(route('input-nilai.kolektif.export', [
            'guru_mata_pelajaran_id' => $d['mapelA']->id, 'semester' => 'ganjil',
        ]))->assertOk());
        $this->assertSame('SB', (string) $this->selExcel($nilai, 'D6')->is->t);
        $this->assertFalse(isset($this->selExcel($nilai, 'F6')->f));
    }

    private function skemaExport(array $data, array $bobot, ?int $tingkat): SkemaBobotNilai
    {
        return SkemaBobotNilai::create([
            'tahun_pelajaran_id' => $data['tahun']->id, 'semester' => 'ganjil', 'tingkat' => $tingkat,
            'bobot_formatif' => $bobot[0], 'bobot_sumatif' => $bobot[1],
            'bobot_sts' => $bobot[2], 'bobot_sas_saj' => $bobot[3], 'aktif' => true,
        ]);
    }

    private function bacaExport(TestResponse $respons, ?string $simpan = null): array
    {
        $isi = $respons->streamedContent();
        $nama = tempnam(sys_get_temp_dir(), 'uji-xlsx-');
        file_put_contents($nama, $isi);
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($nama));
            $lembar = [];
            foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $path) {
                $xml = new SimpleXMLElement($zip->getFromName($path));
                $lembar[] = $xml;
            }
            $zip->close();
        } finally {
            unlink($nama);
        }
        if ($simpan && getenv('NUSA_CAPTURE_NILAI_EXPORT')) {
            $dir = storage_path('framework/testing/input-nilai');
            File::ensureDirectoryExists($dir);
            File::put($dir.'/'.$simpan.'.xlsx', $isi);
        }

        return $lembar;
    }

    private function selExcel(SimpleXMLElement $sheet, string $ref): SimpleXMLElement
    {
        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $sheet->xpath('//x:c[@r="'.$ref.'"]')[0];
    }

    private function simpanKolektif(array $data, array $perubahan, ?int $penugasanId = null)
    {
        return $this->postJson(route('input-nilai.kolektif.store'), [
            'guru_mata_pelajaran_id' => $penugasanId ?? $data['mapelA']->id,
            'semester' => 'ganjil',
            'perubahan' => $perubahan,
        ]);
    }

    private function sel(KomponenNilai $komponen, Siswa $siswa, ?string $nilai, ?string $awal = null): array
    {
        return [
            'komponen_nilai_id' => $komponen->id, 'siswa_id' => $siswa->id, 'nilai' => $nilai, 'nilai_awal' => $awal,
        ];
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

    private function siswa(TahunPelajaran $tahun, Kelas $kelas, string $nama, string $nis, int $nomorAbsen = 1): Siswa
    {
        $siswa = Siswa::create(['nama_lengkap' => $nama, 'nis' => $nis, 'aktif' => true]);
        AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'nomor_absen' => $nomorAbsen, 'status_keanggotaan' => 'aktif']);

        return $siswa;
    }
}
