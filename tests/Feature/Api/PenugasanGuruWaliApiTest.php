<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenugasanGuruWaliApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_ringkasan_referensi_filter_dan_menu_native_tersedia(): void
    {
        [$administrator, , $kelas, $siswaA, , $guru] = $this->dataDasar();
        $penugasan = PenugasanGuruWaliSiswa::create([
            'siswa_id' => $siswaA->id,
            'guru_wali_pegawai_id' => $guru->id,
            'tanggal_mulai' => '2026-07-15',
            'nomor_sk' => 'SK/GW/001',
            'aktif' => true,
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        $token = $this->token($administrator);

        $this->getJson(route('api.v1.penugasan-guru-wali.index'))->assertUnauthorized();
        $response = $this->withToken($token)
            ->getJson(route('api.v1.penugasan-guru-wali.index', [
                'kata_kunci' => 'native',
                'guru_wali_pegawai_id' => $guru->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.jumlah_siswa_aktif', 2)
            ->assertJsonPath('data.ringkasan.jumlah_ditugaskan', 1)
            ->assertJsonPath('data.ringkasan.jumlah_belum_ditugaskan', 1)
            ->assertJsonPath('data.ringkasan.jumlah_guru_wali', 1)
            ->assertJsonPath('data.items.0.id', $penugasan->id)
            ->assertJsonPath('data.items.0.siswa.id', $siswaA->id)
            ->assertJsonPath('data.items.0.kelas.nama', $kelas->nama)
            ->assertJsonPath('data.items.0.guru_wali.id', $guru->id)
            ->assertJsonPath('data.items.0.nomor_sk', 'SK/GW/001')
            ->assertJsonPath('data.pilihan.siswa.0.penugasan_aktif.id', $penugasan->id)
            ->assertJsonPath('data.pilihan.pegawai.0.jumlah_siswa_aktif', 1)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'penugasan-guru-wali',
                'status' => 'tersedia',
                'rute' => '/penugasan-guru-wali',
            ]);
    }

    public function test_penugasan_massal_memindahkan_siswa_menyimpan_riwayat_dan_memasang_role(): void
    {
        [$administrator, , , $siswaA, $siswaB, $guruBaru, $akunGuruBaru, $guruLama] = $this->dataDasar();
        $lama = PenugasanGuruWaliSiswa::create([
            'siswa_id' => $siswaB->id,
            'guru_wali_pegawai_id' => $guruLama->id,
            'tanggal_mulai' => '2026-07-01',
            'aktif' => true,
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        $token = $this->token($administrator);
        $payload = [
            'guru_wali_pegawai_id' => $guruBaru->id,
            'siswa_ids' => [$siswaA->id, $siswaB->id],
            'tanggal_mulai' => '2026-09-01',
            'nomor_sk' => 'SK/GW/NATIVE/002',
            'catatan' => 'Pendampingan lintas kelas.',
        ];

        $this->withToken($token)
            ->postJson(route('api.v1.penugasan-guru-wali.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.baru', 1)
            ->assertJsonPath('data.dipindahkan', 1)
            ->assertJsonPath('data.tetap', 0);

        $this->assertFalse($lama->fresh()->aktif);
        $this->assertSame('2026-09-01', $lama->fresh()->tanggal_selesai?->toDateString());
        $this->assertSame(2, PenugasanGuruWaliSiswa::where('guru_wali_pegawai_id', $guruBaru->id)->where('aktif', true)->count());
        $this->assertDatabaseHas('penugasan_guru_wali_siswa', [
            'siswa_id' => $siswaB->id,
            'guru_wali_pegawai_id' => $guruBaru->id,
            'nomor_sk' => 'SK/GW/NATIVE/002',
            'catatan' => 'Pendampingan lintas kelas.',
            'aktif' => true,
        ]);
        $this->assertTrue($akunGuruBaru->fresh()->memilikiPeran('guru_wali'));

        $this->withToken($token)
            ->postJson(route('api.v1.penugasan-guru-wali.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.baru', 0)
            ->assertJsonPath('data.dipindahkan', 0)
            ->assertJsonPath('data.tetap', 2);
        $this->assertSame(2, PenugasanGuruWaliSiswa::where('guru_wali_pegawai_id', $guruBaru->id)->where('aktif', true)->count());
    }

    public function test_penugasan_aktif_dapat_diakhiri_satu_kali(): void
    {
        [$administrator, , , $siswaA, , $guru] = $this->dataDasar();
        $penugasan = PenugasanGuruWaliSiswa::create([
            'siswa_id' => $siswaA->id,
            'guru_wali_pegawai_id' => $guru->id,
            'tanggal_mulai' => '2026-08-01',
            'aktif' => true,
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->deleteJson(route('api.v1.penugasan-guru-wali.destroy', $penugasan))
            ->assertOk()
            ->assertJsonPath('data.aktif', false)
            ->assertJsonPath('message', 'Penugasan Guru Wali berhasil diakhiri.');
        $this->assertFalse($penugasan->fresh()->aktif);
        $this->assertSame(now()->toDateString(), $penugasan->fresh()->tanggal_selesai?->toDateString());

        $this->withToken($token)
            ->deleteJson(route('api.v1.penugasan-guru-wali.destroy', $penugasan))
            ->assertUnprocessable();
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
            'nama' => 'VIII.GW',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswaA = $this->buatSiswa($tahun, $kelas, 'Siswa Guru Wali Native A', '0099776601');
        $siswaB = $this->buatSiswa($tahun, $kelas, 'Siswa Guru Wali Native B', '0099776602');
        $guruBaru = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Native Baru',
            'nip' => '198201012026091001',
            'jabatan_utama' => 'Guru Mata Pelajaran',
            'aktif' => true,
        ]);
        $akunGuruBaru = Pengguna::create([
            'pegawai_id' => $guruBaru->id,
            'nama' => $guruBaru->nama_lengkap,
            'username' => $guruBaru->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $guruLama = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Native Lama',
            'nip' => '198101012026091002',
            'aktif' => true,
        ]);

        return [$administrator, $tahun, $kelas, $siswaA, $siswaB, $guruBaru, $akunGuruBaru, $guruLama];
    }

    private function buatSiswa(TahunPelajaran $tahun, Kelas $kelas, string $nama, string $nisn): Siswa
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => 'NIS-'.$nisn,
            'nisn' => $nisn,
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahun->tanggal_mulai,
        ]);

        return $siswa;
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Penugasan Guru Wali', ['mobile'])->plainTextToken;
    }
}
