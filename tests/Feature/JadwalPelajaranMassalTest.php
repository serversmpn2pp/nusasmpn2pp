<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalPelajaranMassalTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_susun_menampilkan_jadwal_mingguan_dan_mengunci_slot_nonpelajaran(): void
    {
        $data = $this->dataDasar();
        JadwalPelajaran::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas_a']->id,
            'hari' => 'senin',
            'jam_pelajaran_id' => $data['senin_1']->id,
            'guru_mata_pelajaran_id' => $data['penugasan_a_matematika']->id,
            'aktif' => true,
        ]);

        $this->actingAs($data['administrator'])
            ->get(route('jadwal-pelajaran.susun', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas_a']->id,
            ]))
            ->assertOk()
            ->assertSee('Jadwal mingguan VII.A')
            ->assertSee('Senin')
            ->assertSee('Selasa')
            ->assertSee('Upacara')
            ->assertSee('Matematika - Guru Matematika')
            ->assertSee('Bahasa Indonesia - Guru Bahasa Indonesia')
            ->assertSee('name="jadwal['.$data['senin_1']->id.']"', false)
            ->assertDontSee('name="jadwal['.$data['senin_2_upacara']->id.']"', false)
            ->assertViewHas(
                'jadwalTersimpan',
                fn ($jadwal) => (int) $jadwal->get($data['senin_1']->id)?->guru_mata_pelajaran_id
                    === $data['penugasan_a_matematika']->id,
            );
    }

    public function test_jadwal_massal_menyimpan_semua_slot_dan_menonaktifkan_slot_yang_dikosongkan(): void
    {
        $data = $this->dataDasar();
        $jadwalLama = JadwalPelajaran::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas_a']->id,
            'hari' => 'selasa',
            'jam_pelajaran_id' => $data['selasa_2']->id,
            'guru_mata_pelajaran_id' => $data['penugasan_a_matematika']->id,
            'aktif' => true,
        ]);

        $this->actingAs($data['administrator'])
            ->post(route('jadwal-pelajaran.simpan-massal'), [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas_a']->id,
                'jadwal' => [
                    $data['senin_1']->id => $data['penugasan_a_matematika']->id,
                    $data['selasa_1']->id => $data['penugasan_a_bahasa']->id,
                    $data['selasa_2']->id => '',
                ],
            ])
            ->assertRedirect(route('jadwal-pelajaran.susun', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas_a']->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('berhasil');

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'kelas_id' => $data['kelas_a']->id,
            'jam_pelajaran_id' => $data['senin_1']->id,
            'guru_mata_pelajaran_id' => $data['penugasan_a_matematika']->id,
            'aktif' => true,
        ]);
        $this->assertDatabaseHas('jadwal_pelajaran', [
            'kelas_id' => $data['kelas_a']->id,
            'jam_pelajaran_id' => $data['selasa_1']->id,
            'guru_mata_pelajaran_id' => $data['penugasan_a_bahasa']->id,
            'aktif' => true,
        ]);
        $this->assertFalse($jadwalLama->fresh()->aktif);
    }

    public function test_jadwal_massal_menolak_penugasan_dari_kelas_lain(): void
    {
        $data = $this->dataDasar();

        $this->actingAs($data['administrator'])
            ->post(route('jadwal-pelajaran.simpan-massal'), [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas_a']->id,
                'jadwal' => [
                    $data['senin_1']->id => $data['penugasan_b_matematika']->id,
                ],
            ])
            ->assertSessionHasErrors('jadwal');

        $this->assertDatabaseCount('jadwal_pelajaran', 0);
    }

    public function test_kokurikuler_dan_ekstrakurikuler_dapat_dijadwalkan_tanpa_penugasan_guru(): void
    {
        $data = $this->dataDasar();
        $kokurikuler = MataPelajaran::create([
            'nama' => 'Projek Kokurikuler',
            'kelompok' => 'Kokurikuler',
            'aktif' => true,
        ]);
        $ekstrakurikuler = MataPelajaran::create([
            'nama' => 'Pramuka',
            'kelompok' => 'Ekstrakurikuler',
            'aktif' => true,
        ]);

        $this->actingAs($data['administrator'])
            ->get(route('jadwal-pelajaran.susun', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas_a']->id,
            ]))
            ->assertOk()
            ->assertSee('Kokurikuler')
            ->assertSee('Projek Kokurikuler')
            ->assertSee('Ekstrakurikuler')
            ->assertSee('Pramuka');

        $this->actingAs($data['administrator'])
            ->post(route('jadwal-pelajaran.simpan-massal'), [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas_a']->id,
                'jadwal' => [
                    $data['senin_1']->id => 'kegiatan:'.$kokurikuler->id,
                    $data['selasa_1']->id => 'kegiatan:'.$ekstrakurikuler->id,
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'kelas_id' => $data['kelas_a']->id,
            'jam_pelajaran_id' => $data['senin_1']->id,
            'guru_mata_pelajaran_id' => null,
            'mata_pelajaran_id' => $kokurikuler->id,
            'aktif' => true,
        ]);
        $this->assertDatabaseHas('jadwal_pelajaran', [
            'kelas_id' => $data['kelas_a']->id,
            'jam_pelajaran_id' => $data['selasa_1']->id,
            'guru_mata_pelajaran_id' => null,
            'mata_pelajaran_id' => $ekstrakurikuler->id,
            'aktif' => true,
        ]);

        $this->actingAs($data['administrator'])
            ->get(route('jadwal-pelajaran.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas_a']->id,
            ]))
            ->assertOk()
            ->assertSee('Projek Kokurikuler')
            ->assertSee('Pramuka')
            ->assertSee('Kegiatan kelas');
    }

    public function test_bentrok_guru_membatalkan_seluruh_penyimpanan_massal(): void
    {
        $data = $this->dataDasar();
        JadwalPelajaran::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas_b']->id,
            'hari' => 'senin',
            'jam_pelajaran_id' => $data['senin_1']->id,
            'guru_mata_pelajaran_id' => $data['penugasan_b_matematika']->id,
            'aktif' => true,
        ]);

        $this->actingAs($data['administrator'])
            ->post(route('jadwal-pelajaran.simpan-massal'), [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas_a']->id,
                'jadwal' => [
                    $data['senin_1']->id => $data['penugasan_a_matematika']->id,
                    $data['selasa_1']->id => $data['penugasan_a_bahasa']->id,
                ],
            ])
            ->assertSessionHasErrors('jadwal.'.$data['senin_1']->id);

        $this->assertDatabaseMissing('jadwal_pelajaran', [
            'kelas_id' => $data['kelas_a']->id,
        ]);
        $this->assertDatabaseCount('jadwal_pelajaran', 1);
    }

    private function dataDasar(): array
    {
        $administrator = Pengguna::create([
            'nama' => 'Administrator Jadwal Massal',
            'username' => 'administrator-jadwal-massal',
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
        $kelasA = $this->buatKelas($tahun, 'VII.A');
        $kelasB = $this->buatKelas($tahun, 'VII.B');
        $matematika = MataPelajaran::create([
            'nama' => 'Matematika',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);
        $bahasaIndonesia = MataPelajaran::create([
            'nama' => 'Bahasa Indonesia',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);
        $guruMatematika = $this->buatGuru('Guru Matematika', '198001012010011001');
        $guruBahasa = $this->buatGuru('Guru Bahasa Indonesia', '198101012010012002');
        $penugasanAMatematika = $this->buatPenugasan($tahun, $kelasA, $matematika, $guruMatematika);
        $penugasanABahasa = $this->buatPenugasan($tahun, $kelasA, $bahasaIndonesia, $guruBahasa);
        $penugasanBMatematika = $this->buatPenugasan($tahun, $kelasB, $matematika, $guruMatematika);
        $senin1 = $this->buatJam('senin', 1, 'Jam 1', '06:40', '07:20');
        $senin2Upacara = $this->buatJam('senin', 2, 'Upacara', '07:20', '08:00', 'upacara');
        $selasa1 = $this->buatJam('selasa', 1, 'Jam 1', '06:40', '07:20');
        $selasa2 = $this->buatJam('selasa', 2, 'Jam 2', '07:20', '08:00');

        return [
            'administrator' => $administrator,
            'tahun' => $tahun,
            'kelas_a' => $kelasA,
            'kelas_b' => $kelasB,
            'penugasan_a_matematika' => $penugasanAMatematika,
            'penugasan_a_bahasa' => $penugasanABahasa,
            'penugasan_b_matematika' => $penugasanBMatematika,
            'senin_1' => $senin1,
            'senin_2_upacara' => $senin2Upacara,
            'selasa_1' => $selasa1,
            'selasa_2' => $selasa2,
        ];
    }

    private function buatKelas(TahunPelajaran $tahun, string $nama): Kelas
    {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => $nama,
            'tingkat' => 7,
            'aktif' => true,
        ]);
    }

    private function buatGuru(string $nama, string $nip): Pegawai
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

    private function buatJam(
        string $hari,
        int $nomor,
        string $label,
        string $mulai,
        string $selesai,
        string $jenis = 'pelajaran',
    ): JamPelajaran {
        return JamPelajaran::create([
            'hari' => $hari,
            'nomor_jam' => $nomor,
            'label' => $label,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis' => $jenis,
            'aktif' => true,
        ]);
    }
}
