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
use App\Services\Cbt\BatasWaktuPesertaUjianCbtService;
use App\Services\Cbt\KeamananUjianService;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UjianSayaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_media_dan_konten_pratinjau_guru_sama_dengan_soal_siswa(): void
    {
        Storage::fake('public');
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $soal = $data['soal_pg']->soalCbt;
        $bundles = [];
        foreach (['utama', 'stimulus', 'pilihan_A'] as $nama) {
            $path = 'soal-cbt/'.$nama.'.png';
            Storage::disk('public')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
            $bundles[$nama] = ['gambar' => ['path' => $path, 'alt' => 'Diagram '.$nama]];
        }
        $soal->update(['media' => [...$bundles['utama'], 'konten' => $bundles]]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $preview = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.bank-soal.pratinjau', $soal))
            ->assertOk()->json('data.soal');
        $this->assertNull($data['peserta']->fresh()->waktu_mulai);
        $this->app['auth']->forgetGuards();

        $siswa = $this->withToken($this->token($data['pengguna']))
            ->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
                'token' => 'MULAI1', 'perangkat' => 'Android Media',
            ])
            ->assertOk()->json('data.soal.0');
        foreach (['jenis', 'label_jenis', 'pertanyaan', 'stimulus', 'media', 'stimulus_media', 'pilihan', 'pernyataan', 'pasangan'] as $key) {
            $this->assertSame($preview[$key], $siswa[$key], $key);
        }
        foreach (['media', 'stimulus_media'] as $key) {
            $this->assertStringContainsString('/storage/soal-cbt/', $siswa[$key]['gambar']['url']);
        }
        $this->assertStringContainsString('/storage/soal-cbt/pilihan_A.png', $siswa['pilihan'][0]['media']['gambar']['url']);
        $this->assertArrayNotHasKey('kunci_jawaban', $siswa);
        $this->assertArrayNotHasKey('pembahasan', $siswa);
        $this->withToken($this->token($data['pengguna']))
            ->getJson(route('api.v1.bank-soal.pratinjau', $soal))->assertForbidden();
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
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ujian');

        $this->assertSame('sedang_mengerjakan', $data['peserta']->fresh()->status);

        Carbon::setTestNow('2026-09-05 08:15:00');

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

    #[DataProvider('modePengacakanMenjodohkan')]
    public function test_relasi_menjodohkan_tidak_dapat_direkonstruksi_dengan_hash_publik_dan_jawaban_lama_tetap_dinilai(bool $acak): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $this->app->instance('encrypter', new Encrypter(hash('sha256', 'server-pengujian', true), 'AES-256-CBC'));
        $data = $this->fondasi(hanyaObjektif: true);
        $ujian = $data['peserta']->ujianCbt;
        $ujian->update(['acak_jawaban' => $acak]);
        $relasi = $data['soal_pg'];
        $kunci = [];
        $pasangan = [];
        foreach (range(1, 8) as $nomor) {
            $kanan = 'Opsi-'.hash('sha256', 'teks-kanan-'.$nomor);
            $kunci[$nomor] = $kanan;
            $pasangan[] = ['nomor' => $nomor, 'kiri' => 'Butir-'.$nomor, 'kanan' => $kanan];
        }
        $pengecoh = 'Opsi-tambahan';
        $relasi->soalCbt->update([
            'jenis_soal' => 'menjodohkan',
            'pertanyaan' => 'Pasangkan setiap butir dengan opsi yang sesuai.',
            'opsi' => ['pasangan' => $pasangan, 'pengecoh' => [$pengecoh]],
            'kunci_jawaban' => ['jawaban' => $kunci],
            'media' => null,
        ]);
        $relasi->update(['bobot' => 8]);
        $token = $this->token($data['pengguna']);
        $perangkat = 'NUSA Android Matching Security';
        $respons = $this->withToken($token)->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'token' => 'MULAI1', 'perangkat' => $perangkat,
        ])->assertOk()->assertJsonMissingPath('data.soal.0.kunci_jawaban');
        $kertas = $respons->json('data');
        $soal = $kertas['soal'][0];

        // Reproduksi algoritme rentan hanya memakai ID/nomor dari respons siswa.
        $identitas = array_map(fn ($item) => 'pasangan-'.$item['nomor'], $soal['pasangan']);
        $identitas[] = 'pengecoh-1';
        $awal = $identitas;
        $konteksPeserta = $acak ? (string) $kertas['peserta']['id'] : 'tetap';
        $seedPublik = 'nusa-cbt|'.$kertas['ujian']['id'].'|'.$konteksPeserta.'|pasangan:'.$soal['id'].'|';
        usort($identitas, fn ($a, $b) => strcmp(hash('sha256', $seedPublik.$a), hash('sha256', $seedPublik.$b)));
        if ($identitas === $awal) {
            $identitas[] = array_shift($identitas);
        }
        $tebakan = [];
        foreach ($identitas as $index => $id) {
            if (str_starts_with($id, 'pasangan-')) {
                $tebakan[(int) substr($id, strlen('pasangan-'))] = $soal['pilihan'][$index]['teks'];
            }
        }
        ksort($tebakan);
        $this->assertNotSame($kunci, $tebakan, 'Algoritme publik lama tidak boleh merekonstruksi seluruh kunci fixture.');
        foreach ($soal['pasangan'] as $item) {
            $this->assertSame(['nomor', 'kiri', 'media'], array_keys($item));
        }
        foreach ($soal['pilihan'] as $item) {
            $this->assertSame(['kode', 'teks', 'media', 'label'], array_keys($item));
        }

        // Format lama menyimpan teks, bukan posisi A/B/C: jangan migrasikan atau hapus jawaban ini.
        $data['peserta']->jawabanPesertaUjianCbt()->create([
            'soal_ujian_cbt_id' => $relasi->id,
            'soal_cbt_id' => $relasi->soal_cbt_id,
            'jawaban' => $kunci,
            'ragu' => false,
            'waktu_dijawab' => now(),
        ]);
        $urlLanjut = route('api.v1.ujian-saya.kerjakan', [
            'pesertaUjianCbt' => $data['peserta'], 'perangkat' => $perangkat,
        ]);
        $lanjut = $this->withToken($token)->getJson($urlLanjut)->assertOk();
        $this->assertSame($kunci, $lanjut->json('data.soal.0.jawaban'));
        $this->assertSame($soal['pilihan'], $lanjut->json('data.soal.0.pilihan'));

        $jawabanBaru = $kunci;
        $jawabanBaru[8] = $pengecoh;
        $this->withToken($token)->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
            'soal_ujian_cbt_id' => $relasi->id,
            'jawaban' => $jawabanBaru,
            'ragu' => false,
            'perangkat' => $perangkat,
        ])->assertOk()->assertJsonPath('data.terjawab', true);
        $this->withToken($token)->getJson($urlLanjut)->assertOk()
            ->assertJsonPath('data.soal.0.jawaban', $jawabanBaru);
        Carbon::setTestNow('2026-09-05 08:16:00');
        $this->withToken($token)->postJson(route('api.v1.ujian-saya.selesai', $data['peserta']), [
            'perangkat' => $perangkat,
        ])->assertOk()->assertJsonPath('data.mode', 'selesai');

        $tersimpan = $data['peserta']->jawabanPesertaUjianCbt()->firstOrFail();
        $this->assertSame($jawabanBaru, $tersimpan->jawaban);
        $this->assertEquals(7.0, (float) $tersimpan->skor);
        $this->assertFalse($tersimpan->benar);
    }

    public static function modePengacakanMenjodohkan(): array
    {
        return ['seragam' => [false], 'per_peserta' => [true]];
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

        $this->aktivitasKeamanan($token, $data['peserta'], 'keluar')->assertOk()
            ->assertJsonPath('data.kejadian_dihitung', true)->assertJsonPath('data.keamanan.jumlah_kejadian', 1);
        Carbon::setTestNow($waktu->copy()->addSeconds(2));
        $this->aktivitasKeamanan($token, $data['peserta'], 'kembali')
            ->assertOk()
            ->assertJsonPath('data.kejadian_dihitung', false)
            ->assertJsonPath('data.keamanan.jumlah_kejadian', 1);

        for ($kejadian = 2; $kejadian <= 3; $kejadian++) {
            $waktu = now()->addSecond();
            Carbon::setTestNow($waktu);
            $this->aktivitasKeamanan($token, $data['peserta'], 'keluar')->assertOk()
                ->assertJsonPath('data.kejadian_dihitung', true)->assertJsonPath('data.keamanan.jumlah_kejadian', $kejadian);
            Carbon::setTestNow($waktu->copy()->addSeconds(4));
            $respons = $this->aktivitasKeamanan($token, $data['peserta'], 'kembali')
                ->assertOk()
                ->assertJsonPath('data.kejadian_dihitung', false)
                ->assertJsonPath('data.keamanan.jumlah_kejadian', $kejadian);
        }

        $respons
            ->assertJsonPath('data.mode', 'ditahan')
            ->assertJsonPath('data.keamanan.ditahan', true);
        $this->assertSame('terblokir', $data['peserta']->fresh()->status);
        $this->assertDatabaseCount('aktivitas_keamanan_ujian_cbt', 3);

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
        $this->assertDatabaseHas('aktivitas_keamanan_ujian_cbt', [
            'peserta_ujian_cbt_id' => $data['peserta']->id,
            'jenis' => 'buka_mode_aman',
            'oleh_pengguna_id' => $admin->id,
            'catatan' => null,
        ]);

        app('auth')->forgetGuards();
        $this->withToken($token)->getJson(route('api.v1.ujian-saya.kerjakan', [
            'pesertaUjianCbt' => $data['peserta'],
            'perangkat' => 'NUSA Android Aman',
        ]))
            ->assertOk()
            ->assertJsonPath('data.mode', 'pengerjaan')
            ->assertJsonPath('data.keamanan.jumlah_kejadian', 3);
    }

    public function test_token_kedua_dengan_nama_perangkat_sama_tidak_bisa_mengambil_sesi_atau_mengirim_jawaban_dan_heartbeat(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $pertama = $this->token($data['pengguna']);
        $kedua = $this->token($data['pengguna']);
        $this->withToken($pertama)->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'token' => 'MULAI1', 'perangkat' => 'NUSA Android Aman',
        ])->assertOk();
        $hash = $data['peserta']->fresh()->sesi_ujian_hash;
        $this->assertNotEmpty($hash);
        app('auth')->forgetGuards();
        $this->withToken($kedua)->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'perangkat' => 'NUSA Android Aman',
        ])->assertUnprocessable()->assertJsonValidationErrors('perangkat');
        $this->withToken($kedua)->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
            'soal_ujian_cbt_id' => $data['soal_pg']->id, 'jawaban' => ['A'], 'ragu' => false,
            'perangkat' => 'NUSA Android Aman',
        ])->assertUnprocessable();
        $this->aktivitasKeamanan($kedua, $data['peserta'], 'heartbeat')->assertUnprocessable();
        $this->assertSame($hash, $data['peserta']->fresh()->sesi_ujian_hash);
        $this->assertDatabaseCount('jawaban_peserta_ujian_cbt', 0);
        app('auth')->forgetGuards();
        $this->withToken($pertama)->getJson(route('api.v1.ujian-saya.kerjakan', [
            'pesertaUjianCbt' => $data['peserta'], 'perangkat' => 'NUSA Android Aman',
        ]))->assertOk();
    }

    public function test_ujian_aktif_mobile_tidak_dapat_dibuka_dan_disimpan_dari_web(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $token = $this->token($data['pengguna']);
        $this->withToken($token)->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'token' => 'MULAI1', 'perangkat' => 'NUSA Android Aman',
        ])->assertOk();
        app('auth')->forgetGuards();
        $this->actingAs($data['pengguna']->fresh(), 'web')
            ->post(route('ujian-saya.masuk', $data['peserta']))->assertSessionHasErrors('perangkat');
        // Even an old web session established before the mobile start cannot bypass the lease.
        $this->withSession(['cbt_peserta_ujian_id' => $data['peserta']->id, 'cbt_pengguna_id' => $data['pengguna']->id])
            ->postJson(route('cbt.ujian.jawaban'), ['soal_ujian_cbt_id' => $data['soal_pg']->id, 'jawaban' => ['A']])
            ->assertUnprocessable();
        $this->assertDatabaseCount('jawaban_peserta_ujian_cbt', 0);
    }

    public function test_ujian_aktif_web_tidak_dapat_diambil_mobile_dan_browser_kedua(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $this->actingAs($data['pengguna'], 'web')->post(route('ujian-saya.masuk', $data['peserta']), ['token' => 'MULAI1'])->assertRedirect();
        $this->post(route('cbt.ujian.mulai'))->assertRedirect();
        $this->assertSame('web', $data['peserta']->fresh()->sesi_ujian_saluran);
        $hash = $data['peserta']->fresh()->sesi_ujian_hash;
        $this->get(route('cbt.ujian.kerjakan'))->assertOk();
        $this->withSession(['cbt_identitas_perangkat' => 'browser-kedua'])
            ->getJson(route('cbt.ujian.kerjakan'))->assertUnprocessable();
        app('auth')->guard('web')->logout();
        app('auth')->forgetGuards();
        $this->withToken($this->token($data['pengguna']->fresh()))->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'perangkat' => 'Web',
        ])->assertUnprocessable();
        $this->assertSame($hash, $data['peserta']->fresh()->sesi_ujian_hash);
    }

    public function test_sinkronisasi_ulang_idempotent_dan_jeda_heartbeat_menunggu_peninjauan_tanpa_sanksi(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $data['peserta']->ujianCbt->update(['deteksi_pindah_tab' => true, 'tindakan_pindah_aplikasi' => 'tahan', 'batas_pindah_aplikasi' => 1]);
        $token = $this->token($data['pengguna']);
        $this->withToken($token)->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'token' => 'MULAI1', 'perangkat' => 'NUSA Android Aman',
        ])->assertOk();
        $this->aktivitasKeamanan($token, $data['peserta'], 'heartbeat')->assertOk();
        Carbon::setTestNow(now()->addMinutes(3));
        $this->aktivitasKeamanan($token, $data['peserta'], 'heartbeat')->assertOk()
            ->assertJsonPath('data.keamanan.jumlah_perlu_ditinjau', 1)->assertJsonPath('data.keamanan.jumlah_kejadian', 0);
        $payload = ['peristiwa' => 'keluar', 'perangkat' => 'NUSA Android Aman', 'metadata' => [
            'kejadian_id' => 'e327f67b-5889-43f9-97eb-a5c51e86cc91', 'dikirim_ulang' => true,
            'waktu_klien' => '2026-09-05T08:00:05+07:00',
        ]];
        foreach (range(1, 2) as $retry) {
            $this->postJson(route('api.v1.ujian-saya.aktivitas-keamanan', $data['peserta']), $payload)->assertOk()
                ->assertJsonPath('data.keamanan.jumlah_kejadian', 1)->assertJsonPath('data.keamanan.jumlah_perlu_ditinjau', 2);
        }
        $this->assertDatabaseCount('penerimaan_keamanan_ujian_cbt', 1);
        $this->assertSame('terblokir', $data['peserta']->fresh()->status);
        $this->withToken($token)->postJson(route('api.v1.ujian-saya.aktivitas-keamanan', $data['peserta_lain']), $payload)->assertNotFound();
    }

    public function test_reset_sesi_hanya_oleh_petugas_dan_tidak_menghapus_jawaban(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $token = $this->token($data['pengguna']);
        $this->withToken($token)->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'token' => 'MULAI1', 'perangkat' => 'NUSA Android Aman',
        ])->assertOk();
        $this->withToken($token)->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
            'soal_ujian_cbt_id' => $data['soal_pg']->id, 'jawaban' => ['A'], 'ragu' => false, 'perangkat' => 'NUSA Android Aman',
        ])->assertOk();
        $route = route('api.v1.keamanan-ujian.reset-perangkat', $data['peserta']);
        $this->postJson($route, ['alasan' => 'Gangguan perangkat siswa.'])->assertForbidden();
        $admin = Pengguna::create(['nama' => 'Pengawas Reset', 'username' => 'reset.admin', 'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'administrator', 'aktif' => true, 'akun_sistem' => false, 'wajib_ganti_kata_sandi' => false]);
        $admin->daftarPeran()->attach(Peran::where('kode', 'administrator')->firstOrFail());
        Sanctum::actingAs($admin, ['mobile']);
        $this->postJson($route, ['alasan' => 'Gangguan perangkat siswa.'])->assertOk();
        $this->assertNull($data['peserta']->fresh()->sesi_ujian_hash);
        $this->assertDatabaseCount('jawaban_peserta_ujian_cbt', 1);
        $this->assertDatabaseHas('aktivitas_keamanan_ujian_cbt', ['jenis' => 'reset_perangkat', 'oleh_pengguna_id' => $admin->id]);
        app('auth')->forgetGuards();
        $this->withToken($this->token($data['pengguna']->fresh()))->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'perangkat' => 'NUSA Android Pengganti',
        ])->assertOk()->assertJsonPath('data.kemajuan.terjawab', 1);
    }

    public function test_retry_respons_hilang_tidak_menggandakan_sanksi_dan_waktu_klien_tidak_menentukan_durasi(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $data['peserta']->ujianCbt->update(['deteksi_pindah_tab' => true, 'toleransi_pindah_aplikasi_detik' => 3,
            'tindakan_pindah_aplikasi' => 'tahan', 'batas_pindah_aplikasi' => 1]);
        $token = $this->token($data['pengguna']);
        $this->withToken($token)->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'token' => 'MULAI1', 'perangkat' => 'NUSA Android Aman',
        ])->assertOk();
        $route = route('api.v1.ujian-saya.aktivitas-keamanan', $data['peserta']);
        $this->postJson($route, ['peristiwa' => 'keluar', 'perangkat' => 'NUSA Android Aman',
            'metadata' => ['kejadian_id' => '0d9e84de-b2ac-44b2-a307-1a61b0849d44', 'waktu_klien' => '2099-01-01T00:00:00Z'],
        ])->assertOk();
        Carbon::setTestNow(now()->addSeconds(5));
        $payload = ['peristiwa' => 'kembali', 'perangkat' => 'NUSA Android Aman',
            'metadata' => ['kejadian_id' => 'd7258acf-07f2-440f-b4e9-7f12d1d4c4b8', 'waktu_klien' => '2001-01-01T00:00:00Z']];
        $this->postJson($route, $payload)->assertOk()->assertJsonPath('data.mode', 'ditahan')
            ->assertJsonPath('data.durasi_kejadian_detik', 5)->assertJsonPath('data.keamanan.jumlah_kejadian', 1);
        $payload['metadata']['dikirim_ulang'] = true;
        $this->postJson($route, $payload)->assertOk()->assertJsonPath('data.kejadian_dihitung', false)
            ->assertJsonPath('data.keamanan.jumlah_kejadian', 1)->assertJsonPath('data.keamanan.jumlah_perlu_ditinjau', 0);
        $this->assertDatabaseCount('penerimaan_keamanan_ujian_cbt', 2);
        $this->assertDatabaseCount('aktivitas_keamanan_ujian_cbt', 1);
    }

    public function test_bukti_tertunda_setelah_selesai_tetap_dicatat_tanpa_mengubah_status_atau_heartbeat(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $data['peserta']->ujianCbt->update(['deteksi_pindah_tab' => true]);
        $token = $this->token($data['pengguna']);
        $this->withToken($token)->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'token' => 'MULAI1', 'perangkat' => 'NUSA Android Aman',
        ])->assertOk();
        $this->aktivitasKeamanan($token, $data['peserta'], 'heartbeat')->assertOk();
        $data['peserta']->refresh()->update(['status' => 'selesai', 'waktu_selesai' => now()]);
        $heartbeat = $data['peserta']->fresh()->heartbeat_terakhir_pada->toISOString();
        Carbon::setTestNow(now()->addMinute());
        $payload = ['peristiwa' => 'pemulihan', 'perangkat' => 'NUSA Android Aman', 'metadata' => [
            'kejadian_id' => '0d9e84de-b2ac-44b2-a307-1a61b0849d44', 'dikirim_ulang' => true,
        ]];
        for ($i = 0; $i < 2; $i++) {
            $this->postJson(route('api.v1.ujian-saya.aktivitas-keamanan', $data['peserta']), $payload)->assertOk()
                ->assertJsonPath('data.mode', 'selesai')->assertJsonPath('data.keamanan.jumlah_perlu_ditinjau', 1);
        }
        $this->aktivitasKeamanan($token, $data['peserta'], 'heartbeat')->assertOk();
        $this->assertSame($heartbeat, $data['peserta']->fresh()->heartbeat_terakhir_pada->toISOString());
        $this->assertSame('selesai', $data['peserta']->fresh()->status);
        $this->assertSame(0, $data['peserta']->fresh()->jumlah_pindah_aplikasi);
    }

    public function test_keluar_lama_yang_diterima_normal_tetap_dihitung_meski_tidak_ada_heartbeat_di_background(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $data['peserta']->ujianCbt->update(['deteksi_pindah_tab' => true, 'tindakan_pindah_aplikasi' => 'tahan', 'batas_pindah_aplikasi' => 1]);
        $token = $this->token($data['pengguna']);
        $this->withToken($token)->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'token' => 'MULAI1', 'perangkat' => 'NUSA Android Aman',
        ])->assertOk();
        $this->aktivitasKeamanan($token, $data['peserta'], 'heartbeat')->assertOk();
        $this->aktivitasKeamanan($token, $data['peserta'], 'keluar')->assertOk();
        Carbon::setTestNow(now()->addMinutes(3));
        $this->aktivitasKeamanan($token, $data['peserta'], 'kembali')->assertOk()
            ->assertJsonPath('data.mode', 'ditahan')->assertJsonPath('data.durasi_kejadian_detik', 180)
            ->assertJsonPath('data.keamanan.jumlah_kejadian', 1)->assertJsonPath('data.keamanan.jumlah_perlu_ditinjau', 0);
    }

    public function test_request_invalid_tidak_mengikat_sesi_legacy_yang_belum_diambil(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi();
        $data['peserta']->update(['status' => 'sedang_mengerjakan', 'waktu_mulai' => now()]);
        $token = $this->token($data['pengguna']);
        $this->withToken($token)->getJson(route('api.v1.ujian-saya.kerjakan', $data['peserta']))
            ->assertUnprocessable()->assertJsonValidationErrors('perangkat');
        $this->withToken($token)->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), ['perangkat' => ['tidak', 'valid']])
            ->assertUnprocessable()->assertJsonValidationErrors('perangkat');
        $this->assertNull($data['peserta']->fresh()->sesi_ujian_hash);
        $this->assertNull($data['peserta']->fresh()->perangkat_terakhir);
    }

    public function test_flag_retry_baru_tidak_memberi_jalur_bebas_sanksi_dan_tetap_menahan_akses(): void
    {
        $data = $this->fondasiKeamananKetat();
        foreach (range(1, 3) as $nomor) {
            foreach (['keluar', 'kembali'] as $event) {
                $this->kirimKeamanan($data, $event, ['dikirim_ulang' => true])
                    ->assertOk()->assertJsonPath('data.mode', 'ditahan')
                    ->assertJsonPath('data.keamanan.jumlah_kejadian', $nomor)
                    ->assertJsonPath('data.keamanan.alasan_tahanan', 'peninjauan_sesi');
                Carbon::setTestNow(now()->addSeconds(5));
            }
        }
        $this->withToken($data['token'])->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
            'soal_ujian_cbt_id' => $data['soal_pg']->id, 'jawaban' => ['A'], 'ragu' => false,
            'perangkat' => 'NUSA Android Aman',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('jawaban_peserta_ujian_cbt', 0);
        $this->assertDatabaseCount('penerimaan_keamanan_ujian_cbt', 6);
    }

    public function test_pemulihan_dengan_pemicu_palsu_tidak_menghapus_keluar_atau_membuka_kertas(): void
    {
        $data = $this->fondasiKeamananKetat();
        $this->kirimKeamanan($data, 'keluar')->assertOk();
        Carbon::setTestNow(now()->addSeconds(5));
        $this->kirimKeamanan($data, 'pemulihan', ['pemicu' => 'pemilih-berkas'])->assertOk()
            ->assertJsonPath('data.mode', 'ditahan')->assertJsonPath('data.keamanan.jumlah_kejadian', 1);
        $this->assertSame(1, $data['peserta']->aktivitasKeamananUjianCbt()->where('jenis', 'keluar_aplikasi')->whereNull('selesai_pada')->count());
        $this->kirimKeamanan($data, 'heartbeat')->assertOk()->assertJsonPath('data.mode', 'ditahan');
        $this->withToken($data['token'])->getJson(route('api.v1.ujian-saya.kerjakan', [
            'pesertaUjianCbt' => $data['peserta'], 'perangkat' => 'NUSA Android Aman',
        ]))->assertOk()->assertJsonPath('data.mode', 'ditahan')->assertJsonMissingPath('data.soal');
        $admin = $this->petugasKeamanan();
        app(KeamananUjianService::class)->bukaTahanan($admin, $data['peserta']->fresh(), 'Gangguan sesi telah diperiksa.');
        $keluar = $data['peserta']->aktivitasKeamananUjianCbt()->where('jenis', 'keluar_aplikasi')->firstOrFail();
        $this->assertNotNull($keluar->selesai_pada);
        $this->assertTrue($keluar->dihitung);
        $this->assertSame($admin->id, $keluar->metadata['diselesaikan_oleh_pengguna_id']);
        $this->withToken($data['token'])->getJson(route('api.v1.ujian-saya.kerjakan', [
            'pesertaUjianCbt' => $data['peserta'], 'perangkat' => 'NUSA Android Aman',
        ]))->assertOk()->assertJsonPath('data.mode', 'pengerjaan');
    }

    public function test_jeda_sesi_pada_penulisan_langsung_menahan_tanpa_rollback_atau_menghapus_jawaban(): void
    {
        $data = $this->fondasiKeamananKetat();
        $url = route('api.v1.ujian-saya.jawaban.update', $data['peserta']);
        $payload = ['soal_ujian_cbt_id' => $data['soal_pg']->id, 'jawaban' => ['A'], 'ragu' => false, 'perangkat' => 'NUSA Android Aman'];
        $this->withToken($data['token'])->putJson($url, $payload)->assertOk();
        $mulai = $data['peserta']->fresh()->waktu_mulai->toISOString();
        Carbon::setTestNow(now()->addSeconds(121));
        $payload['jawaban'] = ['B'];
        $this->putJson($url, $payload)->assertUnprocessable()->assertJsonPath('data.keamanan.alasan_tahanan', 'peninjauan_sesi');
        $this->assertSame('terblokir', $data['peserta']->fresh()->status);
        $this->assertSame($mulai, $data['peserta']->fresh()->waktu_mulai->toISOString());
        $this->assertSame(['A'], $data['peserta']->jawabanPesertaUjianCbt()->firstOrFail()->jawaban);
        $this->assertSame(0, $data['peserta']->fresh()->jumlah_pindah_aplikasi);
    }

    public function test_jeda_sesi_tidak_bisa_dilewati_dengan_mulai_lanjut_detail_atau_heartbeat(): void
    {
        $data = $this->fondasiKeamananKetat();
        Carbon::setTestNow(now()->addSeconds(121));
        $this->withToken($data['token'])->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'perangkat' => 'NUSA Android Aman',
        ])->assertOk()->assertJsonPath('data.mode', 'ditahan')->assertJsonMissingPath('data.soal');
        $this->getJson(route('api.v1.ujian-saya.show', $data['peserta']))->assertOk()
            ->assertJsonPath('data.mode', 'ditahan');
        $this->kirimKeamanan($data, 'heartbeat')->assertOk()->assertJsonPath('data.mode', 'ditahan');
    }

    public function test_simpan_keluar_memerlukan_pembukaan_petugas_dan_tidak_menghapus_jawaban_atau_menambah_waktu(): void
    {
        $data = $this->fondasiKeamananKetat();
        $this->withToken($data['token'])->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
            'soal_ujian_cbt_id' => $data['soal_pg']->id, 'jawaban' => ['A'], 'ragu' => false, 'perangkat' => 'NUSA Android Aman',
        ])->assertOk();
        $mulai = $data['peserta']->fresh()->waktu_mulai->toISOString();
        $id = (string) Str::uuid();
        $this->kirimKeamanan($data, 'keluar_pengerjaan', ['kejadian_id' => $id])->assertOk()
            ->assertJsonPath('data.mode', 'ditahan')->assertJsonPath('data.keamanan.alasan_tahanan', 'keluar_pengerjaan')
            ->assertJsonPath('data.keamanan.jumlah_kejadian', 0);
        $this->postJson(route('api.v1.keamanan-ujian.buka', $data['peserta']))->assertForbidden();
        $admin = $this->petugasKeamanan();
        Sanctum::actingAs($admin, ['mobile']);
        $this->postJson(route('api.v1.keamanan-ujian.buka', $data['peserta']))->assertOk()
            ->assertJsonPath('data.keamanan.alasan_tahanan', null);
        Carbon::setTestNow(now()->addSeconds(15));
        app('auth')->forgetGuards();
        // Respons keluar pertama hilang: UUID yang sudah diakui tidak boleh menahan ulang.
        $this->kirimKeamanan($data, 'keluar_pengerjaan', ['kejadian_id' => $id, 'dikirim_ulang' => true])->assertOk()
            ->assertJsonPath('data.mode', 'pengerjaan');
        $this->assertSame($mulai, $data['peserta']->fresh()->waktu_mulai->toISOString());
        $this->assertSame(['A'], $data['peserta']->jawabanPesertaUjianCbt()->firstOrFail()->jawaban);
        $this->assertDatabaseHas('aktivitas_keamanan_ujian_cbt', ['jenis' => 'buka_mode_aman', 'oleh_pengguna_id' => $admin->id]);
        $this->assertSame(1785, app(BatasWaktuPesertaUjianCbtService::class)->sisaDetik($data['peserta']->fresh()));
    }

    public function test_interval_keluar_belum_selesai_memblokir_penulisan_sampai_kembali(): void
    {
        $data = $this->fondasiKeamananKetat();
        $this->kirimKeamanan($data, 'keluar')->assertOk();
        Carbon::setTestNow(now()->addSeconds(5));
        $url = route('api.v1.ujian-saya.jawaban.update', $data['peserta']);
        $payload = ['soal_ujian_cbt_id' => $data['soal_pg']->id, 'jawaban' => ['A'], 'ragu' => false, 'perangkat' => 'NUSA Android Aman'];
        $this->withToken($data['token'])->putJson($url, $payload)->assertUnprocessable();
        $this->kirimKeamanan($data, 'kembali')->assertOk()->assertJsonPath('data.keamanan.jumlah_kejadian', 1);
        $this->putJson($url, $payload)->assertOk();
    }

    public function test_mode_catat_tidak_menahan_gangguan_dan_deadline_tetap_dapat_menyelesaikan_sesi(): void
    {
        $data = $this->fondasiKeamananKetat();
        $data['peserta']->ujianCbt->update(['tindakan_pindah_aplikasi' => 'catat']);
        $this->kirimKeamanan($data, 'pemulihan', ['tertunda' => true])->assertOk()
            ->assertJsonPath('data.mode', 'pengerjaan')->assertJsonPath('data.keamanan.jumlah_kejadian', 0);
        $data['peserta']->ujianCbt->update(['tindakan_pindah_aplikasi' => 'tahan']);
        Carbon::setTestNow(now()->addMinutes(31));
        $this->withToken($data['token'])->postJson(route('api.v1.ujian-saya.selesai', $data['peserta']), [
            'perangkat' => 'NUSA Android Aman',
        ])->assertOk()->assertJsonPath('data.mode', 'selesai');
        $this->assertSame('selesai', $data['peserta']->fresh()->status);
    }

    public function test_interval_keluar_tidak_dapat_dilewati_dengan_pengumpulan_sebelum_deadline(): void
    {
        $data = $this->fondasiKeamananKetat();
        Carbon::setTestNow(now()->addMinutes(16));
        // Simulasikan heartbeat yang benar-benar diterima sesaat sebelum keluar.
        $data['peserta']->forceFill(['heartbeat_terakhir_pada' => now()])->save();
        $this->kirimKeamanan($data, 'keluar')->assertOk();
        $this->withToken($data['token'])->postJson(route('api.v1.ujian-saya.selesai', $data['peserta']), [
            'perangkat' => 'NUSA Android Aman',
        ])->assertOk()->assertJsonPath('data.mode', 'ditahan');
        $this->assertSame('terblokir', $data['peserta']->fresh()->status);
        $this->assertNull($data['peserta']->fresh()->waktu_selesai);
    }

    public function test_simpan_keluar_mode_catat_mencatat_interval_tanpa_menahan(): void
    {
        $data = $this->fondasiKeamananKetat();
        $data['peserta']->ujianCbt->update(['tindakan_pindah_aplikasi' => 'catat']);
        $this->kirimKeamanan($data, 'keluar_pengerjaan')->assertOk()->assertJsonPath('data.mode', 'pengerjaan');
        Carbon::setTestNow(now()->addSeconds(5));
        $this->kirimKeamanan($data, 'kembali')->assertOk()->assertJsonPath('data.keamanan.jumlah_kejadian', 1);
        $this->assertSame('sedang_mengerjakan', $data['peserta']->fresh()->status);
    }

    public function test_jeda_heartbeat_tanpa_ikatan_satu_perangkat_tetap_dapat_ditinjau(): void
    {
        $data = $this->fondasiKeamananKetat(batasiPerangkat: false);
        $this->assertNull($data['peserta']->fresh()->sesi_ujian_mulai_pada);
        Carbon::setTestNow(now()->addSeconds(121));
        $this->kirimKeamanan($data, 'heartbeat')->assertOk()->assertJsonPath('data.mode', 'ditahan')
            ->assertJsonPath('data.keamanan.alasan_tahanan', 'peninjauan_sesi')
            ->assertJsonPath('data.keamanan.jumlah_kejadian', 0);
    }

    public function test_kegagalan_proteksi_layar_dan_klaim_picker_tanpa_izin_memerlukan_peninjauan_bukan_penalti(): void
    {
        $data = $this->fondasiKeamananKetat();
        $this->kirimKeamanan($data, 'pemulihan', ['pemicu' => 'proteksi-layar-gagal'])->assertOk()
            ->assertJsonPath('data.mode', 'ditahan')->assertJsonPath('data.kejadian_dihitung', false)
            ->assertJsonPath('data.keamanan.jumlah_kejadian', 0)->assertJsonPath('data.keamanan.alasan_tahanan', 'peninjauan_sesi');
        app(KeamananUjianService::class)->bukaTahanan($this->petugasKeamanan(), $data['peserta']->fresh());
        $this->kirimKeamanan($data, 'pemulihan', ['pemicu' => 'pemilih-berkas'])->assertOk()
            ->assertJsonPath('data.mode', 'ditahan')->assertJsonPath('data.keamanan.jumlah_kejadian', 0);
    }

    public function test_izin_berkas_hanya_untuk_soal_upload_milik_peserta_dan_retry_tidak_memperpanjang_izin(): void
    {
        $data = $this->fondasiKeamananKetat();
        $this->mulaiIzinBerkas($data)->assertUnprocessable();
        $data['soal_pg']->soalCbt->update(['jenis_soal' => 'upload_file']);
        $id = (string) Str::uuid();
        $response = $this->mulaiIzinBerkas($data, $id)->assertOk()->assertJsonPath('data.diterima', true)
            ->assertJsonPath('data.izin.sisa_detik', 60)->assertJsonMissingPath('data.izin.sesi_hash');
        $izin = $response->json('data.izin');
        Carbon::setTestNow(now()->addSeconds(20));
        $this->mulaiIzinBerkas($data, $id)->assertOk()->assertJsonPath('data.izin.id', $izin['id'])
            ->assertJsonPath('data.izin.berlaku_sampai', $izin['berlaku_sampai'])->assertJsonPath('data.izin.sisa_detik', 40);
        $this->mulaiIzinBerkas($data)->assertUnprocessable();
        $this->assertSame(1, $data['peserta']->aktivitasKeamananUjianCbt()->where('jenis', 'pemilih_berkas')->count());
        $this->withToken($data['token'])->postJson(route('api.v1.ujian-saya.izin-berkas.mulai', $data['peserta_lain']), [
            'soal_ujian_cbt_id' => $data['soal_pg']->id, 'permintaan_id' => (string) Str::uuid(), 'perangkat' => 'NUSA Android Aman',
        ])->assertNotFound();
    }

    public function test_izin_aktif_menolak_penulisan_dan_unggah_tanpa_menghapus_jawaban_lama(): void
    {
        Storage::fake('public');
        $data = $this->fondasiKeamananKetat();
        $payload = ['soal_ujian_cbt_id' => $data['soal_pg']->id, 'jawaban' => ['A'], 'ragu' => false, 'perangkat' => 'NUSA Android Aman'];
        $url = route('api.v1.ujian-saya.jawaban.update', $data['peserta']);
        $this->withToken($data['token'])->putJson($url, $payload)->assertOk();
        $data['soal_pg']->soalCbt->update(['jenis_soal' => 'upload_file']);
        $this->mulaiIzinBerkas($data)->assertOk();
        $payload['jawaban'] = ['B'];
        $this->putJson($url, $payload)->assertUnprocessable();
        $this->postJson(route('api.v1.ujian-saya.jawaban-berkas.store', $data['peserta']), [
            'soal_ujian_cbt_id' => $data['soal_pg']->id, 'berkas' => UploadedFile::fake()->create('jawaban.pdf', 10, 'application/pdf'),
            'ragu' => false, 'perangkat' => 'NUSA Android Aman',
        ])->assertUnprocessable();
        $this->assertSame(['A'], $data['peserta']->jawabanPesertaUjianCbt()->firstOrFail()->jawaban);
        $this->assertSame('sedang_mengerjakan', $data['peserta']->fresh()->status);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_picker_dibatalkan_atau_dipilih_ditutup_idempoten_tanpa_sanksi_dan_kuota_tidak_bisa_diperbarui_sendiri(): void
    {
        $data = $this->fondasiKeamananKetat();
        $data['soal_pg']->soalCbt->update(['jenis_soal' => 'upload_file']);
        $mulai = $data['peserta']->fresh()->waktu_mulai->toISOString();
        foreach (['dibatalkan', 'dipilih', 'gagal'] as $hasil) {
            $izin = $this->mulaiIzinBerkas($data)->assertOk()->json('data.izin.id');
            Carbon::setTestNow(now()->addSeconds(5));
            $this->selesaiIzinBerkas($data, $izin, $hasil)->assertOk()->assertJsonPath('data.diterima', true)
                ->assertJsonPath('data.mode', 'pengerjaan')->assertJsonPath('data.keamanan.jumlah_kejadian', 0);
            $this->selesaiIzinBerkas($data, $izin, 'masa_habis')->assertOk()->assertJsonPath('data.diterima', true);
        }
        $this->mulaiIzinBerkas($data)->assertOk()->assertJsonPath('data.diterima', false)
            ->assertJsonPath('data.mode', 'ditahan')->assertJsonPath('data.keamanan.jumlah_kejadian', 0);
        app(KeamananUjianService::class)->bukaTahanan($this->petugasKeamanan(), $data['peserta']->fresh());
        $this->mulaiIzinBerkas($data)->assertOk()->assertJsonPath('data.diterima', true);
        $this->assertSame($mulai, $data['peserta']->fresh()->waktu_mulai->toISOString());
    }

    public function test_izin_kedaluwarsa_menahan_pada_penulisan_dan_tidak_bisa_dicuci_dengan_close_atau_heartbeat(): void
    {
        $data = $this->fondasiKeamananKetat();
        $data['soal_pg']->soalCbt->update(['jenis_soal' => 'upload_file']);
        $izin = $this->mulaiIzinBerkas($data)->assertOk()->json('data.izin.id');
        Carbon::setTestNow(now()->addSeconds(60));
        $this->withToken($data['token'])->putJson(route('api.v1.ujian-saya.jawaban.update', $data['peserta']), [
            'soal_ujian_cbt_id' => $data['soal_pg']->id, 'jawaban' => null, 'ragu' => false, 'perangkat' => 'NUSA Android Aman',
        ])->assertUnprocessable()->assertJsonPath('data.keamanan.alasan_tahanan', 'peninjauan_sesi');
        $this->selesaiIzinBerkas($data, $izin, 'dipilih')->assertOk()->assertJsonPath('data.diterima', false)
            ->assertJsonPath('data.mode', 'ditahan')->assertJsonPath('data.keamanan.jumlah_kejadian', 0);
        $this->kirimKeamanan($data, 'heartbeat')->assertOk()->assertJsonPath('data.mode', 'ditahan');
        $this->assertDatabaseCount('jawaban_peserta_ujian_cbt', 0);
    }

    public function test_izin_dibatasi_sesi_terautentikasi_meski_pembatas_satu_perangkat_dimatikan(): void
    {
        $data = $this->fondasiKeamananKetat(false);
        $data['soal_pg']->soalCbt->update(['jenis_soal' => 'upload_file']);
        $permintaan = (string) Str::uuid();
        $izin = $this->mulaiIzinBerkas($data, $permintaan)->assertOk()->json('data.izin.id');
        $tokenLain = $this->token($data['pengguna']);
        app('auth')->forgetGuards();
        $this->withToken($tokenLain)->postJson(route('api.v1.ujian-saya.izin-berkas.selesai', $data['peserta']), [
            'izin_id' => $izin, 'hasil' => 'dipilih', 'perangkat' => 'NUSA Android Aman',
        ])->assertForbidden();
        $data['token'] = $tokenLain;
        $this->mulaiIzinBerkas($data, $permintaan)->assertForbidden();
        $this->assertSame(1, $data['peserta']->aktivitasKeamananUjianCbt()->where('jenis', 'pemilih_berkas')->whereNull('selesai_pada')->count());
    }

    public function test_permintaan_izin_baru_setelah_kedaluwarsa_tidak_merollback_keputusan_peninjauan(): void
    {
        $data = $this->fondasiKeamananKetat();
        $data['soal_pg']->soalCbt->update(['jenis_soal' => 'upload_file']);
        $this->mulaiIzinBerkas($data)->assertOk();
        Carbon::setTestNow(now()->addSeconds(61));
        $this->mulaiIzinBerkas($data)->assertOk()->assertJsonPath('data.diterima', false)
            ->assertJsonPath('data.mode', 'ditahan')->assertJsonPath('data.keamanan.jumlah_kejadian', 0);
        $this->assertSame('terblokir', $data['peserta']->fresh()->status);
        $this->assertSame(0, $data['peserta']->aktivitasKeamananUjianCbt()->where('jenis', 'pemilih_berkas')->whereNull('selesai_pada')->count());
        $this->assertSame(1, $data['peserta']->aktivitasKeamananUjianCbt()->where('jenis', 'pemilih_berkas')->count());
    }

    public function test_buka_ulang_kertas_saat_izin_terbuka_memerlukan_pengawas_dan_petugas_menutup_izin(): void
    {
        $data = $this->fondasiKeamananKetat();
        $data['soal_pg']->soalCbt->update(['jenis_soal' => 'upload_file']);
        $this->mulaiIzinBerkas($data)->assertOk();
        $this->withToken($data['token'])->getJson(route('api.v1.ujian-saya.kerjakan', [
            'pesertaUjianCbt' => $data['peserta'], 'perangkat' => 'NUSA Android Aman',
        ]))->assertOk()->assertJsonPath('data.mode', 'ditahan')->assertJsonMissingPath('data.soal');
        $admin = $this->petugasKeamanan();
        app(KeamananUjianService::class)->bukaTahanan($admin, $data['peserta']->fresh());
        $izin = $this->mulaiIzinBerkas($data)->assertOk()->json('data.izin.id');
        app(KeamananUjianService::class)->resetPerangkat($admin, $data['peserta']->fresh(), 'Pergantian perangkat disetujui');
        $aktivitas = $data['peserta']->aktivitasKeamananUjianCbt()->where('metadata->izin_id', $izin)->firstOrFail();
        $this->assertSame($admin->id, $aktivitas->metadata['diselesaikan_oleh_pengguna_id']);
        $this->assertNotNull($aktivitas->selesai_pada);
    }

    public function test_mode_catat_dan_batas_waktu_ujian_tetap_berlaku_pada_izin_berkas(): void
    {
        $data = $this->fondasiKeamananKetat();
        $data['soal_pg']->soalCbt->update(['jenis_soal' => 'upload_file']);
        $data['peserta']->ujianCbt->update(['tindakan_pindah_aplikasi' => 'catat']);
        $izin = $this->mulaiIzinBerkas($data)->assertOk()->json('data.izin.id');
        Carbon::setTestNow(now()->addSeconds(60));
        $this->selesaiIzinBerkas($data, $izin)->assertOk()->assertJsonPath('data.diterima', false)
            ->assertJsonPath('data.mode', 'pengerjaan')->assertJsonPath('data.keamanan.jumlah_perlu_ditinjau', 1);
        $data['peserta']->ujianCbt->update(['tindakan_pindah_aplikasi' => 'tahan']);
        Carbon::setTestNow('2026-09-05 08:29:50');
        $data['peserta']->update(['heartbeat_terakhir_pada' => now()]);
        $this->mulaiIzinBerkas($data)->assertOk()->assertJsonPath('data.izin.sisa_detik', 10);
        Carbon::setTestNow(now()->addSeconds(10));
        $this->withToken($data['token'])->postJson(route('api.v1.ujian-saya.selesai', $data['peserta']), ['perangkat' => 'NUSA Android Aman'])
            ->assertOk()->assertJsonPath('data.mode', 'selesai');
        $this->assertSame(0, $data['peserta']->fresh()->jumlah_pindah_aplikasi);
    }

    public function test_nol_toleransi_mobile_menghitung_saat_keluar_nol_detik_sekali_per_interval_dan_uuid(): void
    {
        $data = $this->fondasiKeamananKetat();
        foreach (range(1, 3) as $nomor) {
            $id = (string) Str::uuid();
            $this->kirimKeamanan($data, 'keluar', ['kejadian_id' => $id])->assertOk()
                ->assertJsonPath('data.kejadian_dihitung', true)->assertJsonPath('data.keamanan.jumlah_kejadian', $nomor)
                ->assertJsonPath('data.keamanan.nol_toleransi_mobile', true)->assertJsonPath('data.keamanan.toleransi_mobile_detik', 0);
            $this->kirimKeamanan($data, 'keluar', ['kejadian_id' => $id, 'dikirim_ulang' => true])->assertOk()
                ->assertJsonPath('data.kejadian_dihitung', false)->assertJsonPath('data.keamanan.jumlah_kejadian', $nomor);
            $this->kirimKeamanan($data, 'keluar')->assertOk()->assertJsonPath('data.keamanan.jumlah_kejadian', $nomor);
            $this->kirimKeamanan($data, 'kembali')->assertOk()->assertJsonPath('data.kejadian_dihitung', false)
                ->assertJsonPath('data.durasi_kejadian_detik', 0)->assertJsonPath('data.keamanan.jumlah_kejadian', $nomor);
        }
        $this->assertSame('terblokir', $data['peserta']->fresh()->status);
        $this->assertSame('batas_kejadian', app(KeamananUjianService::class)->ringkas($data['peserta']->fresh())['alasan_tahanan']);
        $this->assertSame(3, $data['peserta']->aktivitasKeamananUjianCbt()->where('jenis', 'keluar_aplikasi')->count());
    }

    public function test_versi_jawaban_stabil_dan_draft_lama_tidak_menimpa_jawaban_server_yang_berubah(): void
    {
        $data = $this->fondasiKeamananKetat();
        $versi = $this->withToken($data['token'])->getJson(route('api.v1.ujian-saya.kerjakan', [
            'pesertaUjianCbt' => $data['peserta'], 'perangkat' => 'NUSA Android Aman',
        ]))->assertOk()->json('data.soal.0.versi_jawaban');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $versi);
        $url = route('api.v1.ujian-saya.jawaban.update', $data['peserta']);
        $payload = ['soal_ujian_cbt_id' => $data['soal_pg']->id, 'jawaban' => ['A'], 'ragu' => false,
            'perangkat' => 'NUSA Android Aman', 'versi_jawaban' => $versi];
        $versiBaru = $this->putJson($url, $payload)->assertOk()->json('data.versi_jawaban');
        $this->assertNotSame($versi, $versiBaru);
        $this->getJson(route('api.v1.ujian-saya.kerjakan', ['pesertaUjianCbt' => $data['peserta'], 'perangkat' => 'NUSA Android Aman']))
            ->assertOk()->assertJsonPath('data.soal.0.versi_jawaban', $versiBaru);
        $payload['jawaban'] = ['B'];
        $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('versi_jawaban');
        $this->assertSame(['A'], $data['peserta']->jawabanPesertaUjianCbt()->firstOrFail()->jawaban);
        $payload['versi_jawaban'] = $versiBaru;
        $this->putJson($url, $payload)->assertOk();
        $this->assertSame(['B'], $data['peserta']->jawabanPesertaUjianCbt()->firstOrFail()->jawaban);
    }

    public function test_pemulihan_draft_harus_menunggu_pengawas_dan_tidak_melampaui_deadline(): void
    {
        $data = $this->fondasiKeamananKetat();
        $versi = $this->withToken($data['token'])->getJson(route('api.v1.ujian-saya.kerjakan', [
            'pesertaUjianCbt' => $data['peserta'], 'perangkat' => 'NUSA Android Aman',
        ]))->assertOk()->json('data.soal.0.versi_jawaban');
        $payload = ['soal_ujian_cbt_id' => $data['soal_pg']->id, 'jawaban' => ['A'], 'ragu' => false,
            'perangkat' => 'NUSA Android Aman', 'versi_jawaban' => $versi];
        $this->kirimKeamanan($data, 'pemulihan', ['pemicu' => 'buka-ulang-sesi'])->assertOk()->assertJsonPath('data.mode', 'ditahan');
        $url = route('api.v1.ujian-saya.jawaban.update', $data['peserta']);
        $this->putJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('jawaban_peserta_ujian_cbt', 0);
        app(KeamananUjianService::class)->bukaTahanan($this->petugasKeamanan(), $data['peserta']->fresh());
        $payload['versi_jawaban'] = $this->putJson($url, $payload)->assertOk()->json('data.versi_jawaban');
        Carbon::setTestNow(now()->addMinutes(31));
        $payload['jawaban'] = ['B'];
        $this->putJson($url, $payload)->assertOk()->assertJsonPath('data.mode', 'selesai');
        $this->assertSame(['A'], $data['peserta']->jawabanPesertaUjianCbt()->firstOrFail()->jawaban);
    }

    private function mulaiIzinBerkas(array $data, ?string $id = null)
    {
        return $this->withToken($data['token'])->postJson(route('api.v1.ujian-saya.izin-berkas.mulai', $data['peserta']), [
            'soal_ujian_cbt_id' => $data['soal_pg']->id, 'permintaan_id' => $id ?? (string) Str::uuid(), 'perangkat' => 'NUSA Android Aman',
        ]);
    }

    private function selesaiIzinBerkas(array $data, string $id, string $hasil = 'dipilih')
    {
        return $this->withToken($data['token'])->postJson(route('api.v1.ujian-saya.izin-berkas.selesai', $data['peserta']), [
            'izin_id' => $id, 'hasil' => $hasil, 'perangkat' => 'NUSA Android Aman',
        ]);
    }

    private function fondasiKeamananKetat(bool $batasiPerangkat = true): array
    {
        Carbon::setTestNow('2026-09-05 08:00:00');
        $data = $this->fondasi(hanyaObjektif: true);
        $data['peserta']->ujianCbt->update(['deteksi_pindah_tab' => true, 'tindakan_pindah_aplikasi' => 'tahan',
            'batas_pindah_aplikasi' => 3, 'toleransi_pindah_aplikasi_detik' => 3, 'batasi_satu_perangkat' => $batasiPerangkat]);
        $data['token'] = $this->token($data['pengguna']);
        $this->withToken($data['token'])->postJson(route('api.v1.ujian-saya.mulai', $data['peserta']), [
            'token' => 'MULAI1', 'perangkat' => 'NUSA Android Aman',
        ])->assertOk();

        return $data;
    }

    private function petugasKeamanan(): Pengguna
    {
        $admin = Pengguna::create(['nama' => 'Pengawas Paket Keamanan', 'username' => 'pengawas.paket', 'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'administrator', 'aktif' => true, 'akun_sistem' => false, 'wajib_ganti_kata_sandi' => false]);
        $admin->daftarPeran()->attach(Peran::where('kode', 'administrator')->firstOrFail());

        return $admin;
    }

    private function kirimKeamanan(array $data, string $event, array $metadata = [])
    {
        return $this->withToken($data['token'])->postJson(route('api.v1.ujian-saya.aktivitas-keamanan', $data['peserta']), [
            'peristiwa' => $event, 'perangkat' => 'NUSA Android Aman',
            'metadata' => array_merge(['kejadian_id' => (string) Str::uuid()], $metadata),
        ]);
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
