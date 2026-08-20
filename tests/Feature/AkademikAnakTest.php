<?php

namespace Tests\Feature;

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
use App\Models\SkemaBobotNilai;
use App\Models\SurveiPembelajaran;
use App\Models\TahunPelajaran;
use App\Services\AkunOrangTuaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkademikAnakTest extends TestCase
{
    use RefreshDatabase;

    public function test_orang_tua_hanya_melihat_jadwal_kelas_anak_yang_terhubung(): void
    {
        $data = $this->dataDasar();
        $mapelAnak = $this->buatMataPelajaran('MTK7', 'Matematika');
        $mapelLain = $this->buatMataPelajaran('IPA7', 'Ilmu Pengetahuan Alam Rahasia');
        $guruMapelAnak = $this->buatGuruMapel($data['tahun'], $data['kelas'], $mapelAnak, $data['pegawai']);
        $guruMapelLain = $this->buatGuruMapel($data['tahun'], $data['kelas_lain'], $mapelLain, $data['pegawai']);
        $jam = JamPelajaran::create([
            'hari' => 'senin',
            'nomor_jam' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:40:00',
            'jenis' => 'pelajaran',
            'aktif' => true,
        ]);
        $this->buatJadwal($data['tahun'], $data['kelas'], $guruMapelAnak, $jam);
        $this->buatJadwal($data['tahun'], $data['kelas_lain'], $guruMapelLain, $jam);

        $this->actingAs($data['akun_orang_tua'])
            ->get(route('akademik-anak.index'))
            ->assertOk()
            ->assertViewIs('akademik-anak.index')
            ->assertViewHas('siswa', fn (?Siswa $siswa) => $siswa?->is($data['siswa']) === true)
            ->assertSee('Akademik Anak')
            ->assertSee('Jadwal Pelajaran')
            ->assertSee('Matematika')
            ->assertSee($data['kelas']->nama)
            ->assertDontSee('Ilmu Pengetahuan Alam Rahasia')
            ->assertDontSee($data['kelas_lain']->nama);
    }

    public function test_nilai_orang_tua_tetap_terkunci_sampai_anak_mengisi_survei(): void
    {
        $data = $this->dataDasar();
        $mapel = $this->buatMataPelajaran('BIN7', 'Bahasa Indonesia');
        $guruMapel = $this->buatGuruMapel($data['tahun'], $data['kelas'], $mapel, $data['pegawai']);
        $komponen = KomponenNilai::create([
            'guru_mata_pelajaran_id' => $guruMapel->id,
            'semester' => 'ganjil',
            'jenis_komponen' => 'formatif',
            'nama' => 'Formatif Rahasia Anak',
            'aktif' => true,
        ]);
        NilaiSiswa::create([
            'komponen_nilai_id' => $komponen->id,
            'siswa_id' => $data['siswa']->id,
            'nilai' => 88,
        ]);
        SkemaBobotNilai::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'semester' => 'ganjil',
            'tingkat' => 7,
            'bobot_formatif' => 100,
            'bobot_sumatif' => 0,
            'bobot_sts' => 0,
            'bobot_sas_saj' => 0,
            'aktif' => true,
        ]);
        PublikasiNilaiSiswa::create([
            'guru_mata_pelajaran_id' => $guruMapel->id,
            'semester' => 'ganjil',
            'dipublikasikan' => true,
            'dipublikasikan_pada' => now(),
        ]);

        $this->actingAs($data['akun_orang_tua'])
            ->get(route('akademik-anak.index', ['tab' => 'nilai', 'semester' => 'ganjil']))
            ->assertOk()
            ->assertSee('Bahasa Indonesia')
            ->assertSee('Menunggu survei anak')
            ->assertSee('Nilai akan terbuka setelah anak mengisi survei pembelajaran melalui akun siswa.')
            ->assertDontSee('Formatif Rahasia Anak')
            ->assertDontSee('88,00')
            ->assertDontSee('Isi survei');

        SurveiPembelajaran::create([
            'guru_mata_pelajaran_id' => $guruMapel->id,
            'siswa_id' => $data['siswa']->id,
            'semester' => 'ganjil',
            'versi_pertanyaan' => 1,
            'jawaban' => ['kejelasan_materi' => 4],
            'snapshot_pertanyaan' => [],
            'diisi_pada' => now(),
        ]);

        $this->actingAs($data['akun_orang_tua'])
            ->get(route('akademik-anak.index', ['tab' => 'nilai', 'semester' => 'ganjil']))
            ->assertOk()
            ->assertSee('Formatif Rahasia Anak')
            ->assertSee('88,00')
            ->assertSee('Tuntas')
            ->assertDontSee('Nilai akan terbuka setelah anak mengisi survei pembelajaran melalui akun siswa.');
    }

    public function test_akun_bukan_orang_tua_tidak_dapat_membuka_akademik_anak(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('akademik-anak.index'))
            ->assertForbidden();
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2031/2032',
            'tanggal_mulai' => '2031-07-01',
            'tanggal_selesai' => '2032-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.B',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Anak Orang Tua Akademik',
            'nis' => '310101',
            'nisn' => '0310101001',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'tanggal_masuk' => '2031-07-01',
            'status_keanggotaan' => 'aktif',
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Akademik Anak',
            'nip' => '198001012010011111',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akunOrangTua = app(AkunOrangTuaService::class)->buat($siswa);
        $akunOrangTua->update(['wajib_ganti_kata_sandi' => false]);

        return [
            'tahun' => $tahun,
            'kelas' => $kelas,
            'kelas_lain' => $kelasLain,
            'siswa' => $siswa,
            'pegawai' => $pegawai,
            'akun_orang_tua' => $akunOrangTua,
        ];
    }

    private function buatMataPelajaran(string $kode, string $nama): MataPelajaran
    {
        return MataPelajaran::create([
            'kode' => $kode,
            'nama' => $nama,
            'kelompok' => 'Pelajaran Umum',
            'tingkat' => 7,
            'kkm' => 75,
            'aktif' => true,
        ]);
    }

    private function buatGuruMapel(
        TahunPelajaran $tahun,
        Kelas $kelas,
        MataPelajaran $mataPelajaran,
        Pegawai $pegawai,
    ): GuruMataPelajaran {
        return GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $pegawai->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
    }

    private function buatJadwal(
        TahunPelajaran $tahun,
        Kelas $kelas,
        GuruMataPelajaran $guruMapel,
        JamPelajaran $jam,
    ): void {
        JadwalPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'hari' => 'senin',
            'jam_pelajaran_id' => $jam->id,
            'guru_mata_pelajaran_id' => $guruMapel->id,
            'aktif' => true,
        ]);
    }
}
