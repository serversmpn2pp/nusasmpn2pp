<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\AnggotaKelas;
use App\Models\BatchScanUjianOmr;
use App\Models\HasilScanLjkUjianOmr;
use App\Models\Kelas;
use App\Models\KelasUjianOmr;
use App\Models\KomponenNilai;
use App\Models\LembarJawabUjianOmr;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UjianOmr;
use App\Models\VersiSoalUjianOmr;
use App\Services\Omr\PembacaPdfLjkOmr;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class UjianOmrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite belum aktif pada PHP lokal.');
        }

        $this->artisan('migrate:fresh');
    }

    public function test_administrator_dapat_membuat_ujian_mengisi_kunci_dan_menandai_siap(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator);

        $this->get(route('ujian-omr.create'))
            ->assertOk()
            ->assertSee('Tambah ujian')
            ->assertSee('Kelas Peserta dan Tujuan Nilai');

        $this->post(route('ujian-omr.store'), [
                ...$this->dataUjian($tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai),
                'status' => 'draft',
            ])
            ->assertRedirect();

        $ujianOmr = UjianOmr::where('kode', 'OMR-UJI-001')->firstOrFail();
        $versiA = VersiSoalUjianOmr::where('ujian_omr_id', $ujianOmr->id)->where('kode', 'A')->firstOrFail();
        $versiB = VersiSoalUjianOmr::where('ujian_omr_id', $ujianOmr->id)->where('kode', 'B')->firstOrFail();
        $jawaban = collect(range(1, 50))
            ->mapWithKeys(fn (int $nomor) => [$nomor => ['A', 'B', 'C', 'D'][($nomor - 1) % 4]])
            ->all();

        $this->get(route('ujian-omr.show', $ujianOmr))
            ->assertOk()
            ->assertSee('Ujian Percobaan OMR')
            ->assertSee('0 / 50 jawaban');

        $this->get(route('ujian-omr.kunci-jawaban.edit', [$ujianOmr, $versiA]))
            ->assertOk()
            ->assertSee('Kunci jawaban versi A')
            ->assertSee('Nomor 50');

        $this->put(route('ujian-omr.kunci-jawaban.update', [$ujianOmr, $versiA]), ['jawaban' => $jawaban])
            ->assertRedirect(route('ujian-omr.show', $ujianOmr));
        $this->put(route('ujian-omr.kunci-jawaban.update', [$ujianOmr, $versiB]), ['jawaban' => $jawaban])
            ->assertRedirect(route('ujian-omr.show', $ujianOmr));

        $this->assertDatabaseCount('kunci_jawaban_ujian_omr', 100);

        $this->get(route('ujian-omr.edit', $ujianOmr))
            ->assertOk()
            ->assertSee('Edit ujian');

        $this->put(route('ujian-omr.update', $ujianOmr), [
            ...$this->dataUjian($tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai),
            'status' => 'siap',
        ])->assertRedirect(route('ujian-omr.show', $ujianOmr));

        $this->assertSame('siap', $ujianOmr->fresh()->status);
        $this->get(route('ujian-omr.show', $ujianOmr))
            ->assertOk()
            ->assertSee('Siap digunakan')
            ->assertSee('50 / 50 jawaban');
    }

    public function test_ujian_belum_bisa_siap_jika_kunci_jawaban_belum_lengkap(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('ujian-omr.store'), [
                ...$this->dataUjian($tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai),
                'status' => 'draft',
            ]);

        $ujianOmr = UjianOmr::where('kode', 'OMR-UJI-001')->firstOrFail();

        $this->from(route('ujian-omr.edit', $ujianOmr))
            ->put(route('ujian-omr.update', $ujianOmr), [
                ...$this->dataUjian($tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai),
                'status' => 'siap',
            ])
            ->assertRedirect(route('ujian-omr.edit', $ujianOmr))
            ->assertSessionHasErrors('status');

        $this->assertSame('draft', $ujianOmr->fresh()->status);
    }

    public function test_omr_menolak_komponen_sumatif_harian_sebagai_tujuan_nilai(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik('sumatif');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->from(route('ujian-omr.create'))
            ->post(route('ujian-omr.store'), [
                ...$this->dataUjian($tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai),
                'status' => 'draft',
            ])
            ->assertRedirect(route('ujian-omr.create'))
            ->assertSessionHasErrors('kelas_peserta');

        $this->assertDatabaseCount('ujian_omr', 0);
    }

    public function test_administrator_dapat_generate_dan_mencetak_ljk_personal_siswa(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $anggotaPertama = $this->buatSiswaPeserta($tahunPelajaran, $kelas, 'Alya Putri Ramadhani', '0123456781', 1);
        $anggotaKedua = $this->buatSiswaPeserta($tahunPelajaran, $kelas, 'Fajar Maulana', '0123456782', 2);

        $this->actingAs($administrator)
            ->post(route('ujian-omr.store'), [
                ...$this->dataUjian($tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai),
                'status' => 'draft',
            ]);

        $ujianOmr = UjianOmr::where('kode', 'OMR-UJI-001')->firstOrFail();
        $jawaban = collect(range(1, 50))
            ->mapWithKeys(fn (int $nomor) => [$nomor => ['A', 'B', 'C', 'D'][($nomor - 1) % 4]])
            ->all();

        foreach ($ujianOmr->versiSoal as $versi) {
            $this->put(route('ujian-omr.kunci-jawaban.update', [$ujianOmr, $versi]), ['jawaban' => $jawaban]);
        }

        $this->put(route('ujian-omr.update', $ujianOmr), [
            ...$this->dataUjian($tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai),
            'status' => 'siap',
        ]);

        $this->post(route('ujian-omr.lembar-jawab.generate', $ujianOmr))
            ->assertRedirect(route('ujian-omr.show', $ujianOmr))
            ->assertSessionHas('berhasil');

        $lembarJawab = LembarJawabUjianOmr::query()
            ->with('versiSoalUjianOmr')
            ->where('ujian_omr_id', $ujianOmr->id)
            ->orderBy('anggota_kelas_id')
            ->get();

        $this->assertCount(2, $lembarJawab);
        $this->assertSame([$anggotaPertama->id, $anggotaKedua->id], $lembarJawab->pluck('anggota_kelas_id')->all());
        $this->assertSame(['A', 'B'], $lembarJawab->pluck('versiSoalUjianOmr.kode')->all());
        $this->assertTrue($lembarJawab->every(fn (LembarJawabUjianOmr $lembar) => preg_match('/^[0-9]{18}$/', $lembar->token) === 1));

        $tokenAwal = $lembarJawab->pluck('token', 'anggota_kelas_id');
        $this->post(route('ujian-omr.lembar-jawab.generate', $ujianOmr))
            ->assertRedirect(route('ujian-omr.show', $ujianOmr));
        $this->assertDatabaseCount('lembar_jawab_ujian_omr', 2);
        $this->assertSame($tokenAwal->all(), LembarJawabUjianOmr::pluck('token', 'anggota_kelas_id')->all());

        $this->get(route('ujian-omr.lembar-jawab.cetak', $ujianOmr))
            ->assertOk()
            ->assertSee('Lembar Jawab Komputer')
            ->assertSee('Alya Putri Ramadhani')
            ->assertSee('Fajar Maulana')
            ->assertSee('Gunakan A4 landscape dengan skala cetak 100%');
    }

    public function test_ljk_belum_dapat_dibuat_saat_ujian_masih_draft(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatSiswaPeserta($tahunPelajaran, $kelas, 'Alya Putri Ramadhani', '0123456781', 1);

        $this->actingAs($administrator)
            ->post(route('ujian-omr.store'), [
                ...$this->dataUjian($tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai),
                'status' => 'draft',
            ]);

        $ujianOmr = UjianOmr::where('kode', 'OMR-UJI-001')->firstOrFail();

        $this->from(route('ujian-omr.show', $ujianOmr))
            ->post(route('ujian-omr.lembar-jawab.generate', $ujianOmr))
            ->assertRedirect(route('ujian-omr.show', $ujianOmr))
            ->assertSessionHasErrors('ujian');

        $this->assertDatabaseCount('lembar_jawab_ujian_omr', 0);
    }

    public function test_administrator_dapat_mengunggah_pdf_dan_melihat_hasil_pembacaan_ljk(): void
    {
        Storage::fake('local');
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $anggota = $this->buatSiswaPeserta($tahunPelajaran, $kelas, 'Alya Putri Ramadhani', '0123456781', 1);
        $ujianOmr = UjianOmr::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'kode' => 'OMR-SCAN-001',
            'nama' => 'Ujian Scan Percobaan',
            'semester' => 'ganjil',
            'jumlah_soal' => 50,
            'jumlah_pilihan' => 4,
            'status' => 'siap',
        ]);
        $kelasUjian = KelasUjianOmr::create([
            'ujian_omr_id' => $ujianOmr->id,
            'kelas_id' => $kelas->id,
            'komponen_nilai_id' => $komponenNilai->id,
        ]);
        $versi = VersiSoalUjianOmr::create([
            'ujian_omr_id' => $ujianOmr->id,
            'kode' => 'A',
            'aktif' => true,
        ]);

        foreach (range(1, 50) as $nomorSoal) {
            $versi->kunciJawaban()->create([
                'nomor_soal' => $nomorSoal,
                'jawaban' => 'A',
            ]);
        }

        $lembarJawab = LembarJawabUjianOmr::create([
            'ujian_omr_id' => $ujianOmr->id,
            'kelas_ujian_omr_id' => $kelasUjian->id,
            'anggota_kelas_id' => $anggota->id,
            'versi_soal_ujian_omr_id' => $versi->id,
            'token' => '123456789012345678',
            'status' => 'siap_dicetak',
        ]);
        $jawabanMesin = collect(range(1, 50))->map(fn (int $nomor) => [
            'number' => $nomor,
            'answer' => 'A',
            'status' => 'terbaca',
            'darkness' => ['A' => 0.9, 'B' => 0.01, 'C' => 0.01, 'D' => 0.01],
        ])->all();

        $this->mock(PembacaPdfLjkOmr::class)
            ->shouldReceive('baca')
            ->once()
            ->withArgs(fn ($pdf, $pratinjau, $jumlahSoal) => str_ends_with($pdf, '.pdf')
                && str_contains($pratinjau, 'pratinjau')
                && $jumlahSoal === 50)
            ->andReturn([
                'pages' => 1,
                'sheets' => [[
                    'page' => 1,
                    'slot' => 1,
                    'token' => $lembarJawab->token,
                    'preview' => 'halaman-001-ljk-1.jpg',
                    'status' => 'terbaca',
                    'warnings' => [],
                    'answers' => $jawabanMesin,
                ]],
            ]);

        $this->actingAs($administrator)
            ->get(route('ujian-omr.scan.index', $ujianOmr))
            ->assertOk()
            ->assertSee('Proses PDF hasil scan');

        $this->actingAs($administrator)
            ->post(route('ujian-omr.scan.store', $ujianOmr), [
                'file_pdf' => UploadedFile::fake()->create('scan-kelas.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('batch_scan_ujian_omr', [
            'ujian_omr_id' => $ujianOmr->id,
            'jumlah_halaman_pdf' => 1,
            'jumlah_ljk_terdeteksi' => 1,
            'jumlah_berhasil' => 1,
            'status' => 'selesai',
        ]);
        $this->assertDatabaseHas('hasil_scan_ljk_ujian_omr', [
            'lembar_jawab_ujian_omr_id' => $lembarJawab->id,
            'jumlah_benar' => 50,
            'jumlah_salah' => 0,
            'nilai' => '100.00',
            'status' => 'terbaca',
        ]);
        $this->assertDatabaseCount('jawaban_hasil_scan_ujian_omr', 50);
        $this->assertDatabaseCount('nilai_siswa', 0);

        $batchScan = $ujianOmr->batchScanUjianOmr()->firstOrFail();
        $this->actingAs($administrator)
            ->get(route('ujian-omr.scan.show', [$ujianOmr, $batchScan]))
            ->assertOk()
            ->assertSee('Alya Putri Ramadhani')
            ->assertSee('Benar 50')
            ->assertSee('1 nilai siap diterapkan');

        $this->actingAs($administrator)
            ->post(route('ujian-omr.scan.terapkan-nilai', [$ujianOmr, $batchScan]))
            ->assertRedirect(route('ujian-omr.scan.show', [$ujianOmr, $batchScan]))
            ->assertSessionHas('berhasil');

        $this->assertDatabaseHas('nilai_siswa', [
            'komponen_nilai_id' => $komponenNilai->id,
            'siswa_id' => $anggota->siswa_id,
            'nilai' => '100.00',
        ]);
        $this->assertDatabaseHas('hasil_scan_ljk_ujian_omr', [
            'lembar_jawab_ujian_omr_id' => $lembarJawab->id,
            'nilai' => '100.00',
            'diterapkan_oleh_pengguna_id' => $administrator->id,
        ]);

        $this->actingAs($administrator)
            ->post(route('ujian-omr.scan.terapkan-nilai', [$ujianOmr, $batchScan]))
            ->assertRedirect(route('ujian-omr.scan.show', [$ujianOmr, $batchScan]))
            ->assertSessionHas('gagal');

        $this->assertDatabaseCount('nilai_siswa', 1);
    }

    public function test_administrator_dapat_mengoreksi_ljk_yang_qr_nya_tidak_terbaca(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $anggota = $this->buatSiswaPeserta($tahunPelajaran, $kelas, 'Alya Putri Ramadhani', '0123456781', 1);
        $ujianOmr = UjianOmr::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'kode' => 'OMR-KOREKSI-001',
            'nama' => 'Ujian Koreksi Percobaan',
            'semester' => 'ganjil',
            'jumlah_soal' => 4,
            'jumlah_pilihan' => 4,
            'status' => 'siap',
        ]);
        $kelasUjian = KelasUjianOmr::create([
            'ujian_omr_id' => $ujianOmr->id,
            'kelas_id' => $kelas->id,
            'komponen_nilai_id' => $komponenNilai->id,
        ]);
        $versi = VersiSoalUjianOmr::create([
            'ujian_omr_id' => $ujianOmr->id,
            'kode' => 'A',
            'aktif' => true,
        ]);

        foreach (range(1, 4) as $nomorSoal) {
            $versi->kunciJawaban()->create([
                'nomor_soal' => $nomorSoal,
                'jawaban' => 'A',
            ]);
        }

        $lembarJawab = LembarJawabUjianOmr::create([
            'ujian_omr_id' => $ujianOmr->id,
            'kelas_ujian_omr_id' => $kelasUjian->id,
            'anggota_kelas_id' => $anggota->id,
            'versi_soal_ujian_omr_id' => $versi->id,
            'token' => '123456789012345678',
            'status' => 'siap_dicetak',
        ]);
        $batchScan = BatchScanUjianOmr::create([
            'ujian_omr_id' => $ujianOmr->id,
            'nama_file_asli' => 'scan-koreksi.pdf',
            'lokasi_file' => 'omr/scan-koreksi.pdf',
            'jumlah_halaman_pdf' => 1,
            'jumlah_ljk_terdeteksi' => 1,
            'jumlah_berhasil' => 0,
            'jumlah_perlu_diperiksa' => 1,
            'status' => 'selesai',
        ]);
        $hasilScan = HasilScanLjkUjianOmr::create([
            'batch_scan_ujian_omr_id' => $batchScan->id,
            'halaman_pdf' => 1,
            'urutan_ljk' => 1,
            'token_terbaca' => null,
            'lokasi_pratinjau' => 'omr/pratinjau-koreksi.jpg',
            'status' => 'token_tidak_dikenali',
            'jumlah_benar' => 0,
            'jumlah_salah' => 0,
            'jumlah_kosong' => 1,
            'jumlah_ganda' => 1,
            'catatan' => 'Token QR tidak ditemukan pada daftar LJK ujian ini.',
        ]);

        foreach (range(1, 4) as $nomorSoal) {
            $hasilScan->jawaban()->create([
                'nomor_soal' => $nomorSoal,
                'jawaban' => $nomorSoal === 1 ? 'A' : null,
                'status' => $nomorSoal === 2 ? 'ganda' : ($nomorSoal === 1 ? 'terbaca' : 'kosong'),
            ]);
        }

        $this->actingAs($administrator)
            ->get(route('ujian-omr.scan.hasil.periksa', [$ujianOmr, $batchScan, $hasilScan]))
            ->assertOk()
            ->assertSee('Periksa hasil LJK')
            ->assertSee('Alya Putri Ramadhani');

        $this->actingAs($administrator)
            ->put(route('ujian-omr.scan.hasil.koreksi', [$ujianOmr, $batchScan, $hasilScan]), [
                'lembar_jawab_ujian_omr_id' => $lembarJawab->id,
                'jawaban' => [
                    1 => 'A',
                    2 => 'A',
                    3 => 'B',
                    4 => null,
                ],
                'catatan_koreksi' => 'Identitas dicocokkan dari nama siswa pada LJK.',
            ])
            ->assertRedirect(route('ujian-omr.scan.show', [$ujianOmr, $batchScan]))
            ->assertSessionHas('berhasil');

        $this->assertDatabaseHas('hasil_scan_ljk_ujian_omr', [
            'id' => $hasilScan->id,
            'lembar_jawab_ujian_omr_id' => $lembarJawab->id,
            'status' => 'terbaca',
            'jumlah_benar' => 2,
            'jumlah_salah' => 1,
            'jumlah_kosong' => 1,
            'jumlah_ganda' => 0,
            'nilai' => '50.00',
            'dikoreksi_oleh_pengguna_id' => $administrator->id,
        ]);
        $this->assertDatabaseHas('jawaban_hasil_scan_ujian_omr', [
            'hasil_scan_ljk_ujian_omr_id' => $hasilScan->id,
            'nomor_soal' => 4,
            'jawaban' => null,
            'status' => 'kosong_dikonfirmasi',
        ]);
        $this->assertDatabaseHas('batch_scan_ujian_omr', [
            'id' => $batchScan->id,
            'jumlah_berhasil' => 1,
            'jumlah_perlu_diperiksa' => 0,
        ]);

        $this->actingAs($administrator)
            ->post(route('ujian-omr.scan.terapkan-nilai', [$ujianOmr, $batchScan]))
            ->assertRedirect(route('ujian-omr.scan.show', [$ujianOmr, $batchScan]))
            ->assertSessionHas('berhasil');

        $this->assertDatabaseHas('nilai_siswa', [
            'komponen_nilai_id' => $komponenNilai->id,
            'siswa_id' => $anggota->siswa_id,
            'nilai' => '50.00',
        ]);
    }

    private function buatDataAkademik(string $jenisKomponen = 'sts'): array
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
        $guruMataPelajaran = GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $pegawai->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
        $komponenNilai = KomponenNilai::create([
            'guru_mata_pelajaran_id' => $guruMataPelajaran->id,
            'semester' => 'ganjil',
            'jenis_komponen' => $jenisKomponen,
            'nama' => $jenisKomponen === 'sts' ? 'STS Semester Ganjil' : 'Sumatif Bab 1',
            'aktif' => true,
        ]);

        return [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai];
    }

    private function dataUjian(TahunPelajaran $tahunPelajaran, MataPelajaran $mataPelajaran, Kelas $kelas, KomponenNilai $komponenNilai): array
    {
        return [
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'kode' => 'OMR-UJI-001',
            'nama' => 'Ujian Percobaan OMR',
            'semester' => 'ganjil',
            'tanggal_ujian' => '2026-08-15',
            'jumlah_soal' => 50,
            'jumlah_pilihan' => 4,
            'kode_versi' => 'A, B',
            'kelas_peserta' => [
                $kelas->id => $komponenNilai->id,
            ],
        ];
    }

    private function buatSiswaPeserta(TahunPelajaran $tahunPelajaran, Kelas $kelas, string $nama, string $nisn, int $nomorAbsen): AnggotaKelas
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nisn' => $nisn,
            'aktif' => true,
        ]);

        return AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $nomorAbsen,
            'status_keanggotaan' => 'aktif',
        ]);
    }
}
