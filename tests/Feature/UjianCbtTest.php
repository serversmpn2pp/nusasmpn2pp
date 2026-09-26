<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalUjianCbt;
use App\Models\JawabanPesertaUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Models\SesiUjianCbt;
use App\Models\Siswa;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Skor ditentukan otomatis')
            ->assertSee('CBT-MTK-8-001')
            ->assertSee('CBT-MTK-8-002')
            ->assertDontSee('name="soal['.$soalPertama->id.'][bobot]"', false)
            ->assertDontSee('CBT-IPA-8-001');

        $this->actingAs($administrator)
            ->put(route('ujian-cbt.soal.update', $ujianCbt), [
                'soal' => [
                    $soalPertama->id => [
                        'dipilih' => '1',
                        'nomor_urut' => '1',
                        'bobot' => '99',
                    ],
                    $soalKedua->id => [
                        'dipilih' => '1',
                        'nomor_urut' => '2',
                        'bobot' => '99',
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
        $this->assertSame(['2.00', '2.00'], $ujianCbt->soalUjianCbt()->orderBy('nomor_urut')->pluck('bobot')->all());
        $this->assertSame(2, $ujianCbt->fresh()->jumlah_soal);

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
        $this->assertSame(3, $kelasUjianCbt->pesertaUjianCbt()->count());

        $peserta = PesertaUjianCbt::query()
            ->with('anggotaKelas.siswa')
            ->where('ujian_cbt_id', $ujianCbt->id)
            ->orderBy('id')
            ->firstOrFail();

        $this->assertSame($sesi->id, $peserta->sesi_ujian_cbt_id);
        $this->assertNotEmpty($peserta->nomor_peserta);

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.peserta.index', $ujianCbt))
            ->assertOk()
            ->assertSee($peserta->anggotaKelas->siswa->nama_lengkap)
            ->assertSee($peserta->anggotaKelas->siswa->nisn)
            ->assertDontSee('Password:');

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

    public function test_administrator_dapat_mengatur_ruang_nomor_meja_absensi_dan_berita_acara_cbt(): void
    {
        Storage::fake('local');

        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $jenisUjian = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatAnggotaSiswa($tahunPelajaran, $kelas, 3);

        $ujianCbt = UjianCbt::create([
            ...collect($this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                ->except('kelas_peserta')
                ->all(),
            'jumlah_soal' => 2,
            'status' => 'berlangsung',
            'token' => 'RUANG1',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        KelasUjianCbt::create([
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
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenisUjian->id,
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'kode' => 'STS-RUANG-2026',
            'nama' => 'Sumatif Tengah Semester',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-08-15',
            'tanggal_selesai' => '2026-08-15',
            'status' => 'aktif',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        $jadwal = JadwalUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'ujian_cbt_id' => $ujianCbt->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tanggal' => '2026-08-15',
            'waktu_mulai' => '08:00',
            'waktu_selesai' => '10:00',
            'label_sesi' => 'Jam 1',
            'tingkat' => $kelas->tingkat,
            'urutan' => 1,
            'status' => 'siap',
        ]);
        $jadwal->kelas()->sync([$kelas->id]);

        $this->actingAs($administrator)
            ->post(route('ujian-cbt.peserta.generate', $ujianCbt))
            ->assertRedirect(route('ujian-cbt.peserta.index', $ujianCbt));

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.ruang.index', $ujianCbt))
            ->assertOk()
            ->assertSee('Ruang ujian CBT')
            ->assertSee('Bagi peserta otomatis');

        $this->actingAs($administrator)
            ->post(route('ujian-cbt.ruang.generate', $ujianCbt), [
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'prefix' => 'LAB',
                'jumlah_ruang' => 2,
                'kapasitas' => 2,
                'lokasi' => 'Lantai 2',
            ])
            ->assertRedirect(route('ujian-cbt.ruang.index', [
                $ujianCbt,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
            ]));

        $this->assertDatabaseCount('ruang_ujian_cbt', 2);
        $this->assertDatabaseHas('ruang_ujian_cbt', [
            'ujian_cbt_id' => $ujianCbt->id,
            'jadwal_ujian_cbt_id' => $jadwal->id,
        ]);

        $this->actingAs($administrator)
            ->post(route('ujian-cbt.ruang.bagi-otomatis', $ujianCbt), [
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
            ])
            ->assertRedirect(route('ujian-cbt.ruang.index', [
                $ujianCbt,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
            ]));

        $ruang = RuangUjianCbt::query()
            ->where('ujian_cbt_id', $ujianCbt->id)
            ->orderBy('kode')
            ->get();
        $peserta = PesertaUjianCbt::query()
            ->with('anggotaKelas')
            ->where('ujian_cbt_id', $ujianCbt->id)
            ->get()
            ->sortBy(fn (PesertaUjianCbt $item) => $item->anggotaKelas?->nomor_absen)
            ->values();

        $this->assertSame($ruang[0]->id, $peserta[0]->ruang_ujian_cbt_id);
        $this->assertSame(1, $peserta[0]->nomor_meja);
        $this->assertSame($ruang[0]->id, $peserta[1]->ruang_ujian_cbt_id);
        $this->assertSame(2, $peserta[1]->nomor_meja);
        $this->assertSame($ruang[1]->id, $peserta[2]->ruang_ujian_cbt_id);
        $this->assertSame(1, $peserta[2]->nomor_meja);

        $this->actingAs($administrator)
            ->put(route('ujian-cbt.ruang.peserta.update', $ujianCbt), [
                'peserta' => [
                    $peserta[0]->id => [
                        'ruang_ujian_cbt_id' => $peserta[0]->ruang_ujian_cbt_id,
                        'nomor_meja' => $peserta[0]->nomor_meja,
                        'status_kehadiran_ujian' => 'hadir',
                        'catatan_kehadiran_ujian' => 'Hadir tepat waktu.',
                    ],
                ],
            ])
            ->assertRedirect(route('ujian-cbt.ruang.index', $ujianCbt));

        $peserta[0]->refresh();
        $this->assertSame('hadir', $peserta[0]->status_kehadiran_ujian);
        $this->assertSame('Hadir tepat waktu.', $peserta[0]->catatan_kehadiran_ujian);
        $this->assertNotNull($peserta[0]->absen_ujian_pada);

        $this->actingAs($administrator)
            ->put(route('ujian-cbt.ruang.update', [$ujianCbt, $ruang[0]]), [
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'kode' => $ruang[0]->kode,
                'nama' => 'Labor Komputer 1',
                'lokasi' => 'Lantai 2',
                'kapasitas' => 2,
                'pengawas_utama_pegawai_id' => Pegawai::firstOrFail()->id,
                'pengawas_pendamping_pegawai_id' => null,
                'waktu_mulai_aktual' => '2026-08-15 08:05',
                'waktu_selesai_aktual' => '2026-08-15 09:35',
                'berita_acara' => 'Ujian CBT berjalan tertib.',
                'hambatan' => 'Satu perangkat sempat restart.',
                'tindak_lanjut' => 'Peserta diberi tambahan waktu lima menit.',
                'catatan' => 'Catatan proktor.',
                'status' => 'selesai',
            ])
            ->assertRedirect(route('ujian-cbt.ruang.index', [
                $ujianCbt,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_ujian_cbt_id' => $ruang[0]->id,
            ]));

        $ruang[0]->refresh();
        $this->assertSame('Labor Komputer 1', $ruang[0]->nama);
        $this->assertSame('selesai', $ruang[0]->status);
        $this->assertSame('Ujian CBT berjalan tertib.', $ruang[0]->berita_acara);

        $this->actingAs($administrator)
            ->put(route('ujian-cbt.ruang.kunci', [$ujianCbt, $ruang[0]]))
            ->assertRedirect(route('ujian-cbt.ruang.index', [
                $ujianCbt,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_ujian_cbt_id' => $ruang[0]->id,
            ]));

        $ruang[0]->refresh();
        $this->assertNotNull($ruang[0]->dikunci_pada);
        $this->assertSame($administrator->id, $ruang[0]->dikunci_oleh_pengguna_id);

        $this->actingAs($administrator)
            ->from(route('ujian-cbt.ruang.index', [
                $ujianCbt,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_ujian_cbt_id' => $ruang[0]->id,
            ]))
            ->put(route('ujian-cbt.ruang.update', [$ujianCbt, $ruang[0]]), [
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'kode' => $ruang[0]->kode,
                'nama' => 'Ruang revisi setelah dikunci',
                'lokasi' => 'Lantai 2',
                'kapasitas' => 2,
                'pengawas_utama_pegawai_id' => Pegawai::firstOrFail()->id,
                'pengawas_pendamping_pegawai_id' => null,
                'status' => 'selesai',
            ])
            ->assertRedirect(route('ujian-cbt.ruang.index', [
                $ujianCbt,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_ujian_cbt_id' => $ruang[0]->id,
            ]))
            ->assertSessionHasErrors('ruang');

        $this->actingAs($administrator)
            ->from(route('ujian-cbt.ruang.index', [
                $ujianCbt,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_ujian_cbt_id' => $ruang[0]->id,
            ]))
            ->put(route('ujian-cbt.ruang.peserta.update', $ujianCbt), [
                'filter_sesi_ujian_cbt_id' => $sesi->id,
                'filter_jadwal_ujian_cbt_id' => $jadwal->id,
                'filter_ruang_ujian_cbt_id' => $ruang[0]->id,
                'peserta' => [
                    $peserta[0]->id => [
                        'ruang_ujian_cbt_id' => $peserta[0]->ruang_ujian_cbt_id,
                        'nomor_meja' => 99,
                        'status_kehadiran_ujian' => 'hadir',
                        'catatan_kehadiran_ujian' => 'Tetap hadir.',
                    ],
                ],
            ])
            ->assertRedirect(route('ujian-cbt.ruang.index', [
                $ujianCbt,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_ujian_cbt_id' => $ruang[0]->id,
            ]))
            ->assertSessionHasErrors('peserta');

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.ruang.cetak', [
                $ujianCbt,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_ujian_cbt_id' => $ruang[0]->id,
            ]))
            ->assertOk()
            ->assertSee('Daftar Hadir Peserta Ujian CBT')
            ->assertSee('Berita Acara Ujian CBT')
            ->assertSee('Tanda Tangan Peserta')
            ->assertSee('Labor Komputer 1')
            ->assertSee('Sumatif Tengah Semester')
            ->assertSee('15-08-2026 08:00')
            ->assertSee('Ujian CBT berjalan tertib.');

        $this->actingAs($administrator)
            ->post(route('ujian-cbt.ruang.bukti.update', [$ujianCbt, $ruang[0]]), [
                'bukti_daftar_hadir' => UploadedFile::fake()->create('daftar-hadir.jpg', 120, 'image/jpeg'),
                'bukti_berita_acara' => UploadedFile::fake()->create('berita-acara.pdf', 180, 'application/pdf'),
            ])
            ->assertRedirect(route('ujian-cbt.ruang.index', [
                $ujianCbt,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_ujian_cbt_id' => $ruang[0]->id,
            ]));

        $ruang[0]->refresh();
        $this->assertSame('daftar-hadir.jpg', $ruang[0]->bukti_daftar_hadir_nama_file_asli);
        $this->assertSame('berita-acara.pdf', $ruang[0]->bukti_berita_acara_nama_file_asli);
        $this->assertSame($administrator->id, $ruang[0]->bukti_daftar_hadir_diunggah_oleh_pengguna_id);
        Storage::disk('local')->assertExists($ruang[0]->bukti_daftar_hadir_lokasi_file);
        Storage::disk('local')->assertExists($ruang[0]->bukti_berita_acara_lokasi_file);

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.ruang.bukti.download', [$ujianCbt, $ruang[0], 'daftar-hadir']))
            ->assertOk();

        $lokasiDaftarHadir = $ruang[0]->bukti_daftar_hadir_lokasi_file;
        $this->actingAs($administrator)
            ->delete(route('ujian-cbt.ruang.bukti.destroy', [$ujianCbt, $ruang[0], 'daftar-hadir']))
            ->assertRedirect(route('ujian-cbt.ruang.index', [
                $ujianCbt,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_ujian_cbt_id' => $ruang[0]->id,
            ]));

        $ruang[0]->refresh();
        $this->assertNull($ruang[0]->bukti_daftar_hadir_lokasi_file);
        $this->assertNotNull($ruang[0]->bukti_berita_acara_lokasi_file);
        Storage::disk('local')->assertMissing($lokasiDaftarHadir);

        $pesertaRuangKedua = $peserta[2]->fresh();
        $this->actingAs($administrator)
            ->delete(route('ujian-cbt.ruang.destroy', [$ujianCbt, $ruang[1]]))
            ->assertRedirect(route('ujian-cbt.ruang.index', [
                $ujianCbt,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
            ]));

        $this->assertDatabaseMissing('ruang_ujian_cbt', [
            'id' => $ruang[1]->id,
        ]);
        $pesertaRuangKedua->refresh();
        $this->assertNull($pesertaRuangKedua->ruang_ujian_cbt_id);
        $this->assertNull($pesertaRuangKedua->nomor_meja);
    }

    public function test_pengawas_dapat_memindai_kartu_pelajar_dan_mencatat_presensi_ruangnya(): void
    {
        Carbon::setTestNow('2026-08-15 07:30:00');

        try {
            [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
            $jenisUjian = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
            $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
            $this->buatAnggotaSiswa($tahunPelajaran, $kelas, 3);

            $ujianCbt = UjianCbt::create([
                ...collect($this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                    ->except('kelas_peserta')
                    ->all(),
                'status' => 'terjadwal',
                'dibuat_oleh_pengguna_id' => $administrator->id,
            ]);
            $kelasUjian = KelasUjianCbt::create([
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
                'status' => 'aktif',
            ]);
            $kegiatan = KegiatanUjianCbt::create([
                'jenis_ujian_cbt_id' => $jenisUjian->id,
                'tahun_pelajaran_id' => $tahunPelajaran->id,
                'kode' => 'STS-PRESENSI-2026',
                'nama' => 'Sumatif Tengah Semester',
                'semester' => 'ganjil',
                'tanggal_mulai' => '2026-08-15',
                'tanggal_selesai' => '2026-08-15',
                'status' => 'aktif',
                'dibuat_oleh_pengguna_id' => $administrator->id,
            ]);
            $jadwal = JadwalUjianCbt::create([
                'kegiatan_ujian_cbt_id' => $kegiatan->id,
                'ujian_cbt_id' => $ujianCbt->id,
                'mata_pelajaran_id' => $mataPelajaran->id,
                'tanggal' => '2026-08-15',
                'waktu_mulai' => '08:00',
                'waktu_selesai' => '10:00',
                'label_sesi' => 'Jam 1',
                'tingkat' => $kelas->tingkat,
                'urutan' => 1,
                'status' => 'siap',
            ]);
            $jadwal->kelas()->sync([$kelas->id]);

            $pegawaiPengawas = Pegawai::firstOrFail();
            $pengawas = Pengguna::create([
                'pegawai_id' => $pegawaiPengawas->id,
                'nama' => $pegawaiPengawas->nama_lengkap,
                'username' => $pegawaiPengawas->nip,
                'kata_sandi' => 'rahasia-pengawas',
                'peran' => 'pegawai',
                'aktif' => true,
            ]);
            $pengawas->daftarPeran()->attach(Peran::where('kode', 'pegawai')->firstOrFail());
            $this->assertFalse($pengawas->memilikiIzin('cbt.presensi'));

            $ruangSatu = RuangUjianCbt::create([
                'ujian_cbt_id' => $ujianCbt->id,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'kode' => 'R-01',
                'nama' => 'Ruang 1',
                'lokasi' => 'Kelas VIII.A',
                'kapasitas' => 2,
                'pengawas_utama_pegawai_id' => $pegawaiPengawas->id,
                'status' => 'siap',
            ]);
            $ruangDua = RuangUjianCbt::create([
                'ujian_cbt_id' => $ujianCbt->id,
                'sesi_ujian_cbt_id' => $sesi->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'kode' => 'R-02',
                'nama' => 'Ruang 2',
                'lokasi' => 'Kelas VIII.B',
                'kapasitas' => 2,
                'status' => 'siap',
            ]);

            $anggota = $kelas->anggotaKelas()->with('siswa')->orderBy('nomor_absen')->get();
            $peserta = $anggota->values()->map(function ($anggotaKelas, $index) use ($administrator, $kelasUjian, $ruangDua, $ruangSatu, $sesi, $ujianCbt) {
                $urutan = $index + 1;

                return PesertaUjianCbt::create([
                    'ujian_cbt_id' => $ujianCbt->id,
                    'sesi_ujian_cbt_id' => $sesi->id,
                    'kelas_ujian_cbt_id' => $kelasUjian->id,
                    'ruang_ujian_cbt_id' => $index < 2 ? $ruangSatu->id : $ruangDua->id,
                    'nomor_meja' => $index < 2 ? $urutan : 1,
                    'anggota_kelas_id' => $anggotaKelas->id,
                    'nomor_peserta' => 'PRESENSI-'.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT),
                    'status' => 'aktif',
                    'status_kehadiran_ujian' => 'belum_absen',
                    'dibuat_oleh_pengguna_id' => $administrator->id,
                ]);
            });

            Carbon::setTestNow('2026-08-14 22:08:00');

            $this->actingAs($pengawas)
                ->get(route('presensi-ujian-cbt.show', [$ujianCbt, $ruangSatu]))
                ->assertOk()
                ->assertSee('Presensi belum dibuka')
                ->assertSee('Presensi dapat dicatat mulai');

            $this->actingAs($pengawas)
                ->postJson(route('presensi-ujian-cbt.scan', [$ujianCbt, $ruangSatu]), [
                    'isi_scan' => $anggota[0]->siswa->nisn,
                ])
                ->assertUnprocessable()
                ->assertJsonPath('status', 'presensi_belum_dibuka');

            $this->actingAs($pengawas)
                ->putJson(route('presensi-ujian-cbt.manual', [$ujianCbt, $ruangSatu, $peserta[1]]), [
                    'status_kehadiran_ujian' => 'izin',
                ])
                ->assertUnprocessable()
                ->assertJsonPath('status', 'presensi_belum_dibuka');

            $peserta[0]->update([
                'status_kehadiran_ujian' => 'hadir',
                'absen_ujian_pada' => now(),
                'absen_ujian_oleh_pengguna_id' => $pengawas->id,
            ]);

            $this->actingAs($pengawas)
                ->get(route('presensi-ujian-cbt.show', [$ujianCbt, $ruangSatu]))
                ->assertOk()
                ->assertSee('Batalkan catatan');

            $this->actingAs($pengawas)
                ->putJson(route('presensi-ujian-cbt.manual', [$ujianCbt, $ruangSatu, $peserta[0]]), [
                    'status_kehadiran_ujian' => 'belum_absen',
                ])
                ->assertOk()
                ->assertJsonPath('peserta.status', 'belum_absen');

            $this->assertNull($peserta[0]->fresh()->absen_ujian_pada);
            Carbon::setTestNow('2026-08-15 07:30:00');

            $this->actingAs($pengawas)
                ->get(route('presensi-ujian-cbt.index'))
                ->assertOk()
                ->assertSee('R-01 - Ruang 1')
                ->assertDontSee('R-02 - Ruang 2');

            $this->actingAs($pengawas)
                ->get(route('presensi-ujian-cbt.show', [$ujianCbt, $ruangSatu]))
                ->assertOk()
                ->assertSee('Scan atau masukkan NISN')
                ->assertSee($anggota[0]->siswa->nama_lengkap)
                ->assertSee('Meja 1');

            $this->actingAs($pengawas)
                ->postJson(route('presensi-ujian-cbt.scan', [$ujianCbt, $ruangSatu]), [
                    'isi_scan' => $anggota[0]->siswa->nisn,
                ])
                ->assertOk()
                ->assertJsonPath('berhasil', true)
                ->assertJsonPath('baru', true)
                ->assertJsonPath('peserta.nomor_meja', 1)
                ->assertJsonPath('ringkasan.hadir', 1);

            $peserta[0]->refresh();
            $this->assertSame('hadir', $peserta[0]->status_kehadiran_ujian);
            $this->assertSame($pengawas->id, $peserta[0]->absen_ujian_oleh_pengguna_id);
            $this->assertNotNull($peserta[0]->absen_ujian_pada);

            $waktuScanPertama = $peserta[0]->absen_ujian_pada->toDateTimeString();
            Carbon::setTestNow('2026-08-15 07:31:00');

            $this->actingAs($pengawas)
                ->postJson(route('presensi-ujian-cbt.scan', [$ujianCbt, $ruangSatu]), [
                    'isi_scan' => $anggota[0]->siswa->nisn,
                ])
                ->assertOk()
                ->assertJsonPath('berhasil', true)
                ->assertJsonPath('baru', false)
                ->assertJsonPath('ringkasan.hadir', 1);

            $this->assertSame($waktuScanPertama, $peserta[0]->fresh()->absen_ujian_pada->toDateTimeString());

            $this->actingAs($pengawas)
                ->postJson(route('presensi-ujian-cbt.scan', [$ujianCbt, $ruangSatu]), [
                    'isi_scan' => $anggota[2]->siswa->nisn,
                ])
                ->assertUnprocessable()
                ->assertJsonPath('status', 'salah_ruang')
                ->assertJsonPath('ruang_seharusnya', 'Ruang 2');

            $this->actingAs($pengawas)
                ->putJson(route('presensi-ujian-cbt.manual', [$ujianCbt, $ruangSatu, $peserta[1]]), [
                    'status_kehadiran_ujian' => 'izin',
                ])
                ->assertOk()
                ->assertJsonPath('peserta.status', 'izin')
                ->assertJsonPath('ringkasan.tidak_hadir', 1);

            $this->assertSame('izin', $peserta[1]->fresh()->status_kehadiran_ujian);

            $this->actingAs($pengawas)
                ->get(route('presensi-ujian-cbt.show', [$ujianCbt, $ruangDua]))
                ->assertForbidden();

            $pegawaiPengganti = Pegawai::create([
                'nama_lengkap' => 'Pengawas Pengganti',
                'nip' => '198301012010012002',
                'aktif' => true,
            ]);
            $pengawasPengganti = Pengguna::create([
                'pegawai_id' => $pegawaiPengganti->id,
                'nama' => $pegawaiPengganti->nama_lengkap,
                'username' => $pegawaiPengganti->nip,
                'kata_sandi' => 'rahasia-pengganti',
                'peran' => 'pegawai',
                'aktif' => true,
            ]);
            $pengawasPengganti->daftarPeran()->attach(Peran::where('kode', 'pegawai')->firstOrFail());
            $this->assertFalse($pengawasPengganti->memilikiIzin('cbt.presensi'));
            $ruangSatu->update(['pengawas_utama_pegawai_id' => $pegawaiPengganti->id]);

            $this->actingAs($pengawas)
                ->get(route('presensi-ujian-cbt.show', [$ujianCbt, $ruangSatu]))
                ->assertForbidden();
            $this->actingAs($pengawas)
                ->postJson(route('presensi-ujian-cbt.scan', [$ujianCbt, $ruangSatu]), [
                    'isi_scan' => $anggota[0]->siswa->nisn,
                ])
                ->assertForbidden();
            $this->actingAs($pengawas)
                ->putJson(route('presensi-ujian-cbt.manual', [$ujianCbt, $ruangSatu, $peserta[1]]), [
                    'status_kehadiran_ujian' => 'hadir',
                ])
                ->assertForbidden();
            $this->actingAs($pengawas)
                ->get(route('presensi-ujian-cbt.index'))
                ->assertForbidden();

            $this->actingAs($pengawasPengganti)
                ->get(route('tugas-pengawas-ujian.show', $ruangSatu))
                ->assertOk()
                ->assertSee('Buka presensi ruang');
            $this->actingAs($pengawasPengganti)
                ->get(route('presensi-ujian-cbt.index'))
                ->assertOk()
                ->assertSee('R-01 - Ruang 1')
                ->assertDontSee('R-02 - Ruang 2');
            $this->actingAs($pengawasPengganti)
                ->get(route('presensi-ujian-cbt.show', [$ujianCbt, $ruangSatu]))
                ->assertOk();
            $this->actingAs($pengawasPengganti)
                ->putJson(route('presensi-ujian-cbt.manual', [$ujianCbt, $ruangSatu, $peserta[1]]), [
                    'status_kehadiran_ujian' => 'sakit',
                ])
                ->assertOk()
                ->assertJsonPath('peserta.status', 'sakit');
            $this->actingAs($pengawasPengganti)
                ->get(route('presensi-ujian-cbt.show', [$ujianCbt, $ruangDua]))
                ->assertForbidden();
            $this->actingAs($pengawasPengganti)
                ->postJson(route('presensi-ujian-cbt.scan', [$ujianCbt, $ruangDua]), [
                    'isi_scan' => $anggota[2]->siswa->nisn,
                ])
                ->assertForbidden();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_administrator_dapat_melihat_pusat_cbt_yang_meringkas_dua_alur(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $jenisUjian = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatAnggotaSiswa($tahunPelajaran, $kelas, 2);

        $ujianCbt = UjianCbt::create([
            ...collect($this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                ->except('kelas_peserta')
                ->all(),
            'jumlah_soal' => 1,
            'status' => 'terjadwal',
            'token' => 'PANITIA',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        KelasUjianCbt::create([
            'ujian_cbt_id' => $ujianCbt->id,
            'kelas_id' => $kelas->id,
            'komponen_nilai_id' => $komponenNilai->id,
        ]);
        $soal = $this->buatSoalCbt($tahunPelajaran, $mataPelajaran, 'CBT-PANITIA-001', 'Soal status panitia.');
        $ujianCbt->soalUjianCbt()->create([
            'soal_cbt_id' => $soal->id,
            'nomor_urut' => 1,
            'bobot' => 1,
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
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenisUjian->id,
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'kode' => 'PANITIA-2026',
            'nama' => 'Status Panitia CBT',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-08-15',
            'tanggal_selesai' => '2026-08-15',
            'status' => 'aktif',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        $jadwal = JadwalUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'ujian_cbt_id' => $ujianCbt->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tanggal' => '2026-08-15',
            'waktu_mulai' => '08:00',
            'waktu_selesai' => '10:00',
            'label_sesi' => 'Jam 1',
            'tingkat' => $kelas->tingkat,
            'urutan' => 1,
            'status' => 'siap',
            'dikunci_pada' => now(),
            'dikunci_oleh_pengguna_id' => $administrator->id,
        ]);
        $jadwal->kelas()->sync([$kelas->id]);

        $this->actingAs($administrator)
            ->post(route('ujian-cbt.peserta.generate', $ujianCbt))
            ->assertRedirect(route('ujian-cbt.peserta.index', $ujianCbt));

        $ruang = $ujianCbt->ruangUjianCbt()->create([
            'sesi_ujian_cbt_id' => $sesi->id,
            'jadwal_ujian_cbt_id' => $jadwal->id,
            'kode' => 'LAB-01',
            'nama' => 'Labor Komputer 1',
            'lokasi' => 'Lantai 2',
            'kapasitas' => 2,
            'pengawas_utama_pegawai_id' => Pegawai::firstOrFail()->id,
            'status' => 'siap',
            'dikunci_pada' => now(),
            'dikunci_oleh_pengguna_id' => $administrator->id,
        ]);

        PesertaUjianCbt::query()
            ->where('ujian_cbt_id', $ujianCbt->id)
            ->orderBy('id')
            ->get()
            ->each(function (PesertaUjianCbt $peserta, int $index) use ($ruang) {
                $peserta->update([
                    'ruang_ujian_cbt_id' => $ruang->id,
                    'nomor_meja' => $index + 1,
                ]);
            });

        $this->actingAs($administrator)
            ->get(route('pusat-cbt.index'))
            ->assertOk()
            ->assertSee('Pusat CBT')
            ->assertSee('Asesmen Kelas')
            ->assertSee('Ujian Terpusat')
            ->assertSee('tidak ada lagi akun atau kartu peserta CBT terpisah');
    }

    public function test_siswa_dapat_masuk_dari_akun_nusa_dan_mengerjakan_ujian_cbt(): void
    {
        Storage::fake('local');
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
                'jumlah_soal' => 4,
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
            $soalMenjodohkan = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
                'kode' => 'CBT-AKSES-MATCH',
                'jenis_soal' => 'menjodohkan',
                'pertanyaan' => 'Jodohkan besaran dengan satuannya.',
                'opsi' => ['pasangan' => [
                    ['nomor' => 1, 'kiri' => 'Frekuensi', 'kanan' => 'Hertz'],
                    ['nomor' => 2, 'kiri' => 'Periode', 'kanan' => 'Sekon'],
                ]],
                'kunci_jawaban' => ['jawaban' => [1 => 'Hertz', 2 => 'Sekon']],
            ]);
            $soalUpload = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
                'kode' => 'CBT-AKSES-UPLOAD',
                'jenis_soal' => 'upload_file',
                'pertanyaan' => 'Unggah laporan praktik.',
                'opsi' => [],
                'kunci_jawaban' => [],
            ]);
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
            $relasiMenjodohkan = $ujianCbt->soalUjianCbt()->create([
                'soal_cbt_id' => $soalMenjodohkan->id,
                'nomor_urut' => 3,
                'bobot' => 1,
            ]);
            $relasiUpload = $ujianCbt->soalUjianCbt()->create([
                'soal_cbt_id' => $soalUpload->id,
                'nomor_urut' => 4,
                'bobot' => 1,
            ]);

            $this->actingAs($administrator)
                ->post(route('ujian-cbt.peserta.generate', $ujianCbt))
                ->assertRedirect(route('ujian-cbt.peserta.index', $ujianCbt));

            $peserta = PesertaUjianCbt::query()
                ->with('anggotaKelas.siswa')
                ->where('ujian_cbt_id', $ujianCbt->id)
                ->where('kelas_ujian_cbt_id', $kelasUjianCbt->id)
                ->firstOrFail();

            $this->assertSame($sesi->id, $peserta->sesi_ujian_cbt_id);

            $akunSiswa = Pengguna::create([
                'siswa_id' => $peserta->anggotaKelas->siswa_id,
                'nama' => $peserta->anggotaKelas->siswa->nama_lengkap,
                'username' => $peserta->anggotaKelas->siswa->nisn,
                'kata_sandi' => 'rahasia-siswa',
                'peran' => 'siswa',
                'aktif' => true,
                'akun_sistem' => false,
                'wajib_ganti_kata_sandi' => false,
            ]);

            $this->actingAs($akunSiswa)
                ->post(route('ujian-saya.masuk', $peserta), [
                    'token' => 'TOKEN1',
                ])
                ->assertRedirect(route('cbt.ujian.show'));

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
                ->assertSee('Jodohkan besaran dengan satuannya.')
                ->assertSee('Pilihan pasangan')
                ->assertSee('Hertz')
                ->assertSee('Sekon')
                ->assertSee('name="jawaban['.$relasiMenjodohkan->id.'][1]"', false)
                ->assertSee('Unggah laporan praktik.')
                ->assertSee('Pilih dan unggah berkas')
                ->assertSee('data-answer-file-input', false)
                ->assertSee('Sisa waktu');

            $this->post(route('cbt.ujian.simpan'), [
                'jawaban' => [
                    $relasiPertama->id => 'B',
                ],
                'aksi' => 'selesai',
            ])->assertRedirect(route('cbt.ujian.kerjakan'))
                ->assertSessionHasErrors('ujian');

            $this->assertSame('sedang_mengerjakan', $peserta->fresh()->status);
            $this->assertSame(
                ['B'],
                $peserta->jawabanPesertaUjianCbt()
                    ->where('soal_ujian_cbt_id', $relasiPertama->id)
                    ->firstOrFail()
                    ->jawaban,
            );

            $this->post(route('cbt.ujian.jawaban-berkas'), [
                'soal_ujian_cbt_id' => $relasiUpload->id,
                'berkas' => UploadedFile::fake()->create('laporan-praktik.pdf', 100, 'application/pdf'),
                'ragu' => '0',
            ], ['Accept' => 'application/json'])
                ->assertOk()
                ->assertJsonPath('terjawab', true)
                ->assertJsonPath('berkas.nama', 'laporan-praktik.pdf');

            $jawabanUpload = $peserta->jawabanPesertaUjianCbt()
                ->where('soal_ujian_cbt_id', $relasiUpload->id)
                ->firstOrFail();
            Storage::disk('local')->assertExists($jawabanUpload->lokasi_file);

            $this->post(route('cbt.ujian.simpan'), [
                'jawaban' => [
                    $relasiPertama->id => 'B',
                    $relasiKedua->id => 'C',
                    $relasiMenjodohkan->id => [1 => 'Hertz', 2 => 'Sekon'],
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
            $jawabanMenjodohkan = $peserta->jawabanPesertaUjianCbt()
                ->where('soal_ujian_cbt_id', $relasiMenjodohkan->id)
                ->firstOrFail();

            $this->assertSame(['B'], $jawabanPertama->jawaban);
            $this->assertSame(['C'], $jawabanKedua->jawaban);
            $this->assertSame(['1' => 'Hertz', '2' => 'Sekon'], $jawabanMenjodohkan->jawaban);
            $this->assertTrue($jawabanKedua->ragu);

            $this->post(route('cbt.ujian.simpan'), [
                'jawaban' => [
                    $relasiPertama->id => 'B',
                    $relasiKedua->id => 'B',
                    $relasiMenjodohkan->id => [1 => 'Hertz', 2 => 'Sekon'],
                ],
                'aksi' => 'selesai',
            ])->assertRedirect(route('cbt.ujian.selesai'));

            $this->assertSame('selesai', $peserta->fresh()->status);

            $jawabanPertama->refresh();
            $jawabanKedua->refresh();
            $jawabanMenjodohkan->refresh();
            $this->assertTrue($jawabanPertama->benar);
            $this->assertTrue($jawabanKedua->benar);
            $this->assertTrue($jawabanMenjodohkan->benar);
            $this->assertEquals(1.0, (float) $jawabanPertama->skor);
            $this->assertEquals(1.0, (float) $jawabanKedua->skor);
            $this->assertEquals(1.0, (float) $jawabanMenjodohkan->skor);

            $this->get(route('cbt.ujian.selesai'))
                ->assertOk()
                ->assertSee('Ujian selesai')
                ->assertSee('4 / 4');

            $this->actingAs($administrator)
                ->get(route('ujian-cbt.koreksi-manual.index', $ujianCbt))
                ->assertOk()
                ->assertSee('laporan-praktik.pdf')
                ->assertSee('Unduh berkas jawaban');

            $this->get(route('ujian-cbt.koreksi-manual.berkas', [$ujianCbt, $jawabanUpload]))
                ->assertOk();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_administrator_dapat_menjalankan_koreksi_otomatis_cbt(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $jenisUjian = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatAnggotaSiswa($tahunPelajaran, $kelas, 1);

        $ujianCbt = UjianCbt::create([
            ...collect($this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                ->except('kelas_peserta')
                ->all(),
            'jumlah_soal' => 6,
            'status' => 'berlangsung',
            'token' => 'AUTO1',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        KelasUjianCbt::create([
            'ujian_cbt_id' => $ujianCbt->id,
            'kelas_id' => $kelas->id,
            'komponen_nilai_id' => $komponenNilai->id,
        ]);
        SesiUjianCbt::create([
            'ujian_cbt_id' => $ujianCbt->id,
            'kode' => 'S-01',
            'nama' => 'Sesi 1',
            'waktu_mulai' => '2026-08-15 08:00',
            'waktu_selesai' => '2026-08-15 10:00',
            'status' => 'aktif',
        ]);

        $soalPg = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
            'kode' => 'CBT-KOR-PG',
            'jenis_soal' => 'pilihan_ganda',
            'pertanyaan' => 'Organ pernapasan manusia adalah ....',
            'opsi' => ['pilihan' => ['A' => 'Lambung', 'B' => 'Paru-paru']],
            'kunci_jawaban' => ['jawaban' => 'B'],
        ]);
        $soalPgk = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
            'kode' => 'CBT-KOR-PGK',
            'jenis_soal' => 'pilihan_ganda_kompleks',
            'pertanyaan' => 'Pilih jawaban benar.',
            'opsi' => ['pilihan' => ['A' => 'Benar 1', 'B' => 'Salah', 'C' => 'Benar 2', 'D' => 'Salah']],
            'kunci_jawaban' => ['jawaban' => ['A', 'C']],
        ]);
        $soalBenarSalah = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
            'kode' => 'CBT-KOR-BS',
            'jenis_soal' => 'benar_salah',
            'pertanyaan' => 'Tentukan benar atau salah.',
            'opsi' => ['pernyataan' => [
                ['nomor' => 1, 'teks' => 'Dua lebih besar dari satu.'],
                ['nomor' => 2, 'teks' => 'Tiga lebih kecil dari dua.'],
                ['nomor' => 3, 'teks' => 'Empat adalah bilangan genap.'],
                ['nomor' => 4, 'teks' => 'Lima adalah bilangan genap.'],
            ]],
            'kunci_jawaban' => ['jawaban' => [1 => true, 2 => false, 3 => true, 4 => false]],
        ]);
        $soalMenjodohkan = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
            'kode' => 'CBT-KOR-MATCH',
            'jenis_soal' => 'menjodohkan',
            'pertanyaan' => 'Jodohkan istilah.',
            'opsi' => [
                'pasangan' => [
                    ['nomor' => 1, 'kiri' => 'Frekuensi', 'kanan' => 'Hertz'],
                    ['nomor' => 2, 'kiri' => 'Periode', 'kanan' => 'Sekon'],
                    ['nomor' => 3, 'kiri' => 'Panjang', 'kanan' => 'Meter'],
                    ['nomor' => 4, 'kiri' => 'Massa', 'kanan' => 'Kilogram'],
                    ['nomor' => 5, 'kiri' => 'Arus listrik', 'kanan' => 'Ampere'],
                ],
                'pengecoh' => ['Volt'],
            ],
            'kunci_jawaban' => ['jawaban' => [1 => 'Hertz', 2 => 'Sekon', 3 => 'Meter', 4 => 'Kilogram', 5 => 'Ampere']],
        ]);
        $soalIsian = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
            'kode' => 'CBT-KOR-ISI',
            'jenis_soal' => 'isian_singkat',
            'pertanyaan' => 'Satuan frekuensi adalah ....',
            'opsi' => null,
            'kunci_jawaban' => ['jawaban' => 'Hertz|Hz'],
        ]);
        $soalNumerik = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
            'kode' => 'CBT-KOR-NUM',
            'jenis_soal' => 'numerik',
            'pertanyaan' => 'Nilai pi dua desimal adalah ....',
            'opsi' => null,
            'kunci_jawaban' => ['jawaban' => '3,14'],
        ]);

        $relasiPg = $ujianCbt->soalUjianCbt()->create(['soal_cbt_id' => $soalPg->id, 'nomor_urut' => 1, 'bobot' => 1]);
        $relasiPgk = $ujianCbt->soalUjianCbt()->create(['soal_cbt_id' => $soalPgk->id, 'nomor_urut' => 2, 'bobot' => 2]);
        $relasiBenarSalah = $ujianCbt->soalUjianCbt()->create(['soal_cbt_id' => $soalBenarSalah->id, 'nomor_urut' => 3, 'bobot' => 3]);
        $relasiMenjodohkan = $ujianCbt->soalUjianCbt()->create(['soal_cbt_id' => $soalMenjodohkan->id, 'nomor_urut' => 4, 'bobot' => 4]);
        $relasiIsian = $ujianCbt->soalUjianCbt()->create(['soal_cbt_id' => $soalIsian->id, 'nomor_urut' => 5, 'bobot' => 1]);
        $relasiNumerik = $ujianCbt->soalUjianCbt()->create(['soal_cbt_id' => $soalNumerik->id, 'nomor_urut' => 6, 'bobot' => 1]);

        $this->actingAs($administrator)
            ->post(route('ujian-cbt.peserta.generate', $ujianCbt))
            ->assertRedirect(route('ujian-cbt.peserta.index', $ujianCbt));

        $peserta = PesertaUjianCbt::where('ujian_cbt_id', $ujianCbt->id)->firstOrFail();
        $peserta->update([
            'status' => 'selesai',
            'waktu_mulai' => now()->subMinutes(90),
            'waktu_selesai' => now(),
        ]);
        $peserta->jawabanPesertaUjianCbt()->create([
            'soal_ujian_cbt_id' => $relasiPg->id,
            'soal_cbt_id' => $soalPg->id,
            'jawaban' => ['B'],
            'waktu_dijawab' => now(),
        ]);
        $peserta->jawabanPesertaUjianCbt()->create([
            'soal_ujian_cbt_id' => $relasiPgk->id,
            'soal_cbt_id' => $soalPgk->id,
            'jawaban' => ['C', 'A'],
            'waktu_dijawab' => now(),
        ]);
        $peserta->jawabanPesertaUjianCbt()->create([
            'soal_ujian_cbt_id' => $relasiBenarSalah->id,
            'soal_cbt_id' => $soalBenarSalah->id,
            'jawaban' => [1 => 'benar', 2 => 'salah', 3 => 'benar', 4 => 'benar'],
            'waktu_dijawab' => now(),
        ]);
        $peserta->jawabanPesertaUjianCbt()->create([
            'soal_ujian_cbt_id' => $relasiMenjodohkan->id,
            'soal_cbt_id' => $soalMenjodohkan->id,
            'jawaban' => [1 => 'hertz', 2 => 'sekon', 3 => 'meter', 4 => 'kilogram', 5 => 'Volt'],
            'waktu_dijawab' => now(),
        ]);
        $peserta->jawabanPesertaUjianCbt()->create([
            'soal_ujian_cbt_id' => $relasiIsian->id,
            'soal_cbt_id' => $soalIsian->id,
            'jawaban' => ['hz'],
            'waktu_dijawab' => now(),
        ]);
        $peserta->jawabanPesertaUjianCbt()->create([
            'soal_ujian_cbt_id' => $relasiNumerik->id,
            'soal_cbt_id' => $soalNumerik->id,
            'jawaban' => ['3.14'],
            'waktu_dijawab' => now(),
        ]);

        $this->actingAs($administrator)
            ->from(route('ujian-cbt.monitoring.index', $ujianCbt))
            ->post(route('ujian-cbt.koreksi-otomatis.store', $ujianCbt))
            ->assertRedirect(route('ujian-cbt.monitoring.index', $ujianCbt))
            ->assertSessionHas('berhasil');

        $hasil = $peserta->jawabanPesertaUjianCbt()->get()->keyBy('soal_ujian_cbt_id');
        $this->assertTrue($hasil[$relasiPg->id]->benar);
        $this->assertEquals(1.0, (float) $hasil[$relasiPg->id]->skor);
        $this->assertTrue($hasil[$relasiPgk->id]->benar);
        $this->assertEquals(2.0, (float) $hasil[$relasiPgk->id]->skor);
        $this->assertFalse($hasil[$relasiBenarSalah->id]->benar);
        $this->assertEquals(2.25, (float) $hasil[$relasiBenarSalah->id]->skor);
        $this->assertFalse($hasil[$relasiMenjodohkan->id]->benar);
        $this->assertEquals(3.2, (float) $hasil[$relasiMenjodohkan->id]->skor);
        $this->assertTrue($hasil[$relasiIsian->id]->benar);
        $this->assertEquals(1.0, (float) $hasil[$relasiIsian->id]->skor);
        $this->assertTrue($hasil[$relasiNumerik->id]->benar);
        $this->assertEquals(1.0, (float) $hasil[$relasiNumerik->id]->skor);

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.hasil.index', $ujianCbt))
            ->assertOk()
            ->assertSee('Rekap hasil CBT')
            ->assertSee('87,08')
            ->assertSee('Tuntas')
            ->assertSee('Benar 4, salah 2, kosong 0');

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.hasil.index', [
                $ujianCbt,
                'status_hasil' => 'tuntas',
            ]))
            ->assertOk()
            ->assertSee($peserta->anggotaKelas->siswa->nama_lengkap)
            ->assertDontSee('Belum ada hasil CBT yang sesuai filter.');
    }

    public function test_administrator_dapat_mengoreksi_jawaban_manual_cbt(): void
    {
        Storage::fake('local');
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
            'token' => 'MANUAL',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        KelasUjianCbt::create([
            'ujian_cbt_id' => $ujianCbt->id,
            'kelas_id' => $kelas->id,
            'komponen_nilai_id' => $komponenNilai->id,
        ]);
        SesiUjianCbt::create([
            'ujian_cbt_id' => $ujianCbt->id,
            'kode' => 'S-01',
            'nama' => 'Sesi 1',
            'waktu_mulai' => '2026-08-15 08:00',
            'waktu_selesai' => '2026-08-15 10:00',
            'status' => 'aktif',
        ]);

        $soalUraian = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
            'kode' => 'CBT-MANUAL-001',
            'jenis_soal' => 'uraian',
            'pertanyaan' => 'Mengapa suara petir terdengar setelah kilat terlihat?',
            'opsi' => null,
            'kunci_jawaban' => null,
            'rubrik' => ['catatan' => 'Cek penjelasan kecepatan cahaya dan bunyi.'],
        ]);
        $relasiUraian = $ujianCbt->soalUjianCbt()->create([
            'soal_cbt_id' => $soalUraian->id,
            'nomor_urut' => 1,
            'bobot' => 2,
        ]);
        $soalUpload = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
            'kode' => 'CBT-MANUAL-UPLOAD',
            'jenis_soal' => 'upload_file',
            'pertanyaan' => 'Unggah laporan hasil pengamatan.',
            'opsi' => [],
            'kunci_jawaban' => [],
        ]);
        $relasiUpload = $ujianCbt->soalUjianCbt()->create([
            'soal_cbt_id' => $soalUpload->id,
            'nomor_urut' => 2,
            'bobot' => 3,
        ]);

        $this->actingAs($administrator)
            ->post(route('ujian-cbt.peserta.generate', $ujianCbt))
            ->assertRedirect(route('ujian-cbt.peserta.index', $ujianCbt));

        $peserta = PesertaUjianCbt::where('ujian_cbt_id', $ujianCbt->id)->firstOrFail();
        $peserta->update([
            'status' => 'selesai',
            'waktu_mulai' => now()->subMinutes(60),
            'waktu_selesai' => now(),
        ]);
        $jawaban = $peserta->jawabanPesertaUjianCbt()->create([
            'soal_ujian_cbt_id' => $relasiUraian->id,
            'soal_cbt_id' => $soalUraian->id,
            'jawaban' => ['Karena cahaya merambat lebih cepat daripada bunyi.'],
            'waktu_dijawab' => now(),
        ]);
        $lokasiBerkas = 'jawaban-cbt/'.$ujianCbt->id.'/'.$peserta->id.'/hasil-pengamatan.pdf';
        Storage::disk('local')->put($lokasiBerkas, 'isi laporan');
        $jawabanUpload = $peserta->jawabanPesertaUjianCbt()->create([
            'soal_ujian_cbt_id' => $relasiUpload->id,
            'soal_cbt_id' => $soalUpload->id,
            'jawaban' => ['berkas' => 'hasil-pengamatan.pdf'],
            'lokasi_file' => $lokasiBerkas,
            'nama_file_asli' => 'hasil-pengamatan.pdf',
            'tipe_mime_file' => 'application/pdf',
            'ukuran_file' => 11,
            'waktu_dijawab' => now(),
        ]);

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.koreksi-manual.index', $ujianCbt))
            ->assertOk()
            ->assertSee('Koreksi manual CBT')
            ->assertSee('Mengapa suara petir terdengar setelah kilat terlihat?')
            ->assertSee('Karena cahaya merambat lebih cepat daripada bunyi.')
            ->assertSee('Unggah laporan hasil pengamatan.')
            ->assertSee('hasil-pengamatan.pdf')
            ->assertSee('Belum dikoreksi');

        $this->actingAs($administrator)
            ->from(route('ujian-cbt.koreksi-manual.index', $ujianCbt))
            ->put(route('ujian-cbt.koreksi-manual.update', $ujianCbt), [
                'skor' => [
                    $jawaban->id => '2',
                    $jawabanUpload->id => '3',
                ],
            ])
            ->assertRedirect(route('ujian-cbt.koreksi-manual.index', $ujianCbt))
            ->assertSessionHas('berhasil');

        $jawaban->refresh();
        $this->assertTrue($jawaban->benar);
        $this->assertEquals(2.0, (float) $jawaban->skor);
        $jawabanUpload->refresh();
        $this->assertTrue($jawabanUpload->benar);
        $this->assertEquals(3.0, (float) $jawabanUpload->skor);

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.hasil.index', $ujianCbt))
            ->assertOk()
            ->assertSee('100,00')
            ->assertSee('Tuntas')
            ->assertDontSee('<span class="badge badge-warning">Perlu koreksi manual</span>', false);

        $this->actingAs($administrator)
            ->from(route('ujian-cbt.hasil.index', $ujianCbt))
            ->post(route('ujian-cbt.terapkan-nilai.store', $ujianCbt))
            ->assertRedirect(route('ujian-cbt.hasil.index', $ujianCbt))
            ->assertSessionHas('berhasil');

        $nilaiSiswa = NilaiSiswa::query()
            ->where('komponen_nilai_id', $komponenNilai->id)
            ->where('siswa_id', $peserta->anggotaKelas->siswa_id)
            ->firstOrFail();

        $this->assertEquals(100.0, (float) $nilaiSiswa->nilai);
        $this->assertSame('Diterapkan dari CBT CBT-UJI-001.', $nilaiSiswa->catatan);
        $this->assertSame($nilaiSiswa->id, $peserta->fresh()->nilai_siswa_id);
        $this->assertNotNull($peserta->fresh()->nilai_diterapkan_pada);

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.koreksi-manual.index', [
                $ujianCbt,
                'status_koreksi' => 'sudah_dikoreksi',
            ]))
            ->assertOk()
            ->assertSee('Sudah dikoreksi')
            ->assertSee('2.00');
    }

    public function test_rekap_hasil_tidak_menganggap_siswa_tidak_hadir_bernilai_nol(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $jenisUjian = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatAnggotaSiswa($tahunPelajaran, $kelas, 5);

        $ujianCbt = UjianCbt::create([
            ...collect($this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                ->except('kelas_peserta')
                ->all(),
            'jumlah_soal' => 1,
            'status' => 'berlangsung',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        KelasUjianCbt::create([
            'ujian_cbt_id' => $ujianCbt->id,
            'kelas_id' => $kelas->id,
            'komponen_nilai_id' => $komponenNilai->id,
        ]);
        $soal = $this->buatSoalCbt($tahunPelajaran, $mataPelajaran, 'CBT-REKAP-001', 'Soal rekap hasil.');
        $relasiSoal = $ujianCbt->soalUjianCbt()->create([
            'soal_cbt_id' => $soal->id,
            'nomor_urut' => 1,
            'bobot' => 2,
        ]);
        $this->actingAs($administrator)->post(route('ujian-cbt.peserta.generate', $ujianCbt))->assertRedirect();
        $peserta = PesertaUjianCbt::query()->where('ujian_cbt_id', $ujianCbt->id)->orderBy('id')->get();
        $peserta[0]->update([
            'status' => 'selesai',
            'status_kehadiran_ujian' => 'hadir',
            'waktu_mulai' => now()->subHour(),
            'waktu_selesai' => now(),
        ]);
        $peserta[0]->jawabanPesertaUjianCbt()->create([
            'soal_ujian_cbt_id' => $relasiSoal->id,
            'soal_cbt_id' => $soal->id,
            'jawaban' => ['B'],
            'benar' => true,
            'skor' => 2,
            'waktu_dijawab' => now(),
        ]);
        foreach (['sakit', 'izin', 'alfa'] as $index => $statusKehadiran) {
            $peserta[$index + 1]->update(['status_kehadiran_ujian' => $statusKehadiran]);
        }
        $peserta[4]->update([
            'status' => 'sedang_mengerjakan',
            'status_kehadiran_ujian' => 'hadir',
            'waktu_mulai' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($administrator)->get(route('ujian-cbt.hasil.index', $ujianCbt));
        $response->assertOk()
            ->assertViewHas('ringkasan', fn ($ringkasan) => $ringkasan['total_peserta'] === 5
                && $ringkasan['hasil_final'] === 1
                && $ringkasan['rata_rata'] === 100.0
                && $ringkasan['nilai_tertinggi'] === 100.0
                && $ringkasan['nilai_terendah'] === 100.0
                && $ringkasan['belum_mengikuti'] === 3
                && $ringkasan['belum_selesai'] === 1)
            ->assertViewHas('rekapHasil', fn ($rekap) => $rekap->where('nilai_tersedia', false)->count() === 4
                && $rekap->where('nilai', null)->count() === 4)
            ->assertSeeText('Belum mengikuti - Sakit')
            ->assertSeeText('Belum mengikuti - Izin')
            ->assertSeeText('Belum mengikuti - Alfa')
            ->assertSeeText('Sedang mengerjakan')
            ->assertSeeText('Tidak dihitung sebagai nilai 0')
            ->assertSeeText('Rata-rata hasil');

        $this->get(route('ujian-cbt.hasil.index', [$ujianCbt, 'status_hasil' => 'belum_mengikuti']))
            ->assertOk()
            ->assertViewHas('rekapHasil', fn ($rekap) => $rekap->count() === 3
                && $rekap->every(fn ($item) => $item['kode_status_hasil'] === 'belum_mengikuti'));

        $this->from(route('ujian-cbt.hasil.index', $ujianCbt))
            ->post(route('ujian-cbt.terapkan-nilai.store', $ujianCbt))
            ->assertRedirect(route('ujian-cbt.hasil.index', $ujianCbt));
        $this->assertSame(1, NilaiSiswa::query()->where('komponen_nilai_id', $komponenNilai->id)->count());
        $this->assertTrue($peserta->slice(1)->every(fn ($item) => is_null($item->fresh()->nilai_siswa_id)));
    }

    public function test_analisis_soal_membedakan_skor_penuh_parsial_dan_koreksi_tertunda(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatAnggotaSiswa($tahunPelajaran, $kelas, 4);

        $ujian = UjianCbt::create([
            ...collect($this->dataUjian(JenisUjianCbt::where('kode', 'STS')->firstOrFail(), $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                ->except('kelas_peserta')->all(),
            'jumlah_soal' => 3,
            'acak_soal' => false,
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        KelasUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_id' => $kelas->id,
            'komponen_nilai_id' => $komponenNilai->id,
        ]);
        $soalPg = $this->buatSoalCbt($tahunPelajaran, $mataPelajaran, 'CBT-ANALISIS-PG', 'Pilih satu jawaban.');
        $soalPgk = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
            'kode' => 'CBT-ANALISIS-PGK',
            'jenis_soal' => 'pilihan_ganda_kompleks',
            'pertanyaan' => 'Pilih semua jawaban benar.',
            'opsi' => ['pilihan' => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga']],
            'kunci_jawaban' => ['jawaban' => ['A', 'C']],
        ]);
        $soalUraian = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
            'kode' => 'CBT-ANALISIS-URAIAN',
            'jenis_soal' => 'uraian',
            'pertanyaan' => 'Jelaskan alasanmu.',
        ]);
        $relasiPg = $ujian->soalUjianCbt()->create(['soal_cbt_id' => $soalPg->id, 'nomor_urut' => 1, 'bobot' => 2]);
        $relasiPgk = $ujian->soalUjianCbt()->create(['soal_cbt_id' => $soalPgk->id, 'nomor_urut' => 2, 'bobot' => 4]);
        $relasiUraian = $ujian->soalUjianCbt()->create(['soal_cbt_id' => $soalUraian->id, 'nomor_urut' => 3, 'bobot' => 3]);
        $this->actingAs($administrator)->post(route('ujian-cbt.peserta.generate', $ujian))->assertRedirect();
        $peserta = PesertaUjianCbt::where('ujian_cbt_id', $ujian->id)->orderBy('id')->get();
        foreach ($peserta->take(3) as $siswa) {
            $siswa->update(['status' => 'selesai', 'waktu_mulai' => now()->subHour(), 'waktu_selesai' => now()]);
        }

        foreach ([
            [$peserta[0], $relasiPg, ['B'], 2],
            [$peserta[1], $relasiPg, ['A'], 0],
            [$peserta[2], $relasiPg, null, 0],
            [$peserta[3], $relasiPg, ['B'], 2],
            [$peserta[0], $relasiPgk, ['A', 'C'], 4],
            [$peserta[1], $relasiPgk, ['A'], 2],
            [$peserta[2], $relasiPgk, ['B'], null],
            [$peserta[0], $relasiUraian, ['Isi jawaban'], 3],
            [$peserta[1], $relasiUraian, ['Belum diperiksa'], null],
        ] as [$siswa, $soal, $jawaban, $skor]) {
            $siswa->jawabanPesertaUjianCbt()->create([
                'soal_ujian_cbt_id' => $soal->id,
                'soal_cbt_id' => $soal->soal_cbt_id,
                'jawaban' => $jawaban,
                'skor' => $skor,
            ]);
        }

        $this->get(route('ujian-cbt.hasil.analisis-soal', $ujian))
            ->assertOk()
            ->assertSeeText('Analisis soal')
            ->assertViewHas('analisis', function (array $analisis) {
                $pg = $analisis['soal'][0];
                $pgk = $analisis['soal'][1];
                $uraian = $analisis['soal'][2];

                return $analisis['peserta_selesai'] === 3
                    && $analisis['soal_belum_lengkap'] === 2
                    && $pg['disajikan'] === 3
                    && $pg['terjawab'] === 2
                    && $pg['belum_dijawab'] === 1
                    && $pg['dinilai'] === 3
                    && $pg['persen_skor_penuh'] === 33.33
                    && $pg['persen_rata_rata_skor'] === 33.33
                    && $pg['kesukaran']['status'] === 'sampel_terbatas'
                    && $pg['kesukaran']['kategori'] === null
                    && $pg['pilihan'] === ['A' => 1, 'B' => 1, 'C' => 0, 'D' => 0]
                    && $pgk['disajikan'] === 3
                    && $pgk['dinilai'] === 2
                    && $pgk['belum_dinilai'] === 1
                    && $pgk['skor_sebagian'] === 1
                    && $pgk['persen_skor_penuh'] === 50.0
                    && $pgk['persen_rata_rata_skor'] === 75.0
                    && $pgk['kesukaran']['indeks'] === 0.75
                    && $pgk['kesukaran']['status'] === 'menunggu_koreksi'
                    && $pgk['kesukaran']['kategori'] === null
                    && $pgk['pilihan'] === ['A' => 2, 'B' => 1, 'C' => 1]
                    && $uraian['disajikan'] === 3
                    && $uraian['dinilai'] === 2
                    && $uraian['belum_dijawab'] === 1
                    && $uraian['belum_dinilai'] === 1
                    && $uraian['skor_nol'] === 1
                    && $uraian['kesukaran']['indeks'] === 0.5
                    && $uraian['kesukaran']['status'] === 'menunggu_koreksi'
                    && $analisis['ringkasan_kesukaran']['belum_dikategorikan'] === 3;
            });
    }

    public function test_analisis_soal_acak_menghitung_hanya_siswa_yang_menerima_soal(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatAnggotaSiswa($tahunPelajaran, $kelas, 3);

        $ujian = UjianCbt::create([
            ...collect($this->dataUjian(JenisUjianCbt::where('kode', 'STS')->firstOrFail(), $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                ->except('kelas_peserta')->all(),
            'jumlah_soal' => 1,
            'acak_soal' => true,
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        KelasUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_id' => $kelas->id,
            'komponen_nilai_id' => $komponenNilai->id,
        ]);
        foreach (range(1, 3) as $nomor) {
            $soal = $this->buatSoalCbt($tahunPelajaran, $mataPelajaran, 'CBT-ACAK-'.$nomor, 'Soal acak '.$nomor);
            $ujian->soalUjianCbt()->create(['soal_cbt_id' => $soal->id, 'nomor_urut' => $nomor, 'bobot' => 2]);
        }
        $this->actingAs($administrator)->post(route('ujian-cbt.peserta.generate', $ujian))->assertRedirect();
        $peserta = PesertaUjianCbt::where('ujian_cbt_id', $ujian->id)->orderBy('id')->get();
        $peserta[0]->update(['status' => 'selesai']);
        $peserta[1]->update(['status' => 'selesai']);

        $this->get(route('ujian-cbt.hasil.analisis-soal', $ujian))
            ->assertOk()
            ->assertViewHas('analisis', fn (array $analisis) => $analisis['peserta_selesai'] === 2
                && $analisis['soal']->sum('disajikan') === 2
                && $analisis['soal']->sum('belum_dinilai') === 2
                && $analisis['soal']->every(fn ($item) => $item['kesukaran']['indeks'] === null
                    && $item['kesukaran']['kategori'] === null));

        // Jawaban di luar subset peserta tidak boleh masuk distribusi, termasuk data peserta belum selesai.
        foreach ($peserta as $siswa) {
            foreach ($ujian->soalUjianCbt()->get() as $soal) {
                $siswa->jawabanPesertaUjianCbt()->create([
                    'soal_ujian_cbt_id' => $soal->id,
                    'soal_cbt_id' => $soal->soal_cbt_id,
                    'jawaban' => ['B'],
                    'skor' => 2,
                ]);
            }
        }
        $this->get(route('ujian-cbt.hasil.analisis-soal', $ujian))
            ->assertOk()
            ->assertViewHas('analisis', fn (array $analisis) => $analisis['soal']->sum('pilihan.B') === 2
                && $analisis['soal']->every(fn ($item) => $item['rincian_pilihan']['opsi'][1]['persen'] === ($item['disajikan'] > 0 ? 100.0 : null)
                    && $item['rincian_pilihan']['perlu_ditinjau'] === 0));
    }

    public function test_kesukaran_hasil_siswa_mengikuti_skor_parsial_dan_filter_kelas_tanpa_mengubah_nilai(): void
    {
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatAnggotaSiswa($tahunPelajaran, $kelas, 12);
        $ujian = UjianCbt::create([
            ...collect($this->dataUjian(JenisUjianCbt::where('kode', 'STS')->firstOrFail(), $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                ->except('kelas_peserta')->all(),
            'jumlah_soal' => 3,
            'acak_soal' => false,
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        KelasUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_id' => $kelas->id,
            'komponen_nilai_id' => $komponenNilai->id,
        ]);

        foreach (['pilihan_ganda' => 2, 'pilihan_ganda_kompleks' => 4, 'benar_salah' => 1] as $jenis => $bobot) {
            $soal = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
                'kode' => 'CBT-KESUKARAN-'.$jenis,
                'jenis_soal' => $jenis,
                'pertanyaan' => 'Soal analisis '.$jenis,
            ]);
            $ujian->soalUjianCbt()->create(['soal_cbt_id' => $soal->id, 'nomor_urut' => $ujian->soalUjianCbt()->count() + 1, 'bobot' => $bobot]);
        }
        $this->actingAs($administrator)->post(route('ujian-cbt.peserta.generate', $ujian))->assertRedirect();
        $peserta = $ujian->pesertaUjianCbt()->orderBy('id')->get();
        $soalPaket = $ujian->soalUjianCbt()->orderBy('nomor_urut')->get();
        foreach ($peserta as $index => $siswa) {
            $siswa->update([
                'status' => $index < 10 ? 'selesai' : 'aktif',
                'status_kehadiran_ujian' => $index === 11 ? 'sakit' : 'hadir',
            ]);
            foreach ($soalPaket as $nomor => $soal) {
                $skor = match ($nomor) {
                    0 => $index < 3 ? 2 : 0,
                    1 => 2,
                    default => $index < 8 ? 1 : 0,
                };
                $siswa->jawabanPesertaUjianCbt()->create([
                    'soal_ujian_cbt_id' => $soal->id,
                    'soal_cbt_id' => $soal->soal_cbt_id,
                    'jawaban' => ['A'],
                    'skor' => $skor,
                ]);
            }
        }
        $nilaiSebelum = JawabanPesertaUjianCbt::query()->orderBy('id')->get()->toArray();
        $soalSebelum = SoalCbt::query()->orderBy('id')->get()->toArray();

        $this->get(route('ujian-cbt.hasil.analisis-soal', $ujian))
            ->assertOk()
            ->assertSeeText('Kesukaran hasil siswa')
            ->assertSeeText('Indeks 0,3000')
            ->assertSeeText('Indeks 0,5000')
            ->assertSeeText('Indeks 0,8000')
            ->assertSeeText('Kesulitan dari guru: Sedang')
            ->assertViewHas('analisis', fn (array $analisis) => $analisis['peserta_selesai'] === 10
                && $analisis['soal']->pluck('kesukaran.kategori')->all() === ['sukar', 'sedang', 'mudah']
                && $analisis['soal'][1]['skor_penuh'] === 0
                && $analisis['soal'][1]['skor_sebagian'] === 10
                && $analisis['ringkasan_kesukaran'] === ['sukar' => 1, 'sedang' => 1, 'mudah' => 1, 'belum_dikategorikan' => 0]);

        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'nama' => 'VIII.B',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $kelasUjianLain = KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $kelasLain->id]);
        foreach ($peserta->slice(8, 2) as $siswa) {
            $siswa->update(['kelas_ujian_cbt_id' => $kelasUjianLain->id]);
        }

        $this->get(route('ujian-cbt.hasil.analisis-soal', [$ujian, 'kelas_id' => $kelas->id]))
            ->assertOk()
            ->assertSeeText('Sampel terbatas')
            ->assertViewHas('analisis', fn (array $analisis) => $analisis['peserta_selesai'] === 8
                && $analisis['soal'][0]['kesukaran']['indeks'] === 0.375
                && $analisis['soal']->every(fn ($item) => $item['kesukaran']['status'] === 'sampel_terbatas')
                && $analisis['ringkasan_kesukaran']['belum_dikategorikan'] === 3);
        $this->assertSame($nilaiSebelum, JawabanPesertaUjianCbt::query()->orderBy('id')->get()->toArray());
        $this->assertSame($soalSebelum, SoalCbt::query()->orderBy('id')->get()->toArray());

        $peserta[0]->jawabanPesertaUjianCbt()->where('soal_ujian_cbt_id', $soalPaket[0]->id)->update(['skor' => 3]);
        $this->get(route('ujian-cbt.hasil.analisis-soal', $ujian))
            ->assertOk()
            ->assertSeeText('Periksa skor')
            ->assertViewHas('analisis', fn (array $analisis) => $analisis['soal'][0]['kesukaran']['status'] === 'skor_tidak_valid'
                && $analisis['soal'][0]['kesukaran']['kategori'] === null);
    }

    public function test_rincian_pilihan_menghitung_siswa_sekali_dan_menjaga_akses_kunci_jawaban(): void
    {
        [$tahun, $mapel, $kelas, $komponen] = $this->buatDataAkademik();
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatAnggotaSiswa($tahun, $kelas, 22);
        $ujian = UjianCbt::create([
            ...collect($this->dataUjian(JenisUjianCbt::where('kode', 'STS')->firstOrFail(), $tahun, $mapel, $kelas, $komponen))
                ->except('kelas_peserta')->all(),
            'jumlah_soal' => 2,
            'acak_soal' => false,
            'acak_jawaban' => true,
            'dibuat_oleh_pengguna_id' => $admin->id,
        ]);
        KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $kelas->id, 'komponen_nilai_id' => $komponen->id]);
        $soalPg = $this->buatSoalCbt($tahun, $mapel, 'RINCIAN-PG', 'Pilih jawaban.');
        $soalPg->update([
            'stimulus' => 'Hasil dari \\(2^{3}\\).',
            'media' => ['konten' => ['pilihan_A' => [
                'gambar' => ['path' => 'cbt/uji-opsi.png', 'alt' => 'Gambar pilihan A', 'keterangan' => 'Keterangan gambar opsi'],
                'tabel' => ['baris' => [['Nilai', 'Jumlah'], ['A', '8']]],
                'rumus' => ['latex' => '2^3'],
            ]]],
            'opsi' => ['pilihan' => ['A' => 'Delapan', 'B' => '<script>alert("opsi")</script>', 'C' => 'Tiga', 'D' => 'Empat']],
        ]);
        $soalPgk = $this->buatSoalObjektif($tahun, $mapel, [
            'kode' => 'RINCIAN-PGK',
            'jenis_soal' => 'pilihan_ganda_kompleks',
            'pertanyaan' => 'Pilih semua jawaban yang benar.',
            'opsi' => ['pilihan' => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga']],
            'kunci_jawaban' => ['jawaban' => ['A', 'C']],
        ]);
        $pg = $ujian->soalUjianCbt()->create(['soal_cbt_id' => $soalPg->id, 'nomor_urut' => 1, 'bobot' => 2]);
        $pgk = $ujian->soalUjianCbt()->create(['soal_cbt_id' => $soalPgk->id, 'nomor_urut' => 2, 'bobot' => 4]);
        $this->actingAs($admin)->post(route('ujian-cbt.peserta.generate', $ujian))->assertRedirect();
        $peserta = $ujian->pesertaUjianCbt()->orderBy('id')->get();
        foreach ($peserta as $nomor => $siswa) {
            $siswa->update(['status' => $nomor < 20 ? 'selesai' : 'aktif', 'status_kehadiran_ujian' => $nomor === 21 ? 'sakit' : 'hadir']);
            $jawabanPg = match ($nomor) {
                0 => [' a ', 'A'],
                18 => ['C'],
                19 => null,
                20, 21 => ['D'],
                default => ['B'],
            };
            foreach ([[$pg, $jawabanPg], [$pgk, $nomor === 19 ? null : [' a ', 'A', 'C']]] as [$soal, $jawaban]) {
                $siswa->jawabanPesertaUjianCbt()->create([
                    'soal_ujian_cbt_id' => $soal->id,
                    'soal_cbt_id' => $soal->soal_cbt_id,
                    'jawaban' => $jawaban,
                    'skor' => $jawaban === null ? 0 : $soal->bobot,
                ]);
            }
        }
        $nilaiSebelum = JawabanPesertaUjianCbt::query()->orderBy('id')->get()->toArray();
        $url = route('ujian-cbt.hasil.rincian-soal', ['ujianCbt' => $ujian, 'soalUjianCbt' => $pg, 'kelas_id' => $kelas->id]);
        $this->get(route('ujian-cbt.hasil.analisis-soal', [$ujian, 'kelas_id' => $kelas->id]))
            ->assertOk()->assertSee($url)->assertSeeText('1 pengecoh perlu ditinjau')
            ->assertSeeText('Saran pemeriksaan, bukan berarti soal salah.');
        $this->get($url)->assertOk()
            ->assertSeeText('Rincian jawaban dan pengecoh')
            ->assertSeeText('Pengecoh belum dipilih')
            ->assertSeeText('Ini saran untuk memeriksa pilihan jawaban, bukan keputusan bahwa soal salah.')
            ->assertSeeText('Apa arti "Perlu ditinjau"? Penjelasan dan contoh')
            ->assertSeeText('Diperlukan minimal 10 peserta selesai')
            ->assertSeeText('Tepat 5% tidak mendapat penanda ini.')
            ->assertSeeText('C dipilih 1 siswa (3,33%): perlu ditinjau karena jarang dipilih.')
            ->assertSeeText('Apa yang perlu diperiksa guru?')
            ->assertSeeText('Analisis ini tidak mengubah kunci, bobot, atau nilai siswa.')
            ->assertSeeText('85,00%')
            ->assertSeeText('Keterangan gambar opsi')
            ->assertSee('data-rumus-latex="2^3"', false)
            ->assertSeeText('Nilai')
            ->assertDontSee('<script>alert("opsi")</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertSee(route('ujian-cbt.hasil.analisis-soal', [$ujian, 'kelas_id' => $kelas->id]).'#soal-'.$pg->id)
            ->assertViewHas('item', fn ($item) => $item['disajikan'] === 20
                && $item['belum_dijawab'] === 1
                && $item['rincian_pilihan']['perlu_ditinjau'] === 1
                && array_column($item['rincian_pilihan']['opsi'], 'dipilih') === [1, 17, 1, 0]
                && array_column($item['rincian_pilihan']['opsi'], 'persen') === [5.0, 85.0, 5.0, 0.0]);
        $this->get(route('ujian-cbt.hasil.rincian-soal', [$ujian, $pgk]))
            ->assertOk()->assertSeeText('Tidak dipilih')->assertSeeText('95,00%')
            ->assertSeeText('tanpa penanda pengecoh perlu ditinjau berdasarkan patokan 5%')
            ->assertDontSeeText('Apa arti "Perlu ditinjau"? Penjelasan dan contoh')
            ->assertViewHas('item', fn ($item) => $item['rincian_pilihan']['perlu_ditinjau'] === 0
                && array_column($item['rincian_pilihan']['opsi'], 'dipilih') === [19, 0, 19]
                && array_sum(array_column($item['rincian_pilihan']['opsi'], 'persen')) === 190.0);
        $this->assertSame($nilaiSebelum, JawabanPesertaUjianCbt::query()->orderBy('id')->get()->toArray());

        $kelasLain = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.B', 'tingkat' => 8, 'aktif' => true]);
        $kelasUjianLain = KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $kelasLain->id]);
        foreach ($peserta->slice(10, 10) as $siswa) {
            $siswa->update(['kelas_ujian_cbt_id' => $kelasUjianLain->id]);
        }
        $this->get($url)->assertOk()->assertViewHas('item', fn ($item) => $item['disajikan'] === 10
            && array_column($item['rincian_pilihan']['opsi'], 'dipilih') === [1, 9, 0, 0]
            && array_column($item['rincian_pilihan']['opsi'], 'persen') === [10.0, 90.0, 0.0, 0.0]);

        $peserta[0]->jawabanPesertaUjianCbt()->where('soal_ujian_cbt_id', $pg->id)->update(['jawaban' => ['A', 'B']]);
        $this->get($url)->assertOk()->assertSeeText('1 jawaban siswa yang tidak sesuai')
            ->assertViewHas('item', fn ($item) => $item['rincian_pilihan']['perlu_ditinjau'] === 0
                && array_column($item['rincian_pilihan']['opsi'], 'dipilih') === [0, 9, 0, 0]);

        $peserta[0]->jawabanPesertaUjianCbt()->where('soal_ujian_cbt_id', $pgk->id)->update(['jawaban' => ['A', 'Z']]);
        $this->get(route('ujian-cbt.hasil.rincian-soal', [$ujian, $pgk]))
            ->assertOk()->assertSeeText('1 jawaban siswa yang tidak sesuai')
            ->assertViewHas('item', fn ($item) => $item['rincian_pilihan']['jawaban_tidak_dikenali'] === 1
                && array_column($item['rincian_pilihan']['opsi'], 'dipilih') === [19, 0, 18]);

        $akunSiswa = Pengguna::create([
            'siswa_id' => $peserta[0]->anggotaKelas->siswa_id,
            'nama' => 'Siswa analisis',
            'username' => 'siswa-analisis',
            'kata_sandi' => 'rahasia-siswa',
            'peran' => 'siswa',
            'aktif' => true,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $this->actingAs($akunSiswa)->get($url)->assertForbidden();
    }

    public function test_rincian_benar_salah_dan_menjodohkan_mengikuti_peserta_kelas_dan_soal_yang_disajikan(): void
    {
        [$tahun, $mapel, $kelas, $komponen] = $this->buatDataAkademik();
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatAnggotaSiswa($tahun, $kelas, 5);
        $ujian = UjianCbt::create([
            ...collect($this->dataUjian(JenisUjianCbt::where('kode', 'STS')->firstOrFail(), $tahun, $mapel, $kelas, $komponen))
                ->except('kelas_peserta')->all(),
            'jumlah_soal' => 2,
            'acak_soal' => false,
            'acak_jawaban' => true,
            'dibuat_oleh_pengguna_id' => $admin->id,
        ]);
        KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $kelas->id, 'komponen_nilai_id' => $komponen->id]);
        $bs = $this->buatSoalObjektif($tahun, $mapel, [
            'kode' => 'RINCIAN-BS',
            'jenis_soal' => 'benar_salah',
            'pertanyaan' => 'Tentukan Benar atau Salah.',
            'opsi' => ['pernyataan' => [
                ['nomor' => 2, 'teks' => 'Hasil dari \\(2+2\\) adalah 4.', 'media_key' => 'bs-dua'],
                ['nomor' => 5, 'teks' => '<script>alert("butir")</script>'],
            ]],
            'kunci_jawaban' => ['jawaban' => [2 => true, 5 => false]],
            'media' => ['konten' => ['bs-dua' => ['tabel' => ['baris' => [['Angka', 'Nilai'], ['Dua', '2']]]]]],
        ]);
        $mj = $this->buatSoalObjektif($tahun, $mapel, [
            'kode' => 'RINCIAN-MJ',
            'jenis_soal' => 'menjodohkan',
            'pertanyaan' => 'Pasangkan wilayah dengan ibu kotanya.',
            'opsi' => [
                'pasangan' => [
                    ['nomor' => 1, 'kiri' => 'Indonesia', 'kanan' => 'Jakarta', 'media_kiri_key' => 'mj-satu', 'media_kanan_key' => 'jawab-satu'],
                    ['nomor' => 3, 'kiri' => 'Jawa Barat', 'kanan' => 'Bandung'],
                ],
                'pengecoh' => ['Surabaya'],
                'pengecoh_media' => [['teks' => 'Surabaya', 'media_key' => 'mj-pengecoh']],
            ],
            'kunci_jawaban' => ['jawaban' => [1 => 'Jakarta', 3 => 'Bandung']],
            'media' => ['konten' => [
                'mj-satu' => ['gambar' => ['path' => 'cbt/rincian-uji.png', 'keterangan' => 'Media pernyataan']],
                'jawab-satu' => ['rumus' => ['latex' => 'x^{2}']],
                'mj-pengecoh' => ['gambar' => ['path' => 'cbt/rincian-uji.png', 'keterangan' => 'Media pengecoh']],
            ]],
        ]);
        $relasiBs = $ujian->soalUjianCbt()->create(['soal_cbt_id' => $bs->id, 'nomor_urut' => 1, 'bobot' => 2]);
        $relasiMj = $ujian->soalUjianCbt()->create(['soal_cbt_id' => $mj->id, 'nomor_urut' => 2, 'bobot' => 2]);
        $this->actingAs($admin)->post(route('ujian-cbt.peserta.generate', $ujian))->assertRedirect();
        $peserta = $ujian->pesertaUjianCbt()->orderBy('id')->get();
        foreach ($peserta as $nomor => $siswa) {
            $siswa->update(['status' => $nomor < 3 ? 'selesai' : 'aktif', 'status_kehadiran_ujian' => $nomor === 4 ? 'sakit' : 'hadir']);
            $jawabanBs = match ($nomor) {
                1 => [2 => false, 5 => true], 2 => [2 => true], default => [2 => true, 5 => false]
            };
            $jawabanMj = match ($nomor) {
                1 => [1 => 'Surabaya', 3 => 'Jakarta'], 2 => [3 => 'Bandung'], default => [1 => 'Jakarta', 3 => 'Bandung']
            };
            foreach ([[$relasiBs, $jawabanBs], [$relasiMj, $jawabanMj]] as [$soal, $jawaban]) {
                $siswa->jawabanPesertaUjianCbt()->create(['soal_ujian_cbt_id' => $soal->id, 'soal_cbt_id' => $soal->soal_cbt_id, 'jawaban' => $jawaban]);
            }
            app(KoreksiOtomatisCbtService::class)->koreksiPeserta($siswa);
        }
        $nilaiSebelum = JawabanPesertaUjianCbt::query()->orderBy('id')->get()->toArray();
        $urlBs = route('ujian-cbt.hasil.rincian-soal', [$ujian, $relasiBs, 'kelas_id' => $kelas->id]);
        $urlMj = route('ujian-cbt.hasil.rincian-soal', [$ujian, $relasiMj, 'kelas_id' => $kelas->id]);
        $this->get(route('ujian-cbt.hasil.analisis-soal', [$ujian, 'kelas_id' => $kelas->id]))
            ->assertOk()->assertSee($urlBs)->assertSee($urlMj);
        $this->get($urlBs)->assertOk()
            ->assertSeeText('Rincian jawaban per pernyataan')->assertSeeText('Kunci: Salah')
            ->assertSeeText('Angka')->assertSeeText('66,67%')
            ->assertDontSee('<script>alert("butir")</script>', false)
            ->assertViewHas('item', fn ($item) => $item['disajikan'] === 3
                && $item['skor_penuh'] === 1 && $item['skor_sebagian'] === 1 && $item['skor_nol'] === 1
                && array_column($item['rincian_pemetaan']['butir'], 'benar') === [2, 1]
                && array_column($item['rincian_pemetaan']['butir'], 'salah') === [1, 1]
                && array_column($item['rincian_pemetaan']['butir'], 'kosong') === [0, 1]);
        $this->get($urlMj)->assertOk()
            ->assertSeeText('Rincian jawaban dan pasangan')->assertSeeText('Pengecoh tambahan')
            ->assertSeeText('Media pernyataan')->assertSeeText('Media pengecoh')
            ->assertSee('data-rumus-latex="x^{2}"', false)
            ->assertViewHas('item', fn ($item) => $item['disajikan'] === 3
                && array_column($item['rincian_pemetaan']['butir'], 'benar') === [1, 2]
                && array_column($item['rincian_pemetaan']['butir'], 'salah') === [1, 1]
                && array_column($item['rincian_pemetaan']['butir'], 'kosong') === [1, 0]
                && array_column($item['rincian_pemetaan']['butir'][0]['pilihan'], 'dipilih') === [1, 0, 1]);
        $this->assertSame($nilaiSebelum, JawabanPesertaUjianCbt::query()->orderBy('id')->get()->toArray());

        $kelasLain = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.B', 'tingkat' => 8, 'aktif' => true]);
        $kelasUjianLain = KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $kelasLain->id]);
        $peserta[2]->update(['kelas_ujian_cbt_id' => $kelasUjianLain->id]);
        foreach ([$urlBs, $urlMj] as $url) {
            $this->get($url)->assertOk()->assertSeeText('50,00%')
                ->assertViewHas('item', fn ($item) => $item['disajikan'] === 2
                    && array_column($item['rincian_pemetaan']['butir'], 'benar') === [1, 1]);
        }
        $ujian->update(['acak_soal' => true, 'jumlah_soal' => 1]);
        $this->get(route('ujian-cbt.hasil.analisis-soal', $ujian))->assertOk()
            ->assertViewHas('analisis', fn ($analisis) => $analisis['soal']->sum('disajikan') === 3
                && $analisis['soal']->every(fn ($item) => collect($item['rincian_pemetaan']['butir'])->every(
                    fn ($butir) => $item['disajikan'] === $butir['benar'] + $butir['salah'] + $butir['kosong']
                )));

        $akunSiswa = Pengguna::create([
            'siswa_id' => $peserta[0]->anggotaKelas->siswa_id, 'nama' => 'Siswa rincian',
            'username' => 'siswa-rincian', 'kata_sandi' => 'rahasia-siswa', 'peran' => 'siswa',
            'aktif' => true, 'wajib_ganti_kata_sandi' => false,
        ]);
        $this->actingAs($akunSiswa)->get($urlBs)->assertForbidden();
        $this->get($urlMj)->assertForbidden();
    }

    public function test_administrator_dapat_memantau_monitoring_peserta_cbt(): void
    {
        Carbon::setTestNow('2026-08-15 08:45:00');

        try {
            [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
            $jenisUjian = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
            $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
            $this->buatAnggotaSiswa($tahunPelajaran, $kelas, 3);

            $ujianCbt = UjianCbt::create([
                ...collect($this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                    ->except('kelas_peserta')
                    ->all(),
                'jumlah_soal' => 2,
                'status' => 'berlangsung',
                'token' => 'MON123',
                'dibuat_oleh_pengguna_id' => $administrator->id,
            ]);
            KelasUjianCbt::create([
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
            $ruangSatu = RuangUjianCbt::create([
                'ujian_cbt_id' => $ujianCbt->id,
                'sesi_ujian_cbt_id' => $sesi->id,
                'kode' => 'R-01',
                'nama' => 'Ruang 1',
                'lokasi' => 'Kelas VIII.A',
                'kapasitas' => 2,
                'status' => 'siap',
            ]);
            $ruangDua = RuangUjianCbt::create([
                'ujian_cbt_id' => $ujianCbt->id,
                'sesi_ujian_cbt_id' => $sesi->id,
                'kode' => 'R-02',
                'nama' => 'Ruang 2',
                'lokasi' => 'Kelas VIII.B',
                'kapasitas' => 1,
                'status' => 'siap',
            ]);

            $soalPertama = $this->buatSoalCbt($tahunPelajaran, $mataPelajaran, 'CBT-MON-001', 'Soal monitoring pertama.');
            $soalKedua = $this->buatSoalCbt($tahunPelajaran, $mataPelajaran, 'CBT-MON-002', 'Soal monitoring kedua.');
            $relasiPertama = $ujianCbt->soalUjianCbt()->create([
                'soal_cbt_id' => $soalPertama->id,
                'nomor_urut' => 1,
                'bobot' => 1,
            ]);
            $ujianCbt->soalUjianCbt()->create([
                'soal_cbt_id' => $soalKedua->id,
                'nomor_urut' => 2,
                'bobot' => 1,
            ]);

            $this->actingAs($administrator)
                ->post(route('ujian-cbt.peserta.generate', $ujianCbt))
                ->assertRedirect(route('ujian-cbt.peserta.index', $ujianCbt));

            $peserta = PesertaUjianCbt::query()
                ->with('anggotaKelas.siswa')
                ->where('ujian_cbt_id', $ujianCbt->id)
                ->orderBy('id')
                ->get();

            $peserta[0]->update([
                'ruang_ujian_cbt_id' => $ruangSatu->id,
                'nomor_meja' => 1,
            ]);
            $peserta[1]->update([
                'ruang_ujian_cbt_id' => $ruangSatu->id,
                'nomor_meja' => 2,
                'status_kehadiran_ujian' => 'hadir',
                'absen_ujian_pada' => now()->subMinutes(20),
                'ip_terakhir' => '127.0.0.1',
                'user_agent_terakhir' => 'Browser CBT',
            ]);
            $peserta[2]->update([
                'ruang_ujian_cbt_id' => $ruangDua->id,
                'nomor_meja' => 1,
                'status_kehadiran_ujian' => 'terlambat',
                'absen_ujian_pada' => now()->subMinutes(16),
                'status' => 'sedang_mengerjakan',
                'waktu_mulai' => now()->subMinutes(15),
                'ip_terakhir' => '10.10.10.5',
                'user_agent_terakhir' => 'Laptop Proktor',
            ]);
            $peserta[2]->jawabanPesertaUjianCbt()->create([
                'soal_ujian_cbt_id' => $relasiPertama->id,
                'soal_cbt_id' => $soalPertama->id,
                'jawaban' => ['B'],
                'ragu' => true,
                'waktu_dijawab' => now(),
            ]);

            $this->actingAs($administrator)
                ->get(route('ujian-cbt.monitoring.index', $ujianCbt))
                ->assertOk()
                ->assertSee('Monitoring CBT')
                ->assertSee('MON123')
                ->assertSee('Belum hadir')
                ->assertSee('Hadir, belum mulai')
                ->assertSee('Sedang mengerjakan')
                ->assertSee('R-02 - Ruang 2')
                ->assertSee('Meja 1')
                ->assertSee('Terlambat')
                ->assertSee('1 / 2')
                ->assertSee('1 ragu')
                ->assertSee('Sisa sekitar');

            $this->actingAs($administrator)
                ->get(route('ujian-cbt.monitoring.index', [
                    $ujianCbt,
                    'status_monitor' => 'sedang_mengerjakan',
                ]))
                ->assertOk()
                ->assertSee($peserta[2]->anggotaKelas->siswa->nama_lengkap)
                ->assertDontSee($peserta[0]->anggotaKelas->siswa->nama_lengkap);

            $this->actingAs($administrator)
                ->get(route('ujian-cbt.monitoring.index', [
                    $ujianCbt,
                    'status_monitor' => 'hadir_belum_mulai',
                ]))
                ->assertOk()
                ->assertSee($peserta[1]->anggotaKelas->siswa->nama_lengkap)
                ->assertDontSee($peserta[0]->anggotaKelas->siswa->nama_lengkap)
                ->assertDontSee($peserta[2]->anggotaKelas->siswa->nama_lengkap);

            $this->actingAs($administrator)
                ->get(route('ujian-cbt.monitoring.index', [
                    $ujianCbt,
                    'ruang_ujian_cbt_id' => $ruangDua->id,
                ]))
                ->assertOk()
                ->assertSee($peserta[2]->anggotaKelas->siswa->nama_lengkap)
                ->assertDontSee($peserta[0]->anggotaKelas->siswa->nama_lengkap);
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
            'skor_maksimal' => 2,
            'status' => 'siap',
            'aktif' => true,
        ]);
    }

    private function buatSoalObjektif(
        TahunPelajaran $tahunPelajaran,
        MataPelajaran $mataPelajaran,
        array $atribut
    ): SoalCbt {
        return SoalCbt::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 8,
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'umum',
            'skor_maksimal' => 1,
            'status' => 'siap',
            'aktif' => true,
            ...$atribut,
        ]);
    }

    private function buatAnggotaSiswa(TahunPelajaran $tahunPelajaran, Kelas $kelas, int $jumlah): void
    {
        for ($i = 1; $i <= $jumlah; $i++) {
            $siswa = Siswa::create([
                'nama_lengkap' => 'Siswa CBT '.$i,
                'nis' => 'CBT'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nisn' => '999000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
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
