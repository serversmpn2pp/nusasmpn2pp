<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputNilaiPredikatTest extends TestCase
{
    use RefreshDatabase;

    public function test_input_nilai_pengembangan_diri_memakai_predikat_sb_b_c_atau_k(): void
    {
        [$administrator, $komponen, $siswa] = $this->dataDasar();

        $this->actingAs($administrator)
            ->get(route('input-nilai.index', ['komponen_nilai_id' => $komponen->id]))
            ->assertOk()
            ->assertSee('Predikat (SB/B/C/K)')
            ->assertSee('name="predikat['.$siswa->id.']"', false)
            ->assertSee('Sangat Baik', false);

        $this->actingAs($administrator)
            ->post(route('input-nilai.store'), [
                'komponen_nilai_id' => $komponen->id,
                'predikat' => [$siswa->id => 'SB'],
                'catatan' => [$siswa->id => 'Aktif dan bertanggung jawab.'],
            ])
            ->assertRedirect(route('input-nilai.index', [
                'komponen_nilai_id' => $komponen->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nilai_siswa', [
            'komponen_nilai_id' => $komponen->id,
            'siswa_id' => $siswa->id,
            'nilai' => null,
            'predikat' => 'SB',
            'catatan' => 'Aktif dan bertanggung jawab.',
        ]);
    }

    public function test_predikat_di_luar_pilihan_ditolak(): void
    {
        [$administrator, $komponen, $siswa] = $this->dataDasar();

        $this->actingAs($administrator)
            ->from(route('input-nilai.index', ['komponen_nilai_id' => $komponen->id]))
            ->post(route('input-nilai.store'), [
                'komponen_nilai_id' => $komponen->id,
                'predikat' => [$siswa->id => 'A'],
            ])
            ->assertSessionHasErrors('predikat.'.$siswa->id);

        $this->assertDatabaseMissing('nilai_siswa', [
            'komponen_nilai_id' => $komponen->id,
            'siswa_id' => $siswa->id,
        ]);
    }

    private function dataDasar(): array
    {
        $administrator = Pengguna::create([
            'nama' => 'Administrator Uji',
            'username' => 'administrator-uji',
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'administrator',
            'aktif' => true,
            'akun_sistem' => true,
        ]);
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Pramuka',
            'nip' => '198001012006041001',
            'aktif' => true,
        ]);
        $mataPelajaran = MataPelajaran::create([
            'nama' => 'Pramuka',
            'kelompok' => 'Ekstrakurikuler',
            'aktif' => true,
        ]);
        $guruMataPelajaran = GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $pegawai->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
        $komponen = KomponenNilai::create([
            'guru_mata_pelajaran_id' => $guruMataPelajaran->id,
            'semester' => 'ganjil',
            'jenis_komponen' => 'formatif',
            'nama' => 'Penilaian Semester',
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Aulia Rahma',
            'nis' => '26001',
            'nisn' => '0123456789',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);

        return [$administrator, $komponen, $siswa];
    }
}
