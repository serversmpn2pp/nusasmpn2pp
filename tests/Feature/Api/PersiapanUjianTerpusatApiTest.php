<?php

namespace Tests\Feature\Api;

use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersiapanUjianTerpusatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_persiapan_memerlukan_autentikasi_mobile(): void
    {
        $this->getJson(route('api.v1.ujian-terpusat.index'))->assertUnauthorized();
    }

    public function test_administrator_dapat_mengelola_informasi_panitia_sesi_dan_ruang(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $jenis = JenisUjianCbt::where('kode', 'SAS')->firstOrFail();
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Teknisi Ujian Mobile',
            'nip' => '19870009',
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $token = $administrator->createToken('Persiapan Ujian Android', ['mobile'])->plainTextToken;

        $response = $this->withToken($token)
            ->postJson(route('api.v1.ujian-terpusat.store'), [
                'jenis_ujian_cbt_id' => $jenis->id,
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'SAS Ganjil Native',
                'semester' => 'ganjil',
                'tanggal_mulai' => '2026-12-01',
                'tanggal_selesai' => '2026-12-08',
                'status' => 'draft',
                'keterangan' => 'Dibuat melalui aplikasi Android.',
            ])
            ->assertCreated();

        $kegiatan = KegiatanUjianCbt::where('nama', 'SAS Ganjil Native')->firstOrFail();
        $response->assertJsonPath('data.id', $kegiatan->id);
        $this->assertSame('UT-2026-001', $kegiatan->kode);

        $this->withToken($token)
            ->postJson(route('api.v1.ujian-terpusat.panitia.store', $kegiatan), [
                'pegawai_id' => $pegawai->id,
                'jabatan' => 'teknisi',
                'catatan' => 'Menangani perangkat dan jaringan.',
            ])
            ->assertOk();
        $this->withToken($token)
            ->postJson(route('api.v1.ujian-terpusat.sesi.store', $kegiatan), [
                'nama' => 'Sesi Pagi',
                'waktu_mulai' => '07:30',
                'waktu_selesai' => '09:30',
                'aktif' => true,
            ])
            ->assertCreated();
        $this->withToken($token)
            ->postJson(route('api.v1.ujian-terpusat.ruang.store', $kegiatan), [
                'nama' => 'Ruang 1',
                'lokasi' => 'Kelas VII.A',
                'kapasitas' => 20,
                'aktif' => true,
            ])
            ->assertCreated();

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-terpusat.index'))
            ->assertOk()
            ->assertJsonPath('data.items.0.nama', 'SAS Ganjil Native')
            ->assertJsonPath('data.items.0.jumlah_panitia', 1)
            ->assertJsonPath('data.items.0.jumlah_sesi', 1)
            ->assertJsonPath('data.items.0.jumlah_ruang', 1)
            ->assertJsonPath('data.items.0.total_kapasitas', 20)
            ->assertJsonPath('data.hak_akses.dapat_kelola_utama', true);

        $detail = $this->withToken($token)
            ->getJson(route('api.v1.ujian-terpusat.show', $kegiatan))
            ->assertOk()
            ->assertJsonPath('data.kegiatan.kode', 'UT-2026-001')
            ->assertJsonPath('data.panitia.0.nama', 'Teknisi Ujian Mobile')
            ->assertJsonPath('data.panitia.0.label_jabatan', 'Teknisi')
            ->assertJsonPath('data.sesi.0.kode', 'S01')
            ->assertJsonPath('data.sesi.0.label_waktu', '07:30 - 09:30')
            ->assertJsonPath('data.ruang.0.kode', 'R01')
            ->assertJsonPath('data.ruang.0.kapasitas', 20)
            ->assertJsonPath('data.referensi.pegawai.0.nama', 'Teknisi Ujian Mobile');
        $this->assertStringContainsString('no-store', (string) $detail->headers->get('Cache-Control'));
    }

    public function test_validasi_dan_perubahan_persiapan_sama_dengan_desktop(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true]);
        $jenis = JenisUjianCbt::where('kode', 'STS')->firstOrFail();
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kode' => 'UT-2026-091',
            'nama' => 'STS Native',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2026-09-05',
            'status' => 'draft',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        $sesi = $kegiatan->sesiKegiatanUjianCbt()->create([
            'kode' => 'S01',
            'nama' => 'Sesi Lama',
            'waktu_mulai' => '07:00',
            'waktu_selesai' => '08:00',
            'urutan' => 1,
            'aktif' => true,
        ]);
        $ruang = $kegiatan->ruangKegiatanUjianCbt()->create([
            'kode' => 'R01',
            'nama' => 'Ruang Lama',
            'kapasitas' => 20,
            'urutan' => 1,
            'aktif' => true,
        ]);
        $token = $administrator->createToken('Validasi Persiapan', ['mobile'])->plainTextToken;

        $this->withToken($token)
            ->patchJson(route('api.v1.ujian-terpusat.sesi.update', [$kegiatan, $sesi]), [
                'nama' => 'Sesi Tidak Valid',
                'waktu_mulai' => '09:00',
                'waktu_selesai' => '08:00',
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('waktu_selesai');

        $this->withToken($token)
            ->patchJson(route('api.v1.ujian-terpusat.sesi.update', [$kegiatan, $sesi]), [
                'nama' => 'Sesi Pertama',
                'waktu_mulai' => '07:30',
                'waktu_selesai' => '09:30',
                'aktif' => true,
                'keterangan' => 'Pagi',
            ])
            ->assertOk();
        $this->assertDatabaseHas('sesi_kegiatan_ujian_cbt', [
            'id' => $sesi->id,
            'nama' => 'Sesi Pertama',
            'waktu_mulai' => '07:30',
        ]);

        $this->withToken($token)
            ->patchJson(route('api.v1.ujian-terpusat.ruang.update', [$kegiatan, $ruang]), [
                'nama' => 'Laboratorium Komputer',
                'lokasi' => 'Lantai 2',
                'kapasitas' => 30,
                'aktif' => true,
                'keterangan' => 'Periksa jaringan sebelum ujian.',
            ])
            ->assertOk();
        $this->assertDatabaseHas('ruang_kegiatan_ujian_cbt', [
            'id' => $ruang->id,
            'nama' => 'Laboratorium Komputer',
            'kapasitas' => 30,
        ]);

        $this->withToken($token)
            ->patchJson(route('api.v1.ujian-terpusat.update', $kegiatan), [
                'jenis_ujian_cbt_id' => $jenis->id,
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'STS Native Revisi',
                'semester' => 'ganjil',
                'tanggal_mulai' => '2026-09-01',
                'tanggal_selesai' => '2026-09-06',
                'status' => 'aktif',
            ])
            ->assertOk();
        $this->assertDatabaseHas('kegiatan_ujian_cbt', [
            'id' => $kegiatan->id,
            'nama' => 'STS Native Revisi',
            'status' => 'aktif',
        ]);
    }

    public function test_api_native_menetapkan_ruang_dan_membagi_peserta_dengan_layanan_desktop(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2027/2028',
            'tanggal_mulai' => '2027-07-01',
            'tanggal_selesai' => '2028-06-30',
            'aktif' => true,
        ]);
        $jenis = JenisUjianCbt::where('kode', 'SAS')->firstOrFail();
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kode' => 'UT-2027-001',
            'nama' => 'SAS Native Pembagian',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2027-12-01',
            'tanggal_selesai' => '2027-12-08',
            'status' => 'draft',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        $sesi = $kegiatan->sesiKegiatanUjianCbt()->create([
            'kode' => 'S01',
            'nama' => 'Sesi Pagi',
            'waktu_mulai' => '07:30',
            'waktu_selesai' => '09:30',
            'urutan' => 1,
            'aktif' => true,
        ]);
        $ruangA = $kegiatan->ruangKegiatanUjianCbt()->create([
            'kode' => 'R01',
            'nama' => 'Ruang Satu',
            'kapasitas' => 2,
            'urutan' => 1,
            'aktif' => true,
        ]);
        $ruangB = $kegiatan->ruangKegiatanUjianCbt()->create([
            'kode' => 'R02',
            'nama' => 'Ruang Dua',
            'kapasitas' => 2,
            'urutan' => 2,
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        foreach (['Citra', 'Aulia', 'Bella'] as $index => $nama) {
            $siswa = Siswa::create([
                'nama_lengkap' => $nama,
                'nis' => 'N2700'.$index,
                'nisn' => '990000270'.$index,
                'jenis_kelamin' => $index % 2 === 0 ? 'L' : 'P',
                'aktif' => true,
            ]);
            $kelas->anggotaKelas()->create([
                'tahun_pelajaran_id' => $tahun->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $index + 1,
                'status_keanggotaan' => 'aktif',
                'tanggal_masuk' => '2027-07-01',
            ]);
        }
        $token = $administrator->createToken('Pembagian Peserta Native', ['mobile'])->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-terpusat.show', $kegiatan))
            ->assertOk()
            ->assertJsonPath('data.tahap_peserta.tingkat.0.tingkat', 7)
            ->assertJsonPath('data.tahap_peserta.tingkat.0.jumlah_siswa_aktif', 3)
            ->assertJsonPath('data.tahap_peserta.tingkat.0.penetapan', null);

        $this->withToken($token)
            ->postJson(route('api.v1.ujian-terpusat.penetapan-ruang.store', $kegiatan), [
                'tingkat' => 7,
                'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                'kelas' => [$kelas->id],
                'ruang' => [$ruangA->id, $ruangB->id],
            ])
            ->assertOk();

        $kelompok = KelompokPesertaKegiatanUjianCbt::firstOrFail();
        $this->assertSame(0, $kelompok->jumlah_peserta);
        $this->assertDatabaseCount('penempatan_peserta_ujian_cbt', 0);

        $this->withToken($token)
            ->postJson(route('api.v1.ujian-terpusat.pembagian-peserta.generate', [$kegiatan, $kelompok]))
            ->assertOk()
            ->assertJsonPath('data.jumlah_peserta', 3);

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-terpusat.pembagian-peserta.show', [$kegiatan, $kelompok]))
            ->assertOk()
            ->assertJsonPath('data.kelompok.jumlah_peserta', 3)
            ->assertJsonPath('data.ruang.0.jumlah_terisi', 2)
            ->assertJsonPath('data.ruang.1.jumlah_terisi', 1)
            ->assertJsonPath('data.ruang.0.peserta.0.nama', 'Aulia')
            ->assertJsonPath('data.ruang.0.peserta.0.nomor_meja', 1)
            ->assertJsonPath('data.ruang.0.peserta.1.nama', 'Bella')
            ->assertJsonPath('data.ruang.1.peserta.0.nama', 'Citra');

        $mataPelajaran = MataPelajaran::create([
            'kode' => 'MTK-NATIVE',
            'nama' => 'Matematika Native',
            'kkm' => 78,
            'aktif' => true,
        ]);
        $responseJadwal = $this->withToken($token)
            ->postJson(route('api.v1.ujian-terpusat.jadwal.store', $kegiatan), [
                'tanggal' => '2027-12-01',
                'mata_pelajaran_id' => $mataPelajaran->id,
                'tingkat' => [7],
                'keterangan' => 'Hari pertama native',
            ])
            ->assertCreated();
        $jadwalId = $responseJadwal->json('data.id.0');

        $this->withToken($token)
            ->postJson(route('api.v1.ujian-terpusat.jadwal.store', $kegiatan), [
                'tanggal' => '2027-12-01',
                'mata_pelajaran_id' => $mataPelajaran->id,
                'tingkat' => [7],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tingkat');

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-terpusat.show', $kegiatan))
            ->assertOk()
            ->assertJsonPath('data.tahap_jadwal.items.0.id', $jadwalId)
            ->assertJsonPath('data.tahap_jadwal.items.0.mata_pelajaran', 'Matematika Native')
            ->assertJsonPath('data.tahap_jadwal.items.0.nama_sesi', 'Sesi Pagi')
            ->assertJsonPath('data.tahap_jadwal.items.0.jumlah_peserta', 3)
            ->assertJsonPath('data.tahap_jadwal.items.0.dapat_dihapus', true)
            ->assertJsonPath('data.tahap_jadwal.mata_pelajaran.0.id', $mataPelajaran->id)
            ->assertJsonPath('data.tahap_jadwal.mata_pelajaran.0.tingkat', [7, 8, 9]);

        $jadwal = $kegiatan->jadwalUjianCbt()->findOrFail($jadwalId);
        $this->withToken($token)
            ->patchJson(route('api.v1.ujian-terpusat.jadwal.update', [$kegiatan, $jadwal]), [
                'tanggal' => '2028-01-01',
                'mata_pelajaran_id' => $mataPelajaran->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tanggal');

        $this->withToken($token)
            ->patchJson(route('api.v1.ujian-terpusat.jadwal.update', [$kegiatan, $jadwal]), [
                'tanggal' => '2027-12-02',
                'mata_pelajaran_id' => $mataPelajaran->id,
                'keterangan' => 'Jadwal direvisi dari Android',
            ])
            ->assertOk();
        $this->assertDatabaseHas('jadwal_ujian_cbt', [
            'id' => $jadwalId,
            'tanggal' => '2027-12-02 00:00:00',
            'keterangan' => 'Jadwal direvisi dari Android',
        ]);

        $this->withToken($token)
            ->deleteJson(route('api.v1.ujian-terpusat.jadwal.destroy', [$kegiatan, $jadwal]))
            ->assertOk();
        $this->assertDatabaseMissing('jadwal_ujian_cbt', ['id' => $jadwalId]);

        $this->withToken($token)
            ->deleteJson(route('api.v1.ujian-terpusat.pembagian-peserta.destroy', [$kegiatan, $kelompok]))
            ->assertOk();
        $this->assertDatabaseMissing('kelompok_peserta_kegiatan_ujian_cbt', ['id' => $kelompok->id]);
        $this->assertDatabaseCount('penempatan_peserta_ujian_cbt', 0);
    }
}
