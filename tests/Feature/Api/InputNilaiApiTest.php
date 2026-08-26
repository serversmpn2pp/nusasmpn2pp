<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiNilaiSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputNilaiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_memerlukan_token_dan_izin_input_nilai(): void
    {
        $this->getJson(route('api.v1.input-nilai.index'))->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Tanpa Izin Nilai',
            'username' => 'tanpa.izin.nilai',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.input-nilai.index'))
            ->assertForbidden();
    }

    public function test_daftar_input_nilai_memuat_siswa_nilai_ringkasan_dan_publikasi(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $data = $this->dataAkademik();
        NilaiSiswa::create([
            'komponen_nilai_id' => $data['komponen']->id,
            'siswa_id' => $data['siswa_1']->id,
            'nilai' => 87.5,
            'catatan' => 'Baik',
        ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.input-nilai.index', [
                'guru_mata_pelajaran_id' => $data['penugasan']->id,
                'semester' => 'ganjil',
                'komponen_nilai_id' => $data['komponen']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.guru_mata_pelajaran_id', $data['penugasan']->id)
            ->assertJsonPath('data.filter.komponen_nilai_id', $data['komponen']->id)
            ->assertJsonPath('data.mode_penilaian', 'angka')
            ->assertJsonPath('data.siswa.0.siswa.nama', 'Alya Nilai Mobile')
            ->assertJsonPath('data.siswa.0.nilai', 87.5)
            ->assertJsonPath('data.ringkasan.jumlah_siswa', 2)
            ->assertJsonPath('data.ringkasan.jumlah_terisi', 1)
            ->assertJsonPath('data.ringkasan.jumlah_belum_terisi', 1)
            ->assertJsonPath('data.publikasi.jumlah_komponen', 1)
            ->assertJsonPath('data.publikasi.jumlah_nilai', 1)
            ->assertJsonPath('data.publikasi.target_nilai', 2)
            ->assertJsonPath('data.publikasi.dapat_dipublikasikan', true)
            ->assertJsonPath('data.hak_akses.dapat_input', true);
    }

    public function test_simpan_nilai_mengubah_publikasi_menjadi_draf_lalu_dapat_dipublikasikan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $data = $this->dataAkademik();
        $publikasi = PublikasiNilaiSiswa::create([
            'guru_mata_pelajaran_id' => $data['penugasan']->id,
            'semester' => 'ganjil',
            'dipublikasikan' => true,
            'dipublikasikan_pada' => now(),
            'dipublikasikan_oleh_pengguna_id' => $administrator->id,
        ]);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->postJson(route('api.v1.input-nilai.store'), [
                'komponen_nilai_id' => $data['komponen']->id,
                'nilai' => [
                    (string) $data['siswa_1']->id => 91.25,
                    (string) $data['siswa_2']->id => 78,
                ],
                'catatan' => [
                    (string) $data['siswa_1']->id => 'Sangat baik',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.publikasi_dibatalkan', true);

        $this->assertFalse($publikasi->fresh()->dipublikasikan);
        $this->assertDatabaseHas('nilai_siswa', [
            'komponen_nilai_id' => $data['komponen']->id,
            'siswa_id' => $data['siswa_1']->id,
            'nilai' => 91.25,
            'catatan' => 'Sangat baik',
        ]);

        $this->withToken($token)
            ->patchJson(route('api.v1.input-nilai.publikasikan', [
                'guruMataPelajaran' => $data['penugasan'],
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertJsonPath('data.dipublikasikan', true);
        $this->assertTrue($publikasi->fresh()->dipublikasikan);

        $this->withToken($token)
            ->patchJson(route('api.v1.input-nilai.jadikan-draf', [
                'guruMataPelajaran' => $data['penugasan'],
                'semester' => 'ganjil',
            ]))
            ->assertOk();
        $this->assertFalse($publikasi->fresh()->dipublikasikan);
    }

    public function test_predikat_divalidasi_dan_guru_hanya_dapat_mengakses_penugasan_sendiri(): void
    {
        $data = $this->dataAkademik();
        $ekskul = MataPelajaran::create([
            'kode' => 'PD-MOBILE',
            'nama' => 'Pengembangan Diri Mobile',
            'kelompok' => 'Pengembangan Diri',
            'aktif' => true,
        ]);
        $penugasanPredikat = $this->buatPenugasan(
            $data['tahun'],
            $data['kelas'],
            $ekskul,
            $data['guru'],
        );
        $komponenPredikat = $this->buatKomponen($penugasanPredikat, 'ganjil', 'formatif', 'Sikap');
        $guru = Pengguna::create([
            'pegawai_id' => $data['guru']->id,
            'nama' => 'Guru Nilai Mobile',
            'username' => 'guru.nilai.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $guru->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());
        $this->app['auth']->forgetGuards();
        $token = $this->token($guru);

        $this->withToken($token)
            ->getJson(route('api.v1.input-nilai.index', [
                'guru_mata_pelajaran_id' => $data['penugasan_lain']->id,
            ]))
            ->assertNotFound();

        $this->withToken($token)
            ->postJson(route('api.v1.input-nilai.store'), [
                'komponen_nilai_id' => $komponenPredikat->id,
                'predikat' => [(string) $data['siswa_1']->id => 'A'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('predikat.'.$data['siswa_1']->id);

        $this->withToken($token)
            ->postJson(route('api.v1.input-nilai.store'), [
                'komponen_nilai_id' => $komponenPredikat->id,
                'predikat' => [(string) $data['siswa_1']->id => 'SB'],
            ])
            ->assertOk();
        $this->assertDatabaseHas('nilai_siswa', [
            'komponen_nilai_id' => $komponenPredikat->id,
            'siswa_id' => $data['siswa_1']->id,
            'predikat' => 'SB',
            'nilai' => null,
        ]);
    }

    private function dataAkademik(): array
    {
        $tahun = TahunPelajaran::create(['nama' => '2026/2027 Nilai', 'aktif' => true]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.NILAI',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.NILAI.LAIN',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $guru = $this->buatPegawai('Guru Nilai Mobile', '198101012026081071');
        $guruLain = $this->buatPegawai('Guru Nilai Lain', '198101012026081072');
        $matematika = MataPelajaran::create([
            'kode' => 'MTK-NILAI',
            'nama' => 'Matematika Nilai Mobile',
            'kelompok' => 'Wajib',
            'aktif' => true,
        ]);
        $penugasan = $this->buatPenugasan($tahun, $kelas, $matematika, $guru);
        $penugasanLain = $this->buatPenugasan($tahun, $kelasLain, $matematika, $guruLain);
        $komponen = $this->buatKomponen($penugasan, 'ganjil', 'formatif', 'Kuis Mobile');
        $siswa1 = $this->buatSiswa('Alya Nilai Mobile', '20260071', '0011223371');
        $siswa2 = $this->buatSiswa('Bima Nilai Mobile', '20260072', '0011223372');
        foreach ([[$siswa1, 1], [$siswa2, 2]] as [$siswa, $absen]) {
            AnggotaKelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $absen,
                'status_keanggotaan' => 'aktif',
            ]);
        }

        return compact('tahun', 'kelas', 'guru', 'penugasan', 'komponen') + [
            'penugasan_lain' => $penugasanLain,
            'siswa_1' => $siswa1,
            'siswa_2' => $siswa2,
        ];
    }

    private function buatPegawai(string $nama, string $nip): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
    }

    private function buatPenugasan(
        TahunPelajaran $tahun,
        Kelas $kelas,
        MataPelajaran $mataPelajaran,
        Pegawai $guru,
    ): GuruMataPelajaran {
        return GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
    }

    private function buatKomponen(
        GuruMataPelajaran $penugasan,
        string $semester,
        string $jenis,
        string $nama,
    ): KomponenNilai {
        return KomponenNilai::create([
            'guru_mata_pelajaran_id' => $penugasan->id,
            'semester' => $semester,
            'jenis_komponen' => $jenis,
            'nama' => $nama,
            'urutan' => 1,
            'aktif' => true,
        ]);
    }

    private function buatSiswa(string $nama, string $nis, string $nisn): Siswa
    {
        return Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => $nis,
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
