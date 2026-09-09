<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\ButirPelanggaranLaporan;
use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\RiwayatProsesPembinaanSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TindakLanjutPembinaanSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressKasusSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_siswa_hanya_melihat_daftar_progress_kasus_miliknya(): void
    {
        [$tahun, $kelas] = $this->buatTahunDanKelas();
        [$siswa, $akun] = $this->buatSiswaBerakun($kelas, 'Siswa Progress Mobile', '0011223301');
        [$siswaLain] = $this->buatSiswaBerakun($kelas, 'Siswa Lain Mobile', '0011223302');
        $milikSendiri = $this->buatLaporan($siswa, $tahun, $kelas, [
            'nomor_laporan' => 'LP-MOBILE-001',
            'status' => 'diproses',
            'status_verifikasi' => 'pemeriksaan_bk',
            'catatan_rahasia' => 'Catatan internal tidak boleh keluar melalui API.',
        ]);
        $this->buatLaporan($siswaLain, $tahun, $kelas, [
            'nomor_laporan' => 'LP-MOBILE-002',
            'status_verifikasi' => 'disahkan',
            'total_poin' => 50,
        ]);

        $this->getJson(route('api.v1.progress-kasus-siswa.index'))
            ->assertUnauthorized();

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.progress-kasus-siswa.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.siswa.id', $siswa->id)
            ->assertJsonPath('data.items.0.id', $milikSendiri->id)
            ->assertJsonPath('data.items.0.status.label', 'Sedang diperiksa BK')
            ->assertJsonPath('data.ringkasan.semua', 1)
            ->assertJsonPath('data.ringkasan.diproses', 1)
            ->assertJsonMissing(['nomor_laporan' => 'LP-MOBILE-002'])
            ->assertJsonMissing(['catatan_rahasia' => 'Catatan internal tidak boleh keluar melalui API.']);
    }

    public function test_detail_hanya_memuat_keputusan_resmi_dan_linimasa_publik(): void
    {
        [$tahun, $kelas] = $this->buatTahunDanKelas();
        [$siswa, $akun] = $this->buatSiswaBerakun($kelas, 'Siswa Poin Mobile', '0011223310');
        [$siswaLain] = $this->buatSiswaBerakun($kelas, 'Siswa Detail Lain', '0011223311');
        $laporan = $this->buatLaporan($siswa, $tahun, $kelas, [
            'nomor_laporan' => 'LP-MOBILE-010',
            'status' => 'selesai',
            'status_verifikasi' => 'disahkan',
            'jenis_laporan' => 'pelanggaran',
            'total_poin' => 15,
            'kronologi' => 'Kronologi yang boleh diketahui siswa.',
            'catatan_rahasia' => 'Catatan rahasia laporan.',
        ]);
        $laporanLain = $this->buatLaporan($siswaLain, $tahun, $kelas, [
            'nomor_laporan' => 'LP-MOBILE-011',
        ]);
        $jenisPelanggaran = JenisPelanggaranSiswa::create([
            'kategori_pembinaan_siswa_id' => KategoriPembinaanSiswa::query()
                ->where('nama', 'Kedisiplinan')
                ->value('id'),
            'kode' => 'P-MOBILE-01',
            'nama' => 'Datang terlambat tanpa alasan',
            'tingkat' => 'ringan',
            'poin' => 15,
            'urutan' => 1,
            'aktif' => true,
        ]);
        ButirPelanggaranLaporan::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'jenis_pelanggaran_siswa_id' => $jenisPelanggaran->id,
            'kode_pelanggaran' => 'P-01',
            'nama_pelanggaran' => 'Datang terlambat tanpa alasan',
            'tingkat' => 'ringan',
            'poin' => 15,
            'catatan' => 'Catatan internal butir.',
        ]);
        RiwayatProsesPembinaanSiswa::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'kode_kegiatan' => 'keputusan_bk',
            'judul' => 'Judul internal',
            'keterangan' => 'Keterangan pemeriksaan internal.',
            'terjadi_pada' => now()->subDay(),
            'data' => ['hasil' => 'sanksi_poin'],
        ]);
        RiwayatProsesPembinaanSiswa::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'kode_kegiatan' => 'poin_disahkan_wakil',
            'judul' => 'Poin disahkan',
            'keterangan' => 'Catatan pengesahan internal.',
            'terjadi_pada' => now(),
        ]);
        TindakLanjutPembinaanSiswa::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'tanggal_tindak_lanjut' => today(),
            'jenis_tindak_lanjut' => 'konseling_siswa',
            'ringkasan' => 'Isi konseling internal.',
            'hasil' => 'Hasil konseling internal.',
            'status_laporan' => 'selesai',
            'catatan_rahasia' => 'Rahasia tindak lanjut.',
        ]);

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.progress-kasus-siswa.show', $laporan))
            ->assertOk()
            ->assertJsonPath('data.laporan.kronologi', 'Kronologi yang boleh diketahui siswa.')
            ->assertJsonPath('data.laporan.status.label', 'Pelanggaran berpoin disahkan')
            ->assertJsonPath('data.tahapan.3.selesai', true)
            ->assertJsonPath('data.keputusan.total_poin_resmi', 15)
            ->assertJsonPath('data.keputusan.butir_pelanggaran.0.nama', 'Datang terlambat tanpa alasan')
            ->assertJsonPath('data.tindak_lanjut.0.jenis', 'Konseling Siswa')
            ->assertJsonFragment(['judul' => 'BK merekomendasikan pelanggaran berpoin'])
            ->assertJsonFragment(['judul' => 'Poin disahkan Wakil Kesiswaan'])
            ->assertJsonMissing(['catatan_rahasia' => 'Catatan rahasia laporan.'])
            ->assertJsonMissing(['catatan' => 'Catatan internal butir.'])
            ->assertJsonMissing(['keterangan' => 'Keterangan pemeriksaan internal.'])
            ->assertJsonMissing(['ringkasan' => 'Isi konseling internal.'])
            ->assertJsonMissing(['hasil' => 'Hasil konseling internal.']);

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.progress-kasus-siswa.show', $laporanLain))
            ->assertNotFound();
    }

    public function test_pegawai_tidak_dapat_membuka_progress_kasus_siswa(): void
    {
        $pegawai = Pengguna::create([
            'nama' => 'Pegawai Biasa Mobile',
            'username' => 'pegawai.progress.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pegawai))
            ->getJson(route('api.v1.progress-kasus-siswa.index'))
            ->assertForbidden();
    }

    private function buatTahunDanKelas(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2039/2040',
            'tanggal_mulai' => '2039-07-01',
            'tanggal_selesai' => '2040-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.Progress',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);

        return [$tahun, $kelas];
    }

    private function buatSiswaBerakun(Kelas $kelas, string $nama, string $nisn): array
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => '26'.$nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
        ]);
        $akun = Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $nama,
            'username' => $nisn,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        return [$siswa, $akun];
    }

    private function buatLaporan(
        Siswa $siswa,
        TahunPelajaran $tahun,
        Kelas $kelas,
        array $tambahan,
    ): LaporanPembinaanSiswa {
        return LaporanPembinaanSiswa::create(array_merge([
            'nomor_laporan' => 'LP-'.fake()->unique()->numerify('########'),
            'jenis_laporan' => 'kejadian',
            'sumber_laporan' => 'manual',
            'tanggal_kejadian' => '2039-08-01',
            'tempat_kejadian' => 'Koridor sekolah',
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'tingkat' => 'ringan',
            'status' => 'baru',
            'status_verifikasi' => 'diajukan',
            'total_poin' => 0,
            'kronologi' => 'Kejadian sedang diperiksa oleh sekolah.',
        ], $tambahan));
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
