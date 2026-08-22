<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalUjianCbt;
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
            $pengawas->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());

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

            $jawabanPertama->refresh();
            $jawabanKedua->refresh();
            $this->assertTrue($jawabanPertama->benar);
            $this->assertTrue($jawabanKedua->benar);
            $this->assertEquals(1.0, (float) $jawabanPertama->skor);
            $this->assertEquals(1.0, (float) $jawabanKedua->skor);

            $this->get(route('cbt.ujian.selesai'))
                ->assertOk()
                ->assertSee('Ujian selesai')
                ->assertSee('2 / 2');
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
            'jumlah_soal' => 5,
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
            ]],
            'kunci_jawaban' => ['jawaban' => [1 => true, 2 => false]],
        ]);
        $soalMenjodohkan = $this->buatSoalObjektif($tahunPelajaran, $mataPelajaran, [
            'kode' => 'CBT-KOR-MATCH',
            'jenis_soal' => 'menjodohkan',
            'pertanyaan' => 'Jodohkan istilah.',
            'opsi' => ['pasangan' => [
                ['nomor' => 1, 'kiri' => 'Frekuensi', 'kanan' => 'Hertz'],
                ['nomor' => 2, 'kiri' => 'Periode', 'kanan' => 'Sekon'],
            ]],
            'kunci_jawaban' => ['jawaban' => [1 => 'Hertz', 2 => 'Sekon']],
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

        $relasiPgk = $ujianCbt->soalUjianCbt()->create(['soal_cbt_id' => $soalPgk->id, 'nomor_urut' => 1, 'bobot' => 2]);
        $relasiBenarSalah = $ujianCbt->soalUjianCbt()->create(['soal_cbt_id' => $soalBenarSalah->id, 'nomor_urut' => 2, 'bobot' => 1]);
        $relasiMenjodohkan = $ujianCbt->soalUjianCbt()->create(['soal_cbt_id' => $soalMenjodohkan->id, 'nomor_urut' => 3, 'bobot' => 2]);
        $relasiIsian = $ujianCbt->soalUjianCbt()->create(['soal_cbt_id' => $soalIsian->id, 'nomor_urut' => 4, 'bobot' => 1]);
        $relasiNumerik = $ujianCbt->soalUjianCbt()->create(['soal_cbt_id' => $soalNumerik->id, 'nomor_urut' => 5, 'bobot' => 1]);

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
            'soal_ujian_cbt_id' => $relasiPgk->id,
            'soal_cbt_id' => $soalPgk->id,
            'jawaban' => ['C', 'A'],
            'waktu_dijawab' => now(),
        ]);
        $peserta->jawabanPesertaUjianCbt()->create([
            'soal_ujian_cbt_id' => $relasiBenarSalah->id,
            'soal_cbt_id' => $soalBenarSalah->id,
            'jawaban' => [1 => 'benar', 2 => 'salah'],
            'waktu_dijawab' => now(),
        ]);
        $peserta->jawabanPesertaUjianCbt()->create([
            'soal_ujian_cbt_id' => $relasiMenjodohkan->id,
            'soal_cbt_id' => $soalMenjodohkan->id,
            'jawaban' => [1 => 'hertz', 2 => 'Menit'],
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
        $this->assertTrue($hasil[$relasiPgk->id]->benar);
        $this->assertEquals(2.0, (float) $hasil[$relasiPgk->id]->skor);
        $this->assertTrue($hasil[$relasiBenarSalah->id]->benar);
        $this->assertEquals(1.0, (float) $hasil[$relasiBenarSalah->id]->skor);
        $this->assertFalse($hasil[$relasiMenjodohkan->id]->benar);
        $this->assertEquals(1.0, (float) $hasil[$relasiMenjodohkan->id]->skor);
        $this->assertTrue($hasil[$relasiIsian->id]->benar);
        $this->assertEquals(1.0, (float) $hasil[$relasiIsian->id]->skor);
        $this->assertTrue($hasil[$relasiNumerik->id]->benar);
        $this->assertEquals(1.0, (float) $hasil[$relasiNumerik->id]->skor);

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.hasil.index', $ujianCbt))
            ->assertOk()
            ->assertSee('Rekap hasil CBT')
            ->assertSee('85,71')
            ->assertSee('Tuntas')
            ->assertSee('Benar 4, salah 1, kosong 0');

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
        [$tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai] = $this->buatDataAkademik();
        $jenisUjian = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatAnggotaSiswa($tahunPelajaran, $kelas, 1);

        $ujianCbt = UjianCbt::create([
            ...collect($this->dataUjian($jenisUjian, $tahunPelajaran, $mataPelajaran, $kelas, $komponenNilai))
                ->except('kelas_peserta')
                ->all(),
            'jumlah_soal' => 1,
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

        $this->actingAs($administrator)
            ->get(route('ujian-cbt.koreksi-manual.index', $ujianCbt))
            ->assertOk()
            ->assertSee('Koreksi manual CBT')
            ->assertSee('Mengapa suara petir terdengar setelah kilat terlihat?')
            ->assertSee('Karena cahaya merambat lebih cepat daripada bunyi.')
            ->assertSee('Belum dikoreksi');

        $this->actingAs($administrator)
            ->from(route('ujian-cbt.koreksi-manual.index', $ujianCbt))
            ->put(route('ujian-cbt.koreksi-manual.update', $ujianCbt), [
                'skor' => [
                    $jawaban->id => '2',
                ],
            ])
            ->assertRedirect(route('ujian-cbt.koreksi-manual.index', $ujianCbt))
            ->assertSessionHas('berhasil');

        $jawaban->refresh();
        $this->assertTrue($jawaban->benar);
        $this->assertEquals(2.0, (float) $jawaban->skor);

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
            'skor_maksimal' => 1,
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
