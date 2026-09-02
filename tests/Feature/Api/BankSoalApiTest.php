<?php

namespace Tests\Feature\Api;

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
    }

    public function test_administrator_dapat_mengelola_soal_dan_media_secara_native(): void
    {
        Storage::fake('public');
        [$tahun, , $mapel] = $this->fondasiAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $payload = $this->payload($mapel, [
            'tahun_pelajaran_id' => $tahun->id,
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
            ->assertJsonPath('data.jawaban.opsi.1.benar', true)
            ->assertJsonPath('data.media.tabel.judul', 'Hasil pengamatan')
            ->assertJsonPath('data.media.rumus.latex', 'f = \\frac{n}{t}')
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);

        $soalId = (int) $response->json('data.id');
        $soal = SoalCbt::findOrFail($soalId);
        $this->assertMatchesRegularExpression('/^SOAL-CBT-\d{8}-\d{3}$/', $soal->kode);
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
            $this->withToken($token)
                ->postJson(route('api.v1.bank-soal.store'), ['payload' => json_encode($payload)])
                ->assertCreated()
                ->assertJsonPath('data.jenis_soal', $jenis);
        }

        $this->assertSame(6, SoalCbt::query()->count());
        $this->assertSame(false, data_get(SoalCbt::where('jenis_soal', 'benar_salah')->firstOrFail()->kunci_jawaban, 'jawaban.2'));
        $this->assertSame('Tokyo', data_get(SoalCbt::where('jenis_soal', 'menjodohkan')->firstOrFail()->kunci_jawaban, 'jawaban.2'));
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
        $token = $this->token($guru);

        $this->withToken($token)
            ->getJson(route('api.v1.bank-soal.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.items.0.id', $soalSendiri->id)
            ->assertJsonMissing(['id' => $soalLain->id]);

        $this->withToken($token)
            ->getJson(route('api.v1.bank-soal.show', $soalLain))
            ->assertForbidden();

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
            'kategori' => 'umum',
            'topik' => 'Getaran',
            'pertanyaan' => 'Satuan frekuensi adalah ....',
            'skor_maksimal' => 1,
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
            'kategori' => 'umum',
            'pertanyaan' => 'Pertanyaan '.$kode,
            'opsi' => ['pilihan' => ['A' => 'Salah', 'B' => 'Benar']],
            'kunci_jawaban' => ['jawaban' => 'B'],
            'skor_maksimal' => 1,
            'status' => 'siap',
            'aktif' => true,
        ];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
