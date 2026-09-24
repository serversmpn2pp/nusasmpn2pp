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
        $pesertaAktif = $this->buatPeserta($ujianAktif, $kelasUjian, $anggota, 'AKTIF-001', ruang: $ruang, nomorMeja: 7);

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
            ->assertDontSee('Matematika Selesai')
            ->assertSee('Riwayat')
            ->assertSee('Ruang 01')
            ->assertSee('Kode meja')
            ->assertSee('Sesi Pagi')
            ->assertSee('Token dari pengawas')
            ->assertSee('Masuk Ujian')
            ->assertDontSee('Ujian Rahasia Siswa Lain')
            ->assertDontSee('Siswa Rahasia Kelas Lain');

        $this->get(route('ujian-saya.index', ['tab' => 'riwayat']))
            ->assertOk()
            ->assertSee('Matematika Selesai')
            ->assertDontSee('Matematika Akan Datang')
            ->assertDontSee('Matematika Aktif')
            ->assertDontSee('Ujian Rahasia Siswa Lain');

        $pesertaAktif->update(['status' => 'terblokir']);
        $this->get(route('ujian-saya.index'))
            ->assertOk()
            ->assertViewHas('ujianAktif', fn ($daftar) => $daftar->pluck('peserta.id')->all() === [$pesertaAktif->id])
            ->assertSee('Ditahan Mode Aman')
            ->assertSee('Hubungi pengawas untuk membuka kembali akses')
            ->assertDontSee('Matematika Selesai')
            ->assertDontSee('Masuk Ujian');
    }

    public function test_nilai_cbt_terpusat_hanya_tampil_untuk_peserta_sendiri_setelah_dipublikasikan(): void
    {
        Carbon::setTestNow('2026-12-01 08:00:00');

        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = $this->buatKelas($tahun, 'VIII.A');
        $kelasLain = $this->buatKelas($tahun, 'VIII.B');
        [, $anggota, $akun] = $this->buatSiswaBerakun($kelas, 'Siswa Pemilik Nilai', '0131201160');
        [, $anggotaLain] = $this->buatSiswaBerakun($kelasLain, 'Siswa Nilai Lain', '0131201161');
        $mapel = MataPelajaran::create([
            'kode' => 'BIND8',
            'nama' => 'Bahasa Indonesia',
            'tingkat' => 8,
            'kkm' => 70,
            'aktif' => true,
        ]);
        $jenis = JenisUjianCbt::firstOrCreate(
            ['kode' => 'STS'],
            ['nama' => 'Sumatif Tengah Semester', 'memerlukan_token' => true, 'dapat_diterapkan_ke_nilai' => true, 'urutan' => 1, 'aktif' => true],
        );
        $ujian = $this->buatUjian(
            $jenis, $tahun, $mapel, 'CBT-NILAI-PUBLIK', 'STS Bahasa Indonesia',
            '2026-11-20 07:30:00', '2026-11-20 09:00:00', 'selesai',
        );
        $ujian->update(['jumlah_soal' => 2]);
        $kelasUjian = KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $kelas->id]);
        $kelasUjianLain = KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $kelasLain->id]);
        $peserta = $this->buatPeserta($ujian, $kelasUjian, $anggota, 'NILAI-001', status: 'selesai');
        $pesertaLain = $this->buatPeserta($ujian, $kelasUjianLain, $anggotaLain, 'NILAI-002', status: 'selesai');

        foreach ([1, 3] as $nomor => $bobot) {
            $soal = SoalCbt::create([
                'tahun_pelajaran_id' => $tahun->id,
                'mata_pelajaran_id' => $mapel->id,
                'tingkat' => 8,
                'kode' => 'NILAI-PUBLIK-'.($nomor + 1),
                'jenis_soal' => $nomor === 0 ? 'pilihan_ganda' : 'pilihan_ganda_kompleks',
                'tingkat_kesulitan' => $nomor === 0 ? 'mudah' : 'sulit',
                'kategori' => 'lots',
                'pertanyaan' => 'Pertanyaan '.($nomor + 1),
                'opsi' => ['pilihan' => ['A' => 'Benar', 'B' => 'Benar juga', 'C' => 'Salah']],
                'kunci_jawaban' => ['jawaban' => $nomor === 0 ? 'A' : ['A', 'B']],
                'skor_maksimal' => $bobot,
                'status' => 'siap',
                'aktif' => true,
            ]);
            $relasiSoal = $ujian->soalUjianCbt()->create([
                'soal_cbt_id' => $soal->id,
                'nomor_urut' => $nomor + 1,
                'bobot' => $bobot,
            ]);
            $peserta->jawabanPesertaUjianCbt()->create([
                'soal_ujian_cbt_id' => $relasiSoal->id,
                'soal_cbt_id' => $soal->id,
                'jawaban' => ['A'],
                'skor' => $nomor === 0 ? 1 : 2,
            ]);
            $pesertaLain->jawabanPesertaUjianCbt()->create([
                'soal_ujian_cbt_id' => $relasiSoal->id,
                'soal_cbt_id' => $soal->id,
                'jawaban' => ['A'],
                'skor' => $bobot,
            ]);
        }

        $this->actingAs($akun)
            ->get(route('ujian-saya.index', ['tab' => 'riwayat']))
            ->assertOk()
            ->assertSee('Belum dipublikasikan')
            ->assertDontSee('75,00');

        $ujian->update(['tampilkan_hasil' => true]);
        $this->get(route('ujian-saya.index', ['tab' => 'riwayat']))
            ->assertOk()
            ->assertSee('Belum dipublikasikan')
            ->assertDontSee('75,00');

        $ujian->update(['hasil_difinalisasi_pada' => now()]);
        $this->get(route('ujian-saya.index'))
            ->assertOk()
            ->assertDontSee('75,00');
        $this->get(route('ujian-saya.index', ['tab' => 'riwayat']))
            ->assertOk()
            ->assertSee('75,00')
            ->assertDontSee('100,00');

        $jawabanKedua = $peserta->jawabanPesertaUjianCbt()->orderByDesc('id')->firstOrFail();
        $jawabanKedua->update(['skor' => null]);
        $this->get(route('ujian-saya.index', ['tab' => 'riwayat']))
            ->assertOk()
            ->assertSee('Belum tersedia')
            ->assertDontSee('75,00');

        $jawabanKedua->update(['skor' => 0]);
        $this->get(route('ujian-saya.index', ['tab' => 'riwayat']))
            ->assertOk()
            ->assertSee('25,00');

        $peserta->jawabanPesertaUjianCbt()->orderBy('id')->firstOrFail()->update(['skor' => 0]);
        $this->get(route('ujian-saya.index', ['tab' => 'riwayat']))
            ->assertOk()
            ->assertSee('0,00')
            ->assertDontSee('Belum tersedia');

        $ujian->update(['tampilkan_hasil' => false]);
        $this->get(route('ujian-saya.index', ['tab' => 'riwayat']))
            ->assertOk()
            ->assertSee('Belum dipublikasikan')
            ->assertDontSee('0,00');
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
        $ujian->update([
            'token' => 'MASUK1',
            'jumlah_soal' => 2,
            'deteksi_pindah_tab' => true,
            'toleransi_pindah_aplikasi_detik' => 3,
            'batas_pindah_aplikasi' => 3,
            'tindakan_pindah_aplikasi' => 'tahan',
        ]);
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
            'stimulus' => 'Perhatikan data organ pernapasan.',
            'pertanyaan' => 'Organ pernapasan utama manusia adalah ....',
            'opsi' => [
                ['kode' => 'A', 'teks' => 'Paru-paru'],
                ['kode' => 'B', 'teks' => 'Lambung'],
                ['kode' => 'C', 'teks' => 'Ginjal'],
                ['kode' => 'D', 'teks' => 'Usus'],
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

        $this->postJson(route('cbt.ujian.aktivitas-keamanan'), ['peristiwa' => 'tidak_valid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('peristiwa');
        $this->postJson(route('cbt.ujian.aktivitas-keamanan'), [
            'peristiwa' => 'heartbeat',
            'metadata' => ['kolom_bebas' => 'tidak boleh disimpan'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metadata');

        $responsHeartbeat = $this->withHeader('User-Agent', 'Chrome Android Pengujian')
            ->postJson(route('cbt.ujian.aktivitas-keamanan'), [
                'peristiwa' => 'heartbeat',
                'metadata' => [
                    'visibility' => 'visible',
                    'pemicu' => 'timer',
                    'fullscreen' => false,
                    'online' => true,
                    'waktu_klien' => '2026-12-01T08:00:00+07:00',
                ],
            ])
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJsonPath('data.mode', 'pengerjaan')
            ->assertJsonPath('data.keamanan.jumlah_kejadian', 0);
        $this->assertStringContainsString('no-store', (string) $responsHeartbeat->headers->get('Cache-Control'));
        $this->assertNotNull($peserta->fresh()->heartbeat_terakhir_pada);

        $this->postJson(route('cbt.ujian.aktivitas-keamanan'), ['peristiwa' => 'keluar'])
            ->assertOk();
        $this->postJson(route('cbt.ujian.aktivitas-keamanan'), ['peristiwa' => 'keluar'])
            ->assertOk();
        Carbon::setTestNow(now()->addSeconds(4));
        $this->postJson(route('cbt.ujian.aktivitas-keamanan'), [
            'peristiwa' => 'kembali',
            'metadata' => ['visibility' => 'visible', 'pemicu' => 'visibilitychange'],
        ])
            ->assertOk()
            ->assertJsonPath('data.kejadian_dihitung', true)
            ->assertJsonPath('data.durasi_kejadian_detik', 4)
            ->assertJsonPath('data.keamanan.jumlah_kejadian', 1)
            ->assertJsonPath('data.keamanan.sisa_kejadian', 2);
        $this->assertDatabaseCount('aktivitas_keamanan_ujian_cbt', 1);
        $this->assertDatabaseHas('aktivitas_keamanan_ujian_cbt', [
            'peserta_ujian_cbt_id' => $peserta->id,
            'jenis' => 'keluar_aplikasi',
            'durasi_detik' => 4,
            'dihitung' => true,
        ]);

        $this->post(route('cbt.logout'))
            ->assertRedirect(route('ujian-saya.index'))
            ->assertSessionMissing('cbt_peserta_ujian_id');
        $this->postJson(route('cbt.ujian.aktivitas-keamanan'), ['peristiwa' => 'heartbeat'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Sesi ujian tidak aktif.');

        $this->actingAs($akun)
            ->post(route('ujian-saya.masuk', $peserta))
            ->assertRedirect(route('cbt.ujian.show'));

        $this->get(route('cbt.ujian.kerjakan'))
            ->assertOk()
            ->assertSee('Organ pernapasan utama manusia adalah')
            ->assertSee('Gas yang dibutuhkan manusia untuk bernapas')
            ->assertSee('Perhatikan data organ pernapasan')
            ->assertSee('Rumus volume udara')
            ->assertSee('Ciri organ')
            ->assertSee('Pertukaran gas')
            ->assertSee('Soal 1 dari 2')
            ->assertSee('Jawaban disimpan otomatis')
            ->assertSee('Sisa waktu')
            ->assertSee('data-security-detection="1"', false)
            ->assertSee('data-security-held="0"', false)
            ->assertSee(route('cbt.ujian.aktivitas-keamanan'), false)
            ->assertSee('Peringatan aktivitas ujian')
            ->assertSee('Ujian sementara ditahan')
            ->assertSee('Kumpulkan Ujian');

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

        foreach (range(2, 3) as $kejadian) {
            Carbon::setTestNow(now()->addSecond());
            $this->postJson(route('cbt.ujian.aktivitas-keamanan'), ['peristiwa' => 'keluar'])
                ->assertOk();
            Carbon::setTestNow(now()->addSeconds(4));
            $this->postJson(route('cbt.ujian.aktivitas-keamanan'), ['peristiwa' => 'kembali'])
                ->assertOk()
                ->assertJsonPath('data.keamanan.jumlah_kejadian', $kejadian);
        }

        $this->assertSame('terblokir', $peserta->fresh()->status);
        $this->get(route('cbt.ujian.kerjakan'))
            ->assertOk()
            ->assertSee('data-security-held="1"', false)
            ->assertSee('Ujian sementara ditahan')
            ->assertSee('Minta pengawas membuka kembali akses ujian Anda.')
            ->assertSee('inert', false);
        $this->postJson(route('cbt.ujian.jawaban'), [
            'soal_ujian_cbt_id' => $relasiSoalPertama->id,
            'jawaban' => ['A'],
        ])->assertStatus(409);

        Carbon::setTestNow($peserta->waktu_mulai->copy()->addMinutes(91));
        $this->post(route('cbt.ujian.simpan'), [
            'aksi' => 'selesai',
            'jawaban' => [
                $relasiSoalPertama->id => ['A'],
                $relasiSoalKedua->id => ['oksigen'],
            ],
            'ragu' => [$relasiSoalKedua->id => '1'],
        ])->assertRedirect(route('cbt.ujian.selesai'));
        $this->assertSame('selesai', $peserta->fresh()->status);
        $this->assertSame(['B'], $peserta->jawabanPesertaUjianCbt()
            ->where('soal_ujian_cbt_id', $relasiSoalPertama->id)
            ->firstOrFail()->jawaban);
        $this->get(route('cbt.ujian.selesai'))
            ->assertOk()
            ->assertSee('Lihat Riwayat Ujian');
        $this->post(route('cbt.logout'), ['tujuan' => 'riwayat'])
            ->assertRedirect(route('ujian-saya.index', ['tab' => 'riwayat']))
            ->assertSessionMissing('cbt_peserta_ujian_id');
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
