<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class SoalCbtTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite belum aktif pada PHP lokal.');
        }

        $this->artisan('migrate:fresh');
    }

    public function test_administrator_dapat_mengelola_bank_soal_cbt(): void
    {
        [$tahunPelajaran, $mataPelajaran] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('soal-cbt.create'))
            ->assertOk()
            ->assertSee('Tambah soal')
            ->assertSee('Isi jawaban dan tentukan kunci')
            ->assertSee('Tambahkan pendukung soal')
            ->assertSee('Pratinjau soal')
            ->assertSee('name="gambar_soal"', false)
            ->assertSee('name="media_tabel"', false)
            ->assertSee('name="rumus_latex"', false)
            ->assertSee('data-formula-field', false)
            ->assertSeeText('Simpan siap & buat berikutnya')
            ->assertDontSee('name="kode"', false)
            ->assertDontSee('name="tahun_pelajaran_id"', false)
            ->assertDontSee('name="skor_maksimal"', false)
            ->assertDontSee('name="aktif"', false);

        $this->actingAs($administrator)
            ->post(route('soal-cbt.store'), $this->dataSoal($tahunPelajaran, $mataPelajaran))
            ->assertRedirect();

        $soalCbt = SoalCbt::where('kode', 'SOAL-CBT-UJI-001')->firstOrFail();

        $this->assertSame('pilihan_ganda', $soalCbt->jenis_soal);
        $this->assertSame('B', $soalCbt->kunci_jawaban['jawaban']);
        $this->assertSame('2 Hz', $soalCbt->opsi['pilihan']['B']);

        $this->actingAs($administrator)
            ->get(route('soal-cbt.show', $soalCbt))
            ->assertOk()
            ->assertSee('SOAL-CBT-UJI-001')
            ->assertSee('Getaran yang terjadi');

        $this->actingAs($administrator)
            ->put(route('soal-cbt.update', $soalCbt), [
                ...$this->dataSoal($tahunPelajaran, $mataPelajaran),
                'jenis_soal' => 'pilihan_ganda_kompleks',
                'kunci_pg' => '',
                'kunci_pgk' => ['B', 'C'],
                'status' => 'siap',
            ])
            ->assertRedirect(route('soal-cbt.show', $soalCbt));

        $soalCbt->refresh();
        $this->assertSame('pilihan_ganda_kompleks', $soalCbt->jenis_soal);
        $this->assertSame(['B', 'C'], $soalCbt->kunci_jawaban['jawaban']);
        $this->assertSame('siap', $soalCbt->status);

        $this->actingAs($administrator)
            ->delete(route('soal-cbt.destroy', $soalCbt))
            ->assertRedirect(route('soal-cbt.index'));

        $this->assertSame('arsip', $soalCbt->fresh()->status);
        $this->assertFalse($soalCbt->fresh()->aktif);
    }

    public function test_gambar_tabel_dan_rumus_dapat_disimpan_dan_ditampilkan(): void
    {
        Storage::fake('public');
        [$tahunPelajaran, $mataPelajaran] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('soal-cbt.store'), [
                ...$this->dataSoal($tahunPelajaran, $mataPelajaran),
                'rumus_latex' => '\\sqrt{\\placeholder{}}',
            ])
            ->assertSessionHasErrors('rumus_latex');

        $this->assertDatabaseMissing('soal_cbt', ['kode' => 'SOAL-CBT-UJI-001']);

        $this->actingAs($administrator)
            ->post(route('soal-cbt.store'), [
                ...$this->dataSoal($tahunPelajaran, $mataPelajaran),
                'gambar_soal' => UploadedFile::fake()->createWithContent(
                    'grafik.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
                ),
                'gambar_alt' => 'Grafik simpangan terhadap waktu',
                'gambar_keterangan' => 'Data percobaan siswa',
                'media_tabel' => json_encode([
                    ['Waktu', 'Simpangan'],
                    ['1 sekon', '4 cm'],
                    ['2 sekon', '8 cm'],
                ]),
                'tabel_judul' => 'Hasil pengamatan',
                'rumus_latex' => 'f = \\frac{n}{t}',
                'rumus_keterangan' => 'Rumus frekuensi',
            ])
            ->assertRedirect();

        $soal = SoalCbt::where('kode', 'SOAL-CBT-UJI-001')->firstOrFail();
        $pathGambar = data_get($soal->media, 'gambar.path');

        Storage::disk('public')->assertExists($pathGambar);
        $this->assertSame('Grafik simpangan terhadap waktu', data_get($soal->media, 'gambar.alt'));
        $this->assertSame('Simpangan', data_get($soal->media, 'tabel.baris.0.1'));
        $this->assertSame('f = \\frac{n}{t}', data_get($soal->media, 'rumus.latex'));

        $this->actingAs($administrator)
            ->get(route('soal-cbt.show', $soal))
            ->assertOk()
            ->assertSee('Hasil pengamatan')
            ->assertSee('Data percobaan siswa')
            ->assertSee('data-rumus-latex="f = \\frac{n}{t}"', false);

        $this->actingAs($administrator)
            ->put(route('soal-cbt.update', $soal), [
                ...$this->dataSoal($tahunPelajaran, $mataPelajaran),
                'hapus_gambar_soal' => '1',
                'media_tabel' => '',
                'rumus_latex' => '',
            ])
            ->assertRedirect(route('soal-cbt.show', $soal));

        Storage::disk('public')->assertMissing($pathGambar);
        $this->assertNull($soal->fresh()->media);
    }

    public function test_form_ringkas_mengisi_identitas_otomatis_dan_melanjutkan_konteks(): void
    {
        [, $mataPelajaran] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $response = $this->actingAs($administrator)
            ->post(route('soal-cbt.store'), [
                'mata_pelajaran_id' => $mataPelajaran->id,
                'tingkat' => 8,
                'jenis_soal' => 'pilihan_ganda',
                'topik' => 'Getaran',
                'pertanyaan' => 'Satuan frekuensi adalah ....',
                'opsi' => [
                    'A' => 'Meter',
                    'B' => 'Hertz',
                    'C' => 'Sekon',
                    'D' => 'Newton',
                ],
                'kunci_pg' => 'B',
                'aksi' => 'simpan_lanjut',
            ]);

        $soal = SoalCbt::where('pertanyaan', 'Satuan frekuensi adalah ....')->firstOrFail();

        $response->assertRedirect(route('soal-cbt.create', [
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 8,
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'umum',
            'topik' => 'Getaran',
            'materi' => null,
        ]));

        $this->assertMatchesRegularExpression('/^SOAL-CBT-\d{8}-\d{3}$/', $soal->kode);
        $this->assertNull($soal->tahun_pelajaran_id);
        $this->assertSame('siap', $soal->status);
        $this->assertSame('1.00', $soal->skor_maksimal);
        $this->assertTrue($soal->aktif);

        $this->actingAs($administrator)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Matematika Kelas VIII')
            ->assertSee('value="Getaran"', false);
    }

    public function test_guru_mapel_hanya_dapat_mengelola_soal_mapel_yang_diajar(): void
    {
        [$tahunPelajaran, $mataPelajaran, $pegawai] = $this->buatDataAkademik();
        $mataPelajaranLain = MataPelajaran::create([
            'kode' => 'IPA-8',
            'nama' => 'IPA Kelas VIII',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $guruMapel = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => 'Dewi Anggraini',
            'username' => '198201012010012001',
            'kata_sandi' => 'secret',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $guruMapel->daftarPeran()->sync([Peran::where('kode', 'guru_mapel')->value('id')]);

        $this->actingAs($guruMapel)
            ->get(route('soal-cbt.create'))
            ->assertOk()
            ->assertSee('Matematika Kelas VIII')
            ->assertDontSee('IPA Kelas VIII');

        $this->actingAs($guruMapel)
            ->post(route('soal-cbt.store'), $this->dataSoal($tahunPelajaran, $mataPelajaran))
            ->assertRedirect();

        $this->actingAs($guruMapel)
            ->post(route('soal-cbt.store'), $this->dataSoal($tahunPelajaran, $mataPelajaranLain, 'SOAL-CBT-LAIN-001'))
            ->assertForbidden();
    }

    private function buatDataAkademik(): array
    {
        $tahunPelajaran = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'nama' => 'VIII.A',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'MTK-8',
            'nama' => 'Matematika Kelas VIII',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Dewi Anggraini, S.Pd.',
            'nip' => '198201012010012001',
            'aktif' => true,
        ]);
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $pegawai->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);

        return [$tahunPelajaran, $mataPelajaran, $pegawai];
    }

    private function dataSoal(TahunPelajaran $tahunPelajaran, MataPelajaran $mataPelajaran, string $kode = 'SOAL-CBT-UJI-001'): array
    {
        return [
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 8,
            'kode' => $kode,
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'umum',
            'topik' => 'Getaran',
            'materi' => 'Frekuensi',
            'tujuan_pembelajaran' => 'Siswa dapat menentukan frekuensi getaran.',
            'stimulus' => null,
            'pertanyaan' => 'Getaran yang terjadi sebanyak 20 kali dalam 10 sekon memiliki frekuensi ....',
            'opsi' => [
                'A' => '0,5 Hz',
                'B' => '2 Hz',
                'C' => '10 Hz',
                'D' => '200 Hz',
            ],
            'kunci_pg' => 'B',
            'kunci_pgk' => [],
            'skor_maksimal' => 1,
            'pembahasan' => 'Frekuensi adalah jumlah getaran dibagi waktu.',
            'status' => 'draft',
            'aktif' => '1',
        ];
    }
}
