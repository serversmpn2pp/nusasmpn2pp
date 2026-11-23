<?php

namespace Tests\Feature\Api;

use App\Models\FolderSoalCbt;
use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankSoalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_soal_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.bank-soal.index'))->assertUnauthorized();
        $this->getJson(route('api.v1.bank-soal.pratinjau', 1))->assertUnauthorized();
        [, , $mapel] = $this->fondasiAkademik();
        $soal = SoalCbt::create($this->dataSoalModel($mapel, 'TANPA-IZIN'));

        $pengguna = Pengguna::create([
            'nama' => 'Pegawai Tanpa CBT',
            'username' => 'tanpa.bank.soal',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.bank-soal.index'))
            ->assertForbidden();
        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.bank-soal.pratinjau', $soal))
            ->assertForbidden();
    }

    public function test_administrator_dapat_mengelola_soal_dan_media_secara_native(): void
    {
        Storage::fake('public');
        [$tahun, , $mapel] = $this->fondasiAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $payload = $this->payload($mapel, [
            'tahun_pelajaran_id' => $tahun->id,
            'skor_maksimal' => 99,
            'tabel' => [['Besaran', 'Nilai'], ['Frekuensi', '2 Hz']],
            'tabel_judul' => 'Hasil pengamatan',
            'rumus_latex' => 'f = \\frac{n}{t}',
            'rumus_keterangan' => 'Rumus frekuensi',
        ]);

        $response = $this->withToken($this->token($administrator))
            ->post(route('api.v1.bank-soal.store'), [
                'payload' => json_encode($payload),
                'gambar_soal' => UploadedFile::fake()->createWithContent(
                    'getaran.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
                ),
            ])
            ->assertCreated()
            ->assertJsonPath('data.jenis_soal', 'pilihan_ganda')
            ->assertJsonPath('data.skor_maksimal', 2)
            ->assertJsonPath('data.jawaban.opsi.1.benar', true)
            ->assertJsonPath('data.media.tabel.judul', 'Hasil pengamatan')
            ->assertJsonPath('data.media.rumus.latex', 'f = \\frac{n}{t}')
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);

        $soalId = (int) $response->json('data.id');
        $soal = SoalCbt::findOrFail($soalId);
        $this->assertMatchesRegularExpression('/^SOAL-CBT-\d{8}-\d{3}$/', $soal->kode);
        $this->assertSame('2.00', $soal->skor_maksimal);
        Storage::disk('public')->assertExists(data_get($soal->media, 'gambar.path'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.bank-soal.index', [
                'mata_pelajaran_id' => $mapel->id,
                'tingkat' => 8,
                'status' => 'siap',
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.items.0.id', $soalId)
            ->assertJsonPath('data.referensi.konteks.0.mata_pelajaran_id', $mapel->id)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);

        $ubah = $this->payload($mapel, [
            'jenis_soal' => 'pilihan_ganda_kompleks',
            'kunci_pg' => null,
            'kunci_pgk' => ['B', 'C'],
            'pertanyaan' => 'Pilih dua pernyataan yang benar.',
            'aksi' => 'simpan_draf',
            'hapus_gambar_soal' => true,
            'tabel' => [],
            'rumus_latex' => '',
        ]);
        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.bank-soal.update', $soal), ['payload' => json_encode($ubah)])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.jawaban.opsi.1.benar', true)
            ->assertJsonPath('data.jawaban.opsi.2.benar', true)
            ->assertJsonPath('data.media.gambar', null)
            ->assertJsonPath('data.media.tabel', null);

        $this->withToken($this->token($administrator))
            ->deleteJson(route('api.v1.bank-soal.destroy', $soal))
            ->assertOk();
        $this->assertDatabaseHas('soal_cbt', ['id' => $soalId, 'status' => 'arsip', 'aktif' => false]);
    }

    public function test_api_mempertahankan_opsi_nol_di_penyimpanan_pembaruan_dan_pratinjau(): void
    {
        [, , $mapel] = $this->fondasiAkademik();
        $this->withToken($this->token(Pengguna::where('username', 'administrator')->firstOrFail()));
        foreach (['pilihan_ganda', 'pilihan_ganda_kompleks'] as $jenis) {
            $data = $this->payload($mapel, ['jenis_soal' => $jenis, 'opsi' => ['a' => ' 0 ', 'B' => '1', 'C' => '  '],
                'kunci_pg' => 'B', 'kunci_pgk' => ['A', 'B']]);
            $hasil = $this->postJson(route('api.v1.bank-soal.store'), ['payload' => json_encode($data)])
                ->assertCreated()->assertJsonCount(2, 'data.jawaban.opsi')
                ->assertJsonPath('data.jawaban.opsi.0.kode', 'A')->assertJsonPath('data.jawaban.opsi.0.teks', '0');
            $soal = SoalCbt::findOrFail($hasil->json('data.id'));
            $this->assertSame(['A' => '0', 'B' => '1'], $soal->opsi['pilihan']);
            $this->postJson(route('api.v1.bank-soal.update', $soal), ['payload' => json_encode([...$data, 'kunci_pg' => 'A', 'kunci_pgk' => ['A']])])
                ->assertOk()->assertJsonPath('data.jawaban.opsi.0.teks', '0')->assertJsonPath('data.jawaban.opsi.0.benar', true);
            $this->getJson(route('api.v1.bank-soal.pratinjau', $soal))->assertOk()->assertJsonCount(2, 'data.soal.pilihan')
                ->assertJsonPath('data.soal.pilihan.0.kode', 'A')->assertJsonPath('data.soal.pilihan.0.teks', '0')
                ->assertJsonMissingPath('data.soal.pilihan.0.benar')->assertJsonMissingPath('data.soal.kunci_jawaban');
        }
        $this->postJson(route('api.v1.bank-soal.store'), ['payload' => json_encode($this->payload($mapel, [
            'opsi' => ['A' => '0', 'B' => '  '], 'kunci_pg' => 'A',
        ]))])->assertUnprocessable()->assertJsonValidationErrors('opsi');
    }

    public function test_api_mempertahankan_kunci_dan_pengecoh_nol(): void
    {
        [, , $mapel] = $this->fondasiAkademik();
        $this->withToken($this->token(Pengguna::where('username', 'administrator')->firstOrFail()));
        foreach (['isian_singkat', 'numerik'] as $jenis) {
            $hasil = $this->postJson(route('api.v1.bank-soal.store'), ['payload' => json_encode($this->payload($mapel, [
                'jenis_soal' => $jenis, 'kunci_teks' => ' 0 ', 'rubrik_teks' => '0',
            ]))])->assertCreated();
            $soal = SoalCbt::findOrFail($hasil->json('data.id'));
            $this->assertSame(['jawaban' => '0'], $soal->kunci_jawaban);
            $this->assertSame(['catatan' => '0'], $soal->rubrik);
        }
        $hasil = $this->postJson(route('api.v1.bank-soal.store'), ['payload' => json_encode($this->payload($mapel, [
            'jenis_soal' => 'menjodohkan', 'pasangan' => [['kiri' => 'Jumlah', 'kanan' => '1']],
            'pengecoh_menjodohkan' => ['0', ' ', '0'],
        ]))])->assertCreated()->assertJsonPath('data.jawaban.pengecoh_menjodohkan', ['0']);
        $preview = $this->getJson(route('api.v1.bank-soal.pratinjau', $hasil->json('data.id')))->assertOk();
        $this->assertEqualsCanonicalizing(['0', '1'], array_column($preview->json('data.soal.pilihan'), 'teks'));
    }

    public function test_semua_bentuk_jawaban_dinormalisasi_oleh_server(): void
    {
        [, , $mapel] = $this->fondasiAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);
        $bentuk = [
            'benar_salah' => [
                'pernyataan' => [
                    ['teks' => 'Bumi mengelilingi Matahari.', 'jawaban' => true],
                    ['teks' => 'Matahari mengelilingi Bumi.', 'jawaban' => false],
                ],
            ],
            'menjodohkan' => [
                'pasangan' => [
                    ['kiri' => 'Indonesia', 'kanan' => 'Jakarta'],
                    ['kiri' => 'Jepang', 'kanan' => 'Tokyo'],
                ],
                'pengecoh_menjodohkan' => ['Seoul'],
            ],
            'isian_singkat' => ['kunci_teks' => 'Hertz'],
            'numerik' => ['kunci_teks' => '2'],
            'uraian' => ['kunci_teks' => '', 'rubrik_teks' => 'Menjelaskan proses dengan benar.'],
            'upload_file' => ['kunci_teks' => '', 'rubrik_teks' => 'Berkas dapat dibuka.'],
        ];

        foreach ($bentuk as $jenis => $tambahan) {
            $payload = $this->payload($mapel, [
                'jenis_soal' => $jenis,
                'pertanyaan' => 'Pertanyaan untuk '.$jenis,
                ...$tambahan,
            ]);
            $response = $this->withToken($token)
                ->postJson(route('api.v1.bank-soal.store'), ['payload' => json_encode($payload)])
                ->assertCreated()
                ->assertJsonPath('data.jenis_soal', $jenis);

            if ($jenis === 'menjodohkan') {
                $response->assertJsonPath('data.jawaban.pengecoh_menjodohkan.0', 'Seoul');
            }

            $preview = $this->withToken($token)
                ->getJson(route('api.v1.bank-soal.pratinjau', $response->json('data.id')))
                ->assertOk()
                ->assertJsonPath('data.mode', 'pratinjau')
                ->assertJsonPath('data.soal.jenis', $jenis)
                ->assertJsonPath('data.soal.jawaban', [])
                ->assertJsonMissingPath('data.soal.kunci_jawaban')
                ->assertJsonMissingPath('data.soal.rubrik')
                ->assertJsonMissingPath('data.soal.pembahasan');
            if ($jenis === 'menjodohkan') {
                $this->assertEqualsCanonicalizing(['Jakarta', 'Tokyo', 'Seoul'], array_column($preview->json('data.soal.pilihan'), 'teks'));
                $preview->assertJsonMissingPath('data.soal.pasangan.0.kanan');
            }
        }

        $this->assertSame(6, SoalCbt::query()->count());
        $this->assertSame(false, data_get(SoalCbt::where('jenis_soal', 'benar_salah')->firstOrFail()->kunci_jawaban, 'jawaban.2'));
        $soalMenjodohkan = SoalCbt::where('jenis_soal', 'menjodohkan')->firstOrFail();
        $this->assertSame('Tokyo', data_get($soalMenjodohkan->kunci_jawaban, 'jawaban.2'));
        $this->assertSame(['Seoul'], data_get($soalMenjodohkan->opsi, 'pengecoh'));
    }

    public function test_detail_dan_pratinjau_memuat_media_per_bagian_tanpa_memulai_ujian(): void
    {
        Storage::fake('public');
        [, , $mapel] = $this->fondasiAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);
        foreach (['utama', 'stimulus', 'pilihan_A', 'pernyataan_1', 'kiri_1', 'kanan_1', 'pengecoh_1'] as $nama) {
            $bundles[$nama] = ['gambar' => ['path' => 'soal-cbt/'.$nama.'.png', 'alt' => 'Diagram '.$nama]];
            Storage::disk('public')->put('soal-cbt/'.$nama.'.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        }
        $soal = SoalCbt::create([
            ...$this->dataSoalModel($mapel, 'MEDIA-PRATINJAU'),
            'stimulus' => 'Baca diagram.',
            'pembahasan' => 'RAHASIA PEMBAHASAN',
            'media' => [...$bundles['utama'], 'konten' => $bundles],
        ]);
        $sebelum = $soal->fresh()->getAttributes();
        $url = fn (string $nama) => url(Storage::disk('public')->url('soal-cbt/'.$nama.'.png'));

        foreach (['pilihan_ganda', 'pilihan_ganda_kompleks'] as $jenis) {
            $soal->update(['jenis_soal' => $jenis]);
            $this->withToken($token)->getJson(route('api.v1.bank-soal.show', $soal))
                ->assertOk()
                ->assertJsonPath('data.media.gambar.url', $url('utama'))
                ->assertJsonPath('data.stimulus_media.gambar.url', $url('stimulus'))
                ->assertJsonPath('data.jawaban.opsi.0.media.gambar.url', $url('pilihan_A'));
            $this->withToken($token)->getJson(route('api.v1.bank-soal.pratinjau', $soal))
                ->assertOk()
                ->assertHeader('Cache-Control')
                ->assertJsonPath('data.soal.media.gambar.url', $url('utama'))
                ->assertJsonPath('data.soal.stimulus_media.gambar.url', $url('stimulus'))
                ->assertJsonPath('data.soal.pilihan.0.media.gambar.url', $url('pilihan_A'))
                ->assertJsonMissingPath('data.soal.pilihan.0.benar');
        }
        $soal->update(['jenis_soal' => 'benar_salah', 'opsi' => ['pernyataan' => [
            ['teks' => 'Amati gambar.', 'media_key' => 'pernyataan_1'],
        ]]]);
        $this->withToken($token)->getJson(route('api.v1.bank-soal.pratinjau', $soal))
            ->assertOk()->assertJsonPath('data.soal.pernyataan.0.nomor', '1')
            ->assertJsonPath('data.soal.pernyataan.0.media.gambar.url', $url('pernyataan_1'));
        $this->withToken($token)->getJson(route('api.v1.bank-soal.show', $soal))
            ->assertOk()->assertJsonPath('data.jawaban.pernyataan.0.media.gambar.url', $url('pernyataan_1'));

        $soal->update(['jenis_soal' => 'menjodohkan', 'opsi' => [
            'pasangan' => [['kiri' => 'Bangun', 'kanan' => 'Persegi', 'media_kiri_key' => 'kiri_1', 'media_kanan_key' => 'kanan_1']],
            'pengecoh' => ['Segitiga'],
            'pengecoh_media' => [['teks' => 'Segitiga', 'media_key' => 'pengecoh_1']],
        ]]);
        $preview = $this->withToken($token)->getJson(route('api.v1.bank-soal.pratinjau', $soal))
            ->assertOk()->assertJsonPath('data.soal.pasangan.0.nomor', '1')
            ->assertJsonPath('data.soal.pasangan.0.media.gambar.url', $url('kiri_1'))
            ->assertJsonMissingPath('data.soal.pasangan.0.kanan')
            ->assertJsonMissingPath('data.soal.kunci_jawaban');
        $byText = collect($preview->json('data.soal.pilihan'))->keyBy('teks');
        $this->assertStringContainsString('no-store', $preview->headers->get('Cache-Control'));
        $this->assertSame($url('kanan_1'), data_get($byText, 'Persegi.media.gambar.url'));
        $this->assertSame($url('pengecoh_1'), data_get($byText, 'Segitiga.media.gambar.url'));
        $this->withToken($token)->getJson(route('api.v1.bank-soal.show', $soal))
            ->assertOk()->assertJsonPath('data.jawaban.pasangan.0.media_kiri.gambar.url', $url('kiri_1'))
            ->assertJsonPath('data.jawaban.pasangan.0.media_kanan.gambar.url', $url('kanan_1'));

        $this->assertSame($sebelum['kunci_jawaban'], $soal->fresh()->getAttributes()['kunci_jawaban']);
        foreach (['ujian_cbt', 'peserta_ujian_cbt', 'jawaban_peserta_ujian_cbt', 'aktivitas_keamanan_ujian_cbt'] as $tabel) {
            $this->assertDatabaseCount($tabel, 0);
        }
        $sebelumPratinjau = $soal->fresh()->getAttributes();
        $this->withToken($token)->getJson(route('api.v1.bank-soal.pratinjau', $soal))->assertOk();
        $this->assertSame($sebelumPratinjau, $soal->fresh()->getAttributes());
    }

    public function test_tingkat_kesulitan_wajib_diisi_pada_api_bank_soal(): void
    {
        [, , $mapel] = $this->fondasiAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $payload = $this->payload($mapel);
        unset($payload['tingkat_kesulitan']);

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.bank-soal.store'), ['payload' => json_encode($payload)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tingkat_kesulitan');

        $this->assertDatabaseCount('soal_cbt', 0);
    }

    public function test_daftar_bank_soal_mobile_mendukung_folder_dan_urutan_tanggal_pembuatan(): void
    {
        [, , $mapel] = $this->fondasiAkademik();
        $this->withToken($this->token(Pengguna::where('username', 'administrator')->firstOrFail()));
        $folder = FolderSoalCbt::create(['mata_pelajaran_id' => $mapel->id, 'tingkat' => 8, 'nama' => 'Sumatif Akhir Semester']);
        $kosong = FolderSoalCbt::create(['mata_pelajaran_id' => $mapel->id, 'tingkat' => 8, 'nama' => 'Ulangan Bab 1']);
        foreach (range(1, 7) as $nomor) {
            $soal = new SoalCbt($this->dataSoalModel($mapel, 'URUT-'.$nomor));
            $soal->created_at = match ($nomor) {
                1 => '2026-09-01 08:00:00',
                2, 3 => '2026-10-02 08:00:00',
                default => '2026-10-01 08:00:00',
            };
            $soal->updated_at = $nomor === 1 ? '2026-11-20 08:00:00' : '2026-11-01 08:00:00';
            $soal->save();
            if ($nomor < 7) {
                $folder->soal()->attach($soal);
            }
        }
        SoalCbt::where('kode', 'URUT-6')->update(['created_at' => null]);

        $parameter = ['per_halaman' => 5, 'status' => 'siap', 'kata_kunci' => 'URUT'];
        $this->getJson(route('api.v1.bank-soal.index', $parameter))
            ->assertOk()->assertJsonPath('data.filter.urutan', 'terbaru')
            ->assertJsonPath('data.filter.folder', 'semua')
            ->assertJsonPath('data.items.0.kode', 'URUT-3')
            ->assertJsonPath('data.referensi.folder.0', [
                'id' => $folder->id,
                'nama' => 'Sumatif Akhir Semester',
                'nama_mata_pelajaran' => 'Matematika',
                'tingkat' => 8,
                'jumlah_soal' => 6,
            ])
            ->assertJsonPath('data.referensi.folder.1.jumlah_soal', 0);

        $diFolder = $parameter + ['folder' => $folder->id, 'urutan' => 'terlama'];
        $hasil = $this->getJson(route('api.v1.bank-soal.index', $diFolder))->assertOk()
            ->assertJsonPath('data.filter.folder', (string) $folder->id)
            ->assertJsonPath('data.filter.mata_pelajaran_id', $mapel->id)
            ->assertJsonPath('data.filter.tingkat', '8')
            ->assertJsonPath('data.filter.urutan', 'terlama')
            ->assertJsonPath('data.paginasi.total', 6)
            ->assertJsonPath('data.paginasi.ada_halaman_berikutnya', true);
        $this->assertSame(['URUT-1', 'URUT-4', 'URUT-5', 'URUT-2', 'URUT-3'], array_column($hasil->json('data.items'), 'kode'));
        $this->getJson(route('api.v1.bank-soal.index', $diFolder + ['halaman' => 2]))->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.kode', 'URUT-6');
        $this->getJson(route('api.v1.bank-soal.index', ['folder' => 'belum']))->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.kode', 'URUT-7');
        $this->getJson(route('api.v1.bank-soal.index', ['folder' => $kosong->id]))->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson(route('api.v1.bank-soal.index', ['folder' => 999999]))->assertNotFound();
        $this->getJson(route('api.v1.bank-soal.index', ['urutan' => 'acak', 'folder' => 'folder-tidak-valid']))
            ->assertUnprocessable()->assertJsonValidationErrors(['urutan', 'folder']);
    }

    public function test_guru_hanya_melihat_dan_mengubah_bank_soal_yang_diajar(): void
    {
        [$tahun, $kelas, $mapel, $pegawai] = $this->fondasiAkademik();
        $mapelLain = MataPelajaran::create([
            'kode' => 'IPA-CBT-LAIN',
            'nama' => 'IPA',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $guru = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $guru->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());
        $soalSendiri = SoalCbt::create($this->dataSoalModel($mapel, 'SOAL-GURU-001'));
        $soalLain = SoalCbt::create($this->dataSoalModel($mapelLain, 'SOAL-LAIN-001'));
        $folderSendiri = FolderSoalCbt::create(['mata_pelajaran_id' => $mapel->id, 'tingkat' => 8, 'nama' => 'Folder mapel sendiri']);
        $folderLain = FolderSoalCbt::create(['mata_pelajaran_id' => $mapelLain->id, 'tingkat' => 8, 'nama' => 'Folder mapel lain']);
        $tingkatLain = FolderSoalCbt::create(['mata_pelajaran_id' => $mapel->id, 'tingkat' => 7, 'nama' => 'Folder tingkat lain']);
        $folderSendiri->soal()->attach([$soalSendiri->id, $soalLain->id]);
        $token = $this->token($guru);

        $this->withToken($token)
            ->getJson(route('api.v1.bank-soal.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.items.0.id', $soalSendiri->id)
            ->assertJsonCount(1, 'data.referensi.folder')
            ->assertJsonPath('data.referensi.folder.0.id', $folderSendiri->id)
            ->assertJsonPath('data.referensi.folder.0.jumlah_soal', 1)
            ->assertJsonMissing(['id' => $soalLain->id]);

        $this->getJson(route('api.v1.bank-soal.index', ['folder' => $folderSendiri->id]))
            ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $soalSendiri->id);
        foreach ([$folderLain, $tingkatLain] as $folder) {
            $this->getJson(route('api.v1.bank-soal.index', ['folder' => $folder->id]))->assertForbidden();
        }

        $this->withToken($token)
            ->getJson(route('api.v1.bank-soal.show', $soalLain))
            ->assertForbidden();
        $this->withToken($token)
            ->getJson(route('api.v1.bank-soal.pratinjau', $soalLain))
            ->assertForbidden();
        $this->withToken($token)
            ->getJson(route('api.v1.bank-soal.pratinjau', $soalSendiri))
            ->assertOk();

        $payloadLain = $this->payload($mapelLain);
        $this->withToken($token)
            ->postJson(route('api.v1.bank-soal.store'), ['payload' => json_encode($payloadLain)])
            ->assertForbidden();

        $payloadSendiri = $this->payload($mapel, ['tahun_pelajaran_id' => $tahun->id]);
        $this->withToken($token)
            ->postJson(route('api.v1.bank-soal.store'), ['payload' => json_encode($payloadSendiri)])
            ->assertCreated();

        $this->assertDatabaseHas('guru_mata_pelajaran', [
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'pegawai_id' => $pegawai->id,
        ]);
    }

    private function fondasiAkademik(): array
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
        $mapel = MataPelajaran::create([
            'kode' => 'MTK-CBT-MOBILE',
            'nama' => 'Matematika',
            'tingkat' => 8,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Bank Soal',
            'nip' => '198701012026091001',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'pegawai_id' => $pegawai->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);

        return [$tahun, $kelas, $mapel, $pegawai];
    }

    private function payload(MataPelajaran $mapel, array $tambahan = []): array
    {
        return [
            'mata_pelajaran_id' => $mapel->id,
            'tingkat' => 8,
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'mots',
            'topik' => 'Getaran',
            'pertanyaan' => 'Satuan frekuensi adalah ....',
            'skor_maksimal' => 99,
            'aksi' => 'simpan_siap',
            'opsi' => ['A' => 'Meter', 'B' => 'Hertz', 'C' => 'Sekon', 'D' => 'Newton'],
            'kunci_pg' => 'B',
            ...$tambahan,
        ];
    }

    private function dataSoalModel(MataPelajaran $mapel, string $kode): array
    {
        return [
            'mata_pelajaran_id' => $mapel->id,
            'tingkat' => 8,
            'kode' => $kode,
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'mots',
            'pertanyaan' => 'Pertanyaan '.$kode,
            'opsi' => ['pilihan' => ['A' => 'Salah', 'B' => 'Benar']],
            'kunci_jawaban' => ['jawaban' => 'B'],
            'skor_maksimal' => 2,
            'status' => 'siap',
            'aktif' => true,
        ];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
