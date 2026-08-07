<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PertanyaanSurveiPembelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PertanyaanSurveiPembelajaranTest extends TestCase
{
    use RefreshDatabase;

    public function test_enam_pernyataan_awal_tersedia_dan_wakil_kurikulum_memiliki_izin_kelola(): void
    {
        $this->assertSame(6, PertanyaanSurveiPembelajaran::aktif()->count());
        $this->assertTrue(
            Peran::where('kode', 'wakil_pimpinan_kurikulum')
                ->firstOrFail()
                ->memilikiIzin('survei.pertanyaan_kelola'),
        );
    }

    public function test_wakil_kurikulum_dapat_menambah_dan_mengubah_pernyataan(): void
    {
        $wakil = $this->buatPenggunaDenganPeran('wakil_pimpinan_kurikulum', 'wakil-kurikulum-survei');

        $this->actingAs($wakil)
            ->post(route('pertanyaan-survei-pembelajaran.store'), [
                'pernyataan' => 'Guru menggunakan media pembelajaran yang membantu pemahaman saya.',
                'urutan' => 7,
                'aktif' => 1,
            ])
            ->assertRedirect(route('pertanyaan-survei-pembelajaran.index'))
            ->assertSessionHasNoErrors();

        $pertanyaan = PertanyaanSurveiPembelajaran::where('urutan', 7)->firstOrFail();
        $kodeAwal = $pertanyaan->kode;

        $this->actingAs($wakil)
            ->put(route('pertanyaan-survei-pembelajaran.update', $pertanyaan), [
                'pernyataan' => 'Guru menggunakan media pembelajaran yang sesuai dengan materi.',
                'urutan' => 3,
            ])
            ->assertRedirect(route('pertanyaan-survei-pembelajaran.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pertanyaan_survei_pembelajaran', [
            'id' => $pertanyaan->id,
            'kode' => $kodeAwal,
            'pernyataan' => 'Guru menggunakan media pembelajaran yang sesuai dengan materi.',
            'urutan' => 3,
            'aktif' => true,
        ]);
    }

    public function test_pengguna_biasa_tidak_dapat_mengelola_pernyataan_survei(): void
    {
        $pegawai = $this->buatPenggunaDenganPeran('pegawai', 'pegawai-survei');

        $this->actingAs($pegawai)
            ->get(route('pertanyaan-survei-pembelajaran.index'))
            ->assertForbidden();
    }

    public function test_minimal_satu_pernyataan_harus_tetap_aktif(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pertanyaan = PertanyaanSurveiPembelajaran::firstOrFail();
        PertanyaanSurveiPembelajaran::whereKeyNot($pertanyaan->id)->update(['aktif' => false]);

        $this->actingAs($administrator)
            ->patch(route('pertanyaan-survei-pembelajaran.status', $pertanyaan))
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertTrue($pertanyaan->fresh()->aktif);
    }

    private function buatPenggunaDenganPeran(string $kodePeran, string $username): Pengguna
    {
        $pengguna = Pengguna::create([
            'nama' => str($kodePeran)->replace('_', ' ')->title()->toString(),
            'username' => $username,
            'kata_sandi' => Hash::make('kata-sandi-uji'),
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', $kodePeran)->value('id'));

        return $pengguna;
    }
}
