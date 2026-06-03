<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\JenisUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PesertaUjianCbt;
use App\Models\Pengguna;
use App\Models\SesiUjianCbt;
use App\Models\SoalCbt;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Support\Carbon;
use PDO;
use Tests\TestCase;

class UjianCbtTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite belum aktif pada PHP lokal.');
        }

        $this->artisan('migrate:fresh');
    }

    public function test_administrator_dapat_mengelola_paket_ujian_cbt(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $jenisUjian = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.create'))
            ->assertOk()
            ->assertSee('Tambah paket CBT')
            ->assertSee('Kelas Peserta dan Tujuan Nilai');

        $this->actingAs($administrator)
            ->post(route('ujian-cbt.store'), $this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
            ->assertRedirect();

        $ujianCbt = UjianCbt::where('kode', 'CBT-UJI-001')->firstOrFail();

        $this->assertSame('123456', $ujianCbt->token);
        $this->assertTrue($ujianCbt->acak_soal);
        $this->assertDatabaseHas('kelas_ujian_cbt', [
            'ujian_cbt_id' => $ujianCbt->id,
            'kelas_id' => $kelas->id,
            'komponen_nilai_id' => $komponenNilai->id,
        ]);

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.show', $ujianCbt))
            ->assertOk()
            ->assertSee('STS Matematika Semester Ganjil')
            ->assertSee('VIII.A')
            ->assertSee('123456');

        $this->actingAs($administrator)
            ->put(route('ujian-cbt.update', $ujianCbt), [
                ...$this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai),
                'nama' => 'STS Matematika Revisi',
                'token' => '',
                'status' => 'terjadwal',
                'acak_jawaban' => '0',
            ])
            ->assertRedirect(route('ujian-cbt.show', $ujianCbt));

        $ujianCbt->refresh();
        $this->assertSame('STS Matematika Revisi', $ujianCbt->nama);
        $this->assertSame('terjadwal', $ujianCbt->status);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $ujianCbt->token);
        $this->assertFalse($ujianCbt->acak_jawaban);

        $this->actingAs($administrator)
            ->delete(route('ujian-cbt.destroy', $ujianCbt))
            ->assertRedirect(route('ujian-cbt.index'));

        $this->assertSame('nonaktif', $ujianCbt->fresh()->status);
    }

    public function test_cbt_menolak_komponen_yang_tidak_sesuai_kelas(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'nama' => 'VIII.B',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $jenisUjian = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->from(route('ujian-cbt.create'))
            ->post(route('ujian-cbt.store'), [
                ...$this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelasLain, $komponenNilai),
                'kelas_peserta' => [
                    $kelasLain->id => [
                        'dipilih' => '1',
                        'komponen_nilai_id' => $komponenNilai->id,
                    ],
                ],
            ])
            ->assertRedirect(route('ujian-cbt.create'))
            ->assertSessionHasErrors('kelas_peserta');

        $this->assertDatabaseCount('ujian_cbt', 0);
        $this->assertDatabaseCount('kelas_ujian_cbt', 0);
    }

    public function test_administrator_dapat_menghubungkan_bank_soal_ke_paket_cbt(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $jenisUjian = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $ujianCbt = UjianCbt::create([
            ...collect($this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                ->except('kelas_peserta')
                ->all(),
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        $soalPertama = $this->buatSoalCbt($tahunPelajaran, $mataPelajaran, 'CBT-MTK-8-001', 'Berapakah hasil dari 12 + 8?');
        $soalKedua = $this->buatSoalCbt($tahunPelajaran, $mataPelajaran, 'CBT-MTK-8-002', 'Berapakah hasil dari 5 x 6?');
        $mapelLain = MataPelajaran::create([
            'kode' => 'IPA-8',
            'nama' => 'IPA Kelas VIII',
            'tingkat' => 8,
            'kkm' => 78,
            'aktif' => true,
        ]);
        $soalTidakSesuai = $this->buatSoalCbt($tahunPelajaran, $mapelLain, 'CBT-IPA-8-001', 'Contoh soal IPA.');

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.soal.edit', $ujianCbt))
            ->assertOk()
            ->assertSee('Kelola soal paket CBT')
            ->assertSee('CBT-MTK-8-001')
            ->assertSee('CBT-MTK-8-002')
            ->assertDontSee('CBT-IPA-8-001');

        $this->actingAs($administrator)
            ->put(route('ujian-cbt.soal.update', $ujianCbt), [
                'soal' => [
                    $soalPertama->id => [
                        'dipilih' => '1',
                        'nomor_urut' => '1',
                        'bobot' => '2',
                    ],
                    $soalKedua->id => [
                        'dipilih' => '1',
                        'nomor_urut' => '2',
                        'bobot' => '1.5',
                    ],
                ],
            ])
            ->assertRedirect(route('ujian-cbt.show', $ujianCbt));

        $this->assertDatabaseHas('soal_ujian_cbt', [
            'ujian_cbt_id' => $ujianCbt->id,
            'soal_cbt_id' => $soalPertama->id,
            'nomor_urut' => 1,
        ]);
        $this->assertDatabaseHas('soal_ujian_cbt', [
            'ujian_cbt_id' => $ujianCbt->id,
            'soal_cbt_id' => $soalKedua->id,
            'nomor_urut' => 2,
        ]);

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.show', $ujianCbt))
            ->assertOk()
            ->assertSee('CBT-MTK-8-001')
            ->assertSee('CBT-MTK-8-002');

        $this->actingAs($administrator)
            ->from(route('ujian-cbt.soal.edit', $ujianCbt))
            ->put(route('ujian-cbt.soal.update', $ujianCbt), [
                'soal' => [
                    $soalTidakSesuai->id => [
                        'dipilih' => '1',
                        'nomor_urut' => '1',
                        'bobot' => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('ujian-cbt.soal.edit', $ujianCbt))
            ->assertSessionHasErrors('soal');

        $this->assertSame(2, $ujianCbt->soalUjianCbt()->count());
    }

    public function test_administrator_dapat_membuat_sesi_dan_generate_peserta_cbt(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $jenisUjian = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatAnggotaSiswa($tahunPelajaran, $kelas, 3);

        $ujianCbt = UjianCbt::create([
            ...collect($this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                ->except('kelas_peserta')
                ->all(),
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        $kelasUjianCbt = KelasUjianCbt::create([
            'ujian_cbt_id' => $ujianCbt->id,
            'kelas_id' => $kelas->id,
            'komponen_nilai_id' => $komponenNilai->id,
        ]);

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.peserta.index', $ujianCbt))
            ->assertOk()
            ->assertSee('Peserta & sesi CBT', false)
            ->assertSee('Generate peserta');

        $this->actingAs($administrator)
            ->post(route('ujian-cbt.sesi.store', $ujianCbt), [
                'kode' => 'S-01',
                'nama' => 'Sesi 1',
                'waktu_mulai' => '2026-08-15 08:00',
                'waktu_selesai' => '2026-08-15 10:00',
                'kapasitas' => 2,
                'status' => 'aktif',
            ])
            ->assertRedirect(route('ujian-cbt.peserta.index', $ujianCbt));

        $sesi = SesiUjianCbt::where('ujian_cbt_id', $ujianCbt->id)->firstOrFail();
        $this->assertSame('Sesi 1', $sesi->nama);

        $this->actingAs($administrator)
            ->post(route('ujian-cbt.peserta.generate', $ujianCbt))
            ->assertRedirect(route('ujian-cbt.peserta.index', $ujianCbt));

        $this->assertDatabaseCount('peserta_ujian_cbt', 3);
        $this->assertDatabaseCount('akun_peserta_cbt', 3);
        $this->assertSame(3, $kelasUjianCbt->pesertaUjianCbt()->count());

        $peserta = PesertaUjianCbt::query()
            ->with(['akunPesertaCbt', 'anggotaKelas.siswa'])
            ->where('ujian_cbt_id', $ujianCbt->id)
            ->orderBy('id')
            ->firstOrFail();
        $akunPeserta = $peserta->akunPesertaCbt;

        $this->assertSame($sesi->id, $peserta->sesi_ujian_cbt_id);
        $this->assertNotNull($akunPeserta);
        $this->assertNotEmpty($akunPeserta->username);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $akunPeserta->kata_sandi);

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.peserta.index', $ujianCbt))
            ->assertOk()
            ->assertSee($peserta->anggotaKelas->siswa->nama_lengkap)
            ->assertSee($akunPeserta->username);

        $usernameLama = 'CBT20260604001-VIIA-001';
        $peserta->forceFill([
            'akun_peserta_cbt_id' => null,
            'username' => $usernameLama,
        ])->save();

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.kartu-peserta.index', $ujianCbt))
            ->assertOk()
            ->assertSee('Kartu peserta ujian')
            ->assertSee($peserta->anggotaKelas->siswa->nama_lengkap)
            ->assertSee($akunPeserta->username)
            ->assertSee($akunPeserta->kata_sandi)
            ->assertSee('Token ujian diberikan oleh pengawas/proktor.')
            ->assertDontSee('Mapel:')
            ->assertDontSee('Token paket:')
            ->assertDontSee($usernameLama);

        $this->assertSame($akunPeserta->id, $peserta->fresh()->akun_peserta_cbt_id);

        $this->actingAs($administrator)
            ->put(route('ujian-cbt.peserta.update', $ujianCbt), [
                'peserta' => [
                    $peserta->id => [
                        'sesi_ujian_cbt_id' => $sesi->id,
                        'status' => 'nonaktif',
                        'catatan' => 'Tidak ikut sesi pertama.',
                    ],
                ],
            ])
            ->assertRedirect(route('ujian-cbt.peserta.index', $ujianCbt));

        $peserta->refresh();
        $this->assertSame('nonaktif', $peserta->status);
        $this->assertSame('Tidak ikut sesi pertama.', $peserta->catatan);
    }

    public function test_peserta_dapat_login_dan_mengerjakan_ujian_cbt(): void
    {
        Carbon::setTestNow('2026-08-15 08:30:00');

        try {
            [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
            $jenisUjian = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
            $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
            $this->buatAnggotaSiswa($tahunPelajaran, $kelas, 1);

            $ujianCbt = UjianCbt::create([
                ...collect($this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                    ->except('kelas_peserta')
                    ->all(),
                'jumlah_soal' => 2,
                'status' => 'berlangsung',
                'token' => 'TOKEN1',
                'dibuat_oleh_pengguna_id' => $administrator->id,
            ]);
            $kelasUjianCbt = KelasUjianCbt::create([
                'ujian_cbt_id' => $ujianCbt->id,
                'kelas_id' => $kelas->id,
                'komponen_nilai_id' => $komponenNilai->id,
            ]);
            $sesi = SesiUjianCbt::create([
                'ujian_cbt_id' => $ujianCbt->id,
                'kode' => 'S-01',
                'nama' => 'Sesi 1',
                'waktu_mulai' => '2026-08-15 08:00',
                'waktu_selesai' => '2026-08-15 10:00',
                'kapasitas' => 32,
                'status' => 'aktif',
            ]);

            $soalPertama = $this->buatSoalCbt($tahunPelajaran, $mataPelajaran, 'CBT-AKSES-001', 'Berapakah hasil dari 12 + 8?');
            $soalKedua = $this->buatSoalCbt($tahunPelajaran, $mataPelajaran, 'CBT-AKSES-002', 'Berapakah hasil dari 5 x 6?');
            $relasiPertama = $ujianCbt->soalUjianCbt()->create([
                'soal_cbt_id' => $soalPertama->id,
                'nomor_urut' => 1,
                'bobot' => 1,
            ]);
            $relasiKedua = $ujianCbt->soalUjianCbt()->create([
                'soal_cbt_id' => $soalKedua->id,
                'nomor_urut' => 2,
                'bobot' => 1,
            ]);

            $this->actingAs($administrator)
                ->post(route('ujian-cbt.peserta.generate', $ujianCbt))
                ->assertRedirect(route('ujian-cbt.peserta.index', $ujianCbt));

            $peserta = PesertaUjianCbt::query()
                ->with(['akunPesertaCbt', 'anggotaKelas.siswa'])
                ->where('ujian_cbt_id', $ujianCbt->id)
                ->where('kelas_ujian_cbt_id', $kelasUjianCbt->id)
                ->firstOrFail();

            $this->assertSame($sesi->id, $peserta->sesi_ujian_cbt_id);

            $this->post(route('cbt.login.store'), [
                'username' => $peserta->akunPesertaCbt->username,
                'kata_sandi' => $peserta->akunPesertaCbt->kata_sandi,
                'token' => 'TOKEN1',
            ])->assertRedirect(route('cbt.ujian.show'));

            $this->get(route('cbt.ujian.show'))
                ->assertOk()
                ->assertSee('STS Matematika Semester Ganjil')
                ->assertSee($peserta->anggotaKelas->siswa->nama_lengkap)
                ->assertSee('Mulai ujian');

            $this->post(route('cbt.ujian.mulai'))
                ->assertRedirect(route('cbt.ujian.kerjakan'));

            $peserta->refresh();
            $this->assertSame('sedang_mengerjakan', $peserta->status);
            $this->assertNotNull($peserta->waktu_mulai);

            $this->get(route('cbt.ujian.kerjakan'))
                ->assertOk()
                ->assertSee('Berapakah hasil dari 12 + 8?')
                ->assertSee('Berapakah hasil dari 5 x 6?')
                ->assertSee('Sisa waktu');

            $this->post(route('cbt.ujian.simpan'), [
                'jawaban' => [
                    $relasiPertama->id => 'B',
                    $relasiKedua->id => 'C',
                ],
                'ragu' => [
                    $relasiKedua->id => '1',
                ],
                'aksi' => 'simpan',
            ])->assertRedirect(route('cbt.ujian.kerjakan'));

            $jawabanPertama = $peserta->jawabanPesertaUjianCbt()
                ->where('soal_ujian_cbt_id', $relasiPertama->id)
                ->firstOrFail();
            $jawabanKedua = $peserta->jawabanPesertaUjianCbt()
                ->where('soal_ujian_cbt_id', $relasiKedua->id)
                ->firstOrFail();

            $this->assertSame(['B'], $jawabanPertama->jawaban);
            $this->assertSame(['C'], $jawabanKedua->jawaban);
            $this->assertTrue($jawabanKedua->ragu);

            $this->post(route('cbt.ujian.simpan'), [
                'jawaban' => [
                    $relasiPertama->id => 'B',
                    $relasiKedua->id => 'B',
                ],
                'aksi' => 'selesai',
            ])->assertRedirect(route('cbt.ujian.selesai'));

            $this->assertSame('selesai', $peserta->fresh()->status);

            $this->get(route('cbt.ujian.selesai'))
                ->assertOk()
                ->assertSee('Ujian selesai')
                ->assertSee('2 / 2');
        } finally {
            Carbon::setTestNow();
        }
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
            'kkm' => 78,
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
            'jenis_komponen' => 'sts',
            'nama' => 'STS Semester Ganjil',
            'aktif' => true,
        ]);

        return [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai];
    }

    private function dataUjian(
        JenisUjianCbt $jenisUjian,
        TahunPelajaran $tahunPelajaran,
        MataPelajaran $mataPelajaran,
        Kelas $kelas,
        KomponenNilai $komponenNilai
    ): array {
        return [
            'jenis_ujian_cbt_id' => $jenisUjian->id,
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'kode' => 'CBT-UJI-001',
            'nama' => 'STS Matematika Semester Ganjil',
            'semester' => 'ganjil',
            'tingkat' => 8,
            'tanggal_mulai' => '2026-08-15 08:00',
            'tanggal_selesai' => '2026-08-15 10:00',
            'durasi_menit' => 90,
            'jumlah_soal' => 40,
            'kkm' => 78,
            'token' => '123456',
            'acak_soal' => '1',
            'acak_jawaban' => '1',
            'batasi_satu_perangkat' => '1',
            'deteksi_pindah_tab' => '1',
            'wajib_fullscreen' => '0',
            'tampilkan_hasil' => '0',
            'status' => 'draft',
            'petunjuk' => 'Kerjakan dengan jujur.',
            'keterangan' => 'Paket percobaan CBT.',
            'kelas_peserta' => [
                $kelas->id => [
                    'dipilih' => '1',
                    'komponen_nilai_id' => $komponenNilai->id,
                ],
            ],
        ];
    }

    private function buatSoalCbt(
        TahunPelajaran $tahunPelajaran,
        MataPelajaran $mataPelajaran,
        string $kode,
        string $pertanyaan
    ): SoalCbt {
        return SoalCbt::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 8,
            'kode' => $kode,
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'umum',
            'pertanyaan' => $pertanyaan,
            'opsi' => [
                ['kode' => 'A', 'teks' => '10'],
                ['kode' => 'B', 'teks' => '20'],
                ['kode' => 'C', 'teks' => '30'],
                ['kode' => 'D', 'teks' => '40'],
            ],
            'kunci_jawaban' => ['B'],
            'skor_maksimal' => 1,
            'status' => 'siap',
            'aktif' => true,
        ]);
    }

    private function buatAnggotaSiswa(TahunPelajaran $tahunPelajaran, Kelas $kelas, int $jumlah): void
    {
        for ($i = 1; $i <= $jumlah; $i++) {
            $siswa = Siswa::create([
                'nama_lengkap' => 'Siswa CBT ' . $i,
                'nis' => 'CBT' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nisn' => '999000' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                'aktif' => true,
            ]);

            $kelas->anggotaKelas()->create([
                'tahun_pelajaran_id' => $tahunPelajaran->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $i,
                'status_keanggotaan' => 'aktif',
                'tanggal_masuk' => '2026-07-01',
            ]);
        }
    }
}
