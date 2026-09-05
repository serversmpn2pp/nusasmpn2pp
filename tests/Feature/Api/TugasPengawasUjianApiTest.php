<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\JadwalUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\RuangUjianCbt;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TugasPengawasUjianApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tugas_pengawas_memerlukan_token_mobile(): void
    {
        $this->getJson(route('api.v1.tugas-pengawas-ujian.index'))->assertUnauthorized();
    }

    public function test_pengawas_hanya_mengakses_ruang_tugasnya_dan_dapat_menjalankan_operasional(): void
    {
        Storage::fake('local');
        $data = $this->fondasi();
        $token = $this->token($data['akun_pengawas']);

        $this->withToken($token)
            ->getJson(route('api.v1.tugas-pengawas-ujian.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.jumlah', 1)
            ->assertJsonPath('data.items.0.ruang_id', $data['ruang_operasional']->id)
            ->assertJsonPath('data.items.0.dapat_dibuka', true)
            ->assertJsonPath('data.items.0.peran', 'Pengawas utama');

        $detail = $this->withToken($token)
            ->getJson(route('api.v1.tugas-pengawas-ujian.show', $data['ruang_operasional']))
            ->assertOk()
            ->assertJsonPath('data.ruang.nama', 'Labor Komputer 1')
            ->assertJsonPath('data.ruang.peran_saya', 'Pengawas utama')
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.peserta.0.nama', 'Siswa Pengawas Mobile')
            ->assertJsonPath('data.peserta.0.perangkat_terikat', true)
            ->assertJsonPath('data.kemampuan.mengelola_ruang', true);
        $this->assertStringContainsString('no-store', (string) $detail->headers->get('Cache-Control'));

        $this->withToken($token)
            ->patchJson(route('api.v1.tugas-pengawas-ujian.status', $data['ruang_operasional']), [
                'aksi' => 'mulai',
            ])
            ->assertOk()
            ->assertJsonPath('data.ruang.status', 'berlangsung');

        $this->withToken($token)
            ->patchJson(route('api.v1.tugas-pengawas-ujian.kehadiran', [
                $data['ruang_operasional'],
                $data['peserta'],
            ]), [
                'status' => 'terlambat',
                'catatan' => 'Datang setelah bel masuk.',
            ])
            ->assertOk()
            ->assertJsonPath('data.peserta.0.kehadiran', 'terlambat')
            ->assertJsonPath('data.peserta.0.catatan_kehadiran', 'Datang setelah bel masuk.');

        $this->withToken($token)
            ->postJson(route('api.v1.tugas-pengawas-ujian.reset-perangkat', [
                $data['ruang_operasional'],
                $data['peserta'],
            ]))
            ->assertOk()
            ->assertJsonPath('data.peserta.0.perangkat_terikat', false);

        $this->withToken($token)
            ->patchJson(route('api.v1.tugas-pengawas-ujian.catatan', $data['ruang_operasional']), [
                'berita_acara' => 'Ujian berjalan tertib.',
                'hambatan' => 'Satu perangkat perlu diganti.',
                'tindak_lanjut' => 'Perangkat pengganti sudah disiapkan.',
                'catatan' => 'Pengawas hadir lengkap.',
            ])
            ->assertOk()
            ->assertJsonPath('data.ruang.berita_acara', 'Ujian berjalan tertib.');

        foreach (['daftar_hadir', 'berita_acara'] as $jenis) {
            $this->withToken($token)
                ->post(route('api.v1.tugas-pengawas-ujian.bukti.store', $data['ruang_operasional']), [
                    'jenis' => $jenis,
                    'berkas' => UploadedFile::fake()->create($jenis.'.jpg', 350, 'image/jpeg'),
                ], ['Accept' => 'application/json'])
                ->assertOk();
        }

        $this->withToken($token)
            ->patchJson(route('api.v1.tugas-pengawas-ujian.bukti.kirim', $data['ruang_operasional']))
            ->assertOk()
            ->assertJsonPath('data.ruang.status_bukti', 'menunggu_pemeriksaan')
            ->assertJsonPath('data.kemampuan.mengubah_bukti', false);

        $this->withToken($token)
            ->patchJson(route('api.v1.tugas-pengawas-ujian.status', $data['ruang_operasional']), [
                'aksi' => 'selesai',
            ])
            ->assertOk()
            ->assertJsonPath('data.ruang.status', 'selesai')
            ->assertJsonPath('data.kemampuan.mengubah_kehadiran', false);

        $this->assertDatabaseHas('peserta_ujian_cbt', [
            'id' => $data['peserta']->id,
            'status_kehadiran_ujian' => 'terlambat',
            'perangkat_terakhir' => null,
        ]);
        $this->assertDatabaseHas('ruang_ujian_cbt', [
            'id' => $data['ruang_operasional']->id,
            'status' => 'selesai',
            'status_bukti' => 'menunggu_pemeriksaan',
        ]);
    }

    public function test_pegawai_yang_tidak_ditugaskan_ditolak_dari_detail_ruang(): void
    {
        $data = $this->fondasi();

        $this->withToken($this->token($data['akun_lain']))
            ->getJson(route('api.v1.tugas-pengawas-ujian.show', $data['ruang_operasional']))
            ->assertForbidden();
    }

    public function test_ruang_tidak_dapat_diselesaikan_saat_peserta_masih_mengerjakan(): void
    {
        $data = $this->fondasi();
        $data['peserta']->update(['status' => 'sedang_mengerjakan']);

        $this->withToken($this->token($data['akun_pengawas']))
            ->patchJson(route('api.v1.tugas-pengawas-ujian.status', $data['ruang_operasional']), [
                'aksi' => 'selesai',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('aksi');
    }

    private function fondasi(): array
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $jenis = JenisUjianCbt::query()->firstOrFail();
        $mapel = MataPelajaran::create([
            'kode' => 'MTK-PENGAWAS',
            'nama' => 'Matematika',
            'tingkat' => 9,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kode' => 'UT-PENGAWAS-MOBILE',
            'nama' => 'Ujian Sekolah Mobile',
            'semester' => 'ganjil',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'status' => 'aktif',
            'dibuat_oleh_pengguna_id' => $admin->id,
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
        $ruangKegiatan = RuangKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'kode' => 'LAB-1',
            'nama' => 'Labor Komputer 1',
            'lokasi' => 'Lantai 1',
            'kapasitas' => 30,
            'urutan' => 1,
            'aktif' => true,
        ]);
        $ujian = UjianCbt::create([
            'alur' => 'terpusat',
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mapel->id,
            'kode' => 'PAKET-PENGAWAS-MOBILE',
            'nama' => 'Paket Matematika',
            'semester' => 'ganjil',
            'tingkat' => 9,
            'tanggal_mulai' => now()->startOfDay()->addHours(7),
            'tanggal_selesai' => now()->startOfDay()->addHours(9),
            'durasi_menit' => 90,
            'jumlah_soal' => 20,
            'status' => 'terjadwal',
        ]);
        $jadwal->update(['ujian_cbt_id' => $ujian->id]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'IX.A',
            'tingkat' => 9,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $kelasUjian = KelasUjianCbt::create(['ujian_cbt_id' => $ujian->id, 'kelas_id' => $kelas->id]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Pengawas Mobile',
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
        $pengawas = Pegawai::create([
            'nama_lengkap' => 'Guru Pengawas Mobile',
            'nip' => '198801012026091001',
            'jenis_kelamin' => 'L',
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
        $pegawaiLain = Pegawai::create([
            'nama_lengkap' => 'Guru Bukan Pengawas',
            'nip' => '198801012026091002',
            'jenis_kelamin' => 'P',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akunLain = Pengguna::create([
            'pegawai_id' => $pegawaiLain->id,
            'nama' => $pegawaiLain->nama_lengkap,
            'username' => $pegawaiLain->nip,
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        PengawasRuangUjianTerpusat::create([
            'jadwal_ujian_cbt_id' => $jadwal->id,
            'ruang_kegiatan_ujian_cbt_id' => $ruangKegiatan->id,
            'pengawas_utama_pegawai_id' => $pengawas->id,
            'ditugaskan_oleh_pengguna_id' => $admin->id,
        ]);
        $ruangOperasional = RuangUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'jadwal_ujian_cbt_id' => $jadwal->id,
            'ruang_kegiatan_ujian_cbt_id' => $ruangKegiatan->id,
            'kode' => 'LAB-1',
            'nama' => 'Labor Komputer 1',
            'lokasi' => 'Lantai 1',
            'kapasitas' => 30,
            'pengawas_utama_pegawai_id' => $pengawas->id,
            'status_bukti' => 'belum_diunggah',
            'status' => 'siap',
        ]);
        $peserta = PesertaUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_ujian_cbt_id' => $kelasUjian->id,
            'ruang_ujian_cbt_id' => $ruangOperasional->id,
            'anggota_kelas_id' => $anggota->id,
            'nomor_peserta' => 'UT-26001',
            'nomor_meja' => 1,
            'status' => 'aktif',
            'status_kehadiran_ujian' => 'belum_absen',
            'menit_tersisa' => 90,
            'perangkat_terakhir' => 'android-device-001',
        ]);

        return [
            'akun_pengawas' => $akunPengawas,
            'akun_lain' => $akunLain,
            'ruang_operasional' => $ruangOperasional,
            'peserta' => $peserta,
        ];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel Pengawas', ['mobile'])->plainTextToken;
    }
}
