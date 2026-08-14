<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_administrator_disusun_berdasarkan_alur_kerja(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('beranda'))
            ->assertOk()
            ->assertSee('Cari menu...')
            ->assertSeeInOrder([
                'Utama',
                'Data Sekolah',
                'Akademik',
                'Ujian &amp; Asesmen',
                'Kehadiran',
                'Kesiswaan &amp; BK',
                'Sarana Prasarana',
                'Sistem',
            ], false)
            ->assertSeeInOrder([
                'Mata Pelajaran',
                'Guru Mata Pelajaran',
                'Jam Pelajaran',
                'Jadwal Pelajaran',
            ])
            ->assertSee('Penempatan Siswa')
            ->assertSee('Pengaturan Presensi Siswa')
            ->assertSee('Peminjaman Barang')
            ->assertSee('Pengembalian Barang')
            ->assertSee('Akun Siswa')
            ->assertSee('Role &amp; Hak Akses', false)
            ->assertDontSee('Data Master');
    }

    public function test_grup_menu_aktif_dibuka_otomatis(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $response = $this->actingAs($administrator)
            ->get(route('tahun-pelajaran.index'))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/<details\s+(?=[^>]*class="sidebar-section\s+has-active\s*")(?=[^>]*data-sidebar-section-id="data-sekolah")(?=[^>]*\sopen(?:\s|>))[^>]*>/s',
            $response->getContent(),
        );
    }
}
