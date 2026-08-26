<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\BuktiRuangUjianCbt;
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
use App\Models\RuangKegiatanUjianCbt;
use App\Models\SesiKegiatanUjianCbt;
use App\Models\Siswa;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use App\Services\Cbt\BagiPesertaUjianTerpusat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $this->actingAs($akunPengawas)
            ->get(route('tugas-pengawas-ujian.index'))
            ->assertOk()
            ->assertSeeText('Tugas Pengawas Saya')
            ->assertSeeText('Ruang 1');
        $this->actingAs($akunPengawas)
            ->get(route('tugas-pengawas-ujian.show', $ruangOperasional))
            ->assertOk()
            ->assertSeeText('Ambil foto atau pilih berkas')
            ->assertSeeText('Kirim ke panitia');
        $this->actingAs($data['akun_guru'])
            ->get(route('tugas-pengawas-ujian.show', $ruangOperasional))
            ->assertForbidden();

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
        $this->actingAs($data['admin'])
            ->get(route('ujian-terpusat.pelaksanaan-nilai.index', $data['kegiatan']))
            ->assertOk()
            ->assertSeeText('Ganti pengawas mendadak')
            ->assertSeeText('Pengawas utama sakit pada hari pelaksanaan ujian.')
            ->assertSeeText('Guru Pengawas Ruang → Guru Pengawas Pengganti');
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
