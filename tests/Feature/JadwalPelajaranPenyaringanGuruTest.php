<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalPelajaranPenyaringanGuruTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_menyediakan_metadata_untuk_menyaring_guru_sesuai_kelas(): void
    {
        $data = $this->dataDasar();

        $this->actingAs($data['administrator'])
            ->get(route('jadwal-pelajaran.create', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas_a']->id,
            ]))
            ->assertOk()
            ->assertSee('data-class="' . $data['kelas_a']->id . '"', false)
            ->assertSee('data-class="' . $data['kelas_b']->id . '"', false)
            ->assertSee($data['guru_a']->nama_lengkap)
            ->assertSee($data['guru_b']->nama_lengkap)
            ->assertDontSee($data['guru_nonaktif']->nama_lengkap)
            ->assertDontSee($data['guru_pendamping']->nama_lengkap)
            ->assertSee('Pilih kelas untuk menampilkan pelajaran, kokurikuler, dan ekstrakurikuler yang tersedia.');
    }

    public function test_jadwal_menolak_guru_pengampu_dari_kelas_lain(): void
    {
        $data = $this->dataDasar();

        $this->actingAs($data['administrator'])
            ->from(route('jadwal-pelajaran.create'))
            ->post(route('jadwal-pelajaran.store'), [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas_a']->id,
                'hari' => 'senin',
                'jam_pelajaran_id' => $data['jam']->id,
                'guru_mata_pelajaran_id' => $data['penugasan_b']->id,
                'aktif' => '1',
            ])
            ->assertRedirect(route('jadwal-pelajaran.create'))
            ->assertSessionHasErrors('guru_mata_pelajaran_id');

        $this->assertDatabaseCount('jadwal_pelajaran', 0);
    }

    public function test_jadwal_menolak_penugasan_nonaktif_atau_bukan_pengampu(): void
    {
        $data = $this->dataDasar();

        foreach ([$data['penugasan_nonaktif'], $data['penugasan_pendamping']] as $penugasan) {
            $this->actingAs($data['administrator'])
                ->post(route('jadwal-pelajaran.store'), [
                    'tahun_pelajaran_id' => $data['tahun']->id,
                    'kelas_id' => $data['kelas_a']->id,
                    'hari' => 'senin',
                    'jam_pelajaran_id' => $data['jam']->id,
                    'guru_mata_pelajaran_id' => $penugasan->id,
                    'aktif' => '1',
                ])
                ->assertSessionHasErrors('guru_mata_pelajaran_id');
        }

        $this->assertDatabaseCount('jadwal_pelajaran', 0);
    }

    public function test_jadwal_dapat_disimpan_dengan_pengampu_aktif_di_kelas_yang_sama(): void
    {
        $data = $this->dataDasar();

        $this->actingAs($data['administrator'])
            ->post(route('jadwal-pelajaran.store'), [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas_a']->id,
                'hari' => 'senin',
                'jam_pelajaran_id' => $data['jam']->id,
                'guru_mata_pelajaran_id' => $data['penugasan_a']->id,
                'aktif' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'kelas_id' => $data['kelas_a']->id,
            'guru_mata_pelajaran_id' => $data['penugasan_a']->id,
            'jam_pelajaran_id' => $data['jam']->id,
        ]);
    }

    private function dataDasar(): array
    {
        $administrator = Pengguna::create([
            'nama' => 'Administrator Jadwal Uji',
            'username' => 'administrator-jadwal-uji',
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
        $kelasA = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $kelasB = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.B',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $mapel = MataPelajaran::create([
            'nama' => 'Matematika',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);
        $guruA = $this->buatGuru('Guru Kelas VII A', '198001012010011001');
        $guruB = $this->buatGuru('Guru Kelas VII B', '198101012010012002');
        $guruNonaktif = $this->buatGuru('Guru Penugasan Nonaktif', '198201012010013003');
        $guruPendamping = $this->buatGuru('Guru Pendamping', '198301012010014004');
        $penugasanA = $this->buatPenugasan($tahun, $kelasA, $mapel, $guruA);
        $penugasanB = $this->buatPenugasan($tahun, $kelasB, $mapel, $guruB);
        $penugasanNonaktif = $this->buatPenugasan(
            $tahun,
            $kelasA,
            $mapel,
            $guruNonaktif,
            'pengampu',
            false,
        );
        $penugasanPendamping = $this->buatPenugasan(
            $tahun,
            $kelasA,
            $mapel,
            $guruPendamping,
            'pendamping',
        );
        $jam = JamPelajaran::create([
            'hari' => 'senin',
            'nomor_jam' => 1,
            'label' => 'Jam 1',
            'jam_mulai' => '06:40',
            'jam_selesai' => '07:20',
            'jenis' => 'pelajaran',
            'aktif' => true,
        ]);

        return [
            'administrator' => $administrator,
            'tahun' => $tahun,
            'kelas_a' => $kelasA,
            'kelas_b' => $kelasB,
            'guru_a' => $guruA,
            'guru_b' => $guruB,
            'guru_nonaktif' => $guruNonaktif,
            'guru_pendamping' => $guruPendamping,
            'penugasan_a' => $penugasanA,
            'penugasan_b' => $penugasanB,
            'penugasan_nonaktif' => $penugasanNonaktif,
            'penugasan_pendamping' => $penugasanPendamping,
            'jam' => $jam,
        ];
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
        MataPelajaran $mapel,
        Pegawai $guru,
        string $jenis = 'pengampu',
        bool $aktif = true,
    ): GuruMataPelajaran {
        return GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => $jenis,
            'aktif' => $aktif,
        ]);
    }
}
