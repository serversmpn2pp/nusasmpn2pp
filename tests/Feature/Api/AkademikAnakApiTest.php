<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PublikasiNilaiSiswa;
use App\Models\Siswa;
use App\Models\SurveiPembelajaran;
use App\Models\TahunPelajaran;
use App\Services\AkunOrangTuaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkademikAnakApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_orang_tua_hanya_melihat_jadwal_anak_yang_dipilih(): void
    {
        $data = $this->dataAkademik();
        $akun = $data['akun'];

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.akademik-anak.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data.pilihan_siswa')
            ->assertJsonPath('data.tab', 'jadwal')
            ->assertJsonPath('data.siswa_id', $data['anak_utama']->id)
            ->assertJsonPath('data.nilai.kelas.nama', 'VIII.AKADEMIK.A')
            ->assertJsonPath('data.jadwal.ringkasan.jam_terjadwal', 1)
            ->assertJsonPath('data.jadwal.ringkasan.mata_pelajaran', 1)
            ->assertJsonFragment(['label' => 'Matematika Anak'])
            ->assertJsonMissing(['label' => 'Biologi Kelas Lain']);

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.akademik-anak.index', [
                'siswa_id' => $data['anak_kedua']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.siswa_id', $data['anak_kedua']->id)
            ->assertJsonPath('data.nilai.kelas.nama', 'VIII.AKADEMIK.B')
            ->assertJsonFragment(['label' => 'Biologi Kelas Lain'])
            ->assertJsonMissing(['label' => 'Matematika Anak']);

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.akademik-anak.index', [
                'siswa_id' => $data['siswa_lain']->id,
            ]))
            ->assertForbidden();
    }

    public function test_nilai_anak_terkunci_tidak_bocor_dan_terbuka_setelah_survei_anak(): void
    {
        $data = $this->dataAkademik();
        $url = route('api.v1.akademik-anak.index', [
            'tab' => 'nilai',
            'tahun_pelajaran_id' => $data['tahun']->id,
            'semester' => 'ganjil',
        ]);

        $this->withToken($this->token($data['akun']))
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.tab', 'nilai')
            ->assertJsonPath('data.nilai.mata_pelajaran.0.terbuka', false)
            ->assertJsonPath('data.nilai.mata_pelajaran.0.nilai_akhir', null)
            ->assertJsonCount(0, 'data.nilai.mata_pelajaran.0.kategori')
            ->assertJsonCount(0, 'data.nilai.mata_pelajaran.0.komponen')
            ->assertJsonMissing(['nilai' => 88]);

        SurveiPembelajaran::create([
            'guru_mata_pelajaran_id' => $data['penugasan_utama']->id,
            'siswa_id' => $data['anak_utama']->id,
            'semester' => 'ganjil',
            'versi_pertanyaan' => 1,
            'jawaban' => ['kejelasan_materi' => 4],
            'diisi_pada' => now(),
        ]);

        $this->withToken($this->token($data['akun']))
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.nilai.mata_pelajaran.0.terbuka', true)
            ->assertJsonPath('data.nilai.mata_pelajaran.0.komponen.0.nilai', 88);
    }

    public function test_menu_jadwal_dan_nilai_anak_hanya_tersedia_bagi_orang_tua(): void
    {
        $data = $this->dataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($data['akun']))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'jadwal-pelajaran-anak',
                'label' => 'Jadwal Pelajaran Anak Saya',
                'rute' => '/jadwal-pelajaran-anak',
            ])
            ->assertJsonFragment([
                'kode' => 'nilai-anak-saya',
                'label' => 'Nilai Anak Saya',
                'rute' => '/nilai-anak-saya',
            ])
            ->assertJsonMissing(['kode' => 'akademik-anak'])
            ->assertJsonMissing(['kode' => 'nilai-saya']);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonMissing(['kode' => 'jadwal-pelajaran-anak'])
            ->assertJsonMissing(['kode' => 'nilai-anak-saya']);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.akademik-anak.index'))
            ->assertForbidden();
    }

    private function dataAkademik(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2042/2043 Akademik Anak',
            'tanggal_mulai' => '2042-07-01',
            'tanggal_selesai' => '2043-06-30',
            'aktif' => true,
        ]);
        $kelasA = $this->buatKelas($tahun, 'VIII.AKADEMIK.A');
        $kelasB = $this->buatKelas($tahun, 'VIII.AKADEMIK.B');
        $anakUtama = $this->buatSiswa($tahun, $kelasA, 'Alya Akademik Anak', '0011225501', 1);
        $anakKedua = $this->buatSiswa($tahun, $kelasB, 'Bima Akademik Anak', '0011225502', 1);
        $siswaLain = $this->buatSiswa($tahun, $kelasB, 'Siswa Tidak Terhubung', '0011225503', 2);
        $akun = app(AkunOrangTuaService::class)->buat($anakUtama);
        $akun->update(['wajib_ganti_kata_sandi' => false]);
        $akun->orangTuaWali->siswa()->attach($anakKedua->id, [
            'hubungan' => 'anak',
            'utama' => false,
        ]);
        $guru = Pegawai::create([
            'nama_lengkap' => 'Guru Akademik Anak',
            'nip' => '198101012042090001',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $matematika = $this->buatMataPelajaran('MTK-AA', 'Matematika Anak');
        $biologi = $this->buatMataPelajaran('BIO-AA', 'Biologi Kelas Lain');
        $penugasanUtama = $this->buatPenugasan($tahun, $kelasA, $matematika, $guru);
        $penugasanLain = $this->buatPenugasan($tahun, $kelasB, $biologi, $guru);
        $jam = JamPelajaran::create([
            'hari' => 'senin',
            'nomor_jam' => 1,
            'label' => 'Jam 1 Akademik Anak',
            'jam_mulai' => '07:30:00',
            'jam_selesai' => '08:15:00',
            'jenis' => 'pelajaran',
            'aktif' => true,
        ]);
        foreach ([[$kelasA, $penugasanUtama, $matematika], [$kelasB, $penugasanLain, $biologi]] as [$kelas, $penugasan, $mataPelajaran]) {
            JadwalPelajaran::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'hari' => 'senin',
                'jam_pelajaran_id' => $jam->id,
                'guru_mata_pelajaran_id' => $penugasan->id,
                'mata_pelajaran_id' => $mataPelajaran->id,
                'aktif' => true,
            ]);
        }
        $komponen = KomponenNilai::create([
            'guru_mata_pelajaran_id' => $penugasanUtama->id,
            'semester' => 'ganjil',
            'jenis_komponen' => 'formatif',
            'nama' => 'Kuis Rahasia Akademik Anak',
            'tanggal_penilaian' => '2042-08-20',
            'urutan' => 1,
            'aktif' => true,
        ]);
        NilaiSiswa::create([
            'komponen_nilai_id' => $komponen->id,
            'siswa_id' => $anakUtama->id,
            'nilai' => 88,
        ]);
        PublikasiNilaiSiswa::create([
            'guru_mata_pelajaran_id' => $penugasanUtama->id,
            'semester' => 'ganjil',
            'dipublikasikan' => true,
            'dipublikasikan_pada' => now(),
            'dipublikasikan_oleh_pengguna_id' => Pengguna::where('username', 'administrator')->value('id'),
        ]);

        return compact(
            'tahun',
            'anakUtama',
            'anakKedua',
            'siswaLain',
            'akun',
            'penugasanUtama',
        ) + [
            'anak_utama' => $anakUtama,
            'anak_kedua' => $anakKedua,
            'siswa_lain' => $siswaLain,
            'penugasan_utama' => $penugasanUtama,
        ];
    }

    private function buatKelas(TahunPelajaran $tahun, string $nama): Kelas
    {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => $nama,
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
    }

    private function buatSiswa(TahunPelajaran $tahun, Kelas $kelas, string $nama, string $nisn, int $absen): Siswa
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => '42'.$nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $absen,
            'status_keanggotaan' => 'aktif',
        ]);

        return $siswa;
    }

    private function buatMataPelajaran(string $kode, string $nama): MataPelajaran
    {
        return MataPelajaran::create([
            'kode' => $kode,
            'nama' => $nama,
            'kelompok' => 'Wajib',
            'kkm' => 75,
            'urutan' => 1,
            'aktif' => true,
        ]);
    }

    private function buatPenugasan(TahunPelajaran $tahun, Kelas $kelas, MataPelajaran $mataPelajaran, Pegawai $guru): GuruMataPelajaran
    {
        return GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
