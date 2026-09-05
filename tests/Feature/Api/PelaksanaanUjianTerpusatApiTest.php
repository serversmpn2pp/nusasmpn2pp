<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\JadwalUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PenempatanPesertaUjianCbt;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\RuangUjianCbt;
use App\Models\SesiKegiatanUjianCbt;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PelaksanaanUjianTerpusatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pusat_pelaksanaan_memerlukan_autentikasi_mobile(): void
    {
        $this->getJson(route('api.v1.pelaksanaan-ujian-terpusat.index'))->assertUnauthorized();
    }

    public function test_panitia_dapat_memantau_token_ruang_peserta_dan_mengganti_pengawas(): void
    {
        $data = $this->fondasi();
        $token = $data['admin']->createToken('Panitia Android', ['mobile'])->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.pelaksanaan-ujian-terpusat.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.items.0.nama', 'Ujian Sekolah Native')
            ->assertJsonPath('data.items.0.paket_siap', 1);

        $response = $this->withToken($token)
            ->getJson(route('api.v1.pelaksanaan-ujian-terpusat.show', [
                $data['kegiatan'],
                'status_peserta' => 'terblokir',
            ]))
            ->assertOk()
            ->assertJsonPath('data.jadwal.0.paket.token', '654321')
            ->assertJsonPath('data.jadwal.0.ruang.0.pengawas_utama.nama', 'Pengawas Lama')
            ->assertJsonPath('data.jadwal.0.ruang.0.ringkasan.terblokir', 1)
            ->assertJsonPath('data.ringkasan.terblokir', 1)
            ->assertJsonPath('data.peserta.items.0.nama', 'Siswa Ujian Native')
            ->assertJsonPath('data.peserta.items.0.dapat_dibuka_mode_aman', true)
            ->assertJsonPath('data.kemampuan.mengatur_pengawas', true);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($token)
            ->patchJson(route('api.v1.pelaksanaan-ujian-terpusat.pengawas.update', [
                $data['kegiatan'], $data['jadwal'], $data['ruang_sumber'],
            ]), [
                'peran' => 'utama',
                'pegawai_id' => $data['pengawas_baru']->id,
                'alasan' => 'Pengawas lama mendadak sakit.',
            ])
            ->assertOk()
            ->assertJsonPath('data.jenis', 'penggantian')
            ->assertJsonPath('data.pengawas_lama', 'Pengawas Lama')
            ->assertJsonPath('data.pengawas_baru', 'Pengawas Baru');

        $this->assertDatabaseHas('riwayat_pergantian_pengawas_ujian', [
            'jadwal_ujian_cbt_id' => $data['jadwal']->id,
            'ruang_kegiatan_ujian_cbt_id' => $data['ruang_sumber']->id,
            'pegawai_lama_id' => $data['pengawas_lama']->id,
            'pegawai_baru_id' => $data['pengawas_baru']->id,
        ]);
        $this->assertDatabaseHas('ruang_ujian_cbt', [
            'id' => $data['ruang_operasional']->id,
            'pengawas_utama_pegawai_id' => $data['pengawas_baru']->id,
            'status' => 'berlangsung',
        ]);

        $this->withToken($token)
            ->postJson(route('api.v1.keamanan-ujian.buka', $data['peserta']))
            ->assertOk()
            ->assertJsonPath('data.status', 'sedang_mengerjakan');
    }

    public function test_penggantian_pengawas_wajib_memiliki_alasan_yang_jelas(): void
    {
        $data = $this->fondasi();
        $token = $data['admin']->createToken('Panitia Android', ['mobile'])->plainTextToken;

        $this->withToken($token)
            ->patchJson(route('api.v1.pelaksanaan-ujian-terpusat.pengawas.update', [
                $data['kegiatan'], $data['jadwal'], $data['ruang_sumber'],
            ]), [
                'peran' => 'utama',
                'pegawai_id' => $data['pengawas_baru']->id,
                'alasan' => 'izin',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('alasan');
    }

    public function test_nilai_dan_hasil_ujian_terpusat_menampilkan_jadwal_dan_peserta(): void
    {
        $data = $this->fondasi();
        $token = $data['admin']->createToken('Hasil Ujian Android', ['mobile'])->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.hasil-ujian-terpusat.index'))
            ->assertOk()
            ->assertJsonPath('data.items.0.nama', 'Ujian Sekolah Native')
            ->assertJsonPath('data.items.0.jumlah_jadwal', 1)
            ->assertJsonPath('data.items.0.jumlah_peserta', 1);

        $response = $this->withToken($token)
            ->getJson(route('api.v1.hasil-ujian-terpusat.show', [
                $data['kegiatan'], 'jadwal_id' => $data['jadwal']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.jadwal_terpilih_id', $data['jadwal']->id)
            ->assertJsonPath('data.dapat_menerapkan_nilai', true)
            ->assertJsonPath('data.hasil.asesmen.mata_pelajaran', 'Matematika')
            ->assertJsonPath('data.hasil.ringkasan.total_peserta', 1)
            ->assertJsonPath('data.hasil.items.0.siswa.nama', 'Siswa Ujian Native')
            ->assertJsonPath('data.hasil.items.0.status', 'belum_selesai');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($token)
            ->postJson(route('api.v1.hasil-ujian-terpusat.terapkan-nilai', [
                $data['kegiatan'], $data['jadwal'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nilai');
    }

    private function fondasi(): array
    {
        $admin = Pengguna::create([
            'nama' => 'Administrator Ujian Native',
            'username' => 'admin-ujian-native',
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
        $jenis = JenisUjianCbt::query()->firstOrFail();
        $jenis->update(['memerlukan_token' => true]);
        $mapel = MataPelajaran::create([
            'kode' => 'MTK-UT-NATIVE',
            'nama' => 'Matematika',
            'tingkat' => 9,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kode' => 'UT-NATIVE',
            'nama' => 'Ujian Sekolah Native',
            'semester' => 'ganjil',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->addDay()->toDateString(),
            'status' => 'aktif',
            'dibuat_oleh_pengguna_id' => $admin->id,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'IX.A',
            'tingkat' => 9,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $jadwal = JadwalUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'mata_pelajaran_id' => $mapel->id,
            'tanggal' => now()->toDateString(),
            'waktu_mulai' => '07:30',
            'waktu_selesai' => '09:00',
            'label_sesi' => 'Sesi Pagi',
            'tingkat' => 9,
            'urutan' => 1,
            'status' => 'siap',
        ]);
        $jadwal->kelas()->sync([$kelas->id]);
        $sesi = SesiKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'kode' => 'S-01',
            'nama' => 'Sesi Pagi',
            'waktu_mulai' => '07:30',
            'waktu_selesai' => '09:00',
            'urutan' => 1,
            'aktif' => true,
        ]);
        $jadwal->update(['sesi_kegiatan_ujian_cbt_id' => $sesi->id]);
        $ruangSumber = RuangKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'kode' => 'R-01',
            'nama' => 'Labor Komputer 1',
            'lokasi' => 'Lantai 1',
            'kapasitas' => 30,
            'urutan' => 1,
            'aktif' => true,
        ]);
        $kelompok = KelompokPesertaKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
            'tingkat' => 9,
            'jumlah_peserta' => 1,
            'total_kapasitas' => 30,
            'dibuat_oleh_pengguna_id' => $admin->id,
        ]);
        $kelompok->kelas()->sync([$kelas->id]);
        $kelompok->ruangKegiatanUjianCbt()->attach($ruangSumber->id, ['urutan' => 1]);
        $ujian = UjianCbt::create([
            'alur' => 'terpusat',
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mapel->id,
            'kode' => 'PAKET-UT-NATIVE',
            'nama' => 'Paket Ujian Matematika',
            'semester' => 'ganjil',
            'tingkat' => 9,
            'tanggal_mulai' => now()->startOfDay()->addHours(7),
            'tanggal_selesai' => now()->startOfDay()->addHours(9),
            'durasi_menit' => 90,
            'jumlah_soal' => 20,
            'token' => '654321',
            'deteksi_pindah_tab' => true,
            'batas_pindah_aplikasi' => 3,
            'tindakan_pindah_aplikasi' => 'tahan',
            'status' => 'berlangsung',
            'dibuat_oleh_pengguna_id' => $admin->id,
        ]);
        $jadwal->update(['ujian_cbt_id' => $ujian->id]);
        $kelasUjian = KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $kelas->id]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Ujian Native',
            'nis' => '2609001',
            'nisn' => '0099009001',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        PenempatanPesertaUjianCbt::create([
            'kelompok_peserta_kegiatan_ujian_cbt_id' => $kelompok->id,
            'anggota_kelas_id' => $anggota->id,
            'ruang_kegiatan_ujian_cbt_id' => $ruangSumber->id,
            'nomor_peserta' => '001',
            'nomor_meja' => 1,
            'kode_meja' => 'R01-01',
        ]);
        $pengawasLama = Pegawai::create([
            'nama_lengkap' => 'Pengawas Lama',
            'nip' => '198801012026091001',
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $pengawasBaru = Pegawai::create([
            'nama_lengkap' => 'Pengawas Baru',
            'nip' => '198801012026091002',
            'jenis_kelamin' => 'P',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        PengawasRuangUjianTerpusat::create([
            'jadwal_ujian_cbt_id' => $jadwal->id,
            'ruang_kegiatan_ujian_cbt_id' => $ruangSumber->id,
            'pengawas_utama_pegawai_id' => $pengawasLama->id,
            'ditugaskan_oleh_pengguna_id' => $admin->id,
        ]);
        $ruangOperasional = RuangUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'jadwal_ujian_cbt_id' => $jadwal->id,
            'ruang_kegiatan_ujian_cbt_id' => $ruangSumber->id,
            'kode' => 'R-01',
            'nama' => 'Labor Komputer 1',
            'lokasi' => 'Lantai 1',
            'kapasitas' => 30,
            'pengawas_utama_pegawai_id' => $pengawasLama->id,
            'status_bukti' => 'belum_diunggah',
            'status' => 'berlangsung',
        ]);
        $peserta = PesertaUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_ujian_cbt_id' => $kelasUjian->id,
            'ruang_ujian_cbt_id' => $ruangOperasional->id,
            'anggota_kelas_id' => $anggota->id,
            'nomor_peserta' => 'UT-NATIVE-001',
            'nomor_meja' => 1,
            'status' => 'terblokir',
            'status_kehadiran_ujian' => 'hadir',
            'jumlah_pindah_aplikasi' => 3,
            'ditahan_mode_aman_pada' => now(),
        ]);

        return compact(
            'admin', 'kegiatan', 'jadwal', 'ujian', 'ruangSumber', 'ruangOperasional',
            'peserta', 'pengawasLama', 'pengawasBaru',
        ) + [
            'ruang_sumber' => $ruangSumber,
            'ruang_operasional' => $ruangOperasional,
            'pengawas_lama' => $pengawasLama,
            'pengawas_baru' => $pengawasBaru,
        ];
    }
}
