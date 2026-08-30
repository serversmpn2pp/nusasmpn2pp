<?php

namespace Tests\Feature\Api;

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

class PelaksanaanSanksiSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_mendukung_filter_ringkasan_dan_menu_native(): void
    {
        [$administrator, $tahun, $kelas, $siswa, $sanksi] = $this->dataDasar();
        $petugas = $this->buatAkunPegawai('BK Daftar Sanksi', '198601012026081001', 'bk')[0];
        $sanksi->update([
            'petugas_pegawai_id' => $petugas->id,
            'batas_pelaksanaan' => today()->subDay(),
        ]);

        $this->getJson(route('api.v1.pelaksanaan-sanksi-siswa.index'))->assertUnauthorized();
        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pelaksanaan-sanksi-siswa.index', [
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'status' => 'aktif',
                'kata_kunci' => 'pelaksanaan',
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.menunggu', 1)
            ->assertJsonPath('data.ringkasan.terlambat', 1)
            ->assertJsonPath('data.filter.tahun_pelajaran_id', $tahun->id)
            ->assertJsonPath('data.filter.kelas_id', $kelas->id)
            ->assertJsonPath('data.hak_akses.dapat_kelola_umum', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $sanksi->id)
            ->assertJsonPath('data.items.0.siswa.nama', $siswa->nama_lengkap)
            ->assertJsonPath('data.items.0.kelas.nama', $kelas->nama)
            ->assertJsonPath('data.items.0.terlambat', true)
            ->assertJsonPath('data.items.0.aturan.batas_poin', $sanksi->aturanSanksiPoin->batas_poin);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'pelaksanaan-sanksi-siswa',
                'status' => 'tersedia',
                'rute' => '/pelaksanaan-sanksi-siswa',
            ]);
    }

    public function test_administrator_dapat_menugaskan_memulai_dan_petugas_menyelesaikan_sanksi(): void
    {
        [$administrator, , , $siswa, $sanksi] = $this->dataDasar();
        [$petugas, $akunBk] = $this->buatAkunPegawai('BK Pelaksana Native', '198702022026082002', 'bk');
        $tokenAdmin = $this->token($administrator);

        $this->withToken($tokenAdmin)
            ->getJson(route('api.v1.pelaksanaan-sanksi-siswa.show', $sanksi))
            ->assertOk()
            ->assertJsonPath('data.sanksi.siswa.nama', $siswa->nama_lengkap)
            ->assertJsonPath('data.sanksi.status', 'menunggu')
            ->assertJsonPath('data.pilihan_status.1.kode', 'diproses')
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonFragment(['nama' => $petugas->nama_lengkap]);

        $this->withToken($tokenAdmin)
            ->putJson(route('api.v1.pelaksanaan-sanksi-siswa.update', $sanksi), [
                'status' => 'diproses',
                'petugas_pegawai_id' => $petugas->id,
                'batas_pelaksanaan' => '2026-09-10',
                'catatan' => 'Jadwalkan konseling dan pemanggilan orang tua.',
                'hasil_pelaksanaan' => null,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Pelaksanaan sanksi berhasil diperbarui.')
            ->assertJsonPath('data.sanksi.status', 'diproses')
            ->assertJsonPath('data.sanksi.petugas.id', $petugas->id)
            ->assertJsonPath('data.sanksi.batas_pelaksanaan', '2026-09-10')
            ->assertJsonPath('data.riwayat.0.status_sebelum', 'menunggu')
            ->assertJsonPath('data.riwayat.0.status_sesudah', 'diproses');

        $this->withToken($this->token($akunBk))
            ->putJson(route('api.v1.pelaksanaan-sanksi-siswa.update', $sanksi), [
                'status' => 'selesai',
                'petugas_pegawai_id' => $petugas->id,
                'batas_pelaksanaan' => '2026-09-10',
                'catatan' => 'Pertemuan telah dilaksanakan.',
                'hasil_pelaksanaan' => 'Siswa dan orang tua menandatangani kesepakatan pembinaan.',
            ])
            ->assertOk()
            ->assertJsonPath('data.sanksi.status', 'selesai')
            ->assertJsonPath('data.sanksi.hasil_pelaksanaan', 'Siswa dan orang tua menandatangani kesepakatan pembinaan.')
            ->assertJsonPath('data.hak_akses.status_final', true)
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);

        $this->assertDatabaseHas('riwayat_sanksi_poin_siswa', [
            'sanksi_poin_siswa_id' => $sanksi->id,
            'status_sebelum' => 'diproses',
            'status_sesudah' => 'selesai',
        ]);
    }

    public function test_transisi_status_tenggat_petugas_hasil_dan_alasan_dibatalkan_divalidasi(): void
    {
        [$administrator, , , , $sanksi] = $this->dataDasar();
        [$petugas] = $this->buatAkunPegawai('BK Validasi Native', '198803032026083003', 'bk');
        $token = $this->token($administrator);

        $this->withToken($token)
            ->putJson(route('api.v1.pelaksanaan-sanksi-siswa.update', $sanksi), [
                'status' => 'selesai',
                'petugas_pegawai_id' => $petugas->id,
                'batas_pelaksanaan' => '2026-09-10',
                'catatan' => 'Tidak boleh melompat.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->withToken($token)
            ->putJson(route('api.v1.pelaksanaan-sanksi-siswa.update', $sanksi), [
                'status' => 'diproses',
                'petugas_pegawai_id' => null,
                'batas_pelaksanaan' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('petugas_pegawai_id');

        $this->withToken($token)
            ->putJson(route('api.v1.pelaksanaan-sanksi-siswa.update', $sanksi), [
                'status' => 'dibatalkan',
                'petugas_pegawai_id' => null,
                'catatan' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('catatan');
    }

    public function test_bukti_privat_dapat_diunggah_diunduh_dan_dihapus_secara_native(): void
    {
        Storage::fake('local');
        [$administrator, , , , $sanksi] = $this->dataDasar();
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->post(route('api.v1.pelaksanaan-sanksi-siswa.bukti.store', $sanksi), [
                'bukti_sanksi' => [UploadedFile::fake()->create('kesepakatan.jpg', 250, 'image/jpeg')],
                'keterangan_bukti' => 'Dokumentasi pertemuan orang tua.',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.sanksi.jumlah_bukti', 1)
            ->assertJsonPath('data.bukti.0.nama_file', 'kesepakatan.jpg')
            ->assertJsonPath('data.bukti.0.keterangan', 'Dokumentasi pertemuan orang tua.');

        $buktiId = $response->json('data.bukti.0.id');
        $bukti = $sanksi->buktiPelaksanaanSanksi()->findOrFail($buktiId);
        Storage::disk('local')->assertExists($bukti->lokasi_file);

        $this->withToken($token)
            ->get(route('api.v1.pelaksanaan-sanksi-siswa.bukti', $bukti))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->withToken($token)
            ->deleteJson(route('api.v1.pelaksanaan-sanksi-siswa.bukti.destroy', $bukti))
            ->assertOk()
            ->assertJsonPath('data.sanksi.jumlah_bukti', 0)
            ->assertJsonPath('message', 'Bukti pelaksanaan berhasil dihapus.');
        Storage::disk('local')->assertMissing($bukti->lokasi_file);
    }

    public function test_wali_kelas_hanya_melihat_sanksi_siswa_di_kelasnya_dan_tidak_mengelola(): void
    {
        [, $tahun, $kelas, $siswaDalam, $sanksiDalam] = $this->dataDasar();
        [$wali, $akunWali] = $this->buatAkunPegawai('Wali Kelas Native', '198904042026084004', 'wali_kelas');
        $kelas->update(['wali_kelas_id' => $wali->id]);
        $siswaLuar = Siswa::create(['nama_lengkap' => 'Siswa Luar Sanksi Native', 'nisn' => '0077999902', 'aktif' => true]);
        $aturan = $sanksiDalam->aturanSanksiPoin;
        $sanksiLuar = SanksiPoinSiswa::create([
            'siswa_id' => $siswaLuar->id,
            'tahun_pelajaran_id' => $tahun->id,
            'aturan_sanksi_poin_id' => $aturan->id,
            'poin_saat_terpicu' => $aturan->batas_poin,
            'status' => 'menunggu',
            'terpicu_pada' => now(),
        ]);
        $token = $this->token($akunWali);

        $this->withToken($token)
            ->getJson(route('api.v1.pelaksanaan-sanksi-siswa.index', ['tahun_pelajaran_id' => $tahun->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.siswa.id', $siswaDalam->id);
        $this->withToken($token)
            ->getJson(route('api.v1.pelaksanaan-sanksi-siswa.show', $sanksiLuar))
            ->assertForbidden();
        $this->withToken($token)
            ->getJson(route('api.v1.pelaksanaan-sanksi-siswa.show', $sanksiDalam))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola', false);
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
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.E',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Pelaksanaan Sanksi Native',
            'nis' => 'NIS-SANKSI-01',
            'nisn' => '0077999901',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);
        $aturan = AturanSanksiPoin::where('aktif', true)->orderBy('batas_poin')->firstOrFail();
        $sanksi = SanksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'aturan_sanksi_poin_id' => $aturan->id,
            'poin_saat_terpicu' => $aturan->batas_poin,
            'status' => 'menunggu',
            'terpicu_pada' => '2026-08-31 08:00:00',
        ]);

        return [$administrator, $tahun, $kelas, $siswa, $sanksi];
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
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', $kodePeran)->firstOrFail());

        return [$pegawai, $pengguna];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Pelaksanaan Sanksi', ['mobile'])->plainTextToken;
    }
}
