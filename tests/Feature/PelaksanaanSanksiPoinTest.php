<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\AturanSanksiPoin;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PelaksanaanSanksiPoinTest extends TestCase
{
    use RefreshDatabase;

    public function test_bk_dan_wakil_kesiswaan_mendapat_izin_kelola_sanksi(): void
    {
        $this->assertDatabaseHas('izin', ['kode' => 'poin_siswa.sanksi_kelola', 'aktif' => true]);
        $this->assertTrue(Peran::where('kode', 'bk')->firstOrFail()->memilikiIzin('poin_siswa.sanksi_kelola'));
        $this->assertTrue(Peran::where('kode', 'wakil_pimpinan_kesiswaan')->firstOrFail()->memilikiIzin('poin_siswa.sanksi_kelola'));
    }

    public function test_sanksi_dapat_ditugaskan_diproses_dan_diselesaikan_dengan_riwayat(): void
    {
        [$administrator, $tahun, $siswa, $sanksi] = $this->dataDasar();
        [$petugas, $akunBk] = $this->buatAkunPegawai('Petugas BK Sanksi', '198701012017011001', 'bk');

        $this->actingAs($administrator)
            ->get(route('sanksi-poin-siswa.index'))
            ->assertOk()
            ->assertSee($siswa->nama_lengkap)
            ->assertSee('Pelaksanaan Sanksi Siswa');
        $this->get(route('sanksi-poin-siswa.show', $sanksi))
            ->assertOk()
            ->assertSee($siswa->nama_lengkap)
            ->assertSee('Proses Pelaksanaan');

        $this->put(route('sanksi-poin-siswa.update', $sanksi), [
            'status' => 'diproses',
            'petugas_pegawai_id' => $petugas->id,
            'batas_pelaksanaan' => '2026-07-30',
            'catatan' => 'Lakukan konseling dan pemanggilan orang tua.',
        ])->assertRedirect(route('sanksi-poin-siswa.show', $sanksi))->assertSessionHasNoErrors();

        $sanksi->refresh();
        $this->assertSame('diproses', $sanksi->status);
        $this->assertSame($petugas->id, $sanksi->petugas_pegawai_id);
        $this->assertNotNull($sanksi->mulai_diproses_pada);
        $this->assertDatabaseHas('riwayat_sanksi_poin_siswa', [
            'sanksi_poin_siswa_id' => $sanksi->id,
            'status_sebelum' => 'menunggu',
            'status_sesudah' => 'diproses',
        ]);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $akunBk->id,
            'judul' => 'Pelaksanaan sanksi dimulai',
        ]);

        $this->actingAs($akunBk)->put(route('sanksi-poin-siswa.update', $sanksi), [
            'status' => 'selesai',
            'petugas_pegawai_id' => $petugas->id,
            'batas_pelaksanaan' => '2026-07-30',
            'catatan' => 'Siswa dan orang tua telah hadir.',
            'hasil_pelaksanaan' => 'Kesepakatan pembinaan ditandatangani dan siswa berkomitmen memperbaiki perilaku.',
        ])->assertRedirect(route('sanksi-poin-siswa.show', $sanksi))->assertSessionHasNoErrors();

        $sanksi->refresh();
        $this->assertSame('selesai', $sanksi->status);
        $this->assertNotNull($sanksi->dilaksanakan_pada);
        $this->assertDatabaseHas('riwayat_sanksi_poin_siswa', [
            'sanksi_poin_siswa_id' => $sanksi->id,
            'status_sebelum' => 'diproses',
            'status_sesudah' => 'selesai',
        ]);

        $this->put(route('sanksi-poin-siswa.update', $sanksi), [
            'status' => 'diproses',
            'petugas_pegawai_id' => $petugas->id,
        ])->assertForbidden();
    }

    public function test_sanksi_tidak_dapat_diselesaikan_tanpa_hasil_pelaksanaan(): void
    {
        [$administrator, , , $sanksi] = $this->dataDasar();
        [$petugas] = $this->buatAkunPegawai('BK Validasi Sanksi', '198801012018011002', 'bk');
        $sanksi->update([
            'status' => 'diproses',
            'petugas_pegawai_id' => $petugas->id,
            'batas_pelaksanaan' => '2026-07-30',
            'mulai_diproses_pada' => now(),
        ]);

        $this->actingAs($administrator)->put(route('sanksi-poin-siswa.update', $sanksi), [
            'status' => 'selesai',
            'petugas_pegawai_id' => $petugas->id,
            'batas_pelaksanaan' => '2026-07-30',
        ])->assertSessionHasErrors('hasil_pelaksanaan');

        $this->assertSame('diproses', $sanksi->fresh()->status);
    }

    public function test_bukti_pelaksanaan_disimpan_privat_dan_tercatat_dalam_riwayat(): void
    {
        Storage::fake('local');
        [$administrator, , , $sanksi] = $this->dataDasar();

        $this->actingAs($administrator)->post(route('bukti-pelaksanaan-sanksi.store', $sanksi), [
            'bukti_sanksi' => [UploadedFile::fake()->create('pemanggilan-orang-tua.jpg', 250, 'image/jpeg')],
            'keterangan_bukti' => 'Dokumentasi pelaksanaan sanksi.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $bukti = $sanksi->buktiPelaksanaanSanksi()->firstOrFail();
        Storage::disk('local')->assertExists($bukti->lokasi_file);
        $this->assertDatabaseHas('riwayat_sanksi_poin_siswa', [
            'sanksi_poin_siswa_id' => $sanksi->id,
            'jenis_kegiatan' => 'bukti_ditambahkan',
        ]);

        $this->get(route('bukti-pelaksanaan-sanksi.download', $bukti))->assertOk();
        $this->delete(route('bukti-pelaksanaan-sanksi.destroy', $bukti))->assertRedirect();
        Storage::disk('local')->assertMissing($bukti->lokasi_file);
        $this->assertDatabaseMissing('bukti_pelaksanaan_sanksi', ['id' => $bukti->id]);
    }

    public function test_wali_kelas_hanya_melihat_sanksi_siswa_di_kelasnya(): void
    {
        [, $tahun, $siswaDalam, $sanksiDalam] = $this->dataDasar();
        [$wali, $akunWali] = $this->buatAkunPegawai('Wali Kelas Cakupan', '198901012019011003', 'wali_kelas');
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_id' => $wali->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswaDalam->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);

        $siswaLuar = Siswa::create(['nama_lengkap' => 'Siswa Di Luar Cakupan', 'nisn' => '0077000099', 'aktif' => true]);
        $sanksiLuar = SanksiPoinSiswa::create([
            'siswa_id' => $siswaLuar->id,
            'tahun_pelajaran_id' => $tahun->id,
            'aturan_sanksi_poin_id' => $sanksiDalam->aturan_sanksi_poin_id,
            'poin_saat_terpicu' => 25,
            'status' => 'menunggu',
            'terpicu_pada' => now(),
        ]);

        $this->actingAs($akunWali)
            ->get(route('sanksi-poin-siswa.index', ['tahun_pelajaran_id' => $tahun->id]))
            ->assertOk()
            ->assertSee($siswaDalam->nama_lengkap)
            ->assertDontSee($siswaLuar->nama_lengkap);

        $this->get(route('sanksi-poin-siswa.show', $sanksiLuar))->assertForbidden();
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
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Pelaksanaan Sanksi', 'nisn' => '0077000001', 'aktif' => true]);
        $aturan = AturanSanksiPoin::where('aktif', true)->orderBy('batas_poin')->firstOrFail();
        $sanksi = SanksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'aturan_sanksi_poin_id' => $aturan->id,
            'poin_saat_terpicu' => $aturan->batas_poin,
            'status' => 'menunggu',
            'terpicu_pada' => '2026-07-23 08:00:00',
        ]);

        return [$administrator, $tahun, $siswa, $sanksi];
    }

    private function buatAkunPegawai(string $nama, string $nip, string $kodePeran): array
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'nip' => $nip, 'aktif' => true]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', $kodePeran)->firstOrFail());

        return [$pegawai, $pengguna];
    }
}
