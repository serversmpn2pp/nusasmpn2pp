<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\FolderSoalCbt;
use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use PDO;
use Tests\TestCase;

class AsesmenKelasCbtTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite belum aktif pada PHP lokal.');
        }

        $this->artisan('migrate:fresh');
    }

    public function test_guru_mapel_membuat_asesmen_untuk_kelas_sendiri_dan_peserta_langsung_terbentuk(): void
    {
        [$tahun, $mapel, $kelasGuru, $kelasLain, $guru, $guruLain, $akunGuru] = $this->buatDataDasar();
        $this->buatSiswa($tahun, $kelasGuru, 2);

        $this->actingAs($akunGuru)
            ->get(route('asesmen-kelas-cbt.create'))
            ->assertOk()
            ->assertSee('Matematika - Kelas 7')
            ->assertSee('VII.A')
            ->assertDontSee('VII.B');

        $kelompok = implode('-', [$guru->id, $mapel->id, 7]);
        $this->actingAs($akunGuru)
            ->post(route('asesmen-kelas-cbt.store'), $this->dataAsesmen($kelompok, $kelasGuru->id))
            ->assertRedirect();

        $asesmen = UjianCbt::query()->where('nama', 'Sumatif Bab Bilangan')->firstOrFail();

        $this->assertSame('kelas', $asesmen->alur);
        $this->assertSame($akunGuru->id, $asesmen->dibuat_oleh_pengguna_id);
        $this->assertNull($asesmen->token);
        $this->assertDatabaseHas('kelas_ujian_cbt', [
            'ujian_cbt_id' => $asesmen->id,
            'kelas_id' => $kelasGuru->id,
        ]);
        $this->assertDatabaseHas('komponen_nilai', [
            'guru_mata_pelajaran_id' => GuruMataPelajaran::where('pegawai_id', $guru->id)->value('id'),
            'jenis_komponen' => 'sumatif',
            'nama' => 'Sumatif Bab Bilangan',
        ]);
        $this->assertSame(2, $asesmen->pesertaUjianCbt()->count());
        $this->assertSame(0, $asesmen->sesiUjianCbt()->count());
        $this->assertSame(0, $asesmen->ruangUjianCbt()->count());

        $this->actingAs($akunGuru)
            ->get(route('ujian-cbt.soal.edit', $asesmen))
            ->assertOk()
            ->assertSee('Pilih soal asesmen');
        $this->actingAs($akunGuru)
            ->get(route('ujian-cbt.monitoring.index', $asesmen))
            ->assertOk()
            ->assertDontSee('Peserta & sesi');
        $this->actingAs($akunGuru)
            ->get(route('ujian-cbt.hasil.index', $asesmen))
            ->assertOk()
            ->assertSee('Hasil asesmen')
            ->assertSee('Tujuan nilai')
            ->assertSee('Masukkan ke nilai')
            ->assertSee('Sumatif Bab Bilangan')
            ->assertSee('Belum tersedia')
            ->assertDontSee('Rekap hasil CBT')
            ->assertDontSee('Semua sesi')
            ->assertDontSee('Ruang');
        $this->actingAs($akunGuru)
            ->get(route('ujian-cbt.koreksi-manual.index', $asesmen))
            ->assertOk()
            ->assertSee('Koreksi jawaban uraian')
            ->assertDontSee('Koreksi manual CBT')
            ->assertDontSee('Semua sesi')
            ->assertDontSee('Tanpa sesi');

        $akunGuruLain = $this->buatAkunGuru($guruLain, 'guru-lain');
        $this->actingAs($akunGuruLain)
            ->get(route('asesmen-kelas-cbt.show', $asesmen))
            ->assertForbidden();
        $this->actingAs($akunGuruLain)
            ->get(route('ujian-cbt.soal.edit', $asesmen))
            ->assertForbidden();
    }

    public function test_guru_mapel_tidak_dapat_memasukkan_kelas_guru_lain(): void
    {
        [, $mapel, $kelasGuru, $kelasLain, $guru, , $akunGuru] = $this->buatDataDasar();
        $kelompok = implode('-', [$guru->id, $mapel->id, 7]);

        $this->actingAs($akunGuru)
            ->from(route('asesmen-kelas-cbt.create'))
            ->post(route('asesmen-kelas-cbt.store'), $this->dataAsesmen($kelompok, $kelasLain->id))
            ->assertRedirect(route('asesmen-kelas-cbt.create'))
            ->assertSessionHasErrors('kelas_peserta');

        $this->assertDatabaseCount('ujian_cbt', 0);
        $this->assertDatabaseCount('peserta_ujian_cbt', 0);
        $this->assertDatabaseCount('komponen_nilai', 0);
        $this->assertNotSame($kelasGuru->id, $kelasLain->id);
    }

    public function test_pilihan_soal_asesmen_memuat_pratinjau_semua_jenis_tanpa_mengubah_pilihan(): void
    {
        [$tahun, $mapel, $kelas, , $guru, , $akunGuru] = $this->buatDataDasar();
        $kelompok = implode('-', [$guru->id, $mapel->id, 7]);
        $this->actingAs($akunGuru)
            ->post(route('asesmen-kelas-cbt.store'), $this->dataAsesmen($kelompok, $kelas->id))
            ->assertRedirect();
        $asesmen = UjianCbt::query()->firstOrFail();
        $folder = FolderSoalCbt::create([
            'mata_pelajaran_id' => $mapel->id,
            'tingkat' => 7,
            'nama' => 'Bilangan dan operasi',
        ]);
        $soal = collect(SoalCbt::DAFTAR_JENIS)->map(function ($label, $jenis) use ($tahun, $mapel, $akunGuru) {
            return SoalCbt::create([
                'tahun_pelajaran_id' => $tahun->id,
                'mata_pelajaran_id' => $mapel->id,
                'tingkat' => 7,
                'kode' => 'REVIEW-'.$jenis,
                'jenis_soal' => $jenis,
                'tingkat_kesulitan' => 'sedang',
                'kategori' => 'mots',
                'stimulus' => 'Perhatikan operasi \\(2^{3}\\) berikut.',
                'pertanyaan' => 'Soal '.$label.': tentukan hasil \\(\\frac{8}{2}\\).',
                'opsi' => match ($jenis) {
                    'pilihan_ganda', 'pilihan_ganda_kompleks' => ['pilihan' => ['A' => '\\(2^{2}\\)', 'B' => '8', 'C' => '16', 'D' => '32']],
                    'benar_salah' => ['pernyataan' => [['teks' => '\\(2^{3} = 8\\)'], ['teks' => 'Delapan dibagi dua adalah empat.']]],
                    'menjodohkan' => ['pasangan' => [['kiri' => 'Dua pangkat tiga', 'kanan' => '8'], ['kiri' => 'Delapan dibagi dua', 'kanan' => '4']], 'pengecoh' => ['16']],
                    default => [],
                },
                'media' => [
                    'gambar' => ['path' => 'soal-cbt/preview-fixture.png', 'alt' => 'Ilustrasi operasi bilangan', 'keterangan' => 'Gambar pendukung soal'],
                    'tabel' => ['judul' => 'Data bilangan', 'baris' => [['Bilangan', 'Hasil'], ['2 pangkat 3', '8']]],
                    'rumus' => ['latex' => '\\frac{8}{2}', 'keterangan' => 'Pembagian bilangan'],
                ],
                'skor_maksimal' => 2,
                'kunci_jawaban' => ['jawaban' => 'KUNCI-RAHASIA-ASESMEN'],
                'pembahasan' => 'PEMBAHASAN-RAHASIA-ASESMEN',
                'status' => 'siap',
                'aktif' => true,
                'dibuat_oleh_pengguna_id' => $akunGuru->id,
            ]);
        });
        foreach (['pilihan_ganda', 'pilihan_ganda_kompleks'] as $index => $jenis) {
            $soal[$jenis]->folders()->attach($folder);
            $asesmen->soalUjianCbt()->create(['soal_cbt_id' => $soal[$jenis]->id, 'nomor_urut' => $index + 1, 'bobot' => 2]);
        }
        $tersembunyi = $soal['pilihan_ganda']->replicate();
        $tersembunyi->fill(['kode' => 'SOAL-TINGKAT-LAIN', 'tingkat' => 9])->save();
        $draft = $soal['pilihan_ganda']->replicate();
        $draft->fill(['kode' => 'SOAL-DRAFT-BELUM-DIPILIH', 'status' => 'draft'])->save();

        $response = $this->actingAs($akunGuru)->get(route('ujian-cbt.soal.edit', $asesmen));
        $response->assertOk()->assertSee('Pratinjau soal')
            ->assertSee('Ilustrasi operasi bilangan')->assertSee('Data bilangan')
            ->assertSee('data-question-preview-dialog', false)
            ->assertDontSee('KUNCI-RAHASIA-ASESMEN')->assertDontSee('PEMBAHASAN-RAHASIA-ASESMEN')
            ->assertDontSee('SOAL-TINGKAT-LAIN')->assertDontSee('SOAL-DRAFT-BELUM-DIPILIH');
        foreach ($soal as $item) {
            $this->assertSame(2, substr_count($response->getContent(), 'data-preview-source="preview-soal-'.$item->id.'"'));
            $this->assertSame(1, substr_count($response->getContent(), '<template id="preview-soal-'.$item->id.'">'));
        }
        $this->assertSame(2, $asesmen->soalUjianCbt()->count());
        $this->assertDatabaseHas('soal_ujian_cbt', ['ujian_cbt_id' => $asesmen->id, 'soal_cbt_id' => $soal['pilihan_ganda']->id, 'nomor_urut' => 1, 'bobot' => 2]);

        if (getenv('ASESMEN_PREVIEW_FIXTURE')) {
            file_put_contents(storage_path('logs/asesmen-preview.html'), $response->getContent());
        }
    }

    public function test_komponen_otomatis_yang_sudah_ada_dipakai_ulang_tanpa_error(): void
    {
        [, $mapel, $kelasGuru, , $guru, , $akunGuru] = $this->buatDataDasar();
        $guruMataPelajaranId = GuruMataPelajaran::query()
            ->where('pegawai_id', $guru->id)
            ->where('kelas_id', $kelasGuru->id)
            ->value('id');
        $komponen = KomponenNilai::create([
            'guru_mata_pelajaran_id' => $guruMataPelajaranId,
            'semester' => 'ganjil',
            'jenis_komponen' => 'sumatif',
            'nama' => 'Sumatif Bab Bilangan',
            'tanggal_penilaian' => '2026-08-20',
            'urutan' => 1,
            'aktif' => true,
        ]);
        $kelompok = implode('-', [$guru->id, $mapel->id, 7]);

        $this->actingAs($akunGuru)
            ->post(route('asesmen-kelas-cbt.store'), $this->dataAsesmen($kelompok, $kelasGuru->id))
            ->assertRedirect();

        $asesmen = UjianCbt::query()->where('nama', 'Sumatif Bab Bilangan')->firstOrFail();
        $this->assertDatabaseCount('komponen_nilai', 1);
        $this->assertDatabaseHas('kelas_ujian_cbt', [
            'ujian_cbt_id' => $asesmen->id,
            'kelas_id' => $kelasGuru->id,
            'komponen_nilai_id' => $komponen->id,
        ]);
    }

    private function buatDataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $mapel = MataPelajaran::create([
            'kode' => 'MTK',
            'nama' => 'Matematika',
            'kelompok' => 'Pelajaran Umum',
            'kkm' => 75,
            'aktif' => true,
        ]);
        $kelasGuru = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.B',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $guru = $this->buatPegawai('Guru Matematika A', '19800001');
        $guruLain = $this->buatPegawai('Guru Matematika B', '19800002');

        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasGuru->id,
            'mata_pelajaran_id' => $mapel->id,
            'pegawai_id' => $guru->id,
            'aktif' => true,
        ]);
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasLain->id,
            'mata_pelajaran_id' => $mapel->id,
            'pegawai_id' => $guruLain->id,
            'aktif' => true,
        ]);

        return [$tahun, $mapel, $kelasGuru, $kelasLain, $guru, $guruLain, $this->buatAkunGuru($guru, 'guru-matematika')];
    }

    private function buatPegawai(string $nama, string $nip): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
    }

    private function buatAkunGuru(Pegawai $pegawai, string $username): Pengguna
    {
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $username,
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::whereIn('kode', ['pegawai', 'guru_mapel'])->pluck('id'));

        return $akun;
    }

    private function buatSiswa(TahunPelajaran $tahun, Kelas $kelas, int $jumlah): void
    {
        for ($index = 1; $index <= $jumlah; $index++) {
            $siswa = Siswa::create([
                'nama_lengkap' => 'Siswa Uji '.$index,
                'nis' => 'NIS-'.$index,
                'nisn' => '000000000'.$index,
                'jenis_kelamin' => $index % 2 ? 'L' : 'P',
                'aktif' => true,
            ]);
            AnggotaKelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $index,
                'status_keanggotaan' => 'aktif',
                'tanggal_masuk' => '2026-07-01',
            ]);
        }
    }

    private function dataAsesmen(string $kelompok, int $kelasId): array
    {
        return [
            'kelompok_pengajaran' => $kelompok,
            'nama' => 'Sumatif Bab Bilangan',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-08-24 08:00:00',
            'tanggal_selesai' => '2026-08-24 09:00:00',
            'durasi_menit' => 40,
            'jumlah_soal' => 20,
            'status' => 'terjadwal',
            'acak_soal' => '1',
            'acak_jawaban' => '1',
            'kelas_peserta' => [
                $kelasId => [
                    'dipilih' => '1',
                    'komponen_nilai_id' => 'baru',
                ],
            ],
        ];
    }
}
