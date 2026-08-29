<?php

namespace Tests\Feature\Api;

use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanPendampingIbadahSiswi;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanBerhalanganIbadahApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_pengaturan_berhalangan_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.pengaturan-berhalangan-ibadah.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Guru Tanpa Izin Berhalangan',
            'username' => 'guru.tanpa.izin.berhalangan',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.pengaturan-berhalangan-ibadah.index'))
            ->assertForbidden();
    }

    public function test_daftar_hanya_memuat_guru_perempuan_aktif_dan_ringkasan_cakupan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $kelas] = $this->buatTahunDanKelas();
        $guruPerempuan = $this->buatPegawai('Guru Perempuan Mobile', 'P', 'Guru');
        $guruLakiLaki = $this->buatPegawai('Guru Laki Mobile', 'L', 'Guru');
        $tenagaKependidikan = $this->buatPegawai('Tendik Perempuan Mobile', 'P', 'Tenaga Kependidikan');
        $penugasan = PenugasanPendampingIbadahSiswi::create([
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $guruPerempuan->id,
            'semua_kelas' => false,
            'aktif' => true,
            'ditugaskan_oleh_pengguna_id' => $administrator->id,
        ]);
        $penugasan->kelas()->sync([$kelas->first()->id]);

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pengaturan-berhalangan-ibadah.index'))
            ->assertOk()
            ->assertJsonPath('data.tersedia', true)
            ->assertJsonPath('data.tahun_pelajaran.id', $tahun->id)
            ->assertJsonPath('data.pengaturan.batas_hari_konfirmasi', 7)
            ->assertJsonPath('data.pengaturan.aktif', true)
            ->assertJsonPath('data.ringkasan.pendamping_aktif', 1)
            ->assertJsonPath('data.ringkasan.kelas_tercakup', 1)
            ->assertJsonPath('data.ringkasan.jumlah_kelas', 2)
            ->assertJsonCount(1, 'data.referensi.pegawai_perempuan')
            ->assertJsonPath('data.referensi.pegawai_perempuan.0.nama', 'Guru Perempuan Mobile')
            ->assertJsonCount(2, 'data.referensi.kelas')
            ->assertJsonCount(1, 'data.penugasan');

        $response->assertJsonMissing(['nama' => $guruLakiLaki->nama_lengkap]);
        $response->assertJsonMissing(['nama' => $tenagaKependidikan->nama_lengkap]);
    }

    public function test_administrator_dapat_menyimpan_batas_konfirmasi(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun] = $this->buatTahunDanKelas();

        $this->withToken($this->token($administrator))
            ->putJson(route('api.v1.pengaturan-berhalangan-ibadah.update'), [
                'batas_hari_konfirmasi' => 6,
                'aktif' => true,
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Pengaturan berhalangan berhasil disimpan.');

        $this->assertDatabaseHas('pengaturan_berhalangan_ibadah', [
            'tahun_pelajaran_id' => $tahun->id,
            'batas_hari_konfirmasi' => 6,
            'aktif' => true,
            'diperbarui_oleh_pengguna_id' => $administrator->id,
        ]);

        $this->withToken($this->token($administrator))
            ->putJson(route('api.v1.pengaturan-berhalangan-ibadah.update'), [
                'batas_hari_konfirmasi' => 31,
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('batas_hari_konfirmasi');
    }

    public function test_administrator_dapat_menyimpan_dan_mengatur_ulang_pendamping(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $kelas] = $this->buatTahunDanKelas();
        $guru = $this->buatPegawai('Guru Pendamping Mobile', 'P', 'Guru');
        $token = $this->token($administrator);

        $this->withToken($token)
            ->postJson(route('api.v1.pengaturan-berhalangan-ibadah.pendamping.store'), [
                'pegawai_id' => $guru->id,
                'semua_kelas' => false,
                'kelas_ids' => $kelas->pluck('id')->all(),
            ])
            ->assertCreated()
            ->assertJsonPath('pesan', 'Pendamping ibadah siswi berhasil disimpan.');

        $penugasan = PenugasanPendampingIbadahSiswi::firstOrFail();
        $this->assertFalse($penugasan->semua_kelas);
        $this->assertEqualsCanonicalizing(
            $kelas->pluck('id')->all(),
            $penugasan->kelas()->pluck('kelas.id')->all(),
        );

        $this->withToken($token)
            ->postJson(route('api.v1.pengaturan-berhalangan-ibadah.pendamping.store'), [
                'pegawai_id' => $guru->id,
                'semua_kelas' => true,
                'kelas_ids' => [],
            ])
            ->assertCreated();

        $this->assertDatabaseCount('penugasan_pendamping_ibadah_siswi', 1);
        $this->assertTrue($penugasan->fresh()->semua_kelas);
        $this->assertDatabaseMissing('kelas_pendamping_ibadah_siswi', [
            'penugasan_pendamping_ibadah_siswi_id' => $penugasan->id,
        ]);
        $this->assertSame($tahun->id, $penugasan->tahun_pelajaran_id);
    }

    public function test_guru_tidak_sesuai_dan_cakupan_kosong_ditolak(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatTahunDanKelas();
        $guruLakiLaki = $this->buatPegawai('Guru Laki Ditolak Mobile', 'L', 'Guru');
        $guruPerempuan = $this->buatPegawai('Guru Perempuan Cakupan Mobile', 'P', 'Guru');
        $token = $this->token($administrator);

        $this->withToken($token)
            ->postJson(route('api.v1.pengaturan-berhalangan-ibadah.pendamping.store'), [
                'pegawai_id' => $guruLakiLaki->id,
                'semua_kelas' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pegawai_id');

        $this->withToken($token)
            ->postJson(route('api.v1.pengaturan-berhalangan-ibadah.pendamping.store'), [
                'pegawai_id' => $guruPerempuan->id,
                'semua_kelas' => false,
                'kelas_ids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kelas_ids');
    }

    public function test_pendamping_dinonaktifkan_tanpa_menghapus_riwayat_kelas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $kelas] = $this->buatTahunDanKelas();
        $guru = $this->buatPegawai('Guru Riwayat Mobile', 'P', 'Guru');
        $penugasan = PenugasanPendampingIbadahSiswi::create([
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $guru->id,
            'semua_kelas' => false,
            'aktif' => true,
            'ditugaskan_oleh_pengguna_id' => $administrator->id,
        ]);
        $penugasan->kelas()->sync([$kelas->first()->id]);

        $this->withToken($this->token($administrator))
            ->deleteJson(route(
                'api.v1.pengaturan-berhalangan-ibadah.pendamping.destroy',
                $penugasan,
            ))
            ->assertOk()
            ->assertJsonPath(
                'pesan',
                'Pendamping telah dinonaktifkan. Riwayat penugasannya tetap tersimpan.',
            );

        $this->assertDatabaseHas('penugasan_pendamping_ibadah_siswi', [
            'id' => $penugasan->id,
            'aktif' => false,
        ]);
        $this->assertDatabaseHas('kelas_pendamping_ibadah_siswi', [
            'penugasan_pendamping_ibadah_siswi_id' => $penugasan->id,
            'kelas_id' => $kelas->first()->id,
        ]);
    }

    private function buatTahunDanKelas(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2035/2036',
            'tanggal_mulai' => '2035-07-01',
            'tanggal_selesai' => '2036-06-30',
            'aktif' => true,
        ]);
        $kelas = collect([
            Kelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'VII.A Mobile',
                'tingkat' => 7,
                'kapasitas' => 32,
                'aktif' => true,
            ]),
            Kelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'VIII.A Mobile',
                'tingkat' => 8,
                'kapasitas' => 32,
                'aktif' => true,
            ]),
        ]);

        return [$tahun, $kelas];
    }

    private function buatPegawai(string $nama, string $jenisKelamin, string $jenisPegawai): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => (string) random_int(100000000000000000, 999999999999999999),
            'jenis_kelamin' => $jenisKelamin,
            'jenis_pegawai' => $jenisPegawai,
            'aktif' => true,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
