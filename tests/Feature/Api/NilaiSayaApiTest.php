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
use App\Models\PertanyaanSurveiPembelajaran;
use App\Models\PublikasiNilaiSiswa;
use App\Models\Siswa;
use App\Models\SkemaBobotNilai;
use App\Models\SurveiPembelajaran;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NilaiSayaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_memerlukan_token_dan_hanya_dapat_diakses_akun_siswa(): void
    {
        $this->getJson(route('api.v1.nilai-saya.index'))->assertUnauthorized();

        $pegawai = Pengguna::create([
            'nama' => 'Pegawai Bukan Siswa',
            'username' => 'pegawai.bukan.siswa',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pegawai))
            ->getJson(route('api.v1.nilai-saya.index'))
            ->assertForbidden();
    }

    public function test_siswa_melihat_nilai_miliknya_yang_dipublikasikan_setelah_survei_diisi(): void
    {
        $data = $this->dataAkademik();
        $this->publikasikan($data, true);
        SurveiPembelajaran::create([
            'guru_mata_pelajaran_id' => $data['penugasan']->id,
            'siswa_id' => $data['siswa_1']->id,
            'semester' => 'ganjil',
            'versi_pertanyaan' => 1,
            'jawaban' => ['kejelasan_materi' => 4],
            'diisi_pada' => now(),
        ]);

        $this->withToken($this->token($data['akun_siswa_1']))
            ->getJson(route('api.v1.nilai-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertJsonPath('data.siswa.nama', 'Alya Nilai Saya')
            ->assertJsonPath('data.kelas.nama', 'VIII.NILAI.SAYA')
            ->assertJsonPath('data.filter.tahun_pelajaran_id', $data['tahun']->id)
            ->assertJsonPath('data.filter.semester', 'ganjil')
            ->assertJsonPath('data.ringkasan.mata_pelajaran', 1)
            ->assertJsonPath('data.ringkasan.nilai_terbuka', 1)
            ->assertJsonPath('data.mata_pelajaran.0.mata_pelajaran.nama', 'Matematika Nilai Saya')
            ->assertJsonPath('data.mata_pelajaran.0.terbuka', true)
            ->assertJsonPath('data.mata_pelajaran.0.nilai_akhir', 85)
            ->assertJsonPath('data.mata_pelajaran.0.kkm', 75)
            ->assertJsonPath('data.mata_pelajaran.0.tuntas', true)
            ->assertJsonPath('data.mata_pelajaran.0.status', 'tuntas')
            ->assertJsonPath('data.mata_pelajaran.0.kategori.0.rata_rata', 80)
            ->assertJsonPath('data.mata_pelajaran.0.komponen.0.nilai', 80)
            ->assertJsonMissing(['nama' => 'Ilmu Pengetahuan Alam Draf'])
            ->assertJsonMissing(['nilai' => 17]);
    }

    public function test_nilai_terkunci_tidak_membocorkan_rincian_sebelum_survei_diisi(): void
    {
        $data = $this->dataAkademik();
        $this->publikasikan($data, true);

        $this->withToken($this->token($data['akun_siswa_2']))
            ->getJson(route('api.v1.nilai-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertJsonPath('data.siswa.nama', 'Bima Nilai Saya')
            ->assertJsonPath('data.ringkasan.nilai_terbuka', 0)
            ->assertJsonPath('data.ringkasan.survei_belum_diisi', 1)
            ->assertJsonPath('data.mata_pelajaran.0.terbuka', false)
            ->assertJsonPath('data.mata_pelajaran.0.status', 'survei_diperlukan')
            ->assertJsonPath('data.mata_pelajaran.0.nilai_akhir', null)
            ->assertJsonPath('data.mata_pelajaran.0.kkm', null)
            ->assertJsonCount(0, 'data.mata_pelajaran.0.kategori')
            ->assertJsonCount(0, 'data.mata_pelajaran.0.komponen');
    }

    public function test_api_survei_hanya_menyediakan_pertanyaan_aktif_untuk_nilai_yang_dipublikasikan(): void
    {
        $data = $this->dataAkademik();
        $this->publikasikan($data, true);
        $pertanyaanAktif = PertanyaanSurveiPembelajaran::create([
            'kode' => 'media_mobile_uji',
            'pernyataan' => 'Media pembelajaran membantu saya memahami materi.',
            'urutan' => 99,
            'aktif' => true,
        ]);
        $pertanyaanNonaktif = PertanyaanSurveiPembelajaran::create([
            'kode' => 'nonaktif_mobile_uji',
            'pernyataan' => 'Pernyataan nonaktif tidak boleh dikirim.',
            'urutan' => 100,
            'aktif' => false,
        ]);
        $token = $this->token($data['akun_siswa_1']);

        $this->withToken($token)
            ->getJson(route('api.v1.survei-pembelajaran.show', [
                'guruMataPelajaran' => $data['penugasan'],
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertJsonPath('data.guru_mata_pelajaran_id', $data['penugasan']->id)
            ->assertJsonPath('data.pembelajaran.mata_pelajaran.nama', 'Matematika Nilai Saya')
            ->assertJsonPath('data.pembelajaran.kelas.nama', 'VIII.NILAI.SAYA')
            ->assertJsonPath('data.sudah_diisi', false)
            ->assertJsonFragment([
                'kode' => $pertanyaanAktif->kode,
                'pernyataan' => $pertanyaanAktif->pernyataan,
            ])
            ->assertJsonMissing(['kode' => $pertanyaanNonaktif->kode])
            ->assertJsonCount(5, 'data.pilihan');

        $this->withToken($token)
            ->getJson(route('api.v1.survei-pembelajaran.show', [
                'guruMataPelajaran' => $data['penugasan_draf'],
                'semester' => 'ganjil',
            ]))
            ->assertNotFound();
    }

    public function test_siswa_mengirim_survei_native_lalu_nilai_otomatis_terbuka(): void
    {
        $data = $this->dataAkademik();
        $this->publikasikan($data, true);
        $token = $this->token($data['akun_siswa_1']);
        $jawaban = PertanyaanSurveiPembelajaran::aktif()
            ->pluck('kode')
            ->mapWithKeys(fn (string $kode) => [$kode => 5])
            ->all();

        $this->withToken($token)
            ->postJson(route('api.v1.survei-pembelajaran.store', [
                'guruMataPelajaran' => $data['penugasan'],
                'semester' => 'ganjil',
            ]), [
                'jawaban' => [array_key_first($jawaban) => 5],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jawaban');

        $this->withToken($token)
            ->postJson(route('api.v1.survei-pembelajaran.store', [
                'guruMataPelajaran' => $data['penugasan'],
                'semester' => 'ganjil',
            ]), [
                'jawaban' => $jawaban,
                'saran' => '  Pembelajaran sudah sangat baik.  ',
            ])
            ->assertOk()
            ->assertJsonPath('data.sudah_diisi', true)
            ->assertJsonPath('data.baru_dibuat', true);

        $this->assertDatabaseHas('survei_pembelajaran', [
            'guru_mata_pelajaran_id' => $data['penugasan']->id,
            'siswa_id' => $data['siswa_1']->id,
            'semester' => 'ganjil',
            'saran' => 'Pembelajaran sudah sangat baik.',
        ]);

        $this->withToken($token)
            ->getJson(route('api.v1.nilai-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertJsonPath('data.mata_pelajaran.0.terbuka', true)
            ->assertJsonPath('data.mata_pelajaran.0.nilai_akhir', 85);
    }

    private function dataAkademik(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027 Nilai Saya',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.NILAI.SAYA',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $guru = Pegawai::create([
            'nama_lengkap' => 'Guru Nilai Saya',
            'nip' => '198101012026081091',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'MTK-NILAI-SAYA',
            'nama' => 'Matematika Nilai Saya',
            'kelompok' => 'Wajib',
            'kkm' => 75,
            'urutan' => 1,
            'aktif' => true,
        ]);
        $penugasan = $this->buatPenugasan($tahun, $kelas, $mataPelajaran, $guru);
        $siswa1 = $this->buatSiswa('Alya Nilai Saya', '20260091', '0011223391');
        $siswa2 = $this->buatSiswa('Bima Nilai Saya', '20260092', '0011223392');
        $akunSiswa1 = $this->buatAkunSiswa($siswa1, '0011223391');
        $akunSiswa2 = $this->buatAkunSiswa($siswa2, '0011223392');

        foreach ([[$siswa1, 1], [$siswa2, 2]] as [$siswa, $absen]) {
            AnggotaKelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $absen,
                'status_keanggotaan' => 'aktif',
            ]);
        }

        SkemaBobotNilai::create([
            'tahun_pelajaran_id' => $tahun->id,
            'semester' => 'ganjil',
            'tingkat' => 8,
            'bobot_formatif' => 30,
            'bobot_sumatif' => 30,
            'bobot_sts' => 20,
            'bobot_sas_saj' => 20,
            'aktif' => true,
        ]);

        foreach ([
            'formatif' => [80, 17],
            'sumatif' => [90, 18],
            'sts' => [70, 19],
            'sas_saj' => [100, 20],
        ] as $jenis => [$nilai1, $nilai2]) {
            $komponen = KomponenNilai::create([
                'guru_mata_pelajaran_id' => $penugasan->id,
                'semester' => 'ganjil',
                'jenis_komponen' => $jenis,
                'nama' => 'Komponen '.strtoupper($jenis),
                'tanggal_penilaian' => '2026-08-20',
                'urutan' => 1,
                'aktif' => true,
            ]);
            NilaiSiswa::create([
                'komponen_nilai_id' => $komponen->id,
                'siswa_id' => $siswa1->id,
                'nilai' => $nilai1,
            ]);
            NilaiSiswa::create([
                'komponen_nilai_id' => $komponen->id,
                'siswa_id' => $siswa2->id,
                'nilai' => $nilai2,
            ]);
        }

        $mapelDraf = MataPelajaran::create([
            'kode' => 'IPA-DRAF',
            'nama' => 'Ilmu Pengetahuan Alam Draf',
            'kelompok' => 'Wajib',
            'aktif' => true,
        ]);
        $penugasanDraf = $this->buatPenugasan($tahun, $kelas, $mapelDraf, $guru);
        PublikasiNilaiSiswa::create([
            'guru_mata_pelajaran_id' => $penugasanDraf->id,
            'semester' => 'ganjil',
            'dipublikasikan' => false,
        ]);

        return compact('tahun', 'kelas', 'guru', 'penugasan') + [
            'siswa_1' => $siswa1,
            'siswa_2' => $siswa2,
            'akun_siswa_1' => $akunSiswa1,
            'akun_siswa_2' => $akunSiswa2,
            'penugasan_draf' => $penugasanDraf,
        ];
    }

    private function publikasikan(array $data, bool $dipublikasikan): void
    {
        PublikasiNilaiSiswa::create([
            'guru_mata_pelajaran_id' => $data['penugasan']->id,
            'semester' => 'ganjil',
            'dipublikasikan' => $dipublikasikan,
            'dipublikasikan_pada' => $dipublikasikan ? now() : null,
            'dipublikasikan_oleh_pengguna_id' => Pengguna::where('username', 'administrator')->value('id'),
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

    private function buatAkunSiswa(Siswa $siswa, string $username): Pengguna
    {
        return Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'username' => $username,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
