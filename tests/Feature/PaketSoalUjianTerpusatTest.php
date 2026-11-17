<?php

namespace Tests\Feature;

use App\Models\FolderSoalCbt;
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
use Illuminate\Support\Facades\File;
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
            ->get(route('paket-soal-terpusat.show', [$data['jadwal'], 'mode' => 'susun']))
            ->assertOk()
            ->assertSee('Susun paket soal')
            ->assertSee('Belum ada soal dalam paket ini.')
            ->assertSee('Tambah dari Bank Soal')
            ->assertSeeText('Durasi ujian')
            ->assertSee('Pengacakan untuk siswa')
            ->assertSee('name="acak_soal"', false)
            ->assertSee('name="acak_jawaban"', false)
            ->assertDontSee('Pertanyaan pertama')
            ->assertDontSee('Pertanyaan kedua')
            ->assertDontSee('data-package-question data-question-id', false)
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

    public function test_admin_dapat_menghapus_jadwal_setelah_soal_dikosongkan_dan_paket_kembali_ke_draf(): void
    {
        $data = $this->buatFondasi();
        $jadwal = $data['jadwal'];
        $kegiatan = $data['kegiatan'];
        $jadwalLain = $jadwal->replicate()->fill(['tanggal' => '2026-09-17']);
        $jadwalLain->save();

        $soal = $this->buatSoal($data['tahun'], $data['mapel'], 'SOAL-UT-HAPUS', 'Soal tetap ada di Bank Soal.');
        $folder = FolderSoalCbt::create([
            'nama' => $kegiatan->nama, 'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'mata_pelajaran_id' => $data['mapel']->id, 'tingkat' => 8,
        ]);
        $folder->soal()->attach($soal);

        $halamanJadwal = route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 7]);
        $urlHapus = route('ujian-terpusat.jadwal.destroy', [$kegiatan, $jadwal]);
        $formHapus = 'action="'.$urlHapus.'" method="POST" onsubmit="return confirm';
        $urlPaket = route('paket-soal-terpusat.update', $jadwal);

        $this->actingAs($data['admin'])->put($urlPaket, [
            'aksi' => 'terbitkan', 'soal' => [$soal->id => ['dipilih' => '1']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $paket = $jadwal->refresh()->ujianCbt;

        $this->from($halamanJadwal)->delete($urlHapus)
            ->assertSessionHasErrors(['jadwal' => 'Kosongkan pilihan soal dan simpan draf sebelum menghapus jadwal.']);
        $this->assertSame(1, $paket->soalUjianCbt()->count());

        $this->put($urlPaket, ['aksi' => 'simpan', 'soal' => []])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(0, $paket->soalUjianCbt()->count());
        $this->get($halamanJadwal)->assertOk()
            ->assertSeeText('Kembalikan paket ke draf sebelum menghapus jadwal.')
            ->assertDontSee($formHapus, false);
        $this->from($halamanJadwal)->delete($urlHapus)->assertSessionHasErrors('jadwal');

        $this->put($urlPaket, ['aksi' => 'draf', 'soal' => []])->assertSessionHasNoErrors()->assertRedirect();
        $this->get($halamanJadwal)->assertOk()->assertSee($formHapus, false);
        $this->actingAs($data['akun_guru'])->delete($urlHapus)->assertForbidden();
        $this->actingAs($data['admin'])->delete($urlHapus)
            ->assertRedirect($halamanJadwal)->assertSessionHas('berhasil');

        $this->assertModelMissing($jadwal);
        $this->assertModelExists($jadwalLain);
        $this->assertModelExists($kegiatan);
        $this->assertSame('nonaktif', $paket->fresh()->status);
        $this->assertTrue($folder->soal()->whereKey($soal->id)->exists());
    }

    public function test_draf_kosong_yang_terkunci_atau_dipakai_jadwal_lain_tidak_dapat_dihapus(): void
    {
        $data = $this->buatFondasi();
        $jadwal = $data['jadwal'];
        $urlHapus = route('ujian-terpusat.jadwal.destroy', [$data['kegiatan'], $jadwal]);
        $this->actingAs($data['admin'])->put(route('paket-soal-terpusat.update', $jadwal), ['aksi' => 'draf'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $paket = $jadwal->refresh()->ujianCbt;

        $jadwal->update(['dikunci_pada' => now()]);
        $this->delete($urlHapus)->assertSessionHasErrors(['jadwal' => 'Jadwal dikunci dan tidak dapat dihapus.']);
        $jadwal->update(['dikunci_pada' => null]);

        $jadwalLain = $jadwal->replicate()->fill(['tanggal' => '2026-09-17']);
        $jadwalLain->save();
        $this->delete($urlHapus)->assertSessionHasErrors(['jadwal' => 'Paket bukan milik jadwal ini saja.']);

        $this->assertModelExists($jadwal);
        $this->assertModelExists($jadwalLain);
        $this->assertSame('draft', $paket->fresh()->status);
    }

    public function test_daftar_paket_menampilkan_ujian_terbaru_dengan_jadwal_tetap_kronologis(): void
    {
        $data = $this->buatFondasi();
        $terbaru = $data['kegiatan']->replicate();
        $terbaru->fill([
            'kode' => 'UT-PAKET-002', 'nama' => 'Ujian Desember 2026',
            'tanggal_mulai' => '2026-12-07', 'tanggal_selesai' => '2026-12-14',
        ])->save();
        $tanpaPeriode = $data['kegiatan']->replicate();
        $tanpaPeriode->fill([
            'kode' => 'UT-PAKET-003', 'nama' => 'Ujian November 2026',
            'tanggal_mulai' => null, 'tanggal_selesai' => null,
        ])->save();

        $jadwal = [];
        foreach ([[$terbaru, '2026-12-11', '10:00', '11:30'], [$terbaru, '2026-12-11', '07:30', '09:00'], [$tanpaPeriode, '2026-11-05', '07:30', '09:00']]
            as [$kegiatan, $tanggal, $mulai, $selesai]) {
            $baris = $data['jadwal']->replicate();
            $baris->fill([
                'kegiatan_ujian_cbt_id' => $kegiatan->id, 'sesi_kegiatan_ujian_cbt_id' => null,
                'tanggal' => $tanggal, 'waktu_mulai' => $mulai, 'waktu_selesai' => $selesai,
            ])->save();
            $baris->kelas()->sync([$data['kelas']->id]);
            $jadwal[] = $baris;
        }

        $this->actingAs($data['akun_guru'])->get(route('paket-soal-terpusat.index'))
            ->assertOk()
            ->assertSeeInOrder([
                $terbaru->nama,
                route('paket-soal-terpusat.show', $jadwal[1]),
                route('paket-soal-terpusat.show', $jadwal[0]),
                $tanpaPeriode->nama,
                $data['kegiatan']->nama,
            ]);

        $this->get(route('paket-soal-terpusat.index', ['tampilan' => 'hasil']))
            ->assertOk()
            ->assertSeeInOrder([$data['kegiatan']->nama, $tanpaPeriode->nama, $terbaru->nama]);
    }

    public function test_panitia_melengkapi_folder_ujian_lama_tanpa_mengubah_folder_dan_soal_yang_sudah_ada(): void
    {
        $data = $this->buatFondasi();
        $kegiatan = $data['kegiatan'];
        $konteks = ['mata_pelajaran_id' => $data['mapel']->id, 'tingkat' => 8];
        $lama = FolderSoalCbt::create([...$konteks, 'nama' => $kegiatan->nama]);
        $soal = $this->buatSoal($data['tahun'], $data['mapel'], 'FOLDER-STS-LAMA', 'Butir yang sudah dikelompokkan.');
        $lama->soal()->attach($soal);
        $jadwalLanjutan = $data['jadwal']->replicate();
        $jadwalLanjutan->fill(['tanggal' => '2026-09-17'])->save();

        $url = route('ujian-terpusat.folder-soal.siapkan', $kegiatan);
        $halaman = route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 7]);
        $this->actingAs($data['akun_guru'])->post($url)->assertForbidden();

        [$pegawaiPanitia, $panitia] = $this->buatAkunPegawai('Panitia Folder', '19880003');
        $panitia->daftarPeran()->attach(Peran::where('kode', 'panitia_ujian')->value('id'));
        $this->actingAs($panitia)->post($url)->assertForbidden();
        PanitiaUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id, 'pegawai_id' => $pegawaiPanitia->id,
            'jabatan' => 'anggota', 'aktif' => true,
        ]);

        $jadwalWeb = $this->actingAs($panitia)->get($halaman)->assertOk()->assertSee('Lengkapi folder Bank Soal');
        $this->assertDatabaseCount('folder_soal_cbt', 1);
        $this->post($url)->assertRedirect($halaman)->assertSessionHas('berhasil', '1 folder Bank Soal baru disiapkan.');
        $baru = FolderSoalCbt::where('kegiatan_ujian_cbt_id', $kegiatan->id)->firstOrFail();
        $this->assertSame("{$kegiatan->nama} ({$kegiatan->kode})", $baru->nama);
        $this->assertEquals($konteks, $baru->only(array_keys($konteks)));
        $this->assertSame([$soal->id], $lama->soal()->pluck('soal_cbt.id')->all());
        $this->assertNull($lama->fresh()->kegiatan_ujian_cbt_id);
        $this->assertFalse($baru->soal()->exists());

        $kegiatan->update(['nama' => 'Judul ujian diperbarui']);
        $this->post($url)->assertRedirect($halaman)->assertSessionHas('berhasil', 'Tidak ada folder Bank Soal baru yang perlu disiapkan.');
        $this->assertDatabaseCount('folder_soal_cbt', 2);
        $this->assertDatabaseCount('soal_cbt', 1);
        $this->assertDatabaseCount('ujian_cbt', 0);

        $folderUrl = route('soal-cbt.index', ['folder' => $baru->id]);
        $bankSoalWeb = $this->actingAs($data['akun_guru'])->get(route('soal-cbt.index'))
            ->assertOk()->assertSee($folderUrl)->assertSeeText($baru->nama);
        $this->get($folderUrl)->assertOk()->assertDontSeeText($soal->pertanyaan);
        $data['penugasan']->update(['aktif' => false]);
        $this->get($folderUrl)->assertForbidden();

        if (getenv('NUSA_CAPTURE_FOLDER_UJIAN')) {
            $direktori = storage_path('framework/testing/folder-ujian');
            File::ensureDirectoryExists($direktori);
            File::put($direktori.'/jadwal.html', $jadwalWeb->getContent());
            File::put($direktori.'/bank-soal.html', $bankSoalWeb->getContent());
        }
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
            ->get(route('paket-soal-terpusat.bank-soal', $data['jadwal']))
            ->assertOk()
            ->assertSee('data-question-id="'.$soal->id.'"', false)
            ->assertSee('data-question-preview-source', false)
            ->assertSee('data-question-preview aria-haspopup="dialog"', false)
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
            ->assertSeeText('Belum ada soal dalam paket ini.')
            ->assertDontSee('SOAL-GURU-001');
        $this->get(route('paket-soal-terpusat.bank-soal', $data['jadwal']))
            ->assertOk()
            ->assertSee('SOAL-GURU-001');
        $this->actingAs($data['akun_guru'])
            ->put(route('paket-soal-terpusat.update', $data['jadwal']), [
                'aksi' => 'draf',
                'soal' => [$soal->id => ['dipilih' => '1', 'bobot' => 1]],
            ])
            ->assertRedirect();

        $this->get(route('paket-soal-terpusat.show', $jadwalLain))->assertForbidden();
        $this->get(route('paket-soal-terpusat.bank-soal', $jadwalLain))->assertForbidden();
    }

    public function test_modal_bank_menyaring_folder_dan_paket_tetap_mengikuti_urutan_yang_disimpan(): void
    {
        $data = $this->buatFondasi();
        $jadwal = $data['jadwal'];
        $folderA = FolderSoalCbt::create(['nama' => 'Folder Bilangan', 'mata_pelajaran_id' => $data['mapel']->id, 'tingkat' => 8]);
        $folderB = FolderSoalCbt::create(['nama' => 'Folder Logika', 'mata_pelajaran_id' => $data['mapel']->id, 'tingkat' => 8]);
        $satu = $this->buatSoal($data['tahun'], $data['mapel'], 'BANK-A-SATU', 'Berapa nilai masukan dengan angka 0?');
        $satu->update(['opsi' => ['pilihan' => ['A' => '0', 'B' => '1']]]);
        $dua = $this->buatSoal($data['tahun'], $data['mapel'], 'BANK-B-DUA', 'Pertanyaan logika kedua.', 'sulit');
        $tidakDipilih = $this->buatSoal($data['tahun'], $data['mapel'], 'BANK-C-LAIN', 'Pertanyaan tidak masuk paket.');
        $belumSiap = $this->buatSoal($data['tahun'], $data['mapel'], 'BANK-DRAF', 'Pertanyaan belum siap.');
        $belumSiap->update(['status' => 'draft']);
        $tingkatLain = $this->buatSoal($data['tahun'], $data['mapel'], 'BANK-TINGKAT-9', 'Pertanyaan tingkat lain.');
        $tingkatLain->update(['tingkat' => 9]);
        $folderA->soal()->attach([$satu->id, $belumSiap->id, $tingkatLain->id]);
        $folderB->soal()->attach([$dua->id, $tidakDipilih->id]);
        $url = route('paket-soal-terpusat.show', $jadwal);
        $urlSusun = route('paket-soal-terpusat.show', [$jadwal, 'mode' => 'susun']);
        $update = route('paket-soal-terpusat.update', [$jadwal, 'mode' => 'susun']);
        $this->actingAs($data['akun_guru']);

        $kosong = $this->get($url)->assertOk()->assertSee('Folder Bilangan')->assertSee('Folder Logika')
            ->assertDontSee('BANK-A-SATU');
        $bank = $this->get(route('paket-soal-terpusat.bank-soal', $jadwal))->assertOk()
            ->assertSeeInOrder([$satu->kode, $dua->kode, $tidakDipilih->kode])
            ->assertDontSee($belumSiap->kode)->assertDontSee($tingkatLain->kode);
        $bankA = $this->get(route('paket-soal-terpusat.bank-soal', [$jadwal, 'folder' => $folderA->id, 'cari' => '0']))
            ->assertOk()->assertSee($satu->kode)->assertDontSee($dua->kode)->assertDontSee($tingkatLain->kode);
        $bankB = $this->get(route('paket-soal-terpusat.bank-soal', [$jadwal, 'folder' => $folderB->id]))
            ->assertOk()->assertSee($dua->kode)->assertSee($tidakDipilih->kode)->assertDontSee($satu->kode);
        $this->get(route('paket-soal-terpusat.bank-soal', [$jadwal, 'folder' => $folderB->id, 'kesulitan' => 'sulit']))
            ->assertOk()->assertSee($dua->kode)->assertDontSee($tidakDipilih->kode);
        $this->get(route('paket-soal-terpusat.bank-soal', [$jadwal, 'cari' => '0']))
            ->assertOk()->assertSee($satu->kode)->assertDontSee($dua->kode);

        $this->put($update, [
            'aksi' => 'draf', 'daftar_soal_dikirim' => '1',
            'soal' => [$dua->id => ['dipilih' => '1'], $satu->id => ['dipilih' => '1']],
        ])->assertSessionHasNoErrors()->assertRedirect($urlSusun);
        $this->get($url)->assertOk()
            ->assertSeeInOrder([$dua->kode, $satu->kode])->assertDontSee($tidakDipilih->kode);
        $this->assertSame([$dua->id, $satu->id], $jadwal->refresh()->ujianCbt->soalUjianCbt()->orderBy('nomor_urut')->pluck('soal_cbt_id')->all());

        $this->from($urlSusun)->put($update, ['aksi' => 'terbitkan', 'daftar_soal_dikirim' => '1'])
            ->assertSessionHasErrors('soal');
        $this->get($urlSusun)->assertOk()
            ->assertSee('Belum ada soal dalam paket ini.')->assertDontSee($dua->kode);
        $periksa = $this->actingAs($data['admin'])->get($url)->assertOk()
            ->assertSee('Periksa paket soal')->assertSee($urlSusun)
            ->assertSeeInOrder([$dua->kode, $satu->kode])
            ->assertDontSee($tidakDipilih->kode)->assertDontSee('data-open-bank', false);

        if (getenv('NUSA_CAPTURE_PAKET_SOAL')) {
            $direktori = storage_path('framework/testing/paket-soal');
            File::ensureDirectoryExists($direktori);
            foreach (['susun' => $kosong, 'bank' => $bank, 'bank-a' => $bankA, 'bank-b' => $bankB, 'periksa' => $periksa] as $nama => $respons) {
                File::put($direktori.'/'.$nama.'.html', $respons->getContent());
            }
        }
    }

    public function test_bank_soal_dimuat_per_halaman(): void
    {
        $data = $this->buatFondasi();
        foreach (range(1, 21) as $nomor) {
            $this->buatSoal($data['tahun'], $data['mapel'], sprintf('BANK-PAG-%02d', $nomor), "Pertanyaan halaman {$nomor}.");
        }

        $this->actingAs($data['akun_guru'])
            ->get(route('paket-soal-terpusat.bank-soal', $data['jadwal']))
            ->assertOk()->assertSee('BANK-PAG-01')->assertSee('BANK-PAG-20')->assertDontSee('BANK-PAG-21')
            ->assertSee('Hal. 1 dari 2');
        $this->get(route('paket-soal-terpusat.bank-soal', [$data['jadwal'], 'halaman' => 2]))
            ->assertOk()->assertSee('BANK-PAG-21')->assertDontSee('BANK-PAG-20')->assertSee('Hal. 2 dari 2');
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
        $soal = $this->buatSoal($data['tahun'], $data['mapel'], 'PAKET-PANITIA-1', 'Soal yang dipilih guru.');
        $this->buatSoal($data['tahun'], $data['mapel'], 'PAKET-PANITIA-2', 'Soal lain belum dipilih.');
        $this->actingAs($data['akun_guru'])
            ->put(route('paket-soal-terpusat.update', $data['jadwal']), ['aksi' => 'draf', 'soal' => [$soal->id => ['dipilih' => '1']]])
            ->assertSessionHasNoErrors();

        $this->actingAs($akunPanitia)
            ->get(route('paket-soal-terpusat.index'))
            ->assertOk()
            ->assertSee('Matematika');
        $this->actingAs($akunPanitia)
            ->get(route('paket-soal-terpusat.show', [$data['jadwal'], 'mode' => 'susun']))
            ->assertOk()
            ->assertSee('Periksa paket soal')
            ->assertSee('Soal yang dipilih guru.')
            ->assertDontSee('Soal lain belum dipilih.')
            ->assertSee('Pratinjau')
            ->assertDontSee('data-bank-dialog', false)
            ->assertDontSee('Terbitkan paket');
        $this->get(route('paket-soal-terpusat.bank-soal', $data['jadwal']))->assertForbidden();
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
