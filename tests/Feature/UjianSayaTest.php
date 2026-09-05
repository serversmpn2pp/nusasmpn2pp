<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\JadwalUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\MataPelajaran;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Models\Siswa;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UjianSayaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_siswa_hanya_melihat_ujian_yang_terhubung_dengan_dirinya(): void
    {
        Carbon::setTestNow('2026-12-01 08:00:00');

        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelasA = $this->buatKelas($tahun, 'VIII.A');
        $kelasB = $this->buatKelas($tahun, 'VIII.B');
        [$siswa, $anggota, $akun] = $this->buatSiswaBerakun($kelasA, 'Siswa Pemilik Ujian', '0131201150');
        [, $anggotaLain] = $this->buatSiswaBerakun($kelasB, 'Siswa Rahasia Kelas Lain', '0131201151');
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'MTK8',
            'nama' => 'Matematika',
            'tingkat' => 8,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $jenis = JenisUjianCbt::firstOrCreate(
            ['kode' => 'STS'],
            [
                'nama' => 'Sumatif Tengah Semester',
                'memerlukan_token' => true,
                'dapat_diterapkan_ke_nilai' => true,
                'urutan' => 1,
                'aktif' => true,
            ],
        );
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kode' => 'STS-GANJIL-2627',
            'nama' => 'STS Semester Ganjil',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-12-01',
            'tanggal_selesai' => '2026-12-05',
            'status' => 'aktif',
        ]);

        $ujianAktif = $this->buatUjian(
            $jenis,
            $tahun,
            $mataPelajaran,
            'CBT-MTK-AKTIF',
            'Matematika Aktif',
            '2026-12-01 07:30:00',
            '2026-12-01 09:00:00',
            'berlangsung',
        );
        $kelasUjian = KelasUjianCbt::create([
            'ujian_cbt_id' => $ujianAktif->id,
            'kelas_id' => $kelasA->id,
        ]);
        $jadwal = JadwalUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'ujian_cbt_id' => $ujianAktif->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tanggal' => '2026-12-01',
            'waktu_mulai' => '07:30',
            'waktu_selesai' => '09:00',
            'label_sesi' => 'Sesi Pagi',
            'tingkat' => 8,
            'urutan' => 1,
            'status' => 'siap',
        ]);
        $jadwal->kelas()->attach($kelasA->id);
        $ruang = RuangUjianCbt::create([
            'ujian_cbt_id' => $ujianAktif->id,
            'jadwal_ujian_cbt_id' => $jadwal->id,
            'kode' => 'R-01',
            'nama' => 'Ruang 01',
            'kapasitas' => 22,
            'status' => 'siap',
        ]);
        $this->buatPeserta($ujianAktif, $kelasUjian, $anggota, 'AKTIF-001', ruang: $ruang, nomorMeja: 7);

        $ujianAkanDatang = $this->buatUjian(
            $jenis,
            $tahun,
            $mataPelajaran,
            'CBT-MTK-AKAN-DATANG',
            'Matematika Akan Datang',
            '2026-12-02 07:30:00',
            '2026-12-02 09:00:00',
            'terjadwal',
        );
        $kelasUjianAkanDatang = KelasUjianCbt::create([
            'ujian_cbt_id' => $ujianAkanDatang->id,
            'kelas_id' => $kelasA->id,
        ]);
        $this->buatPeserta($ujianAkanDatang, $kelasUjianAkanDatang, $anggota, 'AKAN-001');

        $ujianSelesai = $this->buatUjian(
            $jenis,
            $tahun,
            $mataPelajaran,
            'CBT-MTK-SELESAI',
            'Matematika Selesai',
            '2026-11-20 07:30:00',
            '2026-11-20 09:00:00',
            'selesai',
        );
        $kelasUjianSelesai = KelasUjianCbt::create([
            'ujian_cbt_id' => $ujianSelesai->id,
            'kelas_id' => $kelasA->id,
        ]);
        $this->buatPeserta($ujianSelesai, $kelasUjianSelesai, $anggota, 'SELESAI-001', status: 'selesai');

        $ujianMilikSiswaLain = $this->buatUjian(
            $jenis,
            $tahun,
            $mataPelajaran,
            'CBT-RAHASIA',
            'Ujian Rahasia Siswa Lain',
            '2026-12-01 07:30:00',
            '2026-12-01 09:00:00',
            'berlangsung',
        );
        $kelasUjianLain = KelasUjianCbt::create([
            'ujian_cbt_id' => $ujianMilikSiswaLain->id,
            'kelas_id' => $kelasB->id,
        ]);
        $this->buatPeserta($ujianMilikSiswaLain, $kelasUjianLain, $anggotaLain, 'RAHASIA-001');

        $this->actingAs($akun)
            ->get(route('ujian-saya.index'))
            ->assertOk()
            ->assertViewIs('ujian-saya.index')
            ->assertViewHas('siswa', fn (Siswa $nilai) => $nilai->is($siswa))
            ->assertViewHas('ringkasanUjian', fn (array $nilai) => $nilai === [
                'aktif' => 1,
                'akan_datang' => 1,
                'selesai' => 1,
                'total' => 3,
            ])
            ->assertViewHas('daftarUjian', fn ($nilai) => $nilai->pluck('ujian.id')->all() === [
                $ujianAktif->id,
                $ujianAkanDatang->id,
                $ujianSelesai->id,
            ])
            ->assertSee('Ujian Saya')
            ->assertSee('Matematika Akan Datang')
            ->assertSee('Matematika Selesai')
            ->assertSee('Ruang 01')
            ->assertSee('Kode meja')
            ->assertSee('Sesi Pagi')
            ->assertSee('Token dari pengawas')
            ->assertSee('Masuk Ujian')
            ->assertDontSee('Ujian Rahasia Siswa Lain')
            ->assertDontSee('Siswa Rahasia Kelas Lain');
    }

    public function test_siswa_masuk_dan_melanjutkan_ujian_dari_akun_nusa_dengan_token_pengawas(): void
    {
        Carbon::setTestNow('2026-12-01 08:00:00');

        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelasA = $this->buatKelas($tahun, 'VIII.A');
        $kelasB = $this->buatKelas($tahun, 'VIII.B');
        [$siswa, $anggota, $akun] = $this->buatSiswaBerakun($kelasA, 'Siswa Pemilik Ujian', '0131201150');
        [, $anggotaLain] = $this->buatSiswaBerakun($kelasB, 'Siswa Kelas Lain', '0131201151');
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'IPA8',
            'nama' => 'Ilmu Pengetahuan Alam',
            'tingkat' => 8,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $jenis = JenisUjianCbt::firstOrCreate(
            ['kode' => 'STS'],
            [
                'nama' => 'Sumatif Tengah Semester',
                'memerlukan_token' => true,
                'dapat_diterapkan_ke_nilai' => true,
                'urutan' => 1,
                'aktif' => true,
            ],
        );
        $ujian = $this->buatUjian(
            $jenis,
            $tahun,
            $mataPelajaran,
            'CBT-IPA-AKSES',
            'STS IPA Semester Ganjil',
            '2026-12-01 07:30:00',
            '2026-12-01 09:30:00',
            'berlangsung',
        );
        $ujian->update(['token' => 'MASUK1', 'jumlah_soal' => 2]);
        $kelasUjianA = KelasUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_id' => $kelasA->id,
        ]);
        $kelasUjianB = KelasUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_id' => $kelasB->id,
        ]);
        $peserta = $this->buatPeserta($ujian, $kelasUjianA, $anggota, 'AKSES-001');
        $pesertaLain = $this->buatPeserta($ujian, $kelasUjianB, $anggotaLain, 'AKSES-002');
        $soal = SoalCbt::create([
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 8,
            'kode' => 'IPA-AKSES-001',
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'umum',
            'pertanyaan' => 'Organ pernapasan utama manusia adalah ....',
            'opsi' => [
                ['kode' => 'A', 'teks' => 'Paru-paru'],
                ['kode' => 'B', 'teks' => 'Lambung'],
                ['kode' => 'C', 'teks' => 'Ginjal'],
                ['kode' => 'D', 'teks' => 'Usus'],
            ],
            'kunci_jawaban' => ['A'],
            'skor_maksimal' => 1,
            'status' => 'siap',
            'aktif' => true,
        ]);
        $relasiSoalPertama = $ujian->soalUjianCbt()->create([
            'soal_cbt_id' => $soal->id,
            'nomor_urut' => 1,
            'bobot' => 1,
        ]);
        $soalKedua = SoalCbt::create([
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 8,
            'kode' => 'IPA-AKSES-002',
            'jenis_soal' => 'isian_singkat',
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'umum',
            'pertanyaan' => 'Gas yang dibutuhkan manusia untuk bernapas adalah ....',
            'kunci_jawaban' => ['oksigen'],
            'skor_maksimal' => 1,
            'status' => 'siap',
            'aktif' => true,
        ]);
        $relasiSoalKedua = $ujian->soalUjianCbt()->create([
            'soal_cbt_id' => $soalKedua->id,
            'nomor_urut' => 2,
            'bobot' => 1,
        ]);

        $this->actingAs($akun)
            ->post(route('ujian-saya.masuk', $peserta), ['token' => 'SALAH'])
            ->assertSessionHasErrors('token')
            ->assertSessionMissing('cbt_peserta_ujian_id');

        $this->actingAs($akun)
            ->post(route('ujian-saya.masuk', $pesertaLain), ['token' => 'MASUK1'])
            ->assertNotFound();

        $this->actingAs($akun)
            ->post(route('ujian-saya.masuk', $peserta), ['token' => 'masuk1'])
            ->assertRedirect(route('cbt.ujian.show'))
            ->assertSessionHas('cbt_peserta_ujian_id', $peserta->id)
            ->assertSessionHas('cbt_pengguna_id', $akun->id);

        $this->get(route('cbt.ujian.show'))
            ->assertOk()
            ->assertSee($siswa->nama_lengkap)
            ->assertSee($siswa->nisn)
            ->assertSee('Mulai ujian')
            ->assertSee('Kembali ke Ujian Saya');

        $this->post(route('cbt.ujian.mulai'))
            ->assertRedirect(route('cbt.ujian.kerjakan'));

        $peserta->refresh();
        $this->assertSame('sedang_mengerjakan', $peserta->status);
        $this->assertNotNull($peserta->waktu_mulai);

        $this->post(route('cbt.logout'))
            ->assertRedirect(route('ujian-saya.index'))
            ->assertSessionMissing('cbt_peserta_ujian_id');

        $this->actingAs($akun)
            ->post(route('ujian-saya.masuk', $peserta))
            ->assertRedirect(route('cbt.ujian.show'));

        $this->get(route('cbt.ujian.kerjakan'))
            ->assertOk()
            ->assertSee('Organ pernapasan utama manusia adalah')
            ->assertSee('Gas yang dibutuhkan manusia untuk bernapas')
            ->assertSee('Soal 1 dari 2')
            ->assertSee('Jawaban disimpan otomatis')
            ->assertSee('Sisa waktu')
            ->assertSee('Selesai Ujian');

        $this->postJson(route('cbt.ujian.jawaban'), [
            'soal_ujian_cbt_id' => $relasiSoalPertama->id,
            'jawaban' => ['A'],
            'ragu' => false,
        ])->assertOk()
            ->assertJson([
                'message' => 'Jawaban tersimpan.',
                'terjawab' => true,
                'ragu' => false,
            ]);

        $this->postJson(route('cbt.ujian.jawaban'), [
            'soal_ujian_cbt_id' => $relasiSoalKedua->id,
            'jawaban' => ['oksigen'],
            'ragu' => true,
        ])->assertOk()
            ->assertJson([
                'terjawab' => true,
                'ragu' => true,
            ]);

        $jawabanTersimpan = $peserta->jawabanPesertaUjianCbt()
            ->get()
            ->keyBy('soal_ujian_cbt_id');
        $this->assertSame(['A'], $jawabanTersimpan[$relasiSoalPertama->id]->jawaban);
        $this->assertSame(['oksigen'], $jawabanTersimpan[$relasiSoalKedua->id]->jawaban);
        $this->assertTrue($jawabanTersimpan[$relasiSoalKedua->id]->ragu);

        $this->postJson(route('cbt.ujian.jawaban'), [
            'soal_ujian_cbt_id' => $relasiSoalPertama->id,
            'jawaban' => ['B'],
            'ragu' => false,
        ])->assertOk();

        $this->assertSame(
            ['oksigen'],
            $peserta->jawabanPesertaUjianCbt()
                ->where('soal_ujian_cbt_id', $relasiSoalKedua->id)
                ->firstOrFail()
                ->jawaban,
        );

        $this->postJson(route('cbt.ujian.jawaban'), [
            'soal_ujian_cbt_id' => 999999,
            'jawaban' => ['A'],
        ])->assertNotFound();
    }

    public function test_akun_bukan_siswa_tidak_dapat_membuka_ujian_saya(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('ujian-saya.index'))
            ->assertForbidden();
    }

    private function buatKelas(TahunPelajaran $tahun, string $nama): Kelas
    {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => $nama,
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
    }

    private function buatSiswaBerakun(Kelas $kelas, string $nama, string $nisn): array
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => '26'.$nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        $akun = Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $nama,
            'username' => $nisn,
            'kata_sandi' => Hash::make('rahasia'),
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        return [$siswa, $anggota, $akun];
    }

    private function buatUjian(
        JenisUjianCbt $jenis,
        TahunPelajaran $tahun,
        MataPelajaran $mataPelajaran,
        string $kode,
        string $nama,
        string $mulai,
        string $selesai,
        string $status,
    ): UjianCbt {
        return UjianCbt::create([
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'kode' => $kode,
            'nama' => $nama,
            'semester' => 'ganjil',
            'tingkat' => 8,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'durasi_menit' => 90,
            'jumlah_soal' => 40,
            'token' => substr(hash('sha256', $kode), 0, 6),
            'status' => $status,
        ]);
    }

    private function buatPeserta(
        UjianCbt $ujian,
        KelasUjianCbt $kelasUjian,
        AnggotaKelas $anggota,
        string $kode,
        string $status = 'aktif',
        ?RuangUjianCbt $ruang = null,
        ?int $nomorMeja = null,
    ): PesertaUjianCbt {
        return PesertaUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_ujian_cbt_id' => $kelasUjian->id,
            'ruang_ujian_cbt_id' => $ruang?->id,
            'nomor_meja' => $nomorMeja,
            'anggota_kelas_id' => $anggota->id,
            'nomor_peserta' => 'NP-'.$kode,
            'status' => $status,
            'menit_tersisa' => 90,
        ]);
    }
}
