<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\JenisUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\MataPelajaran;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PesertaUjianCbt;
use App\Models\Siswa;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UjianSayaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_siswa_mengerjakan_ujian_native_dengan_autosave_dan_pembatasan_perangkat(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $token = $this->token($data['pengguna']);

        $this->getJson(route('api.v1.ujian-saya.index'))->assertUnauthorized();

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-saya.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.pengerjaan_native', true)
            ->assertJsonPath('data.items.0.nama', 'Asesmen IPA Native');

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-saya.show', $data['peserta_lain']))
            ->assertNotFound();

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-saya.show', $data['peserta']))
            ->assertOk()
            ->assertJsonPath('data.mode', 'konfirmasi')
            ->assertJsonPath('data.memerlukan_token', true)
            ->assertJsonPath('data.kemajuan.jumlah_soal', 2);

        $this->withToken($token)
            ->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
                'token' => 'SALAH',
                'perangkat' => 'NUSA Android A',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');

        $response = $this->withToken($token)
            ->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
                'token' => 'MULAI1',
                'perangkat' => 'NUSA Android A',
            ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'pengerjaan')
            ->assertJsonPath('data.sisa_detik', 1800)
            ->assertJsonPath('data.soal.0.pertanyaan', 'Organ pernapasan manusia adalah ....')
            ->assertJsonPath('data.soal.0.stimulus', 'Perhatikan data pernapasan berikut.')
            ->assertJsonPath('data.soal.0.stimulus_media.rumus.latex', 'V = \\frac{u}{t}')
            ->assertJsonPath('data.soal.0.pilihan.0.media.tabel.judul', 'Ciri organ')
            ->assertJsonPath('data.soal.1.pertanyaan', 'Jelaskan proses pertukaran oksigen.')
            ->assertJsonMissingPath('data.soal.0.kunci_jawaban')
            ->assertJsonMissingPath('data.soal.0.pembahasan');

        $this->assertSame('sedang_mengerjakan', $data['peserta']->fresh()->status);
        $this->assertSame('NUSA Android A', $data['peserta']->fresh()->perangkat_terakhir);
        $this->assertCount(2, $response->json('data.soal'));

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-saya.kerjakan', [
                'pesertaUjianCbt' => $data['peserta'],
                'perangkat' => 'NUSA Android B',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('perangkat');

        Carbon::setTestNow('2026-09-05 08:05:00');
        $this->withToken($token)
            ->getJson(route('api.v1.ujian-saya.kerjakan', [
                'pesertaUjianCbt' => $data['peserta'],
                'perangkat' => 'NUSA Android A',
            ]))
            ->assertOk()
            ->assertJsonPath('data.sisa_detik', 1500);

        $this->withToken($token)
            ->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
                'soal_ujian_cbt_id' => $data['soal_pg']->id,
                'jawaban' => ['A'],
                'ragu' => false,
                'perangkat' => 'NUSA Android A',
            ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'tersimpan')
            ->assertJsonPath('data.terjawab', true);

        $this->withToken($token)
            ->postJson(route('api.v1.ujian-saya.selesai', $data['peserta']), [
                'perangkat' => 'NUSA Android A',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ujian');

        $this->assertSame('sedang_mengerjakan', $data['peserta']->fresh()->status);

        $this->withToken($token)
            ->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
                'soal_ujian_cbt_id' => $data['soal_uraian']->id,
                'jawaban' => ['Pertukaran terjadi di alveolus.'],
                'ragu' => true,
                'perangkat' => 'NUSA Android A',
            ])
            ->assertOk()
            ->assertJsonPath('data.ragu', true);

        $this->withToken($token)
            ->postJson(route('api.v1.ujian-saya.selesai', $data['peserta']), [
                'perangkat' => 'NUSA Android A',
            ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'selesai')
            ->assertJsonPath('data.kemajuan.terjawab', 2)
            ->assertJsonPath('data.hasil.menunggu_koreksi', true)
            ->assertJsonPath('data.hasil.ditampilkan', false)
            ->assertJsonPath('data.hasil.nilai', null);

        $this->assertDatabaseHas('jawaban_peserta_ujian_cbt', [
            'peserta_ujian_cbt_id' => $data['peserta']->id,
            'soal_ujian_cbt_id' => $data['soal_pg']->id,
            'benar' => true,
        ]);
        $this->assertSame('selesai', $data['peserta']->fresh()->status);
    }

    public function test_ujian_dapat_dikumpulkan_dengan_jawaban_belum_lengkap_pada_15_menit_terakhir(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $token = $this->token($data['pengguna']);

        $this->withToken($token)
            ->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
                'token' => 'MULAI1',
                'perangkat' => 'NUSA Android Batas Akhir',
            ])
            ->assertOk()
            ->assertJsonPath('data.penyelesaian.boleh_dikumpulkan', false)
            ->assertJsonPath('data.penyelesaian.batas_akhir_detik', 900);

        $this->withToken($token)
            ->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
                'soal_ujian_cbt_id' => $data['soal_pg']->id,
                'jawaban' => ['A'],
                'ragu' => false,
                'perangkat' => 'NUSA Android Batas Akhir',
            ])
            ->assertOk();

        Carbon::setTestNow('2026-09-05 08:15:00');

        $this->withToken($token)
            ->postJson(route('api.v1.ujian-saya.selesai', $data['peserta']), [
                'perangkat' => 'NUSA Android Batas Akhir',
            ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'selesai')
            ->assertJsonPath('data.kemajuan.terjawab', 1)
            ->assertJsonPath('data.kemajuan.belum_dijawab', 1);

        $this->assertSame('selesai', $data['peserta']->fresh()->status);
    }

    public function test_waktu_server_mengakhiri_ujian_dan_menampilkan_hasil_objektif(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi(hanyaObjektif: true);
        $token = $this->token($data['pengguna']);

        $this->withToken($token)
            ->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
                'token' => 'MULAI1',
                'perangkat' => 'NUSA Android Timer',
            ])
            ->assertOk();

        $this->withToken($token)
            ->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
                'soal_ujian_cbt_id' => $data['soal_pg']->id,
                'jawaban' => ['A'],
                'ragu' => false,
                'perangkat' => 'NUSA Android Timer',
            ])
            ->assertOk();

        Carbon::setTestNow('2026-09-05 08:31:00');
        $this->withToken($token)
            ->getJson(route('api.v1.ujian-saya.kerjakan', [
                'pesertaUjianCbt' => $data['peserta'],
                'perangkat' => 'NUSA Android Timer',
            ]))
            ->assertOk()
            ->assertJsonPath('data.mode', 'selesai')
            ->assertJsonPath('data.hasil.ditampilkan', true)
            ->assertJsonPath('data.hasil.nilai', 100)
            ->assertJsonPath('data.hasil.tuntas', true);

        $this->assertSame('selesai', $data['peserta']->fresh()->status);
    }

    public function test_soal_menjodohkan_mengirim_pilihan_pasangan_tanpa_membocorkan_kunci(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $ujian = $data['peserta']->ujianCbt;
        $ujian->update(['acak_jawaban' => true]);
        $soal = SoalCbt::create([
            'tahun_pelajaran_id' => $ujian->tahun_pelajaran_id,
            'mata_pelajaran_id' => $ujian->mata_pelajaran_id,
            'tingkat' => 8,
            'kode' => 'IPA-NATIVE-MATCH',
            'jenis_soal' => 'menjodohkan',
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'umum',
            'pertanyaan' => 'Jodohkan besaran dengan satuannya.',
            'opsi' => [
                'pasangan' => [
                    [
                        'nomor' => 1,
                        'kiri' => 'Frekuensi',
                        'kanan' => 'Hertz',
                        'media_kiri_key' => 'besaran_frekuensi',
                        'media_kanan_key' => 'satuan_hertz',
                    ],
                    [
                        'nomor' => 2,
                        'kiri' => 'Periode',
                        'kanan' => 'Sekon',
                        'media_kiri_key' => 'besaran_periode',
                        'media_kanan_key' => 'satuan_sekon',
                    ],
                ],
                'pengecoh' => ['Newton'],
                'pengecoh_media' => [
                    ['teks' => 'Newton', 'media_key' => 'pengecoh_newton'],
                ],
            ],
            'kunci_jawaban' => ['jawaban' => [1 => 'Hertz', 2 => 'Sekon']],
            'media' => [
                'konten' => [
                    'besaran_frekuensi' => [
                        'rumus' => ['latex' => 'f', 'keterangan' => 'Lambang frekuensi'],
                    ],
                    'satuan_hertz' => [
                        'rumus' => ['latex' => '\\mathrm{Hz}', 'keterangan' => 'Satuan frekuensi'],
                    ],
                    'pengecoh_newton' => [
                        'gambar' => [
                            'path' => 'soal-cbt/uji/newton.png',
                            'alt' => 'Ilustrasi gaya Newton',
                            'keterangan' => 'Gambar pengecoh Newton',
                        ],
                    ],
                ],
            ],
            'skor_maksimal' => 2,
            'status' => 'siap',
            'aktif' => true,
        ]);
        $relasi = $ujian->soalUjianCbt()->create([
            'soal_cbt_id' => $soal->id,
            'nomor_urut' => 3,
            'bobot' => 2,
        ]);
        $ujian->update(['jumlah_soal' => 3]);
        $token = $this->token($data['pengguna']);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
                'token' => 'MULAI1',
                'perangkat' => 'NUSA Android Menjodohkan',
            ])
            ->assertOk()
            ->assertJsonPath('data.soal.2.jenis', 'menjodohkan')
            ->assertJsonPath('data.soal.2.pasangan.0.kiri', 'Frekuensi')
            ->assertJsonCount(3, 'data.soal.2.pilihan')
            ->assertJsonMissingPath('data.soal.2.pasangan.0.kanan')
            ->assertJsonMissingPath('data.soal.2.kunci_jawaban');

        $pilihan = collect($response->json('data.soal.2.pilihan'));
        $this->assertSame(['A', 'B', 'C'], $pilihan->pluck('label')->all());
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $pilihan->pluck('kode')->all());
        $this->assertSame(['Hertz', 'Newton', 'Sekon'], $pilihan->pluck('teks')->sort()->values()->all());
        $this->assertSame('f', $response->json('data.soal.2.pasangan.0.media.rumus.latex'));
        $this->assertSame(
            '\\mathrm{Hz}',
            data_get($pilihan->firstWhere('teks', 'Hertz'), 'media.rumus.latex'),
        );
        $this->assertSame(
            'Gambar pengecoh Newton',
            data_get($pilihan->firstWhere('teks', 'Newton'), 'media.gambar.keterangan'),
        );

        $this->withToken($token)
            ->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
                'soal_ujian_cbt_id' => $relasi->id,
                'jawaban' => ['1' => 'Hertz', '2' => 'Sekon'],
                'ragu' => false,
                'perangkat' => 'NUSA Android Menjodohkan',
            ])
            ->assertOk()
            ->assertJsonPath('data.terjawab', true);

        $this->assertDatabaseHas('jawaban_peserta_ujian_cbt', [
            'peserta_ujian_cbt_id' => $data['peserta']->id,
            'soal_ujian_cbt_id' => $relasi->id,
        ]);
    }

    public function test_siswa_dapat_mengunggah_berkas_jawaban_dan_menggantinya(): void
    {
        Storage::fake('local');
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $ujian = $data['peserta']->ujianCbt;
        $soal = SoalCbt::create([
            'tahun_pelajaran_id' => $ujian->tahun_pelajaran_id,
            'mata_pelajaran_id' => $ujian->mata_pelajaran_id,
            'tingkat' => 8,
            'kode' => 'IPA-NATIVE-UPLOAD',
            'jenis_soal' => 'upload_file',
            'tingkat_kesulitan' => 'sulit',
            'kategori' => 'praktik',
            'pertanyaan' => 'Unggah laporan praktikum.',
            'opsi' => [],
            'kunci_jawaban' => [],
            'skor_maksimal' => 3,
            'status' => 'siap',
            'aktif' => true,
        ]);
        $relasi = $ujian->soalUjianCbt()->create([
            'soal_cbt_id' => $soal->id,
            'nomor_urut' => 3,
            'bobot' => 3,
        ]);
        $ujian->update(['jumlah_soal' => 3]);
        $token = $this->token($data['pengguna']);

        $this->withToken($token)
            ->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
                'token' => 'MULAI1',
                'perangkat' => 'NUSA Android Upload',
            ])
            ->assertOk();

        $this->withToken($token)
            ->post(route('api.v1.ujian-saya.jawaban-berkas.store', $data['peserta']), [
                'soal_ujian_cbt_id' => $relasi->id,
                'berkas' => UploadedFile::fake()->create('laporan-praktikum.pdf', 120, 'application/pdf'),
                'ragu' => '0',
                'perangkat' => 'NUSA Android Upload',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.terjawab', true)
            ->assertJsonPath('data.berkas.nama', 'laporan-praktikum.pdf');

        $jawaban = $data['peserta']->jawabanPesertaUjianCbt()
            ->where('soal_ujian_cbt_id', $relasi->id)
            ->firstOrFail();
        $lokasiPertama = $jawaban->lokasi_file;
        Storage::disk('local')->assertExists($lokasiPertama);

        $this->withToken($token)
            ->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
                'soal_ujian_cbt_id' => $relasi->id,
                'jawaban' => null,
                'ragu' => true,
                'perangkat' => 'NUSA Android Upload',
            ])
            ->assertOk()
            ->assertJsonPath('data.terjawab', true)
            ->assertJsonPath('data.ragu', true);

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-saya.kerjakan', [
                'pesertaUjianCbt' => $data['peserta'],
                'perangkat' => 'NUSA Android Upload',
            ]))
            ->assertOk()
            ->assertJsonPath('data.soal.2.jawaban.berkas', 'laporan-praktikum.pdf')
            ->assertJsonPath('data.soal.2.berkas_jawaban.nama', 'laporan-praktikum.pdf');

        $this->withToken($token)
            ->post(route('api.v1.ujian-saya.jawaban-berkas.store', $data['peserta']), [
                'soal_ujian_cbt_id' => $relasi->id,
                'berkas' => UploadedFile::fake()->create('laporan-revisi.pdf', 80, 'application/pdf'),
                'ragu' => '1',
                'perangkat' => 'NUSA Android Upload',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.berkas.nama', 'laporan-revisi.pdf');

        $jawaban->refresh();
        Storage::disk('local')->assertMissing($lokasiPertama);
        Storage::disk('local')->assertExists($jawaban->lokasi_file);
        $this->assertSame(['berkas' => 'laporan-revisi.pdf'], $jawaban->jawaban);
    }

    public function test_mode_aman_mencatat_keluar_aplikasi_menahan_dan_dapat_dibuka_pengawas(): void
    {
        $waktu = Carbon::parse('2026-09-05 08:00:00');
        Carbon::setTestNow($waktu);
        $data = $this->fondasi(hanyaObjektif: true);
        $data['peserta']->ujianCbt->update([
            'deteksi_pindah_tab' => true,
            'wajib_fullscreen' => true,
            'blokir_tangkapan_layar' => true,
            'toleransi_pindah_aplikasi_detik' => 3,
            'batas_pindah_aplikasi' => 3,
            'tindakan_pindah_aplikasi' => 'tahan',
        ]);
        $token = $this->token($data['pengguna']);

        $this->withToken($token)
            ->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
                'token' => 'MULAI1',
                'perangkat' => 'NUSA Android Aman',
            ])
            ->assertOk()
            ->assertJsonPath('data.keamanan.layar_aman', true)
            ->assertJsonPath('data.keamanan.wajib_fullscreen', true);

        $this->aktivitasKeamanan($token, $data['peserta'], 'keluar')->assertOk();
        Carbon::setTestNow($waktu->copy()->addSeconds(2));
        $this->aktivitasKeamanan($token, $data['peserta'], 'kembali')
            ->assertOk()
            ->assertJsonPath('data.kejadian_dihitung', false)
            ->assertJsonPath('data.keamanan.jumlah_kejadian', 0);

        for ($kejadian = 1; $kejadian <= 3; $kejadian++) {
            $waktu = now()->addSecond();
            Carbon::setTestNow($waktu);
            $this->aktivitasKeamanan($token, $data['peserta'], 'keluar')->assertOk();
            Carbon::setTestNow($waktu->copy()->addSeconds(4));
            $respons = $this->aktivitasKeamanan($token, $data['peserta'], 'kembali')
                ->assertOk()
                ->assertJsonPath('data.kejadian_dihitung', true)
                ->assertJsonPath('data.keamanan.jumlah_kejadian', $kejadian);
        }

        $respons
            ->assertJsonPath('data.mode', 'ditahan')
            ->assertJsonPath('data.keamanan.ditahan', true);
        $this->assertSame('terblokir', $data['peserta']->fresh()->status);
        $this->assertDatabaseCount('aktivitas_keamanan_ujian_cbt', 4);

        $this->withToken($token)
            ->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
                'soal_ujian_cbt_id' => $data['soal_pg']->id,
                'jawaban' => ['A'],
                'ragu' => false,
                'perangkat' => 'NUSA Android Aman',
            ])
            ->assertUnprocessable();

        $admin = Pengguna::create([
            'nama' => 'Pengawas Admin',
            'username' => 'pengawas.mode.aman',
            'kata_sandi' => 'PengawasModeAman#2026',
            'peran' => 'administrator',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $admin->daftarPeran()->attach(Peran::where('kode', 'administrator')->firstOrFail());

        Sanctum::actingAs($admin, ['mobile']);
        $this->postJson(route('api.v1.keamanan-ujian.buka', $data['peserta']))
            ->assertOk()
            ->assertJsonPath('data.status', 'sedang_mengerjakan')
            ->assertJsonPath('data.keamanan.ditahan', false);

        Sanctum::actingAs($data['pengguna'], ['mobile']);
        $this->getJson(route('api.v1.ujian-saya.kerjakan', [
            'pesertaUjianCbt' => $data['peserta'],
            'perangkat' => 'NUSA Android Aman',
        ]))
            ->assertOk()
            ->assertJsonPath('data.mode', 'pengerjaan')
            ->assertJsonPath('data.keamanan.jumlah_kejadian', 3);
    }

    private function fondasi(bool $hanyaObjektif = false): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.A',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.B',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        [$siswa, $anggota, $pengguna] = $this->siswa($tahun, $kelas, 'Siswa Mobile', '0099000001');
        [, $anggotaLain] = $this->siswa($tahun, $kelasLain, 'Siswa Lain', '0099000002');
        $mapel = MataPelajaran::create([
            'kode' => 'IPA8-M',
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
        $ujian = UjianCbt::create([
            'alur' => 'kelas',
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mapel->id,
            'kode' => 'ASESMEN-IPA-NATIVE',
            'nama' => 'Asesmen IPA Native',
            'semester' => 'ganjil',
            'tingkat' => 8,
            'tanggal_mulai' => '2026-09-05 07:30:00',
            'tanggal_selesai' => '2026-09-05 10:00:00',
            'durasi_menit' => 30,
            'jumlah_soal' => $hanyaObjektif ? 1 : 2,
            'kkm' => 75,
            'token' => 'MULAI1',
            'acak_soal' => false,
            'acak_jawaban' => false,
            'batasi_satu_perangkat' => true,
            'tampilkan_hasil' => true,
            'status' => 'berlangsung',
            'petunjuk' => 'Kerjakan dengan jujur dan teliti.',
        ]);
        $kelasUjian = KelasUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_id' => $kelas->id,
        ]);
        $kelasUjianLain = KelasUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_id' => $kelasLain->id,
        ]);
        $soalPg = SoalCbt::create([
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mapel->id,
            'tingkat' => 8,
            'kode' => 'IPA-NATIVE-001',
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'umum',
            'stimulus' => 'Perhatikan data pernapasan berikut.',
            'pertanyaan' => 'Organ pernapasan manusia adalah ....',
            'opsi' => [
                ['kode' => 'A', 'teks' => 'Paru-paru'],
                ['kode' => 'B', 'teks' => 'Lambung'],
            ],
            'kunci_jawaban' => ['A'],
            'media' => [
                'konten' => [
                    'stimulus' => [
                        'rumus' => ['latex' => 'V = \\frac{u}{t}', 'keterangan' => 'Rumus volume udara'],
                    ],
                    'pilihan_A' => [
                        'tabel' => [
                            'judul' => 'Ciri organ',
                            'baris' => [['Bagian', 'Fungsi'], ['Alveolus', 'Pertukaran gas']],
                        ],
                    ],
                ],
            ],
            'skor_maksimal' => 1,
            'status' => 'siap',
            'aktif' => true,
        ]);
        $relasiPg = $ujian->soalUjianCbt()->create([
            'soal_cbt_id' => $soalPg->id,
            'nomor_urut' => 1,
            'bobot' => 1,
        ]);
        $relasiUraian = null;

        if (! $hanyaObjektif) {
            $soalUraian = SoalCbt::create([
                'tahun_pelajaran_id' => $tahun->id,
                'mata_pelajaran_id' => $mapel->id,
                'tingkat' => 8,
                'kode' => 'IPA-NATIVE-002',
                'jenis_soal' => 'uraian',
                'tingkat_kesulitan' => 'sedang',
                'kategori' => 'umum',
                'pertanyaan' => 'Jelaskan proses pertukaran oksigen.',
                'rubrik' => ['catatan' => 'Periksa konsep alveolus.'],
                'skor_maksimal' => 1,
                'status' => 'siap',
                'aktif' => true,
            ]);
            $relasiUraian = $ujian->soalUjianCbt()->create([
                'soal_cbt_id' => $soalUraian->id,
                'nomor_urut' => 2,
                'bobot' => 1,
            ]);
        }

        $peserta = PesertaUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_ujian_cbt_id' => $kelasUjian->id,
            'anggota_kelas_id' => $anggota->id,
            'nomor_peserta' => 'NATIVE-001',
            'status' => 'aktif',
            'menit_tersisa' => 30,
        ]);
        $pesertaLain = PesertaUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_ujian_cbt_id' => $kelasUjianLain->id,
            'anggota_kelas_id' => $anggotaLain->id,
            'nomor_peserta' => 'NATIVE-002',
            'status' => 'aktif',
            'menit_tersisa' => 30,
        ]);

        return [
            'siswa' => $siswa,
            'pengguna' => $pengguna,
            'peserta' => $peserta,
            'peserta_lain' => $pesertaLain,
            'soal_pg' => $relasiPg,
            'soal_uraian' => $relasiUraian,
        ];
    }

    private function siswa(
        TahunPelajaran $tahun,
        Kelas $kelas,
        string $nama,
        string $nisn,
    ): array {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => '26'.$nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        $pengguna = Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $nama,
            'username' => $nisn,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        return [$siswa, $anggota, $pengguna];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('NUSA Android Test', ['mobile'])->plainTextToken;
    }

    private function aktivitasKeamanan(
        string $token,
        PesertaUjianCbt $peserta,
        string $peristiwa,
    ) {
        return $this->withToken($token)
            ->postJson(route('api.v1.ujian-saya.aktivitas-keamanan', $peserta), [
                'peristiwa' => $peristiwa,
                'perangkat' => 'NUSA Android Aman',
            ]);
    }
}
