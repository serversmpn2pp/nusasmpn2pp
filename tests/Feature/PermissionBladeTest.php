<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PermissionBladeTest extends TestCase
{
    public function test_directive_izin_menyembunyikan_aksi_yang_tidak_dimiliki_pengguna(): void
    {
        $pengguna = new Pengguna([
            'nama' => 'Pengguna Lihat Siswa',
            'username' => 'lihat_siswa',
            'kata_sandi' => 'password',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);

        $peran = new Peran([
            'nama' => 'Pembaca Siswa',
            'kode' => 'pembaca_siswa',
            'aktif' => true,
        ]);

        $izinLihat = new Izin([
            'nama' => 'Lihat Siswa',
            'kode' => 'siswa.lihat',
            'kelompok' => 'Siswa',
            'aktif' => true,
        ]);

        $peran->setRelation('izin', collect([$izinLihat]));
        $pengguna->setRelation('daftarPeran', collect([$peran]));

        $this->actingAs($pengguna);

        $html = Blade::render(<<<'BLADE'
            @izin('siswa.kelola')
                <button>Tambah siswa</button>
            @endizin

            @izin('siswa.lihat')
                <a>Lihat siswa</a>
            @endizin
        BLADE);

        $this->assertStringContainsString('Lihat siswa', $html);
        $this->assertStringNotContainsString('Tambah siswa', $html);
    }
}
