<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\AktivitasKeamananUjianCbt;
use App\Models\BuktiRuangUjianCbt;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalUjianCbt;
use App\Models\JawabanPesertaUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PanitiaUjianCbt;
use App\Models\Pegawai;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\SesiKegiatanUjianCbt;
use App\Models\Siswa;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use App\Services\Cbt\BagiPesertaUjianTerpusat;
use App\Services\Cbt\FinalisasiHasilUjianTerpusatService;
use App\Services\Cbt\KelayakanPenyelesaianUjianCbtService;
use App\Services\Cbt\KelolaJadwalUjianTerpusat;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use App\Services\Cbt\PengacakPenyajianCbt;
use App\Services\Cbt\TerapkanNilaiCbtService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class UjianTerpusatPelaksanaanNilaiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite belum aktif pada PHP lokal.');
        }

        $this->artisan('migrate:fresh');
    }

    public function test_paket_terbit_menyinkronkan_peserta_ruang_dan_tampil_di_akun_siswa(): void
    {
        $data = $this->buatFondasi();
        $data['sesi']->update([
            'waktu_mulai' => '06:00',
            'waktu_selesai' => '06:30',
        ]);
        $soal = SoalCbt::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'mata_pelajaran_id' => $data['mapel']->id,
            'tingkat' => 7,
            'kode' => 'SOAL-TAHAP-7-001',
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'umum',
            'pertanyaan' => 'Hasil dari 2 + 2 adalah ....',
            'opsi' => ['pilihan' => ['A' => '3', 'B' => '4']],
            'kunci_jawaban' => ['jawaban' => 'B'],
            'skor_maksimal' => 1,
            'status' => 'siap',
            'aktif' => true,
        ]);

        $this->actingAs($data['admin'])
            ->put(route('paket-soal-terpusat.update', $data['jadwal']), [
                'aksi' => 'terbitkan',
                'soal' => [$soal->id => ['dipilih' => '1', 'bobot' => 1]],
            ])
            ->assertRedirect();

        $paket = UjianCbt::query()->where('alur', 'terpusat')->firstOrFail();
        $this->assertSame('2026-09-15 07:30', $paket->tanggal_mulai?->format('Y-m-d H:i'));
        $this->assertSame('2026-09-15 09:00', $paket->tanggal_selesai?->format('Y-m-d H:i'));
        $this->assertSame(90, $paket->durasi_menit);
        $this->assertDatabaseHas('sesi_ujian_cbt', [
            'ujian_cbt_id' => $paket->id,
            'sesi_kegiatan_ujian_cbt_id' => $data['sesi']->id,
            'status' => 'aktif',
        ]);
        $this->assertDatabaseHas('ruang_ujian_cbt', [
            'ujian_cbt_id' => $paket->id,
            'ruang_kegiatan_ujian_cbt_id' => $data['ruang']->id,
            'jadwal_ujian_cbt_id' => $data['jadwal']->id,
        ]);
        $this->assertDatabaseCount('peserta_ujian_cbt', 2);
        $this->assertDatabaseHas('peserta_ujian_cbt', [
            'ujian_cbt_id' => $paket->id,
            'anggota_kelas_id' => $data['anggota'][0]->id,
            'nomor_meja' => 1,
            'kode_meja' => 'STS-2627-01-S01-R01-M001',
            'status' => 'aktif',
        ]);

        $this->actingAs($data['akun_siswa'])
            ->get(route('ujian-saya.index'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('Ruang 1')
            ->assertSee('Kode meja')
            ->assertSee('STS-2627-01-S01-R01-M001');

        $this->actingAs($data['admin'])
            ->get(route('ujian-terpusat.pelaksanaan-nilai.index', $data['kegiatan']))
            ->assertOk()
            ->assertSee('Pelaksanaan ujian')
            ->assertSee('execution-flow-number">1</span><div><strong>Siapkan ruang', false)
            ->assertSee('Pantau ujian')
            ->assertSeeText('Nilai & hasil')
            ->assertSee($paket->token);

        $this->assertSame(2, $paket->pesertaUjianCbt()->whereNotNull('ruang_ujian_cbt_id')->count());
        $this->assertSame(2, $paket->pesertaUjianCbt()->whereNotNull('kode_meja')->count());

        $this->actingAs($data['admin'])
            ->get(route('ujian-terpusat.nilai-hasil.index', $data['kegiatan']))
            ->assertOk()
            ->assertSee('Nilai & hasil ujian')
            ->assertSee('execution-flow-number">1</span><div><strong>Periksa jawaban', false)
            ->assertSee('Lihat hasil ujian');

        $this->actingAs($data['akun_guru'])
            ->get(route('ujian-terpusat.nilai-hasil.index', $data['kegiatan']))
            ->assertOk()
            ->assertSee('Koreksi uraian');
        $this->actingAs($data['akun_guru'])
            ->get(route('ujian-cbt.monitoring.index', $paket))
            ->assertOk()
            ->assertSee('Kembali ke pelaksanaan')
            ->assertDontSee('Peserta & sesi')
            ->assertDontSee('Detail paket')
            ->assertDontSee('>Ruang</a>', false)
            ->assertDontSee('Koreksi otomatis');

        $this->actingAs($data['admin'])
            ->get(route('ujian-cbt.monitoring.index', $paket))
            ->assertOk()
            ->assertSee('Presensi ruang')
            ->assertDontSee('Peserta & sesi')
            ->assertDontSee('Detail paket');
        $this->actingAs($data['akun_guru'])
            ->get(route('ujian-cbt.hasil.index', $paket))
            ->assertOk()
            ->assertSeeText('Kembali ke Nilai & Hasil')
            ->assertDontSee('Detail paket')
            ->assertDontSee('>Ruang</a>', false);

        $this->actingAs($data['admin'])
            ->get(route('ujian-cbt.show', $paket))
            ->assertRedirect(route('paket-soal-terpusat.show', $data['jadwal']));

        $this->actingAs($data['admin'])
            ->get(route('ujian-cbt.index'))
            ->assertRedirect(route('pusat-cbt.index'));
    }

    public function test_pengawas_ruang_disimpan_dan_diteruskan_ke_ruang_operasional(): void
    {
        Storage::fake('local');
        $data = $this->buatFondasi();
        $paket = UjianCbt::create([
            'alur' => 'terpusat',
            'jenis_ujian_cbt_id' => $data['kegiatan']->jenis_ujian_cbt_id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'mata_pelajaran_id' => $data['mapel']->id,
            'kode' => 'UT-PENGAWAS-001',
            'nama' => 'STS Matematika Tingkat 7',
            'semester' => 'ganjil',
            'tingkat' => 7,
            'tanggal_mulai' => '2026-09-15 07:30:00',
            'tanggal_selesai' => '2026-09-15 09:00:00',
            'durasi_menit' => 90,
            'jumlah_soal' => 0,
            'token' => '123456',
            'status' => 'terjadwal',
        ]);
        $paket->kelasUjianCbt()->create(['kelas_id' => $data['kelas']->id]);
        $data['jadwal']->update(['ujian_cbt_id' => $paket->id, 'status' => 'siap']);

        $pengawas = Pegawai::create([
            'nama_lengkap' => 'Guru Pengawas Ruang',
            'nip' => '198811112020121001',
            'jenis_kelamin' => 'P',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akunPengawas = Pengguna::create([
            'pegawai_id' => $pengawas->id,
            'nama' => $pengawas->nama_lengkap,
            'username' => $pengawas->nip,
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akunPengawas->daftarPeran()->sync([Peran::query()->where('kode', 'guru_mapel')->value('id')]);
        $pegawaiPanitia = Pegawai::create([
            'nama_lengkap' => 'Panitia Pemeriksa Bukti',
            'nip' => '198811112020121002',
            'jenis_kelamin' => 'P',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akunPanitia = Pengguna::create([
            'pegawai_id' => $pegawaiPanitia->id,
            'nama' => $pegawaiPanitia->nama_lengkap,
            'username' => $pegawaiPanitia->nip,
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akunPanitia->daftarPeran()->sync([Peran::query()->where('kode', 'panitia_ujian')->value('id')]);
        PanitiaUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $data['kegiatan']->id,
            'pegawai_id' => $pegawaiPanitia->id,
            'jabatan' => 'sekretaris',
            'aktif' => true,
            'ditugaskan_oleh_pengguna_id' => $data['admin']->id,
        ]);

        $this->actingAs($data['admin'])
            ->put(route('ujian-terpusat.pengawas.update', [
                $data['kegiatan'],
                $data['jadwal'],
                $data['ruang'],
            ]), [
                'pengawas_utama_pegawai_id' => $pengawas->id,
                'catatan' => 'Membawa daftar hadir',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pengawas_ruang_ujian_terpusat', [
            'jadwal_ujian_cbt_id' => $data['jadwal']->id,
            'ruang_kegiatan_ujian_cbt_id' => $data['ruang']->id,
            'pengawas_utama_pegawai_id' => $pengawas->id,
        ]);
        $this->assertDatabaseHas('ruang_ujian_cbt', [
            'ujian_cbt_id' => $paket->id,
            'ruang_kegiatan_ujian_cbt_id' => $data['ruang']->id,
            'pengawas_utama_pegawai_id' => $pengawas->id,
        ]);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $akunPengawas->id,
            'jenis' => 'penting',
            'judul' => 'Tugas pengawas ujian baru',
            'tautan' => route('tugas-pengawas-ujian.index', absolute: false),
        ]);
        $this->actingAs($data['admin'])
            ->put(route('ujian-terpusat.pengawas.update', [
                $data['kegiatan'],
                $data['jadwal'],
                $data['ruang'],
            ]), [
                'pengawas_utama_pegawai_id' => $pengawas->id,
                'catatan' => 'Catatan tugas diperbarui',
            ])
            ->assertRedirect();
        $this->assertSame(
            1,
            $akunPengawas->notifikasiPengguna()->where('judul', 'Tugas pengawas ujian baru')->count(),
        );
        $ruangOperasional = $paket->ruangUjianCbt()->firstOrFail();

        $halamanDaftarTugas = $this->actingAs($akunPengawas)
            ->get(route('tugas-pengawas-ujian.index'))
            ->assertOk()
            ->assertSeeText('Tugas Pengawas Saya')
            ->assertSeeText('Ruang 1');
        if (getenv('CBT_SUPERVISOR_FIXTURE')) {
            file_put_contents(storage_path('logs/cbt-supervisor-index.html'), $halamanDaftarTugas->getContent());
        }
        $this->actingAs($akunPengawas)
            ->get(route('tugas-pengawas-ujian.show', $ruangOperasional))
            ->assertOk()
            ->assertSeeText('Ruang pengawas')
            ->assertSeeText('Token ujian')
            ->assertSeeText('Buka presensi ruang')
            ->assertDontSeeText('Unggah bukti');
        foreach (['persiapan', 'pantau', 'bukti'] as $tahap) {
            $halaman = $this->actingAs($akunPengawas)
                ->get(route('tugas-pengawas-ujian.show', [$ruangOperasional, 'tahap' => $tahap]))
                ->assertOk();
            if ($tahap === 'pantau') {
                $halaman->assertSeeText('Soal dengan jawaban tersimpan')
                    ->assertDontSeeText('Koreksi otomatis')
                    ->assertViewHas('pesertaPantau', fn ($peserta) => $peserta->count() === 2 && $peserta->every(fn ($item) => $item->ruang_ujian_cbt_id === $ruangOperasional->id));
            }
            if (getenv('CBT_SUPERVISOR_FIXTURE')) {
                file_put_contents(storage_path('logs/cbt-supervisor-'.$tahap.'.html'), $halaman->getContent());
            }
        }
        $halamanPresensi = $this->actingAs($akunPengawas)
            ->get(route('presensi-ujian-cbt.show', [$paket, $ruangOperasional]))
            ->assertOk()
            ->assertSeeText('Presensi Ujian CBT')
            ->assertSeeText('Kamera pemindai')
            ->assertSeeText('Scanner USB atau input NISN')
            ->assertSeeText('Daftar peserta ruang');
        if (getenv('CBT_SUPERVISOR_FIXTURE')) {
            file_put_contents(storage_path('logs/cbt-supervisor-attendance.html'), $halamanPresensi->getContent());
        }
        $this->actingAs($akunPengawas)
            ->get(route('tugas-pengawas-ujian.show', [$ruangOperasional, 'tahap' => 'bukti']))
            ->assertOk()
            ->assertSeeText('Ambil foto atau pilih berkas')
            ->assertSeeText('Kirim ke panitia');
        $this->actingAs($data['akun_guru'])
            ->get(route('tugas-pengawas-ujian.show', $ruangOperasional))
            ->assertForbidden();

        $pesertaDitahan = $ruangOperasional->pesertaUjianCbt()->firstOrFail();
        $pesertaDitahan->update([
            'status' => 'terblokir',
            'ditahan_mode_aman_pada' => now(),
            'jumlah_pindah_aplikasi' => 3,
            'durasi_di_luar_aplikasi_detik' => 12,
        ]);
        AktivitasKeamananUjianCbt::create([
            'peserta_ujian_cbt_id' => $pesertaDitahan->id,
            'jenis' => 'keluar_aplikasi',
            'mulai_pada' => now()->subMinutes(2),
            'selesai_pada' => now()->subMinutes(2)->addSeconds(4),
            'durasi_detik' => 4,
            'dihitung' => true,
        ]);
        AktivitasKeamananUjianCbt::create([
            'peserta_ujian_cbt_id' => $pesertaDitahan->id,
            'jenis' => 'keluar_aplikasi',
            'mulai_pada' => now()->subMinute(),
            'selesai_pada' => now()->subMinute()->addSeconds(2),
            'durasi_detik' => 2,
            'dihitung' => false,
        ]);
        $ruteBukaModeAman = route('tugas-pengawas-ujian.mode-aman.buka', [$ruangOperasional, $pesertaDitahan]);
        $ruteRiwayatModeAman = route('tugas-pengawas-ujian.mode-aman.riwayat', [$ruangOperasional, $pesertaDitahan]);
        $halamanDitahan = $this->actingAs($akunPengawas)
            ->get(route('tugas-pengawas-ujian.show', [$ruangOperasional, 'tahap' => 'pantau']))
            ->assertOk()
            ->assertSeeText('Tinjau & buka')
            ->assertSeeText('3 kejadian dihitung')
            ->assertSee($ruteRiwayatModeAman, false);
        if (getenv('CBT_SUPERVISOR_FIXTURE')) {
            file_put_contents(storage_path('logs/cbt-supervisor-pantau-held.html'), $halamanDitahan->getContent());
        }
        $halamanRiwayat = $this->actingAs($akunPengawas)
            ->get($ruteRiwayatModeAman)
            ->assertOk()
            ->assertSeeText('Riwayat Mode Aman')
            ->assertSeeText('3 kali')
            ->assertSeeText('12 detik')
            ->assertSeeText('Di bawah batas toleransi')
            ->assertSeeText('Dihitung sebagai kejadian')
            ->assertSee($ruteBukaModeAman, false);
        if (getenv('CBT_SUPERVISOR_FIXTURE')) {
            file_put_contents(storage_path('logs/cbt-supervisor-mode-aman-history.html'), $halamanRiwayat->getContent());
        }
        $this->actingAs($data['akun_guru'])
            ->get($ruteRiwayatModeAman)
            ->assertForbidden();
        $this->actingAs($data['akun_guru'])
            ->post($ruteBukaModeAman)
            ->assertForbidden();
        $this->assertSame('terblokir', $pesertaDitahan->fresh()->status);
        $this->actingAs($akunPengawas)
            ->post($ruteBukaModeAman)
            ->assertSessionHasErrors('alasan_pembukaan');
        $this->actingAs($akunPengawas)
            ->post($ruteBukaModeAman, ['alasan_pembukaan' => 'Singkat'])
            ->assertSessionHasErrors('alasan_pembukaan');
        $this->assertSame('terblokir', $pesertaDitahan->fresh()->status);
        $this->actingAs($akunPengawas)
            ->post($ruteBukaModeAman, ['alasan_pembukaan' => 'Aplikasi tertutup saat ada panggilan masuk.'])
            ->assertRedirect($ruteRiwayatModeAman);
        $this->assertDatabaseHas('peserta_ujian_cbt', [
            'id' => $pesertaDitahan->id,
            'status' => 'sedang_mengerjakan',
            'dibuka_mode_aman_oleh_pengguna_id' => $akunPengawas->id,
        ]);
        $this->assertDatabaseHas('aktivitas_keamanan_ujian_cbt', [
            'peserta_ujian_cbt_id' => $pesertaDitahan->id,
            'jenis' => 'buka_mode_aman',
            'oleh_pengguna_id' => $akunPengawas->id,
            'catatan' => 'Aplikasi tertutup saat ada panggilan masuk.',
        ]);
        $this->actingAs($akunPengawas)
            ->get($ruteRiwayatModeAman)
            ->assertOk()
            ->assertSeeText('Aplikasi tertutup saat ada panggilan masuk.');
        $this->actingAs($akunPengawas)
            ->post($ruteBukaModeAman, ['alasan_pembukaan' => 'Mencoba membuka ulang tanpa tahanan.'])
            ->assertSessionHasErrors('peserta');
        $this->assertSame(1, $pesertaDitahan->aktivitasKeamananUjianCbt()->where('jenis', 'buka_mode_aman')->count());

        foreach (['daftar-hadir-1.jpg', 'daftar-hadir-2.jpg'] as $namaFile) {
            $this->actingAs($akunPengawas)
                ->post(route('tugas-pengawas-ujian.bukti.store', $ruangOperasional), [
                    'jenis' => BuktiRuangUjianCbt::JENIS_DAFTAR_HADIR,
                    'berkas' => UploadedFile::fake()->create($namaFile, 350, 'image/jpeg'),
                ])
                ->assertRedirect();
        }
        $this->actingAs($akunPengawas)
            ->post(route('tugas-pengawas-ujian.bukti.store', $ruangOperasional), [
                'jenis' => BuktiRuangUjianCbt::JENIS_BERITA_ACARA,
                'berkas' => UploadedFile::fake()->create('berita-acara.jpg', 350, 'image/jpeg'),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('bukti_ruang_ujian_cbt', 3);
        $this->assertSame('siap_dikirim', $ruangOperasional->fresh()->status_bukti);
        $this->actingAs($akunPengawas)
            ->patch(route('tugas-pengawas-ujian.kirim', $ruangOperasional))
            ->assertRedirect();
        $this->assertSame('menunggu_pemeriksaan', $ruangOperasional->fresh()->status_bukti);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $akunPanitia->id,
            'jenis' => 'penting',
            'judul' => 'Bukti ruang menunggu pemeriksaan',
            'tautan' => route('tugas-pengawas-ujian.show', [
                'ruangUjianCbt' => $ruangOperasional,
                'kembali' => 'panitia',
            ], false),
        ]);
        $this->actingAs($akunPengawas)
            ->post(route('tugas-pengawas-ujian.bukti.store', $ruangOperasional), [
                'jenis' => BuktiRuangUjianCbt::JENIS_DAFTAR_HADIR,
                'berkas' => UploadedFile::fake()->create('tambahan.jpg', 350, 'image/jpeg'),
            ])
            ->assertStatus(422);

        $this->actingAs($data['admin'])
            ->get(route('ujian-terpusat.pelaksanaan-nilai.index', $data['kegiatan']))
            ->assertOk()
            ->assertSeeText('Cetak hadir & berita acara')
            ->assertSeeText('Menunggu pemeriksaan')
            ->assertSeeText('2 hadir · 1 BA')
            ->assertSeeText('Periksa bukti');

        $this->actingAs($akunPanitia)
            ->patch(route('tugas-pengawas-ujian.periksa', $ruangOperasional), [
                'hasil' => 'perlu_diulang',
                'catatan' => 'Foto daftar hadir halaman kedua kurang jelas.',
            ])
            ->assertRedirect();
        $this->assertSame('perlu_diulang', $ruangOperasional->fresh()->status_bukti);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $akunPengawas->id,
            'jenis' => 'peringatan',
            'judul' => 'Bukti ujian perlu difoto ulang',
            'tautan' => route('tugas-pengawas-ujian.show', $ruangOperasional, false),
        ]);

        $this->actingAs($akunPengawas)
            ->patch(route('tugas-pengawas-ujian.kirim', $ruangOperasional))
            ->assertRedirect();
        $this->actingAs($data['admin'])
            ->patch(route('tugas-pengawas-ujian.periksa', $ruangOperasional), [
                'hasil' => 'valid',
            ])
            ->assertRedirect();
        $this->assertSame('valid', $ruangOperasional->fresh()->status_bukti);

        $this->actingAs($data['admin'])
            ->get(route('ujian-terpusat.dokumen-ruang.cetak', [
                $data['kegiatan'],
                $data['jadwal'],
                $data['ruang'],
            ]))
            ->assertOk()
            ->assertSeeText('Daftar Hadir Peserta Ujian CBT')
            ->assertSeeText('Berita Acara Ujian CBT')
            ->assertSeeText('Guru Pengawas Ruang')
            ->assertSeeText('Ruang 1')
            ->assertSeeText('Kode Meja');

        $pengawasPengganti = Pegawai::create([
            'nama_lengkap' => 'Guru Pengawas Pengganti',
            'nip' => '198811112020121003',
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akunPengganti = Pengguna::create([
            'pegawai_id' => $pengawasPengganti->id,
            'nama' => $pengawasPengganti->nama_lengkap,
            'username' => $pengawasPengganti->nip,
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);

        $this->actingAs($data['admin'])
            ->put(route('ujian-terpusat.pengawas.update', [
                $data['kegiatan'],
                $data['jadwal'],
                $data['ruang'],
            ]), [
                'pengawas_utama_pegawai_id' => $pengawasPengganti->id,
            ])
            ->assertSessionHasErrors('pengawas_utama_pegawai_id');
        $this->assertDatabaseMissing('riwayat_pergantian_pengawas_ujian', [
            'pegawai_baru_id' => $pengawasPengganti->id,
        ]);

        $this->actingAs($data['admin'])
            ->patch(route('ujian-terpusat.pengawas.ganti', [
                $data['kegiatan'],
                $data['jadwal'],
                $data['ruang'],
            ]), [
                'peran_pengawas' => 'utama',
                'pegawai_pengganti_id' => $pengawasPengganti->id,
                'alasan' => 'Pengawas utama sakit pada hari pelaksanaan ujian.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('riwayat_pergantian_pengawas_ujian', [
            'jadwal_ujian_cbt_id' => $data['jadwal']->id,
            'ruang_kegiatan_ujian_cbt_id' => $data['ruang']->id,
            'peran_pengawas' => 'utama',
            'pegawai_lama_id' => $pengawas->id,
            'pegawai_baru_id' => $pengawasPengganti->id,
            'alasan' => 'Pengawas utama sakit pada hari pelaksanaan ujian.',
            'diganti_oleh_pengguna_id' => $data['admin']->id,
        ]);
        $this->assertDatabaseHas('pengawas_ruang_ujian_terpusat', [
            'jadwal_ujian_cbt_id' => $data['jadwal']->id,
            'ruang_kegiatan_ujian_cbt_id' => $data['ruang']->id,
            'pengawas_utama_pegawai_id' => $pengawasPengganti->id,
        ]);
        $this->assertDatabaseHas('ruang_ujian_cbt', [
            'id' => $ruangOperasional->id,
            'pengawas_utama_pegawai_id' => $pengawasPengganti->id,
        ]);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $akunPengganti->id,
            'jenis' => 'penting',
            'judul' => 'Tugas sebagai pengawas pengganti',
            'tautan' => route('tugas-pengawas-ujian.index', absolute: false),
        ]);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $akunPengawas->id,
            'jenis' => 'informasi',
            'judul' => 'Tugas pengawas telah dialihkan',
        ]);

        $this->actingAs($akunPengawas)
            ->get(route('tugas-pengawas-ujian.show', $ruangOperasional))
            ->assertForbidden();
        $this->actingAs($akunPengganti)
            ->get(route('tugas-pengawas-ujian.show', $ruangOperasional))
            ->assertOk()
            ->assertSeeText('Guru Pengawas Pengganti');
        $pesertaDitahan->refresh()->update([
            'status' => 'terblokir',
            'ditahan_mode_aman_pada' => now(),
        ]);
        $this->actingAs($akunPengawas)
            ->post($ruteBukaModeAman, ['alasan_pembukaan' => 'Pengawas lama tidak lagi bertugas.'])
            ->assertForbidden();
        $this->actingAs($akunPengawas)
            ->get($ruteRiwayatModeAman)
            ->assertForbidden();
        $this->actingAs($akunPengganti)
            ->get($ruteRiwayatModeAman)
            ->assertOk()
            ->assertSeeText('Buka kembali ujian');
        $this->actingAs($akunPengganti)
            ->post($ruteBukaModeAman, ['alasan_pembukaan' => 'Sudah diperiksa oleh pengawas pengganti.'])
            ->assertRedirect($ruteRiwayatModeAman);
        $this->assertSame($akunPengganti->id, $pesertaDitahan->fresh()->dibuka_mode_aman_oleh_pengguna_id);
        $this->assertSame(2, $pesertaDitahan->aktivitasKeamananUjianCbt()->where('jenis', 'buka_mode_aman')->count());
        $this->assertDatabaseHas('aktivitas_keamanan_ujian_cbt', [
            'peserta_ujian_cbt_id' => $pesertaDitahan->id,
            'jenis' => 'buka_mode_aman',
            'oleh_pengguna_id' => $akunPengganti->id,
            'catatan' => 'Sudah diperiksa oleh pengawas pengganti.',
        ]);
        $this->actingAs($data['admin'])
            ->get(route('ujian-terpusat.pelaksanaan-nilai.index', $data['kegiatan']))
            ->assertOk()
            ->assertSeeText('Ganti pengawas mendadak')
            ->assertSeeText('Pengawas utama sakit pada hari pelaksanaan ujian.')
            ->assertSeeText('Guru Pengawas Ruang → Guru Pengawas Pengganti');
    }

    public function test_pengawas_seluruh_ruang_dapat_disimpan_sekaligus_secara_atomic(): void
    {
        $data = $this->buatFondasi();
        $kelompok = $data['kegiatan']->kelompokPesertaKegiatanUjianCbt()
            ->where('tingkat', 7)
            ->firstOrFail();
        $ruangKedua = RuangKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $data['kegiatan']->id,
            'kode' => 'R02',
            'nama' => 'Ruang 2',
            'lokasi' => 'Lantai 1',
            'kapasitas' => 20,
            'urutan' => 2,
            'aktif' => true,
        ]);
        $kelompok->ruangKegiatanUjianCbt()->attach($ruangKedua->id, ['urutan' => 2]);

        $pengawasKedua = Pegawai::create([
            'nama_lengkap' => 'Guru Pengawas Kedua',
            'nip' => '198811112020121004',
            'jenis_kelamin' => 'P',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akunPengawasKedua = Pengguna::create([
            'pegawai_id' => $pengawasKedua->id,
            'nama' => $pengawasKedua->nama_lengkap,
            'username' => $pengawasKedua->nip,
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $route = route('ujian-terpusat.pengawas.massal', [$data['kegiatan'], $data['jadwal']]);

        $this->actingAs($data['admin'])
            ->put($route, [
                'jadwal_form_id' => $data['jadwal']->id,
                'ruang' => [
                    $data['ruang']->id => ['pengawas_utama_pegawai_id' => $data['akun_guru']->pegawai_id],
                    $ruangKedua->id => ['pengawas_utama_pegawai_id' => $data['akun_guru']->pegawai_id],
                ],
            ])
            ->assertSessionHasErrors("ruang.{$ruangKedua->id}.pengawas_utama_pegawai_id");
        $this->assertDatabaseCount('pengawas_ruang_ujian_terpusat', 0);

        $respons = $this->put($route, [
            'jadwal_form_id' => $data['jadwal']->id,
            'ruang' => [
                $data['ruang']->id => [
                    'pengawas_utama_pegawai_id' => $data['akun_guru']->pegawai_id,
                    'catatan' => 'Koordinator ruang pertama.',
                ],
                $ruangKedua->id => [
                    'pengawas_utama_pegawai_id' => $pengawasKedua->id,
                    'catatan' => 'Koordinator ruang kedua.',
                ],
            ],
        ]);
        $respons->assertRedirect(
            route('ujian-terpusat.pelaksanaan-nilai.index', $data['kegiatan'])
            .'#pengawas-jadwal-'.$data['jadwal']->id,
        )->assertSessionHas('pengawas_jadwal_terbuka', $data['jadwal']->id);

        $this->assertDatabaseHas('pengawas_ruang_ujian_terpusat', [
            'jadwal_ujian_cbt_id' => $data['jadwal']->id,
            'ruang_kegiatan_ujian_cbt_id' => $data['ruang']->id,
            'pengawas_utama_pegawai_id' => $data['akun_guru']->pegawai_id,
            'catatan' => 'Koordinator ruang pertama.',
        ]);
        $this->assertDatabaseHas('pengawas_ruang_ujian_terpusat', [
            'jadwal_ujian_cbt_id' => $data['jadwal']->id,
            'ruang_kegiatan_ujian_cbt_id' => $ruangKedua->id,
            'pengawas_utama_pegawai_id' => $pengawasKedua->id,
            'catatan' => 'Koordinator ruang kedua.',
        ]);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $akunPengawasKedua->id,
            'judul' => 'Tugas pengawas ujian baru',
        ]);

        $this->get(route('ujian-terpusat.pelaksanaan-nilai.index', $data['kegiatan']))
            ->assertOk()
            ->assertSeeText('2 dari 2 siap')
            ->assertSeeText('Simpan semua penugasan')
            ->assertSee('data-auto-focus="true"', false);

        $this->put($route, [
            'jadwal_form_id' => $data['jadwal']->id,
            'only_room' => $data['ruang']->id,
            'ruang' => [
                $data['ruang']->id => ['pengawas_utama_pegawai_id' => $pengawasKedua->id],
            ],
        ])->assertSessionHasErrors("ruang.{$data['ruang']->id}.pengawas_utama_pegawai_id");
        $this->assertDatabaseHas('pengawas_ruang_ujian_terpusat', [
            'jadwal_ujian_cbt_id' => $data['jadwal']->id,
            'ruang_kegiatan_ujian_cbt_id' => $data['ruang']->id,
            'pengawas_utama_pegawai_id' => $data['akun_guru']->pegawai_id,
        ]);
    }

    public function test_perubahan_jadwal_memperbarui_paket_sebelum_ujian_dan_dikunci_setelah_mulai(): void
    {
        $data = $this->buatFondasi();
        $soal = SoalCbt::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'mata_pelajaran_id' => $data['mapel']->id,
            'tingkat' => 7,
            'kode' => 'SOAL-SINKRON-JADWAL',
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'mudah',
            'kategori' => 'lots',
            'pertanyaan' => 'Hasil dari 1 + 1 adalah ....',
            'opsi' => ['pilihan' => ['A' => '1', 'B' => '2']],
            'kunci_jawaban' => ['jawaban' => 'B'],
            'skor_maksimal' => 1,
            'status' => 'siap',
            'aktif' => true,
        ]);

        $this->actingAs($data['admin'])
            ->put(route('paket-soal-terpusat.update', $data['jadwal']), [
                'aksi' => 'terbitkan',
                'soal' => [$soal->id => ['dipilih' => '1']],
            ])
            ->assertRedirect();

        $paket = $data['jadwal']->fresh()->ujianCbt;
        $paketId = $paket->id;
        $token = $paket->token;

        $this->actingAs($data['admin'])
            ->put(route('ujian-terpusat.jadwal.update', [$data['kegiatan'], $data['jadwal']]), [
                'tanggal' => '2026-09-16',
                'mata_pelajaran_id' => $data['mapel']->id,
                'waktu_mulai' => '08:00',
                'waktu_selesai' => '10:00',
                'keterangan' => 'Jadwal diperbarui sebelum ujian',
            ])
            ->assertRedirect();

        $paket->refresh();
        $sesiOperasional = $paket->sesiUjianCbt()->firstOrFail();
        $this->assertSame($paketId, $paket->id);
        $this->assertSame($token, $paket->token);
        $this->assertSame('2026-09-16 08:00', $paket->tanggal_mulai?->format('Y-m-d H:i'));
        $this->assertSame('2026-09-16 10:00', $paket->tanggal_selesai?->format('Y-m-d H:i'));
        $this->assertSame(120, $paket->durasi_menit);
        $this->assertSame(1, $paket->soalUjianCbt()->count());
        $this->assertSame('2026-09-16 08:00', $sesiOperasional->waktu_mulai?->format('Y-m-d H:i'));
        $this->assertSame('2026-09-16 10:00', $sesiOperasional->waktu_selesai?->format('Y-m-d H:i'));

        $peserta = $paket->pesertaUjianCbt()->firstOrFail();
        $peserta->update([
            'status' => 'sedang_mengerjakan',
            'waktu_mulai' => '2026-09-16 08:05:00',
        ]);

        $this->actingAs($data['admin'])
            ->from(route('ujian-terpusat.pelaksanaan.index', [$data['kegiatan'], 'tahap' => 7]))
            ->put(route('ujian-terpusat.jadwal.update', [$data['kegiatan'], $data['jadwal']]), [
                'tanggal' => '2026-09-16',
                'mata_pelajaran_id' => $data['mapel']->id,
                'waktu_mulai' => '08:15',
                'waktu_selesai' => '10:15',
            ])
            ->assertSessionHasErrors('jadwal');

        $paket->refresh();
        $this->assertSame('2026-09-16 08:00', $paket->tanggal_mulai?->format('Y-m-d H:i'));
        $this->assertSame('2026-09-16 10:00', $paket->tanggal_selesai?->format('Y-m-d H:i'));
    }

    public function test_penilaian_pgk_mengikuti_kegiatan_dan_dikunci_setelah_mulai(): void
    {
        $data = $this->buatFondasi();
        $kegiatan = $data['kegiatan'];
        $payload = $kegiatan->only(['jenis_ujian_cbt_id', 'tahun_pelajaran_id', 'nama', 'semester', 'status']);
        $payload += ['tanggal_mulai' => '2026-09-15', 'tanggal_selesai' => '2026-09-20'];
        $this->assertSame('dikotomi', $kegiatan->fresh()->penilaian_pgk);
        $this->actingAs($data['akun_guru'])->put(route('ujian-terpusat.update', $kegiatan), [...$payload, 'penilaian_pgk' => 'parsial'])->assertForbidden();
        $this->actingAs($data['admin'])->put(route('ujian-terpusat.update', $kegiatan), [...$payload, 'penilaian_pgk' => 'invalid'])->assertSessionHasErrors('penilaian_pgk');
        $soal = SoalCbt::create([
            'mata_pelajaran_id' => $data['mapel']->id, 'tahun_pelajaran_id' => $data['tahun']->id,
            'tingkat' => 7, 'kode' => 'PGK-MODE', 'jenis_soal' => 'pilihan_ganda_kompleks',
            'tingkat_kesulitan' => 'sulit', 'kategori' => 'mots', 'pertanyaan' => 'Pilih yang benar',
            'opsi' => ['pilihan' => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat']],
            'kunci_jawaban' => ['jawaban' => ['A', 'C']], 'skor_maksimal' => 3, 'status' => 'siap', 'aktif' => true,
        ]);
        $this->put(route('paket-soal-terpusat.update', $data['jadwal']), [
            'aksi' => 'terbitkan', 'soal' => [$soal->id => ['dipilih' => '1', 'bobot' => 3]],
        ])->assertRedirect();
        $paket = $data['jadwal']->fresh()->ujianCbt;
        $peserta = $paket->pesertaUjianCbt()->firstOrFail();
        $relasi = $paket->soalUjianCbt()->firstOrFail();
        foreach (['dikotomi', 'parsial'] as $mode) {
            $this->put(route('ujian-terpusat.update', $kegiatan), [...$payload, 'penilaian_pgk' => $mode])->assertSessionHasNoErrors()->assertRedirect();
            foreach ([[['A', 'C'], 3], [['A'], 1.5], [['A', 'C', 'D'], 1.5], [['A', 'B'], 0], [['B', 'D'], 0], [[], 0], [['A', 'A'], 1.5]] as [$pilihan, $skorParsial]) {
                $jawaban = JawabanPesertaUjianCbt::updateOrCreate([
                    'peserta_ujian_cbt_id' => $peserta->id, 'soal_ujian_cbt_id' => $relasi->id,
                ], ['soal_cbt_id' => $soal->id, 'jawaban' => $pilihan]);
                app(KoreksiOtomatisCbtService::class)->koreksiPeserta($peserta);
                $expected = $mode === 'parsial' ? $skorParsial : ($pilihan === ['A', 'C'] ? 3 : 0);
                $this->assertEquals($expected, (float) $jawaban->fresh()->skor);
            }
        }
        $peserta->update(['waktu_mulai' => now(), 'status' => 'sedang_mengerjakan']);
        $this->assertNotNull($kegiatan->fresh()->penilaian_pgk_dikunci_pada);
        $peserta->update(['waktu_mulai' => null, 'status' => 'aktif']);
        $this->put(route('ujian-terpusat.update', $kegiatan), [...$payload, 'penilaian_pgk' => 'dikotomi'])->assertSessionHasErrors('penilaian_pgk');
        $this->assertSame('parsial', $kegiatan->fresh()->penilaian_pgk);
        $this->put(route('ujian-terpusat.update', $kegiatan), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('parsial', $kegiatan->fresh()->penilaian_pgk);
        $this->get(route('ujian-terpusat.edit', $kegiatan))->assertOk()->assertSee('Pengaturan dikunci');
        $this->actingAs($data['akun_guru'])->get(route('paket-soal-terpusat.show', $data['jadwal']))->assertOk()->assertSee('Parsial - benar dikurangi salah');
    }

    public function test_pengawas_tidak_dapat_ditugaskan_pada_jadwal_yang_bertumpang_tindih(): void
    {
        $data = $this->buatFondasi();
        $ruangKedua = RuangKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $data['kegiatan']->id,
            'kode' => 'R02',
            'nama' => 'Ruang 2',
            'lokasi' => 'Lantai 1',
            'kapasitas' => 20,
            'urutan' => 2,
            'aktif' => true,
        ]);
        $kelasDelapan = Kelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'nama' => 'VIII.A',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Tingkat Delapan',
            'nis' => '28001',
            'nisn' => '0130008001',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $kelasDelapan->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        app(BagiPesertaUjianTerpusat::class)->bagi(
            $data['kegiatan'],
            8,
            $data['sesi']->id,
            [$kelasDelapan->id],
            [$ruangKedua->id],
            $data['admin'],
        );
        $jadwalKedua = JadwalUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $data['kegiatan']->id,
            'sesi_kegiatan_ujian_cbt_id' => $data['sesi']->id,
            'mata_pelajaran_id' => $data['mapel']->id,
            'tanggal' => '2026-09-15',
            'waktu_mulai' => '08:30',
            'waktu_selesai' => '10:00',
            'label_sesi' => 'Sesi Pagi',
            'tingkat' => 8,
            'urutan' => 2,
            'status' => 'draft',
        ]);
        $jadwalKedua->kelas()->sync([$kelasDelapan->id]);
        $pengawas = Pegawai::create([
            'nama_lengkap' => 'Guru Pengawas Rangkap',
            'nip' => '198811112020129999',
            'jenis_kelamin' => 'P',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);

        $this->actingAs($data['admin'])
            ->put(route('ujian-terpusat.pengawas.update', [
                $data['kegiatan'],
                $data['jadwal'],
                $data['ruang'],
            ]), ['pengawas_utama_pegawai_id' => $pengawas->id])
            ->assertRedirect();

        $this->actingAs($data['admin'])
            ->from(route('ujian-terpusat.pelaksanaan-nilai.index', $data['kegiatan']))
            ->put(route('ujian-terpusat.pengawas.update', [
                $data['kegiatan'],
                $jadwalKedua,
                $ruangKedua,
            ]), ['pengawas_utama_pegawai_id' => $pengawas->id])
            ->assertSessionHasErrors('pengawas_utama_pegawai_id');

        $jadwalKedua->update([
            'waktu_mulai' => '09:00',
            'waktu_selesai' => '10:30',
        ]);
        $this->actingAs($data['admin'])
            ->put(route('ujian-terpusat.pengawas.update', [
                $data['kegiatan'],
                $jadwalKedua,
                $ruangKedua,
            ]), ['pengawas_utama_pegawai_id' => $pengawas->id])
            ->assertRedirect();

        $this->assertDatabaseHas('pengawas_ruang_ujian_terpusat', [
            'jadwal_ujian_cbt_id' => $jadwalKedua->id,
            'ruang_kegiatan_ujian_cbt_id' => $ruangKedua->id,
            'pengawas_utama_pegawai_id' => $pengawas->id,
        ]);
    }

    public function test_paket_simulasi_memiliki_dua_soal_per_jenis_dan_tidak_masuk_nilai(): void
    {
        Storage::fake('public');
        $data = $this->buatFondasi();
        $this->actingAs($data['admin']);
        $this->get(route('simulasi-cbt.index'))->assertOk()->assertSee('Simulasi CBT')->assertSee('Soal 12')->assertDontSee('Kenali Jenis Soal');
        $this->put(route('paket-soal-terpusat.update', $data['jadwal']), ['aksi' => 'terbitkan', 'gunakan_paket_simulasi' => true])->assertStatus(422);
        $data['kegiatan']->update(['jenis_ujian_cbt_id' => JenisUjianCbt::where('kode', 'SIMULASI_CBT')->value('id')]);
        foreach (range(1, 2) as $_) {
            $this->put(route('paket-soal-terpusat.update', $data['jadwal']), ['aksi' => 'terbitkan', 'gunakan_paket_simulasi' => true, 'acak_soal' => false, 'acak_jawaban' => false])->assertSessionHasNoErrors()->assertRedirect();
        }
        $paket = $data['jadwal']->fresh()->ujianCbt;
        $this->assertSame('Simulasi CBT', $paket->nama);
        $this->assertSame(20, $paket->durasi_menit);
        $this->assertSame(12, $paket->soalUjianCbt()->count());
        $this->assertDatabaseCount('soal_cbt', 12);
        $this->assertDatabaseCount('komponen_nilai', 0);
        $this->assertDatabaseCount('nilai_siswa', 0);
        Storage::disk('public')->assertExists('cbt/simulasi/lingkungan-sekolah.jpg');
        $jumlah = $paket->soalUjianCbt()->with('soalCbt')->get()->groupBy('soalCbt.jenis_soal')->map->count();
        $this->assertCount(6, $jumlah);
        $this->assertSame([2], $jumlah->unique()->values()->all());
        $peserta = $paket->pesertaUjianCbt()->firstOrFail();
        $data['jadwal']->update([
            'tanggal' => today()->toDateString(),
            'waktu_mulai' => now()->subMinutes(5)->format('H:i'),
            'waktu_selesai' => now()->addMinutes(30)->format('H:i'),
        ]);
        $paket->update([
            'tanggal_mulai' => now()->subMinutes(5),
            'tanggal_selesai' => now()->addMinutes(30),
            'status' => 'berlangsung',
        ]);
        $peserta->sesiUjianCbt()->update([
            'waktu_mulai' => now()->subMinutes(5),
            'waktu_selesai' => now()->addMinutes(30),
            'status' => 'aktif',
        ]);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $paket->token);
        $this->actingAs($data['admin'])
            ->get(route('paket-soal-terpusat.show', $data['jadwal']))
            ->assertOk()
            ->assertSeeText('Token otomatis')
            ->assertSeeText($paket->token);
        $this->get(route('ujian-terpusat.pelaksanaan-nilai.index', $data['kegiatan']))
            ->assertOk()
            ->assertSeeText('Token ujian')
            ->assertSeeText($paket->token);
        $this->actingAs($data['akun_siswa'])
            ->get(route('ujian-saya.index'))
            ->assertOk()
            ->assertSeeText('Token dari pengawas')
            ->assertSee('name="token"', false);
        $this->actingAs($data['akun_siswa'])
            ->post(route('ujian-saya.masuk', $peserta), ['token' => 'SALAH'])
            ->assertSessionHasErrors('token')
            ->assertSessionMissing('cbt_peserta_ujian_id');
        $this->post(route('ujian-saya.masuk', $peserta), ['token' => $paket->token])
            ->assertRedirect(route('cbt.ujian.show'))
            ->assertSessionHas('cbt_peserta_ujian_id', $peserta->id);
        $this->post(route('cbt.ujian.mulai'))
            ->assertRedirect(route('cbt.ujian.kerjakan'));
        $this->get(route('cbt.ujian.kerjakan'))
            ->assertOk()
            ->assertSeeText('Pilihan Ganda')
            ->assertSeeText('Pilihan Ganda Kompleks')
            ->assertSeeText('Benar-Salah')
            ->assertSeeText('Menjodohkan')
            ->assertSeeText('Isian Singkat')
            ->assertSeeText('Numerik')
            ->assertSeeText('Lingkungan sekolah yang perlu dijaga bersama.')
            ->assertSeeText('Menyiram tanaman')
            ->assertSeeText('Buku di rak kelas');
        foreach ($paket->soalUjianCbt()->with('soalCbt')->get() as $relasi) {
            $kunci = $relasi->soalCbt->kunci_jawaban['jawaban'];
            $jawaban = is_array($kunci) ? $kunci : [trim(explode('|', $kunci)[0])];
            JawabanPesertaUjianCbt::create(['peserta_ujian_cbt_id' => $peserta->id, 'soal_ujian_cbt_id' => $relasi->id, 'soal_cbt_id' => $relasi->soal_cbt_id, 'jawaban' => $jawaban]);
        }
        $hasil = app(KoreksiOtomatisCbtService::class)->koreksiPeserta($peserta);
        $this->assertEquals(12, $hasil['skor_total']);
        $this->assertEquals(12, $hasil['benar']);
        if (getenv('CBT_PHONE_FIXTURE')) {
            $soalUjian = $paket->soalUjianCbt()->with('soalCbt')->orderBy('nomor_urut')->get();
            $jawabanTersimpan = collect();
            $pilihanJawaban = $soalUjian->mapWithKeys(fn ($relasi) => [$relasi->id => app(PengacakPenyajianCbt::class)->pilihanJawaban($paket, $peserta, $relasi)]);
            $sisaDetik = 1200;
            $kelayakanSelesai = app(KelayakanPenyelesaianUjianCbtService::class)
                ->ringkasan($peserta, $soalUjian, $sisaDetik);
            session()->forget('berhasil');
            file_put_contents(storage_path('logs/cbt-phone-audit.html'), view('cbt.kerjakan', compact('peserta', 'soalUjian', 'jawabanTersimpan', 'pilihanJawaban', 'sisaDetik', 'kelayakanSelesai'))->render());
        }
        $this->actingAs($data['akun_siswa'])->get(route('ujian-saya.index'))->assertOk()->assertSee('Simulasi CBT');
        $this->get(route('simulasi-cbt.index'))->assertForbidden();
        $this->expectException(ValidationException::class);
        app(TerapkanNilaiCbtService::class)->terapkan($paket, $data['admin']->id);
    }

    public function test_panitia_dapat_menjadwalkan_susulan_dan_siswa_mengerjakan_paket_utama(): void
    {
        Carbon::setTestNow('2026-09-22 08:00:00');

        try {
            $data = $this->buatFondasi();
            $soal = SoalCbt::create([
                'tahun_pelajaran_id' => $data['tahun']->id,
                'mata_pelajaran_id' => $data['mapel']->id,
                'tingkat' => 7,
                'kode' => 'SOAL-SUSULAN-001',
                'jenis_soal' => 'pilihan_ganda',
                'tingkat_kesulitan' => 'mudah',
                'kategori' => 'lots',
                'pertanyaan' => 'Hasil dari 1 + 1 adalah ....',
                'opsi' => ['pilihan' => ['A' => '1', 'B' => '2']],
                'kunci_jawaban' => ['jawaban' => 'B'],
                'skor_maksimal' => 1,
                'status' => 'siap',
                'aktif' => true,
            ]);

            $this->actingAs($data['admin'])
                ->put(route('paket-soal-terpusat.update', $data['jadwal']), [
                    'aksi' => 'terbitkan',
                    'soal' => [$soal->id => ['dipilih' => '1', 'bobot' => 1]],
                ])
                ->assertRedirect();

            $jadwal = $data['jadwal']->fresh();
            $paket = $jadwal->ujianCbt;
            $peserta = $paket->pesertaUjianCbt()
                ->where('anggota_kelas_id', $data['anggota'][0]->id)
                ->firstOrFail();
            $peserta->update(['status_kehadiran_ujian' => 'sakit']);
            $paket->pesertaUjianCbt()
                ->whereKeyNot($peserta->id)
                ->update(['status_kehadiran_ujian' => 'alfa']);
            $paket->update(['status' => 'selesai']);

            $this->get(route('ujian-terpusat.pelaksanaan-nilai.index', $data['kegiatan']))
                ->assertOk()
                ->assertSeeText('Ketidakhadiran & ujian susulan')
                ->assertSeeText('Alya')
                ->assertSeeText('Sakit');

            $this->post(route('ujian-terpusat.susulan.store', [$data['kegiatan'], $jadwal]), [
                'peserta_ids' => [$peserta->id],
                'susulan_mulai' => '2026-09-22 08:30:00',
                'susulan_selesai' => '2026-09-22 09:30:00',
                'ruang_susulan_kegiatan_ujian_cbt_id' => $data['ruang']->id,
                'pengawas_susulan_pegawai_id' => $data['akun_guru']->pegawai_id,
                'catatan_susulan' => 'Surat keterangan sakit sudah diterima.',
            ])->assertSessionHasNoErrors()->assertRedirect();

            $peserta->refresh();
            $this->assertSame('dijadwalkan', $peserta->status_susulan);
            $this->assertNotNull($peserta->kelompok_susulan);
            $this->assertSame('sakit', $peserta->status_kehadiran_ujian);
            $this->assertSame('Ruang 1', $peserta->ruang_susulan);
            $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $peserta->token_susulan);
            $this->assertDatabaseHas('notifikasi_pengguna', [
                'pengguna_id' => $data['akun_siswa']->id,
                'judul' => 'Jadwal ujian susulan',
            ]);
            $this->assertDatabaseHas('notifikasi_pengguna', [
                'pengguna_id' => $data['akun_guru']->id,
                'judul' => 'Tugas pengawas ujian susulan',
                'tautan' => parse_url(route('tugas-pengawas-ujian.susulan.show', $peserta->kelompok_susulan), PHP_URL_PATH),
            ]);

            $this->actingAs($data['akun_guru'])
                ->get(route('tugas-pengawas-ujian.index'))
                ->assertOk()
                ->assertSeeText('Tugas ujian susulan')
                ->assertSeeText('Ruang 1')
                ->assertSeeText('Buka tugas susulan');
            $this->get(route('tugas-pengawas-ujian.susulan.show', $peserta->kelompok_susulan))
                ->assertOk()
                ->assertSeeText('Tugas Pengawas Susulan')
                ->assertSeeText('Token ujian susulan')
                ->assertSeeText($peserta->token_susulan)
                ->assertSeeText('Alya')
                ->assertSeeText('Ketidakhadiran awal')
                ->assertSeeText('Jawaban tersimpan');
            $this->actingAs($data['akun_siswa'])
                ->get(route('tugas-pengawas-ujian.susulan.show', $peserta->kelompok_susulan))
                ->assertForbidden();
            $this->actingAs($data['admin'])
                ->get(route('tugas-pengawas-ujian.susulan.show', $peserta->kelompok_susulan))
                ->assertOk();

            $kesiapanSebelum = app(FinalisasiHasilUjianTerpusatService::class)
                ->ringkasan($data['admin'], $paket->fresh());
            $this->assertFalse($kesiapanSebelum['siap_difinalisasi']);
            $this->assertSame(1, $kesiapanSebelum['kesiapan']['peserta_belum_selesai']);

            $this->actingAs($data['akun_siswa'])
                ->get(route('ujian-saya.index'))
                ->assertOk()
                ->assertSeeText('Ujian susulan')
                ->assertSeeText('Susulan terjadwal')
                ->assertSeeText('Ruang 1')
                ->assertSeeText('Nilai susulan akan masuk ke komponen nilai ujian yang sama.');

            Carbon::setTestNow('2026-09-22 08:45:00');
            $this->post(route('ujian-saya.masuk', $peserta), ['token' => $paket->token])
                ->assertSessionHasErrors('token');
            $this->post(route('ujian-saya.masuk', $peserta), ['token' => $peserta->token_susulan])
                ->assertRedirect(route('cbt.ujian.show'));
            $this->post(route('cbt.ujian.mulai'))
                ->assertRedirect(route('cbt.ujian.kerjakan'));

            $relasiSoal = $paket->soalUjianCbt()->firstOrFail();
            $this->post(route('cbt.ujian.simpan'), [
                'jawaban' => [$relasiSoal->id => ['B']],
                'aksi' => 'selesai',
            ])->assertRedirect(route('cbt.ujian.selesai'));

            $peserta->refresh();
            $this->assertSame('selesai', $peserta->status);
            $this->assertSame('selesai', $peserta->status_susulan);
            $this->assertSame('sakit', $peserta->status_kehadiran_ujian);
            $this->assertSame($paket->id, $peserta->ujian_cbt_id);
            $this->actingAs($data['akun_guru'])
                ->get(route('tugas-pengawas-ujian.susulan.show', $peserta->kelompok_susulan))
                ->assertOk()
                ->assertSeeText('Selesai')
                ->assertSeeText('1 / 1');
            $kesiapanSesudah = app(FinalisasiHasilUjianTerpusatService::class)
                ->ringkasan($data['admin'], $paket->fresh());
            $this->assertTrue($kesiapanSesudah['siap_difinalisasi']);
            $this->assertSame(2, $kesiapanSesudah['kesiapan']['peserta_tidak_hadir']);

            $hasilPenerapan = app(TerapkanNilaiCbtService::class)
                ->terapkan($paket->fresh(), $data['admin']->id);
            $this->assertSame(1, $hasilPenerapan['ringkasan']['diterapkan']);
            $this->assertEquals(100, (float) $peserta->fresh()->nilaiSiswa?->nilai);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_susulan_hanya_untuk_siswa_tidak_hadir_dan_dapat_dibatalkan_sebelum_dimulai(): void
    {
        Carbon::setTestNow('2026-09-22 08:00:00');

        try {
            $data = $this->buatFondasi();
            $soal = SoalCbt::create([
                'tahun_pelajaran_id' => $data['tahun']->id,
                'mata_pelajaran_id' => $data['mapel']->id,
                'tingkat' => 7,
                'kode' => 'SOAL-SUSULAN-002',
                'jenis_soal' => 'pilihan_ganda',
                'tingkat_kesulitan' => 'mudah',
                'kategori' => 'lots',
                'pertanyaan' => 'Bilangan genap adalah ....',
                'opsi' => ['pilihan' => ['A' => '1', 'B' => '2']],
                'kunci_jawaban' => ['jawaban' => 'B'],
                'skor_maksimal' => 1,
                'status' => 'siap',
                'aktif' => true,
            ]);
            $this->actingAs($data['admin'])->put(route('paket-soal-terpusat.update', $data['jadwal']), [
                'aksi' => 'terbitkan',
                'soal' => [$soal->id => ['dipilih' => '1', 'bobot' => 1]],
            ])->assertRedirect();

            $jadwal = $data['jadwal']->fresh();
            $peserta = $jadwal->ujianCbt->pesertaUjianCbt()->orderBy('id')->get();
            $peserta[0]->update(['status_kehadiran_ujian' => 'izin']);
            $peserta[1]->update(['status_kehadiran_ujian' => 'hadir']);
            $payload = [
                'susulan_mulai' => '2026-09-22 10:00:00',
                'susulan_selesai' => '2026-09-22 11:00:00',
                'ruang_susulan_kegiatan_ujian_cbt_id' => $data['ruang']->id,
                'pengawas_susulan_pegawai_id' => $data['akun_guru']->pegawai_id,
            ];

            $this->actingAs($data['akun_guru'])
                ->post(route('ujian-terpusat.susulan.store', [$data['kegiatan'], $jadwal]), $payload + [
                    'peserta_ids' => [$peserta[0]->id],
                ])->assertForbidden();

            $this->actingAs($data['admin'])
                ->post(route('ujian-terpusat.susulan.store', [$data['kegiatan'], $jadwal]), $payload + [
                    'peserta_ids' => [$peserta[1]->id],
                ])->assertSessionHasErrors('peserta_ids');
            $this->assertNull($peserta[1]->fresh()->status_susulan);

            $peserta[1]->update(['status_kehadiran_ujian' => 'sakit']);
            $this->post(route('ujian-terpusat.susulan.store', [$data['kegiatan'], $jadwal]), $payload + [
                'peserta_ids' => $peserta->pluck('id')->all(),
                'pengawas_susulan_pegawai_id' => $data['akun_guru']->pegawai_id,
            ])->assertSessionHasNoErrors();
            $kelompokSusulan = $peserta[0]->fresh()->kelompok_susulan;
            $this->patch(route('ujian-terpusat.susulan.batalkan', [
                $data['kegiatan'],
                $jadwal,
                $peserta[0],
            ]))->assertSessionHasNoErrors()->assertRedirect();

            $peserta[0]->refresh();
            $this->assertSame('dibatalkan', $peserta[0]->status_susulan);
            $this->assertNull($peserta[0]->token_susulan);
            $this->assertSame('izin', $peserta[0]->status_kehadiran_ujian);
            $peserta[1]->refresh();
            $this->assertSame('dijadwalkan', $peserta[1]->status_susulan);
            $this->assertSame($kelompokSusulan, $peserta[1]->kelompok_susulan);
            $this->assertDatabaseHas('notifikasi_pengguna', [
                'pengguna_id' => $data['akun_guru']->id,
                'judul' => 'Perubahan peserta ujian susulan',
                'tautan' => parse_url(route('tugas-pengawas-ujian.susulan.show', $kelompokSusulan), PHP_URL_PATH),
            ]);

            $this->patch(route('ujian-terpusat.susulan.batalkan', [
                $data['kegiatan'],
                $jadwal,
                $peserta[1],
            ]))->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame('dibatalkan', $peserta[1]->fresh()->status_susulan);
            $this->assertDatabaseHas('notifikasi_pengguna', [
                'pengguna_id' => $data['akun_guru']->id,
                'judul' => 'Jadwal pengawasan susulan dibatalkan',
                'tautan' => parse_url(route('tugas-pengawas-ujian.index'), PHP_URL_PATH),
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_jadwal_susulan_menolak_bentrok_ruang_dan_pengawas_dengan_ujian_utama(): void
    {
        Carbon::setTestNow('2026-09-22 08:00:00');

        try {
            $data = $this->buatFondasi();
            $jadwal = $this->terbitkanPaketUntukSusulan($data, 'SOAL-SUSULAN-BENTROK-UTAMA');
            $jadwal->update([
                'tanggal' => '2026-09-22',
                'waktu_mulai' => '10:00',
                'waktu_selesai' => '11:00',
            ]);
            $peserta = $jadwal->ujianCbt->pesertaUjianCbt()->firstOrFail();
            $peserta->update(['status_kehadiran_ujian' => 'sakit']);

            $ruangKedua = RuangKegiatanUjianCbt::create([
                'kegiatan_ujian_cbt_id' => $data['kegiatan']->id,
                'kode' => 'R02',
                'nama' => 'Ruang 2',
                'lokasi' => 'Lantai 1',
                'kapasitas' => 20,
                'urutan' => 2,
                'aktif' => true,
            ]);
            $pengawasKedua = Pegawai::create([
                'nama_lengkap' => 'Pengawas Kedua',
                'nip' => '198800012020121002',
                'jenis_kelamin' => 'P',
                'jenis_pegawai' => 'Guru',
                'aktif' => true,
            ]);
            PengawasRuangUjianTerpusat::create([
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_kegiatan_ujian_cbt_id' => $data['ruang']->id,
                'pengawas_utama_pegawai_id' => $data['akun_guru']->pegawai_id,
                'ditugaskan_oleh_pengguna_id' => $data['admin']->id,
            ]);

            $payload = [
                'peserta_ids' => [$peserta->id],
                'susulan_mulai' => '2026-09-22 10:15:00',
                'susulan_selesai' => '2026-09-22 11:15:00',
            ];

            $this->actingAs($data['admin'])
                ->post(route('ujian-terpusat.susulan.store', [$data['kegiatan'], $jadwal]), $payload + [
                    'ruang_susulan_kegiatan_ujian_cbt_id' => $data['ruang']->id,
                    'pengawas_susulan_pegawai_id' => $pengawasKedua->id,
                ])
                ->assertSessionHasErrors('ruang_susulan_kegiatan_ujian_cbt_id');

            $this->post(route('ujian-terpusat.susulan.store', [$data['kegiatan'], $jadwal]), $payload + [
                'ruang_susulan_kegiatan_ujian_cbt_id' => $ruangKedua->id,
                'pengawas_susulan_pegawai_id' => $data['akun_guru']->pegawai_id,
            ])->assertSessionHasErrors('pengawas_susulan_pegawai_id');

            $this->assertNull($peserta->fresh()->status_susulan);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_jadwal_susulan_menolak_bentrok_susulan_lain_dan_mengizinkan_waktu_bersambung(): void
    {
        Carbon::setTestNow('2026-09-22 08:00:00');

        try {
            $data = $this->buatFondasi();
            $jadwal = $this->terbitkanPaketUntukSusulan($data, 'SOAL-SUSULAN-BENTROK-SUSULAN');
            $peserta = $jadwal->ujianCbt->pesertaUjianCbt()->orderBy('id')->get();
            $peserta->each->update(['status_kehadiran_ujian' => 'sakit']);

            $ruangKedua = RuangKegiatanUjianCbt::create([
                'kegiatan_ujian_cbt_id' => $data['kegiatan']->id,
                'kode' => 'R02',
                'nama' => 'Ruang 2',
                'lokasi' => 'Lantai 1',
                'kapasitas' => 20,
                'urutan' => 2,
                'aktif' => true,
            ]);
            $pengawasKedua = Pegawai::create([
                'nama_lengkap' => 'Pengawas Kedua',
                'nip' => '198800012020121002',
                'jenis_kelamin' => 'P',
                'jenis_pegawai' => 'Guru',
                'aktif' => true,
            ]);

            $this->actingAs($data['admin'])
                ->post(route('ujian-terpusat.susulan.store', [$data['kegiatan'], $jadwal]), [
                    'peserta_ids' => [$peserta[0]->id],
                    'susulan_mulai' => '2026-09-22 10:00:00',
                    'susulan_selesai' => '2026-09-22 11:00:00',
                    'ruang_susulan_kegiatan_ujian_cbt_id' => $data['ruang']->id,
                    'pengawas_susulan_pegawai_id' => $data['akun_guru']->pegawai_id,
                ])
                ->assertSessionHasNoErrors();

            $data['kegiatan']->update(['tanggal_selesai' => '2026-09-30']);
            try {
                app(KelolaJadwalUjianTerpusat::class)->ubah($data['kegiatan']->fresh(), $jadwal, [
                    'tanggal' => '2026-09-22',
                    'mata_pelajaran_id' => $data['mapel']->id,
                    'waktu_mulai' => '10:30',
                    'waktu_selesai' => '11:30',
                    'keterangan' => null,
                ], $data['admin']);
                $this->fail('Jadwal utama yang berbenturan dengan ruang susulan seharusnya ditolak.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('waktu_mulai', $exception->errors());
            }

            $jadwal->update([
                'tanggal' => '2026-09-22',
                'waktu_mulai' => '10:30',
                'waktu_selesai' => '10:45',
            ]);
            $this->put(route('ujian-terpusat.pengawas.update', [$data['kegiatan'], $jadwal, $data['ruang']]), [
                'pengawas_utama_pegawai_id' => $data['akun_guru']->pegawai_id,
                'pengawas_pendamping_pegawai_id' => null,
            ])->assertSessionHasErrors('pengawas_utama_pegawai_id');
            $jadwal->update([
                'tanggal' => '2026-09-15',
                'waktu_mulai' => '07:30',
                'waktu_selesai' => '09:00',
            ]);

            $payloadBentrok = [
                'peserta_ids' => [$peserta[1]->id],
                'susulan_mulai' => '2026-09-22 10:30:00',
                'susulan_selesai' => '2026-09-22 11:30:00',
            ];
            $this->post(route('ujian-terpusat.susulan.store', [$data['kegiatan'], $jadwal]), $payloadBentrok + [
                'ruang_susulan_kegiatan_ujian_cbt_id' => $data['ruang']->id,
                'pengawas_susulan_pegawai_id' => $pengawasKedua->id,
            ])->assertSessionHasErrors('ruang_susulan_kegiatan_ujian_cbt_id');

            $this->post(route('ujian-terpusat.susulan.store', [$data['kegiatan'], $jadwal]), $payloadBentrok + [
                'ruang_susulan_kegiatan_ujian_cbt_id' => $ruangKedua->id,
                'pengawas_susulan_pegawai_id' => $data['akun_guru']->pegawai_id,
            ])->assertSessionHasErrors('pengawas_susulan_pegawai_id');

            $this->post(route('ujian-terpusat.susulan.store', [$data['kegiatan'], $jadwal]), [
                'peserta_ids' => [$peserta[1]->id],
                'susulan_mulai' => '2026-09-22 11:00:00',
                'susulan_selesai' => '2026-09-22 12:00:00',
                'ruang_susulan_kegiatan_ujian_cbt_id' => $data['ruang']->id,
                'pengawas_susulan_pegawai_id' => $data['akun_guru']->pegawai_id,
            ])->assertSessionHasNoErrors();

            $this->assertSame('dijadwalkan', $peserta[1]->fresh()->status_susulan);
            $this->assertSame($data['ruang']->id, $peserta[1]->fresh()->ruang_susulan_kegiatan_ujian_cbt_id);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function terbitkanPaketUntukSusulan(array $data, string $kodeSoal): JadwalUjianCbt
    {
        $soal = SoalCbt::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'mata_pelajaran_id' => $data['mapel']->id,
            'tingkat' => 7,
            'kode' => $kodeSoal,
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'mudah',
            'kategori' => 'lots',
            'pertanyaan' => 'Hasil dari 2 + 2 adalah ....',
            'opsi' => ['pilihan' => ['A' => '3', 'B' => '4']],
            'kunci_jawaban' => ['jawaban' => 'B'],
            'skor_maksimal' => 1,
            'status' => 'siap',
            'aktif' => true,
        ]);

        $this->actingAs($data['admin'])
            ->put(route('paket-soal-terpusat.update', $data['jadwal']), [
                'aksi' => 'terbitkan',
                'soal' => [$soal->id => ['dipilih' => '1', 'bobot' => 1]],
            ])
            ->assertRedirect();

        return $data['jadwal']->fresh();
    }

    private function buatFondasi(): array
    {
        $admin = Pengguna::create([
            'nama' => 'Administrator Tahap Tujuh',
            'username' => 'admin-tahap-tujuh',
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
        $jenis = JenisUjianCbt::query()->where('kode', 'STS')->firstOrFail();
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kode' => 'UT-TAHAP-7',
            'nama' => 'STS Tahap Tujuh',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-09-15',
            'tanggal_selesai' => '2026-09-20',
            'status' => 'aktif',
            'dibuat_oleh_pengguna_id' => $admin->id,
        ]);
        $sesi = SesiKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'kode' => 'S01',
            'nama' => 'Sesi Pagi',
            'waktu_mulai' => '07:30',
            'waktu_selesai' => '09:00',
            'urutan' => 1,
            'aktif' => true,
        ]);
        $ruang = RuangKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'kode' => 'R01',
            'nama' => 'Ruang 1',
            'lokasi' => 'Lantai 1',
            'kapasitas' => 20,
            'urutan' => 1,
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $anggota = collect(['Alya', 'Bima'])->map(function (string $nama, int $index) use ($tahun, $kelas) {
            $siswa = Siswa::create([
                'nama_lengkap' => $nama,
                'nis' => '2600'.$index,
                'nisn' => '013000000'.$index,
                'jenis_kelamin' => $index ? 'L' : 'P',
                'aktif' => true,
            ]);

            return AnggotaKelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $index + 1,
                'status_keanggotaan' => 'aktif',
            ]);
        });
        $akunSiswa = Pengguna::create([
            'siswa_id' => $anggota[0]->siswa_id,
            'nama' => 'Alya',
            'username' => '0130000000',
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $mapel = MataPelajaran::create([
            'kode' => 'MTK7',
            'nama' => 'Matematika',
            'tingkat' => 7,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $guru = Pegawai::create([
            'nama_lengkap' => 'Guru Matematika',
            'nip' => '198800012020121001',
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akunGuru = Pengguna::create([
            'pegawai_id' => $guru->id,
            'nama' => $guru->nama_lengkap,
            'username' => $guru->nip,
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akunGuru->daftarPeran()->sync([Peran::query()->where('kode', 'guru_mapel')->value('id')]);
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);

        app(BagiPesertaUjianTerpusat::class)->bagi(
            $kegiatan,
            7,
            $sesi->id,
            [$kelas->id],
            [$ruang->id],
            $admin,
        );

        $jadwal = JadwalUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
            'mata_pelajaran_id' => $mapel->id,
            'tanggal' => '2026-09-15',
            'waktu_mulai' => '07:30',
            'waktu_selesai' => '09:00',
            'label_sesi' => 'Sesi Pagi',
            'tingkat' => 7,
            'urutan' => 1,
            'status' => 'draft',
        ]);
        $jadwal->kelas()->sync([$kelas->id]);

        return compact('admin', 'tahun', 'kegiatan', 'sesi', 'ruang', 'kelas', 'anggota', 'akunSiswa', 'akunGuru', 'mapel', 'jadwal') + [
            'akun_siswa' => $akunSiswa,
            'akun_guru' => $akunGuru,
        ];
    }
}
