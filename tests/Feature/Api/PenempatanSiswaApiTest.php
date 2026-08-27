<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenempatanSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_penempatan_siswa_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.penempatan-siswa.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Pengguna Tanpa Izin Kelas',
            'username' => 'tanpa.izin.penempatan',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.penempatan-siswa.index'))
            ->assertForbidden();
    }

    public function test_administrator_melihat_kelas_anggota_dan_siswa_yang_belum_ditempatkan(): void
    {
        $data = $this->dataPenempatan();

        $this->withToken($this->token($data['administrator']))
            ->getJson(route('api.v1.penempatan-siswa.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas']->id,
                'cari' => 'calon mobile',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.tahun_pelajaran_id', $data['tahun']->id)
            ->assertJsonPath('data.filter.kelas_id', $data['kelas']->id)
            ->assertJsonPath('data.kelas_dipilih.nama', 'VII.P Mobile')
            ->assertJsonPath('data.kelas_dipilih.jumlah_anggota', 1)
            ->assertJsonPath('data.kelas_dipilih.sisa_kursi', 2)
            ->assertJsonPath('data.anggota.0.siswa.nama', 'Anggota Mobile Lama')
            ->assertJsonCount(2, 'data.siswa_tersedia')
            ->assertJsonFragment(['nama' => 'Calon Mobile Satu'])
            ->assertJsonMissing(['nama' => 'Sudah Di Kelas Lain Mobile'])
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonStructure([
                'data' => [
                    'ringkasan' => ['siswa_aktif', 'ditempatkan', 'belum_ditempatkan'],
                    'tahun_pelajaran',
                    'kelas',
                ],
            ]);
    }

    public function test_administrator_dapat_menempatkan_massal_sesuai_kapasitas(): void
    {
        $data = $this->dataPenempatan();
        $token = $this->token($data['administrator']);

        $this->withToken($token)
            ->postJson(route('api.v1.penempatan-siswa.store'), [
                'kelas_id' => $data['kelas']->id,
                'siswa_ids' => [$data['calon_satu']->id, $data['calon_dua']->id],
                'tanggal_masuk' => '2028-07-15',
                'keterangan' => 'Penempatan massal dari pengujian mobile',
            ])
            ->assertCreated()
            ->assertJsonPath('data.jumlah_ditempatkan', 2);

        $this->assertDatabaseHas('anggota_kelas', [
            'kelas_id' => $data['kelas']->id,
            'siswa_id' => $data['calon_satu']->id,
            'tanggal_masuk' => '2028-07-15 00:00:00',
            'keterangan' => 'Penempatan massal dari pengujian mobile',
        ]);
        $this->assertSame(3, $data['kelas']->anggotaKelas()->count());

        $calonTambahan = $this->siswa('Calon Tambahan Mobile', '991000006');
        $this->withToken($token)
            ->postJson(route('api.v1.penempatan-siswa.store'), [
                'kelas_id' => $data['kelas']->id,
                'siswa_ids' => [$calonTambahan->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('siswa_ids');
    }

    public function test_siswa_tidak_dapat_ditempatkan_di_dua_kelas_pada_tahun_yang_sama(): void
    {
        $data = $this->dataPenempatan();

        $this->withToken($this->token($data['administrator']))
            ->postJson(route('api.v1.penempatan-siswa.store'), [
                'kelas_id' => $data['kelas']->id,
                'siswa_ids' => [$data['sudah_lain']->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('siswa_ids');

        $this->assertSame(
            1,
            AnggotaKelas::where('siswa_id', $data['sudah_lain']->id)
                ->where('tahun_pelajaran_id', $data['tahun']->id)
                ->count(),
        );
    }

    private function dataPenempatan(): array
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2028/2029 Penempatan Mobile',
            'tanggal_mulai' => '2028-07-15',
            'tanggal_selesai' => '2029-06-20',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.P Mobile',
            'tingkat' => 7,
            'kapasitas' => 3,
            'aktif' => true,
        ]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.Q Mobile',
            'tingkat' => 7,
            'kapasitas' => 30,
            'aktif' => true,
        ]);
        $anggotaLama = $this->siswa('Anggota Mobile Lama', '991000001');
        $calonSatu = $this->siswa('Calon Mobile Satu', '991000002');
        $calonDua = $this->siswa('Calon Mobile Dua', '991000003');
        $sudahLain = $this->siswa('Sudah Di Kelas Lain Mobile', '991000004');
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $anggotaLama->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2028-07-15',
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasLain->id,
            'siswa_id' => $sudahLain->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2028-07-15',
        ]);

        return compact(
            'administrator',
            'tahun',
            'kelas',
            'kelasLain',
            'anggotaLama',
            'calonSatu',
            'calonDua',
            'sudahLain',
        ) + [
            'calon_satu' => $calonSatu,
            'calon_dua' => $calonDua,
            'sudah_lain' => $sudahLain,
        ];
    }

    private function siswa(string $nama, string $nisn): Siswa
    {
        return Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => $nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
