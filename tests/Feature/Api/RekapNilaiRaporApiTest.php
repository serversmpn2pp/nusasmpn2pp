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
use App\Models\Siswa;
use App\Models\SkemaBobotNilai;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapNilaiRaporApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_memerlukan_token_dan_izin_rekap_nilai(): void
    {
        $this->getJson(route('api.v1.rekap-nilai-rapor.index'))->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Tanpa Izin Rekap',
            'username' => 'tanpa.izin.rekap',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.rekap-nilai-rapor.index'))
            ->assertForbidden();
    }

    public function test_rekap_menghitung_kelengkapan_rata_rata_dan_nilai_akhir(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $data = $this->dataAkademik();

        SkemaBobotNilai::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'semester' => 'ganjil',
            'tingkat' => 8,
            'bobot_formatif' => 30,
            'bobot_sumatif' => 30,
            'bobot_sts' => 20,
            'bobot_sas_saj' => 20,
            'aktif' => true,
        ]);

        $nilaiSiswaPertama = [
            'formatif' => 80,
            'sumatif' => 90,
            'sts' => 70,
            'sas_saj' => 100,
        ];

        foreach ($nilaiSiswaPertama as $jenis => $nilai) {
            $komponen = KomponenNilai::create([
                'guru_mata_pelajaran_id' => $data['penugasan']->id,
                'semester' => 'ganjil',
                'jenis_komponen' => $jenis,
                'nama' => 'Komponen '.strtoupper($jenis),
                'urutan' => 1,
                'aktif' => true,
            ]);
            NilaiSiswa::create([
                'komponen_nilai_id' => $komponen->id,
                'siswa_id' => $data['siswa_1']->id,
                'nilai' => $nilai,
            ]);
        }

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.rekap-nilai-rapor.index', [
                'guru_mata_pelajaran_id' => $data['penugasan']->id,
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.guru_mata_pelajaran_id', $data['penugasan']->id)
            ->assertJsonPath('data.guru_mata_pelajaran_dipilih.kelas.nama', 'VIII.REKAP')
            ->assertJsonPath('data.label_nilai_akhir', 'SAS')
            ->assertJsonPath('data.skema.bobot.formatif', 30)
            ->assertJsonPath('data.kategori.0.jumlah_komponen', 1)
            ->assertJsonPath('data.ringkasan.jumlah_siswa', 2)
            ->assertJsonPath('data.ringkasan.jumlah_lengkap', 1)
            ->assertJsonPath('data.ringkasan.jumlah_belum_lengkap', 1)
            ->assertJsonPath('data.ringkasan.rata_rata_akhir', 85)
            ->assertJsonPath('data.siswa.0.siswa.nama', 'Alya Rekap Mobile')
            ->assertJsonPath('data.siswa.0.kategori.formatif.rata', 80)
            ->assertJsonPath('data.siswa.0.nilai_akhir', 85)
            ->assertJsonPath('data.siswa.0.lengkap', true)
            ->assertJsonPath('data.siswa.1.nilai_akhir', null)
            ->assertJsonPath('data.siswa.1.status', 'Nilai belum lengkap')
            ->assertJsonCount(0, 'data.peringatan');
    }

    public function test_rekap_memberi_peringatan_saat_skema_dan_komponen_belum_ada(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $data = $this->dataAkademik();

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.rekap-nilai-rapor.index', [
                'guru_mata_pelajaran_id' => $data['penugasan']->id,
                'semester' => 'genap',
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.jumlah_lengkap', 0)
            ->assertJsonPath('data.siswa.0.status', 'Skema belum ada')
            ->assertJsonCount(2, 'data.peringatan');
    }

    private function dataAkademik(): array
    {
        $tahun = TahunPelajaran::create(['nama' => '2026/2027 Rekap', 'aktif' => true]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.REKAP',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $guru = Pegawai::create([
            'nama_lengkap' => 'Guru Rekap Mobile',
            'nip' => '198101012026081081',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'MTK-REKAP',
            'nama' => 'Matematika Rekap',
            'kelompok' => 'Wajib',
            'aktif' => true,
        ]);
        $penugasan = GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
        $siswa1 = $this->buatSiswa('Alya Rekap Mobile', '20260081', '0011223381');
        $siswa2 = $this->buatSiswa('Bima Rekap Mobile', '20260082', '0011223382');

        foreach ([[$siswa1, 1], [$siswa2, 2]] as [$siswa, $absen]) {
            AnggotaKelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $absen,
                'status_keanggotaan' => 'aktif',
            ]);
        }

        return compact('tahun', 'kelas', 'guru', 'penugasan') + [
            'siswa_1' => $siswa1,
            'siswa_2' => $siswa2,
        ];
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
