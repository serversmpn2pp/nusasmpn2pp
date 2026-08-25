<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KenaikanKelasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_kenaikan_kelas_memerlukan_token_dan_izin_kelola(): void
    {
        $this->getJson(route('api.v1.kenaikan-kelas.index'))->assertUnauthorized();

        $pengelola = $this->penggunaDenganIzin('kenaikan_kelas.kelola');
        $this->withToken($this->token($pengelola))
            ->getJson(route('api.v1.kenaikan-kelas.index'))
            ->assertOk();
    }

    public function test_pengguna_tanpa_izin_tidak_dapat_mengakses_kenaikan_kelas(): void
    {
        $pengguna = $this->buatPengguna('Tanpa Izin Kenaikan', 'tanpa.izin.kenaikan');

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.kenaikan-kelas.index'))
            ->assertForbidden();
    }

    public function test_referensi_memuat_tahun_dan_kelas_asal(): void
    {
        [$tahunAsal, , $kelasAsal] = $this->buatDataKenaikan();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.kenaikan-kelas.index', [
                'tahun_asal_id' => $tahunAsal->id,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.tahun_pelajaran')
            ->assertJsonCount(1, 'data.kelas_asal')
            ->assertJsonPath('data.kelas_asal.0.id', $kelasAsal->id)
            ->assertJsonPath('data.kelas_asal.0.jumlah_siswa', 2)
            ->assertJsonPath('data.filter.tahun_asal_id', $tahunAsal->id)
            ->assertJsonPath('data.filter.tahun_tujuan_id', null)
            ->assertJsonPath('data.siap_diproses', false);
    }

    public function test_preview_memuat_saran_kelas_dan_penempatan_yang_sudah_ada(): void
    {
        [$tahunAsal, $tahunTujuan, $kelasAsal, $kelasTujuanA, $kelasTujuanB, $anggotaA, $anggotaB] = $this->buatDataKenaikan();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahunTujuan->id,
            'kelas_id' => $kelasTujuanB->id,
            'siswa_id' => $anggotaA->siswa_id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahunTujuan->tanggal_mulai,
            'keterangan' => 'Penempatan sebelumnya',
        ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.kenaikan-kelas.index', [
                'tahun_asal_id' => $tahunAsal->id,
                'tahun_tujuan_id' => $tahunTujuan->id,
                'kelas_asal_id' => $kelasAsal->id,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.kelas_tujuan')
            ->assertJsonCount(2, 'data.anggota')
            ->assertJsonPath('data.saran_kelas_tujuan_id', $kelasTujuanA->id)
            ->assertJsonPath('data.anggota.0.id', $anggotaA->id)
            ->assertJsonPath('data.anggota.0.penempatan_tujuan.kelas.id', $kelasTujuanB->id)
            ->assertJsonPath('data.anggota.0.kelas_tujuan_disarankan_id', $kelasTujuanB->id)
            ->assertJsonPath('data.anggota.1.id', $anggotaB->id)
            ->assertJsonPath('data.anggota.1.penempatan_tujuan', null)
            ->assertJsonPath('data.anggota.1.kelas_tujuan_disarankan_id', $kelasTujuanA->id)
            ->assertJsonPath('data.ringkasan.sudah_ditempatkan', 1)
            ->assertJsonPath('data.ringkasan.belum_ditempatkan', 1)
            ->assertJsonPath('data.siap_diproses', true);
    }

    public function test_proses_mencegah_kapasitas_berlebih_dan_aman_dijalankan_ulang(): void
    {
        [$tahunAsal, $tahunTujuan, $kelasAsal, $kelasTujuanA, , $anggotaA, $anggotaB] = $this->buatDataKenaikan();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);
        $payload = [
            'tahun_asal_id' => $tahunAsal->id,
            'tahun_tujuan_id' => $tahunTujuan->id,
            'kelas_asal_id' => $kelasAsal->id,
            'penempatan' => [
                [
                    'anggota_kelas_id' => $anggotaA->id,
                    'kelas_tujuan_id' => $kelasTujuanA->id,
                    'keterangan' => 'Naik kelas melalui mobile',
                ],
                [
                    'anggota_kelas_id' => $anggotaB->id,
                    'kelas_tujuan_id' => $kelasTujuanA->id,
                    'keterangan' => 'Naik kelas melalui mobile',
                ],
            ],
        ];

        $this->withToken($token)
            ->postJson(route('api.v1.kenaikan-kelas.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.diproses', 2)
            ->assertJsonPath('data.ditempatkan', 1)
            ->assertJsonPath('data.dilewati', 1)
            ->assertJsonPath('data.catatan.0', 'Siswa B Kenaikan: kelas VIII.A sudah penuh.');

        $this->assertDatabaseHas('anggota_kelas', [
            'tahun_pelajaran_id' => $tahunTujuan->id,
            'kelas_id' => $kelasTujuanA->id,
            'siswa_id' => $anggotaA->siswa_id,
            'nomor_absen' => 1,
            'keterangan' => 'Naik kelas melalui mobile',
        ]);
        $this->assertDatabaseMissing('anggota_kelas', [
            'tahun_pelajaran_id' => $tahunTujuan->id,
            'siswa_id' => $anggotaB->siswa_id,
        ]);

        $payload['penempatan'] = [$payload['penempatan'][0]];
        $this->withToken($token)
            ->postJson(route('api.v1.kenaikan-kelas.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.diproses', 1)
            ->assertJsonPath('data.ditempatkan', 1)
            ->assertJsonPath('data.dilewati', 0);
        $this->assertSame(
            1,
            AnggotaKelas::where('tahun_pelajaran_id', $tahunTujuan->id)
                ->where('siswa_id', $anggotaA->siswa_id)
                ->count(),
        );
    }

    public function test_kelas_asal_dan_tahun_tujuan_divalidasi_dalam_konteks(): void
    {
        [$tahunAsal, $tahunTujuan, , $kelasTujuanA] = $this->buatDataKenaikan();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $this->withToken($token)
            ->getJson(route('api.v1.kenaikan-kelas.index', [
                'tahun_asal_id' => $tahunAsal->id,
                'tahun_tujuan_id' => $tahunAsal->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tahun_tujuan_id');

        $this->withToken($token)
            ->getJson(route('api.v1.kenaikan-kelas.index', [
                'tahun_asal_id' => $tahunAsal->id,
                'tahun_tujuan_id' => $tahunTujuan->id,
                'kelas_asal_id' => $kelasTujuanA->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kelas_asal_id');
    }

    public function test_form_web_lama_tetap_memakai_proses_kenaikan_yang_sama(): void
    {
        [$tahunAsal, $tahunTujuan, $kelasAsal, , $kelasTujuanB, $anggotaA] = $this->buatDataKenaikan();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('kenaikan-kelas.store'), [
                'tahun_asal_id' => $tahunAsal->id,
                'tahun_tujuan_id' => $tahunTujuan->id,
                'kelas_asal_id' => $kelasAsal->id,
                'tujuan' => [$anggotaA->id => $kelasTujuanB->id],
                'keterangan' => [$anggotaA->id => 'Naik kelas melalui web'],
            ])
            ->assertRedirect(route('kenaikan-kelas.index', [
                'tahun_asal_id' => $tahunAsal->id,
                'tahun_tujuan_id' => $tahunTujuan->id,
                'kelas_asal_id' => $kelasAsal->id,
            ]))
            ->assertSessionHas('ringkasan_kenaikan.ditempatkan', 1);

        $this->assertDatabaseHas('anggota_kelas', [
            'tahun_pelajaran_id' => $tahunTujuan->id,
            'kelas_id' => $kelasTujuanB->id,
            'siswa_id' => $anggotaA->siswa_id,
            'keterangan' => 'Naik kelas melalui web',
        ]);
    }

    private function buatDataKenaikan(): array
    {
        $tahunAsal = TahunPelajaran::create([
            'nama' => '2025/2026 Uji Kenaikan',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'aktif' => false,
        ]);
        $tahunTujuan = TahunPelajaran::create([
            'nama' => '2026/2027 Uji Kenaikan',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelasAsal = $this->buatKelas($tahunAsal, 'VII.A', 7, 32);
        $kelasTujuanA = $this->buatKelas($tahunTujuan, 'VIII.A', 8, 1);
        $kelasTujuanB = $this->buatKelas($tahunTujuan, 'VIII.B', 8, 32);
        $anggotaA = $this->buatAnggota($tahunAsal, $kelasAsal, 'Siswa A Kenaikan', '2610001');
        $anggotaB = $this->buatAnggota($tahunAsal, $kelasAsal, 'Siswa B Kenaikan', '2610002');

        return [
            $tahunAsal,
            $tahunTujuan,
            $kelasAsal,
            $kelasTujuanA,
            $kelasTujuanB,
            $anggotaA,
            $anggotaB,
        ];
    }

    private function buatKelas(
        TahunPelajaran $tahun,
        string $nama,
        int $tingkat,
        int $kapasitas,
    ): Kelas {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => $nama,
            'tingkat' => $tingkat,
            'kapasitas' => $kapasitas,
            'aktif' => true,
        ]);
    }

    private function buatAnggota(
        TahunPelajaran $tahun,
        Kelas $kelas,
        string $nama,
        string $nis,
    ): AnggotaKelas {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => $nis,
            'nisn' => '00'.$nis,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        return AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahun->tanggal_mulai,
            'keterangan' => 'Anggota kelas asal',
        ]);
    }

    private function buatPengguna(string $nama, string $username): Pengguna
    {
        return Pengguna::create([
            'nama' => $nama,
            'username' => $username,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pengelola Kenaikan Kelas API',
            'kode' => 'pengelola_kenaikan_kelas_api',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = $this->buatPengguna(
            'Pengelola Kenaikan Kelas API',
            'pengelola.kenaikan.kelas.api',
        );
        $pengguna->daftarPeran()->attach($peran);

        return $pengguna;
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
