<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\ButirPelanggaranLaporan;
use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\RiwayatProsesPembinaanSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TindakLanjutPembinaanSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProgressKasusSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_siswa_hanya_melihat_progress_kasus_miliknya_tanpa_catatan_internal(): void
    {
        [$tahun, $kelas] = $this->buatTahunDanKelas();
        [$siswa, $akun] = $this->buatSiswaBerakun($kelas, 'Siswa Pemilik Kasus', '0011223344');
        [$siswaLain, $akunLain] = $this->buatSiswaBerakun($kelas, 'Siswa Lain', '0099887766');

        $laporan = $this->buatLaporan($siswa, $tahun, $kelas, [
            'nomor_laporan' => 'LP-2026-001',
            'status' => 'diproses',
            'status_verifikasi' => 'pemeriksaan_bk',
            'kronologi' => 'Siswa diminta memberikan keterangan tentang kejadian di koridor.',
            'catatan_rahasia' => 'Catatan internal BK tidak boleh terlihat siswa.',
        ]);
        $laporanLain = $this->buatLaporan($siswaLain, $tahun, $kelas, [
            'nomor_laporan' => 'LP-2026-002',
            'status_verifikasi' => 'disahkan',
            'total_poin' => 50,
            'kronologi' => 'Kronologi rahasia siswa lain.',
        ]);

        $this->actingAs($akun)
            ->get(route('progress-kasus-siswa.index'))
            ->assertOk()
            ->assertViewIs('progress-kasus-siswa.index')
            ->assertSee('LP-2026-001')
            ->assertSee('Sedang diperiksa BK')
            ->assertSee('Progress Kasus Saya')
            ->assertDontSee('LP-2026-002')
            ->assertDontSee('Siswa Lain')
            ->assertDontSee('Catatan internal BK tidak boleh terlihat siswa.');

        $this->actingAs($akun)
            ->get(route('progress-kasus-siswa.show', $laporan))
            ->assertOk()
            ->assertSee('Siswa diminta memberikan keterangan tentang kejadian di koridor.')
            ->assertDontSee('Catatan internal BK tidak boleh terlihat siswa.');

        $this->actingAs($akun)
            ->get(route('progress-kasus-siswa.show', $laporanLain))
            ->assertNotFound();

        $this->actingAs($akunLain)
            ->get(route('progress-kasus-siswa.show', $laporan))
            ->assertNotFound();
    }

    public function test_siswa_melihat_poin_resmi_dan_linimasa_aman_setelah_disahkan(): void
    {
        [$tahun, $kelas] = $this->buatTahunDanKelas();
        [$siswa, $akun] = $this->buatSiswaBerakun($kelas, 'Siswa Poin Resmi', '0011223355');
        $laporan = $this->buatLaporan($siswa, $tahun, $kelas, [
            'nomor_laporan' => 'LP-2026-010',
            'status' => 'selesai',
            'status_verifikasi' => 'disahkan',
            'jenis_laporan' => 'pelanggaran',
            'total_poin' => 15,
        ]);
        $kategori = KategoriPembinaanSiswa::query()->where('nama', 'Kedisiplinan')->firstOrFail();
        $jenisPelanggaran = JenisPelanggaranSiswa::create([
            'kategori_pembinaan_siswa_id' => $kategori->id,
            'kode' => 'P-01',
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
            'catatan' => 'Catatan butir internal tidak ditampilkan.',
        ]);
        RiwayatProsesPembinaanSiswa::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'kode_kegiatan' => 'keputusan_bk',
            'judul' => 'Judul internal',
            'keterangan' => 'Catatan pemeriksaan internal yang sensitif.',
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

        $this->actingAs($akun)
            ->get(route('progress-kasus-siswa.show', $laporan))
            ->assertOk()
            ->assertSee('Pelanggaran berpoin disahkan')
            ->assertSee('Datang terlambat tanpa alasan')
            ->assertSee('15 poin')
            ->assertSee('BK merekomendasikan pelanggaran berpoin')
            ->assertSee('Poin disahkan Wakil Kesiswaan')
            ->assertSee('Konseling Siswa')
            ->assertDontSee('Catatan pemeriksaan internal yang sensitif.')
            ->assertDontSee('Catatan butir internal tidak ditampilkan.')
            ->assertDontSee('Isi konseling internal.')
            ->assertDontSee('Hasil konseling internal.')
            ->assertDontSee('Rahasia tindak lanjut.');
    }

    public function test_pegawai_tidak_dapat_membuka_progress_kasus_siswa(): void
    {
        $pegawai = Pengguna::create([
            'nama' => 'Pegawai Biasa',
            'username' => 'pegawai-biasa',
            'kata_sandi' => Hash::make('rahasia'),
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->actingAs($pegawai)
            ->get(route('progress-kasus-siswa.index'))
            ->assertForbidden();
    }

    private function buatTahunDanKelas(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
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
            'kata_sandi' => Hash::make('rahasia'),
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $akun->daftarPeran()->sync([Peran::where('kode', 'siswa')->value('id')]);

        return [$siswa, $akun];
    }

    private function buatLaporan(Siswa $siswa, TahunPelajaran $tahun, Kelas $kelas, array $tambahan): LaporanPembinaanSiswa
    {
        return LaporanPembinaanSiswa::create(array_merge([
            'nomor_laporan' => 'LP-'.fake()->unique()->numerify('########'),
            'jenis_laporan' => 'kejadian',
            'sumber_laporan' => 'manual',
            'tanggal_kejadian' => '2026-08-01',
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
}
