<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiswaWaliSayaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_wali_melihat_siswa_dampingannya_dari_berbagai_kelas(): void
    {
        [$akun, $guruWali, $tahun] = $this->buatAkunGuruWali();
        $kelasTujuh = $this->buatKelas($tahun, 'VII.A', 7);
        $kelasDelapan = $this->buatKelas($tahun, 'VIII.B', 8);
        $siswaTujuh = $this->buatSiswaDalamKelas($kelasTujuh, 'Andi Siswa Wali', '0077000001', 1);
        $siswaDelapan = $this->buatSiswaDalamKelas($kelasDelapan, 'Bella Siswa Wali', '0088000002', 2);
        $siswaLain = $this->buatSiswaDalamKelas($kelasTujuh, 'Citra Bukan Siswa Wali', '0077000003', 3);
        $this->tugaskan($guruWali, $siswaTujuh);
        $this->tugaskan($guruWali, $siswaDelapan);

        $this->actingAs($akun)
            ->get(route('siswa-wali-saya.index'))
            ->assertOk()
            ->assertSee('Andi Siswa Wali')
            ->assertSee('Bella Siswa Wali')
            ->assertSee('VII.A')
            ->assertSee('VIII.B')
            ->assertDontSee($siswaLain->nama_lengkap);

        $this->get(route('siswa-wali-saya.index', ['tingkat' => 7]))
            ->assertOk()
            ->assertSee('Andi Siswa Wali')
            ->assertDontSee('Bella Siswa Wali')
            ->assertDontSee($siswaLain->nama_lengkap);
    }

    public function test_guru_wali_dapat_membuka_detail_siswa_yang_ditugaskan(): void
    {
        [$akun, $guruWali, $tahun] = $this->buatAkunGuruWali();
        $kelas = $this->buatKelas($tahun, 'IX.C', 9);
        $siswa = $this->buatSiswaDalamKelas($kelas, 'Dina Dalam Perwalian', '0099000004', 4);
        $siswa->update([
            'nama_ayah' => 'Ayah Dina',
            'nomor_wa_ayah' => '081234567890',
            'alamat' => 'Padang Panjang',
        ]);
        $this->tugaskan($guruWali, $siswa);

        $this->actingAs($akun)
            ->get(route('siswa-wali-saya.show', $siswa))
            ->assertOk()
            ->assertSee('Dina Dalam Perwalian')
            ->assertSee('0099000004')
            ->assertSee('IX.C')
            ->assertSee('Ayah Dina')
            ->assertSee('081234567890')
            ->assertSee('Padang Panjang');
    }

    public function test_guru_wali_tidak_dapat_membuka_detail_siswa_di_luar_penugasannya(): void
    {
        [$akun, , $tahun] = $this->buatAkunGuruWali();
        $kelas = $this->buatKelas($tahun, 'VII.D', 7);
        $siswa = $this->buatSiswaDalamKelas($kelas, 'Siswa Di Luar Perwalian', '0077000005', 5);

        $this->actingAs($akun)
            ->get(route('siswa-wali-saya.show', $siswa))
            ->assertForbidden();
    }

    private function buatAkunGuruWali(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $guruWali = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Pengujian',
            'nip' => '198909092015091009',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $guruWali->id,
            'nama' => $guruWali->nama_lengkap,
            'username' => $guruWali->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'guru_wali')->firstOrFail());

        return [$akun, $guruWali, $tahun];
    }

    private function buatKelas(TahunPelajaran $tahun, string $nama, int $tingkat): Kelas
    {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => $nama,
            'tingkat' => $tingkat,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
    }

    private function buatSiswaDalamKelas(
        Kelas $kelas,
        string $nama,
        string $nisn,
        int $nomorAbsen,
    ): Siswa {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => '26'.str_pad((string) $nomorAbsen, 5, '0', STR_PAD_LEFT),
            'nisn' => $nisn,
            'jenis_kelamin' => $nomorAbsen % 2 === 0 ? 'P' : 'L',
            'aktif' => true,
        ]);

        AnggotaKelas::create([
            'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $nomorAbsen,
            'status_keanggotaan' => 'aktif',
        ]);

        return $siswa;
    }

    private function tugaskan(Pegawai $guruWali, Siswa $siswa): void
    {
        PenugasanGuruWaliSiswa::create([
            'siswa_id' => $siswa->id,
            'guru_wali_pegawai_id' => $guruWali->id,
            'tanggal_mulai' => '2026-07-15',
            'aktif' => true,
        ]);
    }
}
