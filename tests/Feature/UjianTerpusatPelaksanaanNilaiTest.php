<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\MataPelajaran;
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
            'status' => 'aktif',
        ]);

        $this->actingAs($data['akun_siswa'])
            ->get(route('ujian-saya.index'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('Ruang 1')
            ->assertSee('Nomor meja');

        $this->actingAs($data['admin'])
            ->get(route('ujian-terpusat.pelaksanaan-nilai.index', $data['kegiatan']))
            ->assertOk()
            ->assertSee('Pelaksanaan ujian')
            ->assertSee('execution-flow-number">1</span><div><strong>Siapkan ruang', false)
            ->assertSee('Pantau ujian')
            ->assertSeeText('Nilai & hasil')
            ->assertSee($paket->token);

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
