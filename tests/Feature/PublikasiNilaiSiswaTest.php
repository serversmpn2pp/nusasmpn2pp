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
use App\Models\PertanyaanSurveiPembelajaran;
use App\Models\PublikasiNilaiSiswa;
use App\Models\Siswa;
use App\Models\SkemaBobotNilai;
use App\Models\SurveiPembelajaran;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublikasiNilaiSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_hanya_dapat_mempublikasikan_nilai_dalam_penugasannya(): void
    {
        $data = $this->dataDasar();
        [, $akunTanpaNilai] = $this->buatSiswaBerakun(
            $data['tahun'],
            $data['kelas'],
            'Siswa Tanpa Nilai',
            '0011223366',
            3,
        );

        $this->actingAs($data['akun_guru_lain'])
            ->patch(route('publikasi-nilai.publikasikan', [
                $data['guru_mapel'],
                'ganjil',
            ]), [
                'komponen_nilai_id' => $data['komponen']['formatif']->id,
            ])
            ->assertForbidden();

        $this->actingAs($data['akun_guru'])
            ->patch(route('publikasi-nilai.publikasikan', [
                $data['guru_mapel'],
                'ganjil',
            ]), [
                'komponen_nilai_id' => $data['komponen']['formatif']->id,
            ])
            ->assertRedirect(route('input-nilai.index', [
                'komponen_nilai_id' => $data['komponen']['formatif']->id,
            ]));

        $this->assertDatabaseHas('publikasi_nilai_siswa', [
            'guru_mata_pelajaran_id' => $data['guru_mapel']->id,
            'semester' => 'ganjil',
            'dipublikasikan' => true,
            'dipublikasikan_oleh_pengguna_id' => $data['akun_guru']->id,
        ]);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $data['akun_siswa']->id,
            'judul' => 'Nilai Matematika telah tersedia',
            'tautan' => route('nilai-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
            ], false).'#mapel-'.$data['guru_mapel']->id,
        ]);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $data['akun_siswa_lain']->id,
            'judul' => 'Nilai Matematika telah tersedia',
        ]);
        $this->assertDatabaseMissing('notifikasi_pengguna', [
            'pengguna_id' => $akunTanpaNilai->id,
            'judul' => 'Nilai Matematika telah tersedia',
        ]);

        $this->actingAs($data['akun_guru'])
            ->patch(route('publikasi-nilai.publikasikan', [
                $data['guru_mapel'],
                'ganjil',
            ]), [
                'komponen_nilai_id' => $data['komponen']['formatif']->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('notifikasi_pengguna', 2);

        $this->actingAs($data['akun_guru'])
            ->get(route('input-nilai.index', [
                'komponen_nilai_id' => $data['komponen_lain']->id,
            ]))
            ->assertNotFound();
    }

    public function test_siswa_hanya_melihat_nilai_miliknya_yang_sudah_dipublikasikan(): void
    {
        $data = $this->dataDasar();
        TahunPelajaran::create([
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'aktif' => false,
        ]);
        PublikasiNilaiSiswa::create([
            'guru_mata_pelajaran_id' => $data['guru_mapel']->id,
            'semester' => 'ganjil',
            'dipublikasikan' => true,
            'dipublikasikan_pada' => now(),
            'dipublikasikan_oleh_pengguna_id' => $data['akun_guru']->id,
        ]);
        $this->buatSurvei($data['guru_mapel'], $data['siswa']);
        $this->buatSurvei($data['guru_mapel'], $data['siswa_lain']);

        $response = $this->actingAs($data['akun_siswa'])
            ->get(route('nilai-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
            ]));

        $response
            ->assertOk()
            ->assertViewIs('nilai-saya.index')
            ->assertSee('Matematika')
            ->assertSee('79,00')
            ->assertSee('Tuntas')
            ->assertSee('Formatif 1')
            ->assertSee('80,00')
            ->assertDontSee('Ilmu Pengetahuan Alam')
            ->assertDontSee('2025/2026')
            ->assertDontSee('23,00')
            ->assertDontSee($data['siswa_lain']->nama_lengkap);

        $this->actingAs($data['akun_siswa_lain'])
            ->get(route('nilai-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertSee('23,00')
            ->assertDontSee('80,00');
    }

    public function test_nilai_baru_terbuka_setelah_siswa_mengisi_survei_pembelajaran(): void
    {
        $data = $this->dataDasar();
        PublikasiNilaiSiswa::create([
            'guru_mata_pelajaran_id' => $data['guru_mapel']->id,
            'semester' => 'ganjil',
            'dipublikasikan' => true,
            'dipublikasikan_pada' => now(),
            'dipublikasikan_oleh_pengguna_id' => $data['akun_guru']->id,
        ]);

        $this->actingAs($data['akun_siswa'])
            ->get(route('nilai-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertSee('Nilai terkunci')
            ->assertSee('Isi survei')
            ->assertDontSee('79,00')
            ->assertDontSee('80,00');

        $this->actingAs($data['akun_siswa'])
            ->get(route('survei-pembelajaran.create', [$data['guru_mapel'], 'ganjil']))
            ->assertOk()
            ->assertSee('Survei Pembelajaran')
            ->assertSee('Guru menjelaskan materi dengan jelas dan mudah dipahami.');

        $this->actingAs($data['akun_siswa'])
            ->from(route('survei-pembelajaran.create', [$data['guru_mapel'], 'ganjil']))
            ->post(route('survei-pembelajaran.store', [$data['guru_mapel'], 'ganjil']), [
                'jawaban' => ['kejelasan_materi' => 4],
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('survei_pembelajaran', [
            'guru_mata_pelajaran_id' => $data['guru_mapel']->id,
            'siswa_id' => $data['siswa']->id,
            'semester' => 'ganjil',
        ]);

        $jawaban = PertanyaanSurveiPembelajaran::aktif()
            ->pluck('kode')
            ->mapWithKeys(fn (string $kode) => [$kode => 4])
            ->all();

        $this->actingAs($data['akun_siswa'])
            ->post(route('survei-pembelajaran.store', [$data['guru_mapel'], 'ganjil']), [
                'jawaban' => $jawaban,
                'saran' => 'Pembelajaran sudah baik.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('nilai-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
            ]).'#mapel-'.$data['guru_mapel']->id);

        $this->assertDatabaseHas('survei_pembelajaran', [
            'guru_mata_pelajaran_id' => $data['guru_mapel']->id,
            'siswa_id' => $data['siswa']->id,
            'semester' => 'ganjil',
            'saran' => 'Pembelajaran sudah baik.',
        ]);

        $this->actingAs($data['akun_siswa'])
            ->get(route('nilai-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertSee('79,00')
            ->assertSee('Tuntas')
            ->assertDontSee('Nilai terkunci');
    }

    public function test_survei_hanya_dapat_diisi_oleh_siswa_untuk_nilai_yang_sudah_dipublikasikan(): void
    {
        $data = $this->dataDasar();

        $this->actingAs($data['akun_siswa'])
            ->get(route('survei-pembelajaran.create', [$data['guru_mapel'], 'ganjil']))
            ->assertNotFound();

        $this->actingAs($data['akun_guru'])
            ->get(route('survei-pembelajaran.create', [$data['guru_mapel'], 'ganjil']))
            ->assertForbidden();
    }

    public function test_survei_memakai_pernyataan_aktif_dan_menyimpan_teks_saat_diisi(): void
    {
        $data = $this->dataDasar();
        PublikasiNilaiSiswa::create([
            'guru_mata_pelajaran_id' => $data['guru_mapel']->id,
            'semester' => 'ganjil',
            'dipublikasikan' => true,
            'dipublikasikan_pada' => now(),
            'dipublikasikan_oleh_pengguna_id' => $data['akun_guru']->id,
        ]);
        $pertanyaanBaru = PertanyaanSurveiPembelajaran::create([
            'kode' => 'media_pembelajaran_uji',
            'pernyataan' => 'Guru menggunakan media pembelajaran yang membantu pemahaman saya.',
            'urutan' => 7,
            'aktif' => true,
        ]);
        $pertanyaanNonaktif = PertanyaanSurveiPembelajaran::create([
            'kode' => 'pertanyaan_nonaktif_uji',
            'pernyataan' => 'Pernyataan ini tidak boleh tampil.',
            'urutan' => 8,
            'aktif' => false,
        ]);

        $this->actingAs($data['akun_siswa'])
            ->get(route('survei-pembelajaran.create', [$data['guru_mapel'], 'ganjil']))
            ->assertOk()
            ->assertSee($pertanyaanBaru->pernyataan)
            ->assertDontSee($pertanyaanNonaktif->pernyataan);

        $jawaban = PertanyaanSurveiPembelajaran::aktif()
            ->pluck('kode')
            ->mapWithKeys(fn (string $kode) => [$kode => 5])
            ->all();

        $this->actingAs($data['akun_siswa'])
            ->post(route('survei-pembelajaran.store', [$data['guru_mapel'], 'ganjil']), [
                'jawaban' => $jawaban,
            ])
            ->assertSessionHasNoErrors();

        $survei = SurveiPembelajaran::where('siswa_id', $data['siswa']->id)->firstOrFail();
        $teksSaatDiisi = $survei->snapshot_pertanyaan[$pertanyaanBaru->kode]['pernyataan'];
        $pertanyaanBaru->update(['pernyataan' => 'Teks pernyataan telah diubah.']);

        $this->assertSame(
            'Guru menggunakan media pembelajaran yang membantu pemahaman saya.',
            $teksSaatDiisi,
        );
        $this->assertSame(
            'Guru menggunakan media pembelajaran yang membantu pemahaman saya.',
            $survei->fresh()->snapshot_pertanyaan[$pertanyaanBaru->kode]['pernyataan'],
        );
    }

    public function test_perubahan_nilai_setelah_publikasi_otomatis_mengembalikan_nilai_menjadi_draf(): void
    {
        $data = $this->dataDasar();
        $publikasi = PublikasiNilaiSiswa::create([
            'guru_mata_pelajaran_id' => $data['guru_mapel']->id,
            'semester' => 'ganjil',
            'dipublikasikan' => true,
            'dipublikasikan_pada' => now(),
            'dipublikasikan_oleh_pengguna_id' => $data['akun_guru']->id,
        ]);

        $this->actingAs($data['akun_guru'])
            ->post(route('input-nilai.store'), [
                'komponen_nilai_id' => $data['komponen']['formatif']->id,
                'nilai' => [
                    $data['siswa']->id => 85,
                    $data['siswa_lain']->id => 40,
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('berhasil', fn (string $pesan) => str_contains($pesan, 'kembali menjadi draf'));

        $this->assertFalse($publikasi->fresh()->dipublikasikan);
        $this->assertNull($publikasi->fresh()->dipublikasikan_pada);

        $this->actingAs($data['akun_siswa'])
            ->get(route('nilai-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertSee('Belum ada nilai yang dipublikasikan')
            ->assertDontSee('Matematika');
    }

    public function test_nilai_tidak_dapat_dipublikasikan_jika_belum_ada_isinya(): void
    {
        $data = $this->dataDasar();
        NilaiSiswa::query()
            ->whereHas('komponenNilai', fn ($query) => $query->where(
                'guru_mata_pelajaran_id',
                $data['guru_mapel']->id,
            ))
            ->delete();

        $this->actingAs($data['akun_guru'])
            ->from(route('input-nilai.index', [
                'komponen_nilai_id' => $data['komponen']['formatif']->id,
            ]))
            ->patch(route('publikasi-nilai.publikasikan', [
                $data['guru_mapel'],
                'ganjil',
            ]), [
                'komponen_nilai_id' => $data['komponen']['formatif']->id,
            ])
            ->assertSessionHasErrors('publikasi');

        $this->assertDatabaseMissing('publikasi_nilai_siswa', [
            'guru_mata_pelajaran_id' => $data['guru_mapel']->id,
            'semester' => 'ganjil',
            'dipublikasikan' => true,
        ]);
    }

    public function test_guru_mendapat_notifikasi_satu_kali_saat_survei_mencapai_lima_responden(): void
    {
        $data = $this->dataDasar();
        [$siswaKetiga] = $this->buatSiswaBerakun($data['tahun'], $data['kelas'], 'Siswa Survei 3', '0011223367', 3);
        [$siswaKeempat] = $this->buatSiswaBerakun($data['tahun'], $data['kelas'], 'Siswa Survei 4', '0011223368', 4);
        [, $akunKelima] = $this->buatSiswaBerakun($data['tahun'], $data['kelas'], 'Siswa Survei 5', '0011223369', 5);
        [, $akunKeenam] = $this->buatSiswaBerakun($data['tahun'], $data['kelas'], 'Siswa Survei 6', '0011223370', 6);

        PublikasiNilaiSiswa::create([
            'guru_mata_pelajaran_id' => $data['guru_mapel']->id,
            'semester' => 'ganjil',
            'dipublikasikan' => true,
            'dipublikasikan_pada' => now(),
            'dipublikasikan_oleh_pengguna_id' => $data['akun_guru']->id,
        ]);

        collect([$data['siswa'], $data['siswa_lain'], $siswaKetiga, $siswaKeempat])
            ->each(fn (Siswa $siswa) => $this->buatSurvei($data['guru_mapel'], $siswa));
        $jawaban = PertanyaanSurveiPembelajaran::aktif()
            ->pluck('kode')
            ->mapWithKeys(fn (string $kode) => [$kode => 4])
            ->all();

        $this->actingAs($akunKelima)
            ->post(route('survei-pembelajaran.store', [$data['guru_mapel'], 'ganjil']), [
                'jawaban' => $jawaban,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $data['akun_guru']->id,
            'judul' => 'Hasil survei sudah dapat dibuka',
            'kunci_unik' => 'hasil-survei-terbuka:'.$data['guru_mapel']->id.':ganjil',
            'tautan' => route('hasil-survei-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
                'guru_mata_pelajaran_id' => $data['guru_mapel']->id,
            ], false).'#rincian-survei',
        ]);

        $this->actingAs($akunKeenam)
            ->post(route('survei-pembelajaran.store', [$data['guru_mapel'], 'ganjil']), [
                'jawaban' => $jawaban,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(6, SurveiPembelajaran::where('guru_mata_pelajaran_id', $data['guru_mapel']->id)->count());
        $this->assertDatabaseCount('notifikasi_pengguna', 1);
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $pegawai = $this->buatPegawai('Guru Matematika', '198001012006041001');
        $pegawaiLain = $this->buatPegawai('Guru IPA', '198001012006041002');
        $akunGuru = $this->buatAkunPegawai($pegawai, 'guru-matematika');
        $akunGuruLain = $this->buatAkunPegawai($pegawaiLain, 'guru-ipa');
        $matematika = MataPelajaran::create([
            'kode' => 'MTK',
            'nama' => 'Matematika',
            'kelompok' => 'Pelajaran Umum',
            'tingkat' => 7,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $ipa = MataPelajaran::create([
            'kode' => 'IPA',
            'nama' => 'Ilmu Pengetahuan Alam',
            'kelompok' => 'Pelajaran Umum',
            'tingkat' => 7,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $guruMapel = $this->buatGuruMapel($tahun, $kelas, $matematika, $pegawai);
        $guruMapelLain = $this->buatGuruMapel($tahun, $kelas, $ipa, $pegawaiLain);
        $komponen = collect([
            'formatif' => $this->buatKomponen($guruMapel, 'formatif', 'Formatif 1'),
            'sumatif' => $this->buatKomponen($guruMapel, 'sumatif', 'Sumatif 1'),
            'sts' => $this->buatKomponen($guruMapel, 'sts', 'STS'),
            'sas_saj' => $this->buatKomponen($guruMapel, 'sas_saj', 'SAS'),
        ]);
        $komponenLain = $this->buatKomponen($guruMapelLain, 'formatif', 'Formatif IPA');
        [$siswa, $akunSiswa] = $this->buatSiswaBerakun($tahun, $kelas, 'Siswa Nilai', '0011223344', 1);
        [$siswaLain, $akunSiswaLain] = $this->buatSiswaBerakun($tahun, $kelas, 'Siswa Nilai Lain', '0011223355', 2);

        $nilaiSiswa = [
            'formatif' => 80,
            'sumatif' => 70,
            'sts' => 90,
            'sas_saj' => 80,
        ];
        foreach ($komponen as $kode => $item) {
            NilaiSiswa::create([
                'komponen_nilai_id' => $item->id,
                'siswa_id' => $siswa->id,
                'nilai' => $nilaiSiswa[$kode],
            ]);
            NilaiSiswa::create([
                'komponen_nilai_id' => $item->id,
                'siswa_id' => $siswaLain->id,
                'nilai' => $kode === 'formatif' ? 23 : 70,
            ]);
        }
        NilaiSiswa::create([
            'komponen_nilai_id' => $komponenLain->id,
            'siswa_id' => $siswa->id,
            'nilai' => 95,
        ]);
        SkemaBobotNilai::create([
            'tahun_pelajaran_id' => $tahun->id,
            'semester' => 'ganjil',
            'tingkat' => 7,
            'bobot_formatif' => 35,
            'bobot_sumatif' => 25,
            'bobot_sts' => 15,
            'bobot_sas_saj' => 25,
            'aktif' => true,
        ]);

        return compact(
            'tahun',
            'kelas',
            'akunGuru',
            'akunGuruLain',
            'guruMapel',
            'guruMapelLain',
            'komponen',
            'komponenLain',
            'siswa',
            'siswaLain',
            'akunSiswa',
            'akunSiswaLain',
        ) + [
            'akun_guru' => $akunGuru,
            'akun_guru_lain' => $akunGuruLain,
            'guru_mapel' => $guruMapel,
            'guru_mapel_lain' => $guruMapelLain,
            'komponen_lain' => $komponenLain,
            'akun_siswa' => $akunSiswa,
            'akun_siswa_lain' => $akunSiswaLain,
            'siswa_lain' => $siswaLain,
        ];
    }

    private function buatPegawai(string $nama, string $nip): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
    }

    private function buatAkunPegawai(Pegawai $pegawai, string $username): Pengguna
    {
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $username,
            'kata_sandi' => Hash::make('rahasia'),
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $akun->daftarPeran()->sync([Peran::where('kode', 'guru_mapel')->value('id')]);

        return $akun;
    }

    private function buatGuruMapel(
        TahunPelajaran $tahun,
        Kelas $kelas,
        MataPelajaran $mataPelajaran,
        Pegawai $pegawai,
    ): GuruMataPelajaran {
        return GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $pegawai->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
    }

    private function buatKomponen(
        GuruMataPelajaran $guruMapel,
        string $jenis,
        string $nama,
    ): KomponenNilai {
        return KomponenNilai::create([
            'guru_mata_pelajaran_id' => $guruMapel->id,
            'semester' => 'ganjil',
            'jenis_komponen' => $jenis,
            'nama' => $nama,
            'aktif' => true,
        ]);
    }

    private function buatSiswaBerakun(
        TahunPelajaran $tahun,
        Kelas $kelas,
        string $nama,
        string $nisn,
        int $nomorAbsen,
    ): array {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => '26'.$nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $nomorAbsen,
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
        $akun->daftarPeran()->sync([Peran::where('kode', 'siswa')->value('id')]);

        return [$siswa, $akun];
    }

    private function buatSurvei(GuruMataPelajaran $guruMataPelajaran, Siswa $siswa): SurveiPembelajaran
    {
        $pertanyaan = PertanyaanSurveiPembelajaran::aktif()->terurut()->get();

        return SurveiPembelajaran::create([
            'guru_mata_pelajaran_id' => $guruMataPelajaran->id,
            'siswa_id' => $siswa->id,
            'semester' => 'ganjil',
            'versi_pertanyaan' => SurveiPembelajaran::VERSI_PERTANYAAN,
            'jawaban' => $pertanyaan
                ->mapWithKeys(fn (PertanyaanSurveiPembelajaran $item) => [$item->kode => 4])
                ->all(),
            'snapshot_pertanyaan' => $pertanyaan
                ->mapWithKeys(fn (PertanyaanSurveiPembelajaran $item) => [
                    $item->kode => ['pernyataan' => $item->pernyataan, 'urutan' => $item->urutan],
                ])
                ->all(),
            'diisi_pada' => now(),
        ]);
    }
}
