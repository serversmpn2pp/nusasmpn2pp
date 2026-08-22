<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
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
            ->assertSee('Pusat CBT')
            ->assertDontSee('Jenis Ujian CBT')
            ->assertDontSee('Status Panitia CBT')
            ->assertSee('Pengaturan Presensi Siswa')
            ->assertSee('Peminjaman Barang')
            ->assertSee('Pengajuan Barang')
            ->assertSee('Pengembalian Barang')
            ->assertSee('Akun Siswa')
            ->assertSee('Akun Orang Tua')
            ->assertSee('Role &amp; Hak Akses', false)
            ->assertSee('Aktivitas Login')
            ->assertSee('Backup &amp; Restore', false)
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

    public function test_sidebar_guru_mapel_menampilkan_pusat_cbt(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru CBT',
            'nip' => '198801012020011001',
            'aktif' => true,
        ]);
        $guru = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => 'Guru CBT',
            'username' => '198801012020011001',
            'kata_sandi' => 'secret',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $guru->daftarPeran()->sync([Peran::where('kode', 'guru_mapel')->value('id')]);

        $this->actingAs($guru)
            ->get(route('beranda'))
            ->assertOk()
            ->assertSee('Ujian &amp; Asesmen', false)
            ->assertSee('Pusat CBT');
    }
}
