<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PanitiaUjianCbt;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SesiKegiatanUjianCbt;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class PaketSoalUjianTerpusatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite belum aktif pada PHP lokal.');
        }

        $this->artisan('migrate:fresh');
    }

    public function test_paket_dibuat_dari_jadwal_dan_pengaturan_pasti_diisi_otomatis(): void
    {
        $data = $this->buatFondasi();
        $soalSatu = $this->buatSoal($data['tahun'], $data['mapel'], 'SOAL-UT-001', 'Pertanyaan pertama');
        $soalDua = $this->buatSoal($data['tahun'], $data['mapel'], 'SOAL-UT-002', 'Pertanyaan kedua', 'sedang');

        $this->actingAs($data['admin'])
            ->get(route('paket-soal-terpusat.index', ['kegiatan' => $data['kegiatan']->id]))
            ->assertOk()
            ->assertSee($data['kegiatan']->jenisUjianCbt->nama)
            ->assertSee($data['kegiatan']->nama)
            ->assertSeeText('120 menit')
            ->assertSee('0 dari 1 paket siap');

        $this->actingAs($data['admin'])
            ->get(route('paket-soal-terpusat.show', $data['jadwal']))
            ->assertOk()
            ->assertSee('Susun paket soal')
            ->assertSee('Informasi lainnya sudah diambil dari jadwal')
            ->assertSeeText('Durasi ujian')
            ->assertSeeText('Jadwal & durasi')
            ->assertSeeText('Mengikuti tanggal dan jam pada Tahap 7.')
            ->assertSee('Pengacakan untuk siswa')
            ->assertSee('Skor soal')
            ->assertSee('name="acak_soal"', false)
            ->assertSee('name="acak_jawaban"', false)
            ->assertSee('Pratinjau')
            ->assertSee('data-question-preview', false)
            ->assertSee('data-preview-source="preview-soal-'.$soalSatu->id.'"', false)
            ->assertSee('Benar')
            ->assertDontSee('name="mata_pelajaran_id"', false)
            ->assertDontSee('name="tingkat"', false)
            ->assertDontSee('name="jumlah_soal"', false)
            ->assertDontSee('name="soal['.$soalSatu->id.'][bobot]"', false)
            ->assertDontSee('name="token"', false);

        $this->actingAs($data['admin'])
            ->put(route('paket-soal-terpusat.update', $data['jadwal']), [
                'aksi' => 'terbitkan',
                'soal' => [
                    $soalSatu->id => ['dipilih' => '1', 'bobot' => 99],
                    $soalDua->id => ['dipilih' => '1', 'bobot' => 99],
                ],
            ])
            ->assertRedirect(route('paket-soal-terpusat.show', $data['jadwal']));

        $paket = UjianCbt::query()->where('alur', 'terpusat')->firstOrFail();
        $this->assertSame($data['jadwal']->id, $paket->jadwalUjianCbt()->firstOrFail()->id);
        $this->assertSame($data['kegiatan']->jenis_ujian_cbt_id, $paket->jenis_ujian_cbt_id);
        $this->assertSame($data['tahun']->id, $paket->tahun_pelajaran_id);
        $this->assertSame($data['mapel']->id, $paket->mata_pelajaran_id);
        $this->assertSame(8, $paket->tingkat);
        $this->assertSame(120, $paket->durasi_menit);
        $this->assertSame(2, $paket->jumlah_soal);
        $this->assertSame(78, $paket->kkm);
        $this->assertSame('terjadwal', $paket->status);
        $this->assertTrue($paket->acak_soal);
        $this->assertTrue($paket->acak_jawaban);
        $this->assertTrue($paket->batasi_satu_perangkat);
        $this->assertTrue($paket->deteksi_pindah_tab);
        $this->assertTrue($paket->wajib_fullscreen);
        $this->assertTrue($paket->blokir_tangkapan_layar);
        $this->assertSame(3, $paket->toleransi_pindah_aplikasi_detik);
        $this->assertSame(3, $paket->batas_pindah_aplikasi);
        $this->assertSame('tahan', $paket->tindakan_pindah_aplikasi);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $paket->token);
        $this->assertSame([1, 2], $paket->soalUjianCbt()->orderBy('nomor_urut')->pluck('nomor_urut')->all());
        $this->assertSame(['1.00', '2.00'], $paket->soalUjianCbt()->orderBy('nomor_urut')->pluck('bobot')->all());
        $this->assertDatabaseHas('jadwal_ujian_cbt', ['id' => $data['jadwal']->id, 'ujian_cbt_id' => $paket->id, 'status' => 'siap']);
        $this->assertDatabaseHas('kelas_ujian_cbt', ['ujian_cbt_id' => $paket->id, 'kelas_id' => $data['kelas']->id]);
        $this->assertDatabaseHas('komponen_nilai', [
            'guru_mata_pelajaran_id' => $data['penugasan']->id,
            'jenis_komponen' => 'sts',
            'semester' => 'ganjil',
            'aktif' => true,
        ]);

        $this->actingAs($data['admin'])
            ->put(route('paket-soal-terpusat.update', $data['jadwal']), [
                'aksi' => 'simpan',
                'acak_soal' => '0',
                'acak_jawaban' => '0',
                'soal' => [
                    $soalSatu->id => ['dipilih' => '1'],
                    $soalDua->id => ['dipilih' => '1'],
                ],
            ])
            ->assertRedirect(route('paket-soal-terpusat.show', $data['jadwal']));

        $paket->refresh();
        $this->assertFalse($paket->acak_soal);
        $this->assertFalse($paket->acak_jawaban);
    }

    public function test_pratinjau_paket_memuat_gambar_stimulus_dan_pilihan_seperti_bank_soal(): void
    {
        $data = $this->buatFondasi();
        $soal = $this->buatSoal($data['tahun'], $data['mapel'], 'SOAL-MEDIA-001', 'Perhatikan gambar.');
        $soal->update([
            'stimulus' => 'Amati diagram berikut.',
            'media' => [
                'konten' => [
                    'stimulus' => ['gambar' => [
                        'path' => 'soal-cbt/diagram-stimulus.png',
                        'alt' => 'Diagram stimulus',
                        'keterangan' => 'Diagram untuk soal',
                    ]],
                    'pilihan_B' => ['gambar' => [
                        'path' => 'soal-cbt/diagram-jawaban.png',
                        'alt' => 'Diagram jawaban',
                        'keterangan' => 'Pilihan B',
                    ]],
                ],
            ],
        ]);

        $this->actingAs($data['admin'])
            ->get(route('paket-soal-terpusat.show', $data['jadwal']))
            ->assertOk()
            ->assertSee('id="preview-soal-'.$soal->id.'"', false)
            ->assertSee('data-preview-source="preview-soal-'.$soal->id.'"', false)
            ->assertSee(Storage::disk('public')->url('soal-cbt/diagram-stimulus.png'))
            ->assertSee(Storage::disk('public')->url('soal-cbt/diagram-jawaban.png'))
            ->assertSee('Diagram untuk soal')
            ->assertSee('Pilihan B');
    }

    public function test_guru_hanya_mengelola_paket_mapel_dan_kelas_yang_diampu(): void
    {
        $data = $this->buatFondasi();
        $mapelLain = MataPelajaran::create(['kode' => 'IPA8', 'nama' => 'IPA', 'tingkat' => 8, 'kkm' => 76, 'aktif' => true]);
        $jadwalLain = JadwalUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $data['kegiatan']->id,
            'sesi_kegiatan_ujian_cbt_id' => $data['sesi']->id,
            'mata_pelajaran_id' => $mapelLain->id,
            'tanggal' => '2026-09-16',
            'waktu_mulai' => '07:30',
            'waktu_selesai' => '09:30',
            'label_sesi' => 'Sesi Pagi',
            'tingkat' => 8,
            'urutan' => 2,
            'status' => 'draft',
        ]);
        $jadwalLain->kelas()->sync([$data['kelas']->id]);
        $soal = $this->buatSoal($data['tahun'], $data['mapel'], 'SOAL-GURU-001', 'Soal guru');

        $this->actingAs($data['akun_guru'])
            ->get(route('paket-soal-terpusat.index', ['kegiatan' => $data['kegiatan']->id]))
            ->assertOk()
            ->assertSee($data['kegiatan']->nama)
            ->assertSee('Matematika')
            ->assertDontSee('>IPA<', false);

        $this->actingAs($data['akun_guru'])
            ->get(route('paket-soal-terpusat.show', $data['jadwal']))
            ->assertOk()
            ->assertSee('SOAL-GURU-001');
        $this->actingAs($data['akun_guru'])
            ->put(route('paket-soal-terpusat.update', $data['jadwal']), [
                'aksi' => 'draf',
                'soal' => [$soal->id => ['dipilih' => '1', 'bobot' => 1]],
            ])
            ->assertRedirect();

        $this->actingAs($data['akun_guru'])
            ->get(route('paket-soal-terpusat.show', $jadwalLain))
            ->assertForbidden();
    }

    public function test_guru_dapat_membuka_hasil_dan_analisis_dari_pusat_cbt_tanpa_akses_pengelolaan_kegiatan(): void
    {
        $data = $this->buatFondasi();
        $soal = $this->buatSoal($data['tahun'], $data['mapel'], 'HASIL-MTK', 'Soal hasil Matematika');
        $mapelLain = MataPelajaran::create(['kode' => 'IPA8', 'nama' => 'IPA khusus', 'tingkat' => 8, 'aktif' => true]);
        $soalLain = $this->buatSoal($data['tahun'], $mapelLain, 'HASIL-IPA', 'Soal hasil IPA');
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'nama' => 'VIII.B di luar penugasan',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $jadwalMapelLain = $data['jadwal']->replicate(['ujian_cbt_id']);
        $jadwalMapelLain->fill(['mata_pelajaran_id' => $mapelLain->id, 'tanggal' => '2026-09-16', 'urutan' => 2])->save();
        $jadwalMapelLain->kelas()->sync([$data['kelas']->id]);
        $jadwalKelasLain = $data['jadwal']->replicate(['ujian_cbt_id']);
        $jadwalKelasLain->fill(['tanggal' => '2026-09-17', 'urutan' => 3])->save();
        $jadwalKelasLain->kelas()->sync([$kelasLain->id]);

        foreach ([[$data['jadwal'], $soal], [$jadwalMapelLain, $soalLain], [$jadwalKelasLain, $soal]] as [$jadwal, $pilihan]) {
            $this->actingAs($data['admin'])
                ->put(route('paket-soal-terpusat.update', $jadwal), [
                    'aksi' => 'terbitkan',
                    'soal' => [$pilihan->id => ['dipilih' => '1']],
                ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $paket = $data['jadwal']->fresh()->ujianCbt;
        $paketMapelLain = $jadwalMapelLain->fresh()->ujianCbt;
        $paketKelasLain = $jadwalKelasLain->fresh()->ujianCbt;
        $this->assertNull($paket->hasil_difinalisasi_pada);

        $this->actingAs($data['akun_guru'])->get(route('pusat-cbt.index'))
            ->assertOk()
            ->assertSeeText('Hasil & Analisis Ujian')
            ->assertSee(route('paket-soal-terpusat.index', ['tampilan' => 'hasil']))
            ->assertDontSeeText('Buka Ujian Terpusat');

        $this->get(route('paket-soal-terpusat.index', ['tampilan' => 'hasil']))
            ->assertOk()
            ->assertViewHas('tampilanHasil', true)
            ->assertViewHas('jumlahJadwal', 1)
            ->assertViewHas('jumlahHasilTersedia', 1)
            ->assertSeeText($data['kegiatan']->nama)
            ->assertSeeText('Lihat nilai')
            ->assertSee(route('ujian-cbt.hasil.index', $paket))
            ->assertSee(route('ujian-cbt.hasil.analisis-soal', $paket))
            ->assertDontSeeText('IPA khusus')
            ->assertDontSeeText($kelasLain->nama)
            ->assertDontSeeText('Terbitkan');

        $this->get(route('ujian-cbt.hasil.index', $paket))->assertOk()->assertSeeText('Rekap hasil CBT');
        $this->get(route('ujian-cbt.hasil.analisis-soal', $paket))->assertOk()->assertSeeText('Analisis soal');
        $this->get(route('ujian-cbt.hasil.rincian-soal', [$paket, $paket->soalUjianCbt()->firstOrFail()]))
            ->assertOk()->assertSeeText('Rincian jawaban dan pengecoh');
        $this->get(route('paket-soal-terpusat.show', $data['jadwal']))
            ->assertOk()->assertSee(route('ujian-cbt.hasil.index', $paket));

        foreach ([$paketMapelLain, $paketKelasLain] as $tidakDiampu) {
            $this->get(route('ujian-cbt.hasil.index', $tidakDiampu))->assertForbidden();
            $this->get(route('ujian-cbt.hasil.analisis-soal', $tidakDiampu))->assertForbidden();
            $this->get(route('ujian-cbt.hasil.rincian-soal', [$tidakDiampu, $tidakDiampu->soalUjianCbt()->firstOrFail()]))->assertForbidden();
        }
        $this->get(route('ujian-cbt.hasil.rincian-soal', [$paket, $paketMapelLain->soalUjianCbt()->firstOrFail()]))->assertNotFound();
        $this->get(route('ujian-terpusat.show', $data['kegiatan']))->assertForbidden();
    }

    public function test_panitia_dapat_memantau_tetapi_tidak_mengubah_paket(): void
    {
        $data = $this->buatFondasi();
        [$pegawaiPanitia, $akunPanitia] = $this->buatAkunPegawai('Panitia Pemantau', '19880002');
        $akunPanitia->daftarPeran()->sync([Peran::where('kode', 'panitia_ujian')->value('id')]);
        PanitiaUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $data['kegiatan']->id,
            'pegawai_id' => $pegawaiPanitia->id,
            'jabatan' => 'anggota',
            'aktif' => true,
        ]);

        $this->actingAs($akunPanitia)
            ->get(route('paket-soal-terpusat.index'))
            ->assertOk()
            ->assertSee('Matematika');
        $this->actingAs($akunPanitia)
            ->get(route('paket-soal-terpusat.show', $data['jadwal']))
            ->assertOk()
            ->assertSee('Panitia dapat memantau')
            ->assertSee('Pratinjau')
            ->assertDontSee('Terbitkan paket');
        $this->actingAs($akunPanitia)
            ->put(route('paket-soal-terpusat.update', $data['jadwal']), ['aksi' => 'draf'])
            ->assertForbidden();
    }

    private function buatFondasi(): array
    {
        $admin = Pengguna::create([
            'nama' => 'Administrator Uji',
            'username' => 'administrator-paket-ut',
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'administrator',
            'aktif' => true,
            'akun_sistem' => true,
        ]);
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $jenis = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kode' => 'UT-PAKET-001',
            'nama' => 'STS Ganjil 2026/2027',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-09-15',
            'tanggal_selesai' => '2026-09-20',
            'status' => 'draft',
            'dibuat_oleh_pengguna_id' => $admin->id,
        ]);
        $sesi = SesiKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'kode' => 'S01',
            'nama' => 'Sesi Pagi',
            'waktu_mulai' => '07:30',
            'waktu_selesai' => '09:30',
            'urutan' => 1,
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
            'kode' => 'MTK8',
            'nama' => 'Matematika',
            'tingkat' => 8,
            'kkm' => 78,
            'aktif' => true,
        ]);
        [$pegawaiGuru, $akunGuru] = $this->buatAkunPegawai('Guru Matematika', '19880001');
        $akunGuru->daftarPeran()->sync([Peran::where('kode', 'guru_mapel')->value('id')]);
        $penugasan = GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'pegawai_id' => $pegawaiGuru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
        $jadwal = JadwalUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
            'mata_pelajaran_id' => $mapel->id,
            'tanggal' => '2026-09-15',
            'waktu_mulai' => '07:30',
            'waktu_selesai' => '09:30',
            'label_sesi' => 'Sesi Pagi',
            'tingkat' => 8,
            'urutan' => 1,
            'status' => 'draft',
        ]);
        $jadwal->kelas()->sync([$kelas->id]);

        return compact('admin', 'tahun', 'kegiatan', 'sesi', 'kelas', 'mapel', 'penugasan', 'jadwal') + [
            'pegawai_guru' => $pegawaiGuru,
            'akun_guru' => $akunGuru,
        ];
    }

    private function buatAkunPegawai(string $nama, string $nip): array
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'nip' => $nip, 'jenis_kelamin' => 'L', 'jenis_pegawai' => 'Guru', 'aktif' => true]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);

        return [$pegawai, $akun];
    }

    private function buatSoal(TahunPelajaran $tahun, MataPelajaran $mapel, string $kode, string $pertanyaan, string $tingkatKesulitan = 'mudah'): SoalCbt
    {
        return SoalCbt::create([
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mapel->id,
            'tingkat' => 8,
            'kode' => $kode,
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => $tingkatKesulitan,
            'kategori' => 'umum',
            'pertanyaan' => $pertanyaan,
            'opsi' => ['pilihan' => ['A' => 'Salah', 'B' => 'Benar']],
            'kunci_jawaban' => ['jawaban' => 'B'],
            'skor_maksimal' => SoalCbt::skorUntukKesulitan($tingkatKesulitan),
            'status' => 'siap',
            'aktif' => true,
        ]);
    }
}
