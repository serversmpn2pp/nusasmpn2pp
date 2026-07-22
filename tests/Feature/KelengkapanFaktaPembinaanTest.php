<?php

namespace Tests\Feature;

use App\Models\BuktiLaporanPembinaanSiswa;
use App\Models\JenisPelanggaranSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KelengkapanFaktaPembinaanTest extends TestCase
{
    use RefreshDatabase;

    public function test_laporan_baru_menyimpan_bukti_saksi_dan_linimasa(): void
    {
        Storage::fake('local');
        [$administrator, $tahun, $siswa, $jenis] = $this->dataDasar();

        $respons = $this->actingAs($administrator)->post(route('laporan-pembinaan-siswa.store'), [
            'jenis_laporan' => 'pelanggaran',
            'tanggal_kejadian' => '2026-07-22',
            'tempat_kejadian' => 'Koridor kelas',
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'jenis_pelanggaran_ids' => [$jenis->id],
            'kronologi' => 'Siswa terlihat melakukan pelanggaran saat pergantian jam.',
            'daftar_saksi' => [[
                'jenis_saksi' => 'lainnya',
                'nama_saksi' => 'Saksi Uji',
                'pernyataan' => 'Melihat kejadian secara langsung dari koridor.',
            ]],
            'bukti_laporan' => [UploadedFile::fake()->create('bukti-koridor.jpg', 120, 'image/jpeg')],
            'keterangan_bukti' => 'Dokumentasi awal pelapor.',
        ]);

        $laporan = LaporanPembinaanSiswa::latest('id')->firstOrFail();
        $respons->assertRedirect(route('laporan-pembinaan-siswa.show', $laporan));
        $respons->assertSessionHasNoErrors();
        $this->assertDatabaseHas('saksi_laporan_pembinaan_siswa', [
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'nama_saksi' => 'Saksi Uji',
        ]);
        $bukti = BuktiLaporanPembinaanSiswa::where('laporan_pembinaan_siswa_id', $laporan->id)->firstOrFail();
        Storage::disk('local')->assertExists($bukti->lokasi_file);
        $this->assertDatabaseHas('riwayat_proses_pembinaan_siswa', ['laporan_pembinaan_siswa_id' => $laporan->id, 'kode_kegiatan' => 'laporan_dibuat']);
        $this->assertDatabaseHas('riwayat_proses_pembinaan_siswa', ['laporan_pembinaan_siswa_id' => $laporan->id, 'kode_kegiatan' => 'saksi_ditambahkan']);
        $this->assertDatabaseHas('riwayat_proses_pembinaan_siswa', ['laporan_pembinaan_siswa_id' => $laporan->id, 'kode_kegiatan' => 'bukti_ditambahkan']);

        $this->get(route('bukti-laporan-pembinaan.download', $bukti))->assertOk();
        $this->get(route('laporan-pembinaan-siswa.show', $laporan))
            ->assertOk()
            ->assertSee('Bukti Pendukung')
            ->assertSee('Saksi Kejadian')
            ->assertSee('Linimasa Proses');
    }

    public function test_bk_dapat_mencatat_klarifikasi_dan_saksi_tambahan(): void
    {
        [$administrator, $tahun, $siswa, $jenis] = $this->dataDasar();
        $laporan = $this->buatLaporan($administrator, $tahun, $siswa, $jenis);

        $this->actingAs($administrator)->post(route('klarifikasi-siswa-pembinaan.store', $laporan), [
            'metode' => 'didampingi',
            'pendamping' => 'Orang tua siswa',
            'disampaikan_pada' => '2026-07-22 10:15:00',
            'isi_klarifikasi' => 'Siswa menjelaskan urutan kejadian dari sudut pandangnya.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post(route('saksi-laporan-pembinaan.store', $laporan), [
            'jenis_saksi' => 'lainnya',
            'nama_saksi' => 'Petugas Piket',
            'pernyataan' => 'Petugas piket menerima laporan pada pukul 10.00.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('klarifikasi_siswa_pembinaan', [
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'metode' => 'didampingi',
        ]);
        $this->assertDatabaseHas('riwayat_proses_pembinaan_siswa', ['laporan_pembinaan_siswa_id' => $laporan->id, 'kode_kegiatan' => 'klarifikasi_siswa']);
    }

    public function test_bukti_privat_tidak_dapat_diakses_pengguna_di_luar_cakupan(): void
    {
        Storage::fake('local');
        [$administrator, $tahun, $siswa, $jenis] = $this->dataDasar();
        $laporan = $this->buatLaporan($administrator, $tahun, $siswa, $jenis);
        $file = UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf');
        $this->actingAs($administrator)->post(route('bukti-laporan-pembinaan.store', $laporan), [
            'bukti_laporan' => [$file],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $bukti = $laporan->buktiLaporanPembinaanSiswa()->firstOrFail();

        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru Di Luar Cakupan', 'nip' => '199001012020121001', 'aktif' => true]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());

        $this->actingAs($pengguna)
            ->get(route('bukti-laporan-pembinaan.download', $bukti))
            ->assertForbidden();
    }

    public function test_fakta_tidak_dapat_diubah_setelah_laporan_final(): void
    {
        Storage::fake('local');
        [$administrator, $tahun, $siswa, $jenis] = $this->dataDasar();
        $laporan = $this->buatLaporan($administrator, $tahun, $siswa, $jenis);
        $laporan->update(['status_verifikasi' => 'disahkan']);

        $this->actingAs($administrator)->post(route('bukti-laporan-pembinaan.store', $laporan), [
            'bukti_laporan' => [UploadedFile::fake()->create('terlambat.jpg', 120, 'image/jpeg')],
        ])->assertForbidden();

        $this->assertDatabaseCount('bukti_laporan_pembinaan_siswa', 0);
    }

    private function dataDasar(): array
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Fakta Uji', 'nisn' => '0099887701', 'aktif' => true]);
        $jenis = JenisPelanggaranSiswa::where('aktif', true)->firstOrFail();

        return [$administrator, $tahun, $siswa, $jenis];
    }

    private function buatLaporan(Pengguna $administrator, TahunPelajaran $tahun, Siswa $siswa, JenisPelanggaranSiswa $jenis): LaporanPembinaanSiswa
    {
        return LaporanPembinaanSiswa::create([
            'nomor_laporan' => 'PB-FAKTA-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'jenis_laporan' => 'pelanggaran',
            'tanggal_kejadian' => '2026-07-22',
            'siswa_id' => $siswa->id,
            'kategori_pembinaan_siswa_id' => $jenis->kategori_pembinaan_siswa_id,
            'tahun_pelajaran_id' => $tahun->id,
            'tingkat' => $jenis->tingkat,
            'status' => 'baru',
            'status_verifikasi' => 'diajukan',
            'total_poin' => $jenis->poin,
            'kronologi' => 'Kronologi untuk pengujian kelengkapan fakta.',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
    }
}
