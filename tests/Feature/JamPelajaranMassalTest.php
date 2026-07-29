<?php

namespace Tests\Feature;

use App\Models\JamPelajaran;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JamPelajaranMassalTest extends TestCase
{
    use RefreshDatabase;

    public function test_satu_jam_dapat_diterapkan_ke_banyak_hari(): void
    {
        $administrator = $this->buatAdministrator();

        $this->actingAs($administrator)
            ->post(route('jam-pelajaran.store'), [
                'hari' => ['senin', 'selasa', 'rabu', 'kamis', 'jumat'],
                'nomor_jam' => 1,
                'label' => 'Jam 1',
                'jam_mulai' => '06:40',
                'jam_selesai' => '07:20',
                'jenis' => 'pelajaran',
                'aktif' => '1',
            ])
            ->assertRedirect(route('jam-pelajaran.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('berhasil', fn ($pesan) => str_contains($pesan, '5 hari'));

        $this->assertDatabaseCount('jam_pelajaran', 5);

        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat'] as $hari) {
            $this->assertDatabaseHas('jam_pelajaran', [
                'hari' => $hari,
                'nomor_jam' => 1,
                'jam_mulai' => '06:40',
                'jam_selesai' => '07:20',
                'aktif' => true,
            ]);
        }
    }

    public function test_penerapan_ke_hari_yang_sudah_ada_memperbarui_tanpa_mengganti_id(): void
    {
        $administrator = $this->buatAdministrator();
        $senin = $this->buatJam('senin', '07:00', '07:40');
        $selasa = $this->buatJam('selasa', '07:10', '07:50');
        $idSelasa = $selasa->id;

        $this->actingAs($administrator)
            ->put(route('jam-pelajaran.update', $senin), [
                'hari' => 'senin',
                'hari_tujuan' => ['selasa'],
                'nomor_jam' => 1,
                'label' => 'Jam Pertama',
                'jam_mulai' => '06:40',
                'jam_selesai' => '07:20',
                'jenis' => 'pelajaran',
                'aktif' => '1',
                'keterangan' => 'Berlaku Senin dan Selasa.',
            ])
            ->assertRedirect(route('jam-pelajaran.show', $senin))
            ->assertSessionHasNoErrors();

        $this->assertSame($idSelasa, JamPelajaran::where('hari', 'selasa')->where('nomor_jam', 1)->value('id'));

        foreach (['senin', 'selasa'] as $hari) {
            $this->assertDatabaseHas('jam_pelajaran', [
                'hari' => $hari,
                'nomor_jam' => 1,
                'label' => 'Jam Pertama',
                'jam_mulai' => '06:40',
                'jam_selesai' => '07:20',
                'keterangan' => 'Berlaku Senin dan Selasa.',
            ]);
        }
    }

    public function test_jam_baru_dapat_disisipkan_dan_menggeser_urutan_berikutnya_di_banyak_hari(): void
    {
        $administrator = $this->buatAdministrator();
        $seninJamTiga = null;
        $selasaJamTiga = null;

        foreach (['senin', 'selasa'] as $hari) {
            $this->buatJamBernomor($hari, 1, 'Jam ke-1', '06:40', '07:20');
            $this->buatJamBernomor($hari, 2, 'Jam ke-2', '07:20', '08:00');
            $jamTiga = $this->buatJamBernomor($hari, 3, 'Jam ke-3', '08:00', '08:40');

            if ($hari === 'senin') {
                $seninJamTiga = $jamTiga;
            } else {
                $selasaJamTiga = $jamTiga;
            }
        }

        $this->actingAs($administrator)
            ->post(route('jam-pelajaran.store'), [
                'hari' => ['senin', 'selasa'],
                'posisi_sisip' => 'setelah:2',
                'label' => 'Literasi Pagi',
                'jam_mulai' => '08:00',
                'jam_selesai' => '08:15',
                'jenis' => 'lainnya',
                'aktif' => '1',
            ])
            ->assertRedirect(route('jam-pelajaran.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('berhasil', fn ($pesan) => (
                str_contains($pesan, '2 hari')
                && str_contains($pesan, '2 slot berikutnya digeser otomatis')
            ));

        foreach (['senin', 'selasa'] as $hari) {
            $this->assertDatabaseHas('jam_pelajaran', [
                'hari' => $hari,
                'nomor_jam' => 3,
                'label' => 'Literasi Pagi',
            ]);
            $this->assertDatabaseHas('jam_pelajaran', [
                'hari' => $hari,
                'nomor_jam' => 4,
                'label' => 'Jam ke-4',
            ]);
        }

        $this->assertSame(4, $seninJamTiga->fresh()->nomor_jam);
        $this->assertSame(4, $selasaJamTiga->fresh()->nomor_jam);
        $this->assertDatabaseCount('jam_pelajaran', 8);
    }

    public function test_posisi_akhir_menambahkan_slot_tanpa_mengubah_urutan_lama(): void
    {
        $administrator = $this->buatAdministrator();
        $jamPertama = $this->buatJamBernomor('senin', 1, 'Jam 1', '06:40', '07:20');

        $this->actingAs($administrator)
            ->post(route('jam-pelajaran.store'), [
                'hari' => ['senin'],
                'posisi_sisip' => 'akhir',
                'label' => 'Jam 2',
                'jam_mulai' => '07:20',
                'jam_selesai' => '08:00',
                'jenis' => 'pelajaran',
                'aktif' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $jamPertama->fresh()->nomor_jam);
        $this->assertDatabaseHas('jam_pelajaran', [
            'hari' => 'senin',
            'nomor_jam' => 2,
            'label' => 'Jam 2',
        ]);
    }

    public function test_edit_dapat_memindahkan_slot_ke_urutan_baru_pada_banyak_hari(): void
    {
        $administrator = $this->buatAdministrator();
        $slotDipindahkan = [];
        $slotKetiga = [];

        foreach (['senin', 'selasa'] as $hari) {
            $this->buatJamBernomor($hari, 1, 'Jam ke-1', '06:40', '07:20');
            $slotDipindahkan[$hari] = $this->buatJamBernomor($hari, 2, 'Jam ke-2', '07:20', '08:00');
            $slotKetiga[$hari] = $this->buatJamBernomor($hari, 3, 'Jam ke-3', '08:00', '08:40');
            $this->buatJamBernomor($hari, 4, 'Jam ke-4', '08:40', '09:20');
        }

        $this->actingAs($administrator)
            ->put(route('jam-pelajaran.update', $slotDipindahkan['senin']), [
                'hari' => 'senin',
                'hari_tujuan' => ['selasa'],
                'nomor_jam' => 2,
                'posisi_pindah' => 'urutan:4',
                'label' => 'Jam ke-2',
                'jam_mulai' => '07:20',
                'jam_selesai' => '08:00',
                'jenis' => 'pelajaran',
                'aktif' => '1',
            ])
            ->assertRedirect(route('jam-pelajaran.show', $slotDipindahkan['senin']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('berhasil', fn ($pesan) => str_contains($pesan, '2 dipindahkan'));

        foreach (['senin', 'selasa'] as $hari) {
            $this->assertSame(4, $slotDipindahkan[$hari]->fresh()->nomor_jam);
            $this->assertSame('Jam ke-4', $slotDipindahkan[$hari]->fresh()->label);
            $this->assertSame(2, $slotKetiga[$hari]->fresh()->nomor_jam);
            $this->assertSame('Jam ke-2', $slotKetiga[$hari]->fresh()->label);
        }

        $this->assertDatabaseCount('jam_pelajaran', 8);
    }

    public function test_edit_dapat_memindahkan_slot_ke_urutan_lebih_awal(): void
    {
        $administrator = $this->buatAdministrator();
        $jamPertama = $this->buatJamBernomor('senin', 1, 'Jam 1', '06:40', '07:20');
        $jamKedua = $this->buatJamBernomor('senin', 2, 'Jam 2', '07:20', '08:00');
        $jamKetiga = $this->buatJamBernomor('senin', 3, 'Jam 3', '08:00', '08:40');

        $this->actingAs($administrator)
            ->put(route('jam-pelajaran.update', $jamKetiga), [
                'hari' => 'senin',
                'nomor_jam' => 3,
                'posisi_pindah' => 'urutan:1',
                'label' => 'Jam 3',
                'jam_mulai' => '08:00',
                'jam_selesai' => '08:40',
                'jenis' => 'pelajaran',
                'aktif' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $jamKetiga->fresh()->nomor_jam);
        $this->assertSame('Jam 1', $jamKetiga->fresh()->label);
        $this->assertSame(2, $jamPertama->fresh()->nomor_jam);
        $this->assertSame(3, $jamKedua->fresh()->nomor_jam);
    }

    public function test_form_menampilkan_pilihan_hari_berlaku_dan_hari_tujuan(): void
    {
        $administrator = $this->buatAdministrator();
        $senin = $this->buatJam('senin', '06:40', '07:20');
        $this->buatJamBernomor('senin', 2, 'Jam 2', '07:20', '08:00');

        $this->actingAs($administrator)
            ->get(route('jam-pelajaran.create'))
            ->assertOk()
            ->assertSee('Hari berlaku')
            ->assertSee('Posisi jam baru')
            ->assertSee('Setelah urutan 1')
            ->assertSee('Senin')
            ->assertSee('Jumat')
            ->assertSee('Terapkan Jam');

        $this->actingAs($administrator)
            ->get(route('jam-pelajaran.edit', $senin))
            ->assertOk()
            ->assertSee('Hari utama')
            ->assertSee('Terapkan juga ke hari lain')
            ->assertSee('Posisi jam')
            ->assertSee('Pindahkan menjadi urutan')
            ->assertSee('Simpan dan Terapkan');
    }

    public function test_tambah_jam_memerlukan_minimal_satu_hari(): void
    {
        $administrator = $this->buatAdministrator();

        $this->actingAs($administrator)
            ->post(route('jam-pelajaran.store'), [
                'hari' => [],
                'nomor_jam' => 1,
                'jam_mulai' => '06:40',
                'jam_selesai' => '07:20',
                'jenis' => 'pelajaran',
                'aktif' => '1',
            ])
            ->assertSessionHasErrors('hari');

        $this->assertDatabaseCount('jam_pelajaran', 0);
    }

    private function buatAdministrator(): Pengguna
    {
        return Pengguna::create([
            'nama' => 'Administrator Jam Uji',
            'username' => 'administrator-jam-uji-' . str()->random(6),
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'administrator',
            'aktif' => true,
            'akun_sistem' => true,
        ]);
    }

    private function buatJam(string $hari, string $mulai, string $selesai): JamPelajaran
    {
        return $this->buatJamBernomor($hari, 1, 'Jam 1', $mulai, $selesai);
    }

    private function buatJamBernomor(
        string $hari,
        int $nomor,
        string $label,
        string $mulai,
        string $selesai,
    ): JamPelajaran {
        return JamPelajaran::create([
            'hari' => $hari,
            'nomor_jam' => $nomor,
            'label' => $label,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis' => 'pelajaran',
            'aktif' => true,
        ]);
    }
}
