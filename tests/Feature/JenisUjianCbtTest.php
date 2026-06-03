<?php

namespace Tests\Feature;

use App\Models\JenisUjianCbt;
use App\Models\Pengguna;
use PDO;
use Tests\TestCase;

class JenisUjianCbtTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite belum aktif pada PHP lokal.');
        }

        $this->artisan('migrate:fresh');
    }

    public function test_administrator_dapat_mengelola_jenis_ujian_cbt(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('jenis-ujian-cbt.index'))
            ->assertOk()
            ->assertSee('Jenis ujian CBT')
            ->assertSee('Tambah jenis ujian');

        $this->actingAs($administrator)
            ->post(route('jenis-ujian-cbt.store'), [
                'nama' => 'Ujian Praktik Informatika',
                'kode' => 'praktik informatika',
                'deskripsi' => 'Ujian praktik berbasis CBT.',
                'memerlukan_token' => '1',
                'dapat_diterapkan_ke_nilai' => '1',
                'tampil_di_kartu_peserta' => '1',
                'urutan' => 1,
                'aktif' => '1',
            ])
            ->assertRedirect();

        $jenisUjianCbt = JenisUjianCbt::where('kode', 'PRAKTIK_INFORMATIKA')->firstOrFail();
        $this->assertDatabaseHas('jenis_ujian_cbt', [
            'id' => $jenisUjianCbt->id,
            'nama' => 'Ujian Praktik Informatika',
            'kode' => 'PRAKTIK_INFORMATIKA',
            'memerlukan_token' => true,
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->get(route('jenis-ujian-cbt.show', $jenisUjianCbt))
            ->assertOk()
            ->assertSee('Ujian Praktik Informatika')
            ->assertSee('Memerlukan token proktor');

        $this->actingAs($administrator)
            ->put(route('jenis-ujian-cbt.update', $jenisUjianCbt), [
                'nama' => 'Simulasi Praktik Informatika',
                'kode' => 'simulasi praktik',
                'deskripsi' => 'Latihan ujian berbasis literasi dan numerasi.',
                'memerlukan_token' => '0',
                'dapat_diterapkan_ke_nilai' => '0',
                'tampil_di_kartu_peserta' => '1',
                'urutan' => 5,
                'aktif' => '1',
            ])
            ->assertRedirect(route('jenis-ujian-cbt.show', $jenisUjianCbt));

        $this->assertDatabaseHas('jenis_ujian_cbt', [
            'id' => $jenisUjianCbt->id,
            'nama' => 'Simulasi Praktik Informatika',
            'kode' => 'SIMULASI_PRAKTIK',
            'memerlukan_token' => false,
            'dapat_diterapkan_ke_nilai' => false,
            'urutan' => 5,
        ]);

        $this->actingAs($administrator)
            ->delete(route('jenis-ujian-cbt.destroy', $jenisUjianCbt))
            ->assertRedirect(route('jenis-ujian-cbt.index'));

        $this->assertFalse($jenisUjianCbt->fresh()->aktif);
    }
}
