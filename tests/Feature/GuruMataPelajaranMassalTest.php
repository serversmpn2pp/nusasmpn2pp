<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PengaturanMataPelajaran;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruMataPelajaranMassalTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dapat_menugaskan_satu_guru_ke_banyak_kelas(): void
    {
        [$administrator, $tahun, $guru, $mapel, $kelas] = $this->dataDasar();

        $this->actingAs($administrator)
            ->post(route('guru-mata-pelajaran.store'), [
                'tahun_pelajaran_id' => $tahun->id,
                'pegawai_id' => $guru->id,
                'mata_pelajaran_id' => $mapel->id,
                'kelas_ids' => $kelas->pluck('id')->all(),
                'jenis_penugasan' => 'pengampu',
                'aktif' => '1',
                'keterangan' => 'Penugasan awal tahun.',
            ])
            ->assertRedirect(route('guru-mata-pelajaran.index', [
                'tahun_pelajaran_id' => $tahun->id,
                'status' => 'aktif',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('guru_mata_pelajaran', 3);

        foreach ($kelas as $item) {
            $this->assertDatabaseHas('guru_mata_pelajaran', [
                'tahun_pelajaran_id' => $tahun->id,
                'pegawai_id' => $guru->id,
                'mata_pelajaran_id' => $mapel->id,
                'kelas_id' => $item->id,
                'jenis_penugasan' => 'pengampu',
                'aktif' => true,
            ]);
        }
    }

    public function test_pengiriman_ulang_memperbarui_tanpa_membuat_duplikasi(): void
    {
        [$administrator, $tahun, $guru, $mapel, $kelas] = $this->dataDasar();
        $kelasDipilih = $kelas->take(2);

        foreach ($kelasDipilih as $item) {
            GuruMataPelajaran::create([
                'tahun_pelajaran_id' => $tahun->id,
                'pegawai_id' => $guru->id,
                'mata_pelajaran_id' => $mapel->id,
                'kelas_id' => $item->id,
                'jenis_penugasan' => 'pengampu',
                'aktif' => false,
            ]);
        }

        $this->actingAs($administrator)
            ->post(route('guru-mata-pelajaran.store'), [
                'tahun_pelajaran_id' => $tahun->id,
                'pegawai_id' => $guru->id,
                'mata_pelajaran_id' => $mapel->id,
                'kelas_ids' => $kelasDipilih->pluck('id')->all(),
                'jenis_penugasan' => 'pendamping',
                'aktif' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('guru_mata_pelajaran', 2);
        $this->assertSame(
            2,
            GuruMataPelajaran::query()
                ->where('jenis_penugasan', 'pendamping')
                ->where('aktif', true)
                ->count(),
        );
    }

    public function test_kelas_di_tingkat_yang_tidak_tersedia_ditolak(): void
    {
        [$administrator, $tahun, $guru, $mapel] = $this->dataDasar();
        $kelasSembilan = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'IX.A',
            'tingkat' => 9,
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->from(route('guru-mata-pelajaran.create'))
            ->post(route('guru-mata-pelajaran.store'), [
                'tahun_pelajaran_id' => $tahun->id,
                'pegawai_id' => $guru->id,
                'mata_pelajaran_id' => $mapel->id,
                'kelas_ids' => [$kelasSembilan->id],
                'jenis_penugasan' => 'pengampu',
                'aktif' => '1',
            ])
            ->assertRedirect(route('guru-mata-pelajaran.create'))
            ->assertSessionHasErrors('mata_pelajaran_id');

        $this->assertDatabaseCount('guru_mata_pelajaran', 0);
    }

    public function test_form_massal_menampilkan_pilihan_kelas_dan_kontrol_pilih_semua(): void
    {
        [$administrator, $tahun] = $this->dataDasar();

        $this->actingAs($administrator)
            ->get(route('guru-mata-pelajaran.create', ['tahun_pelajaran_id' => $tahun->id]))
            ->assertOk()
            ->assertSee('Tambah Penugasan Mengajar')
            ->assertSee('Pilih guru, mata pelajaran, dan satu atau beberapa kelas yang diajar.')
            ->assertSee('Simpan Penugasan')
            ->assertSee('Pilih semua')
            ->assertSeeInOrder([
                'Tingkat VII',
                'VII.A',
                'VII.B',
                'Tingkat VIII',
                'VIII.A',
            ])
            ->assertDontSee('MTK7 / MTK8');
    }

    private function dataDasar(): array
    {
        $administrator = Pengguna::create([
            'nama' => 'Administrator Uji',
            'username' => 'administrator-guru-mapel-uji',
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
        $guru = Pegawai::create([
            'nama_lengkap' => 'Guru Matematika Uji',
            'nip' => '198501012010011001',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $mapel = MataPelajaran::create([
            'nama' => 'Matematika',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);

        foreach ([7 => ['MTK7', 70], 8 => ['MTK8', 73]] as $tingkat => [$kode, $kkm]) {
            PengaturanMataPelajaran::create([
                'tahun_pelajaran_id' => $tahun->id,
                'mata_pelajaran_id' => $mapel->id,
                'tingkat' => $tingkat,
                'kode' => $kode,
                'kkm' => $kkm,
                'aktif' => true,
            ]);
        }

        $kelas = collect([
            Kelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'VII.A',
                'tingkat' => 7,
                'aktif' => true,
            ]),
            Kelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'VII.B',
                'tingkat' => 7,
                'aktif' => true,
            ]),
            Kelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => 'VIII.A',
                'tingkat' => 8,
                'aktif' => true,
            ]),
        ]);

        return [$administrator, $tahun, $guru, $mapel, $kelas];
    }
}
